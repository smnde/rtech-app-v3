<script setup>
import { ref, watch, computed } from 'vue';
import AppModal from '@/Components/UI/AppModal.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import AppCurrencyInput from '@/Components/UI/AppCurrencyInput.vue';
import AppDatePicker from '@/Components/UI/AppDatePicker.vue';

const props = defineProps({
    show:  { type: Boolean, default: false },
    /** null = mode create, object = mode edit */
    promo: { type: Object, default: null },
    /** Daftar jenis barang yang boleh ikut promo, dari backend. */
    itemTypeOptions: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'submit']);

const typeOptions = [
    { value: 'nominal', label: 'Nominal (Rp)' },
    { value: 'percent', label: 'Persen (%)' },
];

const typeLabels = {
    laptop: 'Laptop',
    gadget: 'Gadget',
    accessory: 'Aksesoris',
    sparepart: 'Sparepart',
};

const errors  = ref({});
const loading = ref(false);

const form = ref({
    title: '',
    type: 'nominal',
    value: '',
    starts_at: '',
    ends_at: '',
    is_active: true,
    item_types: [],
});

const isEdit = computed(() => props.promo !== null);
const title  = computed(() => isEdit.value ? 'Edit Promo' : 'Tambah Promo');
const isPercent = computed(() => form.value.type === 'percent');

watch(
    () => props.show,
    (val) => {
        if (!val) return;
        errors.value = {};
        form.value = {
            title: props.promo?.title ?? '',
            type: props.promo?.type ?? 'nominal',
            value: props.promo?.value ?? '',
            starts_at: props.promo?.starts_at ?? '',
            ends_at: props.promo?.ends_at ?? '',
            is_active: props.promo?.is_active ?? true,
            item_types: [...(props.promo?.item_types ?? [])],
        };
    },
    { immediate: true },
);

const toggleItemType = (type) => {
    const index = form.value.item_types.indexOf(type);
    if (index === -1) {
        form.value.item_types.push(type);
    } else {
        form.value.item_types.splice(index, 1);
    }
};

const validate = () => {
    const e = {};

    if (!form.value.title.trim()) e.title = 'Judul promo wajib diisi.';
    if (form.value.value === '' || Number(form.value.value) <= 0) e.value = 'Nilai promo wajib diisi.';
    if (isPercent.value && Number(form.value.value) > 100) e.value = 'Persen promo tidak boleh lebih dari 100%.';
    if (!form.value.starts_at) e.starts_at = 'Tanggal mulai wajib diisi.';
    if (!form.value.ends_at) e.ends_at = 'Tanggal selesai wajib diisi.';
    if (form.value.starts_at && form.value.ends_at && form.value.ends_at < form.value.starts_at) {
        e.ends_at = 'Tanggal selesai tidak boleh sebelum tanggal mulai.';
    }
    if (form.value.item_types.length === 0) e.item_types = 'Pilih minimal satu jenis barang.';

    errors.value = e;
    return Object.keys(e).length === 0;
};

const handleSubmit = () => {
    if (!validate()) return;
    loading.value = true;
    emit('submit', { ...form.value, value: Number(form.value.value) });
};

defineExpose({ stopLoading: () => { loading.value = false; } });
</script>

<template>
    <AppModal :show="show" :title="title" size="md" @close="$emit('close')">
        <form @submit.prevent="handleSubmit" class="space-y-4" novalidate>

            <!-- Judul -->
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Judul Promo <span class="text-red-500">*</span>
                </label>
                <input
                    v-model="form.title"
                    type="text"
                    placeholder="Contoh: Diskon 11.11"
                    :class="[
                        'w-full rounded-lg border bg-white px-3.5 py-2.5 text-sm text-gray-900 transition placeholder:text-gray-400 focus:outline-none focus:ring-2 dark:bg-gray-800 dark:text-white',
                        errors.title ? 'border-red-400 focus:ring-red-400 dark:border-red-500' : 'border-gray-300 focus:ring-primary-500 dark:border-gray-700',
                    ]"
                />
                <p v-if="errors.title" class="mt-1.5 text-xs text-red-500">{{ errors.title }}</p>
            </div>

            <!-- Jenis potongan -->
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Jenis Potongan <span class="text-red-500">*</span>
                </label>
                <div class="flex gap-2">
                    <label
                        v-for="opt in typeOptions"
                        :key="opt.value"
                        :class="[
                            'flex flex-1 cursor-pointer items-center justify-center rounded-lg border px-3 py-2.5 text-sm font-medium transition',
                            form.type === opt.value
                                ? 'border-primary-500 bg-primary-50 text-primary-700 dark:border-primary-500 dark:bg-primary-900/20 dark:text-primary-400'
                                : 'border-gray-300 text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-800',
                        ]"
                    >
                        <input v-model="form.type" type="radio" :value="opt.value" class="sr-only" />
                        {{ opt.label }}
                    </label>
                </div>
            </div>

            <!-- Nilai -->
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                    {{ isPercent ? 'Persen per Unit' : 'Nominal per Unit' }} <span class="text-red-500">*</span>
                </label>
                <AppCurrencyInput v-if="!isPercent" v-model="form.value" :error="!!errors.value" placeholder="0" />
                <input
                    v-else
                    v-model="form.value"
                    type="number"
                    min="0"
                    max="100"
                    placeholder="0"
                    :class="[
                        'w-full rounded-lg border bg-white px-3.5 py-2.5 text-sm text-gray-900 transition placeholder:text-gray-400 focus:outline-none focus:ring-2 dark:bg-gray-800 dark:text-white',
                        errors.value ? 'border-red-400 focus:ring-red-400 dark:border-red-500' : 'border-gray-300 focus:ring-primary-500 dark:border-gray-700',
                    ]"
                />
                <p v-if="errors.value" class="mt-1.5 text-xs text-red-500">{{ errors.value }}</p>
                <p v-else class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">
                    Potongan berlaku untuk setiap unit yang ikut promo.
                </p>
            </div>

            <!-- Rentang tanggal -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Mulai <span class="text-red-500">*</span>
                    </label>
                    <AppDatePicker v-model="form.starts_at" :error="!!errors.starts_at" placeholder="Pilih tanggal" />
                    <p v-if="errors.starts_at" class="mt-1.5 text-xs text-red-500">{{ errors.starts_at }}</p>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Selesai <span class="text-red-500">*</span>
                    </label>
                    <AppDatePicker v-model="form.ends_at" :error="!!errors.ends_at" placeholder="Pilih tanggal" />
                    <p v-if="errors.ends_at" class="mt-1.5 text-xs text-red-500">{{ errors.ends_at }}</p>
                </div>
            </div>

            <!-- Jenis barang -->
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Berlaku Untuk <span class="text-red-500">*</span>
                </label>
                <div class="flex flex-wrap gap-2">
                    <label
                        v-for="type in itemTypeOptions"
                        :key="type"
                        :class="[
                            'cursor-pointer rounded-lg border px-3 py-1.5 text-sm font-medium transition',
                            form.item_types.includes(type)
                                ? 'border-primary-500 bg-primary-50 text-primary-700 dark:border-primary-500 dark:bg-primary-900/20 dark:text-primary-400'
                                : 'border-gray-300 text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-800',
                        ]"
                    >
                        <input
                            type="checkbox"
                            class="sr-only"
                            :checked="form.item_types.includes(type)"
                            @change="toggleItemType(type)"
                        />
                        {{ typeLabels[type] ?? type }}
                    </label>
                </div>
                <p v-if="errors.item_types" class="mt-1.5 text-xs text-red-500">{{ errors.item_types }}</p>
            </div>

            <!-- Status -->
            <label class="flex cursor-pointer items-center gap-2.5">
                <input
                    v-model="form.is_active"
                    type="checkbox"
                    class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900"
                />
                <span class="text-sm text-gray-700 dark:text-gray-300">Promo aktif</span>
            </label>

        </form>

        <template #footer>
            <AppButton variant="secondary" size="sm" @click="$emit('close')">Batal</AppButton>
            <AppButton variant="primary" size="sm" :loading="loading" @click="handleSubmit">
                {{ isEdit ? 'Simpan Perubahan' : 'Tambah Promo' }}
            </AppButton>
        </template>
    </AppModal>
</template>
