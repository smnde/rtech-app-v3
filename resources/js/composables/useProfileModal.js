import { ref } from 'vue';

const show = ref(false);

export const useProfileModal = () => ({
    show,
    open: () => { show.value = true; },
    close: () => { show.value = false; },
});
