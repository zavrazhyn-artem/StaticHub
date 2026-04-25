import { reactive } from 'vue'

const state = reactive({
    open: false,
    context: null, // { rsvpRoute, userCharacters, selectedCharacterId, currentAttendance, characterSpecs }
})

function openModal(context) {
    if (!context) return
    state.context = context
    state.open = true
}

function closeModal() {
    state.open = false
}

export function useRsvpModal() {
    return { state, openModal, closeModal }
}
