@extends('admin.layouts.app')

@section('content')
    <div style="margin-bottom: 2rem;">
        <h1 style="font-family: 'Space Grotesk', sans-serif; font-size: 1.5rem; font-weight: 700;">Invite Codes</h1>
        <p style="color: #888; font-size: 0.875rem;">Manage one-time invite codes for static group creation</p>
    </div>

    {{-- Stats --}}
    <div style="display: grid; grid-template-columns: repeat(3, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 2rem; max-width: 600px;">
        <div class="admin-card" style="padding: 1rem;">
            <div class="admin-metric-label">Total</div>
            <div class="admin-metric-value" style="font-size: 1.5rem;">{{ $stats['total'] }}</div>
        </div>
        <div class="admin-card" style="padding: 1rem;">
            <div class="admin-metric-label">Available</div>
            <div class="admin-metric-value admin-primary" style="font-size: 1.5rem;">{{ $stats['unused'] }}</div>
        </div>
        <div class="admin-card" style="padding: 1rem;">
            <div class="admin-metric-label">Used</div>
            <div class="admin-metric-value" style="font-size: 1.5rem; color: #4ade80;">{{ $stats['used'] }}</div>
        </div>
    </div>

    {{-- Generate --}}
    <div class="admin-card" style="padding: 1.25rem; margin-bottom: 2rem; max-width: 400px;">
        <div style="font-weight: 600; margin-bottom: 0.75rem;">Generate New Codes</div>
        <form method="POST" action="{{ route('admin.invite-codes.generate') }}" style="display: flex; gap: 0.75rem; align-items: end;">
            @csrf
            <div>
                <label style="display: block; font-size: 0.75rem; color: #888; margin-bottom: 0.25rem;">Count</label>
                <input type="number" name="count" value="1" min="1" max="50" class="admin-input" style="width: 80px;">
            </div>
            <button type="submit" class="admin-btn admin-btn-primary">
                <span class="material-symbols-outlined" style="font-size: 18px;">add</span>
                Generate
            </button>
        </form>
        @error('count')
            <div style="color: #f87171; font-size: 0.8rem; margin-top: 0.5rem;">{{ $message }}</div>
        @enderror
    </div>

    {{-- Table --}}
    <div class="admin-card" style="overflow-x: auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Status</th>
                    <th>Used By</th>
                    <th>Used At</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($codes as $code)
                    <tr>
                        <td style="font-family: monospace; letter-spacing: 0.05em;">{{ $code->code }}</td>
                        <td>
                            @if($code->is_used)
                                <span class="admin-badge admin-badge-success">Used</span>
                            @else
                                <span class="admin-badge admin-badge-neutral">Available</span>
                            @endif
                        </td>
                        <td>
                            @if($code->usedBy)
                                {{ $code->usedBy->getDisplayName() }}
                            @else
                                <span style="color: #555;">-</span>
                            @endif
                        </td>
                        <td>{{ $code->used_at?->format('M d, Y H:i') ?? '-' }}</td>
                        <td>{{ $code->created_at->format('M d, Y H:i') }}</td>
                        <td>
                            @unless($code->is_used)
                                <form method="POST" action="{{ route('admin.invite-codes.destroy', $code) }}" style="display: inline;"
                                      onsubmit="return confirm('Delete this invite code?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="admin-btn admin-btn-danger" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;">
                                        Delete
                                    </button>
                                </form>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="text-align: center; color: #666; padding: 2rem;">No invite codes yet. Generate some above.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top: 1rem;">
        {{ $codes->links() }}
    </div>
@endsection
