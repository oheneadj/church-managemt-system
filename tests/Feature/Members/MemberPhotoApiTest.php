<?php

declare(strict_types=1);

use App\Enums\MemberStatus;
use App\Models\Church;
use App\Models\Member;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

function photoStaff(Church $church, string $role): User
{
    $user = User::factory()->for($church)->create();
    $user->assignRole($role);

    return $user;
}

/** Signs the staff member in for the next request, the way Laravel's tests do. */
function photoAs(User $user): mixed
{
    Sanctum::actingAs($user, ['*']);

    return test();
}

function photoUrl(Member $member): string
{
    return 'http://grace.localhost/api/v1/members/'.$member->id.'/photo';
}

/** A real JPEG with an EXIF-style APP1 segment carrying a marker, standing in for a phone's GPS data. */
function jpegWithHiddenData(string $marker): UploadedFile
{
    $image = imagecreatetruecolor(600, 800);
    ob_start();
    imagejpeg($image);
    $jpeg = (string) ob_get_clean();

    $payload = "Exif\0\0".$marker;
    $withExif = substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($payload) + 2).$payload.substr($jpeg, 2);

    $path = tempnam(sys_get_temp_dir(), 'photo');
    file_put_contents($path, $withExif);

    return new UploadedFile($path, 'IMG_0042.jpg', 'image/jpeg', null, true);
}

beforeEach(function (): void {
    Storage::fake('local');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->church = Church::factory()->create(['slug' => 'grace']);
    $this->admin = photoStaff($this->church, 'church-admin');
    $this->member = Member::factory()->create(['church_id' => $this->church->id]);
    $this->other = Church::factory()->create(['slug' => 'other']);
});

it('rejects the endpoint without a token', function (): void {
    $this->postJson(photoUrl($this->member), [])->assertUnauthorized();
});

describe('uploading', function (): void {
    it('stores a re-encoded square JPEG under the member\'s own key and returns a signed link', function (): void {
        $response = photoAs($this->admin)->postJson(photoUrl($this->member), ['photo' => UploadedFile::fake()->image('me.jpg', 1200, 800)])->assertOk();

        $path = "churches/{$this->church->id}/members/{$this->member->id}/photo.jpg";
        expect($response->json('photo_url'))->toBeString()->toContain('photo.jpg')
            ->and($this->member->fresh()->photo_url)->toBe($path);
        Storage::disk('local')->assertExists($path);

        $size = getimagesizefromstring(Storage::disk('local')->get($path));
        expect($size[0])->toBe(512)->and($size[1])->toBe(512)->and($size['mime'])->toBe('image/jpeg');
    });

    it('accepts a PNG and stores it as JPEG', function (): void {
        photoAs($this->admin)->postJson(photoUrl($this->member), ['photo' => UploadedFile::fake()->image('me.png', 300, 300)])->assertOk();

        $stored = Storage::disk('local')->get($this->member->fresh()->photo_url);
        expect(getimagesizefromstring($stored)['mime'])->toBe('image/jpeg');
    });

    it('strips hidden camera data from the stored file', function (): void {
        photoAs($this->admin)->postJson(photoUrl($this->member), ['photo' => jpegWithHiddenData('GPS-5.6037N-0.1870W')])->assertOk();

        expect(Storage::disk('local')->get($this->member->fresh()->photo_url))->not->toContain('GPS-5.6037N');
    });

    it('never uses the name the client gave the file', function (): void {
        photoAs($this->admin)->postJson(photoUrl($this->member), ['photo' => UploadedFile::fake()->image('../../etc/passwd.jpg', 100, 100)])->assertOk();

        expect($this->member->fresh()->photo_url)->not->toContain('passwd')->not->toContain('..');
    });

    it('replaces the old photo and leaves nothing behind', function (): void {
        photoAs($this->admin)->postJson(photoUrl($this->member), ['photo' => UploadedFile::fake()->image('a.jpg', 100, 100)])->assertOk();
        photoAs($this->admin)->postJson(photoUrl($this->member), ['photo' => UploadedFile::fake()->image('b.jpg', 200, 100)])->assertOk();

        expect(Storage::disk('local')->allFiles())->toHaveCount(1);
    });

    it('lets the pastor upload', function (): void {
        photoAs(photoStaff($this->church, 'pastor'))->postJson(photoUrl($this->member), ['photo' => UploadedFile::fake()->image('a.jpg', 100, 100)])->assertOk();
    });

    it('rejects files that are not JPG or PNG photos', function (string $name, string $contents): void {
        $file = UploadedFile::fake()->createWithContent($name, $contents);

        photoAs($this->admin)->postJson(photoUrl($this->member), ['photo' => $file])->assertUnprocessable()->assertJsonValidationErrors('photo');

        expect($this->member->fresh()->photo_url)->toBeNull()->and(Storage::disk('local')->allFiles())->toBeEmpty();
    })->with([
        'php script named .jpg' => ['photo.jpg', '<?php echo "owned";'],
        'plain text' => ['photo.png', 'hello'],
        'svg' => ['photo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'],
    ]);

    it('rejects a GIF, a missing file and a text field', function (): void {
        photoAs($this->admin)->postJson(photoUrl($this->member), ['photo' => UploadedFile::fake()->image('a.gif', 50, 50)])->assertUnprocessable();
        photoAs($this->admin)->postJson(photoUrl($this->member), [])->assertUnprocessable()->assertJsonValidationErrors('photo');
        photoAs($this->admin)->postJson(photoUrl($this->member), ['photo' => 'not a file'])->assertUnprocessable();
    });

    it('rejects a photo over 5 MB or over 4096 pixels', function (): void {
        photoAs($this->admin)->postJson(photoUrl($this->member), ['photo' => UploadedFile::fake()->image('big.jpg', 100, 100)->size(5121)])->assertUnprocessable();
        photoAs($this->admin)->postJson(photoUrl($this->member), ['photo' => UploadedFile::fake()->image('wide.jpg', 4097, 100)])->assertUnprocessable();
    });

    it('is forbidden for roles that cannot edit members', function (string $role): void {
        photoAs(photoStaff($this->church, $role))->postJson(photoUrl($this->member), ['photo' => UploadedFile::fake()->image('a.jpg', 100, 100)])->assertForbidden();

        expect(Storage::disk('local')->allFiles())->toBeEmpty();
    })->with(['finance-secretary', 'department-head', 'youth-pastor']);

    it('keeps a deceased member read-only', function (): void {
        $this->member->update(['status' => MemberStatus::Deceased]);

        photoAs($this->admin)->postJson(photoUrl($this->member), ['photo' => UploadedFile::fake()->image('a.jpg', 100, 100)])->assertForbidden();
    });

    it('returns 404 for another church\'s member and stores nothing', function (): void {
        $foreign = Member::factory()->create(['church_id' => $this->other->id]);

        photoAs($this->admin)->postJson(photoUrl($foreign), ['photo' => UploadedFile::fake()->image('a.jpg', 100, 100)])->assertNotFound();

        expect($foreign->fresh()->photo_url)->toBeNull()->and(Storage::disk('local')->allFiles())->toBeEmpty();
    });

    it('is rate limited', function (): void {
        foreach (range(1, 20) as $attempt) {
            photoAs($this->admin)->postJson(photoUrl($this->member), [])->assertUnprocessable();
        }

        photoAs($this->admin)->postJson(photoUrl($this->member), [])->assertStatus(429);
    });
});

describe('showing the photo', function (): void {
    // The fake test disk marks a time-limited link with expiration=; real R2 signing is covered in R2DiskTest.
    it('puts a signed link on the member record and in the directory, never the storage path', function (): void {
        photoAs($this->admin)->postJson(photoUrl($this->member), ['photo' => UploadedFile::fake()->image('a.jpg', 100, 100)])->assertOk();
        $path = $this->member->fresh()->photo_url;

        $show = photoAs($this->admin)->getJson('http://grace.localhost/api/v1/members/'.$this->member->id)->assertOk()->json('data.photo_url');
        $list = photoAs($this->admin)->getJson('http://grace.localhost/api/v1/members')->assertOk()->json('data.0.photo_url');

        foreach ([$show, $list] as $link) {
            expect($link)->toBeString()->toContain('expiration=')->not->toBe($path);
        }
    });

    it('is null for a member with no photo', function (): void {
        photoAs($this->admin)->getJson('http://grace.localhost/api/v1/members/'.$this->member->id)->assertJsonPath('data.photo_url', null);
    });
});
