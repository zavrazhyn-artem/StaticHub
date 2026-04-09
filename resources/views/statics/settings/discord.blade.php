<x-app-layout>
    <settings-discord
        static-name="{{ $static->name }}"
        :bot-guilds='@json($botGuilds ?? [])'
        :discord-channels='@json($discordChannels ?? [])'
        :discord-roles='@json($discordRoles ?? [])'
        discord-guild-id="{{ $discordGuildId ?? '' }}"
        discord-channel-id="{{ $static->discord_channel_id ?? '' }}"
        discord-role-id="{{ $static->automation_settings['ping_role_id'] ?? '' }}"
        webhook-url="{{ $static->discord_webhook_url ?? '' }}"
        :webhook-channel='@json($webhookChannel)'
        :webhook-muted='@json((bool)($static->automation_settings["webhook_muted"] ?? false))'
        update-url="{{ route('statics.settings.discord.update', $static) }}"
        test-url="{{ route('statics.settings.discord.test', $static) }}"
        test-channel-url="{{ route('statics.settings.discord.test-channel', $static) }}"
        delete-message-url="{{ route('statics.settings.discord.message.delete', [$static, ':messageId']) }}"
        delete-channel-message-url="{{ route('statics.settings.discord.channel-message.delete', [$static, ':messageId']) }}"
        invite-url="{{ $discordInviteUrl }}"
        profile-tab-url="{{ route('statics.settings.profile', $static) }}"
        schedule-tab-url="{{ route('statics.settings.schedule', $static) }}"
        discord-tab-url="{{ route('statics.settings.discord', $static) }}"
        logs-tab-url="{{ route('statics.settings.logs', $static) }}"
        :can-manage="true"
    ></settings-discord>
</x-app-layout>
