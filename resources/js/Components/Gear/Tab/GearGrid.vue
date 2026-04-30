<script setup>
import GearSlotCard from './GearSlotCard.vue';
import CharacterStatsPanel from './CharacterStatsPanel.vue';

const props = defineProps({
    slots: { type: Object, required: true },
    stats: { type: Object, default: null },
    statsPlaceholder: { type: String, default: '' },
    enchantableSlots: { type: Array, default: () => [] },
    audit: { type: Boolean, default: false },
    editable: { type: Boolean, default: false },
});

const emit = defineEmits(['edit', 'clear']);

const LEFT_COL = ['head', 'neck', 'shoulder', 'back', 'chest', null, null, 'wrist'];
const RIGHT_COL = ['hands', 'waist', 'legs', 'feet', 'finger_1', 'finger_2', 'trinket_1', 'trinket_2'];
const BOTTOM_ROW = ['main_hand', 'off_hand'];
</script>

<template>
    <!-- Side cards comfortably fit item names; center stats stays compact at
         its original size, with extra gap-x acting as breathing room. -->
    <div class="grid gap-x-24 gap-y-3 justify-center" style="grid-template-columns: minmax(220px, 260px) minmax(220px, 280px) minmax(220px, 260px);">
        <!-- Left column -->
        <div class="space-y-1.5 pt-1">
            <template v-for="(slot, idx) in LEFT_COL" :key="`L${idx}`">
                <GearSlotCard
                    v-if="slot"
                    :slot="slot"
                    :item="slots[slot]"
                    :enchantable-slots="enchantableSlots"
                    :audit="audit"
                    :editable="editable"
                    @edit="emit('edit', slot)"
                    @clear="emit('clear', slot)"
                />
                <div v-else class="h-[40px]"></div>
            </template>
        </div>

        <!-- Center: stats panel -->
        <div class="pt-1">
            <CharacterStatsPanel :stats="stats" :placeholder-text="statsPlaceholder || undefined" />
        </div>

        <!-- Right column -->
        <div class="space-y-1.5 pt-1">
            <GearSlotCard
                v-for="slot in RIGHT_COL"
                :key="slot"
                :slot="slot"
                :item="slots[slot]"
                :enchantable-slots="enchantableSlots"
                :audit="audit"
                :editable="editable"
                mirror
                @edit="emit('edit', slot)"
                @clear="emit('clear', slot)"
            />
        </div>

        <!-- Bottom row: weapons centered under the model. -->
        <div class="col-span-3 flex justify-center mt-2">
            <div class="grid grid-cols-2 gap-3 w-full max-w-md">
                <GearSlotCard
                    v-for="slot in BOTTOM_ROW"
                    :key="slot"
                    :slot="slot"
                    :item="slots[slot]"
                    :enchantable-slots="enchantableSlots"
                    :audit="audit"
                    :editable="editable"
                    @edit="emit('edit', slot)"
                    @clear="emit('clear', slot)"
                />
            </div>
        </div>
    </div>
</template>
