<?php

namespace App\Console\Commands;

use App\Jobs\SyncStaticGroupJob;
use App\Mappers\BlizzardDataMapper;
use App\Models\StaticGroup;
use App\Services\Analysis\RaiderIoService;
use App\Services\BlizzardApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestCommand extends Command
{
    protected $signature = 'app:test';

    public function __construct(private BlizzardApiService $blizzardApiService,
    private RaiderIoService $raiderIoService,
    ){
        parent::__construct();
    }

    public function handle()
    {
        $data = $this->raiderIoService->getCharacterProfile('eu', 'tarren-mill', 'Zavrikk');
        dd($data);
    }
}
