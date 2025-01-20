import "../css/app.css";

document.addEventListener("DOMContentLoaded", () => {
  const dropdowns = document.querySelectorAll(".dropdown");

  document.addEventListener("click", (e) => {
    let target = false;
    dropdowns.forEach((dropdown) => {
      if (dropdown.contains(e.target)) {
        target = true;
      }
    });

    if (!target) {
      dropdowns.forEach((d) => d.removeAttribute("open"));
    }
  });
});
