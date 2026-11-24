@props(['status' => 'info', 'kicker' => null])
<table class="status status-{{ in_array($status, ['danger', 'warning', 'success', 'info'], true) ? $status : 'info' }}" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="status-cell">
@if (filled($kicker))
<p class="status-kicker">{{ $kicker }}</p>
@endif
<p class="status-title">{{ trim($slot) }}</p>
</td>
</tr>
</table>
