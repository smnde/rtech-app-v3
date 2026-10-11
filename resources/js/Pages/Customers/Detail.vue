<script setup>
import { computed, reactive, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Phone, MapPin, Plus, Trash2, Check, Undo2 } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import AppBadge from '@/Components/UI/AppBadge.vue';
import AppCard from '@/Components/UI/AppCard.vue';
import AppDropdown from '@/Components/UI/AppDropdown.vue';
import { usePermission } from '@/composables/usePermission';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    customer:     { type: Object, required: true },
    notes:        { type: Array, default: () => [] },
    transactions: { type: Array, default: () => [] },
});

const { can } = usePermission();
const confirm = useConfirm();

const NOTE_TYPES = {
    hutang:  { label: 'Hutang',  color: 'red' },
    janji:   { label: 'Janji',   color: 'amber' },
    info:    { label: 'Info',    color: 'blue' },
    general: { label: 'Umum',    color: 'gray' },
};

const noteTypeOptions = Object.entries(NOTE_TYPES).map(([id, { label }]) => ({ id, label }));

const formatDateTime = (value) => (value
    ? new Date(value).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
    : '—');

const rupiah = (value) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value ?? 0);

const cardClass = 'rounded-xl p-4 border transition-colors bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-800';
const sectionTitle = 'text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-3';

const pendingNotes = computed(() => props.notes.filter((note) => note.status === 'pending'));
const doneNotes = computed(() => props.notes.filter((note) => note.status === 'done'));

const newNote = reactive({ type: 'general', description: '' });
const adding = ref(false);

const submitNote = () => {
    if (!newNote.description.trim()) return;
    router.post(route('customers.notes.store', props.customer.id), { ...newNote }, {
        preserveScroll: true,
        onStart: () => { adding.value = true; },
        onFinish: () => { adding.value = false; },
        onSuccess: () => { newNote.type = 'general'; newNote.description = ''; },
    });
};

const toggleNoteStatus = (note) => {
    router.put(route('customers.notes.update', [props.customer.id, note.id]), {
        type: note.type,
        description: note.description,
        status: note.status === 'done' ? 'pending' : 'done',
    }, { preserveScroll: true });
};

const deleteNote = async (note) => {
    const ok = await confirm.confirm({
        title: 'Hapus catatan?',
        message: 'Catatan ini akan dihapus permanen.',
        confirmLabel: 'Hapus',
    });
    if (!ok) return;
    router.delete(route('customers.notes.destroy', [props.customer.id, note.id]), { preserveScroll: true });
};

const TRANSACTION_STATUS = {
    lunas:  'emerald',
    hutang: 'red',
    keep:   'amber',
};
</script>

<template>
    <Head :title="`${customer.name} — Detail`" />

    <AppLayout title="Detail Customer">
        <div class="flex-1 min-h-0 flex flex-col gap-4">

            <div class="shrink-0">
                <Link :href="route('customers.index')"
                    class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white transition-colors">
                    <ArrowLeft class="h-4 w-4" />
                    Kembali ke daftar customer
                </Link>
            </div>

            <div class="scrollbar-styled scrollbar-mobile-hidden flex-1 min-h-0 overflow-y-auto grid grid-cols-1 lg:grid-cols-3 gap-4 items-start">

                <div class="order-2 lg:order-1 lg:col-span-2 space-y-4">
                    <AppCard>
                        <div class="flex items-center justify-between gap-3 mb-3">
                            <h2 :class="sectionTitle" class="mb-0">Catatan</h2>
                            <AppBadge :color="pendingNotes.length ? 'red' : 'gray'" size="sm">
                                {{ pendingNotes.length }} belum selesai
                            </AppBadge>
                        </div>

                        <form v-if="can('edit customer')" class="mb-4 grid grid-cols-1 sm:grid-cols-4 gap-2" @submit.prevent="submitNote">
                            <div class="sm:col-span-1">
                                <AppDropdown v-model="newNote.type" :options="noteTypeOptions" />
                            </div>
                            <input
                                v-model="newNote.description"
                                type="text"
                                placeholder="Contoh: hutang tas Rp 200.000"
                                class="sm:col-span-2 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500"
                            />
                            <AppButton type="submit" variant="primary" size="md" :loading="adding" :disabled="!newNote.description.trim()">
                                <Plus class="h-4 w-4" />
                                Tambah
                            </AppButton>
                        </form>

                        <p v-if="notes.length === 0" class="text-sm text-gray-500 dark:text-gray-400">Belum ada catatan.</p>

                        <ul v-else class="divide-y divide-gray-100 dark:divide-gray-800">
                            <li v-for="note in notes" :key="note.id" class="flex items-start justify-between gap-3 py-3">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2 mb-1">
                                        <AppBadge :color="NOTE_TYPES[note.type]?.color ?? 'gray'" size="sm">
                                            {{ NOTE_TYPES[note.type]?.label ?? note.type }}
                                        </AppBadge>
                                        <span class="text-xs text-gray-400">{{ formatDateTime(note.created_at) }}</span>
                                    </div>
                                    <p :class="['text-sm', note.status === 'done' ? 'line-through text-gray-400 dark:text-gray-500' : 'text-gray-900 dark:text-white']">
                                        {{ note.description }}
                                    </p>
                                </div>
                                <div v-if="can('edit customer')" class="flex shrink-0 items-center gap-1">
                                    <AppButton variant="ghost" size="sm" icon-only
                                        :title="note.status === 'done' ? 'Tandai belum selesai' : 'Tandai selesai'"
                                        @click="toggleNoteStatus(note)">
                                        <Undo2 v-if="note.status === 'done'" class="h-4 w-4" />
                                        <Check v-else class="h-4 w-4" />
                                    </AppButton>
                                    <AppButton variant="ghost" size="sm" icon-only title="Hapus"
                                        class="hover:!text-red-500 dark:hover:!text-red-400" @click="deleteNote(note)">
                                        <Trash2 class="h-4 w-4" />
                                    </AppButton>
                                </div>
                            </li>
                        </ul>
                    </AppCard>

                    <AppCard>
                        <h2 :class="sectionTitle">Riwayat Transaksi</h2>

                        <p v-if="transactions.length === 0" class="text-sm text-gray-500 dark:text-gray-400">Belum ada transaksi.</p>

                        <ul v-else class="divide-y divide-gray-100 dark:divide-gray-800">
                            <li v-for="trx in transactions" :key="`${trx.type}-${trx.reference}`" class="flex items-center justify-between gap-3 py-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ trx.type }} <span class="font-mono text-xs text-gray-500">{{ trx.reference }}</span>
                                    </p>
                                    <p class="text-xs text-gray-400">{{ formatDateTime(trx.date) }}</p>
                                </div>
                                <div class="shrink-0 text-right">
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white tabular-nums">{{ rupiah(trx.total) }}</p>
                                    <AppBadge :color="TRANSACTION_STATUS[trx.status] ?? 'gray'" size="sm">{{ trx.status }}</AppBadge>
                                </div>
                            </li>
                        </ul>
                    </AppCard>
                </div>

                <div class="order-1 lg:order-2 space-y-4 lg:sticky lg:top-0">
                    <AppCard>
                        <h2 :class="sectionTitle">Informasi</h2>
                        <h1 class="mb-3 text-xl font-semibold text-gray-900 dark:text-white">{{ customer.name }}</h1>
                        <dl class="space-y-3 text-sm">
                            <div class="flex items-center gap-2 text-gray-700 dark:text-gray-300">
                                <Phone class="h-4 w-4 shrink-0 text-gray-400" />
                                <span class="tabular-nums">{{ customer.phone }}</span>
                            </div>
                            <div class="flex items-start gap-2 text-gray-700 dark:text-gray-300">
                                <MapPin class="h-4 w-4 mt-0.5 shrink-0 text-gray-400" />
                                <span>{{ customer.address ?? '—' }}</span>
                            </div>
                            <div class="pt-1">
                                <AppBadge :color="customer.is_member ? 'purple' : 'gray'" size="sm">
                                    {{ customer.is_member ? 'Member' : 'Non-member' }}
                                </AppBadge>
                            </div>
                        </dl>
                    </AppCard>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
