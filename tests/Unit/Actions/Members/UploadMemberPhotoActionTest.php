<?php

declare(strict_types=1);

use App\Actions\Members\UploadMemberPhotoAction;
use App\DTOs\Members\UploadMemberPhotoDTO;
use App\Models\Member;
use App\Repositories\MemberRepositoryInterface;
use App\Services\MemberPhotoService;
use Illuminate\Http\UploadedFile;

it('saves the path the photo service stored the file at', function (): void {
    $member = unitMember(persisted: true);
    $file = UploadedFile::fake()->image('me.jpg');
    $photos = Mockery::mock(MemberPhotoService::class);
    $photos->shouldReceive('store')->once()->with($member, $file)->andReturn('churches/01churchunit/members/01memberunit/photo.jpg');
    $members = Mockery::mock(MemberRepositoryInterface::class);
    $members->shouldReceive('save')->once()->andReturnUsing(fn (Member $m) => $m);

    $result = (new UploadMemberPhotoAction($photos, $members))->execute(new UploadMemberPhotoDTO(unitUser(), $member, $file));

    expect($result->photo_url)->toBe('churches/01churchunit/members/01memberunit/photo.jpg');
});
