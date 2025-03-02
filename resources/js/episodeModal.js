import NoEpImageSvg from "../images/no-img-ep.svg";

export function openEpisodeModal(episode) {
  const modalElement = document.querySelector("#episode-modal");
  const titleElement = modalElement.querySelector("#episode-modal-title");
  const showTitleElement = modalElement.querySelector(
    "#episode-modal-show-name"
  );
  const summaryElement = modalElement.querySelector("#episode-modal-summary");
  const imgElement = modalElement.querySelector("#episode-modal-img");
  const seasonElement = modalElement.querySelector("#episode-modal-season");
  const episodeElement = modalElement.querySelector("#episode-modal-number");
  const networkElement = modalElement.querySelector("#episode-modal-network");
  const webChannelElement = modalElement.querySelector(
    "#episode-modal-web-channel"
  );
  titleElement.textContent = "";
  showTitleElement.textContent = "";
  summaryElement.textContent = "No episode summary available.";
  seasonElement.textContent = "-";
  episodeElement.classList.add("stat-value");
  episodeElement.textContent = "-";
  networkElement.textContent = "";
  webChannelElement.textContent = "";
  imgElement.setAttribute("src", NoEpImageSvg);
  imgElement.classList.add("border-2");
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
    imgElement.classList.remove("border-2");
  }

  if (episode.seasonNumber) {
    seasonElement.textContent = episode.seasonNumber;
  }
  if (episode.episodeNumber) {
    episodeElement.textContent = episode.episodeNumber;
  }
  if (episode.type.includes("special")) {
    episodeElement.classList.remove("stat-value");
    episodeElement.textContent = "SPECIAL";
  }

  if (episode.networkName) {
    const formatter = Intl.DateTimeFormat(navigator.language, {
      hour: "numeric",
      minute: "numeric",
    });
    const airtime = episode.airstamp
      ? formatter.format(new Date(episode.airstamp))
      : "";
    networkElement.textContent = `${airtime + " "}on ${episode.networkName}`;
  }
  if (episode.webChannelName) {
    webChannelElement.textContent = `streaming on ${episode.webChannelName}`;
  }

  modalElement.showModal();
}

function strip(html) {
  let doc = new DOMParser().parseFromString(html, "text/html");
  return doc.body.textContent || "";
}
