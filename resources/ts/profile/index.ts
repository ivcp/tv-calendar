import {
  deleteProfile,
  resendEmail,
  setStartOfWeekSunday,
} from '../utils/ajax';
import { assertHtmlElement } from '../utils/assertElement';
import { notification } from '../utils/notification';

document.addEventListener('DOMContentLoaded', async () => {
  const deleteProfileBtn = document.getElementById('delete-account');
  assertHtmlElement(deleteProfileBtn);
  const resendEmailBtn = document.getElementById('email-resend');
  const weekStartCheckbox = document.getElementById('weekstart');

  deleteProfileBtn.addEventListener('click', async () => {
    if (!confirm('Are you sure you want to delete your profile?')) {
      return;
    }
    const result = await deleteProfile();
    if (result.error) {
      notification(result.messages, 'alert-error');
      return;
    }
    notification(result.messages, 'alert-info');
    window.location.href = '/register';
  });

  if (resendEmailBtn) {
    resendEmailBtn.addEventListener('click', async () => {
      const email = resendEmailBtn.dataset.email;
      if (!confirm(`Resend verification email to ${email}?`)) {
        return;
      }
      const response = await resendEmail();
      if (response.error) {
        notification(response.messages, 'alert-error');
        return;
      }
      notification(response.messages, 'alert-success');
    });
  }

  weekStartCheckbox?.addEventListener('change', async ({ target }) => {
    const t = target as HTMLInputElement;
    const response = await setStartOfWeekSunday(t.checked);
    if (response.error) {
      notification(response.messages, 'alert-error');
      return;
    }
    notification(response.messages, 'alert-success');
  });
});
