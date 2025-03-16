import { assertHtmlElement } from '../utils/assertElement';
import { getLocalShowlist } from '../utils/localStorageHelpers';

document.addEventListener('DOMContentLoaded', () => {
  const form =
    document.querySelector("form[action='/register']") ||
    document.querySelector("form[action='/login']");
  assertHtmlElement(form);
  const localList = getLocalShowlist();
  let inputs = '';
  localList.forEach(
    id => (inputs += `<input type="hidden" name="shows[]" value="${id}" />`)
  );

  if (inputs) {
    form.insertAdjacentHTML('beforeend', inputs);
  }
});
