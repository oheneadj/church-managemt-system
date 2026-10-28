/**
 * How often the live check-in screen asks the server for the latest headcount, in milliseconds. Ten seconds keeps the
 * count feeling live without hammering the server, and needs no push connection (D-044).
 */
export const LIVE_CHECKIN_POLL_MS = 10_000;
