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
  const airstampEl = document.getElementsByClassName('ep-airstamp');

  for (const el of airstampEl) {
    const element = el as HTMLElement;
    if (element.textContent && element.dataset.airstampEp) {
      const date = new Date(element.dataset.airstampEp);
      const format = date.toLocaleDateString('en-US', {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
      });
      el.textContent = format;
    }
  }

  for (const el of document.querySelectorAll<HTMLElement>('[data-airtime]')) {
    const airstamp = el.getAttribute('datetime');
    if (!airstamp) {
      continue;
    }
    el.textContent = new Date(airstamp).toLocaleString('en-US', {
      month: 'long',
      day: 'numeric',
      year: 'numeric',
      hour: 'numeric',
      minute: '2-digit',
    });
  }

  const countdownEl = document.getElementById('next-episode-countdown');
  const countdownAirstamp = countdownEl?.dataset.airstamp;
  if (countdownEl && countdownAirstamp) {
    const remaining = new Date(countdownAirstamp).getTime() - Date.now();
    if (remaining > 0) {
      countdownEl.textContent = relativeLabel(remaining);
    }
  }

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
    starringSpan.removeAttribute('aria-busy');
    starringSpan.classList.remove('skeleton');
    const actors = starring.messages;
    if (actors.length > 0) {
      const starringLabel = document.createElement('strong');
      starringLabel.textContent = 'Starring: ';
      starringSpan.replaceChildren(starringLabel, actors.join(', '));
    } else {
      starringSpan.remove();
    }
  } else {
    starringSpan.remove();
  }
});

function relativeLabel(remaining: number): string {
  const minutes = Math.max(1, Math.round(remaining / 60_000));

  if (minutes < 60) {
    return `in ${minutes} minute${minutes === 1 ? '' : 's'}`;
  }

  const hours = Math.round(remaining / 3_600_000);

  if (hours < 24) {
    return `in ${hours} hour${hours === 1 ? '' : 's'}`;
  }

  const days = Math.round(remaining / 86_400_000);

  return `in ${days} day${days === 1 ? '' : 's'}`;
}
