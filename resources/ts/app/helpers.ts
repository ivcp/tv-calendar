import { get } from '../utils/ajax';
import { notification } from '../utils/notification';
import { Pagination, Show } from '../types';

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

const createNoMatch = (query: string): HTMLLIElement => {
  const item = document.createElement('li');
  item.className = 'bg-base-100 hover:bg-base-200 rounded-lg';

  const message = document.createElement('p');
  message.className = 'p-2';
  message.textContent = `No match found for "${query}"`;

  item.append(message);
  return item;
};

const createSearchResult = (show: Show): HTMLLIElement => {
  const item = document.createElement('li');
  item.className = 'bg-base-100 hover:bg-base-200 rounded-lg';

  const link = document.createElement('a');
  link.className = 'flex justify-between p-2';
  link.href = `/shows/${encodeURIComponent(String(show.id))}`;

  const name = document.createElement('span');
  name.className = 'max-w-80 truncate';
  name.textContent = show.name;

  const arrow = document.createElement('span');
  arrow.textContent = '→';

  link.append(name, arrow);
  item.append(link);
  return item;
};

const createPagination = (
  pagination: Pagination,
  onPage: (page: number) => void,
): HTMLElement => {
  const wrapper = document.createElement('div');
  wrapper.className = 'mt-1 join rounded-full text-lg justify-center gap-2';

  const previous = document.createElement('button');
  previous.type = 'button';
  previous.id = 'search-prev-btn';
  previous.className =
    'join-item btn bg-base-100 hover:bg-base-200 min-h-10 h-10';
  previous.textContent = '«';
  previous.disabled = pagination.page <= 1;
  previous.addEventListener('click', () => onPage(pagination.page - 1));

  const next = document.createElement('button');
  next.type = 'button';
  next.id = 'search-next-btn';
  next.className = 'join-item btn bg-base-100 hover:bg-base-200 min-h-10 h-10';
  next.textContent = '»';
  next.disabled = pagination.page >= pagination.totalPages;
  next.addEventListener('click', () => onPage(pagination.page + 1));

  wrapper.append(previous, next);
  return wrapper;
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
    `/search?query=${encodeURIComponent(value.trim())}${
      page ? `&page=${page}` : ''
    }`
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
      searchResults.replaceChildren(createNoMatch(value.trim()));
      return;
    }

    searchResults.replaceChildren(
      ...shows.map(createSearchResult),
      createPagination(pagination, nextPage =>
        renderResults(value, searchResults, nextPage)
      )
    );
  }
};

export { assertIsNode, debounce, renderResults };
