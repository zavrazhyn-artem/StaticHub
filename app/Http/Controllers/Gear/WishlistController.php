<?php

declare(strict_types=1);

namespace App\Http\Controllers\Gear;

use App\Exceptions\WishlistImportException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gear\ImportWishlistRequest;
use App\Jobs\Wishlist\ImportWishlistJob;
use App\Models\StaticGroup;
use App\Models\Wishlist;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class WishlistController extends Controller
{
    public function store(StaticGroup $static, ImportWishlistRequest $request): RedirectResponse
    {
        $url    = (string) $request->validated('url');
        $userId = (int) Auth::id();

        try {
            ImportWishlistJob::dispatchSync($userId, $url);
        } catch (WishlistImportException $e) {
            return back()->withErrors(['url' => $e->getMessage()])->withInput();
        }

        return back()->with('success', 'Wishlist imported.');
    }

    public function destroy(StaticGroup $static, Wishlist $wishlist): RedirectResponse
    {
        if ($wishlist->character->user_id !== Auth::id()) {
            abort(403, 'You can only delete wishlists for your own characters.');
        }

        $wishlist->delete();

        return back()->with('success', 'Wishlist deleted.');
    }
}
