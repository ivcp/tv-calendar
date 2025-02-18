import { openEpisodeModal } from "./episodeModal";

document.addEventListener("DOMContentLoaded", () => {
  const episodeBtns = document.querySelectorAll("button[data-episode-info]");
  episodeBtns.forEach((btn) =>
    btn.addEventListener("click", function () {
      const episode = JSON.parse(btn.dataset.episodeInfo);
      openEpisodeModal(episode);
    })
  );
});
