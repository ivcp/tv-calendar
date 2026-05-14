import { ConsentValue } from '../types';

const getLocalShowlist = (): string[] => {
  const showlist = window.localStorage.getItem('showlist');
  if (showlist) {
    return JSON.parse(showlist) as string[];
  }
  return [];
};

const setLocalShowList = (list: string[]) =>
  window.localStorage.setItem('showlist', JSON.stringify(list));

const setMakeAccountSeen = (isSeen: boolean) =>
  window.localStorage.setItem(
    'make-account-prompt-seen',
    JSON.stringify(isSeen),
  );

const isMakeAccountSeen = (): boolean => {
  const seen = window.localStorage.getItem('make-account-prompt-seen');
  if (!seen) {
    return false;
  }
  return JSON.parse(seen) as boolean;
};

const getSavedTheme = (): boolean | null => {
  const savedTheme = localStorage.getItem('theme-light');
  if (!savedTheme) {
    return null;
  }
  return JSON.parse(savedTheme) as boolean;
};

const setTheme = (isLight: boolean) =>
  localStorage.setItem('theme-light', JSON.stringify(isLight));

const setCookieConsent = (cookieConsent: ConsentValue) =>
  window.localStorage.setItem('cookie_consent', JSON.stringify(cookieConsent));

const getCookieConsent = (): ConsentValue | null => {
  const consent = localStorage.getItem('cookie_consent');
  if (!consent) {
    return null;
  }
  return JSON.parse(consent);
};

export {
  getLocalShowlist,
  setLocalShowList,
  setMakeAccountSeen,
  isMakeAccountSeen,
  getSavedTheme,
  setTheme,
  setCookieConsent,
  getCookieConsent,
};
