<script setup lang="ts">
// ProjectItems: filterable RAB item list with detail drawer. PRD P3, P4 (Langkah 3).

import { computed, ref } from 'vue';
import { formatPercentAbove, formatRupiah } from '@/lib/format';
import { priceLabel as priceLabelTexts, priceStandardSource } from '@/lib/labels';
import type { ProjectBudgetItem, ProjectDetail } from '@/types/project';

interface Props {
    project: ProjectDetail;
}

const props = defineProps<Props>();

type Filter = 'all' | 'above' | 'no_ref';

const selectedFilter = ref<Filter>('all');
const activeItem = ref<ProjectBudgetItem | null>(null);

const filteredItems = computed(() => {
    if (selectedFilter.value === 'above') {
        return props.project.items.filter((i) => i.label === 'above_standard');
    }
    if (selectedFilter.value === 'no_ref') {
        return props.project.items.filter((i) => i.label === 'no_reference');
    }
    return props.project.items;
});

type FilterOption = { value: Filter; label: string; count: number };

const filterOptions = computed<FilterOption[]>(() => [
    { value: 'all', label: 'Semua', count: props.project.items.length },
    { value: 'above', label: 'Di atas standar', count: props.project.label_counts.above_standard },
    { value: 'no_ref', label: 'Tanpa acuan', count: props.project.label_counts.no_reference },
]);
</script>

<template>
    <section class="mt-8">
        <h2 class="text-xl font-semibold text-content">Rincian anggaran</h2>
        <p class="text-sm text-content-secondary">Ketuk baris untuk melihat perbandingan acuan</p>

        <!-- Filter tabs (P4) -->
        <div class="mt-3 flex gap-2 overflow-x-auto pb-1 text-sm" role="group" aria-label="Saring item">
            <button
                v-for="opt in filterOptions"
                :key="opt.value"
                type="button"
                class="h-11 shrink-0 rounded-full border px-4 font-medium transition-colors"
                :class="
                    selectedFilter === opt.value
                        ? 'border-primary bg-primary-subtle text-primary'
                        : 'border-stroke bg-surface text-content-secondary hover:bg-surface-muted'
                "
                :aria-pressed="selectedFilter === opt.value"
                @click="selectedFilter = opt.value"
            >
                {{ opt.label }} ({{ opt.count }})
            </button>
        </div>

        <!-- Item list -->
        <div class="mt-3 flex flex-col gap-2">
            <div
                v-if="filteredItems.length === 0"
                class="rounded-lg border border-dashed border-stroke-strong p-6 text-center text-sm text-content-secondary"
            >
                Tidak ada item untuk filter ini.
            </div>

            <button
                v-for="item in filteredItems"
                :key="item.id"
                type="button"
                class="flex min-h-14 w-full items-center justify-between gap-3 rounded-lg border border-stroke bg-surface p-3 text-left transition-colors hover:bg-surface-muted"
                :class="{ 'bg-warning-subtle': item.label === 'needs_check' }"
                @click="activeItem = item"
            >
                <div class="min-w-0">
                    <p class="text-base font-medium text-content">{{ item.name }}</p>
                    <p class="mt-0.5 text-sm text-content-secondary tabular-nums">
                        {{ item.volume }} {{ item.unit }}
                        <span v-if="item.unit_price_rp">&middot; @{{ formatRupiah(item.unit_price_rp) }}</span>
                    </p>
                </div>

                <div class="flex shrink-0 flex-col items-end gap-1">
                    <span class="text-base font-semibold text-content tabular-nums">
                        {{ item.amount_rp ? formatRupiah(item.amount_rp) : '-' }}
                    </span>
                    <span
                        v-if="item.label === 'above_standard' && item.over_pct"
                        class="rounded-full border border-negative-border bg-negative-subtle px-2 py-0.5 text-sm font-medium text-negative"
                    >
                        {{ priceLabelTexts.above_standard(item.over_pct) }}
                    </span>
                    <span
                        v-else-if="item.label === 'no_reference'"
                        class="rounded-full border border-stroke bg-surface-muted px-2 py-0.5 text-sm font-medium text-content-secondary"
                    >
                        {{ priceLabelTexts.no_reference }}
                    </span>
                    <span
                        v-else-if="item.label === 'within_standard'"
                        class="rounded-full border border-positive-border bg-positive-subtle px-2 py-0.5 text-sm font-medium text-positive"
                    >
                        {{ priceLabelTexts.within_standard }}
                    </span>
                </div>
            </button>
        </div>
    </section>

    <!-- Item detail drawer (P3, P4) -->
    <div
        v-if="activeItem"
        class="fixed inset-0 z-50 flex items-end justify-center bg-content/50 sm:items-center sm:p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="modal-item-title"
        @click.self="activeItem = null"
    >
        <div
            class="max-h-[90vh] w-full max-w-md overflow-y-auto rounded-t-lg bg-surface p-4 shadow-sm sm:rounded-lg sm:p-6"
        >
            <div class="flex items-start justify-between gap-3 border-b border-stroke pb-3">
                <div>
                    <p class="text-sm text-content-secondary">Baris {{ activeItem.position }}</p>
                    <h3 id="modal-item-title" class="mt-0.5 text-xl font-semibold text-content">
                        {{ activeItem.name }}
                    </h3>
                </div>
                <button
                    type="button"
                    class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-md text-content-secondary hover:bg-surface-muted hover:text-content focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                    aria-label="Tutup panel detail"
                    @click="activeItem = null"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M6 18L18 6M6 6l12 12"
                        />
                    </svg>
                </button>
            </div>

            <!-- Price comparison -->
            <div class="mt-4 flex flex-col gap-3 text-sm">
                <dl class="grid grid-cols-2 gap-3 rounded-md bg-surface-subtle p-3">
                    <div>
                        <dt class="text-content-secondary">Harga satuan RAB</dt>
                        <dd class="mt-0.5 text-base font-semibold text-content tabular-nums">
                            {{ activeItem.unit_price_rp ? formatRupiah(activeItem.unit_price_rp) : '-' }}
                        </dd>
                        <dd class="text-xs text-content-secondary">per {{ activeItem.unit }}</dd>
                    </div>
                    <div>
                        <dt class="text-content-secondary">Standar acuan kab.</dt>
                        <dd class="mt-0.5 text-base font-semibold text-content tabular-nums">
                            {{ activeItem.standard_price_rp ? formatRupiah(activeItem.standard_price_rp) : '-' }}
                        </dd>
                        <dd class="text-xs text-content-secondary">
                            {{ activeItem.standard_price_rp ? `per ${activeItem.unit}` : 'Tidak ada di daftar' }}
                        </dd>
                    </div>
                </dl>

                <dl class="grid grid-cols-2 gap-3 rounded-md bg-surface-subtle p-3">
                    <div>
                        <dt class="text-content-secondary">Volume</dt>
                        <dd class="mt-0.5 text-base font-semibold text-content tabular-nums">
                            {{ activeItem.volume }} {{ activeItem.unit }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-content-secondary">Total di RAB</dt>
                        <dd class="mt-0.5 text-base font-semibold text-content tabular-nums">
                            {{ activeItem.amount_rp ? formatRupiah(activeItem.amount_rp) : '-' }}
                        </dd>
                    </div>
                </dl>

                <!-- Diff block: above_standard only -->
                <div
                    v-if="activeItem.label === 'above_standard' && activeItem.unit_diff_rp"
                    class="rounded-md border border-negative-border bg-negative-subtle p-3 text-negative"
                >
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-medium">Selisih per {{ activeItem.unit }}</span>
                        <span class="font-semibold tabular-nums">+{{ formatRupiah(activeItem.unit_diff_rp) }}</span>
                    </div>
                    <div
                        v-if="activeItem.total_diff_rp"
                        class="mt-1.5 flex items-center justify-between gap-2 border-t border-negative-border pt-1.5"
                    >
                        <span class="font-medium">Total selisih anggaran</span>
                        <span class="text-base font-semibold tabular-nums"
                            >+{{ formatRupiah(activeItem.total_diff_rp) }}</span
                        >
                    </div>
                </div>

                <!-- Neutral explanation (PRD 4.3) -->
                <div class="rounded-md border border-stroke bg-surface p-3 text-content-secondary">
                    <p class="font-semibold text-content">Keterangan</p>

                    <div v-if="activeItem.label === 'above_standard'" class="mt-1.5 flex flex-col gap-2">
                        <p>
                            Harga di RAB {{ formatPercentAbove(activeItem.over_pct ?? 0) }} di atas standar harga
                            Kabupaten {{ project.village.regency }} {{ project.year }}.
                            <span class="font-medium text-content">
                                Belum tentu salah: BPD dapat meminta penjelasan resmi dari pemerintah desa.
                            </span>
                        </p>
                        <div v-if="activeItem.explanation" class="rounded-md bg-info-subtle p-2.5 text-info">
                            <p class="font-semibold">Catatan dari Pemerintah Desa:</p>
                            <p class="mt-0.5">&ldquo;{{ activeItem.explanation }}&rdquo;</p>
                        </div>
                    </div>

                    <p v-else-if="activeItem.label === 'no_reference'" class="mt-1.5">
                        Item ini tidak ada di daftar standar harga Kabupaten {{ project.village.regency }}
                        {{ project.year }}, sehingga tidak dinilai selisihnya.
                    </p>

                    <p v-else class="mt-1.5">
                        Harga satuan pada item ini berada dalam batas wajar standar harga daerah.
                    </p>

                    <p class="mt-3 text-xs text-content-tertiary">
                        Sumber: {{ activeItem.standard_source || priceStandardSource(project.year) }}
                    </p>
                </div>
            </div>

            <button
                type="button"
                class="mt-5 h-11 w-full rounded-md border border-stroke-strong bg-surface text-base font-medium text-content transition-colors hover:bg-surface-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                @click="activeItem = null"
            >
                Tutup
            </button>
        </div>
    </div>
</template>
