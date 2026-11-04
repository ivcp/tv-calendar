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

  const tabs = [userShowsBtn, popularShowsBtn, premieresShowsBtn];

  const activateTab = (activeTab: HTMLElement) => {
    for (const tab of tabs) {
      const isActive = tab === activeTab;
      tab.classList.toggle('tab-active', isActive);
      tab.classList.toggle('bg-primary', isActive);
      tab.classList.toggle('text-primary-content', isActive);
    }
  };

  const guestWithSavedShows = !user && localShowlist.length > 0;

  if (userShowsBtn.hasAttribute('active') || guestWithSavedShows) {
    const episodes = await getShows(url, 'user', user, localShowlist);
    populateDates(episodes);
    activateTab(userShowsBtn);
  } else {
    const episodes = await getShows(url, 'popular');
    populateDates(episodes);
    activateTab(popularShowsBtn);
  }
  popularShowsBtn.addEventListener('click', async () => {
    const episodes = await getShows(url, 'popular');
    populateDates(episodes);
    activateTab(popularShowsBtn);
  });
  premieresShowsBtn.addEventListener('click', async () => {
    const episodes = await getShows(url, 'premieres');
    populateDates(episodes, true);
    activateTab(premieresShowsBtn);
  });
  userShowsBtn.addEventListener('click', async () => {
    const episodes = await getShows(url, 'user', user, localShowlist);
    populateDates(episodes);
    activateTab(userShowsBtn);
  });
});
