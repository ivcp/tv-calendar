import { getLocalShowlist } from "./localStorageHelpers";

document.addEventListener("DOMContentLoaded", () => {
  const form =
    document.querySelector("form[action='/register']") ||
    document.querySelector("form[action='/login']");
  const localList = getLocalShowlist();
  let inputs = "";
  localList.forEach(
    (id) => (inputs += `<input type="hidden" name="shows[]" value="${id}" />`)
  );

  if (inputs) {
    form.insertAdjacentHTML("beforeend", inputs);
  }
});
