import {
  getLocalShowlist,
  setMakeAccountSeen,
} from '../utils/localStorageHelpers';

const sortShows = (
  btn: Element,
  cards: NodeListOf<HTMLElement>,
  showGrid: HTMLElement,
  activeSortClasses: string[]
) => {
  let sorted;
  switch (btn.getAttribute('id')) {
    case 'sort-alphabetical':
      sorted = [...cards].sort((a, b) => {
        const aText = a.querySelector('p')?.textContent?.trim();
        const bText = b.querySelector('p')?.textContent?.trim();
        if (!aText || !bText) {
          return 0;
        }
        if (aText < bText) return -1;
        if (aText > bText) return 1;
        return 0;
      });
      showGrid.replaceChildren();
      sorted.forEach(e => showGrid.insertAdjacentElement('beforeend', e));
      btn.classList.add(...activeSortClasses);
      break;
    case 'sort-added':
      const localShowlist = getLocalShowlist();

      sorted = [...cards].sort((a, b) => {
        const aShowId = a
          .querySelector('.remove-show')
          ?.getAttribute('data-show-id') as string;
        const bShowId = b
          .querySelector('.remove-show')
          ?.getAttribute('data-show-id') as string;
        return localShowlist.indexOf(aShowId) - localShowlist.indexOf(bShowId);
      });
      showGrid.replaceChildren();
      sorted.forEach(e => showGrid.insertAdjacentElement('beforeend', e));
      btn.classList.add(...activeSortClasses);
      break;

    default:
      break;
  }
};

const showCreateAccountPrompt = () => {
  const propmtElement = document.createElement('div');
  propmtElement.classList.add(
    'absolute',
    'top-32',
    'lg:top-48',
    'lg:right-0',
    'mx-2',
    'card',
    'shadow',
    'rounded-lg',
    'bg-base-300',
    'lg:w-96'
  );

  document
    ?.querySelector('body')
    ?.insertAdjacentElement('beforeend', propmtElement);

  propmtElement.insertAdjacentHTML(
    'beforeend',
    `<div class="card-body items-center text-center">
        <h2 class="card-title">Hey there!</h2>
        <p>
          Your shows are currently saved in this browser only. You don't need an
          account to use the app, but by signing up, you can store your shows
          permanently and access your account from any browser or device.
        </p>
        <div class="card-actions justify-end">
          <button id="register-btn" class="btn btn-primary btn-outline">Register</button>
          <button id="dismiss-btn" class="btn btn-outline">Cool</button>
        </div>
      </div>`
  );

  document.querySelector('#register-btn')?.addEventListener('click', () => {
    setMakeAccountSeen(true);
    window.location.href = '/register';
  });
  document.querySelector('#dismiss-btn')?.addEventListener('click', () => {
    setMakeAccountSeen(true);
    propmtElement.classList.add('hidden');
  });
};

export { showCreateAccountPrompt, sortShows };
