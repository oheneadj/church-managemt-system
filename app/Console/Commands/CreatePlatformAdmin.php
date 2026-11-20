<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Repositories\PlatformAdminRepositoryInterface;
use Illuminate\Console\Command;

use function Laravel\Prompts\password;

/**
 * Creates a platform admin account from the command line (D-069). There is no sign-up and no screen for it: only someone with
 * access to the server can make one, which is the point. The password is asked for, never given as an argument, so it does
 * not end up in a shell history.
 */
class CreatePlatformAdmin extends Command
{
    protected $signature = 'platform:create-admin {email : The sign-in email} {name : Their name}';

    protected $description = 'Create a platform admin account, apart from every church';

    /**
     * Asks for a password, checks it, and creates the account.
     */
    public function handle(PlatformAdminRepositoryInterface $admins): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('That is not an email address.');

            return self::FAILURE;
        }

        if ($admins->emailTaken($email)) {
            $this->error('A platform admin with that email already exists.');

            return self::FAILURE;
        }

        $minimum = (int) config('otp.password_min_length');
        $first = $this->secret("Password (at least {$minimum} characters)");
        $second = $this->secret('Confirm the password');

        if (mb_strlen($first) < $minimum || strlen($first) > (int) config('otp.password_max_bytes')) {
            $this->error("The password must be at least {$minimum} characters and at most ".config('otp.password_max_bytes').' bytes.');

            return self::FAILURE;
        }

        if ($first !== $second) {
            $this->error('The two passwords do not match.');

            return self::FAILURE;
        }

        $admins->create(['name' => trim((string) $this->argument('name')), 'email' => $email, 'password' => $first]);
        $this->info("Created the platform admin {$email}.");

        return self::SUCCESS;
    }
}
