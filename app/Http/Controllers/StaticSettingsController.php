<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateLogsRequest;
use App\Http\Requests\UpdateScheduleRequest;
use App\Models\StaticGroup;
use App\Services\StaticGroup\StaticSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class StaticSettingsController extends Controller
{
    public function __construct(
        protected StaticSettingsService $service
    ) {}

    public function schedule(StaticGroup $static): View
    {
        return view('statics.settings.schedule', $this->service->buildScheduleSettingsPayload($static));
    }

    public function logs(StaticGroup $static): View
    {
        return view('statics.settings.logs', compact('static'));
    }

    public function updateLogs(UpdateLogsRequest $request, StaticGroup $static): RedirectResponse
    {
        $this->service->executeUpdateLogsSettings($static, $request->validated());

        return redirect()->back()->with('success', __('Warcraft Logs settings updated!'));
    }

    public function updateSchedule(UpdateScheduleRequest $request, StaticGroup $static): RedirectResponse
    {
        $this->service->executeUpdateScheduleSettings(
            $static,
            $request->validated(),
            $request->has('automation_settings.post_next_after_raid')
        );

        return redirect()->back()->with('success', __('Raid schedule updated and events generated!'));
    }

    public function testDiscordWebhook(StaticGroup $static): JsonResponse
    {
        $success = $this->service->executeWebhookTest($static);

        return response()->json(['success' => $success]);
    }
}
