<?php

declare(strict_types=1);

namespace App\Builders;

use App\Models\Character;
use App\Models\Specialization;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class WishlistBuilder extends Builder
{
    public function forCharacter(int $characterId): self
    {
        return $this->where('character_id', $characterId);
    }

    public function forStatic(int $staticId): self
    {
        return $this->whereHas('character.statics', function ($query) use ($staticId) {
            $query->where('statics.id', $staticId);
        });
    }

    /**
     * Restrict to wishlists whose raid_slug refers to a real raid in the
     * season catalogue. Excludes M+ dungeon, Catalyst, Crafted, Delve and
     * any other source — the wishlist UI is intentionally raid-only.
     */
    public function raidsOnly(): self
    {
        return $this->whereIn('raid_slug', function ($q) {
            $q->select('source_slug')
                ->from('season_items')
                ->where('source_type', 'raid')
                ->distinct();
        });
    }

    public function findLatest(
        int $characterId,
        int $specId,
        string $raidSlug,
        string $difficulty
    ): ?Wishlist {
        return $this
            ->where('character_id', $characterId)
            ->where('spec_id', $specId)
            ->where('raid_slug', $raidSlug)
            ->where('difficulty', $difficulty)
            ->first();
    }

    /**
     * Wipe-and-replace upsert for a (character, spec, raid_slug, difficulty) tuple.
     * Replaces an existing wishlist + all its items in a single transaction.
     *
     * @param array<int, array{item_id:int, value:int, percent:float, status:string, comment:?string, position:int}> $items
     */
    public function upsertReplacing(
        Character $character,
        Specialization $spec,
        string $raidSlug,
        string $difficulty,
        string $source,
        string $sourceUrl,
        ?string $sourceReportId,
        ?array $rawPayload,
        ?\DateTimeInterface $generatedAt,
        array $items
    ): Wishlist {
        return DB::transaction(function () use (
            $character,
            $spec,
            $raidSlug,
            $difficulty,
            $source,
            $sourceUrl,
            $sourceReportId,
            $rawPayload,
            $generatedAt,
            $items
        ) {
            $wishlist = Wishlist::query()->updateOrCreate(
                [
                    'character_id' => $character->id,
                    'spec_id' => $spec->id,
                    'raid_slug' => $raidSlug,
                    'difficulty' => $difficulty,
                ],
                [
                    'source' => $source,
                    'source_url' => $sourceUrl,
                    'source_report_id' => $sourceReportId,
                    'raw_payload' => $rawPayload,
                    'generated_at' => $generatedAt,
                    'imported_at' => now(),
                ]
            );

            WishlistItem::query()->where('wishlist_id', $wishlist->id)->delete();

            if (! empty($items)) {
                $rows = array_map(
                    fn (array $item) => array_merge($item, [
                        'wishlist_id' => $wishlist->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]),
                    $items
                );
                WishlistItem::query()->insert($rows);
            }

            return $wishlist->fresh('items');
        });
    }

    /**
     * Eager-load items + spec + character for display.
     */
    public function withDisplayRelations(): self
    {
        return $this->with([
            'specialization',
            'character:id,user_id,name,realm_id,playable_class,avatar_url',
            'character.realm:id,name,slug',
            'items.item',
        ]);
    }

    /**
     * Group wishlists for a character into raid → difficulty buckets, returning a flat
     * Collection. Caller picks the structure they want.
     */
    public function listForCharacter(int $characterId): Collection
    {
        return $this
            ->forCharacter($characterId)
            ->withDisplayRelations()
            ->orderBy('raid_slug')
            ->orderBy('difficulty')
            ->get();
    }
}
