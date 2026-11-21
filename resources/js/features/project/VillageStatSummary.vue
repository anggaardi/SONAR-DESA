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
    citizenCount: 27,
});

const formattedBudget = computed(() => formatRupiah(props.totalBudgetRp));
</script>

<template>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3 md:gap-5">
        <!-- Card 1: Proyek Fisik -->
        <div class="flex items-center gap-4 rounded-2xl border border-stroke bg-surface p-4.5 shadow-xs">
            <div
                class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-primary-subtle text-primary"
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
            <div class="min-w-0">
                <p class="text-lg font-bold text-content tabular-nums sm:text-xl">{{ projectCount }} Proyek Fisik</p>
                <p class="mt-0.5 text-xs text-content-secondary">Terbit tahun anggaran {{ yearText }}</p>
            </div>
        </div>

        <!-- Card 2: Total Nilai Anggaran -->
        <div class="flex items-center gap-4 rounded-2xl border border-stroke bg-surface p-4.5 shadow-xs">
            <div
                class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-primary-subtle text-primary"
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
            <div class="min-w-0">
                <p class="text-lg font-bold text-content tabular-nums sm:text-xl">
                    {{ formattedBudget }}
                </p>
                <p class="mt-0.5 text-xs text-content-secondary">Total nilai anggaran RAB</p>
            </div>
        </div>

        <!-- Card 3: Warga Terlibat -->
        <div
            class="flex items-center gap-4 rounded-2xl border border-stroke bg-surface p-4.5 shadow-xs sm:col-span-2 md:col-span-1"
        >
            <div
                class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-primary-subtle text-primary"
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
            <div class="min-w-0">
                <p class="text-lg font-bold text-content tabular-nums sm:text-xl">{{ citizenCount }} Warga Terlibat</p>
                <p class="mt-0.5 text-xs text-content-secondary">Ikut memverifikasi spek di lapangan</p>
            </div>
        </div>
    </div>
</template>
