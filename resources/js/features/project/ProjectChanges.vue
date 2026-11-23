<script setup lang="ts">
// ProjectChanges: collapsible change history section (Langkah 6).
// Only shown when there are recorded changes.

import { ref } from 'vue';
import { formatDate } from '@/lib/format';
import type { ProjectDetail } from '@/types/project';

interface Props {
    changes: ProjectDetail['changes'];
}

defineProps<Props>();

const showHistory = ref(false);
</script>

<template>
    <section v-if="changes && changes.length > 0" id="dokumen" class="mt-8 scroll-mt-20 border-t border-stroke pt-6">
        <button
            type="button"
            class="flex min-h-11 w-full items-center justify-between gap-3 text-left"
            :aria-expanded="showHistory"
            @click="showHistory = !showHistory"
        >
            <div>
                <h2 class="text-xl font-semibold text-content">Riwayat perubahan ({{ changes.length }})</h2>
                <p class="text-sm text-content-secondary">Pembaruan data setelah proyek diterbitkan</p>
            </div>
            <span class="shrink-0 text-sm font-medium text-primary">
                {{ showHistory ? 'Sembunyikan' : 'Lihat riwayat' }}
            </span>
        </button>

        <div v-if="showHistory" class="mt-4 flex flex-col gap-2">
            <div
                v-for="change in changes"
                :key="change.id"
                class="rounded-xl border border-stroke bg-surface p-3 text-sm shadow-2xs"
            >
                <div class="flex items-center justify-between gap-2 text-xs text-content-secondary sm:text-sm">
                    <span class="font-medium text-content">{{ change.item_label }}</span>
                    <span>{{ formatDate(change.changed_at) }}</span>
                </div>
                <p class="mt-1 text-xs text-content-secondary sm:text-sm">
                    Perubahan pada <span class="font-medium text-content">{{ change.field }}</span>:
                </p>
                <div
                    class="mt-1 flex flex-wrap items-center gap-2 rounded-lg bg-surface-subtle p-2 text-xs tabular-nums sm:text-sm"
                >
                    <span class="text-content-secondary line-through">{{ change.old_value }}</span>
                    <span class="text-content-tertiary" aria-hidden="true">&rarr;</span>
                    <span class="font-semibold text-content">{{ change.new_value }}</span>
                </div>
                <p v-if="change.reason" class="mt-1.5 text-xs text-content-secondary italic">
                    Alasan: {{ change.reason }}
                </p>
            </div>
        </div>
    </section>
</template>
