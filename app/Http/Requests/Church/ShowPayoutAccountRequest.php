<?php

declare(strict_types=1);

namespace App\Http\Requests\Church;

use App\Http\Requests\Concerns\DelegatesToPolicy;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Checks that the caller may see where their church's gifts are paid out. Only the church admin may: it is money.
 */
class ShowPayoutAccountRequest extends FormRequest
{
    use DelegatesToPolicy;

    /**
     * Asks the policy whether the caller may manage their church's payout account.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $church = $user instanceof User ? $user->church : null;

        return $church !== null && $this->allowed('update', $church);
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
