<?php

namespace App\Console\Commands;

use App\Mappers\BlizzardDataMapper;
use App\Services\Analysis\RaiderIoService;
use App\Services\BlizzardApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestCommand extends Command
{
    protected $signature = 'app:test';

    public function __construct(private readonly BlizzardApiService $apiService){
        parent::__construct();
    }

    public function handle()
    {

        $data = $this->apiService->getCharacterRaidEncounters('tarren-mill', 'Zavrikk');
        dd($data['character']);
    }
}
