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
  const airtimeElement = modalElement.querySelector("#episode-modal-airtime");
  const networkElement = modalElement.querySelector("#episode-modal-network");
  titleElement.textContent = "";
  showTitleElement.textContent = "";
  summaryElement.textContent = "No episode summary available.";
  seasonElement.textContent = "-";
  episodeElement.textContent = "-";
  airtimeElement.textContent = "?";
  networkElement.textContent = "?";
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
