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
export const CHART_MAX_LABELS = 6;
