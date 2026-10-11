<script setup>
import { ref, watch, computed } from 'vue';
import AppModal from '@/Components/UI/AppModal.vue';
import AppButton from '@/Components/UI/AppButton.vue';

/**
 * Props:
 * - show: boolean
 * - item: object|null → null = mode create, object = mode edit
 * Field mengikuti kolom tabel `suppliers`: name, phone, description.
 */
const props = defineProps({
    show: { type: Boolean, default: false },
    item: { type: Object, default: null },
});

const emit = defineEmits(['close', 'submit']);

const emptyForm = () => ({
    name:        '',
    phone:       '',
    description: '',
});

const form    = ref(emptyForm());
const errors  = ref({});
const loading = ref(false);

const isEdit = computed(() => props.item !== null);
const title  = computed(() => isEdit.value ? 'Edit Supplier' : 'Tambah Supplier');

watch(
    () => props.show,
    (val) => {
        if (!val) return;
        errors.value = {};
        form.value = props.item
            ? {
                name:        props.item.name        ?? '',
                phone:       props.item.phone       ?? '',
                description: props.item.description ?? '',
            }
            : emptyForm();
    },
    { immediate: true },
);

const validate = () => {
    const e = {};
    if (!String(form.value.name).trim()) e.name = 'Nama supplier wajib diisi.';
    errors.value = e;
    return Object.keys(e).length === 0;
};

const handleSubmit = () => {
    if (!validate()) return;
    loading.value = true;
    emit('submit', {
        ...form.value,
        phone:       form.value.phone || null,
        description: form.value.description || null,
    });
};

defineExpose({ stopLoading: () => { loading.value = false; } });

const inputClass = (hasError) => [
    'w-full rounded-lg border px-3.5 py-2.5 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-800 placeholder:text-gray-400 focus:outline-none focus:ring-2 transition',
    hasError
        ? 'border-red-400 dark:border-red-500 focus:ring-red-400'
        : 'border-gray-300 dark:border-gray-700 focus:ring-primary-500',
];
</script>

<template>
    <AppModal :show="show" :title="title" size="md" @close="$emit('close')">
        <form @submit.prevent="handleSubmit" class="space-y-4" novalidate>

            <!-- Nama -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                    Nama Supplier <span class="text-red-500">*</span>
                </label>
                <input v-model="form.name" type="text" placeholder="Contoh: PT Sumber Elektronik"
                    autofocus :class="inputClass(errors.name)" />
                <p v-if="errors.name" class="mt-1.5 text-xs text-red-500">{{ errors.name }}</p>
            </div>

            <!-- No. Telepon -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">No. Telepon</label>
                <input v-model="form.phone" type="tel" placeholder="Contoh: 0215550112"
                    :class="inputClass(false)" />
            </div>

            <!-- Keterangan -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Keterangan</label>
                <textarea v-model="form.description" rows="2" placeholder="Catatan supplier (opsional)"
                    :class="inputClass(false)"></textarea>
            </div>

        </form>

        <template #footer>
            <AppButton variant="secondary" size="sm" @click="$emit('close')">
                Batal
            </AppButton>
            <AppButton variant="primary" size="sm" :loading="loading" @click="handleSubmit">
                {{ isEdit ? 'Simpan Perubahan' : 'Tambah Supplier' }}
            </AppButton>
        </template>
    </AppModal>
</template>
