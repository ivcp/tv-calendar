import { assertDialogElement, assertHtmlElement } from './assertElement';

export type ConfirmOptions = {
  title: string;
  message?: string;
  confirmLabel?: string;
  cancelLabel?: string;
  danger?: boolean;
};

const DIALOG_ID = 'confirm-dialog';

interface DialogElements {
  dialog: HTMLDialogElement;
  title: HTMLElement;
  message: HTMLElement;
  confirmButton: HTMLButtonElement;
  cancelButton: HTMLButtonElement;
}

let openDialog: { dismiss: () => void } | null = null;

const isDialogSupported = (): boolean =>
  typeof HTMLDialogElement !== 'undefined' &&
  typeof HTMLDialogElement.prototype.showModal === 'function';

const buildDialog = (): DialogElements => {
  document.body.insertAdjacentHTML(
    'beforeend',
    `<dialog
      id="${DIALOG_ID}"
      class="modal"
      aria-labelledby="${DIALOG_ID}-title"
    >
      <div class="modal-box">
        <form method="dialog">
          <button
            id="${DIALOG_ID}-close"
            value="cancel"
            aria-label="Close"
            class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2"
          >
            ✕
          </button>
        </form>
        <h3 id="${DIALOG_ID}-title" class="text-lg font-bold pr-8"></h3>
        <p id="${DIALOG_ID}-message" class="py-4"></p>
        <div class="modal-action">
          <form method="dialog" class="flex justify-end gap-2">
            <button
              id="${DIALOG_ID}-cancel"
              value="cancel"
              class="btn btn-ghost"
            >
              Cancel
            </button>
            <button
              id="${DIALOG_ID}-confirm"
              value="confirm"
              class="btn btn-primary"
            >
              Confirm
            </button>
          </form>
        </div>
      </div>
    </dialog>`
  );

  const dialog = document.getElementById(DIALOG_ID);
  assertDialogElement(dialog);

  const title = dialog.querySelector(`#${DIALOG_ID}-title`);
  assertHtmlElement(title);

  const message = dialog.querySelector(`#${DIALOG_ID}-message`);
  assertHtmlElement(message);

  const confirmButton = dialog.querySelector(`#${DIALOG_ID}-confirm`);
  if (!(confirmButton instanceof HTMLButtonElement)) {
    throw new Error('Expected confirm button to be an HTMLButtonElement');
  }

  const cancelButton = dialog.querySelector(`#${DIALOG_ID}-cancel`);
  if (!(cancelButton instanceof HTMLButtonElement)) {
    throw new Error('Expected cancel button to be an HTMLButtonElement');
  }

  return { dialog, title, message, confirmButton, cancelButton };
};

export async function confirmDialog({
  title,
  message,
  confirmLabel = 'Confirm',
  cancelLabel = 'Cancel',
  danger = false,
}: ConfirmOptions): Promise<boolean> {
  if (!isDialogSupported()) {
    return window.confirm([title, message].filter(Boolean).join('\n\n'));
  }

  // Dialogs cannot be meaningfully stacked here, so a new request dismisses
  // whichever one is still open, as a cancellation.
  openDialog?.dismiss();

  const {
    dialog,
    title: titleElement,
    message: messageElement,
    confirmButton,
    cancelButton,
  } = buildDialog();

  titleElement.textContent = title;
  messageElement.textContent = message ?? '';
  messageElement.classList.toggle('hidden', !message);
  if (message) {
    dialog.setAttribute('aria-describedby', `${DIALOG_ID}-message`);
  }

  confirmButton.textContent = confirmLabel;
  confirmButton.classList.toggle('btn-error', danger);
  confirmButton.classList.toggle('btn-primary', !danger);
  cancelButton.textContent = cancelLabel;

  return new Promise<boolean>(resolve => {
    let settled = false;
    const finish = (confirmed: boolean) => {
      if (settled) {
        return;
      }
      settled = true;
      openDialog = null;
      if (dialog.open) {
        dialog.close();
      }
      dialog.remove();
      resolve(confirmed);
    };

    // Clicking either form button sets `returnValue`, so anything other than
    // "confirm" (Cancel, the close button or Escape) counts as a no.
    dialog.addEventListener('close', () =>
      finish(dialog.returnValue === 'confirm')
    );

    openDialog = { dismiss: () => finish(false) };
    dialog.returnValue = '';
    dialog.showModal();
    // Default to the safe choice, especially for destructive actions.
    cancelButton.focus();
  });
}
