import { assertAnchorElement, assertHtmlElement } from '../utils/assertElement';
import { TODAY_CARD_CLASSES } from './helpers';

function drawCalendar(): void {
  const calendarElement = document.getElementById('calendar');
  const currentMonthElement = document.getElementById('current-month');
  const currentYearElement = document.getElementById('current-year');
  const nextMonthBtn = document.getElementById('next-month-btn');
  const prevMonthBtn = document.getElementById('prev-month-btn');
  assertHtmlElement(calendarElement);
  assertHtmlElement(currentMonthElement);
  assertHtmlElement(currentYearElement);
  assertAnchorElement(nextMonthBtn);
  assertAnchorElement(prevMonthBtn);

  const sundayStart = document
    .getElementById('days')
    ?.hasAttribute('sunday-start');

  const now = new Date();
  currentMonthElement.textContent = now.toLocaleString('en-us', {
    month: 'long',
  });
  currentYearElement.textContent = `| ${now.getFullYear()}`;

  const monthNav = new Date();
  monthNav.setDate(1);
  monthNav.setMonth(monthNav.getMonth() + 1);
  const nextMonth = String(monthNav.getMonth() + 1).padStart(2, '0');
  nextMonthBtn.href = `${monthNav.getFullYear()}-${nextMonth}`;

  monthNav.setMonth(monthNav.getMonth() - 2);
  const prevMonth = String(monthNav.getMonth() + 1).padStart(2, '0');
  prevMonthBtn.href = `${monthNav.getFullYear()}-${prevMonth}`;

  const daysInMonth = new Date(
    now.getFullYear(),
    now.getMonth() + 1,
    0,
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
        firstDayInMonth,
      ),
    );
  });
}

function dateCard(date: Date, firstDay: number) {
  const isToday = sameDay(date, new Date());
  const dateNumber = date.getDate();
  return `<div
        id="date-${dateNumber}"
        class="date-card skeleton
        ${dateNumber === 1 ? 'lg:col-start-' + firstDay : ''}
        ${isToday ? TODAY_CARD_CLASSES : ''}
        "
        ${isToday ? 'aria-current="date"' : ''}
        >
        <p class="date-number self-start px-1 py-1 text-sm font-semibold lg:text-base${
          isToday ? ' text-warning font-bold' : ''
        }">
          <span class="font-normal lg:hidden">${date.toLocaleString('en-us', {
            weekday: 'long',
          })}, </span>
          ${dateNumber}<span class="text-xs lg:hidden">${nthNumber(
            dateNumber,
          )}</span>
        </p>
        <div class="card-body justify-start gap-2 p-1 lg:gap-1">
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
