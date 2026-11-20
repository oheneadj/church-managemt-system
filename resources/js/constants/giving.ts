import { PAYMENT_METHODS, type PaymentMethod } from '@/constants/enums';

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

/** The most a single pledge may promise, in GHS, as exact text. Mirrors `giving.pledge_max`. */
export const PLEDGE_MAX = '999999.99';

/** The longest description a pledge may have. */
export const PLEDGE_DESCRIPTION_MAX = 255;

/** How many of a member's pledges to one fund are asked for when choosing which one a gift pays; 100 is the most the server allows. */
export const GIFT_PLEDGES_PER_PAGE = 100;

/** The order payment methods are listed in on the offering report: cash first, as in docs/04. */
export const OFFERING_METHOD_ORDER = ['cash', 'momo', 'card', 'ussd'] as const satisfies readonly PaymentMethod[];

/** The words for each payment method, from the mirrored enum, so no label is typed out twice. */
export const PAYMENT_METHOD_LABELS: Record<PaymentMethod, string> = Object.fromEntries(
    PAYMENT_METHODS.map((method) => [method.value, method.label]),
) as Record<PaymentMethod, string>;

/** What the API writes for an amount of nothing. Used only to leave out a payment method nobody used, never to add money. */
export const ZERO_AMOUNT = '0.00';

/** The limits on a fund type that the server also checks (config/giving.php and docs/08, Fund Types). */
export const FUND_NAME_MAX = 100;
export const FUND_DESCRIPTION_MAX = 2000;
export const FUND_SORT_ORDER_MAX = 1000;
