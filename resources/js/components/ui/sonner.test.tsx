import { act, render, screen } from '@testing-library/react';
import { toast } from 'sonner';
import { describe, expect, it } from 'vitest';
import { Toaster } from '@/components/ui/sonner';

// Each kind of toast has its own deep, solid colour with light text (docs/design-system/interactions.md, section 9). Sonner paints a
// toast from variables on the toast area, so these tests check that every kind is switched to those variables and that they point at
// the status tokens. The contrast of the tokens themselves is checked in designTokens.test.ts.

const KINDS = ['success', 'info', 'warning', 'error'] as const;

/** Shows one toast of every kind and waits until they are on screen. */
async function showAllKinds() {
    render(<Toaster />);

    act(() => {
        toast.success('Changes saved.');
        toast.info('Your report is being prepared.');
        toast.warning('You have unsaved changes.');
        toast.error('We could not save your changes. Try again.');
    });

    await screen.findByText('Changes saved.');
}

describe('Toaster', () => {
    it.each([
        ['success', 'Changes saved.'],
        ['info', 'Your report is being prepared.'],
        ['warning', 'You have unsaved changes.'],
        ['error', 'We could not save your changes. Try again.'],
    ] as const)('colours a %s toast by its own kind', async (kind, message) => {
        await showAllKinds();

        const shown = document.querySelector(`[data-sonner-toast][data-type="${kind}"]`);

        expect(shown).toHaveAttribute('data-rich-colors', 'true');
        expect(shown).toHaveTextContent(message);
    });

    it('paints each kind deep and solid with light text, from the status tokens', async () => {
        await showAllKinds();
        const area = document.querySelector<HTMLElement>('[data-sonner-toaster]');

        for (const kind of KINDS) {
            expect(area?.style.getPropertyValue(`--${kind}-bg`)).toBe(`var(--feedback-${kind}-solid)`);
            expect(area?.style.getPropertyValue(`--${kind}-border`)).toContain(`var(--feedback-${kind}-solid)`);
            expect(area?.style.getPropertyValue(`--${kind}-text`)).toBe('var(--feedback-solid-foreground)');
        }
    });

    it('gives each kind a different colour', async () => {
        await showAllKinds();
        const area = document.querySelector<HTMLElement>('[data-sonner-toaster]');
        const backgrounds = KINDS.map((kind) => area?.style.getPropertyValue(`--${kind}-bg`));

        expect(new Set(backgrounds).size).toBe(4);
    });

    it('leaves a plain toast without a kind, so it keeps the neutral surface', async () => {
        render(<Toaster />);

        act(() => {
            toast('Just so you know.');
        });
        await screen.findByText('Just so you know.');

        const plain = screen.getByText('Just so you know.').closest('[data-sonner-toast]');

        expect(KINDS as readonly string[]).not.toContain(plain?.getAttribute('data-type') ?? 'none');
    });
});
