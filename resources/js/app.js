import "../css/app.css";

document.addEventListener("DOMContentLoaded", () => {
  const dropdowns = document.querySelectorAll(".dropdown");
  const searchBtn = document.querySelector("#search-btn");
  const searchDiv = document.querySelector("#search");
  const searchBackdrop = document.querySelector("#search-backdrop");

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

  searchBtn.addEventListener("click", () => {
    searchDiv.classList.remove("hidden");
    searchDiv.classList.add("flex");
    searchBackdrop.classList.remove("hidden");
  });

  searchBackdrop.addEventListener("click", () => {
    searchDiv.classList.add("hidden");
    searchDiv.classList.remove("flex");
    searchBackdrop.classList.add("hidden");
  });
});
