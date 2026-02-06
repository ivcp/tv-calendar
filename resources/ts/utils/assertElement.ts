function assertHtmlElement(el: unknown): asserts el is HTMLElement {
  if (!(el instanceof HTMLElement))
    throw new Error(
      `Expected element to be an HTMLElement, was ${
        (el && el.constructor && el.constructor.name) || el
      }`,
    );
}
function assertHtmlInputElement(el: unknown): asserts el is HTMLInputElement {
  if (!(el instanceof HTMLInputElement))
    throw new Error(
      `Expected element to be an HTMLInputElement, was ${
        (el && el.constructor && el.constructor.name) || el
      }`,
    );
}

function assertDialogElement(el: unknown): asserts el is HTMLDialogElement {
  if (!(el instanceof HTMLDialogElement))
    throw new Error(
      `Expected element to be an HTMLDialogElement, was ${
        (el && el.constructor && el.constructor.name) || el
      }`,
    );
}

function assertFormElement(el: unknown): asserts el is HTMLFormElement {
  if (!(el instanceof HTMLFormElement))
    throw new Error(
      `Expected element to be an HTMLFormElement, was ${
        (el && el.constructor && el.constructor.name) || el
      }`,
    );
}

function assertButtonElement(el: unknown): asserts el is HTMLButtonElement {
  if (!(el instanceof HTMLButtonElement))
    throw new Error(
      `Expected element to be an HTMLButtonElement, was ${
        (el && el.constructor && el.constructor.name) || el
      }`,
    );
}

function assertAnchorElement(el: unknown): asserts el is HTMLAnchorElement {
  if (!(el instanceof HTMLAnchorElement))
    throw new Error(
      `Expected element to be an HTMLAnchorElement, was ${
        (el && el.constructor && el.constructor.name) || el
      }`,
    );
}
export {
  assertHtmlElement,
  assertHtmlInputElement,
  assertDialogElement,
  assertFormElement,
  assertButtonElement,
  assertAnchorElement,
};
