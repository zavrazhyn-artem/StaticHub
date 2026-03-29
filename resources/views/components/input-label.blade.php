@props(['value'])

<label {{ $attributes->merge(['class' => 'block font-headline text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-2 ml-1']) }}>
    {{ $value ?? $slot }}
</label>
