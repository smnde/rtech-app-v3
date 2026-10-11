<script setup>
import { ref, computed, watch } from 'vue';
import AppSelect from '@/Components/UI/AppSelect.vue';
import AppButton from '@/Components/UI/AppButton.vue';

const props = defineProps({
    specification: { type: Object, default: () => ({}) },
    users: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    processing: { type: Boolean, default: false },
});

const emit = defineEmits(['submit', 'cancel']);

const TYPES = [
    { value: 'upgrade', label: 'Upgrade', desc: 'Perubahan spesifikasi' },
    { value: 'repair', label: 'Perbaikan', desc: 'Solved kendala / perbaikan' },
    { value: 'note', label: 'Catatan', desc: 'Catatan bebas' },
];

const SPEC_FIELDS = [
    { key: 'cpu', label: 'CPU' },
    { key: 'gpu', label: 'GPU' },
    { key: 'memory', label: 'Memory' },
    { key: 'storage', label: 'Storage' },
    { key: 'display', label: 'Display' },
];

const type = ref('upgrade');
const description = ref('');
const assignedTo = ref('');
const statusAfter = ref('');

const currentSpec = Object.fromEntries(SPEC_FIELDS.map(({ key }) => [key, props.specification?.[key] ?? '']));

const spec = ref({ ...currentSpec });

watch(type, () => {
    statusAfter.value = '';
});

const userOptions = computed(() => [
    { id: '', label: 'Pilih Teknisi (opsional)' },
    ...props.users.map((user) => ({ id: user.id, label: user.name })),
]);

const isChanged = (key) => spec.value[key] && spec.value[key] !== currentSpec[key];

const isValid = computed(() => {
    if (!description.value.trim()) return false;
    if (type.value === 'repair' && !statusAfter.value) return false;
    return true;
});

const submit = () => {
    emit('submit', {
        type: type.value,
        description: description.value,
        assigned_to: assignedTo.value || null,
        status_after: type.value === 'repair' ? statusAfter.value : null,
        ...(type.value === 'upgrade' ? spec.value : {}),
    });
};

const inputClass = 'w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 placeholder-gray-400 transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder-gray-500';
const labelClass = 'mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400';
</script>

<template>
    <div class="space-y-4">
        <div>
            <label :class="labelClass">Tipe Log</label>
            <div class="grid grid-cols-3 gap-2">
                <button v-for="option in TYPES" :key="option.value" type="button"
                    :class="[
                        'rounded-lg border px-3 py-2.5 text-left transition-colors',
                        type === option.value
                            ? 'border-indigo-500 bg-indigo-600 text-white'
                            : 'border-gray-200 bg-white text-gray-700 hover:border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-600',
                    ]"
                    @click="type = option.value">
                    <p class="text-xs font-semibold">{{ option.label }}</p>
                    <p class="mt-0.5 text-[10px] opacity-70">{{ option.desc }}</p>
                </button>
            </div>
        </div>

        <div>
            <label :class="labelClass">Deskripsi *</label>
            <textarea v-model="description" rows="3" maxlength="1000" :class="inputClass" class="resize-none"
                :placeholder="type === 'upgrade'
                    ? 'Contoh: RAM diupgrade dari 8GB ke 16GB DDR4'
                    : type === 'repair'
                    ? 'Contoh: Layar berhasil diganti, laptop kembali normal'
                    : 'Contoh: Laptop sudah dibersihkan dari debu'" />
        </div>

        <div v-if="type === 'upgrade'">
            <label :class="labelClass">Spesifikasi Baru</label>
            <p class="mb-2 text-xs text-gray-400 dark:text-gray-500">Isi hanya yang berubah. Kosongkan kalau tidak ada perubahan.</p>
            <div class="grid grid-cols-2 gap-2">
                <div v-for="field in SPEC_FIELDS" :key="field.key">
                    <label class="mb-1 block text-[10px] font-medium uppercase text-gray-500 dark:text-gray-500">{{ field.label }}</label>
                    <div class="relative">
                        <input v-model="spec[field.key]" type="text" maxlength="255"
                            :placeholder="currentSpec[field.key] || field.label"
                            :class="[
                                inputClass,
                                isChanged(field.key) ? 'border-amber-400 bg-amber-50/50 dark:border-amber-500 dark:bg-amber-900/10' : '',
                            ]" />
                        <span v-if="isChanged(field.key)"
                            class="absolute right-2 top-1/2 -translate-y-1/2 text-[10px] font-medium text-amber-500">
                            berubah
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="type === 'repair'">
            <label :class="labelClass">Status Setelah Perbaikan *</label>
            <div class="flex flex-wrap gap-2">
                <button v-for="status in statuses" :key="status" type="button"
                    :class="[
                        'rounded-lg border px-3 py-1.5 text-xs font-medium transition-colors',
                        statusAfter === status
                            ? 'border-indigo-500 bg-indigo-600 text-white'
                            : 'border-gray-200 bg-white text-gray-600 hover:border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400',
                    ]"
                    @click="statusAfter = status">
                    {{ status }}
                </button>
            </div>
        </div>

        <div>
            <label :class="labelClass">Dikerjakan Oleh</label>
            <AppSelect v-model="assignedTo" :options="userOptions" placeholder="Pilih Teknisi (opsional)" />
        </div>

        <div class="flex gap-3 pt-1">
            <AppButton variant="secondary" class="flex-1" @click="emit('cancel')">Batal</AppButton>
            <AppButton class="flex-1" :disabled="!isValid" :loading="processing" @click="submit">Simpan Log</AppButton>
        </div>
    </div>
</template>
