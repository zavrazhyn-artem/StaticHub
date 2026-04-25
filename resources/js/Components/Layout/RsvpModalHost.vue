<template>
    <rsvp-modal
        v-if="state.context"
        :show="state.open"
        :user-characters="state.context.userCharacters"
        :selected-character-id="state.context.selectedCharacterId"
        :current-attendance="state.context.currentAttendance"
        :csrf-token="csrf"
        :rsvp-route="state.context.rsvpRoute"
        :character-specs="state.context.characterSpecs"
        @close="closeModal"
    />
</template>

<script setup>
import RsvpModal from '../Raid/RsvpModal.vue'
import { useRsvpModal } from '@/composables/useRsvpModal.js'

defineProps({
    csrf: { type: String, required: true },
})

const { state, closeModal } = useRsvpModal()
</script>
