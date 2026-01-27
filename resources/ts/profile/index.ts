import {
  deleteProfile,
  resendEmail,
  setStartOfWeekSunday,
  enableNtfyNotifications,
  disableNtfyNotifications,
  setNotificationTime,
  sendTestNtfy,
  enableDiscordNotifications,
  disableDiscordNotifications,
  sendTestDiscord,
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
  const ntfyNotificationsToggle = document.getElementById(
    'ntfy-notifications-toggle',
  );
  const discordNotificationsToggle = document.getElementById(
    'discord-notifications-toggle',
  );
  const enableNtfyNotificationsForm = document.getElementById(
    'enable-ntfy-notifications-form',
  );
  const enableDiscordNotificationsForm = document.getElementById(
    'enable-discord-notifications-form',
  );
  const ntfySubmitBtn = document.getElementById('ntfy-submit-btn');
  const discordSubmitBtn = document.getElementById('discord-submit-btn');
  const testNtfyBtn = document.getElementById('test-ntfy');
  const testDiscordBtn = document.getElementById('test-discord-webhook');

  const ntfyNotificationsSettingsContent = document.getElementById(
    'ntfy-notifications-settings-content',
  );
  const discordNotificationsSettingsContent = document.getElementById(
    'discord-notifications-settings-content',
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

  ntfyNotificationsToggle?.addEventListener('change', async ({ target }) => {
    const t = target as HTMLInputElement;

    const ntfyEnabled = t.dataset.ntfyEnabled;
    if (ntfyEnabled === undefined) {
      return;
    }
    if (ntfyEnabled === 'false') {
      t.checked
        ? ntfyNotificationsSettingsContent?.classList.remove('hidden')
        : ntfyNotificationsSettingsContent?.classList.add('hidden');
    }

    if (ntfyEnabled === 'true') {
      t.disabled = true;
      if (
        confirm(
          `Are you sure you want to disable ntfy notifications?\n\nThis action will delete the current topic ID and notification credentials.`,
        )
      ) {
        ntfyNotificationsSettingsContent?.insertAdjacentElement(
          'beforeend',
          loadingBars,
        );
        const response = await disableNtfyNotifications();
        if (response.error) {
          t.checked = true;
          t.disabled = false;
          ntfyNotificationsSettingsContent?.removeChild(loadingBars);
          notification(response.messages, 'alert-error');
          return;
        }
        ntfyNotificationsSettingsContent?.removeChild(loadingBars);
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

  discordNotificationsToggle?.addEventListener('change', async ({ target }) => {
    const t = target as HTMLInputElement;

    const discordEnabled = t.dataset.discordEnabled;
    if (discordEnabled === undefined) {
      return;
    }
    if (discordEnabled === 'false') {
      t.checked
        ? discordNotificationsSettingsContent?.classList.remove('hidden')
        : discordNotificationsSettingsContent?.classList.add('hidden');
    }

    if (discordEnabled === 'true') {
      t.disabled = true;
      if (confirm(`Are you sure you want to disable Discord notifications?`)) {
        discordNotificationsSettingsContent?.insertAdjacentElement(
          'beforeend',
          loadingBars,
        );
        const response = await disableDiscordNotifications();
        if (response.error) {
          t.checked = true;
          t.disabled = false;
          discordNotificationsSettingsContent?.removeChild(loadingBars);
          notification(response.messages, 'alert-error');
          return;
        }
        discordNotificationsSettingsContent?.removeChild(loadingBars);
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

  enableNtfyNotificationsForm?.addEventListener('submit', async e => {
    e.preventDefault();
    assertFormElement(enableNtfyNotificationsForm);
    const formData = new FormData(enableNtfyNotificationsForm);

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

    ntfyNotificationsSettingsContent?.insertAdjacentElement(
      'beforeend',
      loadingBars,
    );
    assertButtonElement(ntfySubmitBtn);
    ntfySubmitBtn.disabled = true;

    const response = await enableNtfyNotifications(password, confirmPassword);

    if (response.error) {
      ntfySubmitBtn.disabled = false;
      ntfyNotificationsSettingsContent?.removeChild(loadingBars);
      notification(response.messages.flat(), 'alert-error');
      return;
    }
    ntfyNotificationsSettingsContent?.removeChild(loadingBars);

    notification(response.messages, 'alert-success');
    //sleep to show success message
    new Promise(resolve => setTimeout(resolve, 250));
    //refresh the page
    window.location.reload();
  });

  enableDiscordNotificationsForm?.addEventListener('submit', async e => {
    e.preventDefault();
    assertFormElement(enableDiscordNotificationsForm);
    const formData = new FormData(enableDiscordNotificationsForm);

    const discordWebhookUrl = formData.get('discordWebhookUrl');

    if (
      discordWebhookUrl === null ||
      typeof discordWebhookUrl !== 'string' ||
      discordWebhookUrl.trim() === ''
    ) {
      return;
    }

    discordNotificationsSettingsContent?.insertAdjacentElement(
      'beforeend',
      loadingBars,
    );
    assertButtonElement(discordSubmitBtn);
    discordSubmitBtn.disabled = true;

    const response = await enableDiscordNotifications(discordWebhookUrl);

    if (response.error) {
      discordSubmitBtn.disabled = false;
      discordNotificationsSettingsContent?.removeChild(loadingBars);
      notification(response.messages.flat(), 'alert-error');
      return;
    }
    discordNotificationsSettingsContent?.removeChild(loadingBars);

    notification(response.messages, 'alert-success');
    //sleep to show success message
    new Promise(resolve => setTimeout(resolve, 250));
    //refresh the page
    window.location.reload();
  });

  testNtfyBtn?.addEventListener('click', async function ({ target }) {
    const t = target as HTMLButtonElement;
    t.disabled = true;
    const response = await sendTestNtfy();
    if (response.error) {
      t.disabled = false;
      notification(response.messages, 'alert-error');
      return;
    }
    t.disabled = false;
    notification(response.messages, 'alert-success');
  });

  testDiscordBtn?.addEventListener('click', async function ({ target }) {
    const t = target as HTMLButtonElement;
    t.disabled = true;
    const response = await sendTestDiscord();
    if (response.error) {
      t.disabled = false;
      notification(response.messages, 'alert-error');
      return;
    }
    t.disabled = false;
    notification(response.messages, 'alert-success');
  });
});
