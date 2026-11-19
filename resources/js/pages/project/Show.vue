<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { formatDate, formatPercentAbove, formatRupiah } from '@/lib/format';
import {
    citizenCount,
    document as documentLabels,
    measurementsInRab,
    priceLabel,
    priceStandardSource,
    projectType,
    publicStatus,
    type ReviewOutcome,
} from '@/lib/labels';
import type { ProjectBudgetItem, ProjectDetail } from '@/types/project';

interface Props {
    project: ProjectDetail;
}

const props = defineProps<Props>();

const selectedFilter = ref<'all' | 'above' | 'no_ref'>('all');
const activeItem = ref<ProjectBudgetItem | null>(null);
const activePhoto = ref<string | null>(null);
const showHistory = ref(false);
const toastMessage = ref<string | null>(null);
let toastTimer: ReturnType<typeof setTimeout> | null = null;

const filteredItems = computed(() => {
    if (selectedFilter.value === 'above') {
        return props.project.items.filter((item) => item.label === 'above_standard');
    }
    if (selectedFilter.value === 'no_ref') {
        return props.project.items.filter((item) => item.label === 'no_reference');
    }
    return props.project.items;
});

const statusText = computed(() => {
    if (props.project.public_status === 'in_review' && props.project.in_review_since) {
        return publicStatus.in_review(props.project.in_review_since);
    }
    if (props.project.public_status === 'reviewed' && props.project.review?.outcome) {
        return publicStatus.reviewed(props.project.review.outcome as ReviewOutcome);
    }
    return null;
});

const projectTypeName = computed(() => {
    return projectType[props.project.type as keyof typeof projectType] ?? props.project.type;
});

const measurementSummary = computed(() => {
    if (!props.project.measurements || props.project.measurements.length === 0) {
        return null;
    }
    return measurementsInRab(props.project.measurements);
});

function openItemDetail(item: ProjectBudgetItem): void {
    activeItem.value = item;
}

function closeItemDetail(): void {
    activeItem.value = null;
}

function openPhoto(url: string): void {
    activePhoto.value = url;
}

function closePhoto(): void {
    activePhoto.value = null;
}

function showToast(message: string): void {
    toastMessage.value = message;
    if (toastTimer) {
        clearTimeout(toastTimer);
    }
    toastTimer = setTimeout(() => {
        toastMessage.value = null;
    }, 2500);
}

async function handleShare(): Promise<void> {
    const url = window.location.href;
    const shareData = {
        title: props.project.name,
        text: `Transparansi ${props.project.name} - Desa ${props.project.village.name}`,
        url,
    };

    if (navigator.share) {
        try {
            await navigator.share(shareData);
            return;
        } catch {
            // Pengguna membatalkan dialog share atau browser fallback ke clipboard
        }
    }

    if (navigator.clipboard) {
        try {
            await navigator.clipboard.writeText(url);
            showToast('Tautan proyek berhasil disalin!');
            return;
        } catch {
            showToast('Gagal menyalin tautan');
        }
    }
}

function handleKeyDown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        closeItemDetail();
        closePhoto();
    }
}

onMounted(() => {
    window.addEventListener('keydown', handleKeyDown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeyDown);
    if (toastTimer) {
        clearTimeout(toastTimer);
    }
});
</script>

<template>
    <div class="min-h-screen bg-surface-subtle text-content antialiased">
        <Head :title="project.name" />

        <!-- Top Header Navigation -->
        <header class="sticky top-0 z-20 border-b border-stroke bg-surface">
            <div class="mx-auto flex max-w-md items-center justify-between px-4 py-3 sm:max-w-xl md:max-w-2xl">
                <Link
                    :href="`/d/${project.village.slug}`"
                    class="inline-flex min-h-11 items-center gap-1.5 text-sm font-medium text-content-secondary transition-colors hover:text-primary"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                    <span>Desa {{ project.village.name }}</span>
                </Link>

                <button
                    type="button"
                    class="inline-flex h-11 items-center gap-1.5 rounded-md border border-stroke-strong bg-surface px-3 text-sm font-medium text-content transition-colors hover:bg-surface-muted"
                    aria-label="Bagikan tautan proyek"
                    @click="handleShare"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"
                        />
                    </svg>
                    <span>Bagikan</span>
                </button>
            </div>
        </header>

        <main class="mx-auto max-w-md px-4 py-4 sm:max-w-xl sm:px-6 md:max-w-2xl">
            <!-- Project Header Card -->
            <section class="rounded-lg border border-stroke bg-surface p-4">
                <div class="flex flex-wrap items-center gap-2">
                    <span
                        class="rounded-full border border-stroke bg-surface-muted px-2.5 py-0.5 text-sm font-medium text-content-secondary"
                    >
                        {{ projectTypeName }}
                    </span>
                    <span class="text-sm text-content-secondary">
                        {{ project.fund_source }} &middot; Tahun {{ project.year }}
                    </span>
                </div>

                <h1 class="mt-3 text-2xl font-semibold text-content">
                    {{ project.name }}
                </h1>
                <p class="mt-1 text-sm text-content-secondary">
                    Desa {{ project.village.name }}, Kabupaten {{ project.village.regency }}
                </p>

                <!-- Status Publik (P5, P6): tidak pernah ditandai hijau/aman -->
                <div v-if="statusText" class="mt-4">
                    <div
                        v-if="project.public_status === 'in_review'"
                        class="rounded-lg border border-warning-border bg-warning-subtle p-3 text-sm text-warning"
                    >
                        <div class="flex items-center gap-2 font-semibold">
                            <span class="inline-block h-2 w-2 rounded-full bg-warning-solid" aria-hidden="true"></span>
                            <span>{{ statusText }}</span>
                        </div>
                        <p class="mt-1">BPD sedang memverifikasi laporan fisik dan dokumen pendukung proyek ini.</p>
                    </div>

                    <div
                        v-else-if="project.public_status === 'reviewed'"
                        class="rounded-lg border border-info-border bg-info-subtle p-3 text-sm text-info"
                    >
                        <div class="flex items-center gap-2 font-semibold">
                            <span class="inline-block h-2 w-2 rounded-full bg-info-solid" aria-hidden="true"></span>
                            <span>{{ statusText }}</span>
                        </div>
                        <p v-if="project.review?.note" class="mt-1.5">
                            {{ project.review.note }}
                        </p>
                        <div
                            v-if="project.review?.notes && project.review.notes.length > 0"
                            class="mt-2 flex flex-col gap-1.5 border-t border-info-border pt-2"
                        >
                            <p class="font-medium">Catatan tindak lanjut:</p>
                            <p
                                v-for="(subNote, idx) in project.review.notes"
                                :key="idx"
                                class="rounded-md bg-surface p-2 text-content"
                            >
                                {{ subNote.note }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Total Anggaran & Progres Fisik -->
                <div class="mt-4 grid grid-cols-2 gap-3 border-t border-stroke pt-4">
                    <div class="rounded-md bg-surface-subtle p-3">
                        <p class="text-sm text-content-secondary">Total anggaran (RAB)</p>
                        <p class="mt-0.5 text-lg font-bold text-content tabular-nums">
                            {{ formatRupiah(project.total_amount_rp) }}
                        </p>
                    </div>

                    <div class="rounded-md bg-surface-subtle p-3">
                        <div class="flex items-center justify-between text-sm text-content-secondary">
                            <span>Progres fisik</span>
                            <span class="font-semibold text-content tabular-nums">{{ project.progress_pct }}%</span>
                        </div>
                        <div
                            class="mt-2 h-2 w-full overflow-hidden rounded-full bg-stroke"
                            role="progressbar"
                            :aria-valuenow="project.progress_pct"
                            aria-valuemin="0"
                            aria-valuemax="100"
                            aria-label="Progres fisik"
                        >
                            <div
                                class="h-full rounded-full bg-primary transition-all duration-300"
                                :style="{ width: `${Math.min(100, Math.max(0, project.progress_pct))}%` }"
                            ></div>
                        </div>
                        <p v-if="project.last_photo_date" class="mt-1.5 text-xs text-content-tertiary">
                            Foto: {{ formatDate(project.last_photo_date) }}
                        </p>
                    </div>
                </div>

                <!-- Dicek warga di lokasi (P7). Netral: jumlah warga bukan penanda "aman". -->
                <div
                    v-if="project.has_citizen_reports"
                    class="mt-3 flex items-center gap-2 rounded-md bg-surface-subtle px-3 py-2 text-sm text-content"
                >
                    <svg
                        class="h-4 w-4 shrink-0 text-content-secondary"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"
                        />
                    </svg>
                    <span class="font-medium">{{ citizenCount(project.citizen_reports_count) }}</span>
                </div>

                <!-- Label Breakdown Summary (PRD 4.2 Langkah 2) -->
                <div class="mt-3 flex flex-wrap gap-2 text-sm">
                    <span
                        v-if="project.label_counts.above_standard > 0"
                        class="inline-flex items-center gap-1 rounded-full border border-negative-border bg-negative-subtle px-2.5 py-0.5 font-medium text-negative"
                    >
                        <span class="font-bold tabular-nums">{{ project.label_counts.above_standard }}</span>
                        <span>Di atas standar</span>
                    </span>
                    <span
                        v-if="project.label_counts.within_standard > 0"
                        class="inline-flex items-center gap-1 rounded-full border border-positive-border bg-positive-subtle px-2.5 py-0.5 font-medium text-positive"
                    >
                        <span class="font-bold tabular-nums">{{ project.label_counts.within_standard }}</span>
                        <span>Sesuai standar</span>
                    </span>
                    <span
                        v-if="project.label_counts.no_reference > 0"
                        class="inline-flex items-center gap-1 rounded-full border border-stroke bg-surface-muted px-2.5 py-0.5 font-medium text-content-secondary"
                    >
                        <span class="font-bold tabular-nums">{{ project.label_counts.no_reference }}</span>
                        <span>Tanpa acuan</span>
                    </span>
                </div>
            </section>

            <!-- Blok "Ukuran di RAB" & Action CTA (P12, F21, P11) -->
            <section class="mt-4 rounded-lg border border-stroke bg-surface p-4">
                <div v-if="measurementSummary">
                    <p class="text-base font-semibold text-content">
                        {{ measurementSummary }}
                    </p>
                    <p class="mt-1 text-sm text-content-secondary">
                        Ukuran fisik resmi dari dokumen RAB untuk dicocokkan langsung di lokasi proyek.
                    </p>
                </div>

                <div class="mt-4 flex flex-col gap-3">
                    <!-- CTA Utama: Cek Spek di lokasi (Langkah 9). Dinonaktifkan sampai M4 (route belum ada). -->
                    <button
                        type="button"
                        disabled
                        aria-disabled="true"
                        class="inline-flex h-11 w-full cursor-not-allowed items-center justify-center gap-2 rounded-md bg-surface-muted px-4 text-base font-medium text-content-tertiary"
                    >
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"
                            />
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"
                            />
                        </svg>
                        <span>Cek Spek di lokasi</span>
                    </button>

                    <!-- CTA Dokumen Asli (P11, F20, Langkah 7) -->
                    <div v-if="project.document_url" class="border-t border-stroke pt-3 text-center">
                        <a
                            :href="project.document_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex min-h-11 items-center gap-1.5 text-base font-medium text-primary underline-offset-2 hover:text-primary-hover hover:underline"
                        >
                            <svg
                                class="h-5 w-5"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                                />
                            </svg>
                            <span>{{ documentLabels.viewOriginal }}</span>
                        </a>
                        <p class="text-sm text-content-secondary">
                            {{ documentLabels.readFrom }}
                        </p>
                    </div>
                </div>
            </section>

            <!-- Rincian Anggaran (RAB) Section (P3, P4) -->
            <section class="mt-8">
                <h2 class="text-xl font-semibold text-content">Rincian anggaran</h2>
                <p class="text-sm text-content-secondary">Ketuk baris untuk melihat perbandingan acuan</p>

                <!-- Filter Tabs (P4) -->
                <div class="mt-3 flex gap-2 overflow-x-auto pb-1 text-sm" role="group" aria-label="Saring item">
                    <button
                        type="button"
                        class="h-11 shrink-0 rounded-full border px-4 font-medium transition-colors"
                        :class="
                            selectedFilter === 'all'
                                ? 'border-primary bg-primary-subtle text-primary'
                                : 'border-stroke bg-surface text-content-secondary hover:bg-surface-muted'
                        "
                        :aria-pressed="selectedFilter === 'all'"
                        @click="selectedFilter = 'all'"
                    >
                        Semua ({{ project.items.length }})
                    </button>
                    <button
                        type="button"
                        class="h-11 shrink-0 rounded-full border px-4 font-medium transition-colors"
                        :class="
                            selectedFilter === 'above'
                                ? 'border-primary bg-primary-subtle text-primary'
                                : 'border-stroke bg-surface text-content-secondary hover:bg-surface-muted'
                        "
                        :aria-pressed="selectedFilter === 'above'"
                        @click="selectedFilter = 'above'"
                    >
                        Di atas standar ({{ project.label_counts.above_standard }})
                    </button>
                    <button
                        type="button"
                        class="h-11 shrink-0 rounded-full border px-4 font-medium transition-colors"
                        :class="
                            selectedFilter === 'no_ref'
                                ? 'border-primary bg-primary-subtle text-primary'
                                : 'border-stroke bg-surface text-content-secondary hover:bg-surface-muted'
                        "
                        :aria-pressed="selectedFilter === 'no_ref'"
                        @click="selectedFilter = 'no_ref'"
                    >
                        Tanpa acuan ({{ project.label_counts.no_reference }})
                    </button>
                </div>

                <!-- Item List (Cards / Rows) -->
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
                        @click="openItemDetail(item)"
                    >
                        <div class="min-w-0">
                            <p class="text-base font-medium text-content">
                                {{ item.name }}
                            </p>
                            <p class="mt-0.5 text-sm text-content-secondary tabular-nums">
                                {{ item.volume }} {{ item.unit }}
                                <span v-if="item.unit_price_rp">
                                    &middot; @{{ formatRupiah(item.unit_price_rp) }}
                                </span>
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
                                {{ priceLabel.above_standard(item.over_pct) }}
                            </span>
                            <span
                                v-else-if="item.label === 'no_reference'"
                                class="rounded-full border border-stroke bg-surface-muted px-2 py-0.5 text-sm font-medium text-content-secondary"
                            >
                                {{ priceLabel.no_reference }}
                            </span>
                            <span
                                v-else-if="item.label === 'within_standard'"
                                class="rounded-full border border-positive-border bg-positive-subtle px-2 py-0.5 text-sm font-medium text-positive"
                            >
                                {{ priceLabel.within_standard }}
                            </span>
                        </div>
                    </button>
                </div>
            </section>

            <!-- Foto Progres Timeline (P8, Langkah 5) -->
            <section class="mt-8 border-t border-stroke pt-6">
                <h2 class="text-xl font-semibold text-content">Foto progres lapangan</h2>
                <p class="text-sm text-content-secondary">Diunggah oleh Pemerintah Desa</p>

                <div
                    v-if="project.progress_photos.length === 0"
                    class="mt-3 rounded-lg bg-surface-muted p-4 text-center"
                >
                    <p class="text-sm text-content-secondary">Desa belum mengunggah foto progres</p>
                </div>

                <div v-else class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
                    <div
                        v-for="photo in project.progress_photos"
                        :key="photo.id"
                        class="overflow-hidden rounded-lg border border-stroke bg-surface"
                    >
                        <button type="button" class="block w-full text-left" @click="openPhoto(photo.url)">
                            <div class="aspect-4/3 w-full overflow-hidden bg-surface-muted">
                                <img
                                    :src="photo.url"
                                    :alt="`Progres ${photo.progress_pct}%`"
                                    class="h-full w-full object-cover"
                                    loading="lazy"
                                />
                            </div>
                            <div class="flex items-center justify-between p-2">
                                <span class="text-sm font-semibold text-content tabular-nums">
                                    {{ photo.progress_pct }}%
                                </span>
                                <span class="text-xs text-content-secondary">
                                    {{ formatDate(photo.taken_at) }}
                                </span>
                            </div>
                        </button>
                    </div>
                </div>
            </section>

            <!-- Riwayat Perubahan (Langkah 6): hanya tampil jika ada perubahan -->
            <section v-if="project.changes && project.changes.length > 0" class="mt-8 border-t border-stroke pt-6">
                <button
                    type="button"
                    class="flex min-h-11 w-full items-center justify-between gap-3 text-left"
                    :aria-expanded="showHistory"
                    @click="showHistory = !showHistory"
                >
                    <div>
                        <h2 class="text-xl font-semibold text-content">
                            Riwayat perubahan ({{ project.changes.length }})
                        </h2>
                        <p class="text-sm text-content-secondary">Pembaruan data setelah proyek diterbitkan</p>
                    </div>
                    <span class="shrink-0 text-sm font-medium text-primary">
                        {{ showHistory ? 'Sembunyikan' : 'Lihat riwayat' }}
                    </span>
                </button>

                <div v-if="showHistory" class="mt-4 flex flex-col gap-2">
                    <div
                        v-for="change in project.changes"
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
                        <div
                            class="mt-1 flex flex-wrap items-center gap-2 rounded-md bg-surface-subtle p-2 tabular-nums"
                        >
                            <span class="text-content-secondary line-through">{{ change.old_value }}</span>
                            <span class="text-content-tertiary" aria-hidden="true">&rarr;</span>
                            <span class="font-semibold text-content">{{ change.new_value }}</span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Footer Info -->
            <footer class="mt-10 border-t border-stroke py-6 text-center text-sm text-content-secondary">
                <p>Sonar Desa &middot; Keterbukaan Informasi Publik Desa</p>
                <p class="mt-1 text-xs text-content-tertiary">
                    Berdasarkan UU No. 6/2014 tentang Desa &amp; UU No. 14/2008 tentang KIP
                </p>
            </footer>
        </main>

        <!-- Drawer Detail Item Modal (P3, P4) -->
        <div
            v-if="activeItem"
            class="fixed inset-0 z-50 flex items-end justify-center bg-content/50 sm:items-center sm:p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="modal-item-title"
            @click.self="closeItemDetail"
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
                        class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-md text-content-secondary hover:bg-surface-muted hover:text-content"
                        aria-label="Tutup panel detail"
                        @click="closeItemDetail"
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

                <!-- Perbandingan Harga -->
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

                    <!-- Selisih Satuan & Selisih Total jika di atas standar -->
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

                    <!-- Penjelasan Netral Sesuai PRD 4.3 -->
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
                    class="mt-5 h-11 w-full rounded-md border border-stroke-strong bg-surface text-base font-medium text-content transition-colors hover:bg-surface-muted"
                    @click="closeItemDetail"
                >
                    Tutup
                </button>
            </div>
        </div>

        <!-- Lightbox Foto Progres -->
        <div
            v-if="activePhoto"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/90 p-4"
            role="dialog"
            aria-modal="true"
            aria-label="Tampilan penuh foto progres"
            @click.self="closePhoto"
        >
            <div class="relative max-h-[90vh] max-w-lg">
                <button
                    type="button"
                    class="absolute -top-12 right-0 inline-flex h-11 w-11 items-center justify-center rounded-full bg-white/20 text-white hover:bg-white/30"
                    aria-label="Tutup foto"
                    @click="closePhoto"
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
                <img
                    :src="activePhoto"
                    alt="Foto progres diperbesar"
                    class="max-h-[80vh] w-auto rounded-lg object-contain"
                />
            </div>
        </div>

        <!-- Toast Feedback -->
        <div
            v-if="toastMessage"
            class="fixed bottom-6 left-1/2 z-50 -translate-x-1/2 rounded-md bg-content px-4 py-2 text-sm font-medium text-content-inverse shadow-sm"
            role="status"
        >
            {{ toastMessage }}
        </div>
    </div>
</template>
