const getLocalShowlist = () =>
  JSON.parse(window.localStorage.getItem("showlist"));

////
const setLocalShowList = (list) =>
  window.localStorage.setItem("showlist", JSON.stringify(list));

const setMakeAccountSeen = (isSeen) =>
  window.localStorage.setItem(
    "make-account-prompt-seen",
    JSON.stringify(isSeen)
  );

const makeAccountSeen = () =>
  JSON.parse(window.localStorage.getItem("make-account-prompt-seen"));

export {
  getLocalShowlist,
  setLocalShowList,
  setMakeAccountSeen,
  makeAccountSeen,
};
