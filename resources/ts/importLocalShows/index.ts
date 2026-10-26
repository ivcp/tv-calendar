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
  const params = new URLSearchParams();

  localList.forEach(id => {
    if (!/^\d+$/.test(id)) {
      return;
    }

    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'shows[]';
    input.value = id;
    form.append(input);

    params.append('shows[]', id);
  });

  googleBtn.addEventListener('click', () => {
    window.location.href = `/google-oauth?${params.toString()}`;
  });
});
