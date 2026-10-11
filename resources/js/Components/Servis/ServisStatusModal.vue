<script setup>
import { computed, ref, watch } from 'vue';
import AppModal from '@/Components/UI/AppModal.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import AppDropdown from '@/Components/UI/AppDropdown.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    reparation: { type: Object, required: true },
    statuses: { type: Array, default: () => [] },
    saving: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'submit']);

const form = ref({ status: '', note: '' });

watch(
    () => props.show,
    (val) => {
        if (!val) return;
        form.value = { status: props.reparation.status, note: '' };
    },
    { immediate: true },
);

const statusOptions = computed(() => props.statuses.map((status) => ({ id: status, label: status })));

const isUnchanged = computed(() => form.value.status === props.reparation.status);

const handleSubmit = () => {
    emit('submit', { status: form.value.status, note: form.value.note || null });
};

const labelClass = 'block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5';
const textareaClass = 'w-full rounded-lg border border-gray-300 dark:border-gray-700 px-3.5 py-2.5 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-primary-500 transition';
</script>

<template>
    <AppModal :show="show" title="Ubah Status" size="sm" @close="$emit('close')">
        <form class="space-y-4" novalidate @submit.prevent="handleSubmit">
            <div>
                <label :class="labelClass">Status</label>
                <AppDropdown v-model="form.status" :options="statusOptions" />
            </div>

            <div>
                <label :class="labelClass">Catatan (opsional)</label>
                <textarea v-model="form.note" rows="3" maxlength="500" :class="textareaClass"></textarea>
            </div>
        </form>

        <template #footer>
            <AppButton variant="secondary" size="sm" @click="$emit('close')">Batal</AppButton>
            <AppButton variant="primary" size="sm" :loading="saving" :disabled="isUnchanged" @click="handleSubmit">
                Simpan Status
            </AppButton>
        </template>
    </AppModal>
</template>
