import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';

// The design tokens are a contract (docs/design-system/CONTRACT.md section 2): the colours that carry meaning must stay readable,
// the motion tokens must exist, and reduced motion must collapse them. This reads the real stylesheet, so a change that breaks the
// contrast limits or removes a token fails here instead of being noticed by a person.

const css = readFileSync('resources/css/app.css', 'utf8');

/** The declarations inside the first block that starts with this selector. */
function block(selector: string): Record<string, string> {
    const start = css.indexOf(`${selector} {`);
    const end = css.indexOf('\n}', start);
    const declarations: Record<string, string> = {};

    for (const match of css.slice(start, end).matchAll(/(--[\w-]+):\s*([^;]+);/g)) {
        declarations[match[1]] = match[2].trim();
    }

    return declarations;
}

const light = block(':root');
const dark = block('.dark');

/** Linear sRGB from an `oklch(L C h)` value, clamped to the screen's range. */
function toLinearRgb(value: string): [number, number, number] {
    const parts = /oklch\(\s*([\d.]+)\s+([\d.]+)\s+([\d.]+)/.exec(value);

    if (parts === null) {
        throw new Error(`Not an oklch colour: ${value}`);
    }

    const [lightness, chroma, hue] = [Number(parts[1]), Number(parts[2]), (Number(parts[3]) * Math.PI) / 180];
    const a = chroma * Math.cos(hue);
    const b = chroma * Math.sin(hue);
    const l = (lightness + 0.3963377774 * a + 0.2158037573 * b) ** 3;
    const m = (lightness - 0.1055613458 * a - 0.0638541728 * b) ** 3;
    const s = (lightness - 0.0894841775 * a - 1.291485548 * b) ** 3;
    const clamp = (x: number) => Math.max(0, Math.min(1, x));

    return [
        clamp(4.0767416621 * l - 3.3077115913 * m + 0.2309699292 * s),
        clamp(-1.2684380046 * l + 2.6097574011 * m - 0.3413193965 * s),
        clamp(-0.0041960863 * l - 0.7034186147 * m + 1.707614701 * s),
    ];
}

function luminance(value: string): number {
    const [r, g, b] = toLinearRgb(value);

    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

/** The WCAG contrast ratio between two colours. */
function contrast(foreground: string, background: string): number {
    const [high, low] = [luminance(foreground), luminance(background)].sort((x, y) => y - x);

    return (high + 0.05) / (low + 0.05);
}

const themes = { light, dark } as const;

describe('contrast, in both themes', () => {
    describe.each(Object.entries(themes))('%s', (_name, tokens) => {
        it('keeps the focus ring at 3:1 or more against the page and the cards (WCAG 2.4.13)', () => {
            expect(contrast(tokens['--ring'], tokens['--background'])).toBeGreaterThanOrEqual(3);
            expect(contrast(tokens['--ring'], tokens['--card'])).toBeGreaterThanOrEqual(3);
        });

        it('keeps placeholder and helper text at 4.5:1 or more', () => {
            expect(contrast(tokens['--muted-foreground'], tokens['--background'])).toBeGreaterThanOrEqual(4.5);
        });

        it.each(['success', 'warning', 'info', 'error'])('keeps %s text at 4.5:1 or more on its own surface', (kind) => {
            expect(contrast(tokens[`--feedback-${kind}`], tokens[`--feedback-${kind}-surface`])).toBeGreaterThanOrEqual(4.5);
        });
    });
});

describe('the interaction tokens', () => {
    it.each([
        '--motion-duration-instant',
        '--motion-duration-micro',
        '--motion-duration-component',
        '--motion-duration-overlay',
        '--motion-duration-content',
        '--motion-ease-standard',
        '--motion-ease-enter',
        '--motion-ease-exit',
        '--opacity-disabled',
        '--opacity-overlay',
        '--size-spinner-sm',
        '--size-spinner-md',
        '--size-spinner-lg',
        '--space-toast-edge',
        '--space-toast-gap',
        '--surface-hover',
        '--surface-pressed',
        '--skeleton-base',
    ])('defines %s', (token) => {
        expect(light[token]).toBeDefined();
    });

    it('keeps the durations in the order the spec gives and under 300 ms', () => {
        const ms = (token: string) => Number.parseFloat(light[token]);
        const ordered = ['instant', 'micro', 'component', 'overlay', 'content'].map((name) => ms(`--motion-duration-${name}`));

        expect(ordered).toEqual([...ordered].sort((x, y) => x - y));
        expect(Math.max(...ordered)).toBeLessThanOrEqual(300);
    });

    it('makes every transition without its own duration use the micro duration and the standard easing', () => {
        expect(css).toContain('--default-transition-duration: var(--motion-duration-micro)');
        expect(css).toContain('--default-transition-timing-function: var(--motion-ease-standard)');
    });
});

describe('reduced motion', () => {
    const reduced = css.slice(css.indexOf('@media (prefers-reduced-motion: reduce)'));

    it('collapses every duration token', () => {
        for (const name of ['instant', 'micro', 'component', 'overlay', 'content']) {
            expect(reduced).toContain(`--motion-duration-${name}: 0.01ms`);
        }
    });

    it('stops animations and transitions but slows, rather than stops, the spinner and the skeleton', () => {
        expect(reduced).toMatch(/animation-duration:\s*0\.01ms\s*!important/);
        expect(reduced).toMatch(/transition-duration:\s*0\.01ms\s*!important/);
        expect(reduced).toMatch(/\.animate-spin\s*{[^}]*animation-iteration-count:\s*infinite/);
        expect(reduced).toMatch(/\.animate-pulse\s*{[^}]*animation-iteration-count:\s*infinite/);
    });
});

describe('the font', () => {
    it('is DM Sans, served from our own build, with system fallbacks', () => {
        expect(css).toContain('@import "@fontsource-variable/dm-sans"');
        expect(css).toMatch(/--font-sans:\s*'DM Sans Variable',[^;]*system-ui/);
        expect(css).not.toMatch(/geist/i);
    });
});
