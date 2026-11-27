<script setup lang="ts">
import { computed } from 'vue';
import { formatRupiah } from '@/lib/format';

interface Props {
    projectCount: number;
    totalBudgetRp: number;
    yearText?: string;
    citizenCount?: number;
}

const props = withDefaults(defineProps<Props>(), {
    yearText: '2026',
    citizenCount: 0,
});

const formattedBudget = computed(() => formatRupiah(props.totalBudgetRp));
</script>

<template>
    <div class="@container">
        <div class="grid grid-cols-1 gap-4 @lg:grid-cols-2 @4xl:grid-cols-3 @4xl:gap-5">
            <!-- Card 1: Proyek Fisik -->
            <div
                class="flex min-w-0 items-center gap-3.5 overflow-hidden rounded-2xl border border-stroke bg-surface p-4 shadow-xs @4xl:gap-4 @4xl:p-4.5"
            >
                <div
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary-subtle text-primary @4xl:h-12 @4xl:w-12"
                    aria-hidden="true"
                >
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"
                        />
                    </svg>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-lg leading-tight font-bold text-content tabular-nums @4xl:text-xl">
                        {{ projectCount }} Proyek Fisik
                    </p>
                    <p class="mt-0.5 text-xs text-content-secondary">Terbit tahun anggaran {{ yearText }}</p>
                </div>
            </div>

            <!-- Card 2: Total Nilai Anggaran -->
            <div
                class="flex min-w-0 items-center gap-3.5 overflow-hidden rounded-2xl border border-stroke bg-surface p-4 shadow-xs @4xl:gap-4 @4xl:p-4.5"
            >
                <div
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary-subtle text-primary @4xl:h-12 @4xl:w-12"
                    aria-hidden="true"
                >
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"
                        />
                    </svg>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-lg leading-tight font-bold break-words text-content tabular-nums @4xl:text-xl">
                        {{ formattedBudget }}
                    </p>
                    <p class="mt-0.5 text-xs text-content-secondary">Total nilai anggaran RAB</p>
                </div>
            </div>

            <!-- Card 3: Warga Terlibat -->
            <div
                class="flex min-w-0 items-center gap-3.5 overflow-hidden rounded-2xl border border-stroke bg-surface p-4 shadow-xs @lg:col-span-2 @4xl:col-span-1 @4xl:gap-4 @4xl:p-4.5"
            >
                <div
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary-subtle text-primary @4xl:h-12 @4xl:w-12"
                    aria-hidden="true"
                >
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"
                        />
                    </svg>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-lg leading-tight font-bold text-content tabular-nums @4xl:text-xl">
                        {{ citizenCount }} Warga Terlibat
                    </p>
                    <p class="mt-0.5 text-xs text-content-secondary">Ikut memverifikasi spek di lapangan</p>
                </div>
            </div>
        </div>
    </div>
</template>
