import { Episode } from '../types';
import { assertHtmlElement } from '../utils/assertElement';
import { openEpisodeModal } from '../utils/episodeModal';
import { getCurrentYearMonth } from './helpers';

function populateDates(episodes: Episode[], premieres: boolean = false) {
  const airingTodayContainer = document.getElementById(
    'airing-today-container',
  );
  assertHtmlElement(airingTodayContainer);
  document.querySelectorAll('.card-body').forEach(e => e.replaceChildren());
  airingTodayContainer.classList.add('hidden');
  airingTodayContainer.querySelector('#airing-today-body')?.replaceChildren();
  const path = window.location.pathname;
  const currentMonth = getCurrentYearMonth();
  const isCurrentMonth = path === '/' || currentMonth === path.slice(1);

  let currentShowId: number, currentDate: number, firstEpId: number;
  let sameDayEps = 1;
  let airingTodayCount = 0;
  episodes.forEach((episode, i) => {
    const date = new Date(episode.airstamp).getDate();
    const cardBody = document
      .getElementById(`date-${date}`)
      ?.querySelector('.card-body');
    assertHtmlElement(cardBody);

    if (currentShowId === episode.showId && currentDate === date) {
      sameDayEps++;
      currentShowId = episode.showId;
      if (sameDayEps === 2) {
        firstEpId = episodes[i - 1].id;
      }

      const prevEp = cardBody.querySelector(`#ep-${firstEpId}`);
      if (prevEp) {
        const sn = prevEp.querySelector('#season-number');
        if (sn) sn.textContent = `${sameDayEps} eps`;
      }

      if (
        isCurrentMonth &&
        date === new Date().getDate() &&
        airingTodayContainer
      ) {
        const body = airingTodayContainer.querySelector('#airing-today-body');
        const prevAiringTodayEp = body?.querySelector(`#ep-${firstEpId}`);
        const tsn = prevAiringTodayEp?.querySelector('#season-number');
        if (tsn) tsn.textContent = `${sameDayEps} eps`;
      }

      return;
    }
    sameDayEps = 1;
    currentShowId = episode.showId;
    currentDate = date;

    if (
      isCurrentMonth &&
      date === new Date().getDate() &&
      airingTodayContainer
    ) {
      airingTodayContainer.classList.remove('hidden');
      const body = airingTodayContainer.querySelector('#airing-today-body');
      if (body) {
        insertEpisode(episode, body, premieres);
        airingTodayCount++;
      }
    }
    insertEpisode(episode, cardBody, premieres);
  });

  const airingTodayCountEl = document.getElementById('airing-today-count');
  if (airingTodayCountEl) {
    airingTodayCountEl.textContent = String(airingTodayCount);
  }

  document
    .querySelectorAll('.skeleton')
    .forEach(e => e.classList.remove('skeleton'));
}

function insertEpisode(
  episode: Episode,
  el: Element,
  isPremiere: boolean,
): void {
  const premiere = episode.episodeNumber === 1 && episode.seasonNumber === 1;
  const newSeasonStart =
    episode.episodeNumber === 1 && episode.seasonNumber > 1;

  const btn = document.createElement('button');
  btn.id = `ep-${episode.id}`;
  btn.className = [
    'bg-base-content/85 lg:hover:bg-base-content',
    premiere ? 'bg-warning/85 text-warning-content lg:hover:bg-warning' : '',
    newSeasonStart
      ? 'bg-primary/85 text-primary-content lg:hover:bg-primary'
      : '',
    !premiere && !newSeasonStart ? 'text-base-100' : '',
    'flex items-center justify-between gap-2 overflow-hidden rounded-md px-3 py-2 text-left text-sm transition-colors ring-1 ring-inset ring-base-content/10 lg:py-1.5',
  ]
    .filter(Boolean)
    .join(' ');

  const name = document.createElement('span');
  name.className = 'min-w-0 truncate';
  name.textContent = episode.showName;
  name.title = episode.showName;

  const season = document.createElement('span');
  season.id = 'season-number';
  season.className =
    'shrink-0 text-nowrap text-xs font-semibold tracking-wide opacity-80';
  season.textContent = isPremiere
    ? `S${episode.seasonNumber}`
    : `S${episode.seasonNumber} E${episode.episodeNumber ?? 'sp'}`;

  // string children become text nodes — no HTML parsing
  btn.append(name, season);
  btn.addEventListener('click', () => openEpisodeModal(episode));
  el.append(btn);
}

export { populateDates };
