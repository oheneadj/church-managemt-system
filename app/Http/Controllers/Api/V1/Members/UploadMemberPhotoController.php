<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Members;

use App\Http\Controllers\Controller;
use App\Http\Requests\Members\UploadMemberPhotoRequest;
use App\Http\Resources\MemberPhotoResource;
use App\Models\Member;
use App\Services\MemberPhotoService;
use Illuminate\Support\Facades\Gate;

/**
 * Replaces a member's profile photo (docs/08, Upload Member Photo).
 */
class UploadMemberPhotoController extends Controller
{
    public function __construct(
        private readonly MemberPhotoService $photos,
    ) {}

    public function __invoke(UploadMemberPhotoRequest $request, Member $member): MemberPhotoResource
    {
        Gate::authorize('update', $member);

        $this->photos->store($member, $request->file('photo'));

        return new MemberPhotoResource($member);
    }
}
