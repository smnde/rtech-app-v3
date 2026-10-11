<script setup>
import { CheckCircle2, XCircle, Info, X } from 'lucide-vue-next';
import { useToast } from '@/composables/useToast';

const toast = useToast();

const iconMap = { success: CheckCircle2, error: XCircle, info: Info };

const styleMap = {
    success: 'border-green-200 dark:border-green-800 text-green-700 dark:text-green-300',
    error:   'border-red-200 dark:border-red-800 text-red-700 dark:text-red-300',
    info:    'border-blue-200 dark:border-blue-800 text-blue-700 dark:text-blue-300',
};
</script>

<template>
    <Teleport to="body">
        <div class="pointer-events-none fixed inset-x-0 top-4 z-[60] flex flex-col items-center gap-2 px-4 sm:inset-x-auto sm:right-4 sm:items-end">
            <TransitionGroup
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0 -translate-y-2 sm:translate-x-4 sm:translate-y-0"
                enter-to-class="opacity-100 translate-y-0 sm:translate-x-0"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-for="item in toast.items"
                    :key="item.id"
                    role="status"
                    :class="[
                        'pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-xl border bg-white dark:bg-gray-900 px-4 py-3 shadow-lg',
                        styleMap[item.type],
                    ]"
                >
                    <component :is="iconMap[item.type]" class="mt-0.5 h-5 w-5 shrink-0" />
                    <p class="flex-1 text-sm font-medium text-gray-800 dark:text-gray-100">{{ item.message }}</p>
                    <button
                        type="button"
                        class="shrink-0 rounded p-0.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                        @click="toast.dismiss(item.id)"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>
            </TransitionGroup>
        </div>
    </Teleport>
</template>
