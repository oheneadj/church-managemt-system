import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { ProfileImage } from '@/components/ui/ProfileImage';

// The profile image (docs/design-system/foundations.md, 5b): the person's photo or their initials on a stable tint, named for screen
// readers (or hidden when the name is written beside it), with presence said in words.

const fallback = () => document.querySelector('[data-slot="avatar-fallback"]') as HTMLElement;

describe('ProfileImage', () => {
    it('is one picture named for the person', () => {
        render(<ProfileImage name="Ama Mensah" />);

        expect(screen.getByRole('img', { name: 'Ama Mensah' })).toBeInTheDocument();
    });

    it('is hidden from screen readers when the name is written beside it, so the name is not read twice', () => {
        render(<ProfileImage name="Ama Mensah" decorative />);

        expect(screen.queryByRole('img')).toBeNull();
        expect(document.querySelector('[aria-hidden="true"] [data-slot="profile-image"]')).not.toBeNull();
    });

    it('shows the initials when there is no photo, and keeps them out of the reading', () => {
        render(<ProfileImage name="Ama Mensah" />);

        expect(fallback()).toHaveTextContent('AM');
        expect(fallback()).toHaveAttribute('aria-hidden', 'true');
    });

    it('shows one initial for a single name', () => {
        render(<ProfileImage name="Kofi" />);

        expect(fallback()).toHaveTextContent('K');
    });

    it('falls back to the initials while the photo is not there', () => {
        render(<ProfileImage name="Ama Mensah" src="/missing.png" />);

        expect(fallback()).toHaveTextContent('AM');
    });

    it.each([
        ['xs', 'size-6'],
        ['sm', 'size-8'],
        ['md', 'size-10'],
        ['lg', 'size-12'],
        ['xl', 'size-16'],
        ['2xl', 'size-24'],
    ] as const)('draws size %s as %s', (size, expected) => {
        render(<ProfileImage name="Ama Mensah" size={size} />);

        expect(document.querySelector('[data-slot="profile-image"]')).toHaveClass(expected);
    });

    it('is medium by default and merges an extra class', () => {
        render(<ProfileImage name="Ama Mensah" className="ring-2" />);

        expect(document.querySelector('[data-slot="profile-image"]')).toHaveClass('size-10', 'ring-2');
    });

    it('gives a person the same tint every time, and different people a spread of them', () => {
        const tintOf = (name: string) => {
            const { unmount } = render(<ProfileImage name={name} />);
            const tint = fallback().style.background;
            unmount();

            return tint;
        };

        expect(tintOf('Ama Mensah')).toBe(tintOf('Ama Mensah'));
        expect(new Set(['Ama Mensah', 'Kofi Boateng', 'Esi Owusu', 'Yaw Darko', 'Abena Asante'].map(tintOf)).size).toBeGreaterThan(2);
        expect(tintOf('Ama Mensah')).toMatch(/var\(--viz-series-[1-5]\)/);
    });

    it('says presence in words for screen readers as well as with a dot', () => {
        render(<ProfileImage name="Ama Mensah" status={{ label: 'Online', tone: 'online' }} />);
        const dot = document.querySelector('[data-slot="profile-status"]');

        expect(dot).toHaveAttribute('data-tone', 'online');
        expect(dot).toHaveTextContent('Online');
        expect(dot?.querySelector('.sr-only')).not.toBeNull();
    });

    it.each([
        ['online', 'var(--feedback-success-solid)'],
        ['away', 'var(--feedback-warning-solid)'],
        ['offline', 'var(--viz-other)'],
    ] as const)('colours %s from the status tokens', (tone, colour) => {
        render(<ProfileImage name="Ama Mensah" status={{ label: tone, tone }} />);

        expect(document.querySelector('[data-slot="profile-status"]')).toHaveStyle({ background: colour });
    });

    it('has no presence dot unless asked', () => {
        render(<ProfileImage name="Ama Mensah" />);

        expect(document.querySelector('[data-slot="profile-status"]')).toBeNull();
    });
});
