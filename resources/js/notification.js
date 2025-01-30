export async function notification(messages, type) {
  const alert = document.querySelector(".alert");
  alert.querySelector("span").textContent = messages.join(". ");
  alert.classList.remove("hidden");
  alert.classList.add(type);
  await sleep(2000);
  alert.classList.add("hidden");
  alert.classList.remove(type);
}

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}
