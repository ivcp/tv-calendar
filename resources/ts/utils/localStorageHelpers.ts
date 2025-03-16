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
    JSON.stringify(isSeen)
  );

const makeAccountSeen = (): boolean => {
  const seen = window.localStorage.getItem('make-account-prompt-seen');
  if (!seen) {
    return false;
  }
  return JSON.parse(seen) as boolean;
};

export {
  getLocalShowlist,
  setLocalShowList,
  setMakeAccountSeen,
  makeAccountSeen,
};
