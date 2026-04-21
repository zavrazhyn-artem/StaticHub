<?php

declare(strict_types=1);

namespace App\Services\Ghost;

use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Auth;

class GhostModeService
{
    private const SESSION_KEY = 'ghost_static_id';

    public function __construct(private readonly Session $session) {}

    /**
     * Whether the currently authenticated user is allowed to use ghost mode.
     * Requires both the admin access-key session flag AND the user's id to
     * match the configured GHOST_USER_ID.
     */
    public function canActivate(): bool
    {
        $ghostUserId = config('ghost.user_id');

        if ($ghostUserId === null || $ghostUserId === '') {
            return false;
        }

        if (! Auth::check()) {
            return false;
        }

        if ($this->session->get('admin_authenticated') !== true) {
            return false;
        }

        return (int) Auth::id() === (int) $ghostUserId;
    }

    /**
     * Whether ghost mode is currently active for this request.
     */
    public function isActive(): bool
    {
        return $this->canActivate() && $this->currentStaticId() !== null;
    }

    public function currentStaticId(): ?int
    {
        $id = $this->session->get(self::SESSION_KEY);

        return $id === null ? null : (int) $id;
    }

    public function enter(int $staticId): void
    {
        $this->session->put(self::SESSION_KEY, $staticId);
    }

    public function exit(): void
    {
        $this->session->forget(self::SESSION_KEY);
    }
}
