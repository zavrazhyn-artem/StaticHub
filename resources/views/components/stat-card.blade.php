@props(['title', 'value', 'subtitle', 'icon', 'color' => 'primary', 'trend' => null])

<div class="bg-surface-container-high p-6 rounded relative overflow-hidden group hover:bg-surface-bright transition-all">
    @if($color === 'success-neon')
        <div class="absolute top-0 right-0 w-32 h-32 bg-success-neon/5 blur-3xl -mr-16 -mt-16"></div>
    @endif
    <span class="material-symbols-outlined text-{{ $color }} mb-4" @if($color === 'success-neon' || $color === 'tertiary-dim' || $color === 'secondary') style="font-variation-settings: 'FILL' 1;" @endif>{{ $icon }}</span>
    <div class="text-{{ $color === 'success-neon' ? 'success-neon' : 'on-surface' }} font-headline text-xl font-bold tracking-tight uppercase">{{ $value }}</div>
    <div class="text-on-surface-variant text-xs font-bold uppercase tracking-widest mt-1">{{ $subtitle }}</div>
    @if($trend)
        <div class="absolute bottom-4 right-4 h-1 w-12 bg-{{ $color }}/20 rounded-full overflow-hidden">
            <div class="h-full bg-{{ $color }}" style="width: {{ $trend }}%"></div>
        </div>
    @endif
</div>
