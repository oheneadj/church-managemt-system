const PERCENT = new Intl.NumberFormat('en-GB', { minimumFractionDigits: 1, maximumFractionDigits: 1, signDisplay: 'exceptZero' });

/** A rise or fall the server worked out, to one decimal place with its sign: "+9.1%", "-66.7%", "0.0%". */
export function formatPercent(value: number): string {
    return `${PERCENT.format(value)}%`;
}
