const debounce = (fn, delay = 400) => {
  let timeoutId;
  return (...args) => {
    clearTimeout(timeoutId);
    timeoutId = setTimeout(() => {
      fn(args);
    }, delay);
  };
};

export default debounce;
