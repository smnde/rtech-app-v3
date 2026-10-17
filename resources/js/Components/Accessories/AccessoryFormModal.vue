<script setup>
import { ref, watch, computed } from 'vue';
import AppModal from '@/Components/UI/AppModal.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import AppSelect from '@/Components/UI/AppSelect.vue';
import AppDropdown from '@/Components/UI/AppDropdown.vue';
import AppNumberInput from '@/Components/UI/AppNumberInput.vue';
import AppCurrencyInput from '@/Components/UI/AppCurrencyInput.vue';

const props = defineProps({
    show:      { type: Boolean, default: false },
    item:      { type: Object, default: null },
    brands:    { type: Array, required: true },
    suppliers: { type: Array, required: true },
});

const emit = defineEmits(['close', 'submit']);

const statusOptions = ['READY STOCK', 'HABIS'];

const emptyForm = () => ({
    name:        '',
    warranty:    '',
    brand_id:    '',
    supplier_id: '',
    stock:       0,
    status:      'READY STOCK',
    buy_price:   '',
    sell_price:  '',
    notes:       '',
});

const form    = ref(emptyForm());
const errors  = ref({});
const loading = ref(false);

const isEdit = computed(() => props.item !== null);
const title  = computed(() => isEdit.value ? 'Edit Aksesoris' : 'Tambah Aksesoris');

const supplierSelectOptions = computed(() => [{ id: '', label: '— Tidak ada —' }, ...props.suppliers]);

watch(
    () => props.show,
    (val) => {
        if (!val) return;
        errors.value = {};
        form.value = props.item
            ? {
                name:        props.item.name        ?? '',
                warranty:    props.item.warranty    ?? '',
                brand_id:    props.item.brand_id    ?? '',
                supplier_id: props.item.supplier_id ?? '',
                stock:       props.item.stock       ?? 0,
                status:      props.item.status      ?? 'READY STOCK',
                buy_price:   props.item.buy_price   ?? '',
                sell_price:  props.item.sell_price  ?? '',
                notes:       props.item.notes       ?? '',
            }
            : emptyForm();
    },
    { immediate: true },
);

const validate = () => {
    const e = {};
    if (!String(form.value.name).trim()) e.name = 'Nama wajib diisi.';
    if (!String(form.value.warranty).trim()) e.warranty = 'Garansi wajib diisi.';
    if (!form.value.brand_id)            e.brand_id = 'Brand wajib dipilih.';
    if (!isEdit.value && (form.value.stock === '' || Number(form.value.stock) < 0)) e.stock = 'Stok wajib diisi (min. 0).';
    if (!form.value.status) e.status = 'Status wajib dipilih.';
    errors.value = e;
    return Object.keys(e).length === 0;
};

const handleSubmit = () => {
    if (!validate()) return;
    loading.value = true;
    const { stock, ...rest } = form.value;
    emit('submit', {
        ...rest,
        brand_id:    Number(form.value.brand_id),
        supplier_id: form.value.supplier_id === '' ? null : Number(form.value.supplier_id),
        ...(isEdit.value ? {} : { stock: Number(form.value.stock) }),
        status:      form.value.status,
        buy_price:   form.value.buy_price  === '' ? null : Number(form.value.buy_price),
        sell_price:  form.value.sell_price === '' ? null : Number(form.value.sell_price),
        notes:       form.value.notes || null,
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
                        <label :class="labelClass">
                            Nama Produk <span class="text-red-500">*</span>
                        </label>
                        <input v-model="form.name" type="text" placeholder="Contoh: Charger Apple 20W Original"
                            autofocus :class="inputClass(errors.name)" />
                        <p v-if="errors.name" class="mt-1.5 text-xs text-red-500">{{ errors.name }}</p>
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
                    <div v-if="!isEdit">
                        <label :class="labelClass">
                            Stok <span class="text-red-500">*</span>
                        </label>
                        <AppNumberInput v-model="form.stock" :min="0" :error="!!errors.stock" />
                        <p v-if="errors.stock" class="mt-1.5 text-xs text-red-500">{{ errors.stock }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
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
                        <label :class="labelClass">Supplier</label>
                        <AppSelect v-model="form.supplier_id" :options="supplierSelectOptions" />
                    </div>
                </div>
            </section>

            <!-- Harga -->
            <section class="grid grid-cols-1 gap-4 border-t border-gray-100 pt-5 sm:grid-cols-2 dark:border-gray-800">
                <div>
                    <label :class="labelClass">Harga Beli</label>
                    <AppCurrencyInput v-model="form.buy_price" />
                </div>
                <div>
                    <label :class="labelClass">Harga Jual</label>
                    <AppCurrencyInput v-model="form.sell_price" />
                </div>
            </section>

            <!-- Catatan -->
            <section class="border-t border-gray-100 pt-5 dark:border-gray-800">
                <label :class="labelClass">Catatan</label>
                <textarea v-model="form.notes" rows="2" placeholder="Catatan tambahan (opsional)"
                    :class="inputClass(false)"></textarea>
            </section>

        </form>

        <template #footer>
            <AppButton variant="secondary" size="sm" @click="$emit('close')">Batal</AppButton>
            <AppButton variant="primary" size="sm" :loading="loading" @click="handleSubmit">
                {{ isEdit ? 'Simpan Perubahan' : 'Tambah Aksesoris' }}
            </AppButton>
        </template>
    </AppModal>
</template>
