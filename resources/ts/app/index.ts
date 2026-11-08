import '../../css/app.css';
import {
  assertHtmlElement,
  assertHtmlInputElement,
} from '../utils/assertElement';
import {
  getLocalShowlist,
  setLocalShowList,
  setMakeAccountSeen,
  setTheme,
} from '../utils/localStorageHelpers';
import { assertIsNode, debounce, renderResults } from './helpers';

document.addEventListener('DOMContentLoaded', () => {
  const dropdowns = document.querySelectorAll('.dropdown');
  const searchBtn = document.getElementById('search-btn');
  assertHtmlElement(searchBtn);
  const searchDiv = document.getElementById('search');
  assertHtmlElement(searchDiv);
  const searchBackdrop = document.getElementById('search-backdrop');
  assertHtmlElement(searchBackdrop);
  const searchInput = document.getElementById('search-input');
  assertHtmlInputElement(searchInput);
  const searchResults = document.getElementById('search-results');
  assertHtmlElement(searchResults);
  const themeToggle = document.getElementById('theme-toggle');
  assertHtmlInputElement(themeToggle);

  const root = document.documentElement;
  const lightTheme = root.dataset.themeLight ?? 'tvlight';
  const darkTheme = root.dataset.themeDark ?? 'dim';

  themeToggle.checked = root.getAttribute('data-theme') === lightTheme;

  themeToggle.addEventListener('change', () => {
    const isLight = themeToggle.checked;
    setTheme(isLight);
    root.setAttribute('data-theme', isLight ? lightTheme : darkTheme);
  });

  const hasLocalShowlist = window.localStorage.getItem('showlist');
  if (!hasLocalShowlist) {
    setLocalShowList([]);
    setMakeAccountSeen(false);
  }
  const localList = hasLocalShowlist ? getLocalShowlist() : [];
  if (hasLocalShowlist && localList.length > 10) {
    setLocalShowList([...localList].slice(0, 10));
  }

  const hideSearchResults = () => {
    searchResults.replaceChildren();
    searchInput.value = '';
    searchResults.classList.add('hidden');
    searchResults.classList.remove('flex');
  };

  document.addEventListener('click', ({ target }) => {
    assertIsNode(target);
    let dropDownTarget = false;
    dropdowns.forEach(dropdown => {
      if (dropdown.contains(target)) {
        dropDownTarget = true;
      }
    });
    const t = target as HTMLElement;
    if (
      !searchDiv.contains(t) &&
      t.id !== 'search-next-btn' &&
      t.id !== 'search-prev-btn'
    ) {
      hideSearchResults();
    }

    if (!dropDownTarget) {
      dropdowns.forEach(d => d.removeAttribute('open'));
    }
  });

  searchBtn.addEventListener('click', () => {
    searchDiv.classList.remove('hidden');
    searchDiv.classList.add('flex');
    searchBackdrop.classList.remove('hidden');
    searchInput.focus();
  });

  searchBackdrop.addEventListener('click', () => {
    searchDiv.classList.add('hidden');
    searchDiv.classList.remove('flex');
    searchBackdrop.classList.add('hidden');
  });

  document.addEventListener('keyup', e => {
    if (e.key === 'Escape') {
      hideSearchResults();
    }
  });

  const debouncedSearch = debounce((query: string[]) => {
    renderResults(query[0], searchResults);
  });

  searchInput.addEventListener('input', async ({ target }) => {
    const input = target as HTMLInputElement;
    debouncedSearch(input.value);
  });
});
