<x-filament::page :widget-data="['record' => $record]" :class="\Illuminate\Support\Arr::toCssClasses([
        'filament-resources-view-record-page',
        'filament-resources-' . str_replace('/', '-', $this->getResource()::getSlug()),
        'filament-resources-record-' . $record->getKey(),
    ])">
    {{-- @php
    $relationManagers = $this->getRelationManagers();
    @endphp

    @if ((! $this->hasCombinedRelationManagerTabsWithForm()) || (! count($relationManagers)))
    {{ $this->form }}
    @endif

    @if (count($relationManagers))
    @if (! $this->hasCombinedRelationManagerTabsWithForm())
    <x-filament::hr />
    @endif

    <x-filament::resources.relation-managers :active-manager="$activeRelationManager" :form-tab-label="$this->getFormTabLabel()" :managers="$relationManagers" :owner-record="$record" :page-class="static::class">
        @if ($this->hasCombinedRelationManagerTabsWithForm())
        <x-slot name="form">
            {{ $this->form }}
        </x-slot>
        @endif
    </x-filament::resources.relation-managers>
    @endif --}}
    <div class="container">
        <div class="grid gap-4 grid-cols-1 md:grid-cols-4">
            <div class="p-4 bg-white rounded-xl col-span-1 dark:bg-gray-800">
                @if (count($this->record->getMedia('pets-main-image'))>0)
                <img class="rounded-full border border-solid " src="{{$this->record->getMedia('pets-main-image')[0]->getUrl()}}" alt="Main Image">
                @else 
                <div class="rounded-full border border-solid border-8 border-black">
                    <x-tabler-paw class="w-full h-full text-sm" />
                </div>
                @endif
                <div class="flex items-center justify-center mt-2"> 
                    <h1 class="text-xl">{{$this->data['name']}}</h1>
                    <div class="inline-flex items-center ml-2 px-2 py-0.5 text-sm  rounded-xl whitespace-nowrap text-gray-700 bg-gray-500/10 dark:text-gray-300 dark:bg-gray-500/20">
                    @switch($this->data['gender'])
                        @case('male')
                            {{ucfirst($this->data['gender'])}}
                            <x-tabler-gender-male class="w-4 h-4" />
                            @break
                        @case('female')
                            {{ucfirst($this->data['gender'])}}
                            <x-tabler-gender-female class="w-4 h-4" />
                            @break
                        @default
                            Undefined
                            <x-tabler-question-mark class="w-4 h-4" />
                            @break
                    @endswitch
                    </div>
                </div>
                <div class="text-center text-gray-600 dark:text-gray-400 mt-0.5">{{$this->record->getConfigSpecie()}}<span class="text-grey-light px-1">&bullet;</span>{{$this->data['chip']}}</div>
                <div class="border border-grey-light mt-2 mb-2 mr-1 ml-1"></div>
            </div>
            <div class="p-4 bg-white rounded-xl dark:bg-gray-800 col-span-1 md:col-span-3">
               
            </div>
        </div>
    </div>
</x-filament::page>