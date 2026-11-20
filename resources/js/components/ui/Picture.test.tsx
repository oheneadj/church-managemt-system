import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { Picture, PICTURE_RATIOS } from '@/components/ui/Picture';

// The picture frame (docs/design-system/foundations.md, 5b): it holds its shape, shows a skeleton until the image is ready, fades it
// in, loads lazily, and says in words when the image is missing or cannot load.

const frame = () => document.querySelector('[data-slot="picture"]') as HTMLElement;

describe('Picture', () => {
    it('shows a skeleton and keeps the image hidden until it has loaded, then fades it in', () => {
        render(<Picture src="/a.png" alt="Green hills" />);
        const image = screen.getByAltText('Green hills');

        expect(frame()).toHaveAttribute('data-phase', 'loading');
        expect(document.querySelector('[data-slot="picture-skeleton"]')).not.toBeNull();
        expect(image).toHaveClass('opacity-0');

        fireEvent.load(image);

        expect(frame()).toHaveAttribute('data-phase', 'loaded');
        expect(document.querySelector('[data-slot="picture-skeleton"]')).toBeNull();
        expect(image).toHaveClass('opacity-100');
    });

    it('loads lazily and decodes off the main thread, unless it is the first thing people see', () => {
        const { rerender } = render(<Picture src="/a.png" alt="x" />);
        expect(screen.getByAltText('x')).toHaveAttribute('loading', 'lazy');
        expect(screen.getByAltText('x')).toHaveAttribute('decoding', 'async');

        rerender(<Picture src="/a.png" alt="x" priority />);
        expect(screen.getByAltText('x')).toHaveAttribute('loading', 'eager');
    });

    it('says in words, with an icon, when the image cannot load, and shows no broken image', () => {
        render(<Picture src="/missing.png" alt="Green hills" />);

        fireEvent.error(screen.getByAltText('Green hills'));

        expect(frame()).toHaveAttribute('data-phase', 'failed');
        expect(screen.queryByAltText('Green hills')).toBeNull();
        expect(screen.getByRole('img', { name: 'Image unavailable' })).toHaveTextContent('Image unavailable');
        expect(frame().querySelector('svg')).toHaveAttribute('aria-hidden', 'true');
    });

    it('says so at once when there is no image, in its own words if given', () => {
        const { rerender } = render(<Picture src={null} alt="" />);
        expect(screen.getByRole('img', { name: 'Image unavailable' })).toBeInTheDocument();

        rerender(<Picture alt="" fallbackText="No image yet" />);
        expect(screen.getByRole('img', { name: 'No image yet' })).toBeInTheDocument();
        expect(document.querySelector('img')).toBeNull();
    });

    it.each(Object.entries(PICTURE_RATIOS))('holds the %s shape from the start so nothing jumps when the image arrives', (ratio, css) => {
        render(<Picture src="/a.png" alt="x" ratio={ratio as keyof typeof PICTURE_RATIOS} />);

        expect(frame().style.aspectRatio.replace(/\s/g, '')).toBe(css.replace(/\s/g, ''));
    });

    it('fills the frame by default and shows the whole image when asked, never stretching it', () => {
        const { rerender } = render(<Picture src="/a.png" alt="x" />);
        expect(screen.getByAltText('x')).toHaveClass('object-cover');

        rerender(<Picture src="/a.png" alt="x" fit="contain" />);
        expect(screen.getByAltText('x')).toHaveClass('object-contain');
    });

    it('starts again at loading when the address changes', () => {
        const { rerender } = render(<Picture src="/a.png" alt="x" />);
        fireEvent.load(screen.getByAltText('x'));
        expect(frame()).toHaveAttribute('data-phase', 'loaded');

        rerender(<Picture src="/b.png" alt="x" />);
        expect(frame()).toHaveAttribute('data-phase', 'loading');
    });

    it('keeps a caption with its picture', () => {
        render(<Picture src="/a.png" alt="Green hills" caption="Hills at dawn" />);

        expect(screen.getByRole('figure')).toContainElement(screen.getByText('Hills at dawn'));
        expect(screen.getByText('Hills at dawn').tagName).toBe('FIGCAPTION');
    });

    it('is not a figure when it has no caption', () => {
        render(<Picture src="/a.png" alt="x" />);

        expect(screen.queryByRole('figure')).toBeNull();
    });
});
