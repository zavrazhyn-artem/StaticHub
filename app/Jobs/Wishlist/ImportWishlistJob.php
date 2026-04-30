<?php

declare(strict_types=1);

namespace App\Jobs\Wishlist;

use App\Models\User;
use App\Services\Wishlist\WishlistService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Imports a wishlist from a Raidbots/QE Live URL on behalf of a user.
 *
 * Phase 0 callers dispatchSync() so the UI gets immediate feedback. The job
 * is queueable for batch flows (auto-resync after equipment change, etc.).
 */
class ImportWishlistJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $backoff = 30;
    public int $timeout = 60;

    public function __construct(
        public readonly int $userId,
        public readonly string $url,
    ) {}

    public function handle(WishlistService $service): void
    {
        $user = User::query()->findOrFail($this->userId);
        $service->importFromUrl($user, $this->url);
    }
}
