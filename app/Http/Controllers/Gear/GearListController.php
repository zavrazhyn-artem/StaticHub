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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GearListController extends Controller
{
    public function __construct(
        private readonly GearListService $service,
        private readonly GearViewService $viewService,
    ) {}

    public function summaries(StaticGroup $static, Request $request): JsonResponse
    {
        $characterId = (int) $request->query('character_id');
        $specId = (int) $request->query('spec_id');
        if ($characterId <= 0 || $specId <= 0) {
            return response()->json(['lists' => []]);
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

    public function setSlot(StaticGroup $static, GearList $list, SetSlotRequest $request): RedirectResponse
    {
        try {
            $this->service->setSlot(
                $request->user(),
                $list->id,
                (string) $request->validated('slot'),
                $request->filled('item_id') ? (int) $request->validated('item_id') : null,
                $request->filled('item_level') ? (int) $request->validated('item_level') : null,
                $request->filled('enchant_id') ? (int) $request->validated('enchant_id') : null,
                $request->validated('bonus_ids'),
            );
        } catch (GearListException $e) {
            return back()->withErrors(['slot' => $e->getMessage()]);
        }

        return back()->with('success', 'Slot updated.');
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
