import { Episode } from '../types';
import { assertDialogElement } from './assertElement';

export function openEpisodeModal(episode: Episode) {
  const modalElement = document.getElementById('episode-modal');
  assertDialogElement(modalElement);

  const titleElement = modalElement.querySelector('#episode-modal-title');
  const showTitleElement = modalElement.querySelector(
    '#episode-modal-show-name',
  );
  const showTitleTextElement = modalElement.querySelector(
    '#episode-modal-show-name-text',
  );
  const summaryElement = modalElement.querySelector('#episode-modal-summary');
  const imgElement = modalElement.querySelector('#episode-modal-img');
  const placeholderElement = modalElement.querySelector(
    '#episode-modal-placeholder',
  );
  const seasonElement = modalElement.querySelector('#episode-modal-season');
  const episodeElement = modalElement.querySelector('#episode-modal-number');
  const networkElement = modalElement.querySelector('#episode-modal-network');
  const webChannelElement = modalElement.querySelector(
    '#episode-modal-web-channel',
  );
  const vpnElement = modalElement.querySelector('#episode-modal-vpn');

  if (
    !titleElement ||
    !showTitleElement ||
    !showTitleTextElement ||
    !summaryElement ||
    !imgElement ||
    !placeholderElement ||
    !seasonElement ||
    !episodeElement ||
    !networkElement ||
    !webChannelElement ||
    !vpnElement
  ) {
    throw new Error(`Expected element not to be null`);
  }

  titleElement.textContent = '';
  showTitleTextElement.textContent = '';
  summaryElement.textContent = '';
  summaryElement.classList.add('hidden');
  seasonElement.textContent = '-';
  episodeElement.classList.add('stat-value');
  episodeElement.textContent = '-';
  networkElement.textContent = '';
  webChannelElement.textContent = '';
  imgElement.removeAttribute('src');
  imgElement.classList.add('hidden');
  imgElement.setAttribute('alt', '');
  placeholderElement.classList.remove('hidden');
  placeholderElement.classList.add('flex');

  titleElement.textContent = episode.episodeName;
  showTitleTextElement.textContent = episode.showName;

  const showPagePath = `/shows/${episode.showId}-${encodeURIComponent(
    episode.showSlug,
  )}`;
  const showPageIdPath = `/shows/${episode.showId}`;
  const isOnShowPage =
    window.location.pathname === showPageIdPath ||
    window.location.pathname.startsWith(`${showPageIdPath}-`);

  if (isOnShowPage) {
    showTitleElement.removeAttribute('href');
    showTitleElement.removeAttribute('aria-label');
    showTitleElement.setAttribute('data-current', '');
  } else {
    showTitleElement.setAttribute('href', showPagePath);
    showTitleElement.setAttribute(
      'aria-label',
      `View show page for ${episode.showName}`,
    );
    showTitleElement.removeAttribute('data-current');
  }
  const summary = episode.episodeSummary ? strip(episode.episodeSummary) : '';
  if (summary) {
    summaryElement.textContent = summary;
    summaryElement.classList.remove('hidden');
  }
  vpnElement.setAttribute('href', '/watch-while-traveling?from=ep-modal');

  if (episode.image) {
    imgElement.setAttribute('src', episode.image);
    imgElement.setAttribute('alt', episode.episodeName);
    imgElement.classList.remove('hidden');
    placeholderElement.classList.add('hidden');
    placeholderElement.classList.remove('flex');
  }

  if (episode.seasonNumber) {
    seasonElement.textContent = episode.seasonNumber.toString();
  }
  if (episode.episodeNumber) {
    episodeElement.textContent = episode.episodeNumber.toString();
  }
  if (episode.type.includes('special')) {
    episodeElement.classList.remove('stat-value');
    episodeElement.textContent = 'SPECIAL';
  }

  if (episode.networkName) {
    const formatter = Intl.DateTimeFormat(navigator.language, {
      hour: 'numeric',
      minute: 'numeric',
    });
    const airtime = episode.airstamp
      ? formatter.format(new Date(episode.airstamp))
      : '';
    networkElement.textContent = `${airtime + ' '} ${episode.networkName}`;
  }

  if (episode.webChannelName) {
    webChannelElement.textContent = `${episode.webChannelName}`;
  }

  modalElement.showModal();
}

function strip(body: string) {
  let doc = new DOMParser().parseFromString(body, 'text/html');
  return doc.body.textContent || '';
}
