/**
 * Shapes of the church endpoints (docs/08-api.md, "Church Profile" and "Church Settings").
 */

/** The church's own details. A detail the church never gave is null. */
export interface ChurchProfile {
    id: string;
    name: string;
    slug: string;
    denomination: string | null;
    address: string | null;
    city: string | null;
    region: string | null;
    /** In international form, for example +233302000000. */
    phone: string | null;
    email: string | null;
    timezone: string;
}

/** What the profile form sends. An optional detail sent as null is cleared. */
export interface ChurchProfileInput {
    name: string;
    denomination: string | null;
    address: string | null;
    city: string | null;
    region: string | null;
    phone: string | null;
    email: string | null;
}

/** Every church setting, with the church's own choice or the default. */
export interface ChurchSettings {
    youth_age_limit: number;
    absence_threshold: number;
    receipt_on_cash: boolean;
    absent_alert: boolean;
}
