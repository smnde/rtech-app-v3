<script setup>
import { ref } from 'vue';
import AppModal from '@/Components/UI/AppModal.vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: 'Aksi',
    },
    item: {
        type: Object,
        default: null,
    },
    actions: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['close', 'action-click']);

const modalRef = ref(null);

const handleActionClick = (action) => {
    if (action.onClick) {
        action.onClick();
    }
    emit('action-click', action);
};

const handleClose = () => {
    emit('close');
};

defineExpose({});
</script>

<template>
    <AppModal
        ref="modalRef"
        :show="show"
        :title="title"
        size="sm"
        @close="handleClose"
    >
        <div class="space-y-2" v-if="item">
            <template v-for="(action, index) in actions" :key="index">
                <hr v-if="action.divider" class="my-1 border-gray-200 dark:border-gray-700" />
                <button
                    v-else
                    type="button"
                    :class="[
                        'flex w-full items-center gap-3 px-4 py-3 text-left text-sm rounded-xl transition-colors',
                        action.class || 'text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800/60',
                    ]"
                    :disabled="action.disabled"
                    @click="handleActionClick(action)"
                >
                    <component v-if="action.icon" :is="action.icon" class="h-5 w-5 text-gray-400 shrink-0" />
                    {{ action.label }}
                </button>
            </template>
        </div>
        <div v-else class="text-center py-8 text-gray-500 dark:text-gray-400">
            Memuat aksi...
        </div>
    </AppModal>
</template>