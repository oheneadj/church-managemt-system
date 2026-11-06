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
