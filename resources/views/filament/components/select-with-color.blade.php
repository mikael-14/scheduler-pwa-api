@if(isset($label))
<div class="fi-in-entry">
    <div class="fi-in-entry-label-col">
        <div class="fi-in-entry-label-ctn">
            <dt class="fi-in-entry-label">
                {{$label}}
            </dt>
        </div>
    </div>
</div>
@endif
<div class='fi-ta-color' style='padding: 0px;'>
    <div class='fi-ta-color-item'
        style='background-color: {{$color}};'>
    </div>
    <span class='fi-select-input-item-label text-sm text-gray-950 dark:text-white'>
        {{$name}}
    </span>
    {{-- its end date has passed: no new schedules, except ones recorded afterwards --}}
    @if(!empty($ended))
        <x-filament::badge color="gray" size="sm">{{ __('Ended') }}</x-filament::badge>
    @endif
</div>