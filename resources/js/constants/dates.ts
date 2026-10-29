/**
 * Date limits that the server also checks (config/members.php and config/attendance.php, D-049). The form shows the
 * message at once; the server stays the judge.
 */

/** The earliest date of birth accepted; anything earlier is a typing mistake. */
export const EARLIEST_BIRTH_DATE = '1900-01-01';

/** How many days back a service may be dated, so a missed Sunday can still be added. */
export const SERVICE_DAYS_BACK = 30;

/** How many days ahead a service may be dated, so a wrong year is caught. */
export const SERVICE_DAYS_AHEAD = 366;

/** What a date box shows as its hint. */
export const DATE_BOX_HINT = 'dd/mm/yyyy';
