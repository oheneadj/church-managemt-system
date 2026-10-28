<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Config\Repository;

/**
 * Lists what is wrong with the configuration a deployed environment started with.
 * I report names and reasons only, never values, so the message is safe to show in a log or a terminal.
 */
final class EnvironmentCheck
{
    /** @var array<string, string> config key => the env variable an operator sets */
    private const REQUIRED = [
        'app.key' => 'APP_KEY',
        'app.url' => 'APP_URL',
        'database.connections.pgsql.host' => 'DB_HOST',
        'database.connections.pgsql.database' => 'DB_DATABASE',
        'database.connections.pgsql.username' => 'DB_USERNAME',
        'database.connections.pgsql.password' => 'DB_PASSWORD',
        'database.redis.default.host' => 'REDIS_HOST',
    ];

    /**
     * Lists what is wrong with the environment, or nothing when it is safe to run.
     *
     * @return list<string>
     */
    public static function problems(Repository $config): array
    {
        $problems = [];

        foreach (self::REQUIRED as $key => $variable) {
            if (blank($config->get($key))) {
                $problems[] = "{$variable} is not set.";
            }
        }

        if ($config->get('app.debug') === true) {
            $problems[] = 'APP_DEBUG must be false.';
        }

        if (in_array($config->get('tenancy.base_domain'), [null, '', 'localhost'], true)) {
            $problems[] = 'TENANT_BASE_DOMAIN must be set to the real platform domain.';
        }

        if (! str_starts_with((string) $config->get('app.url'), 'https://')) {
            $problems[] = 'APP_URL must start with https://.';
        }

        if ($config->get('session.secure') !== true) {
            $problems[] = 'SESSION_SECURE_COOKIE must be true, so session cookies are only sent over HTTPS.';
        }

        $proxies = $config->get('trustedproxy.proxies');

        if ($proxies === '*' || $proxies === '**' || (is_array($proxies) && in_array('*', $proxies, true))) {
            $problems[] = 'TRUSTED_PROXIES must list the proxy address ranges, not "*".';
        }

        if ($config->get('session.domain') !== null) {
            $problems[] = 'SESSION_DOMAIN must be empty, so session cookies stay on one church subdomain.';
        }

        if ($config->get('services.sms.primary') === 'log') {
            $problems[] = 'SMS_PRIMARY_PROVIDER must not be "log" outside development.';
        }

        if ($config->get('services.sms.primary') === 'payperlez' && (blank($config->get('services.payperlez.api_key')) || blank($config->get('services.payperlez.sender_id')))) {
            $problems[] = 'PAYPERLEZ_API_KEY and PAYPERLEZ_SMS_SENDER_ID must be set when SMS_PRIMARY_PROVIDER is "payperlez".';
        }

        if ($config->get('filesystems.default') === 'local') {
            $problems[] = 'FILESYSTEM_DISK must be an object-storage disk (r2), not "local".';
        }

        return $problems;
    }
}
