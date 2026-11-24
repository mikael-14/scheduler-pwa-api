@props(['heading' => null, 'items' => []])
@if (filled($heading))
{{ $heading }}
@endif
@foreach ($items as $item)
- {{ $item['label'] }}@if (filled($item['meta'] ?? null)) — {{ $item['meta'] }}@endif
@if (filled($item['url'] ?? null))
  {{ $item['url'] }}
@endif
@endforeach
