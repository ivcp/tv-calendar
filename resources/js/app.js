import "../css/app.css";
import { get } from "./ajax";
import { notification } from "./notification";
import debounce from "./debounce";

document.addEventListener("DOMContentLoaded", () => {
  const dropdowns = document.querySelectorAll(".dropdown");
  const searchBtn = document.querySelector("#search-btn");
  const searchDiv = document.querySelector("#search");
  const searchBackdrop = document.querySelector("#search-backdrop");
  const searchInput = document.querySelector("#search-input");
  const searchResults = document.querySelector("#search-results");

  const hideSearchResults = () => {
    searchResults.replaceChildren();
    searchInput.value = "";
    searchResults.classList.add("hidden");
    searchResults.classList.remove("flex");
  };

  document.addEventListener("click", (e) => {
    let dropDownTarget = false;
    dropdowns.forEach((dropdown) => {
      if (dropdown.contains(e.target)) {
        dropDownTarget = true;
      }
    });

    if (
      !searchDiv.contains(e.target) &&
      e.target.id !== "search-next-btn" &&
      e.target.id !== "search-prev-btn"
    ) {
      hideSearchResults();
    }

    if (!dropDownTarget) {
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

  document.addEventListener("keyup", (e) => {
    if (e.key === "Escape") {
      hideSearchResults();
    }
  });

  const debouncedSearch = debounce((query) =>
    renderResults(...query, searchResults)
  );

  searchInput.addEventListener("input", async (e) => {
    debouncedSearch(e.target.value);
  });
});

const renderResults = async (value, searchResults, page = null) => {
  if (value.trim() === "") {
    searchResults.replaceChildren();
    searchResults.classList.add("hidden");
    searchResults.classList.remove("flex");
    return;
  }

  searchResults.replaceChildren();
  searchResults.classList.remove("hidden");
  searchResults.classList.add("flex");
  [...Array(10).keys()].forEach((n) => {
    searchResults.insertAdjacentHTML(
      "beforeend",
      `<li class="rounded-lg text-center bg-base-100">
      <div class="skeleton opacity-50 rounded-lg h-10 w-full p-2">       
      </div>    
      </li>${n === 9 ? "<div class='h-11'></div>" : ""}     
      `
    );
  });

  const response = await get(
    `/search?query=${value.trim()}${page ? "&page=" + page : ""}`
  );
  if (response.error) {
    notification(response.messages, "alert-error");
    return;
  }

  searchResults.replaceChildren();

  const result = response.body.result;
  const pagination = response.body.pagination;

  if (result.length === 0) {
    searchResults.insertAdjacentHTML(
      "beforeend",
      `<li class="bg-base-100 hover:bg-base-200 rounded-lg">
        <p class="p-2">
        No match found for "${value.trim()}"
        </p>
        </li>
        `
    );
    return;
  }
  result.forEach((result) => {
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

  searchResults.insertAdjacentHTML(
    "beforeend",
    `<div class="mt-1 join rounded-full text-lg justify-center gap-2">
      <button id="search-prev-btn" class="join-item btn bg-base-100 hover:bg-base-200 min-h-10 h-10" 
      ${pagination.page <= 1 && "disabled"}>
        «
      </button>
      <button id="search-next-btn" class="join-item btn bg-base-100 hover:bg-base-200 min-h-10 h-10"
      ${pagination.page === pagination.totalPages && "disabled"}>
        »
      </button>
    </div>
      `
  );

  searchResults
    .querySelector("#search-next-btn")
    .addEventListener("click", () => {
      renderResults(value, searchResults, pagination.page + 1);
    });

  searchResults
    .querySelector("#search-prev-btn")
    .addEventListener("click", () => {
      renderResults(value, searchResults, pagination.page - 1);
    });
};
