    <div id="qr-code-view">
        <div class="flex justify-between">
        </div>
        <div class="text-center mx-auto">
            @if ($text_save)
            <x-tabler-qrcode class="w-32 h-32 mx-auto" style="opacity: 0.5" />
            <span>{{$text_save}}</span>
            @else
            @if ($qrcode)
            <div class="flex justify-center">
            {{ QrCode::size(150)->generate(env('APP_URL') . '/' . $qrcode) }}
            </div>
            <span class="ml-8"><a class="filepond--label-action font-bold" href="{{ ENV('APP_URL') . '/' . $qrcode }}">Url</a></span>
            @endif
            @if ($text_empty)
            <x-tabler-qrcode-off class="w-32 h-32 mx-auto" style="opacity: 0.3" />
            <span>{{$text_empty}}</span>
            @endif
            @endif
        </div>
    </div>