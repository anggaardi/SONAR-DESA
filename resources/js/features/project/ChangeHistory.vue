<script setup lang="ts">
import Card from '@/components/ui/Card.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import { formatDateTime } from '@/lib/format';

export interface ProjectChangeItem {
    id: number;
    field: string;
    item_label: string;
    old_value: string;
    new_value: string;
    changed_at: string;
}

interface Props {
    changes: ProjectChangeItem[];
}

defineProps<Props>();
</script>

<template>
    <Card padding="app">
        <div>
            <h2 class="text-base font-semibold text-content">Riwayat perubahan</h2>
            <p class="mt-0.5 text-xs text-content-secondary">
                Semua perubahan setelah terbit. Riwayat ini juga tampil di halaman warga dan tidak bisa dihapus.
            </p>
        </div>

        <div v-if="changes.length === 0" class="mt-4">
            <EmptyState
                title="Belum ada riwayat perubahan"
                description="Proyek ini belum memiliki perubahan (addendum/CCO) atau koreksi progres."
            />
        </div>

        <ul v-else class="mt-4 divide-y divide-stroke border-t border-stroke">
            <li v-for="change in changes" :key="change.id" class="space-y-1.5 py-3 text-sm">
                <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-content-secondary">
                    <span class="font-semibold text-content">{{ change.item_label }}</span>
                    <span>{{ formatDateTime(change.changed_at) }}</span>
                </div>
                <p class="text-xs text-content-secondary">
                    Perubahan pada <span class="font-medium text-content">{{ change.field }}</span>:
                </p>
                <div class="flex flex-wrap items-center gap-2 rounded-md bg-surface-subtle p-2 text-xs tabular-nums">
                    <span class="text-content-secondary line-through">{{ change.old_value }}</span>
                    <span class="text-content-tertiary" aria-hidden="true">&rarr;</span>
                    <span class="font-semibold text-content">{{ change.new_value }}</span>
                </div>
            </li>
        </ul>
    </Card>
</template>
