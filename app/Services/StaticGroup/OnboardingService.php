<?php

declare(strict_types=1);

namespace App\Services\StaticGroup;

use App\Models\Character;
use App\Models\Specialization;
use App\Models\StaticGroup;
use App\Models\User;
use App\Services\Character\CharacterSyncService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OnboardingService
{
    public function __construct(
        private readonly JoinStaticService    $joinStaticService,
        private readonly RosterService        $rosterService,
        private readonly CharacterSyncService $characterSyncService,
    ) {}

    /**
     * Build the onboarding payload for the stepper page.
     */
    public function buildOnboardingPayload(User $user): array
    {
        $pendingJoinToken = session('pending_join_token');
        $pendingJoinStatic = null;

        if ($pendingJoinToken) {
            $pendingJoinStatic = $this->resolveTokenPreview($pendingJoinToken);
        }

        return [
            'onboarding' => true,
            'pendingJoinToken' => $pendingJoinToken,
            'pendingJoinStatic' => $pendingJoinStatic,
            'isGuildMaster' => $user->getGuildMasterCharacter() !== null,
            'guildName' => $user->getGuildMasterCharacter()?->guild_name ?? null,
            'csrfToken' => csrf_token(),
        ];
    }

    /**
     * Execute the creation of a new static group.
     */
    public function executeStaticCreation(array $data, int $ownerId): StaticGroup
    {
        $static = StaticGroup::create([
            'name' => $data['name'],
            'region' => $data['region'],
            'owner_id' => $ownerId,
            'invite_token' => Str::random(12),
            'slug' => Str::slug($data['name']),
        ]);

        $static->assignOwner($ownerId);

        return $static;
    }

    /**
     * Validate an invite token and return static info preview.
     */
    public function validateInviteToken(string $rawInput): ?array
    {
        $token = $this->extractTokenFromInput($rawInput);

        return $this->resolveTokenPreview($token);
    }

    /**
     * Execute join flow and return static + character data.
     */
    public function executeJoin(string $rawInput, User $user): array
    {
        $token = $this->extractTokenFromInput($rawInput);

        $static = $this->joinStaticService->executeJoin($token, $user->id);

        // Clear pending token from session
        session()->forget('pending_join_token');

        // Auto-sync characters if Battle.net token available
        $this->attemptCharacterSync($user);

        return [
            'static' => $static,
            'characterData' => $this->buildCharacterStepPayload($user, $static),
        ];
    }

    /**
     * Build character step payload for a given user and static.
     */
    public function buildCharacterStepPayload(User $user, StaticGroup $static): array
    {
        // Auto-sync characters if Battle.net token available
        $this->attemptCharacterSync($user);

        $characters = Character::query()
            ->belongingTo($user->id)
            ->atMaxLevel()
            ->with('realm')
            ->defaultOrder()
            ->get()
            ->map(fn (Character $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'playable_class' => $c->playable_class,
                'equipped_item_level' => $c->equipped_item_level,
                'avatar_url' => $c->avatar_url,
                'realm_name' => $c->realm?->name ?? $c->realm_slug,
                'active_spec' => $c->active_spec,
                'level' => $c->level,
            ]);

        $specializations = Specialization::orderBy('class_name')->orderBy('name')->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'class_name' => $s->class_name,
                'role' => $s->role,
                'icon_url' => $s->icon_url,
            ]);

        return [
            'characters' => $characters,
            'specializations' => $specializations,
            'staticId' => $static->id,
        ];
    }

    /**
     * Sync characters and return payload for the character step.
     */
    public function syncAndBuildCharacterPayload(User $user): array
    {
        $this->attemptCharacterSync($user);

        $static = $user->statics()->first();
        if (!$static) {
            return ['characters' => [], 'specializations' => [], 'staticId' => null];
        }

        return $this->buildCharacterStepPayload($user, $static);
    }

    /**
     * Save character participation (main + alts).
     */
    public function saveParticipation(int $userId, int $staticId, int $mainCharId, array $raidingCharIds): void
    {
        $user = User::findOrFail($userId);
        $static = StaticGroup::findOrFail($staticId);

        // Ensure main is in raiding list
        if (!in_array($mainCharId, $raidingCharIds)) {
            $raidingCharIds[] = $mainCharId;
        }

        $this->rosterService->updateUserParticipation($user, $static, $mainCharId, $raidingCharIds);
    }

    /**
     * Extract token from a full URL or raw token string.
     */
    private function extractTokenFromInput(string $input): string
    {
        $input = trim($input);

        // If it looks like a URL, extract the token from the path
        if (str_contains($input, '/join/')) {
            $parts = explode('/join/', $input);
            return trim(end($parts), '/ ');
        }

        return $input;
    }

    /**
     * Resolve a token to static group preview data.
     */
    private function resolveTokenPreview(string $token): ?array
    {
        try {
            $static = StaticGroup::query()->findByInviteToken($token);

            return [
                'name' => $static->name,
                'region' => $static->region,
                'memberCount' => $static->members()->count(),
                'token' => $token,
            ];
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Attempt to sync characters from Battle.net if token is available.
     */
    private function attemptCharacterSync(User $user): void
    {
        $bnetToken = session('battlenet_token');
        if ($bnetToken) {
            try {
                $this->characterSyncService->syncUserCharacters($bnetToken, $user->id);
            } catch (\Exception $e) {
                Log::warning('Onboarding character sync failed', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
