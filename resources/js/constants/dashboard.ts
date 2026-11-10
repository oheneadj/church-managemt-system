/** How many lines of recent activity the home page shows. The server allows more; the home page only needs the latest few. */
export const DASHBOARD_ACTIVITY_COUNT = 10;

/** How long the activity feed is trusted before it is asked for again when the person comes back to the page, in milliseconds. */
export const ACTIVITY_STALE_MS = 30_000;

/** Services, newest first, over the default 12 weeks. A service counts only once something is recorded and only up to today, so the first is the last one that really happened. */
export const DASHBOARD_RECENT_SERVICES = { weeks: 12, groupBy: 'service' } as const;

/** This calendar month only, for every fund: the number the giving card leads with, set by the server against the month before. */
export const DASHBOARD_GIVING_MONTH = { months: 1, fundId: '' } as const;

/** The last six months, for the small trend on the giving card and the giving chart. */
export const DASHBOARD_GIVING_TREND = { months: 6, fundId: '' } as const;
