const markAdded = (btn: Element) => {
  btn.setAttribute('added', '');
  if (window.location.pathname.includes('/discover')) {
    const svg = btn.querySelector('svg');
    if (svg) svg.classList.add('fill-secondary');
  }
  if (window.location.pathname.includes('/shows')) {
    btn.classList.replace('btn-primary', 'btn-secondary');
    const parent = btn.parentElement;
    if (parent) parent.dataset.tip = 'Remove from my shows';
    btn.textContent = 'Remove';
  }
};

const getShowTitle = (btn: Element): string => {
  const showListTitle =
    btn?.parentElement?.previousElementSibling?.textContent?.trim();
  const discoverTitle = btn.parentElement?.parentElement
    ?.querySelector('p')
    ?.textContent?.trim();
  const title = window.location.pathname.includes('/discover')
    ? discoverTitle
    : showListTitle;
  if (!title) {
    return '';
  }
  return title;
};

export { markAdded, getShowTitle };
