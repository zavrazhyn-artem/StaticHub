import { ref, computed, watch } from 'vue';

export function useTimelineViewport({ fightDuration, leftWidth, rightPadding }) {
    const zoom = ref(1);
    const panX = ref(0);
    const containerWidth = ref(1600);

    const basePxPerSec = computed(() => {
        const usable = containerWidth.value - leftWidth - rightPadding;
        return Math.max(1, usable / Math.max(1, fightDuration.value));
    });
    const pxPerSec = computed(() => basePxPerSec.value * zoom.value);
    const contentWidth = computed(() => fightDuration.value * pxPerSec.value);
    const viewportWidth = computed(() => containerWidth.value - leftWidth - rightPadding);
    const maxPan = computed(() => Math.max(0, contentWidth.value - viewportWidth.value));

    watch(maxPan, () => {
        if (panX.value > maxPan.value) panX.value = maxPan.value;
        if (panX.value < 0) panX.value = 0;
    });

    const timeToX = (sec) => sec * pxPerSec.value - panX.value;
    const xToTime = (x) => Math.max(0, Math.min(
        fightDuration.value,
        Math.round((x + panX.value) / pxPerSec.value),
    ));

    const reset = () => { zoom.value = 1; panX.value = 0; };

    const setContainerWidth = (w) => {
        containerWidth.value = Math.max(800, w - 2);
    };

    // Pan: returns a move handler bound to the start state
    let panMoveHandler = null;
    const startPan = (clientX) => {
        const startPanX = panX.value;
        const startClientX = clientX;
        panMoveHandler = (currentX) => {
            const dx = currentX - startClientX;
            panX.value = Math.max(0, Math.min(maxPan.value, startPanX - dx));
        };
        return panMoveHandler;
    };
    const stopPan = () => { panMoveHandler = null; };
    const isPanning = () => panMoveHandler !== null;

    // Zoom anchored at a local X (relative to timeline area, after subtracting leftWidth)
    const zoomAt = (localX, deltaY) => {
        if (localX < 0) return;
        const timeAtCursor = (localX + panX.value) / pxPerSec.value;
        const factor = deltaY < 0 ? 1.2 : 1 / 1.2;
        const next = Math.max(1, Math.min(8, zoom.value * factor));
        zoom.value = next;
        const newPxPerSec = basePxPerSec.value * next;
        const newContentWidth = fightDuration.value * newPxPerSec;
        const desiredPan = timeAtCursor * newPxPerSec - localX;
        const newMaxPan = Math.max(0, newContentWidth - viewportWidth.value);
        panX.value = Math.max(0, Math.min(newMaxPan, desiredPan));
    };

    return {
        zoom, panX, containerWidth,
        basePxPerSec, pxPerSec, contentWidth, viewportWidth, maxPan,
        timeToX, xToTime,
        reset, setContainerWidth,
        startPan, stopPan, isPanning, zoomAt,
    };
}
