<x-filament-widgets::widget>
    <x-filament::section :heading="__('This week')" :description="$range">
        <ul class="space-y-1">
            @foreach ($days as $day)
                @php($people = $day['approved'] + $day['pending'])
                <li>
                    <a href="{{ $day['url'] }}"
                        @class([
                            'flex items-center gap-3 rounded-lg px-2 py-1.5 text-sm transition hover:bg-gray-50 dark:hover:bg-white/5',
                            'bg-primary-50 dark:bg-primary-500/10' => $day['isToday'],
                            'opacity-60' => $day['isPast'],
                        ])>
                        {{-- the day --}}
                        <span @class([
                            'w-20 shrink-0 capitalize',
                            'font-semibold text-primary-700 dark:text-primary-400' => $day['isToday'],
                            'text-gray-700 dark:text-gray-300' => ! $day['isToday'],
                        ])>
                            {{ $day['date']->translatedFormat('D d') }}
                        </span>

                        {{-- people: approved (green) and pending (amber) --}}
                        <span class="flex h-2.5 min-w-0 flex-1 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10"
                            title="{{ __(':approved approved · :pending pending', ['approved' => $day['approved'], 'pending' => $day['pending']]) }}">
                            <span class="h-full bg-emerald-500" style="width: {{ $day['approved'] / $max * 100 }}%"></span>
                            <span class="h-full bg-amber-400" style="width: {{ $day['pending'] / $max * 100 }}%"></span>
                        </span>

                        <span class="w-24 shrink-0 text-right tabular-nums text-gray-700 dark:text-gray-300">
                            {{ trans_choice(':count person|:count people', $people) }}
                            @if ($day['pending'])
                                <span class="text-xs text-amber-600 dark:text-amber-400">({{ $day['pending'] }}⏳)</span>
                            @endif
                        </span>

                        {{-- the primary type: approved people that day --}}
                        @if ($primary)
                            <span @class([
                                'w-24 shrink-0 truncate text-right text-xs',
                                'font-semibold text-danger-600 dark:text-danger-400' => $day['primary'] === 0 && ! $day['isPast'],
                                'text-gray-500 dark:text-gray-400' => $day['primary'] !== 0 || $day['isPast'],
                            ]) title="{{ $primary->name }}">
                                @if ($day['primary'] === 0 && ! $day['isPast'])
                                    ⚠ {{ $primary->name }}
                                @else
                                    {{ $primary->name }}: {{ $day['primary'] }}
                                @endif
                            </span>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>

        <p class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
            <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-emerald-500"></span>{{ __('Approved') }}</span>
            <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-amber-400"></span>{{ __('Pending') }}</span>
            @if ($primary)
                <span>{{ __(':type: approved people (⚠ nobody yet)', ['type' => $primary->name]) }}</span>
            @endif
        </p>
    </x-filament::section>
</x-filament-widgets::widget>
