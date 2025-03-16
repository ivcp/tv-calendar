import { addShow, removeShow } from '../utils/ajax';
import { notification } from '../utils/notification';
import {
  getLocalShowlist,
  setLocalShowList,
} from '../utils/localStorageHelpers';
import { getShowTitle, markAdded } from './helpers';

document.addEventListener('DOMContentLoaded', () => {
  const addButtons = document.querySelectorAll('.add-show');

  const user = document.querySelector('section')?.hasAttribute('user');
  const localShowlist = getLocalShowlist();

  addButtons.forEach(btn => {
    const showId = btn.getAttribute('data-show-id');
    if (showId) {
      if (!user && localShowlist.includes(showId)) {
        markAdded(btn);
      }

      btn.addEventListener('click', async e => {
        e.preventDefault();
        if (!btn.hasAttribute('added')) {
          if (user) {
            const result = await addShow(showId);
            if (result.error) {
              notification(result.messages, 'alert-error');
              return;
            }
            notification(result.messages, 'alert-success');
          } else {
            const localShowlist = getLocalShowlist();
            if (localShowlist.length >= 10) {
              notification(
                [`Maximum of 10 shows reached. Create an account to add more`],
                'alert-error'
              );
              return;
            }
            try {
              setLocalShowList([showId, ...localShowlist]);
              const title = getShowTitle(btn);
              notification([`${title} added`], 'alert-success');
            } catch (error) {
              notification([`something went wrong`], 'alert-error');
              return;
            }
          }

          markAdded(btn);
          return;
        }

        if (window.location.pathname.includes('/shows')) {
          if (!confirm(`Remove show from your list?`)) {
            return;
          }
        }

        if (user) {
          const result = await removeShow(showId);
          if (result.error) {
            notification(result.messages, 'alert-error');
            return;
          }
          notification(result.messages, 'alert-info');
        } else {
          const localShowlist = getLocalShowlist();
          if (!localShowlist.includes(showId)) return;
          try {
            setLocalShowList([...localShowlist.filter(id => id !== showId)]);
            const title = getShowTitle(btn);
            notification([`${title} removed from your list`], 'alert-info');
          } catch (error) {
            notification([`something went wrong`], 'alert-error');
            return;
          }
        }

        btn.removeAttribute('added');
        if (window.location.pathname.includes('/discover')) {
          const svg = btn.querySelector('svg');
          if (svg) svg.classList.remove('fill-secondary');
        }
        if (window.location.pathname.includes('/shows')) {
          btn.classList.replace('btn-secondary', 'btn-primary');
          const parent = btn.parentElement;
          if (parent) parent.dataset.tip = 'Add to my shows';
          btn.textContent = 'Add';
        }
      });
    }
  });
});
