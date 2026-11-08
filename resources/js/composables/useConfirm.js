import { reactive } from 'vue';

const state = reactive({
    show: false,
    title: '',
    message: '',
    confirmLabel: 'Ya',
    cancelLabel: 'Batal',
    variant: 'danger',
    requirePassword: false,
    password: '',
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
    setPassword: (value) => { state.password = value; },
    confirm: ({
        title = 'Konfirmasi',
        message = '',
        confirmLabel = 'Ya',
        cancelLabel = 'Batal',
        variant = 'danger',
        requirePassword = false,
    } = {}) => {
        state.resolver?.(false);
        Object.assign(state, {
            show: true,
            title,
            message,
            confirmLabel,
            cancelLabel,
            variant,
            requirePassword,
            password: '',
        });
        return new Promise((resolve) => {
            state.resolver = resolve;
        });
    },
});
