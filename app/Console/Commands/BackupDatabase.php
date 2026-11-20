<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Exceptions\BackupFailedException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * The daily database backup (docs/16): dumps the database, encrypts the dump for the backup key, uploads it to the
 * separate backup bucket and removes backups older than the retention period. The plain dump is never kept: it is
 * deleted straight after encrypting, even when something fails. The database password goes to pg_dump in its
 * environment, never on the command line where other users could read it.
 */
class BackupDatabase extends Command
{
    protected $signature = 'db:backup';

    protected $description = 'Dump the database, encrypt it and upload it to the backup bucket';

    /**
     * Makes and stores one backup. A failure is reported to the error monitor and the command exits with an error.
     */
    public function handle(): int
    {
        $directory = (string) config('backup.work_directory');
        $name = 'backup-'.now()->utc()->format('Y-m-d-H-i-s').'.dump.gpg';
        $dump = "{$directory}/{$name}.plain";
        $encrypted = "{$directory}/{$name}";

        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }

        try {
            $this->dump($dump);
            $this->encrypt($dump, $encrypted);
            $this->upload($encrypted, $name);
            $this->prune();
        } catch (Throwable $failure) {
            report($failure);
            $this->error('The backup failed: '.$failure->getMessage());

            return self::FAILURE;
        } finally {
            @unlink($dump);
            @unlink($encrypted);
        }

        Log::info('Database backup complete', ['file' => $name]);
        $this->info("Backup stored as {$name}.");

        return self::SUCCESS;
    }

    /**
     * Writes a compressed (custom format) dump of the database to the given file.
     */
    private function dump(string $file): void
    {
        $database = config('database.connections.'.config('database.default'));

        $result = Process::env(['PGPASSWORD' => (string) $database['password']])->timeout(3600)->run([
            config('backup.pg_dump'), '-Fc',
            '-h', (string) $database['host'], '-p', (string) $database['port'],
            '-U', (string) $database['username'], '-f', $file, (string) $database['database'],
        ]);

        if ($result->failed()) {
            throw new BackupFailedException('dump', (int) $result->exitCode());
        }
    }

    /**
     * Encrypts the dump so only the holder of the private backup key can read it.
     */
    private function encrypt(string $plain, string $encrypted): void
    {
        $recipient = config('backup.gpg_recipient');

        if (blank($recipient)) {
            throw new BackupFailedException('encryption (BACKUP_GPG_RECIPIENT is not set)', 1);
        }

        $result = Process::timeout(3600)->run([
            config('backup.gpg'), '--batch', '--yes', '--trust-model', 'always',
            '--recipient', (string) $recipient, '--output', $encrypted, '--encrypt', $plain,
        ]);

        if ($result->failed()) {
            throw new BackupFailedException('encryption', (int) $result->exitCode());
        }
    }

    /**
     * Sends the encrypted file to the backup bucket as a stream, so a large dump is never held in memory.
     */
    private function upload(string $encrypted, string $name): void
    {
        $stream = fopen($encrypted, 'rb');

        if ($stream === false) {
            throw new BackupFailedException('upload', 1);
        }

        try {
            Storage::disk(config('backup.disk'))->writeStream(config('backup.folder').'/'.$name, $stream);
        } finally {
            fclose($stream);
        }
    }

    /**
     * Deletes backups older than the retention period.
     */
    private function prune(): void
    {
        $disk = Storage::disk(config('backup.disk'));
        $cutoff = now()->subDays(config('backup.retention_days'))->getTimestamp();

        foreach ($disk->files(config('backup.folder')) as $file) {
            if ($disk->lastModified($file) < $cutoff) {
                $disk->delete($file);
            }
        }
    }
}
