/** An amount as a person types it: whole cedis with up to two decimals, no sign, no thousands separators. */
const MONEY_TEXT = /^(\d+)(?:\.(\d{1,2}))?$/;

/**
 * An amount of cedis as whole pesewas, worked out from the text without ever becoming a floating-point number
 * (agreement section 12, D-017). It is used only to compare an amount with a limit, never to add up money; totals
 * come from the API. Returns null when the text is not an amount.
 */
export function moneyToPesewas(text: string): bigint | null {
    const match = MONEY_TEXT.exec(text.trim());

    if (match === null) {
        return null;
    }

    return BigInt(match[1] ?? '0') * 100n + BigInt((match[2] ?? '').padEnd(2, '0'));
}

/**
 * What is left of typed or pasted text once it can only be an amount of cedis: digits and at most one decimal point, with at most
 * two digits after it. Letters, signs, spaces and a second point are dropped, so nothing but an amount can be typed.
 */
export function sanitizeAmountInput(text: string): string {
    const cleaned = text.replace(/[^\d.]/g, '');
    const point = cleaned.indexOf('.');

    if (point === -1) {
        return cleaned;
    }

    return `${cleaned.slice(0, point)}.${cleaned
        .slice(point + 1)
        .replace(/\./g, '')
        .slice(0, 2)}`;
}
