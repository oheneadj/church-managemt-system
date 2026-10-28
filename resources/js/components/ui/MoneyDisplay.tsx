interface MoneyDisplayProps {
    /** The ready-to-show text from the API (for example "GHS 2,400.00"). The app never does money arithmetic. */
    formatted: string;
}

/**
 * The only place an amount of money is put on screen (agreement section 19).
 */
export function MoneyDisplay({ formatted }: MoneyDisplayProps) {
    return <span className="tabular-nums">{formatted}</span>;
}
