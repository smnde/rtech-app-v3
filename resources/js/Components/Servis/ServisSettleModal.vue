<script setup>
import { computed } from 'vue';
import { CheckCircle2, Clock } from 'lucide-vue-next';
import AppModal from '@/Components/UI/AppModal.vue';
import AppButton from '@/Components/UI/AppButton.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    status: { type: String, default: 'DONE' },
    items: { type: Array, default: () => [] },
    total: { type: Number, default: 0 },
    saving: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'confirm']);

const formatRupiah = (value) => `Rp ${Number(value ?? 0).toLocaleString('id-ID')}`;

const title = computed(() => (props.status === 'TAKEN' ? 'Ambil Unit Servis' : 'Servis Selesai'));

const description = computed(() =>
    props.status === 'TAKEN'
        ? 'Unit diambil customer. Pilih status pembayaran servis.'
        : 'Unit sudah selesai dikerjakan. Pilih status pembayaran servis.'
);
</script>

<template>
    <AppModal :show="show" :title="title" size="md" @close="emit('close')">
        <div class="space-y-4">
            <p class="text-sm text-gray-600 dark:text-gray-400">{{ description }}</p>

            <div class="rounded-lg border border-gray-200 dark:border-gray-800 overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-800/60 text-xs uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium">Rincian perbaikan</th>
                            <th class="px-3 py-2 text-right font-medium">Biaya</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-if="items.length === 0">
                            <td colspan="2" class="px-3 py-3 text-center text-gray-500">Belum ada rincian perbaikan.</td>
                        </tr>
                        <tr v-for="(item, i) in items" :key="i">
                            <td class="px-3 py-2 text-gray-900 dark:text-white">{{ item.label }}</td>
                            <td class="px-3 py-2 text-right tabular-nums text-gray-700 dark:text-gray-300">{{ formatRupiah(item.cost) }}</td>
                        </tr>
                    </tbody>
                    <tfoot class="border-t border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/60">
                        <tr>
                            <td class="px-3 py-2 text-sm font-semibold text-gray-900 dark:text-white">Total</td>
                            <td class="px-3 py-2 text-right text-base font-semibold tabular-nums text-gray-900 dark:text-white">{{ formatRupiah(total) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <p class="text-xs text-gray-500">Pilih <span class="font-medium">Lunas</span> untuk langsung mencatat uang masuk ke kas. Bisa dilunasi belakangan lewat tombol Tandai Lunas.</p>
        </div>

        <template #footer>
            <AppButton variant="secondary" size="sm" @click="emit('close')">Batal</AppButton>
            <AppButton variant="secondary" size="sm" :disabled="saving" @click="emit('confirm', false)">
                <Clock class="h-4 w-4" />
                Belum Lunasi
            </AppButton>
            <AppButton variant="primary" size="sm" :loading="saving" @click="emit('confirm', true)">
                <CheckCircle2 class="h-4 w-4" />
                Lunas
            </AppButton>
        </template>
    </AppModal>
</template>
