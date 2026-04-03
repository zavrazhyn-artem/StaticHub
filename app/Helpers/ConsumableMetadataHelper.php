<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\Recipe;

class ConsumableMetadataHelper
{
    /**
     * Apply display metadata and default quantities to the recipe.
     */
    public static function applyDisplayMetadata(Recipe $recipe): Recipe
    {
        $iconName = $recipe->outputItem?->icon;
        if ($iconName && !str_starts_with($iconName, 'http')) {
            $iconUrl = "https://wow.zamimg.com/images/wow/icons/large/{$iconName}.jpg";
        } else {
            $iconUrl = $iconName;
        }

        if (str_contains($recipe->name, 'Potion Cauldron')) {
            $recipe->display_icon_url = $iconUrl ?: 'https://wow.zamimg.com/images/wow/icons/large/inv_alchemy_elixir_empty.jpg';
            $recipe->display_icon = 'science';
            $recipe->display_color = 'mage';
            $recipe->default_quantity = 1;
        } elseif (str_contains($recipe->name, 'Sin\'dorei Flasks')) {
            $recipe->display_icon_url = $iconUrl ?: 'https://wow.zamimg.com/images/wow/icons/large/inv_10_alchemy_flask_color_red.jpg';
            $recipe->display_icon = 'science';
            $recipe->display_color = 'druid';
            $recipe->default_quantity = 1;
        } else {
            $recipe->display_icon_url = $iconUrl ?: 'https://wow.zamimg.com/images/wow/icons/large/inv_misc_food_buff_revelation.jpg';
            $recipe->display_icon = 'restaurant';
            $recipe->display_color = 'secondary';
            $recipe->default_quantity = 2;
        }

        // Compatibility for blade templates
        $recipe->icon = $recipe->outputItem?->icon;

        return $recipe;
    }
}
