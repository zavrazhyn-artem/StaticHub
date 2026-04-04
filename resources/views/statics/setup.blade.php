<x-app-layout>
    <static-setup
        :realms='@json($realms->map(fn($r) => ["slug" => $r->slug, "name" => $r->name]))'
        :guilds='@json($guilds ?? [])'
        store-url="{{ route('statics.store') }}"
        import-url="{{ route('statics.import') }}"
    ></static-setup>
</x-app-layout>
