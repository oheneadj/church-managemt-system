const COUNT = new Intl.NumberFormat('en-GB');

/**
 * A whole number as people read it, with thousands separators ("12,500"). It is for counts such as SMS credits; money is
 * only ever shown from the text the API sends (agreement section 12).
 */
export function formatCount(value: number): string {
    return COUNT.format(value);
}

const SIGNED = new Intl.NumberFormat('en-GB', { signDisplay: 'exceptZero' });

/** A change in a count with its sign: "+3", "-2", "0". */
export function formatSignedCount(value: number): string {
    return SIGNED.format(value);
}
