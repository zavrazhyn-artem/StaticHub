import { reactive } from 'vue'

const state = reactive({
    items: [],
})

let nextId = 1

function push(notification) {
    const id = nextId++
    const item = {
        id,
        type: notification.type ?? 'info',
        icon: notification.icon ?? null,
        title: notification.title ?? '',
        body: notification.body ?? '',
        action: notification.action ?? null,
        dismissible: notification.dismissible ?? true,
        persistKey: notification.persistKey ?? null,
        autoDismissMs: notification.autoDismissMs ?? null,
    }

    if (item.persistKey && localStorage.getItem(`notif_dismissed_${item.persistKey}`)) {
        return null
    }

    state.items.push(item)

    if (item.autoDismissMs) {
        setTimeout(() => dismiss(id), item.autoDismissMs)
    }

    return id
}

function dismiss(id) {
    const idx = state.items.findIndex(i => i.id === id)
    if (idx === -1) return
    const item = state.items[idx]
    if (item.persistKey) {
        localStorage.setItem(`notif_dismissed_${item.persistKey}`, '1')
    }
    state.items.splice(idx, 1)
}

export function useNotifications() {
    return { state, push, dismiss }
}
