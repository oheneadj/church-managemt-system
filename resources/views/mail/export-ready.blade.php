<x-mail::message>
# {{ $churchName }}

{{ __('emails.export_ready.intro', ['what' => $what]) }}

[{{ __('emails.export_ready.download') }}]({{ $url }})

{{ __('emails.export_ready.valid_for', ['duration' => $duration, 'days' => $days]) }}

{{ __('emails.export_ready.ignore') }}
</x-mail::message>
