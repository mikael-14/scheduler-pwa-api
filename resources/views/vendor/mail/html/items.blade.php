@props(['heading' => null, 'items' => []])
@if (filled($heading))
<p class="items-heading">{{ $heading }}</p>
@endif
<table class="items" width="100%" cellpadding="0" cellspacing="0" role="presentation">
@foreach ($items as $item)
@php($status = in_array($item['status'] ?? null, ['danger', 'warning', 'success', 'info'], true) ? $item['status'] : null)
<tr>
@if ($status)
<td class="item-bar item-bar-{{ $status }}" width="4">&nbsp;</td>
@endif
<td class="item-text">
<p class="item-label">{{ $item['label'] }}</p>
@if (filled($item['meta'] ?? null))
<p class="item-meta">{{ $item['meta'] }}</p>
@endif
</td>
@if (filled($item['url'] ?? null))
<td class="item-link-cell" align="right">
<a href="{{ $item['url'] }}" class="item-link" target="_blank" rel="noopener">{{ $item['link'] ?? __('Open') }}</a>
</td>
@endif
</tr>
@endforeach
</table>
