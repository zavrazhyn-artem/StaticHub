<?php

namespace App\Jobs;

use App\Models\Character;
use App\Services\Analysis\WclService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncWclDataJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(protected Character $character)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(WclService $wclService): void
    {
        Log::info("Syncing WCL data for character: {$this->character->name}");

        $region = $this->character->realm->region;
        $realm = $this->character->realm->slug;
        $name = $this->character->name;

        $parses = $wclService->getCharacterParses($region, $realm, $name);

        if ($parses) {
            $this->character->update([
                'raw_wcl_data' => $parses,
            ]);
        } else {
            Log::warning("Failed to fetch WCL data for {$this->character->name}");
        }
    }
}
