import { z } from 'zod';

/**
 * Turns off Zod's runtime code generation (`new Function`). The page's Content-Security-Policy forbids eval (D-056),
 * and Zod would otherwise probe for it and cause a reported violation on every page load. Validation is unchanged;
 * it just uses the plain parser. Imported first in `main.tsx`.
 */
export function configureZod(): void {
    z.config({ jitless: true });
}
