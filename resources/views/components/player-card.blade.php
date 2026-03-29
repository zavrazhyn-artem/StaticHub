@props(['avatar', 'name', 'class', 'ilvl', 'role', 'spec' => null, 'combat_role' => null, 'status' => 'offline', 'player_name' => null, 'realm_name' => null])

@php
    $classColor = strtolower(str_replace([' ', "'"], '-', $class));
    $statusColor = $status === 'online' ? 'success-neon' : 'on-surface-variant';

    $roleIcon = match($combat_role) {
        'tank' => 'shield',
        'heal' => 'medical_services',
        'mdps' => 'swords',
        'rdps' => 'magic_button',
        default => null,
    };

    $roleColor = match($combat_role) {
        'tank' => 'text-blue-400',
        'heal' => 'text-success-neon',
        'mdps' => 'text-error-dim',
        'rdps' => 'text-purple-400',
        default => 'text-on-surface-variant',
    };
@endphp

<div class="bg-surface-container-highest rounded-lg overflow-hidden border-l-4 border-{{ $classColor }} group hover:bg-surface-bright transition-all shadow-lg">
    <div class="p-5 flex items-start gap-4">
        <div class="relative">
            <img alt="{{ $name }} Avatar" class="w-16 h-16 rounded object-cover border border-white/5" src="{{ $avatar }}">
            <div class="absolute -bottom-1 -right-1 w-4 h-4 bg-{{ $statusColor }} rounded-full border-2 border-surface-container-highest @if($status === 'online') glow-primary @endif"></div>
        </div>
        <div class="flex-1 min-w-0">
            <div class="flex justify-between items-start gap-2">
                <div class="truncate">
                    <div class="flex items-center gap-2">
                        <h3 class="font-headline font-bold text-white tracking-tight leading-tight truncate">{{ $name }}</h3>
                    </div>
                    @if($player_name && $player_name !== $name)
                        <div class="text-[10px] text-on-surface-variant font-bold uppercase tracking-widest mt-0.5 truncate">{{ $player_name }}</div>
                    @endif
                </div>
                <span class="bg-{{ $classColor }}/10 text-{{ $classColor }} text-[10px] px-2 py-0.5 rounded font-bold uppercase tracking-widest whitespace-nowrap">{{ $class }}</span>
            </div>
            <div class="flex items-center justify-between mt-2">
                <p class="text-on-surface-variant text-sm font-medium">
                    @if($spec) <span class="text-white/80">{{ $spec }}</span> | @endif <span class="text-white">ilvl {{ $ilvl }}</span>
                </p>
                @if($roleIcon)
                    <span class="material-symbols-outlined text-base {{ $roleColor }}" title="{{ ucfirst($combat_role) }}">{{ $roleIcon }}</span>
                @endif
            </div>
        </div>
    </div>
    @if($slot->isNotEmpty())
        <div x-data="{ open: false }">
            <div @click="open = !open" class="bg-black/20 px-5 py-3 flex justify-between items-center text-[10px] font-bold uppercase tracking-widest text-on-surface-variant border-t border-white/5 cursor-pointer hover:text-white transition-colors">
                <span>Alts ({{ $slot->attributes->get('count', 0) }})</span>
                <span class="material-symbols-outlined text-sm transition-transform" :class="open ? 'rotate-180' : ''">expand_more</span>
            </div>
            <div x-show="open" x-collapse class="bg-black/10 border-t border-white/5">
                {{ $slot }}
            </div>
        </div>
    @endif
</div>
