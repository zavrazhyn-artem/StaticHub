import { onMounted, onUnmounted, ref } from 'vue';

const DODGE_MARGIN = 30;
const DODGE_EVENT = 'bossplanner-drag-pointer';

export function useDodgePanel({ elementRef, position, enabled = () => true }) {
    const isDodging = ref(false);
    let dodgeTimer = null;

    const handleDragPointer = (e) => {
        if (!enabled()) return;
        const el = elementRef.value;
        if (!el) return;
        const rect = el.getBoundingClientRect();
        if (!rect.width || !rect.height) return;

        const { x, y, dx = 0, dy = 0 } = e.detail;
        const hit = x >= rect.left - DODGE_MARGIN && x <= rect.right + DODGE_MARGIN &&
                    y >= rect.top - DODGE_MARGIN && y <= rect.bottom + DODGE_MARGIN;
        if (!hit) return;

        const W = rect.width, H = rect.height;
        const cx = rect.left + W / 2, cy = rect.top + H / 2;

        let nx, ny;
        const mag = Math.hypot(dx, dy);
        if (mag > 0.5) {
            nx = dx / mag;
            ny = dy / mag;
        } else {
            const vx = cx - x, vy = cy - y;
            const vm = Math.hypot(vx, vy) || 1;
            nx = vx / vm;
            ny = vy / vm;
        }

        const pushDist = Math.max(W, H) + 60;
        const vw = window.innerWidth, vh = window.innerHeight;
        const clamp = (px, py) => ({
            x: Math.max(10, Math.min(vw - W - 10, px)),
            y: Math.max(10, Math.min(vh - H - 10, py)),
        });

        let next = clamp(position.value.x + nx * pushDist, position.value.y + ny * pushDist);
        const stillHit = x >= next.x - DODGE_MARGIN && x <= next.x + W + DODGE_MARGIN &&
                         y >= next.y - DODGE_MARGIN && y <= next.y + H + DODGE_MARGIN;
        if (stillHit) {
            next = clamp(position.value.x - ny * pushDist, position.value.y + nx * pushDist);
        }

        position.value = next;
        isDodging.value = true;
        clearTimeout(dodgeTimer);
        dodgeTimer = setTimeout(() => { isDodging.value = false; }, 350);
    };

    onMounted(() => window.addEventListener(DODGE_EVENT, handleDragPointer));
    onUnmounted(() => {
        window.removeEventListener(DODGE_EVENT, handleDragPointer);
        clearTimeout(dodgeTimer);
    });

    return { isDodging };
}
