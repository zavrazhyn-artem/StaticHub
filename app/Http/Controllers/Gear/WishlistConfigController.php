<?php

declare(strict_types=1);

namespace App\Http\Controllers\Gear;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wishlist\SaveDroptimizerConfigRequest;
use App\Models\StaticGroup;
use App\Models\WishlistDroptimizerConfig;
use App\Services\Wishlist\WishlistConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * CRUD for the per-static "Allowed Droptimizer configurations" tab in
 * settings. Authorization: leader OR officer (canAccessSettings gate)
 * — same gate the rest of the static settings tabs use, so a regular
 * member can't see or modify the configs.
 */
class WishlistConfigController extends Controller
{
    public function __construct(
        private readonly WishlistConfigService $service,
    ) {}

    public function index(StaticGroup $static): JsonResponse
    {
        Gate::authorize('canAccessSettings', $static);
        return response()->json([
            'configs'        => $this->service->listForStatic($static->id),
            'fight_styles'   => WishlistDroptimizerConfig::FIGHT_STYLES,
            'ops'            => WishlistDroptimizerConfig::OPS,
            'upgrade_levels' => WishlistDroptimizerConfig::upgradeLevelOptions(),
        ]);
    }

    public function store(StaticGroup $static, SaveDroptimizerConfigRequest $request): RedirectResponse
    {
        Gate::authorize('canAccessSettings', $static);
        $this->service->create($static->id, $request->validated());
        return back()->with('success', 'Droptimizer config created.');
    }

    public function update(
        StaticGroup $static,
        WishlistDroptimizerConfig $config,
        SaveDroptimizerConfigRequest $request,
    ): RedirectResponse {
        Gate::authorize('canAccessSettings', $static);
        abort_unless($config->static_id === $static->id, 404);
        $this->service->update($config, $request->validated());
        return back()->with('success', 'Droptimizer config updated.');
    }

    public function destroy(StaticGroup $static, WishlistDroptimizerConfig $config): RedirectResponse
    {
        Gate::authorize('canAccessSettings', $static);
        abort_unless($config->static_id === $static->id, 404);
        $this->service->delete($config);
        return back()->with('success', 'Droptimizer config deleted.');
    }
}
