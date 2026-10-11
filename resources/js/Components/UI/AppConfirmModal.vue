<script setup>
import { onMounted, onUnmounted, watch, nextTick, ref } from 'vue';
import { AlertTriangle, Info } from 'lucide-vue-next';
import { useConfirm } from '@/composables/useConfirm';
import AppButton from '@/Components/UI/AppButton.vue';

const confirm = useConfirm();
const cancelButton = ref(null);

const iconColor = {
    danger:  'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400',
    primary: 'bg-primary-100 text-primary-600 dark:bg-primary-900/30 dark:text-primary-400',
};

watch(
    () => confirm.state.show,
    async (open) => {
        if (!open) return;
        await nextTick();
        cancelButton.value?.$el?.focus();
    },
);

const onKeydown = (e) => {
    if (!confirm.state.show) return;
    if (e.key === 'Escape') confirm.cancel();
    if (e.key === 'Enter') confirm.accept();
};

onMounted(() => document.addEventListener('keydown', onKeydown));
onUnmounted(() => document.removeEventListener('keydown', onKeydown));
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="confirm.state.show" class="fixed inset-0 z-[70] flex items-center justify-center p-4" role="alertdialog" aria-modal="true">
                <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="confirm.cancel()" />

                <Transition
                    appear
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="opacity-0 scale-95"
                    enter-to-class="opacity-100 scale-100"
                    leave-active-class="transition duration-150 ease-in"
                    leave-from-class="opacity-100 scale-100"
                    leave-to-class="opacity-0 scale-95"
                >
                    <div class="relative z-10 w-full max-w-sm rounded-2xl bg-white dark:bg-gray-900 p-6 shadow-2xl">
                        <div class="flex flex-col items-center text-center">
                            <div :class="['mb-4 flex h-12 w-12 items-center justify-center rounded-full', iconColor[confirm.state.variant] ?? iconColor.primary]">
                                <AlertTriangle v-if="confirm.state.variant === 'danger'" class="h-6 w-6" />
                                <Info v-else class="h-6 w-6" />
                            </div>
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ confirm.state.title }}</h3>
                            <p v-if="confirm.state.message" class="mt-1.5 text-sm text-gray-500 dark:text-gray-400">{{ confirm.state.message }}</p>
                        </div>

                        <div class="mt-6 flex gap-3">
                            <AppButton ref="cancelButton" variant="secondary" size="md" class="flex-1" @click="confirm.cancel()">
                                {{ confirm.state.cancelLabel }}
                            </AppButton>
                            <AppButton :variant="confirm.state.variant" size="md" class="flex-1" @click="confirm.accept()">
                                {{ confirm.state.confirmLabel }}
                            </AppButton>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
