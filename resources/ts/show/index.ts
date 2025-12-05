import { openEpisodeModal } from '../utils/episodeModal';
import { Episode } from '../types';
import { assertButtonElement } from '../utils/assertElement';
import notificationBtnEventListener from '../utils/notificationBtnEventListener';

document.addEventListener('DOMContentLoaded', () => {
  const episodeBtns = document.querySelectorAll('button[data-episode-info]');

  const notificationEnableBtn = document.getElementById(
    'notification-enable-btn'
  );

  episodeBtns.forEach(btn =>
    btn.addEventListener('click', function () {
      const epInfo = btn.getAttribute('data-episode-info');
      if (epInfo) {
        const episode = JSON.parse(epInfo) as Episode;
        openEpisodeModal(episode);
      }
    })
  );

  notificationEnableBtn?.addEventListener('click', () => {
    assertButtonElement(notificationEnableBtn);
    notificationBtnEventListener(notificationEnableBtn);
  });
});
