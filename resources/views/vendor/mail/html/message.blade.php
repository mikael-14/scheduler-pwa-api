@php($pwa = rtrim((string) config('app.pwa_url'), '/'))
<x-mail::layout>
{{-- Header: the app's icon and name, linking to the PWA --}}
<x-slot:header>
<x-mail::header :url="$pwa">
{{ config('app.name') }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
{{ config('app.name') }} · [{{ __('Open the app') }}]({{ $pwa }}) · [{{ __('Notification settings') }}]({{ $pwa }}/settings)
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
