<?php

namespace App\Http\Controllers;

use App\Models\StaticGroup;
use App\Services\RaidScheduleService;
use App\Services\DiscordMessageService;
use App\Services\DiscordService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StaticSettingsController extends Controller
{
    protected RaidScheduleService $raidScheduleService;
    protected DiscordMessageService $discordMessageService;
    protected DiscordService $discordService;

    public function __construct(
        RaidScheduleService $raidScheduleService,
        DiscordMessageService $discordMessageService,
        DiscordService $discordService
    ) {
        $this->raidScheduleService = $raidScheduleService;
        $this->discordMessageService = $discordMessageService;
        $this->discordService = $discordService;
    }

    public function schedule(StaticGroup $static)
    {
        // Check if user is owner/authorized
        if ($static->owner_id !== Auth::id()) {
            abort(403);
        }

        $timezones = timezone_identifiers_list();

        $botGuilds = $this->discordMessageService->getGuildsTheBotIsIn();
        $discordGuildId = $static->discord_guild_id;

        // Auto-discovery: If bot is in exactly one guild and guild_id is not set, set it automatically for the view logic
        if (empty($discordGuildId) && count($botGuilds) === 1) {
            $discordGuildId = $botGuilds[0]['id'];
        }

        $discordChannels = [];
        $discordRoles = [];

        if ($discordGuildId) {
            $discordChannels = $this->discordMessageService->getGuildChannels($discordGuildId);
            $discordRoles = $this->discordMessageService->getGuildRoles($discordGuildId);
        }

        return view('statics.settings.schedule', compact('static', 'timezones', 'discordChannels', 'discordRoles', 'botGuilds', 'discordGuildId'));
    }

    public function logs(StaticGroup $static)
    {
        if ($static->owner_id !== Auth::id()) {
            abort(403);
        }

        return view('statics.settings.logs', compact('static'));
    }

    public function updateLogs(Request $request, StaticGroup $static)
    {
        if ($static->owner_id !== Auth::id()) {
            abort(403);
        }

        $validated = $request->validate([
            'wcl_guild_id' => 'nullable|string|max:50',
            'wcl_region' => 'nullable|string|max:10',
            'wcl_realm' => 'nullable|string|max:100',
        ]);

        $static->update($validated);

        return redirect()->back()->with('success', 'Warcraft Logs settings updated!');
    }

    public function updateSchedule(Request $request, StaticGroup $static)
    {
        if ($static->owner_id !== Auth::id()) {
            abort(403);
        }

        $validated = $request->validate([
            'raid_days' => 'required|array',
            'raid_days.*' => 'in:mon,tue,wed,thu,fri,sat,sun',
            'raid_start_time' => 'nullable|date_format:H:i',
            'raid_end_time' => 'nullable|date_format:H:i',
            'timezone' => 'required|timezone',
            'discord_channel_id' => 'nullable|string|max:20',
            'discord_guild_id' => 'nullable|string|max:20',
            'automation_settings' => 'nullable|array',
            'automation_settings.post_next_after_raid' => 'nullable|boolean',
            'automation_settings.reminder_hours_before' => 'nullable|integer|min:1|max:72',
            'automation_settings.ping_role_id' => 'nullable|string|max:20',
            'guild_tax_per_player' => 'nullable|integer|min:0',
        ]);

        // Fix boolean from checkbox
        if (isset($validated['automation_settings'])) {
            $validated['automation_settings']['post_next_after_raid'] = $request->has('automation_settings.post_next_after_raid');
        }

        $static->update($validated);

        $this->raidScheduleService->generateUpcomingEvents($static);

        return redirect()->back()->with('success', 'Raid schedule updated and events generated!');
    }

    public function testDiscordWebhook(StaticGroup $static)
    {
        if ($static->owner_id !== Auth::id()) {
            abort(403);
        }

        $success = $this->discordService->sendTestMessage();

        return response()->json(['success' => $success]);
    }
}
