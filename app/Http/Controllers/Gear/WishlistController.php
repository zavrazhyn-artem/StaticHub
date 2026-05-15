<?php

declare(strict_types=1);

namespace App\Http\Controllers\Gear;

use App\Exceptions\WishlistImportException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gear\ImportWishlistRequest;
use App\Jobs\Wishlist\ImportWishlistJob;
use App\Models\StaticGroup;
use App\Models\Wishlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class WishlistController extends Controller
{
    /**
     * AJAX-aware: fetch() POSTs with `Accept: application/json` and gets
     * back JSON 422 with the full multi-line WishlistImportException
     * message so the frontend can render it inline in the import modal.
     * Classic form posts still redirect-back with a flash message.
     */
    public function store(StaticGroup $static, ImportWishlistRequest $request): RedirectResponse|JsonResponse
    {
        $url         = (string) $request->validated('url');
        $characterId = (int) $request->validated('character_id');
        $userId      = (int) Auth::id();

        try {
            ImportWishlistJob::dispatchSync($userId, $url, $characterId);
        } catch (WishlistImportException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
            return back()->withErrors(['url' => $e->getMessage()])->withInput();
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Wishlist imported.']);
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
