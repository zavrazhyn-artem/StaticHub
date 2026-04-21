/**
 * Custom mouse-based drag-n-drop for Boss Planner icons.
 *
 * Native HTML5 drag-n-drop breaks in our layout — fixed-position Teleport
 * panels lose dataTransfer payloads when dropped onto SVG zones, and the
 * browser's default drag ghost is unreliable across browsers. This
 * composable bypasses the native API:
 *   - On `mousedown`, create a floating ghost `div` pinned to the cursor.
 *   - Track mouse movement globally so the ghost follows wherever the user
 *     moves, including across SVG/fixed/Teleport boundaries.
 *   - On `mouseup`, dispatch a `custom-drop` window event with the payload
 *     and the element under the cursor. Drop zones register a listener.
 *   - Emit `custom-drag-cancel` if the user presses Escape mid-drag.
 */
// Plain module-scoped state — no Vue reactivity needed, the payload flows
// through the custom-drop event detail. Keeping it non-reactive avoids
// cascading re-renders on drag that would spam the console.
const state = {
    payload: null,
    ghost: null,
    moveHandler: null,
    upHandler: null,
    keyHandler: null,
};

function cleanup() {
    if (state.ghost) {
        state.ghost.remove();
        state.ghost = null;
    }
    if (state.moveHandler) {
        document.removeEventListener('mousemove', state.moveHandler);
        state.moveHandler = null;
    }
    if (state.upHandler) {
        document.removeEventListener('mouseup', state.upHandler);
        state.upHandler = null;
    }
    if (state.keyHandler) {
        document.removeEventListener('keydown', state.keyHandler);
        state.keyHandler = null;
    }
}

/**
 * Start a custom drag. Call from a `@mousedown` handler.
 *
 * @param {MouseEvent} e
 * @param {Object} data    Arbitrary payload that drop targets will receive.
 * @param {String} iconSrc URL of the icon to show as the ghost under the cursor.
 */
export function startCustomDrag(e, data, iconSrc) {
    if (e.button !== 0) return; // left mouse only
    e.preventDefault();
    e.stopPropagation();
    cleanup(); // defensive: drop any stale state
    state.payload = data;

    // Circular ghost so it matches the rest of the Boss Planner visual
    // language (class avatars, marker bubbles). Thin orange ring keeps it
    // distinguishable from the browser's default drag preview.
    const ghost = document.createElement('div');
    ghost.setAttribute('data-custom-drag-ghost', '1');
    ghost.style.cssText = [
        'position: fixed',
        `top: ${e.clientY - 22}px`,
        `left: ${e.clientX - 22}px`,
        'width: 44px',
        'height: 44px',
        'pointer-events: none',
        'z-index: 2147483647',
        'border-radius: 9999px',
        'box-shadow: 0 0 0 2px #F59E0B, 0 6px 20px rgba(0,0,0,0.7)',
        'opacity: 0.95',
        'background-color: #0f0f12',
        iconSrc ? `background-image: url("${iconSrc}")` : '',
        'background-size: cover',
        'background-position: center',
    ].filter(Boolean).join('; ');
    document.body.appendChild(ghost);
    state.ghost = ghost;

    const move = (ev) => {
        ghost.style.left = (ev.clientX - 20) + 'px';
        ghost.style.top = (ev.clientY - 20) + 'px';
    };
    const up = (ev) => {
        const target = document.elementFromPoint(ev.clientX, ev.clientY);
        const payload = state.payload;
        cleanup();
        state.payload = null;
        if (payload) {
            window.dispatchEvent(new CustomEvent('custom-drop', {
                detail: { payload, target, clientX: ev.clientX, clientY: ev.clientY },
            }));
        }
    };
    const key = (ev) => {
        if (ev.key === 'Escape') {
            cleanup();
            state.payload = null;
            window.dispatchEvent(new CustomEvent('custom-drag-cancel'));
        }
    };
    state.moveHandler = move;
    state.upHandler = up;
    state.keyHandler = key;
    document.addEventListener('mousemove', move);
    document.addEventListener('mouseup', up);
    document.addEventListener('keydown', key);
}

