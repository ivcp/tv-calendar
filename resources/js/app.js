import "../css/app.css";
import { get } from "./ajax";

document.addEventListener("DOMContentLoaded", () => {
  const dropdowns = document.querySelectorAll(".dropdown");
  const searchBtn = document.querySelector("#search-btn");
  const searchDiv = document.querySelector("#search");
  const searchBackdrop = document.querySelector("#search-backdrop");
  const searchInput = document.querySelector("#search-input");
  const searchResults = document.querySelector("#search-results");

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

  searchInput.addEventListener("keyup", (e) => {
    if (e.key === "Escape") {
      searchInput.value = "";
      searchResults.classList.add("hidden");
      searchResults.classList.remove("flex");
      searchResults.replaceChildren();
      return;
    }
    if (e.key === "Backspace") {
      console.log("back");
      renderResults(e.target.value, searchResults);
    }
  });

  searchInput.addEventListener("input", (e) =>
    renderResults(e.target.value, searchResults)
  );
});

const renderResults = async (value, searchResults) => {
  if (value.trim() === "") {
    searchResults.classList.add("hidden");
    searchResults.classList.remove("flex");
    searchResults.replaceChildren();
    return;
  }

  searchResults.replaceChildren();

  const result = await get(`/search?query=${value.trim()}`);
  if (result.error) {
    console.log(result.messages);
    return;
  }

  console.log(result.body.pagination);

  searchResults.classList.remove("hidden");
  searchResults.classList.add("flex");

  result.body.result.forEach((result) => {
    searchResults.insertAdjacentHTML(
      "beforeend",
      `<li class="bg-base-100 hover:bg-base-200 rounded-lg">
          <a href="/shows/${result.id}" class="flex justify-between p-2">
            <span class="max-w-80 truncate">${result.name}</span>
            <span>&#8594;</span></a>
      </li>
      `
    );
  });
};
