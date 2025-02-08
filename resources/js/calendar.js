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

function populateDates(shows) {
  document.querySelectorAll(".card-body").forEach((e) => e.replaceChildren());
  for (const [key, value] of Object.entries(shows)) {
    const cards = document.querySelectorAll(`#date-${key}`);
    const cardBody = cards[0].querySelector(".card-body");
    if (cards.length > 1) {
      const airingTodayContainer = document.querySelector(
        "#airing-today-container"
      );
      if (airingTodayContainer && value.length > 0) {
        airingTodayContainer.classList.remove("hidden");
        fillBody(value, cards[0].querySelector(".card-body"));
        fillBody(value, cards[1].querySelector(".card-body"));
      }
      return;
    }
    fillBody(value, cardBody);
  }
}

function fillBody(shows, el) {
  shows.forEach((show, i) => {
    el.insertAdjacentHTML(
      "beforeend",
      `<div class="bg-base-100 rounded-sm p-4 lg:p-2 lg:px-2 flex justify-between overflow-hidden">
      ${show.showName}<span class="">${show.seasonNumber}x${
        show.episodeNumber ?? "S"
      }</span>
      </div>`
    );
  });
}
