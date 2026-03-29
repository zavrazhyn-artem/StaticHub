@props(['recipe', 'value' => 0, 'icon', 'icon_url' => null, 'color' => 'primary'])

<div class="bg-surface-container-highest p-4 rounded-lg relative group">
    <div class="flex items-center gap-3 mb-4">
        <div class="w-10 h-10 bg-gradient-to-br from-{{ $color }}/20 to-transparent rounded p-1 flex items-center justify-center border border-{{ $color }}/30 overflow-hidden">
            @if($icon_url)
                <img src="{{ $icon_url }}" alt="{{ $recipe->name ?? $recipe }}" class="w-full h-full object-cover rounded-sm">
            @else
                <span class="material-symbols-outlined text-{{ $color }}" style="font-variation-settings: 'FILL' 1;">{{ $icon }}</span>
            @endif
        </div>
        <div class="font-headline font-bold text-{{ $color }} text-xs leading-tight">{{ $recipe->name ?? $recipe }}</div>
    </div>
    <input
        class="w-full bg-surface-container-lowest border-none text-xs p-2 rounded-sm focus:ring-0 text-primary border-b-2 border-transparent focus:border-primary transition-all"
        type="number"
        {{ $attributes }}
        value="{{ $value }}"
    />
</div>
