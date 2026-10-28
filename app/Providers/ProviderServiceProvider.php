<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\SmsProvider;
use App\Exceptions\SmsProviderUnavailableException;
use App\Services\Sms\LogSmsProvider;
use Illuminate\Support\ServiceProvider;

/**
 * Binds third-party provider interfaces to the implementation chosen in config.
 * Switching provider is an .env change plus a worker restart, never a code change.
 */
class ProviderServiceProvider extends ServiceProvider
{
    /**
     * Binds each third-party contract to the driver chosen in config.
     */
    public function register(): void
    {
        $this->app->bind(SmsProvider::class, function (): SmsProvider {
            $driver = (string) config('services.sms.primary');

            if ($driver === 'log' && $this->app->isProduction()) {
                throw new SmsProviderUnavailableException('The log SMS driver cannot be used in production.');
            }

            return match ($driver) {
                'log' => new LogSmsProvider,
                default => throw new SmsProviderUnavailableException("SMS provider [{$driver}] is not implemented yet."),
            };
        });
    }
}
