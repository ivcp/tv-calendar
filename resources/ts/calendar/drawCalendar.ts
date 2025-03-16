import { assertHtmlElement } from '../utils/assertElement';

function drawCalendar(): void {
  const calendarElement = document.getElementById('calendar');
  assertHtmlElement(calendarElement);
  const sundayStart = document
    .getElementById('days')
    ?.hasAttribute('sunday-start');

  const now = new Date();
  const daysInMonth = new Date(
    now.getFullYear(),
    now.getMonth() + 1,
    0
  ).getDate();

  let firstDayInMonth = new Date(now.getFullYear(), now.getMonth(), 1).getDay();

  if (!sundayStart) {
    if (firstDayInMonth === 0) {
      firstDayInMonth = 7;
    }
  } else {
    firstDayInMonth++;
  }

  [...Array(daysInMonth).keys()].forEach(date => {
    calendarElement.insertAdjacentHTML(
      'beforeend',
      dateCard(
        new Date(now.getFullYear(), now.getMonth(), date + 1),
        firstDayInMonth
      )
    );
  });
}

function dateCard(date: Date, firstDay: number) {
  const isToday = sameDay(date, new Date());
  const dateNumber = date.getDate();
  return `<div
        id="date-${dateNumber}"
        class="skeleton lg:min-h-52 card gap-6 bg-base-300 rounded-lg p-2 lg:p-0
        ${dateNumber === 1 ? 'lg:col-start-' + firstDay : ''}
        ${isToday ? ' outline outline-2 outline-warning -outline-offset-2' : ''}
        "
        >
        <p class="self-end px-3 py-1 text-sm lg:text-base font-semibold">
          <span class="font-normal lg:hidden">${date.toLocaleString('en-us', {
            weekday: 'long',
          })}, </span></span>
          ${dateNumber}<span class="text-xs lg:hidden">${nthNumber(
    dateNumber
  )}</span>
        </p>
        <div class="card-body justify-end gap-2 lg:gap-1 p-2 text-base-300">
        </div>
        </div>`;
}

function nthNumber(number: number) {
  if (number > 3 && number < 21) return 'th';
  switch (number % 10) {
    case 1:
      return 'st';
    case 2:
      return 'nd';
    case 3:
      return 'rd';
    default:
      return 'th';
  }
}

function sameDay(d1: Date, d2: Date): boolean {
  return (
    d1.getFullYear() === d2.getFullYear() &&
    d1.getMonth() === d2.getMonth() &&
    d1.getDate() === d2.getDate()
  );
}

export { drawCalendar };
