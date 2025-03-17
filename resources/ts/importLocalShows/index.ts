import { assertHtmlElement } from '../utils/assertElement';
import { getLocalShowlist } from '../utils/localStorageHelpers';

document.addEventListener('DOMContentLoaded', () => {
  const form =
    document.querySelector("form[action='/register']") ||
    document.querySelector("form[action='/login']");
  assertHtmlElement(form);
  const googleBtn = document.getElementById('google-oauth');
  assertHtmlElement(googleBtn);

  const localList = getLocalShowlist();
  let inputs = '';
  let shows = '';
  localList.forEach(id => {
    inputs += `<input type="hidden" name="shows[]" value="${id}" />`;
    shows += `shows[]=${id}&`;
  });

  if (inputs) {
    form.insertAdjacentHTML('beforeend', inputs);
  }

  googleBtn.addEventListener('click', () => {
    const params = new URLSearchParams(shows);
    window.location.href = `/google-oauth?${params.toString()}`;
  });
});
