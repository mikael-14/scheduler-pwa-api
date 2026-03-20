@php
    $calculate = $getRecord()->calculateVariation();
    $config_variation = \App\Enums\PetMeasure::getVariation($getRecord()->type);
    $config_unit = \App\Enums\PetMeasure::getUnit($getRecord()->type);
@endphp
<div class="flex flex-nowrap">
   {{ $getState() }} {{ $config_unit }}
    @if ($config_variation)
        @if ($calculate < 0 && abs($calculate) > $config_variation)
            <div class="flex flex-nowrap items-center text-danger-600 ml-2">( {{ $calculate }} )<x-tabler-arrow-down class="w-4 h-4" /></span>
        @elseif ($calculate > 0 && abs($calculate) > $config_variation)
            <div class="flex flex-nowrap items-center text-success-600 ml-2">( {{ $calculate }} )<x-tabler-arrow-up class="w-4 h-4" /></span>
        @else 
            <div class="flex flex-nowrap items-center ml-2">( {{ $calculate }} )<x-tabler-arrows-left-right class="w-4 h-4" /></div>
        @endif
    @endif
</div>
	