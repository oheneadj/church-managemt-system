<?php

declare(strict_types=1);

namespace App\Http\Requests\Giving;

use App\Http\Requests\Concerns\ValidatesDigitalGift;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a gift started on the public giving page (D-073). Open to everyone: anyone may give, and the limits protect the page.
 * The church is the one the address names, never anything sent in the body, so the fund must belong to that church. The giver
 * may leave a name. The hidden trap field is not checked here: a bot that fills it in is turned away quietly by the Action.
 */
class StartPublicGiftRequest extends FormRequest
{
    use ValidatesDigitalGift;

    /**
     * Open to everyone.
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
        $churchId = app('current.church')->getKey();

        return [
            'fund_type_id' => ['required', 'ulid', Rule::exists('fund_types', 'id')->where('church_id', $churchId)->where('is_active', true)->whereNull('deleted_at')],
            'giver_name' => ['nullable', 'string', 'max:100'],
            ...$this->digitalGiftRules(),
        ];
    }

    /**
     * Plain sentences for the rules a person is most likely to hit.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->digitalGiftMessages(),
            'fund_type_id.required' => 'Choose what you are giving to.',
            'fund_type_id.exists' => 'That is not available to give to. Choose again.',
            'giver_name.max' => 'The name can be at most 100 letters.',
        ];
    }

    /**
     * Whether the hidden trap field was filled in, which only a bot does.
     */
    public function trapFilled(): bool
    {
        return $this->filled((string) config('giving.trap_field'));
    }
}
