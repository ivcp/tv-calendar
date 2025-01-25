document.addEventListener("DOMContentLoaded", () => {
  const addButtons = document.querySelectorAll(".add-show");

  addButtons.forEach((btn) =>
    btn.addEventListener("click", () =>
      btn.hasAttribute("added") ? del(btn) : post(btn)
    )
  );

  async function post(el) {
    const response = await fetch("/showlist", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
      body: JSON.stringify({
        showId: el.getAttribute("data-show-id"),
        ...getCsrfFields(),
      }),
    });
  }

  async function del(el) {
    const showId = el.getAttribute("data-show-id");
    const response = await fetch(`/showlist/${showId}`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
      body: JSON.stringify({
        _METHOD: "DELETE",
        ...getCsrfFields(),
      }),
    });
  }

  function getCsrfFields() {
    const csrfNameField = document.querySelector("#csrfName");
    const csrfValueField = document.querySelector("#csrfValue");
    const csrfNameKey = csrfNameField.getAttribute("name");
    const csrfName = csrfNameField.content;
    const csrfValueKey = csrfValueField.getAttribute("name");
    const csrfValue = csrfValueField.content;

    return {
      [csrfNameKey]: csrfName,
      [csrfValueKey]: csrfValue,
    };
  }
});
