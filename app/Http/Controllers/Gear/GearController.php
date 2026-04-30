<?php

declare(strict_types=1);

namespace App\Http\Controllers\Gear;

use App\Http\Controllers\Controller;
use App\Models\StaticGroup;
use App\Services\Gear\GearViewService;
use App\Services\Wishlist\WishlistService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class GearController extends Controller
{
    public function __construct(
        private readonly WishlistService $wishlists,
        private readonly GearViewService $gearView,
    ) {}

    public function index(StaticGroup $static): View
    {
        $userId = (int) Auth::id();

        return view('gear.index', [
            'static'            => $static,
            'wishlistPayload'   => $this->wishlists->buildGearViewPayload($static->id, $userId),
            'gearContext'       => $this->gearView->buildContextPayload($static->id, $userId),
            'enchantableSlots'  => $this->gearView->enchantableSlots(),
        ]);
    }
}
