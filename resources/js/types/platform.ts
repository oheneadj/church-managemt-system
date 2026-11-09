/**
 * Shapes of the platform owner's side (docs/08-api.md, "Platform").
 */

/** A platform admin as they see themselves. */
export interface PlatformAdmin {
    id: string;
    name: string;
    email: string;
}

/** An SMS credit package. The price is exact text in GHS with the text to show beside it. */
export interface SmsPackage {
    id: string;
    name: string;
    credits: number;
    price: string;
    price_formatted: string;
    is_active: boolean;
    sort_order: number;
}

/** What the package form sends. */
export interface SmsPackageInput {
    name: string;
    credits: number;
    price: string;
    sort_order: number;
}
