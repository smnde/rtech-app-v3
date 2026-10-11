<script setup>
import { ref, watch, computed } from 'vue';
import AppModal from '@/Components/UI/AppModal.vue';
import AppButton from '@/Components/UI/AppButton.vue';

const props = defineProps({
    show:     { type: Boolean, default: false },
    /** null = mode create, object = mode edit */
    category: { type: Object, default: null },
});

const emit = defineEmits(['close', 'submit']);

const form    = ref({ name: '' });
const errors  = ref({});
const loading = ref(false);

const isEdit = computed(() => props.category !== null);
const title  = computed(() => isEdit.value ? 'Edit Kategori' : 'Tambah Kategori');

// Reset / populate saat modal dibuka
watch(
    () => props.show,
    (val) => {
        if (!val) return;
        errors.value  = {};
        form.value    = { name: props.category?.name ?? '' };
    },
    { immediate: true },
);

const validate = () => {
    const e = {};
    if (!form.value.name.trim()) e.name = 'Nama kategori wajib diisi.';
    errors.value = e;
    return Object.keys(e).length === 0;
};

const handleSubmit = () => {
    if (!validate()) return;
    loading.value = true;
    emit('submit', { ...form.value });
};

defineExpose({ stopLoading: () => { loading.value = false; } });
</script>

<template>
    <AppModal :show="show" :title="title" size="sm" @close="$emit('close')">

        <form @submit.prevent="handleSubmit" class="space-y-4" novalidate>

            <!-- Nama Kategori -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                    Nama Kategori <span class="text-red-500">*</span>
                </label>
                <input
                    v-model="form.name"
                    type="text"
                    placeholder="Contoh: Elektronik"
                    autofocus
                    :class="[
                        'w-full rounded-lg border px-3.5 py-2.5 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-800 placeholder:text-gray-400 focus:outline-none focus:ring-2 transition',
                        errors.name
                            ? 'border-red-400 dark:border-red-500 focus:ring-red-400'
                            : 'border-gray-300 dark:border-gray-700 focus:ring-primary-500',
                    ]"
                    @keydown.enter.prevent="handleSubmit"
                />
                <p v-if="errors.name" class="mt-1.5 text-xs text-red-500">{{ errors.name }}</p>
            </div>

        </form>

        <template #footer>
            <AppButton variant="secondary" size="sm" @click="$emit('close')">
                Batal
            </AppButton>
            <AppButton
                variant="primary"
                size="sm"
                :loading="loading"
                @click="handleSubmit"
            >
                {{ isEdit ? 'Simpan Perubahan' : 'Tambah Kategori' }}
            </AppButton>
        </template>

    </AppModal>
</template>
