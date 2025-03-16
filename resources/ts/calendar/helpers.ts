import { Episode } from '../types';
import { get } from '../utils/ajax';
import { assertHtmlElement } from '../utils/assertElement';
import { notification } from '../utils/notification';

function getCurrentYearMonth(): string {
  const date = new Date();
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  return `${year}-${month}`;
}

function markToday(): void {
  const todayCard = document.getElementById(`#date-${new Date().getDate()}`);
  assertHtmlElement(todayCard);
  todayCard.classList.add(
    'outline',
    'outline-2',
    'outline-warning',
    '-outline-offset-2'
  );
}

async function getShows(
  url: string,
  schedule: 'user' | 'popular',
  userIsActive?: boolean,
  localShowlist?: string[]
): Promise<Episode[]> {
  let fullUrl = `${url}&schedule=${schedule}`;
  if (!userIsActive && schedule === 'user') {
    if (localShowlist && localShowlist.length === 0) return [];
    let showsParam = '';
    localShowlist?.forEach(show => (showsParam += `shows[]=${show}&`));
    const params = new URLSearchParams(showsParam);
    fullUrl += `&${params.toString()}`;
  }

  const response = await get(fullUrl);
  if (response.error) {
    notification(response.messages, 'alert-error');
    return [];
  }
  const episodes = response.body?.episodes;
  if (!episodes) {
    return [];
  }
  return episodes;
}

export { getCurrentYearMonth, getShows, markToday };
