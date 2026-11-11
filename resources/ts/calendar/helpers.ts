import { Episode, Schedule } from '../types';
import { get } from '../utils/ajax';
import { assertHtmlElement } from '../utils/assertElement';
import { notification } from '../utils/notification';

function getCurrentYearMonth(): string {
  const date = new Date();
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  return `${year}-${month}`;
}

const TODAY_CARD_CLASSES =
  'outline outline-1 outline-warning/40 -outline-offset-2 bg-warning/10';

function markToday(): void {
  const todayCard = document.getElementById(`date-${new Date().getDate()}`);
  assertHtmlElement(todayCard);
  todayCard.classList.add(...TODAY_CARD_CLASSES.split(' '));
  todayCard.setAttribute('aria-current', 'date');
  todayCard
    .querySelector('.date-number')
    ?.classList.add('text-warning', 'font-bold');
}

async function getShows(
  url: string,
  schedule: Schedule,
  userIsActive?: boolean,
  localShowlist?: string[],
  signal?: AbortSignal
): Promise<Episode[] | null> {
  let fullUrl = `${url}&schedule=${schedule}`;
  if (!userIsActive && schedule === 'user') {
    if (localShowlist && localShowlist.length === 0) return [];
    let showsParam = '';
    localShowlist?.forEach(show => (showsParam += `shows[]=${show}&`));
    const params = new URLSearchParams(showsParam);
    fullUrl += `&${params.toString()}`;
  }

  const response = await get(fullUrl, signal);
  if (response.error) {
    notification(response.messages, 'alert-error');
    return null;
  }
  const episodes = response.body?.episodes;
  if (!episodes) {
    return [];
  }
  return episodes;
}

export { getCurrentYearMonth, getShows, markToday, TODAY_CARD_CLASSES };
