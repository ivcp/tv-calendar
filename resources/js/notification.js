export async function notification(messages, type) {
  const alertDiv = document.querySelector("#alert");
  const message = messages.join(". ");
  const notificationElement = createNotificationElement(message, type);
  alertDiv.insertAdjacentElement("beforeend", notificationElement);
  await sleep(10);
  notificationElement.classList.remove("-translate-y-6");
  notificationElement.classList.remove("opacity-0");
  await sleep(1800);
  notificationElement.classList.add("opacity-0");
  await sleep(200);
  notificationElement.remove();
}

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

const createNotificationElement = (message, type) => {
  const notificationElement = document.createElement("div");
  notificationElement.classList.add(
    "alert",
    "shadow-md",
    "-translate-y-6",
    "opacity-0",
    "transition-all",
    "duration-200",
    type
  );
  notificationElement.role = "alert";
  notificationElement.insertAdjacentHTML("beforeend", notificationIcon(type));
  notificationElement.insertAdjacentHTML(
    "beforeend",
    `<span class="row-start-1 row-end-1 place-self-start text-left first-letter:uppercase">${message}</span>`
  );

  return notificationElement;
};

const notificationIcon = (type) => {
  let path;
  switch (type) {
    case "alert-success":
      path = `d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"`;
      break;
    case "alert-error":
      path = `d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"`;
      break;
    case "alert-info":
      path = `d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"`;
      break;
  }

  return `<svg
    xmlns="http://www.w3.org/2000/svg"
    fill="none"
    viewBox="0 0 24 24"
    class="h-6 w-6 shrink-0 stroke-current row-start-1 row-end-1 place-self-start">
      <path
        stroke-linecap="round"
        stroke-linejoin="round"
        stroke-width="2"
        ${path}
      />
  </svg>`;
};
