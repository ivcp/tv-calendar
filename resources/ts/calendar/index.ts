import { assertHtmlElement } from '../utils/assertElement';
import {
  getLocalShowlist,
  setLocalShowList,
} from '../utils/localStorageHelpers';
import { drawCalendar } from './drawCalendar';
import { getCurrentYearMonth, getShows, markToday } from './helpers';
import { populateDates } from './populateDates';

const currentMonth = getCurrentYearMonth();
const path = window.location.pathname;
const timeZone = Intl.DateTimeFormat().resolvedOptions().timeZone;

document.addEventListener('DOMContentLoaded', async () => {
  const popularShowsBtn = document.getElementById('popular-shows');
  assertHtmlElement(popularShowsBtn);
  const userShowsBtn = document.getElementById('user-shows');
  assertHtmlElement(userShowsBtn);

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

  const activeClasses = ['tab-active', 'bg-primary', 'text-primary-content'];

  const activateUserBtn = () => {
    popularShowsBtn.classList.remove(...activeClasses);
    userShowsBtn.classList.add(...activeClasses);
  };
  const activatePopularBtn = () => {
    userShowsBtn.classList.remove(...activeClasses);
    popularShowsBtn.classList.add(...activeClasses);
  };

  const guestWithSavedShows = !user && localShowlist.length > 0;

  if (userShowsBtn.hasAttribute('active') || guestWithSavedShows) {
    const episodes = await getShows(url, 'user', user, localShowlist);
    populateDates(episodes);
    activateUserBtn();
  } else {
    const episodes = await getShows(url, 'popular');
    populateDates(episodes);
    activatePopularBtn();
  }
  popularShowsBtn.addEventListener('click', async () => {
    const episodes = await getShows(url, 'popular');
    populateDates(episodes);
    activatePopularBtn();
  });
  userShowsBtn.addEventListener('click', async () => {
    const episodes = await getShows(url, 'user', user, localShowlist);
    populateDates(episodes);
    activateUserBtn();
  });
});
