import { post, del } from "./ajax";

document.addEventListener("DOMContentLoaded", () => {
  const addButtons = document.querySelectorAll(".add-show");

  addButtons.forEach((btn) =>
    btn.addEventListener("click", () =>
      btn.hasAttribute("added") ? del(btn) : post(btn)
    )
  );
});
