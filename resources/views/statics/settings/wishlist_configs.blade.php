<x-app-layout>
    <wishlist-configs-settings
        static-name="{{ $static->name }}"
        :configs='@json($configs)'
        :fight-styles='@json($fight_styles)'
        :ops='@json($ops)'
        :upgrade-levels='@json($upgrade_levels)'
        store-url="{{ route('statics.wishlist-configs.store') }}"
        update-url-template="{{ route('statics.wishlist-configs.update', ['static' => $static->id, 'config' => '__ID__']) }}"
        destroy-url-template="{{ route('statics.wishlist-configs.destroy', ['static' => $static->id, 'config' => '__ID__']) }}"
        profile-tab-url="{{ route('statics.settings.profile') }}"
        schedule-tab-url="{{ route('statics.settings.schedule') }}"
        discord-tab-url="{{ route('statics.settings.discord') }}"
        logs-tab-url="{{ route('statics.settings.logs') }}"
        wishlist-configs-tab-url="{{ route('statics.settings.wishlist-configs') }}"
        :can-manage="true"
        csrf-token="{{ csrf_token() }}"
    ></wishlist-configs-settings>
</x-app-layout>
