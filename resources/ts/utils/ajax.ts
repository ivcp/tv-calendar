import { Result } from '../types';

async function addShow(showId: string): Promise<Result> {
  try {
    return await getResult(`/showlist`, 'POST', { showId });
  } catch (error) {
    return { error: true, messages: ['something went wrong'] };
  }
}

async function get(url: string): Promise<Result> {
  try {
    return await getResult(url, 'GET');
  } catch (error) {
    return { error: true, messages: ['something went wrong'] };
  }
}

async function removeShow(showId: string): Promise<Result> {
  const method = 'DELETE';
  try {
    return await getResult(`/showlist/${showId}`, method, {
      _METHOD: method,
    });
  } catch (error) {
    return { error: true, messages: ['something went wrong'] };
  }
}

async function resendEmail(): Promise<Result> {
  try {
    return await getResult(`/verify`, 'POST');
  } catch (error) {
    return { error: true, messages: ['something went wrong'] };
  }
}
async function deleteProfile(): Promise<Result> {
  try {
    return await getResult(`/profile`, 'DELETE', {
      _METHOD: 'DELETE',
    });
  } catch (error) {
    return { error: true, messages: ['something went wrong'] };
  }
}
async function setStartOfWeekSunday(start: boolean): Promise<Result> {
  try {
    return await getResult(`/profile`, 'PATCH', {
      _METHOD: 'PATCH',
      startOfWeekSunday: start,
    });
  } catch (error) {
    return { error: true, messages: ['something went wrong'] };
  }
}

async function setNotificationTime(time: string): Promise<Result> {
  try {
    return await getResult(`/profile`, 'PATCH', {
      _METHOD: 'PATCH',
      notificationTime: time,
    });
  } catch (error) {
    return { error: true, messages: ['something went wrong'] };
  }
}

async function enableNotifications(
  password: string,
  confirmPassword: string
): Promise<Result> {
  try {
    return await getResult(`/profile`, 'PATCH', {
      _METHOD: 'PATCH',
      notificationsPassword: password,
      confirmNotificationsPassword: confirmPassword,
    });
  } catch (error) {
    return { error: true, messages: ['something went wrong'] };
  }
}

async function disableNotifications(): Promise<Result> {
  try {
    return await getResult(`/profile`, 'PATCH', {
      _METHOD: 'PATCH',
      disableNotifications: true,
    });
  } catch (error) {
    return { error: true, messages: ['something went wrong'] };
  }
}

async function setNotificationEnabled(
  showId: string,
  notificationsEnabled: boolean
): Promise<Result> {
  try {
    return await getResult(`/showlist/${showId}`, 'PATCH', {
      _METHOD: 'PATCH',
      notificationsEnabled,
    });
  } catch (error) {
    return { error: true, messages: ['something went wrong'] };
  }
}

async function getResult(
  url: string,
  method: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE',
  body: object | undefined = undefined
): Promise<Result> {
  const response = await fetch(url, {
    method: method !== 'GET' ? 'POST' : method,
    headers: {
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
    },
    body:
      body || url === '/verify'
        ? JSON.stringify({
            ...body,
            ...getCsrfFields(),
          })
        : null,
  });
  if (!response.ok) {
    if ([400, 403, 404, 422].includes(response.status)) {
      const json = await response.json();
      if (response.status === 422) {
        return {
          error: true,
          messages:
            url === '/profile'
              ? Object.values(json.errors)
              : json.errors.showId,
        };
      }
      return { error: true, messages: [json.msg] };
    }
    return { error: true, messages: ['something went wrong'] };
  }

  const json = await response.json();
  if (method === 'GET') {
    return { error: false, messages: [], body: json };
  }
  return { error: false, messages: [json.msg] };
}

function getCsrfFields(): object {
  const csrfNameField = document.querySelector('#csrfName') as HTMLMetaElement;
  const csrfValueField = document.querySelector(
    '#csrfValue'
  ) as HTMLMetaElement;
  if (csrfNameField && csrfValueField) {
    const csrfNameKey = csrfNameField.getAttribute('name');
    const csrfName = csrfNameField.content;
    const csrfValueKey = csrfValueField.getAttribute('name');
    const csrfValue = csrfValueField.content;
    if (csrfNameKey && csrfValueKey) {
      return {
        [csrfNameKey]: csrfName,
        [csrfValueKey]: csrfValue,
      };
    }
  }
  return {};
}

export {
  addShow,
  removeShow,
  get,
  resendEmail,
  deleteProfile,
  setStartOfWeekSunday,
  enableNotifications,
  disableNotifications,
  setNotificationTime,
  setNotificationEnabled,
};
