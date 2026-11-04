import { Schedule } from '../types';
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

  const activateTab = (schedule: Schedule) => {
    document.documentElement.dataset.schedule = schedule;
  };

  const showSchedule = async (schedule: Schedule) => {
    const episodes =
      schedule === 'user'
        ? await getShows(url, schedule, user, localShowlist)
        : await getShows(url, schedule);
    populateDates(episodes, schedule === 'premieres');
    activateTab(schedule);
    setSchedule(schedule);
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

  await showSchedule(initialSchedule);

  userShowsBtn.addEventListener('click', () => showSchedule('user'));
  popularShowsBtn.addEventListener('click', () => showSchedule('popular'));
  premieresShowsBtn.addEventListener('click', () => showSchedule('premieres'));
});
