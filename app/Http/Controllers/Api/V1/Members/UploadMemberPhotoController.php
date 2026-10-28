<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Members;

use App\Actions\Members\UploadMemberPhotoAction;
use App\DTOs\Members\UploadMemberPhotoDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Members\UploadMemberPhotoRequest;
use App\Http\Resources\MemberPhotoResource;

/**
 * Replaces a member's profile photo (docs/08, Upload Member Photo).
 */
class UploadMemberPhotoController extends Controller
{
    public function __construct(private readonly UploadMemberPhotoAction $action) {}

    public function __invoke(UploadMemberPhotoRequest $request): MemberPhotoResource
    {
        return new MemberPhotoResource($this->action->execute(UploadMemberPhotoDTO::fromRequest($request)));
    }
}
