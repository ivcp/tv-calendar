import { del } from "./ajax";

document.addEventListener("DOMContentLoaded", () => {
  const removeButtons = document.querySelectorAll(".remove-show");

  removeButtons.forEach((btn) => btn.addEventListener("click", () => del(btn)));
});
