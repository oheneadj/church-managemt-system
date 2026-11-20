<?php

declare(strict_types=1);

namespace App\Http\Requests\Messages;

use App\Enums\MemberStatus;
use App\Enums\StaffPermission;
use App\Http\Requests\Concerns\DelegatesToPolicy;
use App\Rules\MaxSmsParts;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The fields of a broadcast, shared by the question "what would this cost?" and the sending itself: the channel (text
 * messages only until WhatsApp broadcasts are open), the text, and who it is for. Needs `sms.send` (church admin, pastor).
 */
abstract class BroadcastInputRequest extends FormRequest
{
    use DelegatesToPolicy;

    /**
     * Asks whether the caller may send messages.
     */
    public function authorize(): bool
    {
        return $this->allowed(StaffPermission::SmsSend->value, []);
    }

    /**
     * Removes the spaces around the text, so a text of only spaces counts as empty.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('message'))) {
            $this->merge(['message' => trim($this->input('message'))]);
        }
    }

    /**
     * The rules that check this request's input.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            /** @var 'sms' */
            'channel' => ['required', Rule::in(['sms'])],
            'message' => ['required', 'string', 'max:2000', new MaxSmsParts((int) config('messages.broadcast_max_parts'))],
            'recipient_filter' => ['sometimes', 'array:status,department_id'],
            'recipient_filter.status' => ['sometimes', Rule::enum(MemberStatus::class)],
            /** @var string */
            'recipient_filter.department_id' => ['sometimes', 'ulid'],
        ];
    }

    /**
     * Plain sentences for the rules a person is most likely to hit.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['channel.in' => 'Only text message (sms) broadcasts are available for now. WhatsApp broadcasts are not open yet.'];
    }
}
