<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StaticGroup;
use App\Models\User;
use App\Services\Ghost\GhostModeService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminStaticsController extends Controller
{
    private const SORTABLE = ['name', 'owner_name', 'members_count', 'region', 'created_at'];
    private const PER_PAGE = 25;

    public function __construct(private readonly GhostModeService $ghost) {}

    public function index(Request $request): View
    {
        abort_unless($this->ghost->canActivate(), 403, 'Ghost mode not available for this user.');

        $search = trim((string) $request->query('q', ''));
        $sort = (string) $request->query('sort', 'name');
        $direction = strtolower((string) $request->query('dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        if (! in_array($sort, self::SORTABLE, true)) {
            $sort = 'name';
        }

        $query = StaticGroup::withoutGlobalScopes()
            ->withCount('members')
            ->with('owner:id,name');

        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(function ($q) use ($like) {
                $q->where('statics.name', 'like', $like)
                  ->orWhereHas('owner', fn ($o) => $o->where('name', 'like', $like));
            });
        }

        if ($sort === 'owner_name') {
            $query->orderBy(
                User::query()->select('name')->whereColumn('id', 'statics.owner_id'),
                $direction
            );
        } elseif ($sort === 'members_count') {
            $query->orderBy('members_count', $direction);
        } else {
            $query->orderBy('statics.' . $sort, $direction);
        }

        $statics = $query->paginate(self::PER_PAGE)->withQueryString();

        return view('admin.statics.index', [
            'statics' => $statics,
            'search' => $search,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }
}
