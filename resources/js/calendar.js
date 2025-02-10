import { openEpisodeModal } from "./episodeModal";

document.addEventListener("DOMContentLoaded", () => {
  const popularShowsBtn = document.querySelector("#popular-shows");
  const userShowsBtn = document.querySelector("#user-shows");

  const scheduleData = document
    .querySelector("#calendar")
    .getAttribute("data-schedule");
  const data = JSON.parse(scheduleData);

  const activeClasses = ["tab-active", "bg-primary", "text-primary-content"];

  const activateUserBtn = () => {
    popularShowsBtn.classList.remove(...activeClasses);
    userShowsBtn.classList.add(...activeClasses);
  };
  const activatePopularBtn = () => {
    userShowsBtn.classList.remove(...activeClasses);
    popularShowsBtn.classList.add(...activeClasses);
  };

  if (userShowsBtn.hasAttribute("active")) {
    populateDates(data.user_shows);
    activateUserBtn();
  } else {
    populateDates(data.popular);
    activatePopularBtn();
  }
  popularShowsBtn.addEventListener("click", () => {
    populateDates(data.popular);
    activatePopularBtn();
  });
  userShowsBtn.addEventListener("click", () => {
    populateDates(data.user_shows);
    activateUserBtn();
  });
});

function populateDates(data) {
  const airingTodayContainer = document.querySelector(
    "#airing-today-container"
  );
  document.querySelectorAll(".card-body").forEach((e) => e.replaceChildren());
  if (airingTodayContainer) {
    airingTodayContainer.classList.add("hidden");
    airingTodayContainer.querySelector("#airing-today-body").replaceChildren();
  }
  for (const [key, value] of Object.entries(data)) {
    const cardBody = document
      .querySelector(`#date-${key}`)
      .querySelector(".card-body");

    if (
      airingTodayContainer &&
      airingTodayContainer.getAttribute("data-today") === key &&
      value.length > 0
    ) {
      airingTodayContainer.classList.remove("hidden");
      fillBody(value, airingTodayContainer.querySelector("#airing-today-body"));
    }

    fillBody(value, cardBody);
  }
}

function fillBody(episodes, el) {
  episodes.forEach((episode, i) => {
    el.insertAdjacentHTML(
      "beforeend",
      `<button href="#" id="ep-${
        episode.id
      }" class="bg-base-content/85 text-primary-content rounded-md p-4 lg:p-2 lg:px-2 lg:hover:bg-base-content transition-colors flex justify-between overflow-hidden">
      ${episode.showName}<span>${episode.seasonNumber}x${
        episode.episodeNumber ?? "S"
      }</span>
      </button>`
    );
    const epBtn = document.querySelector(`#ep-${episode.id}`);
    epBtn.addEventListener("click", () => openEpisodeModal(episode));
  });
}
