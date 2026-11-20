<?php

declare(strict_types=1);

use App\Exceptions\BackupFailedException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

// docs/16: a daily encrypted database backup in a separate bucket, 30 days kept. Nothing real is run: pg_dump and gpg
// are faked and the bucket is a fake disk, so the tests check what would be run and what would be stored.

beforeEach(function (): void {
    $this->work = sys_get_temp_dir().'/backup-test-'.uniqid();
    config([
        'backup.work_directory' => $this->work,
        'backup.gpg_recipient' => 'backups@example.com',
        'database.connections.pgsql.password' => 'S3cret-pass',
    ]);
    Storage::fake('r2-backup');
});

afterEach(function (): void {
    foreach (glob($this->work.'/*') ?: [] as $leftover) {
        unlink($leftover);
    }
    @rmdir($this->work);
});

/**
 * Fakes pg_dump and gpg. pg_dump writes a "dump" to the file it is given, gpg writes the "encrypted" file, and every
 * command line and environment is recorded. A step named in $failing exits with an error.
 */
function fakeProcesses(array $failing = []): void
{
    $seen = new ArrayObject;
    test()->seen = $seen;

    Process::fake(function (PendingProcess $process) use ($failing, $seen): ProcessResult {
        $command = $process->command;
        $program = basename($command[0]);
        $seen->append(['program' => $program, 'command' => $command, 'env' => $process->environment]);

        if (in_array($program, $failing, true)) {
            return Process::result(errorOutput: 'something went wrong', exitCode: 1);
        }

        $output = $program === 'pg_dump' ? $command[array_search('-f', $command, true) + 1] : $command[array_search('--output', $command, true) + 1];
        file_put_contents($output, $program === 'pg_dump' ? 'PLAIN DUMP WITH MEMBER NAMES' : 'ENCRYPTED BYTES');

        return Process::result();
    });
}

it('dumps, encrypts and uploads a file named with the time', function (): void {
    fakeProcesses();

    $this->artisan('db:backup')->assertSuccessful();

    $files = Storage::disk('r2-backup')->files('backups/database');
    expect($files)->toHaveCount(1)
        ->and($files[0])->toMatch('#^backups/database/backup-\d{4}-\d{2}-\d{2}-\d{2}-\d{2}-\d{2}\.dump\.gpg$#')
        ->and(Storage::disk('r2-backup')->get($files[0]))->toBe('ENCRYPTED BYTES');
});

it('uploads only the encrypted file, never the plain dump', function (): void {
    fakeProcesses();

    $this->artisan('db:backup')->assertSuccessful();

    $stored = collect(Storage::disk('r2-backup')->allFiles())->map(fn (string $file) => Storage::disk('r2-backup')->get($file));
    expect($stored->contains(fn (string $content) => str_contains($content, 'MEMBER NAMES')))->toBeFalse();
});

it('encrypts for the backup key recipient', function (): void {
    fakeProcesses();

    $this->artisan('db:backup')->assertSuccessful();

    $gpg = collect($this->seen->getArrayCopy())->firstWhere('program', 'gpg');
    expect($gpg['command'])->toContain('--recipient')->toContain('backups@example.com')->toContain('--encrypt');
});

it('gives the database password to pg_dump in its environment and never on a command line', function (): void {
    fakeProcesses();

    $this->artisan('db:backup')->assertSuccessful();

    $dump = collect($this->seen->getArrayCopy())->firstWhere('program', 'pg_dump');
    expect($dump['env'])->toMatchArray(['PGPASSWORD' => 'S3cret-pass'])
        ->and(json_encode(array_column($this->seen->getArrayCopy(), 'command')))->not->toContain('S3cret-pass');
});

it('leaves no plain or encrypted file on the server', function (): void {
    fakeProcesses();

    $this->artisan('db:backup')->assertSuccessful();

    expect(glob($this->work.'/*'))->toBeEmpty();
});

it('removes backups older than 30 days and keeps newer ones', function (): void {
    fakeProcesses();
    $disk = Storage::disk('r2-backup');
    $disk->put('backups/database/old.dump.gpg', 'x');
    $disk->put('backups/database/recent.dump.gpg', 'x');
    touch($disk->path('backups/database/old.dump.gpg'), now()->subDays(31)->getTimestamp());
    touch($disk->path('backups/database/recent.dump.gpg'), now()->subDays(29)->getTimestamp());

    $this->artisan('db:backup')->assertSuccessful();

    $disk->assertMissing('backups/database/old.dump.gpg');
    $disk->assertExists('backups/database/recent.dump.gpg');
});

it('fails, reports it, uploads nothing and cleans up when pg_dump fails', function (): void {
    fakeProcesses(['pg_dump']);
    Log::spy();

    $this->artisan('db:backup')->assertFailed();

    expect(Storage::disk('r2-backup')->allFiles())->toBeEmpty()->and(glob($this->work.'/*'))->toBeEmpty();
});

it('fails and removes the plain dump when encryption fails', function (): void {
    fakeProcesses(['gpg']);

    $this->artisan('db:backup')->assertFailed();

    expect(Storage::disk('r2-backup')->allFiles())->toBeEmpty()->and(glob($this->work.'/*'))->toBeEmpty();
});

it('refuses to make an unencrypted backup when no key is set', function (): void {
    fakeProcesses();
    config(['backup.gpg_recipient' => null]);

    $this->artisan('db:backup')->assertFailed();

    expect(Storage::disk('r2-backup')->allFiles())->toBeEmpty()->and(glob($this->work.'/*'))->toBeEmpty();
});

it('does not put the password in the failure it reports', function (): void {
    expect((new BackupFailedException('dump', 1))->getMessage())->not->toContain('S3cret-pass')->toContain('dump');
});

it('runs every day at 02:00 UTC without overlapping', function (): void {
    $event = collect(app(Schedule::class)->events())->first(fn ($event): bool => str_contains($event->command, 'db:backup'));

    expect($event)->not->toBeNull()->and($event->expression)->toBe('0 2 * * *')->and($event->timezone)->toBe('UTC')->and($event->withoutOverlapping)->toBeTrue();
});
