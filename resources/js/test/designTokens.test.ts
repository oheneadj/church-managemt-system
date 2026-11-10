import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';

// The design tokens are a contract (docs/design-system/CONTRACT.md section 2, foundations.md, D-071): the brand colours stay exactly
// as given, the colours that carry meaning stay readable in both themes, and the scales exist. This reads the real stylesheet, so a
// change that breaks a contrast limit, moves a brand colour or removes a token fails here instead of being noticed by a person.

const css = readFileSync('resources/css/app.css', 'utf8');

/** The declarations inside the first block that starts with this selector. */
function block(selector: string): Record<string, string> {
    const start = css.indexOf(`${selector} {`);
    const end = css.indexOf('\n}', start);
    const declarations: Record<string, string> = {};

    for (const [, name, value] of css.slice(start, end).matchAll(/(--[\w-]+):\s*([^;]+);/g)) {
        if (name !== undefined && value !== undefined) {
            declarations[name] = value.trim();
        }
    }

    return declarations;
}

const primitives = block('@theme');
const roles = block('@theme inline');
const light = { ...primitives, ...block(':root') };
const dark = { ...light, ...block('.dark') };
const themes = { light, dark } as const;

/** The value of a token with any `var(--other)` followed to its end, or a failure that names the token. */
function resolve(tokens: Record<string, string>, name: string): string {
    const value = tokens[name];

    if (value === undefined) {
        throw new Error(`The token ${name} is not defined.`);
    }

    const reference = /^var\((--[\w-]+)\)$/.exec(value);

    return reference?.[1] === undefined ? value : resolve(tokens, reference[1]);
}

function channel(hex: string, from: number): number {
    const value = Number.parseInt(hex.slice(from, from + 2), 16) / 255;

    return value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4;
}

function luminance(hex: string): number {
    if (!/^#[0-9a-f]{6}$/i.test(hex)) {
        throw new Error(`Not a six-digit hex colour: ${hex}`);
    }

    return 0.2126 * channel(hex, 1) + 0.7152 * channel(hex, 3) + 0.0722 * channel(hex, 5);
}

/** The WCAG contrast ratio between two hex colours. */
function contrast(foreground: string, background: string): number {
    const [high = 0, low = 0] = [luminance(foreground), luminance(background)].sort((x, y) => y - x);

    return (high + 0.05) / (low + 0.05);
}

/** Mixes the colour `over` into `base` by `share` (0 to 1), as a six-digit hex. */
function mix(base: string, over: string, share: number): string {
    const part = (hex: string, at: number) => Number.parseInt(hex.slice(at, at + 2), 16);
    const blended = [1, 3, 5].map((at) => Math.round(part(base, at) * (1 - share) + part(over, at) * share));

    return `#${blended.map((value) => value.toString(16).padStart(2, '0')).join('')}`;
}

function color(tokens: Record<string, string>, name: string): string {
    return resolve(tokens, name).toLowerCase();
}

const pixels = (value: string, base = 16) => (value.endsWith('rem') ? Number.parseFloat(value) * base : Number.parseFloat(value));

describe('the brand colours are exactly as given', () => {
    it.each([
        ['--color-forest-900', '#183c35'],
        ['--color-forest-800', '#245247'],
        ['--color-forest-100', '#eaf0eb'],
        ['--color-gold-400', '#c3a46b'],
        ['--color-gold-100', '#f3ebdd'],
        ['--color-neutral-50', '#f7f5f0'],
        ['--color-neutral-100', '#f1f2ee'],
        ['--color-neutral-200', '#e7ebe6'],
        ['--color-neutral-400', '#98a29c'],
        ['--color-neutral-500', '#718079'],
        ['--color-neutral-850', '#26332f'],
        ['--color-neutral-950', '#171d1c'],
        ['--color-neutral-900', '#222a28'],
        ['--color-neutral-800', '#2b3531'],
        ['--color-neutral-700', '#39423e'],
    ])('keeps %s at %s', (name, hex) => {
        expect(primitives[name]?.toLowerCase()).toBe(hex);
    });

    it.each(['forest', 'gold', 'neutral'])('has the full %s scale from 50 to 950', (family) => {
        for (const step of [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950]) {
            expect(primitives[`--color-${family}-${step}`]).toMatch(/^#[0-9a-f]{6}$/i);
        }
    });

    it('goes from light to dark in every scale', () => {
        for (const family of ['forest', 'gold', 'neutral']) {
            const steps = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950].map((step) =>
                luminance(color(primitives, `--color-${family}-${step}`)),
            );

            expect(steps).toEqual([...steps].sort((x, y) => y - x));
        }
    });
});

describe('contrast, in both themes', () => {
    describe.each(Object.entries(themes))('%s', (_name, tokens) => {
        const text = (foreground: string, background: string) => contrast(color(tokens, foreground), color(tokens, background));

        it.each([
            ['--foreground', '--background'],
            ['--foreground', '--card'],
            ['--foreground', '--muted'],
            ['--card-foreground', '--card'],
            ['--popover-foreground', '--popover'],
            ['--muted-foreground', '--background'],
            ['--muted-foreground', '--card'],
            ['--muted-foreground', '--muted'],
            ['--muted-foreground', '--popover'],
            ['--primary-foreground', '--primary'],
            ['--secondary-foreground', '--secondary'],
            ['--accent-foreground', '--accent'],
            ['--primary', '--background'],
            ['--primary', '--card'],
            ['--destructive', '--background'],
            ['--destructive', '--card'],
            ['--accent-gold-strong', '--background'],
            ['--accent-gold-strong', '--card'],
        ])('keeps %s on %s at 4.5:1 or more', (foreground, background) => {
            expect(text(foreground, background)).toBeGreaterThanOrEqual(4.5);
        });

        it('keeps the focus ring and the field outline at 3:1 or more against the page and cards (WCAG 1.4.11, 2.4.13)', () => {
            for (const edge of ['--ring', '--input']) {
                for (const surface of ['--background', '--card']) {
                    expect(text(edge, surface)).toBeGreaterThanOrEqual(3);
                }
            }
        });

        it.each(['success', 'warning', 'info', 'error'])(
            'keeps %s text at 4.5:1 or more on its own surface and on the page and cards',
            (kind) => {
                expect(text(`--feedback-${kind}`, `--feedback-${kind}-surface`)).toBeGreaterThanOrEqual(4.5);
                expect(text(`--feedback-${kind}`, '--background')).toBeGreaterThanOrEqual(4.5);
                expect(text(`--feedback-${kind}`, '--card')).toBeGreaterThanOrEqual(4.5);
            },
        );

        it.each(['success', 'warning', 'info', 'error'])('keeps light text on the deep %s toast at 7:1 or more', (kind) => {
            expect(text('--feedback-solid-foreground', `--feedback-${kind}-solid`)).toBeGreaterThanOrEqual(7);
        });

        it('keeps the solid danger button readable: its text on its fill at 4.5:1 or more', () => {
            expect(text('--destructive-foreground', '--destructive')).toBeGreaterThanOrEqual(4.5);
        });

        it('keeps the destructive colour readable on its own tint, as the destructive button shows it', () => {
            const tint = mix(color(tokens, '--card'), color(tokens, '--destructive'), 0.1);

            expect(contrast(color(tokens, '--destructive'), tint)).toBeGreaterThanOrEqual(4.5);
        });
    });

    it("keeps the dark theme's own surfaces and text as given", () => {
        expect(color(dark, '--background')).toBe('#171d1c');
        expect(color(dark, '--card')).toBe('#222a28');
        expect(color(dark, '--popover')).toBe('#2b3531');
        expect(color(dark, '--border')).toBe('#39423e');
        expect(color(dark, '--foreground')).toBe('#f1f2ee');
        expect(color(dark, '--muted-foreground')).toBe('#a6b0aa');
    });

    it("keeps the light theme's page, surface and text as given", () => {
        expect(color(light, '--background')).toBe('#f7f5f0');
        expect(color(light, '--card')).toBe('#ffffff');
        expect(color(light, '--border')).toBe('#e7ebe6');
        expect(color(light, '--foreground')).toBe('#26332f');
        expect(color(light, '--primary')).toBe('#183c35');
    });

    it('does not use the brand greys and the soft gold where they cannot be read', () => {
        // #718079 (3.8:1) and #98A29C (2.6:1) are for large text, outlines and decoration; #C3A46B (2.4:1) is for decoration.
        expect(color(light, '--muted-foreground')).not.toBe('#718079');
        expect(color(light, '--muted-foreground')).not.toBe('#98a29c');
        expect(color(light, '--accent-gold-strong')).not.toBe('#c3a46b');
    });
});

describe('the chart colours', () => {
    // Validated with the data-visualisation checks (lightness band, chroma floor, colour-blind and normal-vision separation of
    // neighbouring pairs, 3:1 contrast on the card). Pinned, so changing one forces a new validation, not a guess.
    const VALIDATED = {
        light: ['#007d58', '#b78a2c', '#893365', '#3275b4', '#d15e49'],
        dark: ['#249c74', '#aa7e1a', '#c15f95', '#5497d9', '#cc624e'],
    } as const;

    describe.each(Object.entries(themes))('%s', (name, tokens) => {
        const fills = [1, 2, 3, 4, 5].map((slot) => color(tokens, `--viz-series-${slot}`));

        it('uses the validated set, in order', () => {
            expect(fills).toEqual([...VALIDATED[name as 'light' | 'dark']]);
        });

        it('keeps every series at 3:1 or more against the card (marks)', () => {
            for (const fill of fills) {
                expect(contrast(fill, color(tokens, '--card'))).toBeGreaterThanOrEqual(3);
            }
        });

        it('prints readable text on every fill: the ink for each is at 4.5:1 or more', () => {
            fills.forEach((fill, index) => {
                expect(contrast(color(tokens, `--viz-ink-${index + 1}`), fill)).toBeGreaterThanOrEqual(4.5);
            });
        });

        it('has a quiet grey for context and furniture that stays recessive', () => {
            expect(color(tokens, '--viz-other')).toMatch(/^#[0-9a-f]{6}$/);
            expect(contrast(color(tokens, '--viz-grid'), color(tokens, '--card'))).toBeLessThan(2);
            expect(contrast(color(tokens, '--viz-baseline'), color(tokens, '--card'))).toBeLessThan(2.6);
        });
    });
});

describe('the type scale', () => {
    const SCALE: Array<[string, number, number]> = [
        ['hero', 48, 600],
        ['display', 36, 600],
        ['h1', 30, 600],
        ['h2', 24, 600],
        ['h3', 20, 600],
        ['h4', 18, 600],
        ['body-lg', 16, 400],
        ['body', 14, 400],
        ['body-sm', 13, 400],
        ['caption', 12, 400],
    ];

    it.each(SCALE)('sets %s at %dpx and weight %d, with a comfortable line height', (name, size, weight) => {
        const line = pixels(primitives[`--text-${name}--line-height`] ?? '');

        expect(pixels(primitives[`--text-${name}`] ?? '')).toBe(size);
        expect(Number(primitives[`--text-${name}--font-weight`])).toBe(weight);
        // The hero figure is a single large number, so its line is tighter than running text needs.
        expect(line / size).toBeGreaterThanOrEqual(name === 'hero' ? 1.05 : 1.2);
        expect(line / size).toBeLessThanOrEqual(1.7);
        expect(primitives[`--text-${name}--letter-spacing`]).toBeDefined();
    });

    it('steps display and heading 1 down on phones', () => {
        const phones = css.slice(css.indexOf('@media (max-width: 40rem)'));

        expect(phones).toMatch(/--text-display:\s*1\.875rem/);
        expect(phones).toMatch(/--text-h1:\s*1\.625rem/);
    });

    it('is DM Sans, served from our own build, with system fallbacks', () => {
        expect(css).toContain('@import "@fontsource-variable/dm-sans"');
        expect(roles['--font-sans']).toMatch(/^'DM Sans Variable',[^;]*system-ui/);
        expect(css).not.toMatch(/geist/i);
    });

    it('sets the page text to the body size and lines numbers up in tables', () => {
        expect(css).toMatch(/body\s*{[^}]*text-body/);
        expect(css).toMatch(/table,\s*\[data-numeric\]\s*{[^}]*tabular-nums/);
    });
});

describe('radius and elevation', () => {
    it.each([
        ['--radius-sm', 4],
        ['--radius-md', 6],
        ['--radius-lg', 12],
        ['--radius-xl', 16],
        ['--radius-2xl', 20],
    ])('sets %s at %dpx, so controls (rounded-lg) are 12px and surfaces (rounded-xl) 16px', (name, size) => {
        expect(pixels(roles[name] ?? '')).toBe(size);
    });

    it('has a full radius for pills', () => {
        expect(roles['--radius-4xl']).toBe('9999px');
    });

    it('has five elevation levels in both themes, each a little stronger than the last', () => {
        expect(light['--elevation-0']).toBe('none');

        for (const tokens of [light, dark]) {
            const strength = [1, 2, 3, 4].map((level) => (tokens[`--elevation-${level}`] ?? '').length);

            expect(strength.every((length) => length > 0)).toBe(true);
            expect(strength).toEqual([...strength].sort((x, y) => x - y));
        }
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
    ])('defines %s', (name) => {
        expect(light[name]).toBeDefined();
    });

    it('sets fast 100, standard 150, moderate 200 and slow 300 ms, with 80 ms for a press', () => {
        const ms = (name: string) => Number.parseFloat(resolve(light, name));

        expect([
            ms('--motion-duration-instant'),
            ms('--motion-duration-micro'),
            ms('--motion-duration-component'),
            ms('--motion-duration-overlay'),
            ms('--motion-duration-content'),
        ]).toEqual([80, 100, 150, 200, 300]);
    });

    it('makes every transition without its own duration use the fast duration and the standard easing', () => {
        expect(roles['--default-transition-duration']).toBe('var(--motion-duration-micro)');
        expect(roles['--default-transition-timing-function']).toBe('var(--motion-ease-standard)');
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
