<?php

declare(strict_types=1);

namespace App\Http\Requests\Giving;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A visitor to the public giving page asks what it should show (D-073). Open to everyone: the page is for anyone who wants to
 * give, and rate limits protect it.
 */
class ShowGivingPageRequest extends FormRequest
{
    /**
     * Open to everyone.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Nothing is sent with this request.
     *
     * @return array<string, never>
     */
    public function rules(): array
    {
        return [];
    }
}
