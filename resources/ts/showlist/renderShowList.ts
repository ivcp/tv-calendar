import { Pagination, Show } from '../types';

const renderShowList = (
  body: { pagination: Pagination; shows: Show[] },
  showCountElement: Element,
  showGrid: HTMLElement,
  paginationElement: HTMLElement | null,
  nextPageBtn: HTMLElement | null,
  event: Event,
) => {
  showCountElement.textContent = `| ${body.pagination?.showCount}`;

  if (body.pagination.showCount === 0) {
    const genre = body.pagination.genre;
    const isGenre = typeof genre === 'string' && genre !== 'All';

    showGrid.replaceChildren(noShowsFragment(isGenre, body));
    paginationElement?.remove();
    return;
  }

  const showCards = body.shows.map(show => showCard(show));

  showGrid.replaceChildren(...showCards);
  showGrid.dispatchEvent(event);

  if (body.pagination.totalPages === body.pagination.page && nextPageBtn) {
    nextPageBtn.classList.add('btn-disabled');
  }
};

const noShowsFragment = (
  isGenre: boolean,
  body: { pagination: Pagination; shows: Show[] },
): DocumentFragment => {
  const fragment = document.createDocumentFragment();
  const genre = body.pagination.genre;

  const message = document.createElement('p');
  message.className = 'text-center lg:col-start-3 text-lg mt-12 uppercase';
  message.textContent = isGenre
    ? `You have no shows in ${genre} category`
    : 'Your list is empty';

  const iconWrapper = document.createElement('div');
  iconWrapper.innerHTML = `
          <svg
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            stroke-width="1"
            stroke="currentColor"
            class="size-12 lg:col-start-3"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M6 20.25h12m-7.5-3v3m3-3v3m-10.125-3h17.25c.621 0 1.125-.504 1.125-1.125V4.875c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125Z"
            />
          </svg>
          `;

  const link = document.createElement('a');
  link.className =
    'text-center font-bold text-primary lg:col-start-3 text-lg';
  link.href = isGenre
    ? `/discover?${new URLSearchParams({ genre: genre ?? '' })}`
    : '/discover';
  link.textContent = isGenre ? 'Add some' : 'Add some shows';

  if (iconWrapper.firstElementChild) {
    fragment.append(message, iconWrapper.firstElementChild, link);
  } else {
    fragment.append(message, link);
  }

  return fragment;
};

const showCard = (show: Show): HTMLElement => {
  const article = document.createElement('article');
  article.className = 'flex flex-col items-center lg:h-80 group';

  const link = document.createElement('a');
  link.className = 'card bg-base-100 image-full w-max rounded-lg before:hidden';
  link.href = `/shows/${encodeURIComponent(
    String(show.id),
  )}-${encodeURIComponent(show.slug)}`;

  const figure = document.createElement('figure');
  figure.className = 'w-[210px] h-[295px] rounded-lg';

  if (show.imageMedium) {
    figure.classList.add('skeleton');

    const image = document.createElement('img');
    image.src = show.imageMedium;
    image.alt = show.name;
    image.loading = 'lazy';
    image.decoding = 'async';
    image.className = 'rounded-lg';
    image.addEventListener(
      'load',
      () => image.classList.add('bg-base-200'),
      { once: true },
    );
    figure.append(image);
  } else {
    const placeholder = document.createElement('div');
    placeholder.className =
      'flex h-full w-full flex-col items-center justify-center gap-2 rounded-lg bg-gradient-to-br from-base-200 to-base-300 px-4 text-center ring-1 ring-base-300';
    placeholder.innerHTML = `
      <svg
        xmlns="http://www.w3.org/2000/svg"
        fill="none"
        viewBox="0 0 24 24"
        stroke-width="1.5"
        stroke="currentColor"
        class="size-10 text-base-content/30"
        aria-hidden="true"
      >
        <path
          stroke-linecap="round"
          stroke-linejoin="round"
          d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"
        />
      </svg>
      <p class="text-sm text-base-content/50">No poster available</p>
    `;
    figure.append(placeholder);
  }

  const removeButton = document.createElement('button');
  removeButton.className =
    'btn btn-link btn-secondary justify-self-end p-2 hidden group-hover:block remove-show';
  removeButton.dataset.showId = String(show.id);
  removeButton.innerHTML = `
                    <div class="tooltip" data-tip="Remove from my shows">
                      <svg
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                        stroke="currentColor"
                        class="size-7 stroke-base-content"
                      >
                        <path
                          stroke-linecap="round"
                          stroke-linejoin="round"
                          d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"
                        />
                      </svg>
                    </div>
                    <span class="sr-only">remove show</span>`;

  const name = document.createElement('p');
  name.className =
    'mt-1 text-center font-bold break-words max-w-[22ch] max-h-12 text-ellipsis overflow-clip';
  name.textContent = show.name;

  link.append(figure, removeButton);
  article.append(link, name);
  return article;
};

export { renderShowList };
