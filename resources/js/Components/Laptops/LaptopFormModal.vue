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

const emptySpecification = () => ({ cpu: '', gpu: '', memory: '', storage: '', display: '' });

const emptyForm = () => ({
    name:          '',
    brand_id:      '',
    serialnumber:  '',
    warranty:      '',
    supplier_id:   '',
    code:          '',
    stock:         0,
    status:        '',
    buy_price:     '',
    sell_price:    '',
    specification: emptySpecification(),
    accessories:   [],
    features:      [],
    issue_notes:   [],
    notes:         '',
});

const form    = ref(emptyForm());
const errors  = ref({});
const loading = ref(false);

const isEdit = computed(() => props.item !== null);
const title  = computed(() => isEdit.value ? 'Edit Laptop' : 'Tambah Laptop');
const issues = computed(() => props.item?.issue_notes ?? []);
const solvedIssues = computed(() => issues.value.filter((issue) => issue.solved));
const issuesToTexts = (list) => (list ?? []).map((issue) => (typeof issue === 'string' ? issue : issue.text));

const statuses = ['ON PROSES', 'SIAP JUAL', 'DISPLAY', 'PENDING', 'KENDALA', 'HABIS'];

const supplierSelectOptions = computed(() => [{ id: '', label: '— Tidak ada —' }, ...props.suppliers]);

const specFields = [
    { key: 'cpu',     label: 'CPU',     placeholder: 'Contoh: Intel Core i5-12450H' },
    { key: 'gpu',     label: 'GPU',     placeholder: 'Contoh: NVIDIA RTX 3050' },
    { key: 'memory',  label: 'Memory',  placeholder: 'Contoh: 16GB DDR4' },
    { key: 'storage', label: 'Storage', placeholder: 'Contoh: 512GB SSD' },
    { key: 'display', label: 'Display', placeholder: 'Contoh: 15.6" FHD 144Hz' },
];

watch(
    () => props.show,
    (val) => {
        if (!val) return;
        errors.value = {};
        form.value = props.item
            ? {
                name:          props.item.name          ?? '',
                brand_id:      props.item.brand_id      ?? '',
                serialnumber:  props.item.serialnumber  ?? '',
                warranty:      props.item.warranty      ?? '',
                supplier_id:   props.item.supplier_id   ?? '',
                code:          props.item.code          ?? '',
                stock:         props.item.stock         ?? 0,
                status:        props.item.status        ?? '',
                buy_price:     props.item.buy_price     ?? '',
                sell_price:    props.item.sell_price    ?? '',
                specification: { ...emptySpecification(), ...(props.item.specification ?? {}) },
                accessories:   [...(props.item.accessories ?? [])],
                features:      [...(props.item.features ?? [])],
                issue_notes:   issuesToTexts(props.item.issue_notes),
                notes:         props.item.notes         ?? '',
            }
            : emptyForm();
    },
    { immediate: true },
);

const validate = () => {
    const e = {};
    if (!String(form.value.name).trim())     e.name = 'Nama wajib diisi.';
    if (!String(form.value.warranty).trim()) e.warranty = 'Garansi wajib diisi.';
    if (!form.value.brand_id)                e.brand_id = 'Brand wajib dipilih.';
    if (!form.value.status)                  e.status = 'Status wajib dipilih.';
    specFields.forEach((field) => {
        if (!String(form.value.specification[field.key] ?? '').trim()) e[`specification.${field.key}`] = `${field.label} wajib diisi.`;
    });
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
        brand_id:      Number(form.value.brand_id),
        serialnumber:  form.value.serialnumber || null,
        warranty:      form.value.warranty,
        supplier_id:   form.value.supplier_id === '' ? null : Number(form.value.supplier_id),
        code:          form.value.code || null,
        ...(isEdit.value ? {} : { stock: Number(form.value.stock) }),
        status:        form.value.status,
        buy_price:     form.value.buy_price  === '' ? null : Number(form.value.buy_price),
        sell_price:    form.value.sell_price === '' ? null : Number(form.value.sell_price),
        specification: cleanSpecification(),
        accessories:   form.value.accessories,
        features:      form.value.features,
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
                    <input v-model="form.name" type="text" placeholder="Contoh: Asus VivoBook 15 X1502"
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
                <div>
                    <label :class="labelClass">
                        Stok <span v-if="!isEdit" class="text-red-500">*</span>
                    </label>
                    <AppNumberInput v-if="!isEdit" v-model="form.stock" :min="0" :error="!!errors.stock" />
                    <input v-else :value="form.stock" type="number" disabled :class="inputClass(false)" class="cursor-not-allowed opacity-60" />
                    <p v-if="errors.stock" class="mt-1.5 text-xs text-red-500">{{ errors.stock }}</p>
                    <p v-if="isEdit" class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">
                        Stok hanya bisa diubah lewat Mutasi Stok, Pembelian, atau Penjualan.
                    </p>
                </div>
                <div>
                    <label :class="labelClass">
                        Status <span class="text-red-500">*</span>
                    </label>
                    <AppDropdown
                        v-model="form.status"
                        :options="statuses"
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
                <div class="sm:col-span-2">
                    <label :class="labelClass">Kode <span class="text-xs font-normal text-gray-400">(opsional)</span></label>
                    <input v-model="form.code" type="text" placeholder="Contoh: LPT-001-A"
                        :class="inputClass(false)" />
                </div>
            </section>

            <!-- Spesifikasi -->
            <section class="border-t border-gray-100 pt-5 dark:border-gray-800">
                <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Spesifikasi</h3>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div v-for="field in specFields" :key="field.key"
                        :class="field.key === 'display' ? 'sm:col-span-2' : ''">
                        <label :class="labelClass">{{ field.label }} <span class="text-red-500">*</span></label>
                        <input v-model="form.specification[field.key]" type="text" :placeholder="field.placeholder"
                            :class="inputClass(!!errors[`specification.${field.key}`])" />
                        <p v-if="errors[`specification.${field.key}`]" class="mt-1.5 text-xs text-red-500">{{ errors[`specification.${field.key}`] }}</p>
                    </div>
                </div>
            </section>

            <!-- Accessories, fitur, kendala -->
            <section class="space-y-4 border-t border-gray-100 pt-5 dark:border-gray-800">
                <div>
                    <label :class="labelClass">Aksesoris</label>
                    <AppTagInput v-model="form.accessories" placeholder="Contoh: Charger, Tas (ketik lalu tekan + atau Enter)" />
                </div>
                <div>
                    <label :class="labelClass">Fitur</label>
                    <AppTagInput v-model="form.features" placeholder="Contoh: Backlit keyboard (ketik lalu tekan + atau Enter)" />
                </div>
                <div>
                    <label :class="labelClass">Catatan Kendala</label>
                    <ul v-if="solvedIssues.length" class="mb-2 space-y-1.5">
                        <li v-for="issue in solvedIssues" :key="issue.id"
                            class="flex items-center gap-2 rounded-lg border border-emerald-500/20 bg-emerald-500/5 px-3 py-2 text-sm">
                            <span class="text-emerald-600 dark:text-emerald-400">✓</span>
                            <span class="text-gray-400 line-through dark:text-gray-500">{{ issue.text }}</span>
                        </li>
                    </ul>
                    <AppTagInput v-model="form.issue_notes" placeholder="Contoh: Keyboard rusak (ketik lalu tekan + atau Enter)" />
                    <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">
                        Kendala yang sudah selesai dicentang di panel Kendala halaman detail.
                    </p>
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
                {{ isEdit ? 'Simpan Perubahan' : 'Tambah Laptop' }}
            </AppButton>
        </template>
    </AppModal>
</template>
