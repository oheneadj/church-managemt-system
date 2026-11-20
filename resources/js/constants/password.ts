/**
 * The password rules for staff, mirrored from config/otp.php. The browser checks these first to save a round
 * trip; the server checks again and is what actually enforces them (D-054).
 */
export const PASSWORD_MIN_LENGTH = 12;

/** Longest password in bytes: the hashing method silently ignores anything past 72, so the server refuses it. */
export const PASSWORD_MAX_BYTES = 72;
