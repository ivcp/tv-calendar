import { Pagination, Show } from '../types';
const NoEpImageSvgPath = require('../../images/no-img-ep.svg') as string;

const renderShowList = (
  body: { pagination: Pagination; shows: Show[] },
  showCountElement: Element,
  showGrid: HTMLElement,
  paginationElement: HTMLElement | null,
  nextPageBtn: HTMLElement | null,
  event: Event
) => {
  showCountElement.textContent = `| ${body.pagination?.showCount}`;

  if (body.pagination.showCount === 0) {
    const genre = body.pagination.genre;
    const isGenre = typeof genre === 'string' && genre !== 'All';

    showGrid.innerHTML = noShowsHtml(isGenre, body);
    paginationElement?.remove();
    return;
  }

  const showCards = body.shows.map(show => showCardHtml(show));

  showGrid.replaceChildren();
  showGrid.innerHTML = showCards.join('');
  showGrid.dispatchEvent(event);

  if (body.pagination.totalPages === body.pagination.page && nextPageBtn) {
    nextPageBtn.classList.add('btn-disabled');
  }
};

const noShowsHtml = (
  isGenre: boolean,
  body: { pagination: Pagination; shows: Show[] }
) => `
           <p class="text-center lg:col-start-3 text-lg mt-12 uppercase">
            ${
              isGenre
                ? 'You have no shows in ' + body.pagination.genre + ' category'
                : 'Your list is empty'
            }
          </p>
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
          <a
            href="/discover${isGenre ? '?genre=' + body.pagination.genre : ''}"
            class="text-center font-bold text-primary lg:col-start-3 text-lg"
            >Add some
            ${isGenre ? '' : 'shows'}</a>
          `;

const showCardHtml = (
  show: Show
): string => `<article class="flex flex-col items-center lg:h-80 group">
              <a 
              class="card bg-base-100 image-full w-max rounded-lg before:hidden"  
              href="/shows/${show.id}"
              >
                <figure class="w-[210px] h-[295px] skeleton rounded-lg">
                  <img
                    src="${show.imageMedium ?? NoEpImageSvgPath}"
                    alt="${show.name}"
                    loading="lazy"
                    decoding="async"
                    onload="this.classList.add('bg-base-200')"
                    class="rounded-lg ${
                      show.imageMedium ?? 'border-2 border-base-200'
                    }"
                  />
                </figure>
                <button
                    class="btn btn-link btn-secondary justify-self-end p-2 hidden group-hover:block remove-show"
                    data-show-id="${show.id}"
                  >
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
                    <span class="sr-only">remove show</span>
                </button>
              </a>
              <p
                class="mt-1 text-center font-bold break-words max-w-[22ch] max-h-12 text-ellipsis overflow-clip"
              >
                ${show.name}
              </p>
            </article>`;

export { renderShowList };
