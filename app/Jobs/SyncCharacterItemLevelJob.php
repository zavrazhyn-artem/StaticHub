<?php

namespace App\Jobs;

use App\Models\Character;
use App\Services\BlizzardApiService;
use App\Tasks\StaticGroup\AssignCharacterRoleTask;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncCharacterItemLevelJob implements ShouldQueue
{
    use Queueable;

    protected Character $character;

    /**
     * Create a new job instance.
     */
    public function __construct(Character $character)
    {
        $this->character = $character;
    }

    /**
     * Execute the job.
     */
    public function handle(BlizzardApiService $blizzardApiService, AssignCharacterRoleTask $assignTask): void
    {
        $profileData = $blizzardApiService->getCharacterProfileSummary(
            $this->character->realm->slug,
            $this->character->name
        );

        if ($profileData !== null) {
            $this->character->update([
                'equipped_item_level' => $profileData['equipped_item_level'],
                'active_spec' => is_array($profileData['active_spec'])
                    ? ($profileData['active_spec']['name'] ?? null)
                    : $profileData['active_spec'],
            ]);

            // After active_spec is saved, auto-set main spec for every static
            // this character belongs to (skipped if spec is already configured).
            $this->character->refresh();
            $staticIds = $this->character->statics()->pluck('statics.id');
            foreach ($staticIds as $staticId) {
                $assignTask->autoSetMainSpecIfMissing($this->character, (int) $staticId);
            }
        }
    }
}
