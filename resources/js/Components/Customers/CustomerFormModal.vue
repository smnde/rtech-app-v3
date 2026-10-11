<script setup>
import { ref, watch, computed } from 'vue';
import AppModal from '@/Components/UI/AppModal.vue';
import AppButton from '@/Components/UI/AppButton.vue';

/**
 * Props:
 * - show: boolean
 * - item: object|null → null = mode create, object = mode edit
 * Field mengikuti kolom tabel `customers`: name, phone, address, is_member.
 * (Email/status VIP di dummy lama tidak ada di DB — dihapus dari form.)
 */
const props = defineProps({
    show: { type: Boolean, default: false },
    item: { type: Object, default: null },
});

const emit = defineEmits(['close', 'submit']);

const emptyForm = () => ({
    name:      '',
    phone:     '',
    address:   '',
    is_member: false,
});

const form    = ref(emptyForm());
const errors  = ref({});
const loading = ref(false);

const isEdit = computed(() => props.item !== null);
const title  = computed(() => isEdit.value ? 'Edit Customer' : 'Tambah Customer');

watch(
    () => props.show,
    (val) => {
        if (!val) return;
        errors.value = {};
        form.value = props.item
            ? {
                name:      props.item.name      ?? '',
                phone:     props.item.phone     ?? '',
                address:   props.item.address   ?? '',
                is_member: Boolean(props.item.is_member),
            }
            : emptyForm();
    },
    { immediate: true },
);

const validate = () => {
    const e = {};
    if (!String(form.value.name).trim())  e.name = 'Nama wajib diisi.';
    if (!String(form.value.phone).trim()) e.phone = 'No. HP wajib diisi.';
    errors.value = e;
    return Object.keys(e).length === 0;
};

const handleSubmit = () => {
    if (!validate()) return;
    loading.value = true;
    emit('submit', {
        ...form.value,
        address: form.value.address || null,
        is_member: form.value.is_member ? 1 : 0,
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
                    Nama Customer <span class="text-red-500">*</span>
                </label>
                <input v-model="form.name" type="text" placeholder="Contoh: Budi Santoso"
                    autofocus :class="inputClass(errors.name)" />
                <p v-if="errors.name" class="mt-1.5 text-xs text-red-500">{{ errors.name }}</p>
            </div>

            <!-- No. HP -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                    No. HP <span class="text-red-500">*</span>
                </label>
                <input v-model="form.phone" type="tel" placeholder="Contoh: 081234567890"
                    :class="inputClass(errors.phone)" />
                <p v-if="errors.phone" class="mt-1.5 text-xs text-red-500">{{ errors.phone }}</p>
            </div>

            <!-- Alamat -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Alamat</label>
                <textarea v-model="form.address" rows="2" placeholder="Alamat customer (opsional)"
                    :class="inputClass(false)"></textarea>
            </div>

            <!-- Member -->
            <label class="flex items-center gap-3 cursor-pointer">
                <input
                    v-model="form.is_member"
                    type="checkbox"
                    class="h-4 w-4 rounded border-gray-300 dark:border-gray-700 text-primary-600 focus:ring-primary-500"
                />
                <span class="text-sm text-gray-700 dark:text-gray-300">Daftarkan sebagai member</span>
            </label>

        </form>

        <template #footer>
            <AppButton variant="secondary" size="sm" @click="$emit('close')">
                Batal
            </AppButton>
            <AppButton variant="primary" size="sm" :loading="loading" @click="handleSubmit">
                {{ isEdit ? 'Simpan Perubahan' : 'Tambah Customer' }}
            </AppButton>
        </template>
    </AppModal>
</template>
