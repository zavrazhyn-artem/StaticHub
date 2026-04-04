<script setup>
defineProps({
    // event.ai_analysis_html — { strategy, wipes, individual }
    analysisHtml: { type: Object, required: true },
});
</script>

<template>
    <div class="space-y-6">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Strategy & Wipes (2/3 width) -->
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-surface-container-high border border-white/5 rounded-2xl p-6">
                    <h3 class="text-primary font-headline text-xs font-black uppercase tracking-widest mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined">strategy</span>
                        Overall Strategy &amp; Execution
                    </h3>
                    <div class="prose prose-invert prose-sm max-w-none text-on-surface-variant" v-html="analysisHtml.strategy"></div>
                </div>

                <div class="bg-surface-container-high border border-white/5 rounded-2xl p-6">
                    <h3 class="text-error font-headline text-xs font-black uppercase tracking-widest mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined">dangerous</span>
                        Critical Failures &amp; Wipe Reasons
                    </h3>
                    <div class="prose prose-invert prose-sm max-w-none text-on-surface-variant" v-html="analysisHtml.wipes"></div>
                </div>
            </div>

            <!-- Performance highlights (1/3 width) -->
            <div class="space-y-6">
                <div class="bg-surface-container-high border border-white/5 rounded-2xl p-6">
                    <h3 class="text-success-neon font-headline text-xs font-black uppercase tracking-widest mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined">person</span>
                        Performance Highlights
                    </h3>
                    <div class="prose prose-invert prose-sm max-w-none text-on-surface-variant" v-html="analysisHtml.individual"></div>
                </div>
            </div>
        </div>
    </div>
</template>
