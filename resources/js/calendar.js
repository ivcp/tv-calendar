import NoEpImageSvg from "../images/no-img-ep.svg";

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

function openEpisodeModal(episode) {
  const modalElement = document.querySelector("#episode-modal");
  const titleElement = modalElement.querySelector("#episode-modal-title");
  const showTitleElement = modalElement.querySelector(
    "#episode-modal-show-name"
  );
  const summaryElement = modalElement.querySelector("#episode-modal-summary");
  const imgElement = modalElement.querySelector("#episode-modal-img");
  const seasonElement = modalElement.querySelector("#episode-modal-season");
  const episodeElement = modalElement.querySelector("#episode-modal-number");
  const airtimeElement = modalElement.querySelector("#episode-modal-airtime");
  const networkElement = modalElement.querySelector("#episode-modal-network");
  titleElement.textContent = "";
  showTitleElement.textContent = "";
  summaryElement.textContent = "No episode summary available.";
  seasonElement.textContent = "-";
  episodeElement.textContent = "-";
  airtimeElement.textContent = "?";
  networkElement.textContent = "?";
  imgElement.setAttribute("alt", "no image available");

  titleElement.textContent = episode.episodeName;
  showTitleElement.textContent = episode.showName;
  showTitleElement.setAttribute("href", `/shows/${episode.showId}`);
  if (episode.episodeSummary) {
    summaryElement.textContent = strip(episode.episodeSummary);
  }

  if (episode.image) {
    imgElement.setAttribute("src", episode.image);
    imgElement.setAttribute("alt", episode.episodeName);
  } else {
    imgElement.setAttribute("src", NoEpImageSvg);
  }

  if (episode.seasonNumber) {
    seasonElement.textContent = episode.seasonNumber;
  }
  if (episode.episodeNumber) {
    episodeElement.textContent = episode.episodeNumber;
  }

  if (episode.airstamp) {
    airtimeElement.textContent = new Date(episode.airstamp).toLocaleTimeString(
      [],
      {
        timeStyle: "short",
        hour12: false,
      }
    );
  }
  if (episode.networkName || episode.webChannelName) {
    networkElement.textContent = episode.networkName
      ? episode.networkName
      : episode.webChannelName;
  }

  modalElement.showModal();
}

function strip(html) {
  let doc = new DOMParser().parseFromString(html, "text/html");
  return doc.body.textContent || "";
}
