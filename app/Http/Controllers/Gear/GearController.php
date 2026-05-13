<?php

declare(strict_types=1);

namespace App\Http\Controllers\Gear;

use App\Enums\StaticGroup\Role;
use App\Http\Controllers\Controller;
use App\Models\StaticGroup;
use App\Services\Gear\GearViewService;
use App\Services\Wishlist\WishlistConfigService;
use App\Services\Wishlist\WishlistService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class GearController extends Controller
{
    public function __construct(
        private readonly WishlistService $wishlists,
        private readonly GearViewService $gearView,
        private readonly WishlistConfigService $wishlistConfigs,
    ) {}

    public function index(StaticGroup $static, ?string $tab = null): View
    {
        $userId = (int) Auth::id();
        // Whitelist enforced at the route layer; default to the gear tab.
        $initialTab = in_array($tab, ['wishlist', 'loot-history'], true) ? $tab : 'gear';

        // Static owner is always Leader regardless of pivot value; otherwise
        // fall back to the membership's access_role. No membership = Member
        // (the controller already requires auth; a non-member shouldn't
        // reach this view, but we hard-default to least-privileged anyway).
        $accessRole = $this->resolveAccessRole($static, $userId);

        return view('gear.index', [
            'static'            => $static,
            'wishlistPayload'   => $this->wishlists->buildGearViewPayload($static->id, $userId, $accessRole),
            'gearContext'       => $this->gearView->buildContextPayload($static->id, $userId),
            'enchantableSlots'  => $this->gearView->enchantableSlots(),
            'initialTab'        => $initialTab,
            'allowPlayerPicker' => $accessRole->isManager(),
            // Allowed Droptimizer configs surfaced for: the matched-config
            // chips on imported wishlists, and the "Run on Raidbots"
            // chooser modal that lists every config as a checklist the
            // player copies into the Raidbots form.
            'wishlistConfigs' => $this->wishlistConfigs->listForStatic($static->id)
                ->map(fn (\App\Models\WishlistDroptimizerConfig $c) => [
                    'id'                       => $c->id,
                    'is_default'               => (bool) $c->is_default,
                    'display_name'             => $c->display_name,
                    'fight_style'              => $c->fight_style,
                    'num_bosses_op'            => $c->num_bosses_op,
                    'num_bosses'               => $c->num_bosses,
                    'fight_length_op'          => $c->fight_length_op,
                    'fight_length_minutes'     => $c->fight_length_minutes,
                    'weight'                   => (float) $c->weight,
                    'upgrade_level_mythic'     => $c->upgrade_level_mythic,
                    'upgrade_level_heroic'     => $c->upgrade_level_heroic,
                    'upgrade_level_normal'     => $c->upgrade_level_normal,
                    'upgrade_level_lfr'        => $c->upgrade_level_lfr,
                    'require_vault_socket'     => (bool) $c->require_vault_socket,
                    'require_pi'               => (bool) $c->require_pi,
                    'voidforged'               => (bool) $c->voidforged,
                    'allow_expert'             => (bool) $c->allow_expert,
                    'require_upgrade_all_same' => (bool) $c->require_upgrade_all_same,
                ])->values(),
        ]);
    }

    private function resolveAccessRole(StaticGroup $static, int $userId): Role
    {
        if ((int) $static->owner_id === $userId) {
            return Role::Leader;
        }
        $pivotRole = (string) ($static->users()
            ->where('users.id', $userId)
            ->first()
            ?->pivot
            ?->access_role ?? 'member');
        return Role::tryFrom($pivotRole) ?? Role::Member;
    }
}
