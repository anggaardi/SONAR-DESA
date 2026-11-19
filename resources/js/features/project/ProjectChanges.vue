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
    <section v-if="changes && changes.length > 0" class="mt-8 border-t border-stroke pt-6">
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
                class="rounded-lg border border-stroke bg-surface p-3 text-sm"
            >
                <div class="flex items-center justify-between gap-2 text-content-secondary">
                    <span class="font-medium text-content">{{ change.item_label }}</span>
                    <span>{{ formatDate(change.changed_at) }}</span>
                </div>
                <p class="mt-1 text-content-secondary">
                    Perubahan pada <span class="font-medium text-content">{{ change.field }}</span>:
                </p>
                <div class="mt-1 flex flex-wrap items-center gap-2 rounded-md bg-surface-subtle p-2 tabular-nums">
                    <span class="text-content-secondary line-through">{{ change.old_value }}</span>
                    <span class="text-content-tertiary" aria-hidden="true">&rarr;</span>
                    <span class="font-semibold text-content">{{ change.new_value }}</span>
                </div>
            </div>
        </div>
    </section>
</template>
