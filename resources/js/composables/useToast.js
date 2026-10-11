import { reactive } from 'vue';

const DEFAULT_DURATION = 3500;

const state = reactive({ items: [] });

let nextId = 1;

const dismiss = (id) => {
    const index = state.items.findIndex((t) => t.id === id);
    if (index !== -1) state.items.splice(index, 1);
};

const push = (type, message, duration = DEFAULT_DURATION) => {
    const id = nextId++;
    state.items.push({ id, type, message });
    if (duration > 0) setTimeout(() => dismiss(id), duration);
    return id;
};

export const useToast = () => ({
    get items() {
        return state.items;
    },
    dismiss,
    success: (message, duration) => push('success', message, duration),
    error:   (message, duration) => push('error', message, duration ?? 5000),
    info:    (message, duration) => push('info', message, duration),
});
