@props(['recipe', 'value' => 0, 'icon', 'icon_url' => null, 'color' => 'primary'])

<div class="bg-surface-container-highest p-2 rounded-lg relative group flex items-center justify-between gap-3">
    <div class="flex items-center gap-2 min-w-0">
        <div class="w-8 h-8 bg-gradient-to-br from-{{ $color }}/20 to-transparent rounded p-1 flex items-center justify-center border border-{{ $color }}/30 overflow-hidden shrink-0">
            @if($icon_url)
                <img src="{{ $icon_url }}" alt="{{ $recipe->name ?? $recipe }}" class="w-full h-full object-cover rounded-sm">
            @else
                <span class="material-symbols-outlined text-{{ $color }}" style="font-variation-settings: 'FILL' 1;">{{ $icon }}</span>
            @endif
        </div>
        <div class="font-headline font-bold text-{{ $color }} text-xs leading-tight truncate">{{ $recipe->name ?? $recipe }}</div>
    </div>

    <div class="flex items-center gap-2 bg-black/20 rounded-md p-1 border border-white/5" x-data="{
        _interval: null,
        startInterval(fn) {
            this.stopInterval();
            this._interval = window.setInterval(fn, 100);
        },
        stopInterval() {
            if (this._interval) {
                window.clearInterval(this._interval);
                this._interval = null;
            }
        }
    }">
        <button type="button"
                x-on:click="{{ $attributes->get('x-model.number') ?? $attributes->get('x-model') ?? 'modelValue' }} = Math.max(0, ({{ $attributes->get('x-model.number') ?? $attributes->get('x-model') ?? '0' }}) - 1)"
                x-on:mousedown="startInterval(() => {{ $attributes->get('x-model.number') ?? $attributes->get('x-model') ?? 'modelValue' }} = Math.max(0, ({{ $attributes->get('x-model.number') ?? $attributes->get('x-model') ?? '0' }}) - 1))"
                x-on:mouseup="stopInterval()"
                x-on:mouseleave="stopInterval()"
                x-on:disabled="({{ $attributes->get('x-model.number') ?? $attributes->get('x-model') ?? '0' }}) <= 0"
                class="w-6 h-6 flex items-center justify-center rounded bg-surface-container-highest hover:bg-white/10 text-on-surface-variant transition-colors select-none disabled:opacity-30 disabled:cursor-not-allowed">
            <span class="material-symbols-outlined text-sm">remove</span>
        </button>

        <div class="min-w-[1.5rem] text-center font-headline font-black text-white text-sm tabular-nums" x-text="{{ $attributes->get('x-model.number') ?? $attributes->get('x-model') ?? '0' }}"></div>

        <button type="button"
                x-on:click="{{ $attributes->get('x-model.number') ?? $attributes->get('x-model') ?? 'modelValue' }} = Math.min(9, ({{ $attributes->get('x-model.number') ?? $attributes->get('x-model') ?? '0' }}) + 1)"
                x-on:mousedown="startInterval(() => {{ $attributes->get('x-model.number') ?? $attributes->get('x-model') ?? 'modelValue' }} = Math.min(9, ({{ $attributes->get('x-model.number') ?? $attributes->get('x-model') ?? '0' }}) + 1))"
                x-on:mouseup="stopInterval()"
                x-on:mouseleave="stopInterval()"
                x-on:disabled="({{ $attributes->get('x-model.number') ?? $attributes->get('x-model') ?? '0' }}) >= 9"
                class="w-6 h-6 flex items-center justify-center rounded bg-surface-container-highest hover:bg-white/10 text-on-surface-variant transition-colors select-none disabled:opacity-30 disabled:cursor-not-allowed">
            <span class="material-symbols-outlined text-sm">add</span>
        </button>

        @if($attributes->get('name'))
            <input type="hidden" name="{{ $attributes->get('name') }}" x-bind:value="{{ $attributes->get('x-model.number') ?? $attributes->get('x-model') ?? '0' }}">
        @endif
    </div>
</div>
