<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\SmsProvider;
use App\Contracts\WhatsAppGateway;
use App\Exceptions\SmsProviderUnavailableException;
use App\Exceptions\WhatsAppUnavailableException;
use App\Services\Sms\LogSmsProvider;
use App\Services\Sms\PayperlezSmsProvider;
use App\Services\WhatsApp\LogWhatsAppGateway;
use App\Services\WhatsApp\MetaWhatsAppGateway;
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
                'payperlez' => $this->payperlez(),
                default => throw new SmsProviderUnavailableException("SMS provider [{$driver}] is not implemented yet."),
            };
        });

        $this->app->bind(WhatsAppGateway::class, function (): WhatsAppGateway {
            $driver = (string) config('services.whatsapp.driver');

            if ($driver === 'log' && $this->app->isProduction()) {
                throw new WhatsAppUnavailableException('The log WhatsApp driver cannot be used in production.');
            }

            return match ($driver) {
                'log' => new LogWhatsAppGateway,
                'meta' => $this->meta(),
                default => throw new WhatsAppUnavailableException("WhatsApp driver [{$driver}] is not implemented."),
            };
        });
    }

    /**
     * The Meta gateway, once its token and phone number id are set. Without them every send would fail, so I stop early.
     */
    private function meta(): MetaWhatsAppGateway
    {
        if (blank(config('services.meta.access_token')) || blank(config('services.meta.phone_number_id'))) {
            throw new WhatsAppUnavailableException('META_ACCESS_TOKEN and META_PHONE_NUMBER_ID must be set to use Meta.');
        }

        return new MetaWhatsAppGateway;
    }

    /**
     * The PayPerlez SMS provider, once its key and sender id are set. Without them every send would fail, so I stop early.
     */
    private function payperlez(): PayperlezSmsProvider
    {
        if (blank(config('services.payperlez.api_key')) || blank(config('services.payperlez.sender_id'))) {
            throw new SmsProviderUnavailableException('PAYPERLEZ_API_KEY and PAYPERLEZ_SMS_SENDER_ID must be set to use PayPerlez.');
        }

        return new PayperlezSmsProvider;
    }
}
