<script setup>
import { ref, onMounted } from 'vue';

const props = defineProps({
    icon: { type: String, required: true },
    iconColor: { type: String, default: 'text-primary' },
    badge: { type: String, default: '' },
    title: { type: String, required: true },
    description: { type: String, required: true },
    features: { type: Array, default: () => [] },
    reversed: { type: Boolean, default: false },
    glowColor: { type: String, default: 'rgba(79,211,247,0.08)' },
});

const el = ref(null);
const isVisible = ref(false);

onMounted(() => {
    const observer = new IntersectionObserver(
        ([entry]) => {
            if (entry.isIntersecting) {
                isVisible.value = true;
                observer.disconnect();
            }
        },
        { threshold: 0.15 }
    );
    if (el.value) observer.observe(el.value);
});
</script>

<template>
    <div
        ref="el"
        class="relative py-20 md:py-28 transition-all duration-700 ease-out"
        :class="isVisible ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-12'"
    >
        <div
            class="max-w-6xl mx-auto px-6 flex flex-col gap-12"
            :class="reversed ? 'md:flex-row-reverse' : 'md:flex-row'"
        >
            <!-- Text side -->
            <div class="flex-1 flex flex-col justify-center space-y-6">
                <!-- Badge -->
                <div v-if="badge" class="inline-flex self-start items-center gap-2 px-3 py-1 rounded-full bg-white/5 border border-white/10">
                    <span class="material-symbols-outlined text-sm" :class="iconColor">{{ icon }}</span>
                    <span class="text-3xs font-semibold uppercase tracking-[0.2em] text-on-surface-variant">{{ badge }}</span>
                </div>

                <h2 class="font-headline text-2xl md:text-4xl font-bold text-white leading-tight" v-html="title"></h2>

                <p class="text-on-surface-variant text-sm md:text-base leading-relaxed max-w-lg">
                    {{ description }}
                </p>

                <!-- Features list -->
                <ul v-if="features.length" class="space-y-3 pt-2">
                    <li
                        v-for="(feature, i) in features"
                        :key="i"
                        class="flex items-start gap-3 transition-all duration-500"
                        :class="isVisible ? 'opacity-100 translate-x-0' : 'opacity-0 -translate-x-4'"
                        :style="{ transitionDelay: `${300 + i * 100}ms` }"
                    >
                        <span class="material-symbols-outlined text-lg mt-0.5 shrink-0" :class="iconColor">check_circle</span>
                        <span class="text-on-surface text-sm leading-relaxed">{{ feature }}</span>
                    </li>
                </ul>
            </div>

            <!-- Visual side -->
            <div class="flex-1 flex items-center justify-center">
                <div
                    class="relative w-full max-w-md aspect-[4/3] rounded-xl border border-white/5 bg-surface-container-low overflow-hidden"
                >
                    <!-- Glow effect -->
                    <div
                        class="absolute inset-0 opacity-40"
                        :style="{ background: `radial-gradient(ellipse at center, ${glowColor}, transparent 70%)` }"
                    ></div>

                    <!-- Placeholder visual with icon -->
                    <div class="absolute inset-0 flex flex-col items-center justify-center gap-4">
                        <div class="w-20 h-20 rounded-2xl bg-white/5 border border-white/10 flex items-center justify-center">
                            <span class="material-symbols-outlined text-4xl" :class="iconColor">{{ icon }}</span>
                        </div>
                        <div class="space-y-2 px-8">
                            <div class="h-2 w-48 bg-white/5 rounded-full mx-auto"></div>
                            <div class="h-2 w-36 bg-white/5 rounded-full mx-auto"></div>
                            <div class="h-2 w-40 bg-white/5 rounded-full mx-auto"></div>
                        </div>
                    </div>

                    <!-- Grid pattern -->
                    <div class="absolute inset-0 arcane-bg opacity-30"></div>
                </div>
            </div>
        </div>
    </div>
</template>
