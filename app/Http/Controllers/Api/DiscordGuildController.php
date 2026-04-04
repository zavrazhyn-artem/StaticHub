<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Discord\DiscordMessageService;
use Illuminate\Http\JsonResponse;

class DiscordGuildController extends Controller
{
    public function __construct(
        private readonly DiscordMessageService $discordMessageService
    ) {}

    public function channels(string $guildId): JsonResponse
    {
        $channels = $this->discordMessageService->getGuildChannels($guildId);

        return response()->json(array_values($channels));
    }

    public function roles(string $guildId): JsonResponse
    {
        $roles = $this->discordMessageService->getGuildRoles($guildId);

        return response()->json(array_values($roles));
    }
}
