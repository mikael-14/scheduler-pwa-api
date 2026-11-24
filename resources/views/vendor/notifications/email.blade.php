{{--
    Every notification email of the app (MailMessage). Besides Laravel's own data it takes, through
    viewData (see AppNotification::toMail and EmailDigest):
      status   danger | warning | success | info: the banner's colour
      kicker   a short word above the title ("Urgent", "Summary"...), optional
      heading  the banner's title (usually the subject)
      sections lists shown after the intro lines: [['heading' => ?, 'items' => [[label, meta?, url?, link?, status?]]]]
--}}
<x-mail::message>
@if (! empty($heading))
<x-mail::status :status="$status ?? 'info'" :kicker="$kicker ?? null">
{{ $heading }}
</x-mail::status>
@endif

{{-- Greeting --}}
@if (! empty($greeting))
@if (! empty($heading))
{{ $greeting }}
@else
# {{ $greeting }}
@endif
@elseif ($level === 'error')
# @lang('Whoops!')
@endif

{{-- Intro Lines --}}
@foreach ($introLines as $line)
{{ $line }}

@endforeach

{{-- Lists (days to book, news...), each row with its own link --}}
@foreach ($sections ?? [] as $section)
<x-mail::items :heading="$section['heading'] ?? null" :items="$section['items']" />

@endforeach

{{-- Action Button --}}
@isset($actionText)
<?php
    $color = match ($level) {
        'success', 'error' => $level,
        default => 'primary',
    };
?>
<x-mail::button :url="$actionUrl" :color="$color">
{{ $actionText }}
</x-mail::button>
@endisset

{{-- Outro Lines: smaller and grey (why this email came, what to do if it was not you) --}}
@foreach ($outroLines as $line)
<x-mail::note>
{{ $line }}
</x-mail::note>
@endforeach

{{-- Salutation: only when a notification sets one; the footer names the app --}}
@if (! empty($salutation))
{{ $salutation }}
@endif

{{-- Subcopy --}}
@isset($actionText)
<x-slot:subcopy>
{{ __('If the ":actionText" button does not work, copy this address into your browser:', ['actionText' => $actionText]) }} <span class="break-all">[{{ $displayableActionUrl }}]({{ $actionUrl }})</span>
</x-slot:subcopy>
@endisset
</x-mail::message>
