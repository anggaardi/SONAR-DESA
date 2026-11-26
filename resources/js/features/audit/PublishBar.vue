<script setup lang="ts">
import Button from '@/components/ui/Button.vue';
import { formatRupiah } from '@/lib/format';
import type { AuditSummary } from '@/types/audit';

interface Props {
    summary: AuditSummary;
    project: { id: number; is_published: boolean } | null;
}

defineProps<Props>();

const emit = defineEmits<{
    openWizard: [];
}>();
</script>

<template>
    <div
        class="sticky bottom-4 z-20 flex flex-col gap-3 rounded-lg border border-t border-stroke bg-surface p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between"
    >
        <div>
            <span class="block text-xs text-content-tertiary">Jumlah rincian belanja</span>
            <div class="flex items-baseline gap-2">
                <span class="text-xl font-bold text-content tabular-nums">
                    {{ formatRupiah(summary.total_calculated_rp) }}
                </span>
                <span class="text-xs text-content-tertiary tabular-nums"> ({{ summary.total_items }} baris) </span>
            </div>
        </div>

        <div class="flex flex-col items-start gap-1 sm:items-end">
            <!-- Already published -->
            <template v-if="summary.publish_block_reason === 'already_published' || project?.is_published">
                <Button v-if="project" variant="primary" :href="`/desa/projects/${project.id}`">
                    Kelola proyek fisik
                </Button>
                <span class="text-xs font-medium text-positive"> Proyek sudah diterbitkan ke halaman warga. </span>
            </template>

            <!-- Normal publish action -->
            <template v-else>
                <Button type="button" variant="primary" :disabled="!summary.can_publish" @click="emit('openWizard')">
                    Lanjut terbitkan proyek
                </Button>

                <span v-if="summary.can_publish" class="text-xs text-content-secondary">
                    Semua baris sudah diperiksa.
                </span>

                <span v-else class="text-xs font-medium text-negative">
                    <template v-if="summary.publish_block_reason === 'has_yellow_rows'">
                        Periksa {{ summary.needs_check_count }} baris kuning untuk menerbitkan
                    </template>
                    <template v-else-if="summary.publish_block_reason === 'unacknowledged_diff'">
                        Centang konfirmasi selisih total untuk menerbitkan
                    </template>
                    <template v-else-if="summary.publish_block_reason === 'no_items'">
                        Tambahkan rincian belanja terlebih dahulu
                    </template>
                    <template v-else-if="summary.publish_block_reason === 'reading'"> Dokumen sedang dibaca… </template>
                    <template v-else-if="summary.publish_block_reason === 'read_failed'">
                        Dokumen belum terbaca. Coba lagi atau isi manual.
                    </template>
                    <template v-else>Lengkapi syarat sebelum menerbitkan</template>
                </span>
            </template>
        </div>
    </div>
</template>
