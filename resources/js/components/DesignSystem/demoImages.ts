/** A flat SVG as an address an image can load. Data addresses are allowed by the page's policy, so demonstration images need no host. */
function dataUri(svg: string): string {
    return `data:image/svg+xml;utf8,${encodeURIComponent(svg)}`;
}

/**
 * A calm landscape in the brand colours (flat shapes, no gradient), for showing a picture in its frame. `tone` moves the hills
 * between the greens and the gold so several pictures on a page look like a set.
 */
export function demoLandscape(tone: 'forest' | 'gold' | 'blue'): string {
    const hills = { forest: ['#245247', '#183C35'], gold: ['#A78A58', '#715934'], blue: ['#426B92', '#2F577D'] }[tone];
    const sun = tone === 'gold' ? '#F3EBDD' : '#C3A46B';

    return dataUri(
        `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 450"><rect width="800" height="450" fill="#F1F2EE"/><circle cx="600" cy="130" r="54" fill="${sun}"/><path d="M0 330 L170 190 L320 300 L470 170 L640 310 L800 220 V450 H0Z" fill="${hills[0]}"/><path d="M0 390 L200 290 L380 380 L560 280 L800 370 V450 H0Z" fill="${hills[1]}"/></svg>`,
    );
}

/** A plain head-and-shoulders silhouette on a tint, standing in for a profile photo in the showcase. */
export function demoPortrait(tint: string): string {
    return dataUri(
        `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200"><rect width="200" height="200" fill="${tint}"/><circle cx="100" cy="78" r="38" fill="#F7F5F0"/><path d="M28 200 C28 140 64 118 100 118 C136 118 172 140 172 200Z" fill="#F7F5F0"/></svg>`,
    );
}
