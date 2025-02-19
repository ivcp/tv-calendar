import { openEpisodeModal } from "./episodeModal";
import { get } from "./ajax";
import { notification } from "./notification";

document.addEventListener("DOMContentLoaded", async () => {
  const popularShowsBtn = document.querySelector("#popular-shows");
  const userShowsBtn = document.querySelector("#user-shows");
  const scheduleData = document
    .querySelector("#calendar")
    .getAttribute("data-schedule");

  let data;
  if (window.location.pathname === "/") {
    const response = await get(getCurrentYearMonth());
    if (response.error) {
      notification(response.messages, "alert-error");
      return;
    }
    data = response.body.schedule;
  } else {
    data = JSON.parse(scheduleData);
  }

  console.log(data);
  return;

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

  ///TODO:

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
  const showIds = episodes.map((e) => e.showId);
  const uniqueShowEps = episodes.filter(
    (ep, i) => showIds.indexOf(ep.showId) === i
  );
  uniqueShowEps.forEach((episode) => {
    const premiere = episode.episodeNumber === 1 && episode.seasonNumber === 1;
    const newSeasonStart =
      episode.episodeNumber === 1 && episode.seasonNumber > 1;

    const premiereStyles =
      "bg-warning/85 text-warning-content lg:hover:bg-warning";
    const newSeasonStartStyles =
      "bg-primary/85 text-primary-content lg:hover:bg-primary";

    el.insertAdjacentHTML(
      "beforeend",
      `<button id="ep-${
        episode.id
      }" class="bg-base-content/85 text-primary-content lg:hover:bg-base-content ${
        premiere && premiereStyles
      } ${
        newSeasonStart && newSeasonStartStyles
      }  rounded-md p-4 lg:p-2 lg:px-2 text-left transition-colors flex justify-between overflow-hidden">
      ${episode.showName}<span>${episode.seasonNumber}x${
        episode.episodeNumber ?? "S"
      }</span>
      </button>`
    );
    const epBtn = el.querySelector(`#ep-${episode.id}`);
    epBtn.addEventListener("click", () => openEpisodeModal(episode));
  });
}

function getCurrentYearMonth() {
  const date = new Date();
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  return `${year}-${month}`;
}
