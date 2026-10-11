<script setup>
import { ref, computed, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppPageHeader from '@/Components/UI/AppPageHeader.vue';
import AppCard from '@/Components/UI/AppCard.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import AppBadge from '@/Components/UI/AppBadge.vue';
import { useToast } from '@/composables/useToast';

const props = defineProps({
    roles:   { type: Array, required: true },
    modules: { type: Array, required: true },
    actions: { type: Array, required: true },
});

const toast = useToast();
const page  = usePage();

watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error)   toast.error(flash.error);
    },
    { deep: true, immediate: true },
);

const roleLabels = { OWNER: 'Owner', SYSADMIN: 'Sysadmin', ADMIN: 'Admin', TECH: 'Teknisi' };
const roleColor  = { OWNER: 'purple', SYSADMIN: 'red', ADMIN: 'blue', TECH: 'orange' };

const actionLabels = { show: 'Lihat', add: 'Tambah', edit: 'Edit', delete: 'Hapus' };
const moduleLabels = {
    user: 'User',
    customer: 'Customer',
    supplier: 'Supplier',
    reparation: 'Servis',
    purchase: 'Pembelian',
    sale: 'Penjualan',
    report: 'Laporan',
    brand: 'Brand',
    category: 'Kategori',
    laptop: 'Laptop',
    gadget: 'Gadget',
    aksesoris: 'Aksesoris',
    sparepart: 'Sparepart',
};

const activeRoleId = ref(props.roles[0]?.id ?? null);
const activeRole = computed(() => props.roles.find((role) => role.id === activeRoleId.value));

const draft = ref({});
const saving = ref({});

watch(
    () => props.roles,
    (roles) => {
        draft.value = Object.fromEntries(roles.map((role) => [role.id, new Set(role.permissions)]));
    },
    { immediate: true },
);

const permissionName = (module, action) => `${action} ${module}`;

const has = (roleId, module, action) => draft.value[roleId]?.has(permissionName(module, action));

const setLevel = (roleId, module, action, enabled) => {
    const set = draft.value[roleId];
    const name = permissionName(module, action);
    enabled ? set.add(name) : set.delete(name);
};

const toggleAllForRole = (roleId) => {
    const set = draft.value[roleId];
    const all = props.modules.flatMap((m) => props.actions.map((a) => permissionName(m, a)));
    const isAll = all.every((name) => set.has(name));
    draft.value[roleId] = new Set(isAll ? [] : all);
};

const isDirty = (role) => {
    const original = new Set(role.permissions);
    const current = draft.value[role.id];
    if (original.size !== current.size) return true;
    return [...current].some((name) => !original.has(name));
};

const save = (role) => {
    saving.value[role.id] = true;
    router.put(
        route('set-permission.update', role.id),
        { permissions: [...draft.value[role.id]] },
        {
            preserveScroll: true,
            onFinish: () => { saving.value[role.id] = false; },
        },
    );
};

const reset = (role) => {
    draft.value[role.id] = new Set(role.permissions);
};

const isOwnerOrSysadmin = (role) => ['OWNER', 'SYSADMIN'].includes(role.name);
const roleLabel = (role) => roleLabels[role.name] ?? role.name;
</script>

<template>
    <Head title="Role & Permission" />

    <AppLayout title="Role & Permission">
        <div class="flex h-full flex-col">
            <AppPageHeader title="Role & Permission" description="Atur hak akses setiap role per modul." class="shrink-0" />

            <div class="flex min-h-0 flex-1 flex-col gap-6 overflow-hidden">
                <AppCard class="shrink-0">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
                            <button
                                v-for="role in roles"
                                :key="role.id"
                                type="button"
                                @click="activeRoleId = role.id"
                                :class="[
                                    'flex items-center justify-center gap-2 rounded-lg border px-3.5 py-2 text-sm font-medium transition-colors',
                                    activeRoleId === role.id
                                        ? 'border-primary-500 bg-primary-50 text-primary-700 dark:border-primary-500/60 dark:bg-primary-900/30 dark:text-primary-300'
                                        : 'border-gray-300 text-gray-600 hover:border-gray-400 dark:border-gray-700 dark:text-gray-300 dark:hover:border-gray-600',
                                ]"
                            >
                                {{ roleLabel(role) }}
                                <span v-if="isDirty(role)" class="h-1.5 w-1.5 rounded-full bg-amber-500" title="Belum disimpan" />
                            </button>
                        </div>
                        <AppButton v-if="activeRole" variant="ghost" size="sm" class="w-full sm:w-auto" @click="toggleAllForRole(activeRole.id)">
                            Pilih semua
                        </AppButton>
                    </div>
                </AppCard>

                <div
                    v-if="activeRole"
                    class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900"
                >
                    <div class="scrollbar-styled min-h-0 flex-1 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-800">
                        <div
                            v-for="module in modules"
                            :key="module"
                            class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <p class="text-sm font-medium text-gray-900 dark:text-white">
                                {{ moduleLabels[module] ?? module }}
                            </p>

                            <div class="grid grid-cols-2 gap-2 sm:flex">
                                <div
                                    v-for="action in actions"
                                    :key="action"
                                    class="flex items-center justify-between gap-3 rounded-lg border border-gray-200 px-3 py-1.5 dark:border-gray-700"
                                >
                                    <span class="text-xs text-gray-600 dark:text-gray-300">{{ actionLabels[action] ?? action }}</span>
                                    <div class="inline-flex rounded-md bg-gray-100 p-0.5 text-xs font-medium dark:bg-gray-800">
                                        <button
                                            type="button"
                                            class="rounded px-2.5 py-1 transition-colors"
                                            :class="has(activeRole.id, module, action)
                                                ? 'bg-primary-600 text-white'
                                                : 'text-gray-500 hover:text-gray-900 dark:hover:text-white'"
                                            @click="setLevel(activeRole.id, module, action, true)"
                                        >On</button>
                                        <button
                                            type="button"
                                            class="rounded px-2.5 py-1 transition-colors"
                                            :class="!has(activeRole.id, module, action)
                                                ? 'bg-gray-400 text-white dark:bg-gray-600'
                                                : 'text-gray-500 hover:text-gray-900 dark:hover:text-white'"
                                            @click="setLevel(activeRole.id, module, action, false)"
                                        >Off</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-if="isDirty(activeRole)" class="flex shrink-0 items-center justify-end gap-2 border-t border-gray-200 px-5 py-3 dark:border-gray-800">
                        <AppButton variant="ghost" size="sm" @click="reset(activeRole)">Batal</AppButton>
                        <AppButton size="sm" :loading="saving[activeRole.id]" @click="save(activeRole)">Simpan</AppButton>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
