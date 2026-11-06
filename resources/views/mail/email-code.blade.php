<x-mail::message>
# {{ $churchName }}

{{ $intro }}

<x-mail::panel>
{{ $code }}
</x-mail::panel>

{{ __('emails.code.valid_for', ['minutes' => $validMinutes]) }}

{{ __('emails.code.ignore') }}
</x-mail::message>
