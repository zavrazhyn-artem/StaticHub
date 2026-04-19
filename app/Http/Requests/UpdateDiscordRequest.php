<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDiscordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'discord_guild_id'    => 'sometimes|nullable|string|max:20',
            'discord_channel_id'  => 'sometimes|nullable|string|max:20',
            'notification_channel_id' => 'sometimes|nullable|string|max:20',
            'discord_webhook_url' => 'sometimes|nullable|url|max:500',
            'automation_settings'                      => 'sometimes|array',
            'automation_settings.ping_role_ids'        => 'sometimes|nullable|array',
            'automation_settings.ping_role_ids.*'      => 'string|max:20',
            'automation_settings.webhook_muted'       => 'sometimes|nullable|boolean',
            'automation_settings.notification_method'  => 'sometimes|nullable|string|in:webhook,channel',
        ];
    }
}
