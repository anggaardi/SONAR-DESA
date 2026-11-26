<script setup lang="ts">
import Badge from '@/components/ui/Badge.vue';
import Card from '@/components/ui/Card.vue';
import { formatRupiah } from '@/lib/format';

interface Props {
    writtenTotalRp: number | null;
    calculatedTotalRp: number;
    diffRp: number;
    isMatch: boolean;
    totalAck: boolean;
    isAcking: boolean;
}

defineProps<Props>();

const emit = defineEmits<{
    ackTotal: [];
}>();
</script>

<template>
    <Card padding="base">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="text-sm text-content">
                <template v-if="isMatch">
                    <span>
                        Total di dokumen
                        <strong class="tabular-nums">{{ formatRupiah(writtenTotalRp ?? calculatedTotalRp) }}</strong>,
                        jumlah baris <strong class="tabular-nums">{{ formatRupiah(calculatedTotalRp) }}</strong>
                    </span>
                    <Badge color="positive" class="ml-2">Sesuai</Badge>
                </template>
                <template v-else>
                    <span>
                        Total di dokumen <strong class="tabular-nums">{{ formatRupiah(writtenTotalRp ?? 0) }}</strong>,
                        jumlah baris <strong class="tabular-nums">{{ formatRupiah(calculatedTotalRp) }}</strong> (beda
                        <strong class="tabular-nums">{{ formatRupiah(diffRp) }}</strong>).
                    </span>
                    <Badge v-if="totalAck" color="positive" class="ml-2">Sudah dikonfirmasi</Badge>
                </template>
            </div>

            <label
                v-if="!isMatch && !totalAck"
                class="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-md border border-stroke-strong bg-surface px-3 py-2 text-sm font-medium text-content transition-colors hover:bg-surface-muted"
            >
                <input
                    type="checkbox"
                    class="h-4 w-4 rounded-sm border-stroke-strong accent-primary"
                    :disabled="isAcking"
                    @change="emit('ackTotal')"
                />
                <span>{{ isAcking ? 'Menyimpan...' : 'Saya sudah memeriksa selisih total ini' }}</span>
            </label>
        </div>
    </Card>
</template>
