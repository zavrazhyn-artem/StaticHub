<?php

declare(strict_types=1);

namespace App\Tasks\StaticGroup;

use App\Models\StaticGroup;

class UpdateStaticSettingsTask
{
    /**
     * Update static group settings.
     *
     * @param StaticGroup $static
     * @param array $data
     * @return void
     */
    public function run(StaticGroup $static, array $data): void
    {
        $static->update($data);
    }
}
