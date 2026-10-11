<script setup>
import { ref, watch, computed } from 'vue';
import { Eye, EyeOff } from 'lucide-vue-next';
import AppModal from '@/Components/UI/AppModal.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import AppSelect from '@/Components/UI/AppSelect.vue';
import { usePermission } from '@/composables/usePermission';

/**
 * Props:
 * - show: boolean
 * - user: object|null  → null = mode create, object = mode edit
 */
const props = defineProps({
    show: { type: Boolean, default: false },
    user: { type: Object, default: null },
    roles: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'submit']);

// ─── State form ───────────────────────────────────────────────
const form = ref({
    name:                  '',
    username:              '',
    role:                  '',
    password:              '',
    password_confirmation: '',
});

const errors = ref({});
const showPassword = ref(false);
const showConfirm  = ref(false);
const loading      = ref(false);

const isEdit = computed(() => props.user !== null);
const { hasRole } = usePermission();
const canChangeRole = computed(() => !isEdit.value || hasRole('SYSADMIN'));
const title  = computed(() => isEdit.value ? 'Edit User' : 'Tambah User');

const roleLabels = {
    OWNER:    'Owner',
    SYSADMIN: 'Sysadmin',
    ADMIN:    'Admin',
    TECH:     'Tech',
};

// Opsi untuk AppSelect (bentuk { id, label })
const roleOptions = computed(() => props.roles.map((r) => ({ id: r, label: roleLabels[r] ?? r })));

// ─── Populate form saat edit, reset saat tambah ───────────────
watch(
    () => props.show,
    (val) => {
        if (!val) return;
        errors.value = {};
        showPassword.value = false;
        showConfirm.value  = false;

        if (props.user) {
            form.value = {
                name:                  props.user.name  ?? '',
                username:              props.user.username ?? '',
                role:                  props.user.role  ?? '',
                password:              '',
                password_confirmation: '',
            };
        } else {
            form.value = { name: '', username: '', role: '', password: '', password_confirmation: '' };
        }
    },
    { immediate: true },
);

// ─── Validasi sederhana di sisi FE ────────────────────────────
const validate = () => {
    const e = {};
    if (!form.value.name.trim())     e.name = 'Nama wajib diisi.';
    if (!form.value.username.trim()) e.username = 'Username wajib diisi.';
    if (!form.value.role)            e.role = 'Role wajib dipilih.';

    if (!isEdit.value) {
        // Create: password wajib
        if (!form.value.password)     e.password = 'Password wajib diisi.';
        else if (form.value.password.length < 8) e.password = 'Password minimal 8 karakter.';
        if (form.value.password !== form.value.password_confirmation)
            e.password_confirmation = 'Konfirmasi password tidak cocok.';
    } else if (form.value.password) {
        // Edit: password opsional, tapi kalau diisi harus valid
        if (form.value.password.length < 8) e.password = 'Password minimal 8 karakter.';
        if (form.value.password !== form.value.password_confirmation)
            e.password_confirmation = 'Konfirmasi password tidak cocok.';
    }

    errors.value = e;
    return Object.keys(e).length === 0;
};

const handleSubmit = () => {
    if (!validate()) return;
    loading.value = true;

    // Buang password_confirmation + password kosong (saat edit tanpa ubah password)
    const payload = { ...form.value };
    if (isEdit.value && !payload.password) {
        delete payload.password;
        delete payload.password_confirmation;
    }

    emit('submit', payload);
    // Parent yang bertanggung jawab set loading.value = false & menutup modal
};

// Biarkan parent bisa matikan loading dari luar (setelah response API)
defineExpose({ stopLoading: () => { loading.value = false; } });
</script>

<template>
    <AppModal :show="show" :title="title" size="md" @close="$emit('close')">

        <form @submit.prevent="handleSubmit" class="space-y-4" novalidate>

            <!-- Nama Lengkap -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                    Nama Lengkap <span class="text-red-500">*</span>
                </label>
                <input
                    v-model="form.name"
                    type="text"
                    placeholder="Contoh: Budi Santoso"
                    :class="[
                        'w-full rounded-lg border px-3.5 py-2.5 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-800 placeholder:text-gray-400 focus:outline-none focus:ring-2 transition',
                        errors.name
                            ? 'border-red-400 dark:border-red-500 focus:ring-red-400'
                            : 'border-gray-300 dark:border-gray-700 focus:ring-primary-500',
                    ]"
                />
                <p v-if="errors.name" class="mt-1.5 text-xs text-red-500">{{ errors.name }}</p>
            </div>

            <!-- Username -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                    Username <span class="text-red-500">*</span>
                </label>
                <input
                    v-model="form.username"
                    type="text"
                    placeholder="Contoh: budi.s"
                    autocomplete="username"
                    :class="[
                        'w-full rounded-lg border px-3.5 py-2.5 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-800 placeholder:text-gray-400 focus:outline-none focus:ring-2 transition',
                        errors.username
                            ? 'border-red-400 dark:border-red-500 focus:ring-red-400'
                            : 'border-gray-300 dark:border-gray-700 focus:ring-primary-500',
                    ]"
                />
                <p v-if="errors.username" class="mt-1.5 text-xs text-red-500">{{ errors.username }}</p>
            </div>

            <!-- Role -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                    Role <span class="text-red-500">*</span>
                </label>
                <AppSelect
                    v-model="form.role"
                    :options="roleOptions"
                    placeholder="Pilih role..."
                    :error="!!errors.role"
                    :disabled="!canChangeRole"
                />
                <p v-if="!canChangeRole" class="mt-1.5 text-xs text-gray-400">Hanya Sysadmin yang dapat mengubah role.</p>
                <p v-if="errors.role" class="mt-1.5 text-xs text-red-500">{{ errors.role }}</p>
            </div>

            <!-- Divider password -->
            <div class="relative">
                <div class="absolute inset-0 flex items-center">
                    <div class="w-full border-t border-gray-200 dark:border-gray-700" />
                </div>
                <div class="relative flex justify-center">
                    <span class="bg-white dark:bg-gray-900 px-3 text-xs text-gray-400">
                        {{ isEdit ? 'Ubah Password (opsional)' : 'Password' }}
                    </span>
                </div>
            </div>

            <!-- Password -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                    Password <span v-if="!isEdit" class="text-red-500">*</span>
                    <span v-else class="text-xs font-normal text-gray-400">(kosongkan jika tidak ingin mengubah)</span>
                </label>
                <div class="relative">
                    <input
                        v-model="form.password"
                        :type="showPassword ? 'text' : 'password'"
                        placeholder="Min. 8 karakter"
                        autocomplete="new-password"
                        :class="[
                            'w-full rounded-lg border px-3.5 py-2.5 pr-10 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-800 placeholder:text-gray-400 focus:outline-none focus:ring-2 transition',
                            errors.password
                                ? 'border-red-400 dark:border-red-500 focus:ring-red-400'
                                : 'border-gray-300 dark:border-gray-700 focus:ring-primary-500',
                        ]"
                    />
                    <button
                        type="button"
                        tabindex="-1"
                        @click="showPassword = !showPassword"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                    >
                        <EyeOff v-if="showPassword" class="h-4 w-4" />
                        <Eye v-else class="h-4 w-4" />
                    </button>
                </div>
                <p v-if="errors.password" class="mt-1.5 text-xs text-red-500">{{ errors.password }}</p>
            </div>

            <!-- Konfirmasi Password -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                    Konfirmasi Password
                    <span v-if="!isEdit" class="text-red-500">*</span>
                </label>
                <div class="relative">
                    <input
                        v-model="form.password_confirmation"
                        :type="showConfirm ? 'text' : 'password'"
                        placeholder="Ulangi password"
                        autocomplete="new-password"
                        :class="[
                            'w-full rounded-lg border px-3.5 py-2.5 pr-10 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-800 placeholder:text-gray-400 focus:outline-none focus:ring-2 transition',
                            errors.password_confirmation
                                ? 'border-red-400 dark:border-red-500 focus:ring-red-400'
                                : 'border-gray-300 dark:border-gray-700 focus:ring-primary-500',
                        ]"
                    />
                    <button
                        type="button"
                        tabindex="-1"
                        @click="showConfirm = !showConfirm"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                    >
                        <EyeOff v-if="showConfirm" class="h-4 w-4" />
                        <Eye v-else class="h-4 w-4" />
                    </button>
                </div>
                <p v-if="errors.password_confirmation" class="mt-1.5 text-xs text-red-500">{{ errors.password_confirmation }}</p>
            </div>

        </form>

        <!-- Footer -->
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
                {{ isEdit ? 'Simpan Perubahan' : 'Tambah User' }}
            </AppButton>
        </template>

    </AppModal>
</template>
