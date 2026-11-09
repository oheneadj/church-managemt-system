/**
 * The limits on an SMS credit package that the server also checks (config/credits.php, docs/08, Platform). The form shows the
 * message at once; the server stays the judge.
 */
export const PACKAGE_NAME_MAX = 100;
export const PACKAGE_CREDITS_MAX = 1_000_000;
/** The most a package may cost, in GHS, as exact text. */
export const PACKAGE_PRICE_MAX = '99999.99';
export const PACKAGE_SORT_ORDER_MAX = 1000;
