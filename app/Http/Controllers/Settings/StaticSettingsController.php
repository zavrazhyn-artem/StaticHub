<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;

use App\Http\Requests\UpdateDiscordRequest;
use App\Http\Requests\UpdateLogsRequest;
use App\Http\Requests\UpdateScheduleRequest;
use App\Models\StaticGroup;
use App\Services\Auth\UserService;
use App\Services\StaticGroup\StaticSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class StaticSettingsController extends Controller
{
    public function __construct(
        protected StaticSettingsService $service,
        protected UserService $userService,
    ) {}

    public function profile(StaticGroup $static): View
    {
        $user = Auth::user();
        $payload = $this->userService->buildProfilePayload($user);
        $canManage = Gate::allows('canAccessSettings', $static);

        return view('statics.settings.profile', [
            'static'      => $static,
            'canManage'   => $canManage,
            'discord'     => [
                'connected' => (bool) $user->discord_id,
                'username'  => $user->discord_username,
            ],
            'statics'     => $user->statics->map(fn ($s) => [
                'id'      => $s->id,
                'name'    => $s->name,
                'isOwner' => $s->owner_id === $user->id,
            ])->values(),
            'transferData' => $payload['transferData'],
        ]);
    }

    public function schedule(StaticGroup $static): View
    {
        Gate::authorize('canAccessSettings', $static);

        return view('statics.settings.schedule', $this->service->buildScheduleSettingsPayload($static));
    }

    public function discord(StaticGroup $static): View
    {
        Gate::authorize('canAccessSettings', $static);

        return view('statics.settings.discord', $this->service->buildDiscordSettingsPayload($static));
    }

    public function logs(StaticGroup $static): View
    {
        Gate::authorize('canAccessSettings', $static);

        $payload = $this->service->buildLogsSettingsPayload($static);

        return view('statics.settings.logs', array_merge(
            ['static' => $static],
            $payload,
        ));
    }

    public function updateLogs(UpdateLogsRequest $request, StaticGroup $static): JsonResponse
    {
        Gate::authorize('canAccessSettings', $static);

        $this->service->executeUpdateLogsSettings($static, $request->validated());

        return response()->json(['success' => true]);
    }

    public function connectGuild(Request $request, StaticGroup $static): JsonResponse
    {
        Gate::authorize('canAccessSettings', $static);

        $request->validate(['wcl_url' => 'required|string|url']);

        $result = $this->service->connectWclGuild($static, $request->input('wcl_url'));

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function disconnectGuild(StaticGroup $static): JsonResponse
    {
        Gate::authorize('canAccessSettings', $static);

        $this->service->disconnectWclGuild($static);

        return response()->json(['success' => true]);
    }

    public function updateSchedule(UpdateScheduleRequest $request, StaticGroup $static): JsonResponse
    {
        Gate::authorize('canAccessSettings', $static);

        $this->service->executeUpdateScheduleSettings(
            $static,
            $request->validated(),
            $request->boolean('automation_settings.post_next_after_raid')
        );

        return response()->json(['success' => true]);
    }

    public function updateDiscord(UpdateDiscordRequest $request, StaticGroup $static): JsonResponse
    {
        Gate::authorize('canAccessSettings', $static);

        $webhookChannel = $this->service->executeUpdateDiscordSettings($static, $request->validated());

        return response()->json([
            'success'         => true,
            'webhook_channel' => $webhookChannel,
        ]);
    }

    public function testDiscordChannel(StaticGroup $static): JsonResponse
    {
        Gate::authorize('canAccessSettings', $static);

        $result = $this->service->executeChannelTest($static);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function deleteChannelMessage(StaticGroup $static, string $messageId): JsonResponse
    {
        Gate::authorize('canAccessSettings', $static);

        $success = $this->service->executeChannelMessageDelete($static, $messageId);

        return response()->json(['success' => $success]);
    }

    public function testDiscordWebhook(StaticGroup $static): JsonResponse
    {
        Gate::authorize('canAccessSettings', $static);

        $result = $this->service->executeWebhookTest($static);

        return response()->json($result);
    }

    public function deleteWebhookMessage(StaticGroup $static, string $messageId): JsonResponse
    {
        Gate::authorize('canAccessSettings', $static);

        $success = $this->service->executeWebhookMessageDelete($static, $messageId);

        return response()->json(['success' => $success]);
    }
}
