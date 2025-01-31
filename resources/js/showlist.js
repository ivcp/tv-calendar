import { del, get } from "./ajax";
import { notification } from "./notification";

document.addEventListener("DOMContentLoaded", () => {
  let removeButtons = document.querySelectorAll(".remove-show");
  const nextPageBtn = document.querySelector("#next-page-btn");
  const showCountElement = document.querySelector("[data-show-count]");
  const showGrid = document.querySelector("#shows-grid");
  const event = new Event("show-deleted");

  removeButtons.forEach((btn) =>
    btn.addEventListener("click", () => deleteShow(btn))
  );

  showGrid.addEventListener("show-deleted", () => {
    removeButtons = document.querySelectorAll(".remove-show");
    removeButtons.forEach((btn) =>
      btn.addEventListener("click", () => deleteShow(btn))
    );
  });

  async function deleteShow(el) {
    const result = await del(el);
    if (result.error) {
      notification(result.messages, "alert-error");
      return;
    }

    notification(result.messages, "alert-success");

    const params = new URLSearchParams(document.location.search).toString();
    const shows = await get(`/showlist?${params}`);

    showCountElement.textContent = `| ${shows.body.pagination.showCount}`;

    const showCards = shows.body.shows.map(
      (show) => `<article class="flex flex-col items-center lg:h-80 group">
          <div class="card bg-base-100 image-full w-max rounded-lg before:hidden">
            <figure>
              <img
                src="${show.imageMedium}"
                alt="${show.name}"
                loading="lazy"
                decoding="async"
                class="rounded-lg ${
                  show.imageMedium ?? "border-2 border-base-200"
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
          </div>
          <p
            class="mt-1 text-center font-bold break-words max-w-[22ch] max-h-12 text-ellipsis overflow-clip"
          >
            ${show.name}
          </p>
        </article>`
    );

    showGrid.replaceChildren();
    showGrid.innerHTML = showCards.join("");
    showGrid.dispatchEvent(event);

    if (shows.body.pagination.totalPages === shows.body.pagination.page) {
      nextPageBtn.classList.add("btn-disabled");
    }
  }
});
