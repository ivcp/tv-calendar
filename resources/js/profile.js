import { deleteProfile } from "./ajax";
import { notification } from "./notification";

document.addEventListener("DOMContentLoaded", async () => {
  const deleteProfileBtn = document.querySelector("#delete-account");
  deleteProfileBtn.addEventListener("click", async () => {
    if (!confirm("Are you sure you want to delete your profile?")) {
      return;
    }
    const result = await deleteProfile();
    if (result.error) {
      notification(result.messages, "alert-error");
      return;
    }
    notification(result.messages, "alert-info");
    window.location.href = "/register";
  });
});
