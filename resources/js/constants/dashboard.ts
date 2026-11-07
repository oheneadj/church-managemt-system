/** How many lines of recent activity the home page shows. The server allows more; the home page only needs the latest few. */
export const DASHBOARD_ACTIVITY_COUNT = 10;

/** How long the activity feed is trusted before it is asked for again when the person comes back to the page, in milliseconds. */
export const ACTIVITY_STALE_MS = 30_000;
