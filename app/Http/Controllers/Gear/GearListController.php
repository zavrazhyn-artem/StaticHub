<?php

declare(strict_types=1);

namespace App\Http\Controllers\Gear;

use App\Exceptions\GearListException;
use App\Exceptions\WishlistImportException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gear\ImportBisRequest;
use App\Http\Requests\Gear\ImportSimcRequest;
use App\Http\Requests\Gear\SetSlotRequest;
use App\Http\Requests\Gear\StoreGearListRequest;
use App\Models\GearList;
use App\Models\StaticGroup;
use App\Services\Gear\GearListService;
use App\Services\Gear\GearViewService;
use App\Services\Gear\SimcExportService;
use App\Services\Gear\SlotItemPickerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GearListController extends Controller
{
    public function __construct(
        private readonly GearListService $service,
        private readonly GearViewService $viewService,
        private readonly SlotItemPickerService $picker,
        private readonly SimcExportService $simcExport,
    ) {}

    /**
     * Returns a Raidbots-ready SimulationCraft string for the list. Owner check
     * mirrors the picker endpoint — only the user who owns the underlying
     * character can pull the export.
     */
    public function exportSimc(StaticGroup $static, GearList $list, Request $request): JsonResponse
    {
        if ($list->character?->user_id !== $request->user()?->id) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        return response()->json(['simc' => $this->simcExport->export($list)]);
    }

    /**
     * Returns the season-catalog items eligible for the (list, slot) pair —
     * filtered by character class + spec role, sorted by name — together with
     * the upgrade-track dropdown options the modal needs to render.
     */
    public function pickerOptions(StaticGroup $static, GearList $list, Request $request): JsonResponse
    {
        $slot = (string) $request->query('slot');
        if ($slot === '') {
            return response()->json(['error' => 'slot is required'], 422);
        }

        // Authorization: must own the character backing this list.
        if ($list->character?->user_id !== $request->user()?->id) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        return response()->json($this->picker->buildPayload($list, $slot));
    }

    public function summaries(StaticGroup $static, Request $request): JsonResponse
    {
        $characterId = (int) $request->query('character_id');
        $specId = (int) $request->query('spec_id');
        if ($characterId <= 0 || $specId <= 0) {
            return response()->json(['lists' => []]);
        }

        // Auto-create the Current + empty BiS singletons if missing so a
        // freshly added character's Gear tab isn't blank — the user expects
        // to see the two slots ready immediately on character switch.
        // Silently skip on ownership/spec errors — non-own characters still
        // return their existing summaries below.
        try {
            $this->service->ensureInitialLists($request->user(), $characterId, $specId);
        } catch (GearListException $e) {
            // not the user's own character — fall through to read-only summaries
        }

        return response()->json([
            'lists' => $this->viewService->listSummaries($characterId, $specId),
        ]);
    }

    public function activePayload(StaticGroup $static, GearList $list): JsonResponse
    {
        return response()->json([
            'list' => $this->viewService->activeList($list->id),
        ]);
    }

    public function store(StaticGroup $static, StoreGearListRequest $request): RedirectResponse
    {
        try {
            $this->service->createCustom(
                $request->user(),
                (int) $request->validated('character_id'),
                (int) $request->validated('spec_id'),
                (string) $request->validated('name'),
            );
        } catch (GearListException $e) {
            return back()->withErrors(['character_id' => $e->getMessage()])->withInput();
        }

        return back()->with('success', 'List created.');
    }

    public function update(StaticGroup $static, GearList $list, Request $request): RedirectResponse
    {
        $name = trim((string) $request->input('name'));
        if ($name === '') {
            return back()->withErrors(['name' => 'Name is required.']);
        }

        try {
            $this->service->rename($request->user(), $list->id, $name);
        } catch (GearListException $e) {
            return back()->withErrors(['name' => $e->getMessage()]);
        }

        return back()->with('success', 'List renamed.');
    }

    public function destroy(StaticGroup $static, GearList $list): RedirectResponse
    {
        try {
            $this->service->delete(Auth::user(), $list->id);
        } catch (GearListException $e) {
            return back()->withErrors(['list' => $e->getMessage()]);
        }

        return back()->with('success', 'List deleted.');
    }

    public function setSlot(StaticGroup $static, GearList $list, SetSlotRequest $request): RedirectResponse|JsonResponse
    {
        $itemLevel = $request->filled('item_level') ? (int) $request->validated('item_level') : null;
        $bonusIds  = $request->validated('bonus_ids');

        // If the picker submitted track+level, resolve to bonus_id + ilvl
        // server-side so the upgrade-track config stays out of the client.
        if ($request->filled('track') && $request->filled('level')) {
            try {
                [$bonusId, $ilvl] = $this->picker->resolveTrackLevel(
                    (string) $request->validated('track'),
                    (int) $request->validated('level'),
                );
                $bonusIds  = [$bonusId];
                $itemLevel = $ilvl;
            } catch (GearListException $e) {
                return $this->slotErrorResponse($request, $e->getMessage());
            }
        }

        try {
            $this->service->setSlot(
                $request->user(),
                $list->id,
                (string) $request->validated('slot'),
                $request->filled('item_id') ? (int) $request->validated('item_id') : null,
                $itemLevel,
                $request->filled('enchant_id') ? (int) $request->validated('enchant_id') : null,
                $bonusIds,
            );
        } catch (GearListException $e) {
            return $this->slotErrorResponse($request, $e->getMessage());
        }

        // AJAX callers (slot picker, clear-slot button) get the refreshed list
        // payload back so the UI can re-render without a navigation.
        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'list'    => $this->viewService->activeList($list->id),
            ]);
        }

        return back()->with('success', 'Slot updated.');
    }

    private function slotErrorResponse(\Illuminate\Http\Request $request, string $message): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['success' => false, 'error' => $message], 422);
        }
        return back()->withErrors(['slot' => $message]);
    }

    public function importBis(StaticGroup $static, ImportBisRequest $request): RedirectResponse
    {
        try {
            $this->service->importBis(
                $request->user(),
                (int) $request->validated('character_id'),
                (int) $request->validated('spec_id'),
                (string) $request->validated('url'),
            );
        } catch (GearListException|WishlistImportException $e) {
            return back()->withErrors(['url' => $e->getMessage()])->withInput();
        }

        return back()->with('success', 'BiS list imported.');
    }

    public function importSimc(StaticGroup $static, GearList $list, ImportSimcRequest $request): RedirectResponse
    {
        try {
            $this->service->importSimcIntoCustom(
                $request->user(),
                $list->id,
                (string) $request->validated('simc'),
            );
        } catch (GearListException|WishlistImportException $e) {
            return back()->withErrors(['simc' => $e->getMessage()])->withInput();
        }

        return back()->with('success', 'List filled from /simc paste.');
    }
}
