import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { SearchBox } from '@/components/ui/SearchBox';

describe('SearchBox', () => {
    it('searches when Search is pressed, with the text trimmed', async () => {
        const onSearch = vi.fn();
        render(<SearchBox id="s" label="Search things" value="" onSearch={onSearch} />);

        await userEvent.type(screen.getByLabelText('Search things'), '  abena ');
        await userEvent.click(screen.getByRole('button', { name: 'Search' }));

        expect(onSearch).toHaveBeenCalledExactlyOnceWith('abena');
    });

    it('searches when Enter is pressed', async () => {
        const onSearch = vi.fn();
        render(<SearchBox id="s" label="Search things" value="" onSearch={onSearch} />);

        await userEvent.type(screen.getByLabelText('Search things'), 'kofi{Enter}');

        expect(onSearch).toHaveBeenCalledExactlyOnceWith('kofi');
    });

    it('starts with the current search and restarts when it changes', () => {
        const { rerender } = render(<SearchBox id="s" label="Search things" value="abena" onSearch={vi.fn()} />);
        expect(screen.getByLabelText('Search things')).toHaveValue('abena');

        rerender(<SearchBox id="s" label="Search things" value="" onSearch={vi.fn()} />);
        expect(screen.getByLabelText('Search things')).toHaveValue('');
    });
});
