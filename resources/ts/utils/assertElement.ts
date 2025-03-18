function assertHtmlElement(el: unknown): asserts el is HTMLElement {
  if (!(el instanceof HTMLElement))
    throw new Error(
      `Expected element to be an HTMLElement, was ${
        (el && el.constructor && el.constructor.name) || el
      }`
    );
}
function assertHtmlInputElement(el: unknown): asserts el is HTMLInputElement {
  if (!(el instanceof HTMLInputElement))
    throw new Error(
      `Expected element to be an HTMLInputElement, was ${
        (el && el.constructor && el.constructor.name) || el
      }`
    );
}

function assertDialogElement(el: unknown): asserts el is HTMLDialogElement {
  if (!(el instanceof HTMLDialogElement))
    throw new Error(
      `Expected element to be an HTMLDialogElement, was ${
        (el && el.constructor && el.constructor.name) || el
      }`
    );
}
export { assertHtmlElement, assertHtmlInputElement, assertDialogElement };
