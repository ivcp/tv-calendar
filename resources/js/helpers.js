const getLocalShowlist = () =>
  JSON.parse(window.localStorage.getItem("showlist"));

export { getLocalShowlist };
