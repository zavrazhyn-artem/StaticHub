<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateDiscordRequest;
use App\Http\Requests\UpdateLogsRequest;
use App\Http\Requests\UpdateScheduleRequest;
use App\Models\StaticGroup;
use App\Services\StaticGroup\StaticSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class StaticSettingsController extends Controller
{
    public function __construct(
        protected StaticSettingsService $service
    ) {}

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

        return view('statics.settings.logs', compact('static'));
    }

    public function updateLogs(UpdateLogsRequest $request, StaticGroup $static): RedirectResponse
    {
        Gate::authorize('canAccessSettings', $static);

        $this->service->executeUpdateLogsSettings($static, $request->validated());

        return redirect()->back()->with('success', __('Warcraft Logs settings updated!'));
    }

    public function updateSchedule(UpdateScheduleRequest $request, StaticGroup $static): RedirectResponse
    {
        Gate::authorize('canAccessSettings', $static);

        $this->service->executeUpdateScheduleSettings(
            $static,
            $request->validated(),
            $request->has('automation_settings.post_next_after_raid')
        );

        return redirect()->back()->with('success', __('Raid schedule updated and events generated!'));
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
