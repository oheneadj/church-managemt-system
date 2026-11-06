@props(['url'])
{{-- The framework's header shows a logo hotlinked from laravel.com when the app is named "Laravel". We never load an
     image from another site in an email (privacy, and it is the wrong brand), so the header is always the app name. --}}
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
{!! $slot !!}
</a>
</td>
</tr>
