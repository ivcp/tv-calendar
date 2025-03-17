import { removeShow, get } from '../utils/ajax';
import { notification } from '../utils/notification';
import {
  getLocalShowlist,
  makeAccountSeen,
  setLocalShowList,
} from '../utils/localStorageHelpers';
import { showCreateAccountPrompt, sortShows } from './helpers';
import { renderShowList } from './renderShowList';
import { assertHtmlElement } from '../utils/assertElement';

const activeSortClasses = ['tab-active', 'bg-primary', 'text-primary-content'];

document.addEventListener('DOMContentLoaded', async () => {
  let removeButtons = document.querySelectorAll('.remove-show');
  const nextPageBtn = document.getElementById('next-page-btn');
  const showCountElement = document.querySelector('[data-show-count]');
  if (!showCountElement) {
    throw new Error(`Expected element not to be null`);
  }
  const showGrid = document.getElementById('shows-grid');
  assertHtmlElement(showGrid);
  const paginationElement = document.getElementById('pagination');
  const event = new Event('redraw-cards');
  const sortBtns = document.querySelectorAll('#sort a');

  const user = document.querySelector('section')?.hasAttribute('user');
  const localShowlist = getLocalShowlist();

  removeButtons.forEach(btn =>
    btn.addEventListener('click', e => {
      e.preventDefault();
      deleteShow(btn);
    })
  );

  showGrid.addEventListener('redraw-cards', () => {
    removeButtons = document.querySelectorAll('.remove-show');
    removeButtons.forEach(btn =>
      btn.addEventListener('click', e => {
        e.preventDefault();
        deleteShow(btn);
      })
    );
  });

  if (!user && localShowlist.length > 0) {
    showGrid.replaceChildren();
    let showsParam = '';
    localShowlist.forEach(show => (showsParam += `shows[]=${show}&`));
    const params = new URLSearchParams(showsParam);
    const result = await get(`/showlist?${params.toString()}`);
    if (result.error) {
      notification(result.messages, 'alert-error');
      return;
    }
    const body = result.body;
    if (body) {
      body.shows = [...body.shows].sort(
        (a, b) =>
          localShowlist.indexOf(a.id.toString()) -
          localShowlist.indexOf(b.id.toString())
      );
      renderShowList(
        body,
        showCountElement,
        showGrid,
        paginationElement,
        nextPageBtn,
        event
      );

      sortBtns.forEach(btn =>
        btn.addEventListener('click', e => {
          e.preventDefault();
          sortBtns.forEach(b => b.classList.remove(...activeSortClasses));
          const cards = showGrid.querySelectorAll('article');

          sortShows(btn, cards, showGrid, activeSortClasses);
        })
      );

      if (!makeAccountSeen() && localShowlist.length > 0) {
        showCreateAccountPrompt();
      }
    }
  }

  const deleteShow = async (el: Element) => {
    const showId = el.getAttribute('data-show-id');
    const title = el.parentElement?.parentElement
      ?.querySelector('p')
      ?.textContent?.trim();
    if (showId) {
      if (!confirm(`Remove "${title}" from your list?`)) {
        return;
      }

      if (!user) {
        let localShowlist = getLocalShowlist();
        try {
          setLocalShowList([...localShowlist.filter(id => id !== showId)]);
        } catch (error) {
          notification([`something went wrong`], 'alert-error');
          return;
        }

        el.parentElement?.parentElement?.remove();
        localShowlist = getLocalShowlist();
        showCountElement.textContent = `| ${localShowlist.length}`;
        notification([`${title} removed from your list`], 'alert-info');
        return;
      }

      const result = await removeShow(showId);
      if (result.error) {
        notification(result.messages, 'alert-error');
        return;
      }
      notification(result.messages, 'alert-info');

      const params = new URLSearchParams(document.location.search);
      const response = await get(`/showlist?${params.toString()}`);
      if (response.error) {
        if (params.has('page')) {
          const paramsPage = params.get('page');
          if (paramsPage) {
            const currentPage = +paramsPage;
            params.set('page', (currentPage - 1).toString());
            window.location.href = `/showlist?${params.toString()}`;
          }
        }
        return;
      }
      if (response.body) {
        renderShowList(
          response.body,
          showCountElement,
          showGrid,
          paginationElement,
          nextPageBtn,
          event
        );
      }
    }
  };
});
