import { Episode } from '../types';
import { assertHtmlElement } from '../utils/assertElement';
import { openEpisodeModal } from '../utils/episodeModal';
import { getCurrentYearMonth } from './helpers';

function populateDates(episodes: Episode[]) {
  const airingTodayContainer = document.getElementById(
    'airing-today-container'
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
      if (body) insertEpisode(episode, body);
    }
    insertEpisode(episode, cardBody);
  });

  document
    .querySelectorAll('.skeleton')
    .forEach(e => e.classList.remove('skeleton'));
}

function insertEpisode(episode: Episode, el: Element) {
  const premiere = episode.episodeNumber === 1 && episode.seasonNumber === 1;
  const newSeasonStart =
    episode.episodeNumber === 1 && episode.seasonNumber > 1;

  const premiereStyles =
    'bg-warning/85 text-warning-content lg:hover:bg-warning';
  const newSeasonStartStyles =
    'bg-primary/85 text-primary-content lg:hover:bg-primary';

  el.insertAdjacentHTML(
    'beforeend',
    `<button id="ep-${
      episode.id
    }" class="bg-base-content/85 lg:hover:bg-base-content ${
      premiere ? premiereStyles : ''
    } ${
      newSeasonStart ? newSeasonStartStyles : ''
    }  rounded-md p-4 lg:p-2 lg:px-2 text-left transition-colors flex justify-between items-baseline overflow-hidden">
          ${
            episode.showName
          }<span id="season-number" class="text-nowrap text-sm">S${
      episode.seasonNumber
    } E${episode.episodeNumber ?? 'sp'}</span>
          </button>`
  );
  const epBtn = el.querySelector(`#ep-${episode.id}`);
  epBtn?.addEventListener('click', () => openEpisodeModal(episode));
}

export { populateDates };
