<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    config([
        'filesystems.disks.r2.key' => 'live-key',
        'filesystems.disks.r2.secret' => 'live-secret',
        'filesystems.disks.r2.bucket' => 'chms-live',
        'filesystems.disks.r2.endpoint' => 'https://abc123.r2.cloudflarestorage.com',
        'filesystems.disks.r2-backup.key' => 'backup-key',
        'filesystems.disks.r2-backup.secret' => 'backup-secret',
        'filesystems.disks.r2-backup.bucket' => 'chms-backups',
        'filesystems.disks.r2-backup.endpoint' => 'https://abc123.r2.cloudflarestorage.com',
    ]);
});

it('configures R2 the way Cloudflare documents it', function (): void {
    $config = config('filesystems.disks.r2');

    expect($config['driver'])->toBe('s3')
        ->and($config['region'])->toBe('auto')
        ->and($config['visibility'])->toBe('private')
        ->and($config['throw'])->toBeTrue()
        ->and($config)->not->toHaveKey('url');
});

it('keeps live files and backups in separate buckets with separate credentials', function (): void {
    $live = config('filesystems.disks.r2');
    $backup = config('filesystems.disks.r2-backup');

    expect($backup['bucket'])->not->toBe($live['bucket'])
        ->and($backup['key'])->not->toBe($live['key'])
        ->and($backup['secret'])->not->toBe($live['secret']);
});

it('signs a time-limited URL locally, without calling R2', function (): void {
    $this->freezeTime(); // The expiry is counted from the clock when signing; a second ticking over would read 3599.

    $url = Storage::disk('r2')->temporaryUrl('churches/01abc/members/01def/photo.jpg', now()->addHour());
    $parts = parse_url($url);
    parse_str($parts['query'], $query);

    expect($parts['host'])->toEndWith('abc123.r2.cloudflarestorage.com')
        ->and($url)->toContain('chms-live')
        ->and($url)->toContain('churches/01abc/members/01def/photo.jpg')
        ->and($query['X-Amz-Expires'])->toBe('3600')
        ->and($query)->toHaveKey('X-Amz-Signature');
});

it('does not put the secret key in a signed URL', function (): void {
    $url = Storage::disk('r2')->temporaryUrl('churches/01abc/exports/01def/members.csv', now()->addMinutes(5));

    expect($url)->not->toContain('live-secret');
});

it('raises instead of silently returning false when an upload fails', function (): void {
    config(['filesystems.disks.r2.endpoint' => 'http://127.0.0.1:9']);

    Storage::disk('r2')->put('health-check.txt', 'ok');
})->throws(Exception::class);
