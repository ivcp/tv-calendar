document.addEventListener("DOMContentLoaded", () => {
  const addButtons = document.querySelectorAll(".add-show");

  addButtons.forEach((btn) =>
    btn.addEventListener("click", () =>
      fetch("/showlist", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-Requested-With": "XMLHttpRequest",
        },
        body: JSON.stringify({
          showId: btn.getAttribute("data-show-id"),
          ...getCsrfFields(),
        }),
      })
    )
  );

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
