<div class="flex rounded-md relative">
    <div class="flex gap-x-1">
        <div>{{ $first }}</div>
        @if ($second)
        <div> - <i>{{ $second }}</i></div>
        @endif
    </div>
</div>