<?php

declare(strict_types=1);

namespace App\Http\Requests\Webhooks;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Meta's check that this address is ours (docs/14, section 3). Meta sends the verify token we set in its dashboard;
 * only a request that carries it is allowed. PHP turns the dots in `hub.mode` into underscores, hence `hub_mode`.
 */
class VerifyWhatsAppWebhookRequest extends FormRequest
{
    /**
     * Allows the request only for the right mode and token. With no token set in config nobody is allowed.
     */
    public function authorize(): bool
    {
        $token = (string) config('services.meta.verify_token');

        return $token !== ''
            && $this->query('hub_mode') === 'subscribe'
            && hash_equals($token, (string) $this->query('hub_verify_token'));
    }

    /**
     * The challenge Meta wants echoed back.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return ['hub_challenge' => ['required', 'string']];
    }

    /**
     * The challenge to echo back.
     */
    public function challenge(): string
    {
        return (string) $this->query('hub_challenge');
    }
}
