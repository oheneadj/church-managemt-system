<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Member;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Image;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Member profile photos (docs/14, Cloudflare R2). The bucket is private, so photos are only ever
 * reached through short-lived signed links.
 *
 * An upload is re-encoded, never stored as received: that applies the camera's rotation, then drops the
 * EXIF data (which can hold the GPS position of the person's home) and anything hidden in the file
 * (patterns/Pattern — File Uploads & Media.md).
 */
class MemberPhotoService
{
    /** Square side of the stored photo, in pixels. Enough for a profile page on a high-density phone. */
    private const SIZE = 512;

    private const QUALITY = 82;

    /** How long a signed link works. The API hands out a fresh one on every response. */
    private const LINK_LIFETIME_MINUTES = 60;

    /**
     * Stores the photo and saves its path on the member. The key is fixed per member, so a new photo
     * replaces the old one and nothing is left behind in the bucket.
     */
    public function store(Member $member, UploadedFile $file): string
    {
        $path = Image::fromUpload($file)
            ->orient()
            ->cover(self::SIZE, self::SIZE)
            ->toJpg()
            ->quality(self::QUALITY)
            ->storeAs("churches/{$member->church_id}/members/{$member->getKey()}", 'photo.jpg');

        if ($path === false) {
            throw new RuntimeException('The photo could not be stored.');
        }

        $member->update(['photo_url' => $path]);

        return $path;
    }

    /**
     * A signed link for a stored photo, or null when the member has none.
     */
    public function url(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        return Storage::temporaryUrl($path, now()->addMinutes(self::LINK_LIFETIME_MINUTES));
    }
}
