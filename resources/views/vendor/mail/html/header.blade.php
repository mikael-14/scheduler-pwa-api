@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<img src="{{ rtrim((string) config('app.pwa_url'), '/') }}/img/icons/icon-192x192.png" class="logo" width="40" height="40" alt="">
<span class="brand">{{ trim($slot) }}</span>
</a>
</td>
</tr>
