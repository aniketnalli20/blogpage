<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

header('content-type: application/json; charset=utf-8');

function lb_json(array $payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function lb_base_url(): string {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/');
    $dir = preg_replace('#/api$#', '', $dir);
    $dir = $dir === '' ? '' : $dir;
    return $scheme . '://' . $host . $dir . '/';
}

function lb_is_valid_url(string $url): bool {
    $url = trim($url);
    if ($url === '' || preg_match('/\s/', $url)) return false;
    if (strlen($url) > 2048) return false;
    if (preg_match('#^https?://#i', $url)) return filter_var($url, FILTER_VALIDATE_URL) !== false;
    if (preg_match('#^mailto:#i', $url)) return preg_match('/^mailto:[^\s@]+@[^\s@]+\.[^\s@]+$/i', $url) === 1;
    if (preg_match('#^tel:#i', $url)) return preg_match('/^tel:\+?[0-9 ()-]{6,}$/', $url) === 1;
    return false;
}

function lb_days_to_expires_at(?int $days): ?string {
    if ($days === null) return null;
    if ($days === 0) return null;
    $d = max(1, min(365, $days));
    return gmdate('Y-m-d H:i:s', time() + ($d * 86400));
}

function lb_code_from_hash(string $url): string {
    $salt = random_bytes(16);
    $hash = hash('sha256', $url . '|' . bin2hex($salt) . '|' . (string) microtime(true), true);
    $b64 = rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');
    return substr($b64, 0, 10);
}

function lb_ensure_tables(PDO $pdo): void {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS short_links (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            code VARCHAR(16) NOT NULL,
            url TEXT NOT NULL,
            created_at DATETIME NOT NULL,
            expires_at DATETIME NULL,
            clicks INT UNSIGNED NOT NULL DEFAULT 0,
            last_clicked_at DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_code (code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;'
    );
}

try {
    $pdo = lb_get_pdo();
    lb_ensure_tables($pdo);
} catch (Throwable $e) {
    lb_json(['ok' => false, 'error' => 'server', 'message' => 'Short link service unavailable.'], 500);
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$action = '';

if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $body = json_decode($raw ?: '[]', true);
    $action = is_array($body) ? (string) ($body['action'] ?? '') : '';
} else {
    $action = (string) ($_GET['action'] ?? '');
}

if ($action === 'create' && $method === 'POST') {
    $raw = file_get_contents('php://input');
    $body = json_decode($raw ?: '[]', true);
    if (!is_array($body)) lb_json(['ok' => false, 'error' => 'bad_request'], 400);

    $url = trim((string) ($body['url'] ?? ''));
    $days = $body['expiresInDays'] ?? 7;
    $days = is_numeric($days) ? (int) $days : 7;
    $days = in_array($days, [0, 1, 7, 30], true) ? $days : 7;

    if (!lb_is_valid_url($url)) {
        lb_json(['ok' => false, 'error' => 'validation', 'message' => 'Enter a valid URL (https://, mailto:, or tel:).'], 422);
    }

    $createdAt = gmdate('Y-m-d H:i:s');
    $expiresAt = lb_days_to_expires_at($days);

    $tries = 0;
    while ($tries < 5) {
        $tries++;
        $code = lb_code_from_hash($url);

        try {
            $stmt = $pdo->prepare('INSERT INTO short_links (code, url, created_at, expires_at) VALUES (?, ?, ?, ?)');
            $stmt->execute([$code, $url, $createdAt, $expiresAt]);
            $shortUrl = lb_base_url() . '?s=' . rawurlencode($code);
            lb_json(['ok' => true, 'code' => $code, 'shortUrl' => $shortUrl]);
        } catch (Throwable $e) {
            continue;
        }
    }

    lb_json(['ok' => false, 'error' => 'server', 'message' => 'Could not generate a short link.'], 500);
}

if ($action === 'resolve' && $method === 'GET') {
    $code = trim((string) ($_GET['code'] ?? ''));
    if ($code === '' || preg_match('/^[A-Za-z0-9_-]{6,16}$/', $code) !== 1) {
        lb_json(['ok' => false, 'error' => 'validation', 'message' => 'Invalid short code.'], 422);
    }

    $stmt = $pdo->prepare('SELECT id, url, expires_at FROM short_links WHERE code = ? LIMIT 1');
    $stmt->execute([$code]);
    $row = $stmt->fetch();
    if (!$row) lb_json(['ok' => false, 'error' => 'not_found', 'message' => 'Short link not found.'], 404);

    $expiresAt = $row['expires_at'];
    if ($expiresAt !== null && strtotime((string) $expiresAt) !== false && time() > strtotime((string) $expiresAt)) {
        lb_json(['ok' => false, 'error' => 'expired', 'message' => 'This short link has expired.'], 410);
    }

    $pdo->prepare('UPDATE short_links SET clicks = clicks + 1, last_clicked_at = ? WHERE id = ?')->execute([gmdate('Y-m-d H:i:s'), (int) $row['id']]);
    lb_json(['ok' => true, 'url' => (string) $row['url']]);
}

lb_json(['ok' => false, 'error' => 'not_found'], 404);
