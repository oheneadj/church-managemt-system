<?php

declare(strict_types=1);

namespace App\Actions\Members;

use App\DTOs\Members\UploadMemberPhotoDTO;
use App\Models\Member;
use App\Repositories\MemberRepositoryInterface;
use App\Services\MemberPhotoService;

/**
 * Replaces a member's profile photo: the service processes and stores the file, then the path is saved.
 */
final class UploadMemberPhotoAction
{
    public function __construct(
        private readonly MemberPhotoService $photos,
        private readonly MemberRepositoryInterface $members,
    ) {}

    public function execute(UploadMemberPhotoDTO $dto): Member
    {
        $member = $dto->member;
        $member->photo_url = $this->photos->store($member, $dto->photo);

        return $this->members->save($member);
    }
}
