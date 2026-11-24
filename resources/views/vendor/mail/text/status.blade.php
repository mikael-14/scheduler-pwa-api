@props(['status' => 'info', 'kicker' => null])
@if (filled($kicker))
{{ mb_strtoupper($kicker) }}
@endif
{{ trim($slot) }}
