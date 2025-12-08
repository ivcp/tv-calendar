import { setNotificationEnabled } from './ajax';
import { notification } from './notification';
import setNotificationIcon from './notificationIcons';

export default async function (notificationBtn: HTMLButtonElement) {
  notificationBtn.disabled = true;
  const enabled = notificationBtn.hasAttribute('enabled');
  const showId = notificationBtn.dataset.showId;
  if (showId) {
    const result = await setNotificationEnabled(showId, enabled ? false : true);
    if (result.error) {
      notification(result.messages, 'alert-error');
      notificationBtn.disabled = false;
      return;
    }
    const tooltip = notificationBtn.querySelector('.tooltip');

    if (enabled) {
      tooltip?.remove();
      notificationBtn.insertAdjacentHTML(
        'beforeend',
        setNotificationIcon(!enabled)
      );
      notificationBtn.removeAttribute('enabled');
    } else {
      tooltip?.remove();
      notificationBtn.insertAdjacentHTML(
        'beforeend',
        setNotificationIcon(!enabled)
      );
      notificationBtn.setAttribute('enabled', '');
    }
    notificationBtn.disabled = false;
    notification(result.messages, 'alert-info');
  }
}
