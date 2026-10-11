<script setup>
defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    /** Perubahan dari periode sebelumnya (misal "+12%") */
    change: { type: String, default: null },
    /** Apakah change positif atau negatif */
    changePositive: { type: Boolean, default: true },
    /** Lucide icon component */
    icon: { type: Object, default: null },
    /** Warna background icon */
    iconColor: {
        type: String,
        default: 'blue',
        validator: (v) => ['blue', 'green', 'yellow', 'red', 'purple', 'orange'].includes(v),
    },
});

const iconBgClasses = {
    blue:   'bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400',
    green:  'bg-green-100 text-green-600 dark:bg-green-900/30 dark:text-green-400',
    yellow: 'bg-yellow-100 text-yellow-600 dark:bg-yellow-900/30 dark:text-yellow-400',
    red:    'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400',
    purple: 'bg-purple-100 text-purple-600 dark:bg-purple-900/30 dark:text-purple-400',
    orange: 'bg-orange-100 text-orange-600 dark:bg-orange-900/30 dark:text-orange-400',
};
</script>

<template>
    <div class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5 shadow-sm">
        <div class="flex items-start justify-between gap-4">
            <div class="flex-1 min-w-0">
                <p class="text-sm text-gray-500 dark:text-gray-400 font-medium">{{ label }}</p>
                <p class="mt-1.5 text-2xl font-bold text-gray-900 dark:text-white tracking-tight">{{ value }}</p>
                <p v-if="change" class="mt-1 text-xs font-medium" :class="changePositive ? 'text-green-600 dark:text-green-400' : 'text-red-500 dark:text-red-400'">
                    {{ changePositive ? '↑' : '↓' }} {{ change }} dari bulan lalu
                </p>
            </div>
            <div v-if="icon" :class="['flex h-11 w-11 shrink-0 items-center justify-center rounded-xl', iconBgClasses[iconColor]]">
                <component :is="icon" class="h-6 w-6" />
            </div>
        </div>
    </div>
</template>
