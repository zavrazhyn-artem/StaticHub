<?php

declare(strict_types=1);

namespace App\Helpers;

class TreasuryTaxWarningHelper
{
    public static function compute(int $fixedTax, int $realCostPerPlayer): array
    {
        $status      = 'success';
        $description = __('Tax covers current AH prices.');
        $cssClass    = 'text-on-surface-variant';

        if ($realCostPerPlayer > $fixedTax) {
            $status      = 'danger';
            $description = __('Deficit! Real cost is ~:amount. Increase tax.', [
                'amount' => number_format(CurrencyHelper::copperToGold($realCostPerPlayer)),
            ]);
            $cssClass = 'text-error';
        } elseif ($fixedTax > $realCostPerPlayer * 1.3) {
            $status      = 'warning';
            $description = __('High Surplus. Real cost dropped to ~:amount. Consider lowering.', [
                'amount' => number_format(CurrencyHelper::copperToGold($realCostPerPlayer)),
            ]);
            $cssClass = 'text-warning';
        }

        return [
            'taxStatus'      => $status,
            'taxDescription' => $description,
            'taxClass'       => $cssClass,
        ];
    }
}
