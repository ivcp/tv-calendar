import { get } from '../utils/ajax';
import { notification } from '../utils/notification';

function assertIsNode(e: EventTarget | null): asserts e is Node {
  if (!e || !('nodeType' in e)) {
    throw new Error(`Node expected`);
  }
}

const debounce = (fn: Function, delay: number = 400) => {
  let timeoutId: NodeJS.Timeout;
  return (...args: unknown[]) => {
    clearTimeout(timeoutId);
    timeoutId = setTimeout(() => {
      fn(args);
    }, delay);
  };
};

const renderResults = async (
  value: string,
  searchResults: HTMLElement,
  page?: number
) => {
  if (value.trim() === '') {
    searchResults.replaceChildren();
    searchResults.classList.add('hidden');
    searchResults.classList.remove('flex');
    return;
  }

  searchResults.replaceChildren();
  searchResults.classList.remove('hidden');
  searchResults.classList.add('flex');
  [...Array(10).keys()].forEach(n => {
    searchResults.insertAdjacentHTML(
      'beforeend',
      `<li class="rounded-lg text-center bg-base-100">
        <div class="skeleton opacity-50 rounded-lg h-10 w-full p-2">       
        </div>    
        </li>${n === 9 ? "<div class='h-11'></div>" : ''}     
        `
    );
  });

  const response = await get(
    `/search?query=${value.trim()}${page ? '&page=' + page : ''}`
  );
  if (response.error) {
    notification(response.messages, 'alert-error');
    return;
  }

  searchResults.replaceChildren();
  const shows = response.body?.result;
  const pagination = response.body?.pagination;

  if (shows && pagination) {
    if (shows.length === 0) {
      searchResults.insertAdjacentHTML(
        'beforeend',
        `<li class="bg-base-100 hover:bg-base-200 rounded-lg">
          <p class="p-2">
          No match found for "${value.trim()}"
          </p>
          </li>
          `
      );
      return;
    }
    shows.forEach(show => {
      searchResults.insertAdjacentHTML(
        'beforeend',
        `<li class="bg-base-100 hover:bg-base-200 rounded-lg">
          <a href="/shows/${show.id}" class="flex justify-between p-2">
          <span class="max-w-80 truncate">${show.name}</span>
          <span>&#8594;</span></a>
          </li>
          `
      );
    });

    searchResults.insertAdjacentHTML(
      'beforeend',
      `<div class="mt-1 join rounded-full text-lg justify-center gap-2">
        <button id="search-prev-btn" class="join-item btn bg-base-100 hover:bg-base-200 min-h-10 h-10" 
        ${pagination.page <= 1 && 'disabled'}>
          «
        </button>
        <button id="search-next-btn" class="join-item btn bg-base-100 hover:bg-base-200 min-h-10 h-10"
        ${pagination.page === pagination.totalPages && 'disabled'}>
          »
        </button>
      </div>
        `
    );

    searchResults
      .querySelector('#search-next-btn')
      ?.addEventListener('click', () => {
        renderResults(value, searchResults, pagination.page + 1);
      });

    searchResults
      .querySelector('#search-prev-btn')
      ?.addEventListener('click', () => {
        renderResults(value, searchResults, pagination.page - 1);
      });
  }
};

export { assertIsNode, debounce, renderResults };
