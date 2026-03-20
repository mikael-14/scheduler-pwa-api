@php
$data = $getRecord()->pet_has_medicines;
$date_format = config('filament.date_time_format');
@endphp

<div class="flex flex-col ">
    <div class="fi-ta-ctn divide-y divide-gray-200 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:divide-white/10 dark:bg-gray-900 dark:ring-white/10">
        <div class="fi-ta-content relative divide-y divide-gray-200 overflow-x-auto dark:divide-white/10 dark:border-t-white/10">
            @if ($data->isNotEmpty())
            <table class="fi-ta-table w-full table-auto divide-y divide-gray-200 text-start dark:divide-white/5">
                <thead class="bg-gray-50 dark:bg-white/5">
                    <tr>
                        <th class="fi-ta-header-cell px-3 py-3.5 sm:first-of-type:ps-6 sm:last-of-type:pe-6">
                            <span class="group flex w-full items-center gap-x-1 whitespace-nowrap justify-start">
                                <span class="fi-ta-header-cell-label text-sm font-semibold text-gray-950 dark:text-white">
                                    {{  __('Date') }}
                                </span>
                            </span>
                        </th>
                        <th class="fi-ta-header-cell px-3 py-3.5 sm:first-of-type:ps-6 sm:last-of-type:pe-6">
                            <span class="group flex w-full items-center gap-x-1 whitespace-nowrap justify-start">
                                <span class="fi-ta-header-cell-label text-sm font-semibold text-gray-950 dark:text-white">
                                    {{ __('Status') }}
                                </span>
                            </span>
                        </th>
                        <th class="fi-ta-header-cell px-3 py-3.5 sm:first-of-type:ps-6 sm:last-of-type:pe-6">
                            <span class="group flex w-full items-center gap-x-1 whitespace-nowrap justify-start">
                                <span class="fi-ta-header-cell-label text-sm font-semibold text-gray-950 dark:text-white">
                                    {{ __('Administered') }}
                                </span>
                            </span>
                        </th>
                        <th class="fi-ta-header-cell px-3 py-3.5 sm:first-of-type:ps-6 sm:last-of-type:pe-6">
                            <span class="group flex w-full items-center gap-x-1 whitespace-nowrap justify-start">
                                <span class="fi-ta-header-cell-label text-sm font-semibold text-gray-950 dark:text-white">
                                    {{ __('Dosage') }}
                                </span>
                            </span>
                        </th>
                        <th class="fi-ta-header-cell px-3 py-3.5 sm:first-of-type:ps-6 sm:last-of-type:pe-6">
                            <span class="group flex w-full items-center gap-x-1 whitespace-nowrap justify-start">
                                <span class="fi-ta-header-cell-label text-sm font-semibold text-gray-950 dark:text-white">
                                    {{ __('Observation') }}
                                </span>
                            </span>
                        </th>
                        <th class="fi-ta-header-cell px-3 py-3.5 sm:first-of-type:ps-6 sm:last-of-type:pe-6">
                            <span class="fi-ta-header-cell-label text-sm font-semibold text-gray-950 dark:text-white">
                                {{ __('Person') }}
                            </span>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 whitespace-nowrap dark:divide-white/5">
                    @foreach($data as $row)
                    <tr class="fi-ta-row [@media(hover:hover)]:transition [@media(hover:hover)]:duration-75 hover:bg-gray-50 dark:hover:bg-white/5">
                        <td class="fi-ta-cell p-0 first-of-type:ps-1 last-of-type:pe-1 sm:first-of-type:ps-3 sm:last-of-type:pe-3">
                            <div class="fi-ta-col-wrp">
                                <div class="fi-ta-text grid gap-y-1 px-3 py-4">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <div class="flex max-w-max">
                                            <div class="fi-ta-text-item inline-flex items-center gap-1.5 ">
                                                <span class="fi-ta-text-item-label text-sm text-gray-950 dark:text-white">
                                                    {{optional($row->date)->format($date_format)}}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </td>
                        <td class="fi-ta-cell p-0 first-of-type:ps-1 last-of-type:pe-1 sm:first-of-type:ps-3 sm:last-of-type:pe-3 fi-table-cell-status">
                            <div class="fi-ta-col-wrp">
                                <div class="fi-ta-text grid gap-y-1 px-3 py-4">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <div class="flex w-max">
                                            <div style="{{
                                                            match($row->status) {
                                                                'completed' => '--c-50:var(--success-50);--c-400:var(--success-400);--c-600:var(--success-600);',
                                                                'on_hold' => '--c-50:var(--warning-50);--c-400:var(--warning-400);--c-600:var(--warning-600);',
                                                                'canceled' => '--c-50:var(--danger-50);--c-400:var(--danger-400);--c-600:var(--danger-600);',
                                                                default => '--c-50:var(--info-50);--c-400:var(--info-400);--c-600:var(--info-600);',
                                                            }
                                                        }}" 
class="fi-badge flex items-center justify-center gap-x-1 rounded-md text-xs font-medium ring-1 ring-inset px-2 min-w-[theme(spacing.6)] py-1 fi-color-custom bg-custom-50 text-custom-600 ring-custom-600/10 dark:bg-custom-400/10 dark:text-custom-400 dark:ring-custom-400/30">
                                                <span class="grid">
                                                    <span class="truncate">
                                                        {{trans('pet/prescriptionmedicines.status.' . $row->status)}}
                                                    </span>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="fi-ta-cell p-0 first-of-type:ps-1 last-of-type:pe-1 sm:first-of-type:ps-3 sm:last-of-type:pe-3 fi-table-cell-dosage">
                            <div class="fi-ta-col-wrp">
                                <div class="fi-ta-text grid gap-y-1 px-3 py-4">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <div class="flex max-w-max">
                                            <div class="fi-ta-text-item inline-flex items-center gap-1.5 ">
                                                <span class="fi-ta-text-item-label text-sm text-gray-950 dark:text-white">
                                                    @if ($row->administered)
                                                    <x-heroicon-o-check-circle class="fi-ta-icon-item fi-ta-icon-item-size-lg h-6 w-6 fi-color-custom text-custom-500 dark:text-custom-400" style="--c-400:var(--success-400);--c-500:var(--success-500);" />
                                                    @else
                                                    <x-heroicon-o-x-circle class="fi-ta-icon-item fi-ta-icon-item-size-lg h-6 w-6 fi-color-custom text-custom-500 dark:text-custom-400" style="--c-400:var(--danger-400);--c-500:var(--danger-500);" />
                                                    @endif
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="fi-ta-cell p-0 first-of-type:ps-1 last-of-type:pe-1 sm:first-of-type:ps-3 sm:last-of-type:pe-3 fi-table-cell-frequency">
                            <div class="fi-ta-col-wrp">
                                <div class="fi-ta-text grid gap-y-1 px-3 py-4">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <div class="flex max-w-max">
                                            <div class="fi-ta-text-item inline-flex items-center gap-1.5 ">
                                                <span class="fi-ta-text-item-label text-sm text-gray-950 dark:text-white">
                                                    {{$row->dosage}}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td x-tooltip="{
            content: '{{ json_encode($row->observation)}}',
            theme: $store.theme,
        }" class="fi-ta-cell p-0 first-of-type:ps-1 last-of-type:pe-1 sm:first-of-type:ps-3 sm:last-of-type:pe-3 fi-table-cell-start-date">
                            <div class="fi-ta-col-wrp">
                                <div class="fi-ta-text grid gap-y-1 px-3 py-4">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <div class="flex max-w-max">
                                            <div class="fi-ta-text-item inline-flex items-center gap-1.5 ">
                                                <span class="fi-ta-text-item-label text-sm text-gray-950 dark:text-white">
                                                    {{ \Illuminate\Support\Str::limit($row->observation,25,'...') }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="fi-ta-cell p-0 first-of-type:ps-1 last-of-type:pe-1 sm:first-of-type:ps-3 sm:last-of-type:pe-3 fi-table-cell-end-date">
                            <div class="fi-ta-col-wrp">
                                <div class="fi-ta-text grid gap-y-1 px-3 py-4">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <div class="flex max-w-max">
                                            <div class="fi-ta-text-item inline-flex items-center gap-1.5 ">
                                                <span class="fi-ta-text-item-label text-sm text-gray-950 dark:text-white">
                                                    {{$row->person?->name}}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @else
            <div class="fi-fo-placeholder text-sm leading-6">
                <div role="alert" class="shout-component  
rounded-lg p-4 fi-color-custom bg-custom-50 text-custom-600 ring-custom-600/10 dark:bg-custom-400/10 dark:text-custom-400 dark:ring-custom-400/30" style="--c-50:var(--info-50);--c-400:var(--info-400);--c-600:var(--info-600);">
                    <div class="flex">
                        <div class="text-sm font-medium">
                            @lang('pet/prescriptionmedicines.schedule.empty')
                        </div>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>