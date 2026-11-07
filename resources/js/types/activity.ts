/**
 * The shape of the recent activity feed (docs/08-api.md, "Get Recent Activity").
 */

/** One line of the feed: the plain sentence the server wrote, who did it and when. It holds nothing more than the sentence says. */
export interface ActivityLine {
    id: string;
    kind: string;
    text: string;
    actor_name: string | null;
    occurred_at: string | null;
}
