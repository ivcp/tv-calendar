import { openEpisodeModal } from '../utils/episodeModal';
import { Episode } from '../types';
import { assertButtonElement, assertHtmlElement } from '../utils/assertElement';
import notificationBtnEventListener from '../utils/notificationBtnEventListener';
import { getStarring } from '../utils/ajax';

document.addEventListener('DOMContentLoaded', async () => {
  const episodeBtns = document.querySelectorAll('button[data-episode-info]');
  const notificationEnableBtn = document.getElementById(
    'notification-enable-btn',
  );
  const starringSpan = document.getElementById('starring');
  assertHtmlElement(starringSpan);

  episodeBtns.forEach(btn =>
    btn.addEventListener('click', function () {
      const epInfo = btn.getAttribute('data-episode-info');
      if (epInfo) {
        const episode = JSON.parse(epInfo) as Episode;
        openEpisodeModal(episode);
      }
    }),
  );

  notificationEnableBtn?.addEventListener('click', () => {
    assertButtonElement(notificationEnableBtn);
    notificationBtnEventListener(notificationEnableBtn);
  });

  const tvMazeId = starringSpan.dataset.tvMazeId;

  const starring = await getStarring(
    `https://api.tvmaze.com/shows/${tvMazeId}/cast`,
  );
  if (!starring.error) {
    starringSpan.classList.remove('skeleton');
    const actors = starring.messages;
    if (actors.length > 0) {
      starringSpan.innerHTML = `<strong>Starring: </strong> ${starring.messages.join(', ')}`;
    } else {
      starringSpan.remove();
    }
  } else {
    starringSpan.remove();
  }
});
