import {
  deleteProfile,
  resendEmail,
  setStartOfWeekSunday,
  enableNotifications,
  disableNotifications,
  setNotificationTime,
} from '../utils/ajax';
import {
  assertHtmlElement,
  assertFormElement,
  assertButtonElement,
} from '../utils/assertElement';
import { notification } from '../utils/notification';

document.addEventListener('DOMContentLoaded', async () => {
  const deleteProfileBtn = document.getElementById('delete-account');
  assertHtmlElement(deleteProfileBtn);
  const resendEmailBtn = document.getElementById('email-resend');
  const weekStartCheckbox = document.getElementById('weekstart');
  const notificationTimeSelect = document.getElementById('notification-time');
  const notificationsToggle = document.getElementById('notifications-toggle');
  const enableNotificationsForm = document.getElementById(
    'enable-notifications-form'
  );
  const ntfySubmitBtn = document.getElementById('ntfy-submit-btn');

  const notificationsSettingsContent = document.getElementById(
    'notifications-settings-content'
  );
  const loadingBars = document.createElement('span');
  loadingBars.classList.add('loading', 'loading-bars', 'loading-lg', 'mx-auto');

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
    t.disabled = true;
    const response = await setStartOfWeekSunday(t.checked);
    if (response.error) {
      t.disabled = false;
      notification(response.messages, 'alert-error');
      return;
    }
    t.disabled = false;
    notification(response.messages, 'alert-success');
  });

  notificationTimeSelect?.addEventListener('change', async ({ target }) => {
    const t = target as HTMLInputElement;
    t.disabled = true;
    const response = await setNotificationTime(t.value);
    if (response.error) {
      t.disabled = false;
      notification(response.messages, 'alert-error');
      return;
    }
    t.disabled = false;
    notification(response.messages, 'alert-success');
  });

  notificationsToggle?.addEventListener('change', async ({ target }) => {
    const t = target as HTMLInputElement;

    const ntfyEnabled = t.dataset.ntfyEnabled;
    if (ntfyEnabled === undefined) {
      return;
    }
    if (ntfyEnabled === 'false') {
      t.checked
        ? notificationsSettingsContent?.classList.remove('hidden')
        : notificationsSettingsContent?.classList.add('hidden');
    }

    if (ntfyEnabled === 'true') {
      t.disabled = true;
      if (
        confirm(
          `Are you sure you want to disable ntfy notifications?\n\nThis action will delete the current topic ID and notification credentials.`
        )
      ) {
        notificationsSettingsContent?.insertAdjacentElement(
          'beforeend',
          loadingBars
        );
        const response = await disableNotifications();
        if (response.error) {
          t.checked = true;
          t.disabled = false;
          notificationsSettingsContent?.removeChild(loadingBars);
          notification(response.messages, 'alert-error');
          return;
        }
        notificationsSettingsContent?.removeChild(loadingBars);
        notification(response.messages, 'alert-success');
        //sleep to show success message
        new Promise(resolve => setTimeout(resolve, 250));
        //refresh the page
        window.location.reload();
        return;
      }
      t.disabled = false;
      t.checked = true;
      return;
    }
  });

  enableNotificationsForm?.addEventListener('submit', async e => {
    e.preventDefault();
    assertFormElement(enableNotificationsForm);
    const formData = new FormData(enableNotificationsForm);

    const password = formData.get('notificationsPassword');
    const confirmPassword = formData.get('confirmNotificationsPassword');

    if (
      password === null ||
      typeof password !== 'string' ||
      password.trim() === ''
    ) {
      return;
    }
    if (
      confirmPassword === null ||
      typeof confirmPassword !== 'string' ||
      confirmPassword.trim() === ''
    ) {
      return;
    }

    notificationsSettingsContent?.insertAdjacentElement(
      'beforeend',
      loadingBars
    );
    assertButtonElement(ntfySubmitBtn);
    ntfySubmitBtn.disabled = true;

    const response = await enableNotifications(password, confirmPassword);

    if (response.error) {
      ntfySubmitBtn.disabled = false;
      notificationsSettingsContent?.removeChild(loadingBars);
      notification(response.messages.flat(), 'alert-error');
      return;
    }
    notificationsSettingsContent?.removeChild(loadingBars);

    notification(response.messages, 'alert-success');
    //sleep to show success message
    new Promise(resolve => setTimeout(resolve, 250));
    //refresh the page
    window.location.reload();
  });
});
