<script setup lang="ts">
import { computed } from 'vue';
import { formatRupiahShort } from '@/lib/format';

interface Props {
    projectCount: number;
    totalBudgetRp: number;
    yearText?: string;
}

const props = withDefaults(defineProps<Props>(), {
    yearText: '2026',
});

const formattedBudget = computed(() => formatRupiahShort(props.totalBudgetRp));
</script>

<template>
    <div class="rounded-2xl border border-stroke bg-surface p-4 shadow-xs">
        <div class="grid grid-cols-2 divide-x divide-stroke">
            <!-- Col 1: Proyek -->
            <div class="pr-3">
                <div class="flex items-center gap-2">
                    <svg
                        class="h-5 w-5 text-primary"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M3 21h18M5 21V10l7-5 7 5v11M10 21v-5h4v5M10 11h4"
                        />
                    </svg>
                    <span class="text-base font-bold text-content tabular-nums sm:text-lg">
                        {{ projectCount }} proyek
                    </span>
                </div>
                <p class="mt-1 text-xs text-content-secondary">Terbit tahun {{ yearText }}</p>
            </div>

            <!-- Col 2: Anggaran -->
            <div class="pl-4">
                <div class="flex items-center gap-2">
                    <svg
                        class="h-5 w-5 text-primary"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M19 7V4a1 1 0 00-1-1H5a2 2 0 000 4h15a1 1 0 011 1v4h-3a2 2 0 000 4h3a1 1 0 001-1v-2a1 1 0 00-1-1M3 5v14a2 2 0 002 2h15a1 1 0 001-1v-4"
                        />
                    </svg>
                    <span class="text-base font-bold text-content tabular-nums sm:text-lg">
                        {{ formattedBudget }}
                    </span>
                </div>
                <p class="mt-1 text-xs text-content-secondary">Total nilai RAB</p>
            </div>
        </div>
    </div>
</template>
