@props(['user', 'status' => 'paid', 'initials' => '??', 'color' => 'outline-variant'])

@php
    $statusClasses = $status === 'paid' ? 'text-success-neon' : 'text-error-dim';
    $statusIcon = $status === 'paid' ? 'check_circle' : 'schedule';
@endphp

<div class="px-6 py-4 flex items-center justify-between hover:bg-white/5 transition-colors">
    <div class="flex items-center gap-3">
        <div class="w-8 h-8 rounded bg-{{ $color }}/20 border border-{{ $color }}/30 flex items-center justify-center text-[10px] font-bold text-{{ $color }}">{{ $initials }}</div>
        <span class="text-xs font-bold text-on-surface">{{ $user->battletag ?? $user->name }}</span>
    </div>
    <span class="text-[10px] font-bold uppercase tracking-widest {{ $statusClasses }} flex items-center gap-1">
        <span class="material-symbols-outlined text-[14px]">{{ $statusIcon }}</span> {{ ucfirst($status) }}
    </span>
</div>
