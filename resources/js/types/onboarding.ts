/**
 * Shapes of the onboarding endpoints (docs/08-api.md, "Onboarding Endpoints").
 */

export interface StartChurchInput {
    church_name: string;
    slug?: string;
    denomination?: string;
    city?: string;
    member_number_prefix: string;
    admin_name: string;
    admin_phone: string;
    otp: string;
}

export interface StartedChurch {
    church: { id: string; name: string; slug: string; member_number_prefix: string; url: string };
    admin: { id: string; name: string };
    login_url: string;
}
