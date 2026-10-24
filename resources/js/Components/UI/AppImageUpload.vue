<script setup>
import { ref, computed, watch, onBeforeUnmount } from 'vue';
import { ImagePlus, Trash2, RefreshCw } from 'lucide-vue-next';

/**
 * Upload satu gambar dengan preview. File tidak langsung diunggah —
 * parent yang mengirim ke server saat form disimpan.
 */
const props = defineProps({
    /** File yang dipilih (belum diunggah) */
    file:     { type: Object, default: null },
    /** URL gambar yang sudah tersimpan di server */
    existing: { type: String, default: null },
    error:    { type: Boolean, default: false },
    accept:   { type: String, default: 'image/jpeg,image/png,image/webp' },
    maxMb:    { type: Number, default: 2 },
});

const emit = defineEmits(['update:file', 'remove-existing']);

const inputRef    = ref(null);
const objectUrl   = ref(null);
const dragging    = ref(false);
const localError  = ref('');

const previewUrl = computed(() => objectUrl.value ?? props.existing);

const setObjectUrl = (file) => {
    if (objectUrl.value) URL.revokeObjectURL(objectUrl.value);
    objectUrl.value = file ? URL.createObjectURL(file) : null;
};

watch(() => props.file, (file) => setObjectUrl(file), { immediate: true });
onBeforeUnmount(() => { if (objectUrl.value) URL.revokeObjectURL(objectUrl.value); });

const pick = () => inputRef.value?.click();

const acceptFile = (file) => {
    localError.value = '';

    if (!file) {
        return;
    }

    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
        localError.value = 'Format harus JPG, PNG, atau WEBP.';
        return;
    }

    if (file.size > props.maxMb * 1024 * 1024) {
        localError.value = `Ukuran maksimal ${props.maxMb} MB.`;
        return;
    }

    emit('update:file', file);
};

const onFileChange = (event) => {
    acceptFile(event.target.files?.[0]);
    event.target.value = '';
};

const onDrop = (event) => {
    dragging.value = false;
    acceptFile(event.dataTransfer?.files?.[0]);
};

const clear = () => {
    localError.value = '';
    emit('update:file', null);

    if (props.existing) {
        emit('remove-existing');
    }
};
</script>

<template>
    <div>
        <input ref="inputRef" type="file" :accept="accept" class="hidden" @change="onFileChange" />

        <!-- Preview -->
        <div v-if="previewUrl" class="group relative overflow-hidden rounded-xl border border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-800/40">
            <img :src="previewUrl" alt="Preview foto produk" class="h-56 w-full object-contain" />

            <div class="absolute inset-x-0 bottom-0 flex items-center justify-between gap-2 bg-gradient-to-t from-black/60 to-transparent p-3">
                <span class="text-xs font-medium text-white/90">
                    {{ file ? 'Belum disimpan' : 'Foto tersimpan' }}
                </span>
                <div class="flex items-center gap-1.5">
                    <button type="button" @click="pick"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-white/15 px-2.5 py-1.5 text-xs font-medium text-white backdrop-blur transition-colors hover:bg-white/25">
                        <RefreshCw class="h-3.5 w-3.5" />
                        Ganti
                    </button>
                    <button type="button" @click="clear"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-red-500/90 px-2.5 py-1.5 text-xs font-medium text-white transition-colors hover:bg-red-500">
                        <Trash2 class="h-3.5 w-3.5" />
                        Hapus
                    </button>
                </div>
            </div>
        </div>

        <!-- Dropzone -->
        <button
            v-else
            type="button"
            @click="pick"
            @dragover.prevent="dragging = true"
            @dragleave.prevent="dragging = false"
            @drop.prevent="onDrop"
            :class="[
                'flex w-full flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed px-4 py-8 transition-colors',
                error || localError
                    ? 'border-red-400 dark:border-red-500'
                    : dragging
                        ? 'border-primary-500 bg-primary-500/5'
                        : 'border-gray-300 dark:border-gray-700 hover:border-primary-400 hover:bg-gray-50 dark:hover:bg-gray-800/40',
            ]"
        >
            <div class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-500">
                <ImagePlus class="h-5 w-5" />
            </div>
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Klik atau tarik gambar ke sini</span>
            <span class="text-xs text-gray-400 dark:text-gray-500">JPG, PNG, atau WEBP · maks. {{ maxMb }} MB</span>
        </button>

        <p v-if="localError" class="mt-1.5 text-xs text-red-500">{{ localError }}</p>
    </div>
</template>
