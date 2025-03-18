import { openEpisodeModal } from '../utils/episodeModal';
import { Episode } from '../types';

document.addEventListener('DOMContentLoaded', () => {
  const episodeBtns = document.querySelectorAll('button[data-episode-info]');
  episodeBtns.forEach(btn =>
    btn.addEventListener('click', function () {
      const epInfo = btn.getAttribute('data-episode-info');
      if (epInfo) {
        const episode = JSON.parse(epInfo) as Episode;
        openEpisodeModal(episode);
      }
    })
  );
});
