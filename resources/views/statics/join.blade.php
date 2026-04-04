<x-app-layout>
    <join-static
        static-name="{{ $static->name }}"
        region="{{ $static->region }}"
        server="{{ $static->server }}"
        join-url="{{ route('statics.join.process', $static->invite_token) }}"
    ></join-static>
</x-app-layout>
