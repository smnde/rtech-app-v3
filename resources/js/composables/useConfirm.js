import { reactive } from 'vue';

const state = reactive({
    show: false,
    title: '',
    message: '',
    confirmLabel: 'Ya',
    cancelLabel: 'Batal',
    variant: 'danger',
    resolver: null,
});

const settle = (result) => {
    const resolver = state.resolver;
    state.show = false;
    state.resolver = null;
    resolver?.(result);
};

export const useConfirm = () => ({
    state,
    accept: () => settle(true),
    cancel: () => settle(false),
    confirm: ({
        title = 'Konfirmasi',
        message = '',
        confirmLabel = 'Ya',
        cancelLabel = 'Batal',
        variant = 'danger',
    } = {}) => {
        state.resolver?.(false);
        Object.assign(state, { show: true, title, message, confirmLabel, cancelLabel, variant });
        return new Promise((resolve) => {
            state.resolver = resolve;
        });
    },
});
