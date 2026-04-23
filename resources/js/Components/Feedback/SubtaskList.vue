<script setup>
import { ref, getCurrentInstance } from 'vue';
import { feedbackApi, routes, SUBTASK_STATUS_META } from './api.js';

const { proxy } = getCurrentInstance();
const __ = (key, replace = {}) => proxy.__(key, replace);

const props = defineProps({
    subtasks: { type: Array, required: true },
    postId: { type: Number, required: true },
    canManage: { type: Boolean, default: false },
});

const STATUS_ORDER = ['todo', 'in_progress', 'done'];

const localSubtasks = ref([...props.subtasks]);
const newTitle = ref('');
const busy = ref(false);

async function addSubtask() {
    if (!newTitle.value.trim() || busy.value) return;
    busy.value = true;

    try {
        const { data } = await feedbackApi.post(routes.subtasks(props.postId), { title: newTitle.value });
        localSubtasks.value.push(data);
        newTitle.value = '';
    } catch (e) {
        alert(__('Failed to add subtask.'));
    } finally {
        busy.value = false;
    }
}

async function cycleStatus(subtask) {
    const currentIdx = STATUS_ORDER.indexOf(subtask.status);
    const nextIdx = (currentIdx + 1) % STATUS_ORDER.length;
    const newStatus = STATUS_ORDER[nextIdx];

    const prev = subtask.status;
    subtask.status = newStatus;

    try {
        await feedbackApi.patch(routes.updateSubtask(subtask.id), { status: newStatus });
    } catch (e) {
        subtask.status = prev;
        alert(__('Failed to update subtask.'));
    }
}

async function remove(subtask) {
    if (!confirm(__('Delete subtask ":title"?', { title: subtask.title }))) return;
    try {
        await feedbackApi.delete(routes.deleteSubtask(subtask.id));
        localSubtasks.value = localSubtasks.value.filter((s) => s.id !== subtask.id);
    } catch (e) {
        alert(__('Failed to delete subtask.'));
    }
}

function meta(status) {
    return SUBTASK_STATUS_META[status] || SUBTASK_STATUS_META.todo;
}
</script>

<template>
    <div class="flex flex-col gap-3">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-headline uppercase tracking-wider text-on-surface-variant">
                {{ __('Subtasks') }} ({{ localSubtasks.length }})
            </h3>
            <span v-if="localSubtasks.length > 0" class="text-xs text-on-surface-variant/70">
                {{ localSubtasks.filter(s => s.status === 'done').length }} / {{ localSubtasks.length }} {{ __('done') }}
            </span>
        </div>

        <div v-if="localSubtasks.length === 0 && !canManage" class="text-sm text-on-surface-variant/60 py-2">
            {{ __('No subtasks yet.') }}
        </div>

        <ul class="flex flex-col gap-1">
            <li
                v-for="subtask in localSubtasks"
                :key="subtask.id"
                class="flex items-center gap-2 px-3 py-2 rounded-lg bg-surface-container-high/40 border border-white/5 group"
            >
                <button
                    v-if="canManage"
                    type="button"
                    @click="cycleStatus(subtask)"
                    class="shrink-0 transition hover:scale-110"
                    :title="__('Click to change status. Current: :label', { label: __(meta(subtask.status).label) })"
                >
                    <span class="material-symbols-outlined text-xl" :class="meta(subtask.status).colorClass">
                        {{ meta(subtask.status).icon }}
                    </span>
                </button>
                <span v-else class="shrink-0">
                    <span class="material-symbols-outlined text-xl" :class="meta(subtask.status).colorClass">
                        {{ meta(subtask.status).icon }}
                    </span>
                </span>

                <span
                    class="flex-1 text-sm"
                    :class="subtask.status === 'done' ? 'text-on-surface-variant/60 line-through' : 'text-on-surface'"
                >
                    {{ subtask.title }}
                </span>

                <button
                    v-if="canManage"
                    @click="remove(subtask)"
                    class="p-1 rounded hover:bg-white/5 text-on-surface-variant hover:text-error transition opacity-0 group-hover:opacity-100"
                >
                    <span class="material-symbols-outlined text-sm">delete</span>
                </button>
            </li>
        </ul>

        <form v-if="canManage" @submit.prevent="addSubtask" class="flex gap-2 mt-1">
            <input
                v-model="newTitle"
                type="text"
                maxlength="300"
                :placeholder="__('Add a subtask…')"
                class="flex-1 px-3 py-2 rounded-lg bg-surface-container-high/60 border border-white/10 focus:border-primary focus:outline-none text-on-surface text-sm"
            />
            <button
                type="submit"
                :disabled="busy || !newTitle.trim()"
                class="px-3 py-2 rounded-lg text-xs font-semibold bg-primary text-on-primary hover:bg-primary/90 disabled:opacity-50 transition"
            >
                {{ __('Add') }}
            </button>
        </form>
    </div>
</template>
