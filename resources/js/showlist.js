import { del } from "./ajax";
import { notification } from "./notification";
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
        notification(result.messages, "alert-error");
        return;
      }
      showCount.decrement();
      //check null
      btn.parentElement.parentElement.remove();
      showCountElement.textContent = `| ${showCount.count}`;
      notification(result.messages, "alert-success");
    })
  );
});
