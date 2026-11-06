/**
 * Limits on a gift that the server also checks (config/giving.php, docs/08). The form shows the message at once; the
 * server stays the judge.
 */

/** The most a single cash gift may be, in GHS, as exact text. Mirrors `giving.cash_max`. */
export const GIFT_CASH_MAX = '999999.99';

/**
 * The time of day sent with a gift dated in the past. The form asks for a date only, and noon UTC keeps that date
 * the same in the church's own calendar (Ghana is on UTC). A gift dated today is sent with no time, so the server
 * records the moment it was entered.
 */
export const GIFT_PAST_DAY_TIME = 'T12:00:00Z';
