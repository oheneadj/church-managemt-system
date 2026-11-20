<?php

declare(strict_types=1);

namespace App\Http\Requests\Attendance;

use App\Http\Requests\Concerns\DelegatesToPolicy;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A member who scanned the screen asks which service the code is for. It is public: the code itself is the proof.
 */
class ShowQrServiceRequest extends FormRequest
{
    use DelegatesToPolicy;

    /**
     * Open to everyone: the code in the address is the proof, and rate limits protect the endpoint.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The rules that check this request's input.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * The scanned code from the address.
     */
    public function token(): string
    {
        return (string) $this->route('token');
    }
}
