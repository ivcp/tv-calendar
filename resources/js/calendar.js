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
    const cardBody = document
      .querySelector(`#date-${key}`)
      .querySelector(".card-body");
    value.forEach((show, i) => {
      cardBody.insertAdjacentHTML(
        "beforeend",
        `<div class="bg-base-100 rounded-sm p-4 lg:p-2 lg:px-2 flex justify-between overflow-hidden">
        ${show.showName}<span class="">${show.seasonNumber}x${
          show.episodeNumber ?? "S"
        }</span>
        </div>`
      );
    });
  }
}
