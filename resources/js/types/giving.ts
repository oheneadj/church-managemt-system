import type { PaymentMethod } from '@/constants/enums';

/**
 * Shapes of the giving endpoints (docs/08-api.md, docs/openapi.json). Every amount is an exact decimal string with a
 * `_formatted` sibling; the app shows the formatted text and never does arithmetic on money.
 */

export interface FundType {
    id: string;
    name: string;
    description: string | null;
    is_active: boolean;
    sort_order: number;
}

export interface Contribution {
    id: string;
    member: { id: string; full_name: string } | null;
    is_anonymous: boolean;
    service: { id: string; name: string; service_date: string } | null;
    /** Present when the gift pays a pledge. */
    pledge?: { id: string; description: string } | null;
    fund_type: { id: string; name: string };
    amount: string;
    amount_formatted: string;
    payment_method: PaymentMethod;
    payment_method_label: string;
    is_voided: boolean;
    voided_at: string | null;
    void_reason: string | null;
    voided_by: string | null;
    recorded_by: string;
    notes: string | null;
    contributed_at: string;
    created_at: string | null;
}

/** Each payment method's amount as text. The keys are the payment methods the API lists for every fund, even at 0.00. */
export type AmountsByMethod = Record<PaymentMethod, string>;

/** One fund's line in the offering report. */
export interface OfferingFundLine {
    fund_type: string;
    by_method: AmountsByMethod;
    /** The same amounts as ready-to-show text ("GHS 1,200.00"). */
    by_method_formatted: AmountsByMethod;
    total: string;
    total_formatted: string;
}

/** One set of figures in the offering report: the gifts at the service, or the gifts given that day with no service. */
export interface OfferingSection {
    grand_total: string;
    grand_total_formatted: string;
    by_fund: OfferingFundLine[];
    by_method: AmountsByMethod;
    by_method_formatted: AmountsByMethod;
    transaction_count: number;
    anonymous_count: number;
}

/** What one service received (docs/08, Sunday Offering Report). It holds totals only, with no giver's name. */
export interface OfferingReport {
    service: { id: string; name: string; service_date: string };
    totals: OfferingSection;
    /** Gifts given that day with no service. They are counted with the day's first service only (`counted_here`). */
    no_service: OfferingSection & { counted_here: boolean };
    day_grand_total: string;
    day_grand_total_formatted: string;
}

/** What the record-a-gift form sends (docs/08, Record Cash Contribution). Empty optional fields are left out. */
export interface ContributionInput {
    member_id?: string;
    service_id?: string;
    fund_type_id: string;
    /** An exact decimal in GHS, as the person typed it. */
    amount: string;
    payment_method: 'cash';
    /** A moment in UTC. Left out for a gift given today, so the server uses the time it was entered. */
    contributed_at?: string;
    notes?: string;
}

/** The gift list's filters as they live in the address bar. Dates are YYYY-MM-DD; an empty text means "no filter". */
export interface ContributionFilters {
    from: string;
    to: string;
    fund: string;
    method: PaymentMethod | '';
    voided: boolean;
    page: number;
}
