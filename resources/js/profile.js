import { deleteProfile, resendEmail } from "./ajax";
import { notification } from "./notification";

document.addEventListener("DOMContentLoaded", async () => {
  const deleteProfileBtn = document.querySelector("#delete-account");
  const resendEmailBtn = document.querySelector("#email-resend");

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

  if (resendEmailBtn) {
    resendEmailBtn.addEventListener("click", async () => {
      const email = resendEmailBtn.dataset.email;
      if (!confirm(`Resend verification email to ${email}?`)) {
        return;
      }
      const response = await resendEmail();
      if (response.error) {
        notification(response.messages, "alert-error");
        return;
      }
      notification(response.messages, "alert-success");
    });
  }
});
