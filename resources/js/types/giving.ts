import type { PaymentMethod, PledgeStatus } from '@/constants/enums';

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

/** What the fund form sends. An emptied description is sent as null, which clears it. */
export interface FundTypeInput {
    name: string;
    description: string | null;
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
    /** The pledge this gift pays. It must be the member's, for the same fund, and not paid in full. */
    pledge_id?: string;
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

/** A member's promise to give an amount to a fund by a date (docs/08, Pledges). Amounts are exact text with a `_formatted` sibling. */
export interface Pledge {
    id: string;
    member: { id: string; full_name: string };
    fund_type: { id: string; name: string };
    description: string;
    amount_pledged: string;
    amount_pledged_formatted: string;
    amount_paid: string;
    amount_paid_formatted: string;
    amount_outstanding: string;
    amount_outstanding_formatted: string;
    /** A whole number from 0 to 100; a pledge paid over stops at 100. */
    progress_percent: number;
    target_date: string;
    status: PledgeStatus;
    status_label: string;
    created_at: string | null;
}

/** The pledge list's filters as they live in the address bar. An empty text means "no filter". */
export interface PledgeFilters {
    status: PledgeStatus | '';
    fund: string;
    page: number;
}

/** What the new-pledge form sends. */
export interface PledgeInput {
    member_id: string;
    fund_type_id: string;
    description: string;
    /** An exact decimal in GHS, as typed. */
    amount_pledged: string;
    /** YYYY-MM-DD. */
    target_date: string;
}

/** What the change-pledge form sends: the member and fund cannot change, because gifts are linked to them. */
export type PledgeChange = Pick<PledgeInput, 'description' | 'amount_pledged' | 'target_date'>;
