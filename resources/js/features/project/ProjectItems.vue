<script setup lang="ts">
// ProjectItems: perbandingan baris RAB dengan harga standar. PRD P3, P4 (Langkah 3).
// Tiga kotak angka sekaligus jadi filter; baris membuka bottom sheet perbandingan harga.

import { computed, ref, watch } from 'vue';
import Button from '@/components/ui/Button.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import PriceLabel from '@/components/ui/PriceLabel.vue';
import { formatPercentAbove, formatRupiah, formatRupiahShort } from '@/lib/format';
import { document as documentLabels, priceStandardSource } from '@/lib/labels';
import type { ProjectBudgetItem, ProjectDetail } from '@/types/project';

export type BudgetFilter = 'all' | 'above' | 'within' | 'no_ref';

type ItemLabel = 'within_standard' | 'above_standard' | 'needs_check' | 'no_reference';

interface Props {
    project: ProjectDetail;
    activeFilter?: BudgetFilter;
}

const props = withDefaults(defineProps<Props>(), {
    activeFilter: 'all',
});

const emit = defineEmits<{
    'update:activeFilter': [filter: BudgetFilter];
}>();

const selectedFilter = ref<BudgetFilter>(props.activeFilter);
const activeItem = ref<ProjectBudgetItem | null>(null);

watch(
    () => props.activeFilter,
    (value) => {
        selectedFilter.value = value;
    },
);

function setFilter(value: BudgetFilter): void {
    const next = selectedFilter.value === value ? 'all' : value;
    selectedFilter.value = next;
    emit('update:activeFilter', next);
}

const filterLabel: Record<Exclude<BudgetFilter, 'all'>, ItemLabel> = {
    above: 'above_standard',
    within: 'within_standard',
    no_ref: 'no_reference',
};

const filteredItems = computed(() => {
    if (selectedFilter.value === 'all') {
        return props.project.items;
    }
    return props.project.items.filter((i) => i.label === filterLabel[selectedFilter.value as keyof typeof filterLabel]);
});

const tiles = computed(() => [
    {
        value: 'above' as const,
        label: 'Di atas standar',
        count: props.project.label_counts.above_standard,
        classes: 'border-negative-border bg-negative-subtle text-negative',
        ring: 'ring-negative',
        icon: 'M12 19V5m-6 6 6-6 6 6',
    },
    {
        value: 'within' as const,
        label: 'Sesuai',
        count: props.project.label_counts.within_standard,
        classes: 'border-positive-border bg-positive-subtle text-positive',
        ring: 'ring-positive',
        icon: 'M5 12.5l4.5 4.5L19 7.5',
    },
    {
        value: 'no_ref' as const,
        label: 'Tanpa acuan',
        count: props.project.label_counts.no_reference,
        classes: 'border-stroke bg-surface-muted text-content-secondary',
        ring: 'ring-content-secondary',
        icon: 'M5 12h14',
    },
]);

const standardSource = computed(
    () =>
        props.project.items.find((i) => i.standard_source)?.standard_source ?? priceStandardSource(props.project.year),
);

const comparison = computed(() => {
    const item = activeItem.value;
    if (!item?.unit_price_rp || !item.standard_price_rp) {
        return null;
    }
    const max = Math.max(item.unit_price_rp, item.standard_price_rp);
    return {
        rabWidth: `${(item.unit_price_rp / max) * 100}%`,
        standardWidth: `${(item.standard_price_rp / max) * 100}%`,
    };
});

/** Selisih total = selisih per satuan × volume, rumus yang sama dengan yang tertulis di layar. */
const totalDiff = computed(() => {
    const item = activeItem.value;
    const volume = Number(item?.volume);
    if (!item?.unit_diff_rp || !item.volume || Number.isNaN(volume)) {
        return null;
    }
    return Math.round(item.unit_diff_rp * volume);
});

function percentAbove(item: ProjectBudgetItem): string | null {
    return item.over_pct ? formatPercentAbove(item.over_pct) : null;
}
</script>

<template>
    <section id="rincian" class="scroll-mt-20">
        <h2 class="text-xl font-semibold text-content">Dibandingkan harga standar</h2>
        <p class="text-sm text-content-secondary">Acuan: {{ standardSource }}</p>

        <div class="mt-3 grid grid-cols-3 gap-2.5" role="group" aria-label="Saring baris RAB menurut label harga">
            <button
                v-for="tile in tiles"
                :key="tile.value"
                type="button"
                class="flex min-h-20 flex-col justify-between rounded-2xl border p-3 text-left transition-shadow focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                :class="[tile.classes, selectedFilter === tile.value ? `ring-2 ring-offset-1 ${tile.ring}` : '']"
                :aria-pressed="selectedFilter === tile.value"
                @click="setFilter(tile.value)"
            >
                <span class="flex w-full items-start justify-between">
                    <span class="text-2xl leading-none font-bold tabular-nums">{{ tile.count }}</span>
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" :d="tile.icon" />
                    </svg>
                </span>
                <span class="mt-2 text-xs leading-tight font-medium sm:text-sm">{{ tile.label }}</span>
            </button>
        </div>

        <p class="mt-3 flex items-start gap-2 text-sm text-content-secondary">
            <svg
                class="mt-0.5 h-4 w-4 shrink-0"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                aria-hidden="true"
            >
                <circle cx="12" cy="12" r="9" stroke-width="2" />
                <path stroke-linecap="round" stroke-width="2" d="M12 11v5M12 8h.01" />
            </svg>
            <span>"Di atas standar" belum tentu salah. Ketuk barisnya untuk melihat perbandingan harganya.</span>
        </p>

        <div class="mt-3 flex items-center justify-between text-sm">
            <p class="text-content-secondary">
                Menampilkan <span class="font-semibold text-content tabular-nums">{{ filteredItems.length }}</span> dari
                {{ project.items.length }} baris
            </p>
            <button
                v-if="selectedFilter !== 'all'"
                type="button"
                class="min-h-11 font-semibold text-primary hover:text-primary-hover"
                @click="setFilter('all')"
            >
                Tampilkan semua
            </button>
        </div>

        <div v-if="filteredItems.length === 0" class="mt-2 rounded-lg border border-stroke bg-surface px-4">
            <EmptyState
                illustration="search-empty"
                compact
                title="Tidak ada baris untuk label ini."
                description="Pilih label harga lainnya atau tampilkan semua baris belanja."
            >
                <Button v-if="selectedFilter !== 'all'" variant="secondary" size="sm" @click="setFilter('all')">
                    Tampilkan semua
                </Button>
            </EmptyState>
        </div>

        <template v-else>
            <!-- Desktop Tabular Table (md:block) ala FigCraft desk-proyek -->
            <div class="mt-3 hidden overflow-hidden rounded-2xl border border-stroke bg-surface shadow-2xs md:block">
                <table class="w-full text-left text-sm" role="table">
                    <thead
                        class="border-b border-stroke bg-surface-subtle text-xs font-semibold tracking-wider text-content-secondary uppercase"
                    >
                        <tr>
                            <th scope="col" class="py-3 pr-3 pl-4">Uraian Belanja</th>
                            <th scope="col" class="px-3 py-3">Volume</th>
                            <th scope="col" class="px-3 py-3 text-right">Harga Satuan</th>
                            <th scope="col" class="px-3 py-3 text-right">Total RAB</th>
                            <th scope="col" class="px-3 py-3 text-right">Standar Kab.</th>
                            <th scope="col" class="py-3 pr-4 pl-3 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stroke">
                        <tr
                            v-for="item in filteredItems"
                            :key="item.id"
                            class="cursor-pointer transition-colors hover:bg-surface-subtle focus-visible:bg-surface-subtle"
                            :class="{ 'bg-warning-subtle/50': item.label === 'needs_check' }"
                            tabindex="0"
                            role="button"
                            :aria-label="`Buka perbandingan harga ${item.name}`"
                            @click="activeItem = item"
                            @keydown.enter="activeItem = item"
                            @keydown.space.prevent="activeItem = item"
                        >
                            <td class="py-3.5 pr-3 pl-4">
                                <p class="font-semibold text-content">{{ item.name }}</p>
                            </td>
                            <td class="px-3 py-3.5 text-content-secondary tabular-nums">
                                {{ item.volume }} {{ item.unit }}
                            </td>
                            <td class="px-3 py-3.5 text-right font-medium text-content tabular-nums">
                                {{ item.unit_price_rp ? formatRupiah(item.unit_price_rp) : '-' }}
                            </td>
                            <td class="px-3 py-3.5 text-right font-bold text-content tabular-nums">
                                {{ item.amount_rp ? formatRupiah(item.amount_rp) : '-' }}
                            </td>
                            <td class="px-3 py-3.5 text-right text-content-secondary tabular-nums">
                                {{ item.standard_price_rp ? formatRupiah(item.standard_price_rp) : '-' }}
                            </td>
                            <td class="py-3.5 pr-4 pl-3 text-center">
                                <PriceLabel :label="item.label as ItemLabel" :percent-above="percentAbove(item)" />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Mobile Compact List (md:hidden) -->
            <ul
                class="mt-2 divide-y divide-stroke overflow-hidden rounded-2xl border border-stroke bg-surface shadow-2xs md:hidden"
            >
                <li v-for="item in filteredItems" :key="item.id">
                    <button
                        type="button"
                        class="flex w-full items-center gap-3 p-4 text-left transition-colors hover:bg-surface-muted focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-primary"
                        :class="{ 'bg-warning-subtle': item.label === 'needs_check' }"
                        @click="activeItem = item"
                    >
                        <span class="min-w-0 flex-1">
                            <span class="flex items-start justify-between gap-3">
                                <span class="text-base font-semibold text-content">{{ item.name }}</span>
                                <span class="shrink-0 text-base font-bold text-content tabular-nums">
                                    {{ item.amount_rp ? formatRupiah(item.amount_rp) : '-' }}
                                </span>
                            </span>
                            <span
                                class="mt-0.5 flex items-start justify-between gap-3 text-sm text-content-secondary tabular-nums"
                            >
                                <span>
                                    {{ item.volume }} {{ item.unit }}
                                    <template v-if="item.unit_price_rp"
                                        >&times; {{ formatRupiah(item.unit_price_rp) }}</template
                                    >
                                </span>
                                <span
                                    v-if="item.label === 'above_standard' && item.standard_price_rp"
                                    class="shrink-0 text-xs text-content-tertiary"
                                >
                                    Standar {{ formatRupiah(item.standard_price_rp) }}
                                </span>
                            </span>
                            <PriceLabel
                                class="mt-2"
                                :label="item.label as ItemLabel"
                                :percent-above="percentAbove(item)"
                            />
                        </span>
                        <svg
                            class="h-5 w-5 shrink-0 text-content-tertiary"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            aria-hidden="true"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 6l6 6-6 6" />
                        </svg>
                    </button>
                </li>
            </ul>

            <!-- Catatan Klarifikasi Harga Standar (ala FigCraft desk-proyek) -->
            <div
                class="mt-4 flex items-start gap-3 rounded-xl border border-stroke bg-surface-subtle p-3.5 text-xs text-content-secondary"
            >
                <svg class="h-5 w-5 shrink-0 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                    />
                </svg>
                <div class="leading-relaxed">
                    <strong class="font-semibold text-content">Catatan Analisis Standar Harga:</strong>
                    Harga yang berada di atas standar daerah belum tentu kesalahan atau penyimpangan. Desa dapat
                    melampirkan nota klarifikasi kondisi lapangan (seperti biaya langsir medan tanjakan/terjal) yang
                    telah diverifikasi dan disahkan oleh BPD.
                </div>
            </div>
        </template>

        <div v-if="project.document_url" class="mt-4 text-center">
            <a
                :href="project.document_url"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-xl border border-stroke-strong bg-surface px-4 text-base font-medium text-content transition-colors hover:bg-surface-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
            >
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8zM14 3v5h5M9 13h6M9 17h4"
                    />
                </svg>
                {{ documentLabels.viewOriginal }}
            </a>
            <p class="mt-2 text-xs text-content-tertiary">{{ documentLabels.readFrom }}</p>
        </div>
    </section>

    <!-- Bottom sheet perbandingan harga (P3, P4) -->
    <div
        v-if="activeItem"
        class="fixed inset-0 z-50 flex items-end justify-center bg-content/50 sm:items-center sm:p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="modal-item-title"
        @click.self="activeItem = null"
    >
        <div
            class="max-h-[90vh] w-full max-w-md overflow-y-auto rounded-t-3xl bg-surface p-5 shadow-md sm:rounded-2xl sm:p-6"
        >
            <div class="mx-auto mb-4 h-1 w-10 rounded-full bg-stroke sm:hidden" aria-hidden="true" />

            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <h3 id="modal-item-title" class="text-xl font-bold text-content">{{ activeItem.name }}</h3>
                    <p class="mt-1 text-sm text-content-secondary tabular-nums">
                        Baris {{ activeItem.position }} &middot; {{ activeItem.volume }} {{ activeItem.unit }}
                        <template v-if="activeItem.amount_rp"
                            >&middot; {{ formatRupiah(activeItem.amount_rp) }}</template
                        >
                    </p>
                </div>
                <button
                    type="button"
                    class="-mt-1 -mr-2 inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-content-secondary hover:bg-surface-muted hover:text-content focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                    aria-label="Tutup perbandingan harga"
                    @click="activeItem = null"
                >
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M6 18L18 6M6 6l12 12"
                        />
                    </svg>
                </button>
            </div>

            <PriceLabel class="mt-3" :label="activeItem.label as ItemLabel" :percent-above="percentAbove(activeItem)" />

            <!-- Dua batang harga satuan -->
            <div v-if="comparison" class="mt-5">
                <p class="text-sm font-semibold text-content">Harga satuan</p>
                <div class="mt-3 flex flex-col gap-4">
                    <div>
                        <div class="flex items-baseline justify-between gap-3 text-sm">
                            <span class="text-content-secondary">Di RAB desa</span>
                            <span class="text-base font-bold text-content tabular-nums">
                                {{ formatRupiah(activeItem.unit_price_rp ?? 0) }} / {{ activeItem.unit }}
                            </span>
                        </div>
                        <div class="mt-2 h-3 rounded-full bg-surface-muted">
                            <div
                                class="h-full rounded-full bg-content-secondary"
                                :style="{ width: comparison.rabWidth }"
                            />
                        </div>
                    </div>
                    <div>
                        <div class="flex items-baseline justify-between gap-3 text-sm">
                            <span class="text-content-secondary">Harga standar</span>
                            <span class="text-base font-bold text-content tabular-nums">
                                {{ formatRupiah(activeItem.standard_price_rp ?? 0) }} / {{ activeItem.unit }}
                            </span>
                        </div>
                        <div class="mt-2 h-3 rounded-full bg-surface-muted">
                            <div class="h-full rounded-full bg-primary" :style="{ width: comparison.standardWidth }" />
                        </div>
                    </div>
                </div>
            </div>

            <div
                v-if="activeItem.label === 'above_standard' && totalDiff !== null"
                class="mt-5 rounded-xl border border-stroke bg-surface-subtle p-3.5"
            >
                <p class="text-sm text-content-secondary tabular-nums">
                    Selisih {{ formatRupiah(activeItem.unit_diff_rp ?? 0) }} per {{ activeItem.unit }} &times;
                    {{ activeItem.volume }} {{ activeItem.unit }}
                </p>
                <p class="mt-0.5 text-base font-semibold text-content tabular-nums">
                    {{ formatRupiahShort(totalDiff ?? 0) }} lebih tinggi dari standar
                </p>
            </div>

            <div class="mt-5">
                <p class="text-sm font-semibold text-content">Kenapa label ini?</p>
                <p class="mt-2 border-l-4 border-info-border pl-3 text-base leading-relaxed text-content">
                    <template v-if="activeItem.explanation">{{ activeItem.explanation }}</template>
                    <template v-else-if="activeItem.label === 'within_standard'">
                        Harga satuan di RAB sama dengan atau di bawah standar harga resmi pemerintah daerah.
                    </template>
                    <template v-else-if="activeItem.label === 'needs_check'">
                        Baris ini belum bisa dibaca dengan yakin dari dokumen. Cocokkan dengan dokumen RAB asli.
                    </template>
                    <template v-else>
                        Item ini tidak tercantum dalam standar harga daerah tahun ini, jadi tidak dinilai.
                    </template>
                </p>
            </div>

            <p v-if="activeItem.standard_source" class="mt-4 text-xs text-content-tertiary">
                Acuan: {{ activeItem.standard_source }}
            </p>

            <button
                type="button"
                class="mt-6 inline-flex h-12 w-full items-center justify-center rounded-xl border border-stroke-strong bg-surface text-base font-medium text-content hover:bg-surface-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                @click="activeItem = null"
            >
                Mengerti
            </button>
        </div>
    </div>
</template>
