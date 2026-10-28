/**
 * What a new church may choose when it starts, mirrored from config/onboarding.php. The browser checks this
 * first to save a round trip; the server checks again and is what actually enforces it.
 */
export const SLUG_MIN_LENGTH = 3;
export const SLUG_MAX_LENGTH = 40;
export const PREFIX_MIN_LENGTH = 2;
export const PREFIX_MAX_LENGTH = 6;

/** Lowercase letters and numbers, with single hyphens between them. */
export const SLUG_PATTERN = /^[a-z0-9]+(-[a-z0-9]+)*$/;

/** The number of digits in a sign-up code, mirrored from config/otp.php. */
export const OTP_LENGTH = 6;
