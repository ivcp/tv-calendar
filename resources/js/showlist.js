import { del } from "./ajax";
import { ShowCount } from "./ShowCount";

document.addEventListener("DOMContentLoaded", () => {
  const removeButtons = document.querySelectorAll(".remove-show");
  const showCountElement = document.querySelector("[data-show-count]");

  const initialShowCount = showCountElement.getAttribute("data-show-count");
  if (!initialShowCount) {
    console.error("no initial show count");
    return;
  }
  const showCount = new ShowCount(+initialShowCount);

  removeButtons.forEach((btn) =>
    btn.addEventListener("click", async () => {
      const result = await del(btn);
      if (result.error) {
        // notify err
        console.log(result.messages);
      }
      showCount.decrement();
      //check null
      btn.parentElement.parentElement.remove();
      showCountElement.textContent = `| ${showCount.showCount}`;
      //notify
      console.log(result.messages);
    })
  );
});
