<?php

namespace App\Console\Commands;

use App\Services\WclService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class TestCommand extends Command
{
    protected $signature = 'app:test';
    protected $description = 'Test Warcraft Logs API directly';

    public function handle()
    {
        $this->info('Starting WCL Test...');
        $wcl = new WclService();

        $reportId = 'aCf93mdkhgNRKFZq';
        $this->info("Fetching data for report: {$reportId}");

        try {
            $data = $wcl->getLogSummary($reportId);

            // Зберігаємо в файл для дебагу
            $path = storage_path("logs/wcl_debug_{$reportId}.json");

// Виводимо в консоль
            File::put($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            $this->info("✅ Дані успішно отримано!");
            $this->info("Збережено у: {$path}");

            $this->table(
                ['Метрика', 'Кількість'],
                [
                    ['Рейдові бої', count($data['fights'] ?? [])],
                    ['Гравці (Roster)', count($data['players'] ?? [])],
                    ['Події смерті', count($data['deaths'] ?? [])],
                    ['Події кіків (Interrupts)', count($data['interrupts'] ?? [])],
                ]
            );

        } catch (\Exception $e) {
            $this->error("Помилка: " . $e->getMessage());
        }
    }
}
