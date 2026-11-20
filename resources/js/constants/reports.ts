/**
 * Choices on the reports page. The server accepts 1 to 52 weeks (config/reports.php); the page offers a few useful ones, and
 * the default is the server's own default of 12.
 */
export const ATTENDANCE_WEEK_CHOICES = [
    { value: '4', label: 'Last 4 weeks' },
    { value: '8', label: 'Last 8 weeks' },
    { value: '12', label: 'Last 12 weeks' },
    { value: '26', label: 'Last 26 weeks' },
    { value: '52', label: 'Last 52 weeks' },
] as const;

export const ATTENDANCE_WEEKS_DEFAULT = 12;

/** The narrowest a bar's slot may get, in pixels. A chart with more bars than fit scrolls sideways inside its own box. */
export const CHART_MIN_SLOT_PX = 12;

/** How many ticks the value axis aims for, and how many labels the bottom axis aims for. */
export const CHART_VALUE_TICKS = 4;
export const CHART_MAX_LABELS = 5;

/** How far back the monthly reports (giving and member growth) may look. The server accepts 1 to 24 months (config/reports.php); the default is its own 12. */
export const REPORT_MONTH_CHOICES = [
    { value: '3', label: 'Last 3 months' },
    { value: '6', label: 'Last 6 months' },
    { value: '12', label: 'Last 12 months' },
    { value: '24', label: 'Last 24 months' },
] as const;

export const REPORT_MONTHS_DEFAULT = 12;

/**
 * The chart colours funds use, in order. A fund takes the colour at its place in the church's list of funds, so it keeps its
 * colour whatever is filtered. Funds past these share one grey, "Other funds", and the table view still lists each one.
 */
export const FUND_SERIES_COLOURS = [
    'var(--viz-series-1)',
    'var(--viz-series-2)',
    'var(--viz-series-3)',
    'var(--viz-series-4)',
    'var(--viz-series-5)',
] as const;

export const OTHER_FUNDS_COLOUR = 'var(--viz-other)';
export const OTHER_FUNDS_KEY = 'other-funds';
export const OTHER_FUNDS_NAME = 'Other funds';

/** The text for a figure that does not exist, such as the change against months in which nothing was given. */
export const NO_FIGURE = '–';
