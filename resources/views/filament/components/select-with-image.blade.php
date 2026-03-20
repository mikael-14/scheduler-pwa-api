<div class="flex rounded-sm relative">
    <div class="flex">
        @if ($image !== false)
        <div>
            <div class="h-8 w-8">
                @if ($image)
                <img src="{{ $image }}" alt="{{ $label }}" role="img" class="h-full w-full rounded-full overflow-hidden shadow object-cover" />
                @else
                <x-tabler-paw class="w-full h-full rounded-full overflow-hidden shadow object-cover" />
                @endif
            </div>
        </div>
        @endif
        <div class="flex flex-col justify-center  @if ($image !== false) pl-3 @endif"> 
            <p class="text-sm font-bold pb-1">{{ $label }}</p>
            <div class="flex flex-col items-start">
                <p class="text-xs leading-5">{{ $description }}</p>
            </div>
        </div>
    </div>
</div>