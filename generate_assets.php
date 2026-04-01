<?php
$dir = __DIR__ . DIRECTORY_SEPARATOR . "assets";
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}

function makeImg(string $file, int $w, int $h, array $a, array $b, int $seed): void
{
    $im = imagecreatetruecolor($w, $h);
    imagealphablending($im, true);

    $r1 = (int) $a[0];
    $g1 = (int) $a[1];
    $b1 = (int) $a[2];
    $r2 = (int) $b[0];
    $g2 = (int) $b[1];
    $b2 = (int) $b[2];

    $den = max(1, $h - 1);
    for ($y = 0; $y < $h; $y++) {
        $t = $y / $den;
        $r = (int) round($r1 + ($r2 - $r1) * $t);
        $g = (int) round($g1 + ($g2 - $g1) * $t);
        $bb = (int) round($b1 + ($b2 - $b1) * $t);
        $col = imagecolorallocate($im, $r, $g, $bb);
        imageline($im, 0, $y, $w, $y, $col);
    }

    mt_srand($seed);
    $count = (int) (($w * $h) / 28);
    for ($i = 0; $i < $count; $i++) {
        $x = mt_rand(0, $w - 1);
        $y = mt_rand(0, $h - 1);
        $col = imagecolorallocatealpha($im, 255, 255, 255, mt_rand(60, 120));
        imagesetpixel($im, $x, $y, $col);
    }

    $cx = (int) ($w * 0.72);
    $cy = (int) ($h * 0.28);
    $rad = (int) ($w * 0.55);
    for ($i = 0; $i < 4; $i++) {
        $alpha = 110 - ($i * 18);
        $ov = imagecolorallocatealpha($im, 255, 255, 255, $alpha);
        imagefilledellipse($im, $cx, $cy, $rad - ($i * 90), $rad - ($i * 90), $ov);
    }

    imagefilter($im, IMG_FILTER_GAUSSIAN_BLUR);
    imagefilter($im, IMG_FILTER_GAUSSIAN_BLUR);
    imagejpeg($im, $file, 84);
    imagedestroy($im);
}

makeImg($dir . DIRECTORY_SEPARATOR . "photo-1.jpg", 1200, 800, [242, 178, 74], [240, 138, 42], 11);
makeImg($dir . DIRECTORY_SEPARATOR . "photo-2.jpg", 1200, 800, [90, 205, 255], [59, 130, 246], 22);
makeImg($dir . DIRECTORY_SEPARATOR . "photo-3.jpg", 1200, 800, [16, 185, 129], [34, 197, 94], 33);
makeImg($dir . DIRECTORY_SEPARATOR . "photo-4.jpg", 1200, 800, [99, 102, 241], [236, 72, 153], 44);

echo "OK\n";
