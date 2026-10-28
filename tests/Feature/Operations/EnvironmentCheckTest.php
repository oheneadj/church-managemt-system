<?php

declare(strict_types=1);

use App\Support\EnvironmentCheck;
use Illuminate\Config\Repository;

/** A configuration that passes every check, so each test breaks exactly one thing. */
function goodConfig(array $overrides = []): Repository
{
    $base = [
        'app' => ['key' => 'base64:abc', 'url' => 'https://app.example.com', 'debug' => false],
        'database' => ['connections' => ['pgsql' => ['host' => 'postgres', 'database' => 'chms', 'username' => 'chms', 'password' => 'x']], 'redis' => ['default' => ['host' => 'redis']]],
        'tenancy' => ['base_domain' => 'example.com'],
        'session' => ['domain' => null, 'secure' => true],
        'trustedproxy' => ['proxies' => ['10.0.0.0/8']],
        'services' => ['sms' => ['primary' => 'payperlez'], 'payperlez' => ['api_key' => 'key', 'sender_id' => 'sender'], 'whatsapp' => ['driver' => 'meta'], 'meta' => ['access_token' => 'token', 'phone_number_id' => '123', 'verify_token' => 'verify', 'app_secret' => 'secret']],
        'filesystems' => ['default' => 'r2'],
        'sentry' => ['dsn' => 'https://key@o0.ingest.sentry.io/1'],
        'backup' => ['gpg_recipient' => 'backups@example.com'],
    ];

    return new Repository(array_replace_recursive($base, $overrides));
}

it('accepts a complete production configuration', function (): void {
    expect(EnvironmentCheck::problems(goodConfig()))->toBe([]);
});

it('names each missing variable', function (string $key, string $value, string $expected): void {
    $config = goodConfig();
    $config->set($key, $value);

    expect(EnvironmentCheck::problems($config))->toContain($expected);
})->with([
    'no app key' => ['app.key', '', 'APP_KEY is not set.'],
    'no database password' => ['database.connections.pgsql.password', '', 'DB_PASSWORD is not set.'],
    'no redis host' => ['database.redis.default.host', '', 'REDIS_HOST is not set.'],
]);

it('rejects unsafe values', function (): void {
    $problems = EnvironmentCheck::problems(goodConfig([
        'app' => ['debug' => true],
        'tenancy' => ['base_domain' => 'localhost'],
        'session' => ['domain' => '.example.com'],
        'services' => ['sms' => ['primary' => 'log']],
        'filesystems' => ['default' => 'local'],
        'trustedproxy' => ['proxies' => ['*']],
    ]));

    expect($problems)->toHaveCount(6); // the bad config sets six wrong values; the Sentry DSN is still set
});

it('requires HTTPS and secure cookies, because TLS ends at the proxy', function (): void {
    $problems = EnvironmentCheck::problems(goodConfig(['app' => ['url' => 'http://app.example.com'], 'session' => ['secure' => false]]));

    expect($problems)->toContain('APP_URL must start with https://.')
        ->and($problems)->toContain('SESSION_SECURE_COOKIE must be true, so session cookies are only sent over HTTPS.');
});

it('never prints a secret value in a message', function (): void {
    $config = goodConfig();
    $config->set('database.connections.pgsql.host', '');

    expect(implode(' ', EnvironmentCheck::problems($config)))->not->toContain('x');
});

it('fails the env:check command on a bad configuration', function (): void {
    config(['app.debug' => true]);

    $this->artisan('env:check')->assertFailed();
});

it('requires the PayPerlez key and sender id when PayPerlez sends the SMS', function (string $missing): void {
    $problems = EnvironmentCheck::problems(goodConfig(['services' => ['payperlez' => [$missing => '']]]));

    expect($problems)->toBe(['PAYPERLEZ_API_KEY and PAYPERLEZ_SMS_SENDER_ID must be set when SMS_PRIMARY_PROVIDER is "payperlez".']);
})->with(['api_key', 'sender_id']);

it('requires the error monitor DSN outside development', function (): void {
    $problems = EnvironmentCheck::problems(goodConfig(['sentry' => ['dsn' => '']]));

    expect($problems)->toBe(['SENTRY_LARAVEL_DSN must be set, so errors reach the error monitor.']);
});

it('requires the backup key recipient outside development', function (): void {
    $problems = EnvironmentCheck::problems(goodConfig(['backup' => ['gpg_recipient' => '']]));

    expect($problems)->toBe(['BACKUP_GPG_RECIPIENT must be set, so the daily database backup can be encrypted.']);
});

it('requires a real WhatsApp driver and the webhook secrets outside development', function (): void {
    $problems = EnvironmentCheck::problems(goodConfig(['services' => ['whatsapp' => ['driver' => 'log'], 'meta' => ['verify_token' => '', 'app_secret' => '']]]));

    expect($problems)->toBe([
        'WHATSAPP_DRIVER must not be "log" outside development.',
        'META_VERIFY_TOKEN and META_APP_SECRET must be set, so WhatsApp webhooks can be verified.',
    ]);
});

it('requires the Meta token and phone number id when Meta sends the WhatsApp messages', function (string $missing): void {
    $problems = EnvironmentCheck::problems(goodConfig(['services' => ['meta' => [$missing => '']]]));

    expect($problems)->toBe(['META_ACCESS_TOKEN and META_PHONE_NUMBER_ID must be set when WHATSAPP_DRIVER is "meta".']);
})->with(['access_token', 'phone_number_id']);
