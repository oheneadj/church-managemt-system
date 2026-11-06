/**
 * SMS credits (docs/02, docs/12 Q4). One credit pays for one part of a text message (D-051).
 */

/**
 * The balance at or under which the app warns that credits are running low. It is a hint on screen only; the server
 * charges and refuses by the real balance (D-051). Chosen by the Product Owner (story 4.18).
 */
export const LOW_SMS_CREDITS = 50;

/** How often the balance is asked for again while the page is open, in milliseconds, so receipts that use credits show up. */
export const CREDITS_REFRESH_MS = 60_000;
