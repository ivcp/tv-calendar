import { openEpisodeModal } from "./episodeModal";
import { get } from "./ajax";
import { notification } from "./notification";

const currentMonth = getCurrentYearMonth();
const path = window.location.pathname;
const timeZone = Intl.DateTimeFormat().resolvedOptions().timeZone;

document.addEventListener("DOMContentLoaded", async () => {
  const popularShowsBtn = document.querySelector("#popular-shows");
  const userShowsBtn = document.querySelector("#user-shows");

  let url;
  if (path === "/") {
    url = `${currentMonth}?tz=${timeZone}`;
    drawCalendar();
  } else {
    url = `${path}?tz=${timeZone}`;
    if (currentMonth === path.slice(1)) {
      markToday();
    }
  }
  const response = await get(url);
  if (response.error) {
    notification(response.messages, "alert-error");
    return;
  }
  const data = response.body.schedule;

  console.log(data);

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

function populateDates(episodes) {
  const airingTodayContainer = document.querySelector(
    "#airing-today-container"
  );
  document.querySelectorAll(".card-body").forEach((e) => e.replaceChildren());
  if (airingTodayContainer) {
    airingTodayContainer.classList.add("hidden");
    airingTodayContainer.querySelector("#airing-today-body").replaceChildren();
  }

  const isCurrentMonth = path === "/" || currentMonth === path.slice(1);

  //TODO: fix this (if one show for whole month, not working)

  let currentShowId, currentDate, firstEpId;
  let sameDayEps = 1;
  episodes.forEach((episode, i) => {
    const date = new Date(episode.airstamp).getDate();
    const cardBody = document
      .querySelector(`#date-${date}`)
      .querySelector(".card-body");

    if (currentShowId === episode.showId && currentDate === date) {
      sameDayEps++;
      currentShowId = episode.showId;
      if (sameDayEps === 2) {
        firstEpId = episodes[i - 1].id;
      }

      const prevEp = cardBody.querySelector(`#ep-${firstEpId}`);
      prevEp.querySelector("#season-number").textContent = `${sameDayEps} eps`;
      return;
    }
    sameDayEps = 1;
    currentShowId = episode.showId;
    currentDate = date;

    if (isCurrentMonth && date === new Date().getDate()) {
      airingTodayContainer.classList.remove("hidden");
      insertEpisode(
        episode,
        airingTodayContainer.querySelector("#airing-today-body")
      );
    }

    insertEpisode(episode, cardBody);
  });

  document
    .querySelectorAll(".skeleton")
    .forEach((e) => e.classList.remove("skeleton"));
}

function insertEpisode(episode, el) {
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
      premiere ? premiereStyles : ""
    } ${
      newSeasonStart ? newSeasonStartStyles : ""
    }  rounded-md p-4 lg:p-2 lg:px-2 text-left transition-colors flex justify-between items-baseline overflow-hidden">
      ${
        episode.showName
      }<span id="season-number" class="text-nowrap text-sm">S${
      episode.seasonNumber
    } E${episode.episodeNumber ?? "sp"}</span>
      </button>`
  );
  const epBtn = el.querySelector(`#ep-${episode.id}`);
  epBtn.addEventListener("click", () => openEpisodeModal(episode));
}

function getCurrentYearMonth() {
  const date = new Date();
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  return `${year}-${month}`;
}

function drawCalendar() {
  const calendarElement = document.querySelector("#calendar");
  const now = new Date();
  const daysInMonth = new Date(
    now.getFullYear(),
    now.getMonth() + 1,
    0
  ).getDate();
  let firstDayInMonth = new Date(now.getFullYear(), now.getMonth(), 1).getDay();
  if (firstDayInMonth === 0) {
    firstDayInMonth = 7;
  }

  [...Array(daysInMonth).keys()].forEach((date) => {
    calendarElement.insertAdjacentHTML(
      "beforeend",
      dateCard(
        new Date(now.getFullYear(), now.getMonth(), date + 1),
        firstDayInMonth
      )
    );
  });
}

function dateCard(date, firstDay) {
  const isToday = sameDay(date, new Date());
  const dateNumber = date.getDate();
  return `<div
    id="date-${dateNumber}"
    class="skeleton lg:min-h-52 card gap-6 bg-base-300 rounded-lg p-2 lg:p-0
    ${dateNumber === 1 ? "lg:col-start-" + firstDay : ""}
    ${isToday ? " outline outline-2 outline-warning -outline-offset-2" : ""}
    "
    >
    <p class="self-end px-3 py-1 text-sm lg:text-base font-semibold">
      <span class="font-normal lg:hidden">${date.toLocaleString("en-us", {
        weekday: "long",
      })}, </span></span>
      ${dateNumber}<span class="text-xs lg:hidden">${nthNumber(
    dateNumber
  )}</span>
    </p>
    <div class="card-body justify-end gap-2 lg:gap-1 p-2 text-base-300">
    </div>
    </div>`;
}

function nthNumber(number) {
  if (number > 3 && number < 21) return "th";
  switch (number % 10) {
    case 1:
      return "st";
    case 2:
      return "nd";
    case 3:
      return "rd";
    default:
      return "th";
  }
}

function sameDay(d1, d2) {
  return (
    d1.getFullYear() === d2.getFullYear() &&
    d1.getMonth() === d2.getMonth() &&
    d1.getDate() === d2.getDate()
  );
}

function markToday() {
  const todayCard = document.querySelector(`#date-${new Date().getDate()}`);
  todayCard.classList.add(
    "outline",
    "outline-2",
    "outline-warning",
    "-outline-offset-2"
  );
}
