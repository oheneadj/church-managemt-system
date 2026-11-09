<?php

declare(strict_types=1);

namespace App\Http\Requests\Webhooks;

use Illuminate\Foundation\Http\FormRequest;

/**
 * What PayPerlez posts to the webhook. It has no rules: the body is kept exactly as it arrived and read later on a worker, and
 * the signature middleware (VerifyPayperlezSignature) has already proved it came from PayPerlez. Anything unexpected must still
 * be answered 200, or PayPerlez sends it again.
 */
class ReceivePayperlezWebhookRequest extends FormRequest
{
    /**
     * Always allowed here: the signature middleware is the guard.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * No validation rules, on purpose (see the class note).
     *
     * @return array<string, never>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * The body exactly as it arrived.
     */
    public function rawPayload(): string
    {
        return $this->getContent();
    }
}
