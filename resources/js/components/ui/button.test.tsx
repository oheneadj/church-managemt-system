import { act, fireEvent, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { createRef } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { Button } from '@/components/ui/button';
import { LOADING_INDICATOR_DELAY_MS } from '@/constants/interaction';

// The button contract (docs/design-system/interactions.md, section 5): a busy button ignores presses so nothing is sent twice, says
// it is busy, keeps its focus and its width, and shows a spinner only when the work is slow.

describe('Button', () => {
    beforeEach(() => vi.useFakeTimers({ shouldAdvanceTime: true }));
    afterEach(() => vi.useRealTimers());

    it('is an ordinary button at rest', async () => {
        const onClick = vi.fn();
        render(<Button onClick={onClick}>Save changes</Button>);
        const button = screen.getByRole('button', { name: 'Save changes' });

        await userEvent.setup({ advanceTimers: vi.advanceTimersByTime }).click(button);

        expect(onClick).toHaveBeenCalledOnce();
        expect(button).not.toHaveAttribute('aria-busy');
        expect(button).not.toHaveAttribute('aria-disabled');
    });

    describe('while loading', () => {
        it('says it is busy at once, and ignores presses, with the mouse and the keyboard', async () => {
            const onClick = vi.fn();
            const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime });
            render(
                <Button loading onClick={onClick}>
                    Save changes
                </Button>,
            );
            const button = screen.getByRole('button', { name: 'Save changes' });

            await user.click(button);
            button.focus();
            await user.keyboard('{Enter}');
            await user.keyboard(' ');

            expect(button).toHaveAttribute('aria-busy', 'true');
            expect(button).toHaveAttribute('aria-disabled', 'true');
            expect(onClick).not.toHaveBeenCalled();
        });

        it('does not send the form it sits in a second time', async () => {
            const onSubmit = vi.fn((event: { preventDefault: () => void }) => event.preventDefault());
            const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime });
            const { rerender } = render(
                <form onSubmit={onSubmit}>
                    <Button type="submit" loading={false}>
                        Save changes
                    </Button>
                </form>,
            );

            await user.click(screen.getByRole('button'));
            rerender(
                <form onSubmit={onSubmit}>
                    <Button type="submit" loading>
                        Save changes
                    </Button>
                </form>,
            );
            await user.click(screen.getByRole('button'));
            await user.click(screen.getByRole('button'));

            expect(onSubmit).toHaveBeenCalledTimes(1);
        });

        it('stays focusable instead of being disabled, so a keyboard user does not lose their place', () => {
            render(<Button loading>Save changes</Button>);
            const button = screen.getByRole('button');

            button.focus();

            expect(button).not.toBeDisabled();
            expect(button).toHaveFocus();
        });

        it('shows no spinner and keeps its label until the work has taken a moment', () => {
            render(
                <Button loading loadingLabel="Saving…">
                    Save changes
                </Button>,
            );

            act(() => vi.advanceTimersByTime(LOADING_INDICATOR_DELAY_MS - 1));

            expect(screen.getByRole('button')).toHaveTextContent('Save changes');
            expect(screen.queryByRole('status')).not.toBeInTheDocument();
        });

        it('then shows the spinner and the loading label, and announces it once', () => {
            render(
                <Button loading loadingLabel="Saving…">
                    Save changes
                </Button>,
            );

            act(() => vi.advanceTimersByTime(LOADING_INDICATOR_DELAY_MS));

            expect(screen.getByRole('button')).toHaveTextContent('Saving…');
            expect(screen.getByRole('button')).not.toHaveTextContent('Save changes');
            expect(screen.getByRole('status')).toHaveTextContent('Saving…');
            expect(screen.getByRole('button').querySelector('svg.animate-spin')).toHaveAttribute('aria-hidden', 'true');
        });

        it("keeps the button's own name clean: the announcement is outside it, so it is not read twice", () => {
            render(
                <Button loading loadingLabel="Saving…">
                    Save changes
                </Button>,
            );

            act(() => vi.advanceTimersByTime(LOADING_INDICATOR_DELAY_MS));

            expect(screen.getByRole('button', { name: 'Saving…' })).toBeInTheDocument();
            expect(screen.getByRole('button')).not.toContainElement(screen.getByRole('status'));
        });

        it('keeps its own label when no loading label is given', () => {
            render(<Button loading>Save changes</Button>);

            act(() => vi.advanceTimersByTime(LOADING_INDICATOR_DELAY_MS));

            expect(screen.getByRole('button')).toHaveTextContent('Save changes');
            expect(screen.getByRole('status')).toHaveTextContent('Working');
        });

        it('never flashes a spinner for work that ends inside the moment', () => {
            const { rerender } = render(
                <Button loading loadingLabel="Saving…">
                    Save changes
                </Button>,
            );

            act(() => vi.advanceTimersByTime(200));
            rerender(<Button loadingLabel="Saving…">Save changes</Button>);
            act(() => vi.advanceTimersByTime(1000));

            expect(screen.getByRole('button')).toHaveTextContent('Save changes');
            expect(screen.queryByRole('status')).not.toBeInTheDocument();
        });

        it('goes back to normal the moment the work ends', async () => {
            const onClick = vi.fn();
            const { rerender } = render(
                <Button loading loadingLabel="Saving…" onClick={onClick}>
                    Save changes
                </Button>,
            );
            act(() => vi.advanceTimersByTime(LOADING_INDICATOR_DELAY_MS));

            rerender(
                <Button loadingLabel="Saving…" onClick={onClick}>
                    Save changes
                </Button>,
            );
            const button = screen.getByRole('button', { name: 'Save changes' });
            await userEvent.setup({ advanceTimers: vi.advanceTimersByTime }).click(button);

            expect(button).not.toHaveAttribute('aria-busy');
            expect(screen.queryByRole('status')).not.toBeInTheDocument();
            expect(onClick).toHaveBeenCalledOnce();
        });

        it('holds the width it had at rest, then lets go of it', () => {
            const width = vi.spyOn(HTMLElement.prototype, 'getBoundingClientRect').mockReturnValue({ width: 132 } as DOMRect);
            const { rerender } = render(<Button loadingLabel="Saving…">Save changes</Button>);
            const button = screen.getByRole('button');

            rerender(
                <Button loading loadingLabel="Saving…">
                    Save changes
                </Button>,
            );
            act(() => vi.advanceTimersByTime(LOADING_INDICATOR_DELAY_MS));
            expect(button.style.minWidth).toBe('132px');

            rerender(<Button loadingLabel="Saving…">Save changes</Button>);
            expect(button.style.minWidth).toBe('');
            width.mockRestore();
        });
    });

    it('stays disabled when told to, whether or not it is loading', () => {
        render(<Button disabled>Save changes</Button>);

        expect(screen.getByRole('button')).toBeDisabled();
    });

    it('hands its element to a ref, as a function or an object', () => {
        const objectRef = createRef<HTMLButtonElement>();
        const functionRef = vi.fn();

        render(
            <>
                <Button ref={objectRef}>One</Button>
                <Button ref={functionRef}>Two</Button>
            </>,
        );

        expect(objectRef.current).toBe(screen.getByRole('button', { name: 'One' }));
        expect(functionRef).toHaveBeenCalledWith(screen.getByRole('button', { name: 'Two' }));
    });

    it('turns another element into a button when asked, and leaves loading to a real button', () => {
        render(
            <Button asChild loading>
                <a href="/members">Members</a>
            </Button>,
        );

        const link = screen.getByRole('link', { name: 'Members' });
        fireEvent.click(link);

        expect(link).not.toHaveAttribute('aria-busy');
        expect(link).toHaveAttribute('data-slot', 'button');
    });
});
