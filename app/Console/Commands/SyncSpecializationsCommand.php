<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Specialization;
use App\Services\BlizzardApiService;
use Illuminate\Console\Command;

class SyncSpecializationsCommand extends Command
{
    protected $signature = 'wow:sync-specializations';

    protected $description = 'Тягне назву, клас та іконку кожного спека з Blizzard Game Data API і зберігає в таблицю specializations. Роль береться з config/wow_season.php. Запускати вручну при появі нового класу/спека.';

    public function __construct(private readonly BlizzardApiService $blizzardApi)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $specRoles = config('wow_season.specializations', []);

        if (empty($specRoles)) {
            $this->error('Список спеків порожній у config/wow_season.specializations');
            return self::FAILURE;
        }

        $this->info('Синхронізація ' . count($specRoles) . ' спеків...');
        $bar = $this->output->createProgressBar(count($specRoles));
        $bar->start();

        $synced  = 0;
        $failed  = 0;

        foreach ($specRoles as $specId => $role) {
            $specData = $this->blizzardApi->getPlayableSpecialization($specId);

            if (!$specData) {
                $this->newLine();
                $this->warn("  Не вдалось отримати дані для spec_id={$specId}");
                $failed++;
                $bar->advance();
                continue;
            }

            $iconUrl = $this->blizzardApi->getPlayableSpecializationIcon($specId) ?? '';

            Specialization::updateOrCreate(
                ['id' => $specId],
                [
                    'name'       => $specData['name'] ?? 'Unknown',
                    'class_name' => $specData['playable_class']['name'] ?? 'Unknown',
                    'role'       => $role,
                    'icon_url'   => $iconUrl,
                ]
            );

            $synced++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Готово: {$synced} синхронізовано, {$failed} помилок.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
