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

/** The gift list's filters as they live in the address bar. Dates are YYYY-MM-DD; an empty text means "no filter". */
export interface ContributionFilters {
    from: string;
    to: string;
    fund: string;
    method: PaymentMethod | '';
    voided: boolean;
    page: number;
}
