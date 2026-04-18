<x-filament-widgets::widget>
    <x-filament::section>
        <div class="mb-6">
            {{ $this->filterForm }}
        </div>
        <x-slot name="heading">
            Legend
        </x-slot>

        <div style="display: flex; flex-wrap: wrap; align-items: center; row-gap: 8px;">
            @foreach($items as $item)
                <div style="display: flex; align-items: center; border-left: 1px solid rgba(156, 163, 175, 0.3); padding: 0 16px; margin-left: -1px;" 
                     class="first:border-l-0 first:pl-0">
                    
                    {{-- The Circle --}}
                    <div style="
                        width: 12px; 
                        height: 12px; 
                        border-radius: 9999px; 
                        margin-right: 8px;
                        flex-shrink: 0;
                        background-color: var(--{{ $item['color'] }}-500);"
                    ></div>
                    
                    {{-- The Text --}}
                    <span style="font-size: 0.875rem; font-weight: 500; white-space: nowrap;" class="text-gray-600 dark:text-gray-300">
                        {{ $item['label'] }}
                    </span>
                </div>
            @endforeach
        </div>

        {{-- This hidden block ensures Filament loads the necessary CSS variables --}}
        <div class="hidden text-primary-500 text-success-500 text-warning-500 text-danger-500 text-info-500 text-gray-500"></div>
    </x-filament::section>
</x-filament-widgets::widget>