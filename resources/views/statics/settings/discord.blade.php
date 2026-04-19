<x-app-layout>
    <settings-discord
        static-name="{{ $static->name }}"
        :bot-guilds='@json($botGuilds ?? [])'
        :discord-channels='@json($discordChannels ?? [])'
        :discord-roles='@json($discordRoles ?? [])'
        discord-guild-id="{{ $discordGuildId ?? '' }}"
        discord-channel-id="{{ $static->discord_channel_id ?? '' }}"
        :discord-role-ids='@json($static->automation_settings["ping_role_ids"] ?? ($static->automation_settings["ping_role_id"] ?? null ? [$static->automation_settings["ping_role_id"]] : []))'
        notification-method="{{ $static->automation_settings['notification_method'] ?? 'webhook' }}"
        notification-channel-id="{{ $static->notification_channel_id ?? '' }}"
        webhook-url="{{ $static->discord_webhook_url ?? '' }}"
        :webhook-channel='@json($webhookChannel)'
        :webhook-muted='@json((bool)($static->automation_settings["webhook_muted"] ?? false))'
        update-url="{{ route('statics.settings.discord.update') }}"
        test-url="{{ route('statics.settings.discord.test') }}"
        test-channel-url="{{ route('statics.settings.discord.test-channel') }}"
        test-notification-channel-url="{{ route('statics.settings.discord.test-notification-channel') }}"
        delete-message-url="{{ route('statics.settings.discord.message.delete', ':messageId') }}"
        delete-channel-message-url="{{ route('statics.settings.discord.channel-message.delete', ':messageId') }}"
        delete-notification-channel-message-url="{{ route('statics.settings.discord.notification-channel-message.delete', ':messageId') }}"
        invite-url="{{ $discordInviteUrl }}"
        profile-tab-url="{{ route('statics.settings.profile') }}"
        schedule-tab-url="{{ route('statics.settings.schedule') }}"
        discord-tab-url="{{ route('statics.settings.discord') }}"
        logs-tab-url="{{ route('statics.settings.logs') }}"
        :can-manage="true"
    ></settings-discord>
</x-app-layout>
