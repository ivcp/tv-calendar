import { Episode, Schedule } from '../types';
import { isAbortError } from '../utils/ajax';
import { assertHtmlElement } from '../utils/assertElement';
import {
  getLocalShowlist,
  getSchedule,
  setLocalShowList,
  setSchedule,
} from '../utils/localStorageHelpers';
import { drawCalendar } from './drawCalendar';
import { getCurrentYearMonth, getShows, markToday } from './helpers';
import { populateDates } from './populateDates';

const currentMonth = getCurrentYearMonth();
const path = window.location.pathname;
const timeZone = Intl.DateTimeFormat().resolvedOptions().timeZone;

const scheduleLabels: Record<Schedule, string> = {
  user: 'My Shows',
  popular: 'Popular',
  premieres: 'Premieres',
};

document.addEventListener('DOMContentLoaded', async () => {
  const userShowsBtn = document.getElementById('user-shows');
  assertHtmlElement(userShowsBtn);
  const popularShowsBtn = document.getElementById('popular-shows');
  assertHtmlElement(popularShowsBtn);
  const premieresShowsBtn = document.getElementById('premieres-shows');
  assertHtmlElement(premieresShowsBtn);

  const user = document.querySelector('section')?.hasAttribute('user');
  const localShowlist = getLocalShowlist();
  if (user && localShowlist.length > 0) {
    setLocalShowList([]);
  }

  let url: string;
  if (path === '/') {
    url = `${currentMonth}?tz=${timeZone}`;
    drawCalendar();
  } else {
    url = `${path}?tz=${timeZone}`;
    if (currentMonth === path.slice(1)) {
      markToday();
    }
  }

  const tabs: Record<Schedule, HTMLElement> = {
    user: userShowsBtn,
    popular: popularShowsBtn,
    premieres: premieresShowsBtn,
  };
  const calendar = document.getElementById('calendar');
  assertHtmlElement(calendar);
  const airingTodayContainer = document.getElementById(
    'airing-today-container',
  );
  assertHtmlElement(airingTodayContainer);
  const statusRegion = document.getElementById('calendar-status');

  const renderedSchedule =
    (document.documentElement.dataset.schedule as Schedule | undefined) ??
    'popular';

  /**
   * Highlights the selected tab immediately and dims the calendar while the
   * matching episodes are on their way. `null` clears the loading state.
   */
  const setBusy = (schedule: Schedule | null) => {
    (Object.keys(tabs) as Schedule[]).forEach(key => {
      const tab = tabs[key];
      const isLoading = schedule === key;
      tab.setAttribute('aria-busy', String(isLoading));
      tab.setAttribute('aria-disabled', String(isLoading));
    });
    [calendar, airingTodayContainer].forEach(element =>
      element.setAttribute('aria-busy', String(schedule !== null)),
    );
    if (statusRegion) {
      statusRegion.textContent =
        schedule === null
          ? ''
          : `Loading ${scheduleLabels[schedule]} schedule`;
    }
  };

  const selectTab = (schedule: Schedule) => {
    (Object.keys(tabs) as Schedule[]).forEach(key =>
      tabs[key].setAttribute('aria-selected', String(key === schedule)),
    );
    document.documentElement.dataset.schedule = schedule;
  };

  // Episodes for the whole page lifetime: month, timezone and showlist are
  // fixed here, so a schedule only needs to be fetched once.
  const cache = new Map<Schedule, Episode[]>();
  let currentSchedule: Schedule | null = null;
  let pendingSchedule: Schedule | null = null;
  let activeRequest: AbortController | null = null;
  let requestId = 0;

  const showSchedule = async (schedule: Schedule) => {
    // Ignore clicks on the tab that is already loading, or on the schedule
    // that is already on screen.
    if (pendingSchedule === schedule) {
      return;
    }
    if (pendingSchedule === null && schedule === currentSchedule) {
      return;
    }

    activeRequest?.abort();
    activeRequest = null;

    const cached = cache.get(schedule);
    if (cached) {
      pendingSchedule = null;
      selectTab(schedule);
      setBusy(null);
      populateDates(cached, schedule === 'premieres');
      currentSchedule = schedule;
      setSchedule(schedule);
      return;
    }

    const controller = new AbortController();
    activeRequest = controller;
    pendingSchedule = schedule;
    const id = ++requestId;

    selectTab(schedule);
    setBusy(schedule);

    try {
      const episodes = await getShows(
        url,
        schedule,
        schedule === 'user' ? user : undefined,
        schedule === 'user' ? localShowlist : undefined,
        controller.signal,
      );

      // A newer click (or this request's own abort) makes this result stale.
      if (controller.signal.aborted || id !== requestId) {
        return;
      }

      if (episodes === null) {
        // The request failed; getShows has already notified the user. Keep the
        // previous calendar and tab instead of showing an empty month.
        selectTab(currentSchedule ?? renderedSchedule);
        return;
      }

      cache.set(schedule, episodes);
      populateDates(episodes, schedule === 'premieres');
      currentSchedule = schedule;
      setSchedule(schedule);
    } catch (error) {
      if (isAbortError(error)) {
        return;
      }
      throw error;
    } finally {
      if (id === requestId) {
        activeRequest = null;
        pendingSchedule = null;
        setBusy(null);
      }
    }
  };

  const guestWithSavedShows = !user && localShowlist.length > 0;
  const defaultSchedule: Schedule =
    userShowsBtn.hasAttribute('active') || guestWithSavedShows
      ? 'user'
      : 'popular';

  const savedSchedule = getSchedule();
  const canShowUserSchedule = user || localShowlist.length > 0;
  const initialSchedule =
    savedSchedule && (savedSchedule !== 'user' || canShowUserSchedule)
      ? savedSchedule
      : defaultSchedule;

  userShowsBtn.addEventListener('click', () => showSchedule('user'));
  popularShowsBtn.addEventListener('click', () => showSchedule('popular'));
  premieresShowsBtn.addEventListener('click', () => showSchedule('premieres'));

  await showSchedule(initialSchedule);
});
