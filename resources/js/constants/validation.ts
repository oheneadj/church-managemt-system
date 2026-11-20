/**
 * Input rules shared by several forms.
 */

/**
 * What the phone box accepts: an optional plus, then 9 to 16 digits, spaces or dashes. The server normalises
 * the number (0244123456 and +233244123456 are the same) and decides if it is valid.
 */
export const PHONE_INPUT_PATTERN = /^\+?[\d\s-]{9,16}$/;
