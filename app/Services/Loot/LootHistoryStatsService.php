<?php

declare(strict_types=1);

namespace App\Services\Loot;

use App\Models\LootDistribution;
use App\Models\SeasonItem;
use App\Models\StaticGroup;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Read-side aggregate for the Loot History tab. Owns filter normalisation,
 * paginated rows (with sort + search + per-page), the per-method/response
 * breakdown bar, and the per-character roll-up restricted to roster mains.
 * Pure SELECT — all mutations live in LootHistorySyncService.
 */
final class LootHistoryStatsService
{
    private const DEFAULT_PER_PAGE = 10;
    private const PER_PAGE_OPTIONS = [10, 25, 50, 100, 200];

    /** @var array<string, string> */
    private const DIFFICULTIES = [
        'M' => 'Mythic',
        'H' => 'Heroic',
        'N' => 'Normal',
        'R' => 'LFR',
    ];

    /** @var array<string, string> */
    private const METHODS = [
        'bis'   => 'BiS',
        'ms'    => 'Mainspec',
        'os'    => 'Offspec',
        'free'  => 'Free roll',
        'trash' => 'Disenchant',
    ];

    /** Whitelist column → backing expression for SQL safety. */
    private const SORT_COLUMNS = [
        'awarded_at'      => 'loot_distributions.awarded_at',
        'recipient_name'  => 'loot_distributions.recipient_name',
        'item_id'         => 'loot_distributions.item_id',
        'item_level'      => 'loot_distributions.item_level',
        'item_slot'       => 'loot_distributions.item_slot',
        'raid_difficulty' => 'loot_distributions.raid_difficulty',
        'method'          => 'loot_distributions.method',
        'encounter_id'    => 'loot_distributions.encounter_id',
    ];

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function buildLootHistoryPayload(StaticGroup $static, array $filters): array
    {
        $f = $this->normaliseFilters($filters);

        $rows = $this->paginatedRows($static, $f);
        $totals = $this->aggregate($static, $f);
        $perCharacter = $this->perCharacter($static, $f);
        $availableSlots = $this->availableSlots($static);

        return [
            'static'           => $static,
            'rows'             => $rows,
            'totals'           => $totals,
            'perCharacter'     => $perCharacter,
            'filters'          => $f,
            'availableSlots'   => $availableSlots,
            'availableBosses'  => $this->availableBosses(),
            'difficulties'     => self::DIFFICULTIES,
            'methods'          => self::METHODS,
            'rosterCharacters' => $this->rosterCharacters($static),
            'perPageOptions'   => self::PER_PAGE_OPTIONS,
        ];
    }

    /**
     * Boss list driven by the season catalogue, not by what's already
     * been awarded — so the dropdown shows every encounter the static
     * COULD see loot from, even if the bekend has zero rows for it yet.
     * Returned items: { encounter_id, boss_name } — the encounter_id is
     * what we filter on, the name is what the picker shows.
     *
     * @return list<array{encounter_id:int, boss_name:string}>
     */
    private function availableBosses(): array
    {
        return DB::table('season_items')
            ->whereNotNull('encounter_id')
            ->whereNotNull('boss_name')
            ->where('season_slug', SeasonItem::CURRENT_SEASON_SLUG)
            ->select('encounter_id', 'boss_name')
            ->distinct()
            ->orderBy('boss_name')
            ->get()
            ->map(fn ($r) => [
                'encounter_id' => (int) $r->encounter_id,
                'boss_name'    => (string) $r->boss_name,
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function normaliseFilters(array $raw): array
    {
        $allowedDiff = array_keys(self::DIFFICULTIES);
        $allowedMethod = array_keys(self::METHODS);

        $diff = isset($raw['difficulty']) && in_array($raw['difficulty'], $allowedDiff, true)
            ? (string) $raw['difficulty'] : null;
        $method = isset($raw['method']) && in_array($raw['method'], $allowedMethod, true)
            ? (string) $raw['method'] : null;
        $slot = isset($raw['slot']) && is_string($raw['slot']) && $raw['slot'] !== ''
            ? (string) $raw['slot'] : null;
        $char = isset($raw['character_id']) && is_numeric($raw['character_id'])
            ? (int) $raw['character_id'] : null;

        $from = $this->parseDate($raw['from'] ?? null);
        $to   = $this->parseDate($raw['to'] ?? null);

        $sort = isset($raw['sort']) && isset(self::SORT_COLUMNS[$raw['sort']])
            ? (string) $raw['sort'] : 'awarded_at';
        $dir = (isset($raw['dir']) && strtolower((string) $raw['dir']) === 'asc') ? 'asc' : 'desc';

        $perPage = isset($raw['per_page']) && in_array((int) $raw['per_page'], self::PER_PAGE_OPTIONS, true)
            ? (int) $raw['per_page'] : self::DEFAULT_PER_PAGE;

        $trimOrNull = function ($v) {
            if (! is_string($v)) return null;
            $t = trim($v);
            return $t === '' ? null : $t;
        };

        $encounterId = isset($raw['encounter_id']) && is_numeric($raw['encounter_id'])
            ? (int) $raw['encounter_id'] : null;

        return [
            'difficulty'   => $diff,
            'slot'         => $slot,
            'method'       => $method,
            'character_id' => $char,
            'from'         => $from?->toDateString(),
            'to'           => $to?->toDateString(),
            'sort'         => $sort,
            'dir'          => $dir,
            'per_page'     => $perPage,
            'item'         => $trimOrNull($raw['item'] ?? null),
            'encounter_id' => $encounterId,
        ];
    }

    private function parseDate(mixed $raw): ?CarbonImmutable
    {
        if (! is_string($raw) || $raw === '') return null;
        try {
            return CarbonImmutable::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Static-wide guards applied to every query (paginated rows,
     * aggregates, per-character roll-up) — without these the wrong
     * static's loot or non-roster awards would leak in.
     */
    private function applyGlobalScope($query, StaticGroup $static)
    {
        return $query->forStatic($static->id)
            ->realAwards()
            // Loot for players outside the static's roster never surfaces
            // in the UI — they're stored (so a future assignToStatic
            // backfill can claim them) but never shown.
            ->whereNotNull('recipient_character_id');
    }

    /**
     * @param  array<string, mixed>  $f
     */
    private function applyFilters($query, StaticGroup $static, array $f, bool $applySearch = true, bool $excludeTest = false)
    {
        $this->applyGlobalScope($query, $static);

        if ($excludeTest) {
            $query->where('is_test_mode', false);
        }

        if ($f['difficulty']) $query->withDifficulty($f['difficulty']);
        if ($f['slot'])       $query->withSlot($f['slot']);
        if ($f['method'])     $query->where('method', $f['method']);
        if ($f['character_id']) $query->forCharacterId($f['character_id']);
        if ($f['from']) $query->where('awarded_at', '>=', Carbon::parse($f['from'])->startOfDay());
        if ($f['to'])   $query->where('awarded_at', '<=', Carbon::parse($f['to'])->endOfDay());

        if (! $applySearch) return $query;

        $like = fn ($s) => '%' . str_replace(['%', '_'], ['\\%', '\\_'], $s) . '%';

        // Item filter — accepts either a numeric item id or a substring of
        // the item name (joined via items table).
        if ($f['item']) {
            $needle = $like($f['item']);
            $query->where(function ($q) use ($needle, $f) {
                $q->whereExists(function ($sub) use ($needle) {
                    $sub->select(DB::raw(1))
                        ->from('items')
                        ->whereColumn('items.id', 'loot_distributions.item_id')
                        ->where('items.name', 'like', $needle);
                });
                if (ctype_digit((string) $f['item'])) {
                    $q->orWhere('loot_distributions.item_id', (int) $f['item']);
                }
            });
        }

        if ($f['encounter_id']) {
            $query->where('loot_distributions.encounter_id', $f['encounter_id']);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $f
     */
    private function paginatedRows(StaticGroup $static, array $f): LengthAwarePaginator
    {
        $query = LootDistribution::query()
            ->with([
                'recipientCharacter:id,name,realm_id,playable_class,avatar_url',
                'recipientCharacter.realm:id,name,slug',
            ]);

        $this->applyFilters($query, $static, $f);

        $query->orderBy(self::SORT_COLUMNS[$f['sort']], $f['dir']);
        // awarded_at desc as a stable secondary so equal sort values sit
        // newest-first within their group.
        if ($f['sort'] !== 'awarded_at') {
            $query->orderByDesc('loot_distributions.awarded_at');
        }

        $page = $query->paginate($f['per_page'])->withQueryString();

        // Hydrate item display fields from the catalogue. items table is
        // canonical for name+icon. Resolve boss_name + raid (source_slug)
        // via season_items keyed on encounter_id — locale-stable, no
        // dependency on whatever localized string the addon shipped.
        $itemIds = $page->pluck('item_id')->unique()->all();
        $itemMeta = DB::table('items')
            ->whereIn('id', $itemIds)
            ->get(['id', 'name', 'icon', 'quality'])
            ->keyBy('id');

        $encounterIds = $page->pluck('encounter_id')->filter()->unique()->all();
        $encounterMeta = empty($encounterIds) ? collect() : DB::table('season_items')
            ->whereIn('encounter_id', $encounterIds)
            ->select('encounter_id', 'boss_name', 'source_slug')
            ->distinct()
            ->get()
            ->keyBy('encounter_id');

        // Spec snapshot resolution — recipient_spec_id is the Blizzard spec
        // id RC's candidate AceComm shipped at award time; the
        // specializations table is keyed on the same id so a single IN
        // lookup yields name + icon + role for the cell.
        $specIds = $page->pluck('recipient_spec_id')->filter()->unique()->all();
        $specMeta = empty($specIds) ? collect() : DB::table('specializations')
            ->whereIn('id', $specIds)
            ->get(['id', 'name', 'role', 'icon_url'])
            ->keyBy('id');

        $page->getCollection()->transform(function ($row) use ($itemMeta, $encounterMeta, $specMeta) {
            $meta = $itemMeta->get($row->item_id);
            $row->item_name    = $meta->name ?? null;
            $row->item_icon    = $meta->icon ?? null;
            $row->item_quality = isset($meta->quality) ? (int) $meta->quality : null;

            $enc = $row->encounter_id ? $encounterMeta->get($row->encounter_id) : null;
            $row->boss_display = $enc->boss_name ?? null;
            $row->raid_name    = $enc ? SeasonItem::displaySource($enc->source_slug) : null;

            $spec = $row->recipient_spec_id ? $specMeta->get($row->recipient_spec_id) : null;
            $row->spec_name = $spec->name ?? null;
            $row->spec_icon = $spec->icon_url ?? null;
            $row->spec_role = $spec->role ?? null;

            // Class + display name come from the linked character (we
            // never show non-roster awards, so recipientCharacter is
            // always loaded here). recipient_name is kept as a debug
            // string but the UI sources its display from the canonical
            // characters row.
            $char = $row->recipientCharacter;
            $row->recipient_class   = $char->playable_class ?? null;
            $row->recipient_display = $char->name ?? (explode('-', $row->recipient_name ?? '', 2)[0] ?: null);
            $row->recipient_avatar  = $char->avatar_url ?? null;

            return $row;
        });

        return $page;
    }

    /**
     * @param  array<string, mixed>  $f
     * @return array{total:int, by_method:array<string,int>, by_difficulty:array<string,int>, by_response:list<array{text:string,color:?string,count:int}>}
     */
    private function aggregate(StaticGroup $static, array $f): array
    {
        // Aggregates are static-wide, NOT scoped by table filters — the
        // cards above the table are meant as a constant overview, so
        // filtering the table to just one boss shouldn't redraw the
        // totals to that boss too.
        $base = LootDistribution::query();
        $this->applyGlobalScope($base, $static);

        $rows = (clone $base)
            ->selectRaw('method, raid_difficulty, response_text, response_color, COUNT(*) as cnt')
            ->groupBy('method', 'raid_difficulty', 'response_text', 'response_color')
            ->get();

        $total = 0;
        $byMethod = array_fill_keys(array_keys(self::METHODS), 0);
        $byDifficulty = array_fill_keys(array_keys(self::DIFFICULTIES), 0);
        $byResponse = [];

        foreach ($rows as $r) {
            $cnt = (int) $r->cnt;
            $total += $cnt;
            if (isset($byMethod[$r->method])) $byMethod[$r->method] += $cnt;
            if (isset($byDifficulty[$r->raid_difficulty])) $byDifficulty[$r->raid_difficulty] += $cnt;
            $key = ($r->response_text ?? '?') . '|' . ($r->response_color ?? '');
            if (! isset($byResponse[$key])) {
                $byResponse[$key] = [
                    'text'  => $r->response_text ?? 'Unknown',
                    'color' => $r->response_color,
                    'count' => 0,
                ];
            }
            $byResponse[$key]['count'] += $cnt;
        }

        usort($byResponse, fn ($a, $b) => $b['count'] <=> $a['count']);

        return [
            'total'         => $total,
            'by_method'     => $byMethod,
            'by_difficulty' => $byDifficulty,
            'by_response'   => array_values($byResponse),
        ];
    }

    /**
     * Roster-only roll-up. Filters to rows whose recipient resolved to a
     * character that's a `main` in this static — alts and pugs are still
     * counted in totals/breakdown but don't clutter this table.
     *
     * @param  array<string, mixed>  $f
     * @return list<array<string, mixed>>
     */
    private function perCharacter(StaticGroup $static, array $f): array
    {
        // Allow-list: every character in this static (mains + alts), so
        // alt-rolling shows up in the per-character roll-up too. A
        // recipient that exists in the characters table but isn't in
        // THIS static (cross-realm pug, etc.) still gets filtered out.
        $rosterIds = DB::table('character_static')
            ->where('static_id', $static->id)
            ->pluck('character_id')
            ->all();
        if (empty($rosterIds)) return [];

        // Per-character roll-up is also a static-wide overview — same
        // reasoning as aggregate(). Roster-only allow-list is added on
        // top of the global scope.
        $base = LootDistribution::query();
        $this->applyGlobalScope($base, $static);
        $base->whereIn('recipient_character_id', $rosterIds);

        $rows = (clone $base)
            ->join('characters', 'characters.id', '=', 'loot_distributions.recipient_character_id')
            ->selectRaw('
                loot_distributions.recipient_character_id,
                characters.name as recipient_name,
                characters.playable_class as recipient_class,
                loot_distributions.method,
                COUNT(*) as cnt,
                MAX(loot_distributions.awarded_at) as last_awarded_at
            ')
            ->groupBy(
                'loot_distributions.recipient_character_id',
                'characters.name',
                'characters.playable_class',
                'loot_distributions.method',
            )
            ->get();

        $byChar = [];
        foreach ($rows as $r) {
            $key = (int) $r->recipient_character_id;
            if (! isset($byChar[$key])) {
                $byChar[$key] = [
                    'character_id'    => $key,
                    'name'            => $r->recipient_name,
                    'class'           => $r->recipient_class,
                    'total'           => 0,
                    'last_awarded_at' => null,
                    'by_method'       => array_fill_keys(array_keys(self::METHODS), 0),
                ];
            }
            $byChar[$key]['total'] += (int) $r->cnt;
            if (isset($byChar[$key]['by_method'][$r->method])) {
                $byChar[$key]['by_method'][$r->method] += (int) $r->cnt;
            }
            $cur = $byChar[$key]['last_awarded_at'];
            if ($r->last_awarded_at && (! $cur || $r->last_awarded_at > $cur)) {
                $byChar[$key]['last_awarded_at'] = (string) $r->last_awarded_at;
            }
        }

        usort($byChar, fn ($a, $b) => $b['total'] <=> $a['total']);
        return array_values($byChar);
    }

    /**
     * Distinct slots that actually appear in this static's loot. Used
     * to populate the slot filter dropdown without dragging in slots
     * we've never seen a drop for.
     *
     * @return list<string>
     */
    private function availableSlots(StaticGroup $static): array
    {
        return LootDistribution::query()
            ->forStatic($static->id)
            ->whereNotNull('item_slot')
            ->distinct()
            ->orderBy('item_slot')
            ->pluck('item_slot')
            ->all();
    }

    /**
     * Every character in this static — mains AND alts. The recipient
     * filter dropdown wants alts visible too because awards do go to
     * alts during normal raid rotations.
     *
     * Sorted: mains first (alphabetical), then alts (alphabetical).
     *
     * @return list<array{id:int, name:string, class:?string, role:string}>
     */
    private function rosterCharacters(StaticGroup $static): array
    {
        return DB::table('characters')
            ->join('character_static', 'characters.id', '=', 'character_static.character_id')
            ->where('character_static.static_id', $static->id)
            ->orderByRaw("CASE character_static.role WHEN 'main' THEN 0 ELSE 1 END")
            ->orderBy('characters.name')
            ->get(['characters.id', 'characters.name', 'characters.playable_class', 'characters.avatar_url', 'character_static.role'])
            ->map(fn ($r) => [
                'id'         => (int) $r->id,
                'name'       => $r->name,
                'class'      => $r->playable_class,
                'avatar_url' => $r->avatar_url,
                'role'       => $r->role,
            ])
            ->all();
    }
}
