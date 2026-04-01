function closeAllDropdowns() {
  document.querySelectorAll(".nav__dropdown.is-open").forEach((el) => {
    el.classList.remove("is-open");
    const trigger = el.querySelector(".nav__trigger");
    if (trigger) trigger.setAttribute("aria-expanded", "false");
  });
}

document.addEventListener("click", (e) => {
  const dropdown = e.target.closest(".nav__dropdown");

  if (!dropdown) {
    closeAllDropdowns();
    return;
  }

  const trigger = e.target.closest(".nav__trigger");
  if (!trigger) return;

  const isOpen = dropdown.classList.contains("is-open");
  closeAllDropdowns();
  dropdown.classList.toggle("is-open", !isOpen);
  trigger.setAttribute("aria-expanded", String(!isOpen));
});

document.addEventListener("keydown", (e) => {
  if (e.key === "Escape") closeAllDropdowns();
});
