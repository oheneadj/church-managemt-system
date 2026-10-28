<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\PhotoNotStoredException;
use App\Models\Member;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Image;
use Illuminate\Support\Facades\Storage;

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
    /**
     * Stores the processed photo and returns its path; the Action saves the path on the member. The key is
     * fixed per member, so a new photo replaces the old one and nothing is left behind in the bucket.
     */
    public function store(Member $member, UploadedFile $file): string
    {
        $path = Image::fromUpload($file)
            ->orient()
            ->cover(config('members.photo_size'), config('members.photo_size'))
            ->toJpg()
            ->quality(config('members.photo_quality'))
            ->storeAs("churches/{$member->church_id}/members/{$member->getKey()}", config('members.photo_file_name'));

        if ($path === false) {
            throw new PhotoNotStoredException;
        }

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

        return Storage::temporaryUrl($path, now()->addMinutes(config('members.photo_link_minutes')));
    }
}
