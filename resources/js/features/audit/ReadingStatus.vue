<script setup lang="ts">
import { computed } from 'vue';
import Alert from '@/components/ui/Alert.vue';
import Button from '@/components/ui/Button.vue';

interface Props {
    status: string;
    regency: string;
    pollSeconds: number;
    errorCode?: string | null;
    isEditable: boolean;
    isRetrying: boolean;
    isReplacingFile: boolean;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    retry: [];
    replaceFile: [];
    addManual: [];
}>();

const failureDescription = computed(() => {
    if (props.errorCode === 'NO_RAB_ITEMS_FOUND') {
        return 'Tabel rincian RAB tidak terbaca pada berkas ini. Pastikan foto atau PDF menampilkan tabel RAB dengan jelas.';
    }
    if (props.errorCode === 'AI_KEY_MISSING') {
        return 'Pembacaan otomatis belum tersedia saat ini. Anda dapat mengisi rincian belanja secara manual.';
    }
    if (props.errorCode === 'AI_QUOTA_EXCEEDED') {
        return 'Coba baca ulang, atau isi rincian secara manual.';
    }
    return 'Berkas tetap tersimpan. Coba baca ulang, atau isi rincian secara manual.';
});
</script>

<template>
    <Alert v-if="status === 'reading'" color="info" title="Dokumen sedang dibaca…">
        <div class="mt-1 space-y-2">
            <p>
                Sistem sedang membaca tabel rincian belanja dan mencocokkannya dengan standar harga Kabupaten
                {{ regency }}. Halaman diperbarui otomatis.
            </p>
            <div class="inline-flex items-center gap-2 text-xs font-medium text-info">
                <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                </svg>
                <span class="tabular-nums">Memeriksa dokumen ({{ pollSeconds }} detik)…</span>
            </div>
        </div>
    </Alert>

    <Alert v-else-if="status === 'read_failed'" color="negative" title="Dokumen belum bisa dibaca otomatis">
        <div class="mt-1 space-y-4">
            <p>{{ failureDescription }}</p>

            <div class="flex flex-wrap gap-2.5">
                <Button type="button" variant="primary" size="sm" :loading="isRetrying" @click="emit('retry')">
                    {{ isRetrying ? 'Memulai ulang...' : 'Coba baca ulang' }}
                </Button>

                <Button
                    v-if="isEditable"
                    type="button"
                    variant="secondary"
                    size="sm"
                    :loading="isReplacingFile"
                    @click="emit('replaceFile')"
                >
                    {{ isReplacingFile ? 'Mengunggah...' : 'Ganti berkas' }}
                </Button>

                <Button type="button" variant="secondary" size="sm" @click="emit('addManual')">
                    Isi rincian manual
                </Button>
            </div>
        </div>
    </Alert>
</template>
