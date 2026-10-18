<script setup>
import { ref, watch, computed } from 'vue';
import AppModal from '@/Components/UI/AppModal.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import AppSelect from '@/Components/UI/AppSelect.vue';
import AppDropdown from '@/Components/UI/AppDropdown.vue';
import AppNumberInput from '@/Components/UI/AppNumberInput.vue';
import AppCurrencyInput from '@/Components/UI/AppCurrencyInput.vue';
import AppTagInput from '@/Components/UI/AppTagInput.vue';

const props = defineProps({
    show:      { type: Boolean, default: false },
    item:      { type: Object, default: null },
    brands:    { type: Array, required: true },
    suppliers: { type: Array, required: true },
});

const emit = defineEmits(['close', 'submit']);

const statusOptions = ['READY STOCK', 'HABIS'];

const emptySpecification = () => ({ cpu: '', gpu: '', memory: '', storage: '', display: '' });

const emptyForm = () => ({
    name:          '',
    serialnumber:  '',
    brand_id:      '',
    supplier_id:   '',
    warranty:      '',
    stock:         0,
    status:        'READY STOCK',
    buy_price:     '',
    sell_price:    '',
    specification: emptySpecification(),
    accessories:   [],
    issue_notes:   [],
    notes:         '',
});

const form    = ref(emptyForm());
const errors  = ref({});
const loading = ref(false);

const isEdit = computed(() => props.item !== null);
const title  = computed(() => isEdit.value ? 'Edit Gadget' : 'Tambah Gadget');

const supplierSelectOptions = computed(() => [{ id: '', label: '— Tidak ada —' }, ...props.suppliers]);

const specFields = [
    { key: 'cpu',     label: 'CPU',     placeholder: 'Contoh: Snapdragon 8 Gen 2' },
    { key: 'gpu',     label: 'GPU',     placeholder: 'Contoh: Adreno 740' },
    { key: 'memory',  label: 'Memory',  placeholder: 'Contoh: 8GB RAM' },
    { key: 'storage', label: 'Storage', placeholder: 'Contoh: 256GB' },
    { key: 'display', label: 'Display', placeholder: 'Contoh: 6.7" AMOLED 120Hz' },
];

watch(
    () => props.show,
    (val) => {
        if (!val) return;
        errors.value = {};
        form.value = props.item
            ? {
                name:          props.item.name          ?? '',
                serialnumber:  props.item.serialnumber  ?? '',
                brand_id:      props.item.brand_id      ?? '',
                supplier_id:   props.item.supplier_id   ?? '',
                warranty:      props.item.warranty      ?? '',
                stock:         props.item.stock         ?? 0,
                status:        props.item.status        ?? 'READY STOCK',
                buy_price:     props.item.buy_price     ?? '',
                sell_price:    props.item.sell_price    ?? '',
                specification: { ...emptySpecification(), ...(props.item.specification ?? {}) },
                accessories:   [...(props.item.accessories ?? [])],
                issue_notes:   [...(props.item.issue_notes ?? [])],
                notes:         props.item.notes         ?? '',
            }
            : emptyForm();
    },
    { immediate: true },
);

const validate = () => {
    const e = {};
    if (!String(form.value.name).trim()) e.name = 'Nama wajib diisi.';
    if (!String(form.value.warranty).trim()) e.warranty = 'Garansi wajib diisi.';
    if (!form.value.brand_id) e.brand_id = 'Brand wajib dipilih.';
    if (!form.value.status) e.status = 'Status wajib dipilih.';
    if (!isEdit.value && (form.value.stock === '' || Number(form.value.stock) < 0)) e.stock = 'Stok wajib diisi (min. 0).';
    errors.value = e;
    return Object.keys(e).length === 0;
};

const cleanSpecification = () => {
    const spec = Object.fromEntries(
        Object.entries(form.value.specification).map(([key, value]) => [key, String(value ?? '').trim() || null]),
    );
    return Object.values(spec).every((value) => value === null) ? null : spec;
};

const handleSubmit = () => {
    if (!validate()) return;
    loading.value = true;
    emit('submit', {
        name:          form.value.name,
        serialnumber:  form.value.serialnumber || null,
        brand_id:      Number(form.value.brand_id),
        supplier_id:   form.value.supplier_id === '' ? null : Number(form.value.supplier_id),
        warranty:      form.value.warranty,
        ...(isEdit.value ? {} : { stock: Number(form.value.stock) }),
        status:        form.value.status,
        buy_price:     form.value.buy_price  === '' ? null : Number(form.value.buy_price),
        sell_price:    form.value.sell_price === '' ? null : Number(form.value.sell_price),
        specification: cleanSpecification(),
        accessories:   form.value.accessories,
        issue_notes:   form.value.issue_notes,
        notes:         form.value.notes || null,
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
        <form @submit.prevent="handleSubmit" class="space-y-5" novalidate>

            <!-- Identitas -->
            <section class="space-y-4">
                <div>
                    <label :class="labelClass">
                        Nama Produk <span class="text-red-500">*</span>
                    </label>
                    <input v-model="form.name" type="text" placeholder="Contoh: Samsung Galaxy A55 5G"
                        autofocus :class="inputClass(errors.name)" />
                    <p v-if="errors.name" class="mt-1.5 text-xs text-red-500">{{ errors.name }}</p>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-[minmax(0,10rem)_1fr]">
                    <div>
                        <label :class="labelClass">
                            Brand <span class="text-red-500">*</span>
                        </label>
                        <AppSelect
                            v-model="form.brand_id"
                            :options="brands"
                            placeholder="Pilih..."
                            :error="!!errors.brand_id"
                        />
                        <p v-if="errors.brand_id" class="mt-1.5 text-xs text-red-500">{{ errors.brand_id }}</p>
                    </div>
                    <div>
                        <label :class="labelClass">Serial Number</label>
                        <input v-model="form.serialnumber" type="text" placeholder="Contoh: SN12345678"
                            :class="inputClass(false)" />
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label :class="labelClass">
                            Garansi <span class="text-red-500">*</span>
                        </label>
                        <input v-model="form.warranty" type="text" placeholder="Contoh: 1 Tahun"
                            :class="inputClass(errors.warranty)" />
                        <p v-if="errors.warranty" class="mt-1.5 text-xs text-red-500">{{ errors.warranty }}</p>
                    </div>
                    <div>
                        <label :class="labelClass">Supplier</label>
                        <AppSelect v-model="form.supplier_id" :options="supplierSelectOptions" />
                    </div>
                </div>
            </section>

            <!-- Stok & harga -->
            <section class="grid grid-cols-1 gap-4 border-t border-gray-100 pt-5 sm:grid-cols-2 dark:border-gray-800">
                <div v-if="!isEdit">
                    <label :class="labelClass">
                        Stok <span class="text-red-500">*</span>
                    </label>
                    <AppNumberInput v-model="form.stock" :min="0" :error="!!errors.stock" />
                    <p v-if="errors.stock" class="mt-1.5 text-xs text-red-500">{{ errors.stock }}</p>
                </div>
                <div>
                    <label :class="labelClass">
                        Status <span class="text-red-500">*</span>
                    </label>
                    <AppDropdown
                        v-model="form.status"
                        :options="statusOptions"
                        placeholder="Pilih status..."
                        :error="!!errors.status"
                    />
                    <p v-if="errors.status" class="mt-1.5 text-xs text-red-500">{{ errors.status }}</p>
                </div>
                <div>
                    <label :class="labelClass">Harga Beli</label>
                    <AppCurrencyInput v-model="form.buy_price" />
                </div>
                <div>
                    <label :class="labelClass">Harga Jual</label>
                    <AppCurrencyInput v-model="form.sell_price" />
                </div>
            </section>

            <!-- Spesifikasi -->
            <section class="border-t border-gray-100 pt-5 dark:border-gray-800">
                <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Spesifikasi</h3>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div v-for="field in specFields" :key="field.key"
                        :class="field.key === 'display' ? 'sm:col-span-2' : ''">
                        <label :class="labelClass">{{ field.label }}</label>
                        <input v-model="form.specification[field.key]" type="text" :placeholder="field.placeholder"
                            :class="inputClass(false)" />
                    </div>
                </div>
            </section>

            <!-- Aksesori, kendala, catatan -->
            <section class="space-y-4 border-t border-gray-100 pt-5 dark:border-gray-800">
                <div>
                    <label :class="labelClass">Aksesori</label>
                    <AppTagInput v-model="form.accessories" placeholder="Contoh: Charger, Case (ketik lalu tekan + atau Enter)" />
                </div>
                <div>
                    <label :class="labelClass">Catatan Kendala</label>
                    <AppTagInput v-model="form.issue_notes" placeholder="Contoh: Layar retak (ketik lalu tekan + atau Enter)" />
                </div>
                <div>
                    <label :class="labelClass">Catatan</label>
                    <textarea v-model="form.notes" rows="2" placeholder="Catatan tambahan (opsional)"
                        :class="inputClass(false)"></textarea>
                </div>
            </section>

        </form>

        <template #footer>
            <AppButton variant="secondary" size="sm" @click="$emit('close')">Batal</AppButton>
            <AppButton variant="primary" size="sm" :loading="loading" @click="handleSubmit">
                {{ isEdit ? 'Simpan Perubahan' : 'Tambah Gadget' }}
            </AppButton>
        </template>
    </AppModal>
</template>
