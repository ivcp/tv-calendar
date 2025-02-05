import { post, del } from "./ajax";
import { notification } from "./notification";

document.addEventListener("DOMContentLoaded", () => {
  const addButtons = document.querySelectorAll(".add-show");

  addButtons.forEach((btn) =>
    btn.addEventListener("click", async (e) => {
      e.preventDefault();
      if (!btn.hasAttribute("added")) {
        const result = await post(btn);
        if (result.error) {
          notification(result.messages, "alert-error");
          return;
        }
        notification(result.messages, "alert-success");
        btn.setAttribute("added", "");
        if (window.location.pathname.includes("/discover")) {
          btn.querySelector("svg").classList.add("fill-secondary");
        }
        if (window.location.pathname.includes("/shows")) {
          btn.classList.replace("btn-primary", "btn-secondary");
          btn.textContent = "Remove";
        }
        return;
      }

      if (window.location.pathname.includes("/shows")) {
        if (!confirm(`Remove show from your list?`)) {
          return;
        }
      }

      const result = await del(btn);
      if (result.error) {
        notification(result.messages, "alert-error");
        return;
      }
      notification(result.messages, "alert-info");
      btn.removeAttribute("added");
      if (window.location.pathname.includes("/discover")) {
        btn.querySelector("svg").classList.remove("fill-secondary");
      }
      if (window.location.pathname.includes("/shows")) {
        btn.classList.replace("btn-secondary", "btn-primary");
        btn.textContent = "Add";
      }
    })
  );
});
