/**
 * How long work has to run before the loading indicator is shown, in milliseconds. Under this the button only blocks a second
 * press, so a quick save never flashes a spinner (docs/design-system/interactions.md, sections 1 and 21).
 */
export const LOADING_INDICATOR_DELAY_MS = 400;

/**
 * The screen width from which a page draws a table instead of a list of cards: 768px, the same width Tailwind's `md:` starts at, so a
 * page's layout and its styles change together.
 */
export const WIDE_SCREEN_QUERY = '(min-width: 768px)';
