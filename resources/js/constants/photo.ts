/**
 * What a member photo upload may be, mirrored from config/members.php. The browser checks this first to save a
 * round trip; the server checks the real file type and size again and is what actually enforces it.
 */
export const PHOTO_TYPES = ['image/jpeg', 'image/png'] as const;

export const PHOTO_MAX_BYTES = 5 * 1024 * 1024;
