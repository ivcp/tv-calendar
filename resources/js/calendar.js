document.addEventListener("DOMContentLoaded", () => {
  const scheduleData = document
    .querySelector("#calendar")
    .getAttribute("data-schedule");
  const data = JSON.parse(scheduleData);

  for (const [key, value] of Object.entries(data.popular)) {
    const cardElement = document
      .querySelector(`#date-${key}`)
      .querySelector(".card-body");
    value.forEach((show, i) =>
      cardElement.insertAdjacentHTML(
        "beforeend",
        `<div class="bg-base-100 rounded-sm p-4 lg:p-2 lg:px-2 flex justify-between overflow-hidden">
        ${show.showName}<span class="">${show.seasonNumber}x${
          show.episodeNumber ?? "S"
        }</span>
        </div>`
      )
    );
  }
});
