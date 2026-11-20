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
     * Lists what is wrong with the environment, or nothing when it is safe to run. These stop the container from starting.
     *
     * @return list<string>
     */
    public static function problems(Repository $config): array
    {
        return self::evaluate($config)['problems'];
    }

    /**
     * Lists what a test (staging) environment is missing but may start without: WhatsApp, the error monitor and the backup key.
     * In production the same settings are problems, so this is always empty there. A warning never stops the container.
     *
     * @return list<string>
     */
    public static function warnings(Repository $config): array
    {
        return self::evaluate($config)['warnings'];
    }

    /**
     * Checks everything once. The settings in `$optional` are warnings in a staging environment and problems anywhere else, so
     * a test site can be stood up without every integration while production stays exactly as strict as before.
     *
     * @return array{problems: list<string>, warnings: list<string>}
     */
    private static function evaluate(Repository $config): array
    {
        $problems = [];
        $optional = [];

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

        if ($config->get('services.whatsapp.driver') === 'log') {
            $optional[] = 'WHATSAPP_DRIVER must not be "log" outside development.';
        }

        if ($config->get('services.whatsapp.driver') === 'meta' && (blank($config->get('services.meta.access_token')) || blank($config->get('services.meta.phone_number_id')))) {
            $problems[] = 'META_ACCESS_TOKEN and META_PHONE_NUMBER_ID must be set when WHATSAPP_DRIVER is "meta".';
        }

        if (blank($config->get('services.meta.verify_token')) || blank($config->get('services.meta.app_secret'))) {
            $optional[] = 'META_VERIFY_TOKEN and META_APP_SECRET must be set, so WhatsApp webhooks can be verified.';
        }

        if (in_array($config->get('mail.default'), ['log', 'array'], true)) {
            $problems[] = 'MAIL_MAILER must not be "log" or "array" outside development.';
        }

        if ($config->get('mail.default') === 'smtp' && (blank($config->get('mail.mailers.smtp.host')) || blank($config->get('mail.mailers.smtp.username')) || blank($config->get('mail.mailers.smtp.password')))) {
            $problems[] = 'MAIL_HOST, MAIL_USERNAME and MAIL_PASSWORD must be set when MAIL_MAILER is "smtp".';
        }

        if (in_array($config->get('mail.from.address'), [null, '', 'hello@example.com'], true)) {
            $problems[] = 'MAIL_FROM_ADDRESS must be set to the real sending address.';
        }

        if (blank($config->get('backup.gpg_recipient'))) {
            $optional[] = 'BACKUP_GPG_RECIPIENT must be set, so the daily database backup can be encrypted.';
        }

        if (blank($config->get('sentry.dsn'))) {
            $optional[] = 'SENTRY_LARAVEL_DSN must be set, so errors reach the error monitor.';
        }

        if ($config->get('filesystems.default') === 'local') {
            $problems[] = 'FILESYSTEM_DISK must be an object-storage disk (r2), not "local".';
        }

        $staging = $config->get('app.env') === 'staging';

        return $staging ? ['problems' => $problems, 'warnings' => $optional] : ['problems' => [...$problems, ...$optional], 'warnings' => []];
    }
}
