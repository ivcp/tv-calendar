import { post, del } from "./ajax";
import { notification } from "./notification";
import { getLocalShowlist } from "./helpers";

document.addEventListener("DOMContentLoaded", () => {
  const addButtons = document.querySelectorAll(".add-show");

  const user = document.querySelector("section").hasAttribute("user");
  const localShowlist = getLocalShowlist();

  addButtons.forEach((btn) => {
    const showId = btn.dataset.showId;
    if (!user && localShowlist.includes(showId)) {
      markAdded(btn);
    }

    btn.addEventListener("click", async (e) => {
      e.preventDefault();
      if (!btn.hasAttribute("added")) {
        if (user) {
          const result = await post(btn);
          if (result.error) {
            notification(result.messages, "alert-error");
            return;
          }
          notification(result.messages, "alert-success");
        } else {
          const localShowlist = getLocalShowlist();
          if (localShowlist.length >= 10) {
            notification(
              [`Maximum of 10 shows reached. Create an account to add more`],
              "alert-error"
            );
            return;
          }
          try {
            window.localStorage.setItem(
              "showlist",
              JSON.stringify([showId, ...localShowlist])
            );
            const title = window.location.pathname.includes("/discover")
              ? btn.parentElement.parentElement
                  .querySelector("p")
                  .textContent.trim()
              : btn.parentElement.previousElementSibling.textContent.trim();

            notification([`${title} added`], "alert-success");
          } catch (error) {
            notification([`something went wrong`], "alert-error");
            return;
          }
        }

        markAdded(btn);
        return;
      }

      if (window.location.pathname.includes("/shows")) {
        if (!confirm(`Remove show from your list?`)) {
          return;
        }
      }

      if (user) {
        const result = await del(btn);
        if (result.error) {
          notification(result.messages, "alert-error");
          return;
        }
        notification(result.messages, "alert-info");
      } else {
        const localShowlist = getLocalShowlist();
        if (!localShowlist.includes(showId)) return;
        try {
          window.localStorage.setItem(
            "showlist",
            JSON.stringify([...localShowlist.filter((id) => id !== showId)])
          );
          const title = window.location.pathname.includes("/discover")
            ? btn.parentElement.parentElement
                .querySelector("p")
                .textContent.trim()
            : btn.parentElement.previousElementSibling.textContent.trim();
          notification([`${title} removed from your list`], "alert-info");
        } catch (error) {
          notification([`something went wrong`], "alert-error");
          return;
        }
      }

      btn.removeAttribute("added");
      if (window.location.pathname.includes("/discover")) {
        btn.querySelector("svg").classList.remove("fill-secondary");
      }
      if (window.location.pathname.includes("/shows")) {
        btn.classList.replace("btn-secondary", "btn-primary");
        btn.parentElement.dataset.tip = "Add to my shows";
        btn.textContent = "Add";
      }
    });
  });
});

const markAdded = (btn) => {
  btn.setAttribute("added", "");
  if (window.location.pathname.includes("/discover")) {
    btn.querySelector("svg").classList.add("fill-secondary");
  }
  if (window.location.pathname.includes("/shows")) {
    btn.classList.replace("btn-primary", "btn-secondary");
    btn.parentElement.dataset.tip = "Remove from my shows";
    btn.textContent = "Remove";
  }
};
