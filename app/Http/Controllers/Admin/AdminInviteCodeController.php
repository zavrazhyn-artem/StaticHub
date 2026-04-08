<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InviteCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminInviteCodeController extends Controller
{
    public function index(): View
    {
        $codes = InviteCode::query()
            ->with('usedBy')
            ->latest()
            ->paginate(25);

        $stats = [
            'total' => InviteCode::count(),
            'unused' => InviteCode::query()->unused()->count(),
            'used' => InviteCode::query()->used()->count(),
        ];

        return view('admin.invite-codes.index', compact('codes', 'stats'));
    }

    public function generate(Request $request): RedirectResponse
    {
        $request->validate([
            'count' => 'required|integer|min:1|max:50',
        ]);

        $count = (int) $request->input('count');
        $generated = [];

        for ($i = 0; $i < $count; $i++) {
            $generated[] = [
                'code' => InviteCode::generateCode(),
                'is_used' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        InviteCode::insert($generated);

        return back()->with('success', "{$count} invite code(s) generated.");
    }

    public function destroy(InviteCode $inviteCode): RedirectResponse
    {
        if ($inviteCode->is_used) {
            return back()->with('error', 'Cannot delete a used invite code.');
        }

        $inviteCode->delete();

        return back()->with('success', 'Invite code deleted.');
    }
}
