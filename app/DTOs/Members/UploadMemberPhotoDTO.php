<?php

declare(strict_types=1);

namespace App\DTOs\Members;

use App\DTOs\Concerns\ResolvesActor;
use App\Http\Requests\Members\UploadMemberPhotoRequest;
use App\Models\Member;
use App\Models\User;
use Illuminate\Http\UploadedFile;

/**
 * A new profile photo for a member, as uploaded.
 */
final readonly class UploadMemberPhotoDTO
{
    use ResolvesActor;

    public function __construct(public User $actor, public Member $member, public UploadedFile $photo) {}

    public static function fromRequest(UploadMemberPhotoRequest $request): self
    {
        $photo = $request->file('photo');

        return new self(self::actorFrom($request), $request->member(), $photo instanceof UploadedFile ? $photo : throw new \LogicException('Validated photo must be a file.'));
    }
}
