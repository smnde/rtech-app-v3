<script setup>
import { computed, ref, watch } from 'vue';
import { UserPlus, X } from 'lucide-vue-next';
import AppModal from '@/Components/UI/AppModal.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import AppBadge from '@/Components/UI/AppBadge.vue';
import AppDropdown from '@/Components/UI/AppDropdown.vue';
import AppDatePicker from '@/Components/UI/AppDatePicker.vue';
import AppTagInput from '@/Components/UI/AppTagInput.vue';
import CustomerFormModal from '@/Components/Customers/CustomerFormModal.vue';

const props = defineProps({
    show:      { type: Boolean, default: false },
    item:      { type: Object, default: null },
    customers: { type: Array, default: () => [] },
    statuses:  { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'submit', 'customer-created']);

const FLAG_OPTIONS = ['HW', 'SW', 'MB'];

const emptyForm = () => ({
    customer_id: '',
    item_name: '',
    flag: [],
    complaint: [],
    condition: [],
    accessories: [],
    check_deadline_at: '',
    process_deadline_at: '',
    status: 'RECEIVED',
    technician_notes: '',
    additional_notes: '',
});

const form = ref(emptyForm());
const errors = ref({});
const loading = ref(false);

const isEdit = computed(() => props.item !== null);
const title = computed(() => (isEdit.value ? 'Edit Servis' : 'Tambah Servis'));

const statusOptions = computed(() => props.statuses.map((status) => ({ id: status, label: status })));

const selectedCustomer = computed(() => props.customers.find((c) => c.id === form.value.customer_id) ?? null);

const customerSearch = ref('');
const customerOpen = ref(false);
const showCustomerModal = ref(false);
const customerModalRef = ref(null);

const customerResults = computed(() => {
    const q = customerSearch.value.trim().toLowerCase();
    if (!q) return props.customers;
    return props.customers.filter((c) =>
        c.name.toLowerCase().includes(q) || (c.phone ?? '').includes(q),
    );
});

const hasNoCustomerMatch = computed(() => customerSearch.value.trim().length > 0 && customerResults.value.length === 0);

const selectCustomer = (customer) => {
    form.value.customer_id = customer.id;
    customerSearch.value = '';
    customerOpen.value = false;
    errors.value.customer_id = '';
};

const clearCustomer = () => { form.value.customer_id = ''; };

const openCustomerModal = () => {
    customerOpen.value = false;
    showCustomerModal.value = true;
};

const handleCustomerSubmit = (payload) => {
    emit('customer-created', payload, (created) => {
        if (created) selectCustomer(created);
        showCustomerModal.value = false;
        customerModalRef.value?.stopLoading();
    });
};

watch(
    () => props.show,
    (val) => {
        if (!val) return;
        errors.value = {};
        customerSearch.value = '';
        form.value = props.item
            ? {
                customer_id: props.item.customer_id,
                item_name: props.item.item_name,
                flag: [...props.item.flag],
                complaint: [...(props.item.complaint ?? [])],
                condition: [...(props.item.condition ?? [])],
                accessories: [...(props.item.accessories ?? [])],
                check_deadline_at: props.item.check_deadline_at ?? '',
                process_deadline_at: props.item.process_deadline_at ?? '',
                status: props.item.status,
                technician_notes: props.item.technician_notes ?? '',
                additional_notes: props.item.additional_notes ?? '',
            }
            : emptyForm();
    },
    { immediate: true },
);

const toggleFlag = (flag) => {
    form.value.flag = form.value.flag.includes(flag)
        ? form.value.flag.filter((item) => item !== flag)
        : [...form.value.flag, flag];
};

const validate = () => {
    const e = {};
    if (!isEdit.value && !form.value.customer_id) e.customer_id = 'Customer wajib dipilih.';
    if (!String(form.value.item_name).trim()) e.item_name = 'Nama laptop wajib diisi.';
    if (form.value.flag.length === 0) e.flag = 'Pilih minimal satu jenis kerusakan.';
    if (form.value.complaint.length === 0) e.complaint = 'Isi minimal satu keluhan atau kendala.';
    errors.value = e;
    return Object.keys(e).length === 0;
};

const handleSubmit = () => {
    if (!validate()) return;
    loading.value = true;
    emit('submit', {
        customer_id: form.value.customer_id,
        item_name: form.value.item_name,
        flag: form.value.flag,
        complaint: form.value.complaint,
        condition: form.value.condition,
        accessories: form.value.accessories,
        check_deadline_at: form.value.check_deadline_at || null,
        process_deadline_at: form.value.process_deadline_at || null,
        status: form.value.status,
        technician_notes: form.value.technician_notes || null,
        additional_notes: form.value.additional_notes || null,
    });
};

defineExpose({ stopLoading: () => { loading.value = false; } });

const inputClass = (hasError) => [
    'w-full rounded-lg border px-3.5 py-2.5 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-800 placeholder:text-gray-400 focus:outline-none focus:ring-2 transition',
    hasError
        ? 'border-red-400 dark:border-red-500 focus:ring-red-400'
        : 'border-gray-300 dark:border-gray-700 focus:ring-primary-500',
];

const labelClass = 'block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5';
</script>

<template>
    <AppModal :show="show" :title="title" size="lg" @close="$emit('close')">
        <form class="space-y-4" novalidate @submit.prevent="handleSubmit">
            <div v-if="!isEdit">
                <label :class="labelClass">Customer <span class="text-red-500">*</span></label>

                <div v-if="selectedCustomer"
                    class="flex items-center gap-3 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/60 px-3.5 py-2.5">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-1.5 min-w-0">
                            <p class="truncate text-sm font-medium text-gray-900 dark:text-white">{{ selectedCustomer.name }}</p>
                            <AppBadge v-if="selectedCustomer.is_member" color="yellow" size="sm">★ Member</AppBadge>
                        </div>
                        <p v-if="selectedCustomer.phone" class="text-xs text-gray-500 dark:text-gray-400">{{ selectedCustomer.phone }}</p>
                    </div>
                    <button type="button" class="shrink-0 rounded-lg p-1.5 text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors"
                        aria-label="Hapus customer" @click="clearCustomer">
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <div v-else class="relative">
                    <input
                        v-model="customerSearch"
                        type="text"
                        placeholder="Cari nama atau nomor telepon..."
                        :class="inputClass(!!errors.customer_id)"
                        @focus="customerOpen = true"
                        @blur="() => setTimeout(() => customerOpen = false, 150)"
                    />

                    <div v-if="customerOpen && customerSearch.trim()"
                        class="absolute z-50 mt-1 w-full overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xl max-h-64 overflow-y-auto">
                        <button v-for="c in customerResults" :key="c.id" type="button"
                            @mousedown.prevent @click="selectCustomer(c)"
                            class="flex w-full items-center gap-3 px-3 py-2.5 text-left hover:bg-gray-50 dark:hover:bg-gray-700/60 transition-colors">
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400 text-sm font-semibold">
                                {{ c.name.charAt(0).toUpperCase() }}
                            </div>
                            <div class="min-w-0">
                                <div class="flex items-center gap-1.5 min-w-0">
                                    <p class="truncate text-sm font-medium text-gray-900 dark:text-white">{{ c.name }}</p>
                                    <AppBadge v-if="c.is_member" color="yellow" size="sm">★ Member</AppBadge>
                                </div>
                                <p v-if="c.phone" class="text-xs text-gray-500 dark:text-gray-400">{{ c.phone }}</p>
                            </div>
                        </button>

                        <button v-if="hasNoCustomerMatch" type="button"
                            @mousedown.prevent @click="openCustomerModal"
                            class="flex w-full items-center gap-2 border-t border-gray-100 dark:border-gray-700 px-3 py-2.5 text-left text-primary-600 dark:text-primary-400 hover:bg-gray-50 dark:hover:bg-gray-50/50 transition-colors">
                            <UserPlus class="h-4 w-4 shrink-0" />
                            <span class="text-sm">Tambah customer baru: "<span class="font-medium">{{ customerSearch.trim() }}</span>"</span>
                        </button>
                    </div>
                </div>
                <p v-if="errors.customer_id" class="mt-1.5 text-xs text-red-500">{{ errors.customer_id }}</p>
            </div>

            <div>
                <label :class="labelClass">Nama Laptop <span class="text-red-500">*</span></label>
                <input v-model="form.item_name" type="text" placeholder="Contoh: Lenovo IdeaPad 3" :class="inputClass(!!errors.item_name)" />
                <p v-if="errors.item_name" class="mt-1.5 text-xs text-red-500">{{ errors.item_name }}</p>
            </div>

            <div>
                <label :class="labelClass">Jenis Kerusakan <span class="text-red-500">*</span></label>
                <div class="flex gap-2">
                    <button v-for="flag in FLAG_OPTIONS" :key="flag" type="button" @click="toggleFlag(flag)"
                        :class="['rounded-lg border px-3 py-1.5 text-sm font-medium transition-colors',
                            form.flag.includes(flag) ? 'border-primary-500 bg-primary-500/10 text-primary-700 dark:text-primary-300' : 'border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-400']">
                        {{ flag }}
                    </button>
                </div>
                <p v-if="errors.flag" class="mt-1.5 text-xs text-red-500">{{ errors.flag }}</p>
            </div>

            <div>
                <label :class="labelClass">Keluhan / Kendala <span class="text-red-500">*</span></label>
                <AppTagInput v-model="form.complaint" :error="!!errors.complaint" placeholder="Contoh: Tidak bisa booting (ketik lalu tekan + atau Enter)" />
                <p v-if="errors.complaint" class="mt-1.5 text-xs text-red-500">{{ errors.complaint }}</p>
            </div>

            <div>
                <label :class="labelClass">Kondisi fisik</label>
                <AppTagInput v-model="form.condition" placeholder="Contoh: Baret di layar (ketik lalu tekan + atau Enter)" />
            </div>

            <div>
                <label :class="labelClass">Aksesoris dibawa</label>
                <AppTagInput v-model="form.accessories" placeholder="Contoh: Charger, Tas (ketik lalu tekan + atau Enter)" />
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label :class="labelClass">Deadline cek</label>
                    <AppDatePicker v-model="form.check_deadline_at" placeholder="Pilih tanggal" />
                </div>
                <div>
                    <label :class="labelClass">Deadline proses</label>
                    <AppDatePicker v-model="form.process_deadline_at" placeholder="Pilih tanggal" />
                </div>
            </div>

            <div v-if="isEdit">
                <label :class="labelClass">Status</label>
                <AppDropdown v-model="form.status" :options="statusOptions" />
            </div>

            <div>
                <label :class="labelClass">Catatan teknisi</label>
                <textarea v-model="form.technician_notes" rows="2" :class="inputClass(false)"></textarea>
            </div>

            <div>
                <label :class="labelClass">Catatan tambahan</label>
                <textarea v-model="form.additional_notes" rows="2" :class="inputClass(false)"></textarea>
            </div>
        </form>

        <template #footer>
            <AppButton variant="secondary" size="sm" @click="$emit('close')">Batal</AppButton>
            <AppButton variant="primary" size="sm" :loading="loading" @click="handleSubmit">
                {{ isEdit ? 'Simpan Perubahan' : 'Tambah Servis' }}
            </AppButton>
        </template>
    </AppModal>

    <CustomerFormModal
        ref="customerModalRef"
        :show="showCustomerModal"
        :item="null"
        @close="showCustomerModal = false"
        @submit="handleCustomerSubmit"
    />
</template>
