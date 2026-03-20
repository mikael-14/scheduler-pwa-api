

<div role="alert"  class="shout-component border 
rounded-lg p-4 fi-color-custom bg-custom-50 text-custom-600 ring-custom-600/10 dark:bg-custom-400/10 dark:text-custom-400 dark:ring-custom-400/30" 
style="{{
    match($type) {
        'success' => '--c-50:var(--success-50);--c-400:var(--success-400);--c-600:var(--success-600);',
        'warning' => '--c-50:var(--warning-50);--c-400:var(--warning-400);--c-600:var(--warning-600);',
        'danger' => '--c-50:var(--danger-50);--c-400:var(--danger-400);--c-600:var(--danger-600);',
        default => '--c-50:var(--info-50);--c-400:var(--info-400);--c-600:var(--info-600);',
    }
}}" >
    <div class="flex">
        <div class="flex-shrink-0 ltr:mr-3 rtl:ml-3 text-{{$type}}-500">
            @switch($type)
            @case('success')
            <x-heroicon-o-check-circle class="h-5 w-5 shrink-0" />
            @break

            @case('info')
            <x-heroicon-o-information-circle class="h-5 w-5 shrink-0"/>
            @break

            @case('warning')
            <x-heroicon-o-exclamation-circle class="h-5 w-5 shrink-0"/>
            @break

            @case('danger')
            <x-heroicon-o-x-circle class="h-5 w-5 shrink-0"/>
            @break

            @endswitch
        </div>
        <div class="text-sm font-medium">
            {{$content}}
        </div>
    </div>
</div>