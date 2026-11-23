<script setup lang="ts">
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import Alert from '@/components/ui/Alert.vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import Dialog from '@/components/ui/Dialog.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import PriceLabel from '@/components/ui/PriceLabel.vue';
import { formatRupiah, formatDateTime } from '@/lib/format';

interface AuditItemData {
    id: number;
    raw_name: string;
    volume: number | string;
    unit: string;
    unit_price_rp: number;
    amount_rp: number;
    label: 'within_standard' | 'above_standard' | 'needs_check' | 'no_reference';
    diff_rp: number;
    over_pct: number | null;
    standard_id: number | null;
    standard?: {
        id: number;
        name: string;
        code: string;
        unit: string;
        price_rp: number;
    } | null;
}

interface MeasurementData {
    id: number;
    name: string;
    unit: string;
    value: number | string;
    how_to: string | null;
}

interface PhotoData {
    id: number;
    url: string;
    created_at: string;
}

interface ChangeData {
    id: number;
    field: string;
    item_label: string;
    old_value: string;
    new_value: string;
    changed_at: string;
}

interface PriceStandardData {
    id: number;
    code: string;
    name: string;
    unit: string;
    price_rp: number;
}

interface ProjectData {
    id: number;
    name: string;
    slug: string;
    type: string;
    fund_source: string;
    year: number;
    lat: number | string;
    lng: number | string;
    progress_pct: number;
    public_status: 'none' | 'in_review' | 'reviewed';
    is_published: boolean;
    published_at: string | null;
    created_at: string;
    audit: {
        id: number;
        status: string;
        file_path: string;
        written_total_rp: number;
        items: AuditItemData[];
    } | null;
    measurements: MeasurementData[];
    progress_photos: PhotoData[];
    changes: ChangeData[];
    village: {
        id: number;
        name: string;
        slug?: string;
        regency: string;
    };
}

interface Props {
    project: ProjectData;
    standards: PriceStandardData[];
}

const props = defineProps<Props>();

const activeTab = ref<'progress' | 'items' | 'measurements' | 'gps' | 'changes'>('progress');

const isFrozen = computed(() => props.project.public_status === 'in_review');

// --- Tab 1: Progres Fisik & Foto ---
const progressForm = useForm({
    progress_pct: props.project.progress_pct,
    photos: [] as File[],
    decrease_reason: '',
});

const isDecrease = computed(() => progressForm.progress_pct < props.project.progress_pct);

function handlePhotoSelect(e: Event): void {
    const target = e.target as HTMLInputElement;
    if (target.files) {
        progressForm.photos = Array.from(target.files);
    }
}

function submitProgress(): void {
    progressForm.post(`/projects/${props.project.id}/progress`, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            progressForm.photos = [];
            progressForm.decrease_reason = '';
        },
    });
}

// --- Tab 2: CCO Item Belanja RAB ---
const editingItem = ref<AuditItemData | null>(null);
const itemDialog = ref(false);

const itemForm = useForm({
    volume: '',
    unit: '',
    unit_price_rp: 0,
    standard_id: null as number | null,
    reason: '',
});

function openItemCco(item: AuditItemData): void {
    if (isFrozen.value) return;
    editingItem.value = item;
    itemForm.volume = String(item.volume);
    itemForm.unit = item.unit;
    itemForm.unit_price_rp = item.unit_price_rp;
    itemForm.standard_id = item.standard_id;
    itemForm.reason = '';
    itemDialog.value = true;
}

function submitItemCco(): void {
    if (!editingItem.value) return;
    itemForm.post(`/desa/projects/${props.project.id}/cco/items/${editingItem.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            itemDialog.value = false;
            editingItem.value = null;
        },
    });
}

// --- Tab 3: CCO Parameter Ukuran (Spek Cek Warga) ---
const editingMeasurement = ref<MeasurementData | null>(null);
const measurementDialog = ref(false);

const measurementForm = useForm({
    value: '',
    how_to: '',
    reason: '',
});

function openMeasurementCco(m: MeasurementData): void {
    if (isFrozen.value) return;
    editingMeasurement.value = m;
    measurementForm.value = String(m.value);
    measurementForm.how_to = m.how_to ?? '';
    measurementForm.reason = '';
    measurementDialog.value = true;
}

function submitMeasurementCco(): void {
    if (!editingMeasurement.value) return;
    measurementForm.post(`/desa/projects/${props.project.id}/cco/measurements/${editingMeasurement.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            measurementDialog.value = false;
            editingMeasurement.value = null;
        },
    });
}

// --- Tab 4: CCO GPS ---
const gpsForm = useForm({
    lat: String(props.project.lat),
    lng: String(props.project.lng),
    reason: '',
});

function submitGpsCco(): void {
    if (isFrozen.value) return;
    gpsForm.post(`/desa/projects/${props.project.id}/cco/gps`, {
        preserveScroll: true,
        onSuccess: () => {
            gpsForm.reason = '';
        },
    });
}

// --- Delete Draft Project ---
function deleteDraftProject(): void {
    if (props.project.is_published) return;
    if (confirm('Yakin ingin menghapus draf proyek ini? Tindakan ini tidak dapat dibatalkan.')) {
        router.delete(`/desa/projects/${props.project.id}`);
    }
}
</script>

<template>
    <AppLayout>
        <Head :title="`Kelola: ${project.name} - Sonar Desa`" />

        <template #breadcrumb>
            <Link href="/" class="transition-colors hover:text-primary">Beranda</Link>
            <span>/</span>
            <Link href="/desa" class="transition-colors hover:text-primary">Pemerintah Desa</Link>
            <span>/</span>
            <span class="font-medium text-content">{{ project.name }}</span>
        </template>

        <template #nav>
            <Link
                href="/desa"
                class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-content-secondary transition-colors hover:bg-surface-muted hover:text-content focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
            >
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="1.5"
                        d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"
                    />
                </svg>
                <span>Dashboard</span>
            </Link>
            <Link
                href="/audits/create"
                class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-content-secondary transition-colors hover:bg-surface-muted hover:text-content focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
            >
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="1.5"
                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                    />
                </svg>
                <span>Audit &amp; Ingesti RAB</span>
            </Link>
            <Link
                href="/desa#daftar-proyek"
                class="flex items-center gap-3 rounded-xl bg-primary-subtle px-3 py-2.5 text-sm font-semibold text-primary transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
            >
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="1.5"
                        d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"
                    />
                </svg>
                <span>Proyek Fisik</span>
            </Link>
            <Link
                v-if="project.is_published"
                :href="`/p/${project.slug}`"
                class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-content-secondary transition-colors hover:bg-surface-muted hover:text-content focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
            >
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="1.5"
                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                    />
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="1.5"
                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                    />
                </svg>
                <span>Lihat Halaman Publik</span>
            </Link>
            <Link
                v-else-if="project.village.slug"
                :href="`/d/${project.village.slug}`"
                class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-content-secondary transition-colors hover:bg-surface-muted hover:text-content focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
            >
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="1.5"
                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                    />
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="1.5"
                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                    />
                </svg>
                <span>Lihat Halaman Publik</span>
            </Link>
        </template>

        <template #sidebarFooter>
            <div class="flex items-center justify-between text-xs text-content-secondary">
                <div class="flex min-w-0 flex-col">
                    <span class="truncate font-medium text-content">{{ project.village.name }}</span>
                    <span class="truncate text-content-tertiary">Kab. {{ project.village.regency }}</span>
                </div>
                <Link
                    href="/logout"
                    method="post"
                    as="button"
                    class="font-medium text-negative underline-offset-2 hover:underline"
                >
                    Keluar
                </Link>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Header Section -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-content sm:text-3xl">
                        {{ project.name }}
                    </h1>
                    <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-content-secondary">
                        <Badge :color="project.is_published ? 'positive' : 'neutral'">
                            {{ project.is_published ? 'Terbit' : 'Draf' }}
                        </Badge>
                        <Badge v-if="project.public_status === 'in_review'" color="warning"> Dalam Tinjauan BPD </Badge>
                        <Badge v-else-if="project.public_status === 'reviewed'" color="info"> Ditinjau BPD </Badge>
                        <span>&bull;</span>
                        <span>{{ project.type }} &middot; Tahun {{ project.year }}</span>
                        <span>&bull;</span>
                        <span>{{ project.fund_source }}</span>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <Button v-if="project.is_published" variant="secondary" :href="`/projects/${project.id}/qr`">
                        Cetak Poster QR
                    </Button>
                    <Button v-if="project.is_published" variant="primary" :href="`/p/${project.slug}`">
                        Lihat Publik &rarr;
                    </Button>
                    <Button v-if="!project.is_published" variant="danger" @click="deleteDraftProject">
                        Hapus Draf Proyek
                    </Button>
                </div>
            </div>

            <!-- Warning Banner D12: Dalam Tinjauan BPD (Pembekuan CCO) -->
            <Alert v-if="isFrozen" color="warning" title="Perubahan CCO Dibekukan (Aturan D12)">
                Proyek ini saat ini berstatus <strong>Dalam Tinjauan BPD</strong>. Sesuai prinsip tata kelola dan
                integritas data publik, form perubahan CCO pada
                <strong>item belanja RAB, parameter ukuran fisik, dan koordinat GPS geofence</strong> dibekukan
                sementara hingga status kembali normal. Pembaruan persentase progres fisik dan pengunggahan foto
                dokumentasi lapangan tetap diperbolehkan.
            </Alert>

            <!-- Navigation Tabs -->
            <div class="border-b border-stroke">
                <nav class="-mb-px flex space-x-6 overflow-x-auto text-sm font-medium">
                    <button
                        type="button"
                        :class="[
                            activeTab === 'progress'
                                ? 'border-primary text-primary'
                                : 'border-transparent text-content-secondary hover:border-stroke hover:text-content',
                            'border-b-2 px-1 py-3 whitespace-nowrap transition-colors',
                        ]"
                        @click="activeTab = 'progress'"
                    >
                        Progres &amp; Foto Lapangan ({{ project.progress_pct }}%)
                    </button>
                    <button
                        type="button"
                        :class="[
                            activeTab === 'items'
                                ? 'border-primary text-primary'
                                : 'border-transparent text-content-secondary hover:border-stroke hover:text-content',
                            'border-b-2 px-1 py-3 whitespace-nowrap transition-colors',
                        ]"
                        @click="activeTab = 'items'"
                    >
                        CCO Item RAB ({{ project.audit?.items.length ?? 0 }})
                    </button>
                    <button
                        type="button"
                        :class="[
                            activeTab === 'measurements'
                                ? 'border-primary text-primary'
                                : 'border-transparent text-content-secondary hover:border-stroke hover:text-content',
                            'border-b-2 px-1 py-3 whitespace-nowrap transition-colors',
                        ]"
                        @click="activeTab = 'measurements'"
                    >
                        CCO Spek Ukuran ({{ project.measurements.length }})
                    </button>
                    <button
                        type="button"
                        :class="[
                            activeTab === 'gps'
                                ? 'border-primary text-primary'
                                : 'border-transparent text-content-secondary hover:border-stroke hover:text-content',
                            'border-b-2 px-1 py-3 whitespace-nowrap transition-colors',
                        ]"
                        @click="activeTab = 'gps'"
                    >
                        CCO Lokasi GPS
                    </button>
                    <button
                        type="button"
                        :class="[
                            activeTab === 'changes'
                                ? 'border-primary text-primary'
                                : 'border-transparent text-content-secondary hover:border-stroke hover:text-content',
                            'border-b-2 px-1 py-3 whitespace-nowrap transition-colors',
                        ]"
                        @click="activeTab = 'changes'"
                    >
                        Riwayat Perubahan ({{ project.changes.length }})
                    </button>
                </nav>
            </div>

            <!-- Tab 1: Progres Fisik & Foto -->
            <div v-if="activeTab === 'progress'" class="space-y-6">
                <Card padding="app">
                    <h2 class="text-base font-semibold text-content">Pembaruan Progres &amp; Foto Lapangan</h2>
                    <p class="mt-1 text-xs text-content-secondary">
                        Unggah foto dokumentasi kegiatan fisik lapangan dan atur persentase capaian progres pekerjaan.
                        Foto disimpan dalam format WebP tanpa metadata EXIF.
                    </p>

                    <form class="mt-5 space-y-4" @submit.prevent="submitProgress">
                        <div>
                            <div class="flex items-center justify-between text-sm">
                                <label for="progress-pct" class="font-medium text-content">
                                    Persentase Capaian Progres (%)
                                </label>
                                <span class="font-bold text-primary tabular-nums"
                                    >{{ progressForm.progress_pct }}%</span
                                >
                            </div>
                            <input
                                id="progress-pct"
                                v-model.number="progressForm.progress_pct"
                                type="range"
                                min="0"
                                max="100"
                                step="1"
                                class="mt-2 h-2 w-full cursor-pointer appearance-none rounded-lg bg-surface-muted accent-primary"
                            />
                            <div class="mt-1 flex justify-between text-[11px] text-content-tertiary">
                                <span>0% (Mulai)</span>
                                <span>50%</span>
                                <span>100% (Selesai)</span>
                            </div>
                            <p v-if="progressForm.errors.progress_pct" class="mt-1 text-xs text-negative">
                                {{ progressForm.errors.progress_pct }}
                            </p>
                        </div>

                        <!-- D13: Reason required if progress decreased -->
                        <div v-if="isDecrease" class="rounded-lg border border-warning/30 bg-warning/5 p-3">
                            <label for="decrease-reason" class="text-warning-content block text-xs font-semibold">
                                Alasan Penurunan Progres (Wajib - Aturan D13) *
                            </label>
                            <p class="mt-0.5 text-xs text-content-secondary">
                                Persentase progres menurun dari {{ project.progress_pct }}% ke
                                {{ progressForm.progress_pct }}%. Tuliskan alasan teknis penurunan volume pekerjaan.
                            </p>
                            <input
                                id="decrease-reason"
                                v-model="progressForm.decrease_reason"
                                type="text"
                                required
                                placeholder="Contoh: Koreksi pengukuran ulang konsultan pengawas"
                                class="mt-2 block w-full rounded-md border border-stroke bg-surface px-3 py-2 text-sm text-content focus:border-primary focus:outline-none"
                            />
                            <p v-if="progressForm.errors.decrease_reason" class="mt-1 text-xs text-negative">
                                {{ progressForm.errors.decrease_reason }}
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-content">
                                Unggah Foto Dokumentasi Lapangan (1 - 5 Foto) *
                            </label>
                            <input
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                multiple
                                required
                                class="mt-1 block w-full text-sm text-content-secondary file:mr-4 file:rounded-md file:border-0 file:bg-surface-muted file:px-3 file:py-2 file:text-xs file:font-medium file:text-content hover:file:bg-stroke"
                                @change="handlePhotoSelect"
                            />
                            <p v-if="progressForm.errors.photos" class="mt-1 text-xs text-negative">
                                {{ progressForm.errors.photos }}
                            </p>
                        </div>

                        <div class="flex justify-end pt-2">
                            <Button
                                type="submit"
                                variant="primary"
                                :disabled="
                                    progressForm.processing ||
                                    progressForm.photos.length === 0 ||
                                    (isDecrease && !progressForm.decrease_reason)
                                "
                            >
                                {{ progressForm.processing ? 'Menyimpan...' : 'Simpan Progres & Foto' }}
                            </Button>
                        </div>
                    </form>
                </Card>

                <!-- Photo Gallery -->
                <div class="space-y-3">
                    <h3 class="text-sm font-semibold text-content">
                        Galeri Dokumentasi Foto ({{ project.progress_photos.length }})
                    </h3>
                    <div
                        v-if="project.progress_photos.length === 0"
                        class="rounded-lg border border-stroke bg-surface p-6 text-center text-xs text-content-secondary"
                    >
                        Belum ada foto progres yang diunggah.
                    </div>
                    <div v-else class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                        <div
                            v-for="photo in project.progress_photos"
                            :key="photo.id"
                            class="group overflow-hidden rounded-lg border border-stroke bg-surface shadow-2xs"
                        >
                            <img
                                :src="photo.url"
                                alt="Foto Progres Fisik"
                                class="aspect-4/3 w-full object-cover transition-transform group-hover:scale-105"
                                loading="lazy"
                            />
                            <div class="p-2 text-[11px] text-content-secondary">
                                {{ formatDateTime(photo.created_at) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 2: CCO Item Belanja RAB -->
            <div v-if="activeTab === 'items'" class="space-y-4">
                <div class="rounded-lg border border-stroke bg-surface p-4 sm:p-6">
                    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-base font-semibold text-content">CCO Tambah Kurang Volume Belanja RAB</h2>
                            <p class="text-xs text-content-secondary">
                                Sesuai aturan D13, setiap perubahan volume atau harga satuan akan dicatat pada riwayat
                                linimasa publik dan label harga dihitung ulang secara deterministik oleh sistem (D14).
                            </p>
                        </div>
                        <div class="text-xs text-content-secondary">
                            Total Anggaran:
                            <strong class="text-content">{{
                                formatRupiah(project.audit?.written_total_rp ?? 0)
                            }}</strong>
                        </div>
                    </div>

                    <div v-if="!project.audit || project.audit.items.length === 0" class="mt-4">
                        <EmptyState
                            title="Tidak ada item belanja RAB"
                            description="Dokumen audit RAB belum memiliki baris rincian item belanja."
                        />
                    </div>

                    <div v-else class="mt-4 overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead
                                class="border-b border-stroke bg-surface-subtle text-xs font-semibold text-content-secondary uppercase"
                            >
                                <tr>
                                    <th class="px-3 py-2.5">Nama Item Belanja</th>
                                    <th class="px-3 py-2.5 text-right">Volume</th>
                                    <th class="px-3 py-2.5 text-right">Harga Satuan</th>
                                    <th class="px-3 py-2.5 text-right">Subtotal</th>
                                    <th class="px-3 py-2.5 text-center">Kepatuhan Harga</th>
                                    <th class="px-3 py-2.5 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-stroke">
                                <tr v-for="item in project.audit.items" :key="item.id" class="hover:bg-surface-subtle">
                                    <td class="px-3 py-3">
                                        <p class="font-medium text-content">{{ item.raw_name }}</p>
                                        <p v-if="item.standard" class="text-xs text-content-tertiary">
                                            Standar: {{ item.standard.name }} ({{
                                                formatRupiah(item.standard.price_rp)
                                            }}/{{ item.standard.unit }})
                                        </p>
                                    </td>
                                    <td class="px-3 py-3 text-right font-mono text-xs text-content tabular-nums">
                                        {{ item.volume }} {{ item.unit }}
                                    </td>
                                    <td class="px-3 py-3 text-right font-mono text-xs text-content tabular-nums">
                                        {{ formatRupiah(item.unit_price_rp) }}
                                    </td>
                                    <td
                                        class="px-3 py-3 text-right font-mono text-xs font-semibold text-content tabular-nums"
                                    >
                                        {{ formatRupiah(item.amount_rp) }}
                                    </td>
                                    <td class="px-3 py-3 text-center">
                                        <PriceLabel
                                            :label="item.label"
                                            :percent-above="item.over_pct ? `+${item.over_pct}%` : null"
                                        />
                                    </td>
                                    <td class="px-3 py-3 text-right">
                                        <Button
                                            size="sm"
                                            variant="secondary"
                                            :disabled="isFrozen"
                                            :title="
                                                isFrozen ? 'CCO dibekukan saat Dalam Tinjauan BPD' : 'Ubah item (CCO)'
                                            "
                                            @click="openItemCco(item)"
                                        >
                                            Ubah CCO
                                        </Button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Tab 3: CCO Parameter Ukuran (Spek Cek Warga) -->
            <div v-if="activeTab === 'measurements'" class="space-y-4">
                <div class="rounded-lg border border-stroke bg-surface p-4 sm:p-6">
                    <div>
                        <h2 class="text-base font-semibold text-content">
                            CCO Parameter Ukuran Fisik (Cek Spek Warga)
                        </h2>
                        <p class="text-xs text-content-secondary">
                            Ukuran fisik yang diverifikasi oleh warga di lapangan. Nilai target dapat disesuaikan
                            melalui CCO (D13). Parameter tidak dapat dihapus setelah proyek terbit untuk melindungi
                            integritas jawaban warga (D11).
                        </p>
                    </div>

                    <div v-if="project.measurements.length === 0" class="mt-4">
                        <EmptyState
                            title="Belum ada parameter ukuran"
                            description="Proyek ini belum memiliki parameter spek teknis fisik yang dipublikasikan."
                        />
                    </div>

                    <div v-else class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <div
                            v-for="m in project.measurements"
                            :key="m.id"
                            class="flex flex-col justify-between rounded-lg border border-stroke bg-surface p-4 shadow-2xs"
                        >
                            <div>
                                <span class="text-xs font-medium text-content-secondary uppercase">Parameter</span>
                                <h3 class="text-base font-semibold text-content">{{ m.name }}</h3>
                                <div class="mt-2 text-2xl font-bold text-primary tabular-nums">
                                    {{ m.value }}
                                    <span class="text-sm font-normal text-content-secondary">{{ m.unit }}</span>
                                </div>
                                <p v-if="m.how_to" class="mt-2 text-xs text-content-tertiary">
                                    <strong>Petunjuk:</strong> {{ m.how_to }}
                                </p>
                            </div>

                            <div class="mt-4 flex justify-end border-t border-stroke pt-3">
                                <Button
                                    size="sm"
                                    variant="secondary"
                                    :disabled="isFrozen"
                                    :title="
                                        isFrozen ? 'CCO dibekukan saat Dalam Tinjauan BPD' : 'Ubah nilai target CCO'
                                    "
                                    @click="openMeasurementCco(m)"
                                >
                                    Ubah Target CCO
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 4: CCO Titik Lokasi GPS -->
            <div v-if="activeTab === 'gps'" class="space-y-4">
                <Card padding="app">
                    <h2 class="text-base font-semibold text-content">CCO Koordinat Titik Lokasi Geofence (GPS)</h2>
                    <p class="mt-1 text-xs text-content-secondary">
                        Koordinat titik lokasi fisik proyek digunakan untuk validasi geofence pelaporan warga (radius
                        500m). Perubahan lokasi fisik lapangan akibat relokasi tapak wajib menyertakan alasan CCO.
                    </p>

                    <form class="mt-5 max-w-lg space-y-4" @submit.prevent="submitGpsCco">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="gps-lat" class="block text-xs font-medium text-content">Latitude *</label>
                                <input
                                    id="gps-lat"
                                    v-model="gpsForm.lat"
                                    type="text"
                                    required
                                    :disabled="isFrozen"
                                    class="mt-1 block w-full rounded-md border border-stroke bg-surface px-3 py-2 text-sm text-content focus:border-primary focus:outline-none disabled:bg-surface-muted"
                                />
                                <p v-if="gpsForm.errors.lat" class="mt-1 text-xs text-negative">
                                    {{ gpsForm.errors.lat }}
                                </p>
                            </div>
                            <div>
                                <label for="gps-lng" class="block text-xs font-medium text-content">Longitude *</label>
                                <input
                                    id="gps-lng"
                                    v-model="gpsForm.lng"
                                    type="text"
                                    required
                                    :disabled="isFrozen"
                                    class="mt-1 block w-full rounded-md border border-stroke bg-surface px-3 py-2 text-sm text-content focus:border-primary focus:outline-none disabled:bg-surface-muted"
                                />
                                <p v-if="gpsForm.errors.lng" class="mt-1 text-xs text-negative">
                                    {{ gpsForm.errors.lng }}
                                </p>
                            </div>
                        </div>

                        <div>
                            <label for="gps-reason" class="block text-xs font-medium text-content">
                                Alasan Perubahan Koordinat GPS (Wajib - Aturan D13) *
                            </label>
                            <input
                                id="gps-reason"
                                v-model="gpsForm.reason"
                                type="text"
                                required
                                :disabled="isFrozen"
                                placeholder="Contoh: Penyesuaian batas patok tanah warga hasil musyawarah"
                                class="mt-1 block w-full rounded-md border border-stroke bg-surface px-3 py-2 text-sm text-content focus:border-primary focus:outline-none disabled:bg-surface-muted"
                            />
                            <p v-if="gpsForm.errors.reason" class="mt-1 text-xs text-negative">
                                {{ gpsForm.errors.reason }}
                            </p>
                        </div>

                        <div class="flex items-center justify-between pt-2">
                            <a
                                :href="`https://www.google.com/maps?q=${project.lat},${project.lng}`"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="text-xs font-medium text-primary hover:underline"
                            >
                                Buka di Google Maps &rarr;
                            </a>

                            <Button
                                type="submit"
                                variant="primary"
                                :disabled="isFrozen || gpsForm.processing || !gpsForm.reason"
                            >
                                {{ gpsForm.processing ? 'Menyimpan...' : 'Simpan Koordinat Baru' }}
                            </Button>
                        </div>
                    </form>
                </Card>
            </div>

            <!-- Tab 5: Linimasa Riwayat Perubahan (Audit Trail) -->
            <div v-if="activeTab === 'changes'" class="space-y-4">
                <div class="rounded-lg border border-stroke bg-surface p-4 sm:p-6">
                    <div>
                        <h2 class="text-base font-semibold text-content">
                            Linimasa Riwayat Perubahan (Audit Trail CCO)
                        </h2>
                        <p class="text-xs text-content-secondary">
                            Catatan append-only perubahan teknis proyek yang otomatis tercatat dan dapat dibaca oleh
                            publik (D10, D13).
                        </p>
                    </div>

                    <div v-if="project.changes.length === 0" class="mt-4">
                        <EmptyState
                            title="Belum ada riwayat perubahan"
                            description="Proyek ini belum pernah mengalami Contract Change Order (CCO) atau koreksi progres."
                        />
                    </div>

                    <div v-else class="mt-4 space-y-3">
                        <div
                            v-for="change in project.changes"
                            :key="change.id"
                            class="rounded-lg border border-stroke bg-surface p-3 text-sm shadow-2xs"
                        >
                            <div class="flex items-center justify-between gap-2 text-xs text-content-secondary">
                                <span class="font-semibold text-content">{{ change.item_label }}</span>
                                <span>{{ formatDateTime(change.changed_at) }}</span>
                            </div>
                            <p class="mt-1 text-xs text-content-secondary">
                                Perubahan pada <span class="font-medium text-content">{{ change.field }}</span>:
                            </p>
                            <div
                                class="mt-1 flex flex-wrap items-center gap-2 rounded bg-surface-subtle p-2 text-xs tabular-nums"
                            >
                                <span class="text-content-secondary line-through">{{ change.old_value }}</span>
                                <span class="text-content-tertiary">&rarr;</span>
                                <span class="font-semibold text-content">{{ change.new_value }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dialog CCO Item Belanja RAB -->
        <Dialog :open="itemDialog" title="Formulir CCO Item Belanja RAB" @close="itemDialog = false">
            <div v-if="editingItem" class="space-y-4">
                <p class="text-xs text-content-secondary">
                    Perubahan volume atau harga satuan pada item <strong>{{ editingItem.raw_name }}</strong>.
                </p>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="item-vol" class="block text-xs font-medium text-content">Volume Baru *</label>
                        <input
                            id="item-vol"
                            v-model="itemForm.volume"
                            type="text"
                            required
                            class="mt-1 block w-full rounded-md border border-stroke bg-surface px-3 py-2 text-sm text-content focus:border-primary focus:outline-none"
                        />
                        <p v-if="itemForm.errors.volume" class="mt-1 text-xs text-negative">
                            {{ itemForm.errors.volume }}
                        </p>
                    </div>
                    <div>
                        <label for="item-unit" class="block text-xs font-medium text-content">Satuan *</label>
                        <input
                            id="item-unit"
                            v-model="itemForm.unit"
                            type="text"
                            required
                            class="mt-1 block w-full rounded-md border border-stroke bg-surface px-3 py-2 text-sm text-content focus:border-primary focus:outline-none"
                        />
                        <p v-if="itemForm.errors.unit" class="mt-1 text-xs text-negative">{{ itemForm.errors.unit }}</p>
                    </div>
                </div>

                <div>
                    <label for="item-price" class="block text-xs font-medium text-content">Harga Satuan (Rp) *</label>
                    <input
                        id="item-price"
                        v-model.number="itemForm.unit_price_rp"
                        type="number"
                        min="1"
                        required
                        class="mt-1 block w-full rounded-md border border-stroke bg-surface px-3 py-2 text-sm text-content focus:border-primary focus:outline-none"
                    />
                    <p v-if="itemForm.errors.unit_price_rp" class="mt-1 text-xs text-negative">
                        {{ itemForm.errors.unit_price_rp }}
                    </p>
                </div>

                <div>
                    <label for="item-standard" class="block text-xs font-medium text-content"
                        >Standar Acuan Harga (Opsional)</label
                    >
                    <select
                        id="item-standard"
                        v-model.number="itemForm.standard_id"
                        class="mt-1 block w-full rounded-md border border-stroke bg-surface px-3 py-2 text-xs text-content focus:border-primary focus:outline-none"
                    >
                        <option :value="null">-- Tanpa Standar Acuan --</option>
                        <option v-for="std in standards" :key="std.id" :value="std.id">
                            {{ std.name }} ({{ formatRupiah(std.price_rp) }}/{{ std.unit }})
                        </option>
                    </select>
                </div>

                <div>
                    <label for="item-reason" class="block text-xs font-medium text-content">
                        Alasan Perubahan CCO (Wajib - Aturan D13) *
                    </label>
                    <textarea
                        id="item-reason"
                        v-model="itemForm.reason"
                        rows="2"
                        required
                        placeholder="Contoh: Penyesuaian volume akibat kontur tanah berbatu di STA 0+150"
                        class="mt-1 block w-full rounded-md border border-stroke bg-surface px-3 py-2 text-sm text-content focus:border-primary focus:outline-none"
                    ></textarea>
                    <p v-if="itemForm.errors.reason" class="mt-1 text-xs text-negative">{{ itemForm.errors.reason }}</p>
                </div>
            </div>

            <template #actions>
                <div class="flex justify-end gap-2">
                    <Button variant="secondary" @click="itemDialog = false">Batal</Button>
                    <Button
                        variant="primary"
                        :disabled="itemForm.processing || !itemForm.reason"
                        @click="submitItemCco"
                    >
                        {{ itemForm.processing ? 'Menyimpan...' : 'Simpan CCO' }}
                    </Button>
                </div>
            </template>
        </Dialog>

        <!-- Dialog CCO Measurement -->
        <Dialog :open="measurementDialog" title="Formulir CCO Parameter Ukuran" @close="measurementDialog = false">
            <div v-if="editingMeasurement" class="space-y-4">
                <p class="text-xs text-content-secondary">
                    Penyesuaian target ukuran
                    <strong>{{ editingMeasurement.name }} ({{ editingMeasurement.unit }})</strong>.
                </p>

                <div>
                    <label for="m-val" class="block text-xs font-medium text-content">Target Nilai Baru *</label>
                    <input
                        id="m-val"
                        v-model="measurementForm.value"
                        type="text"
                        required
                        class="mt-1 block w-full rounded-md border border-stroke bg-surface px-3 py-2 text-sm text-content focus:border-primary focus:outline-none"
                    />
                    <p v-if="measurementForm.errors.value" class="mt-1 text-xs text-negative">
                        {{ measurementForm.errors.value }}
                    </p>
                </div>

                <div>
                    <label for="m-howto" class="block text-xs font-medium text-content"
                        >Petunjuk Pengukuran (Opsional)</label
                    >
                    <input
                        id="m-howto"
                        v-model="measurementForm.how_to"
                        type="text"
                        class="mt-1 block w-full rounded-md border border-stroke bg-surface px-3 py-2 text-sm text-content focus:border-primary focus:outline-none"
                    />
                </div>

                <div>
                    <label for="m-reason" class="block text-xs font-medium text-content">
                        Alasan Perubahan CCO (Wajib - Aturan D13) *
                    </label>
                    <textarea
                        id="m-reason"
                        v-model="measurementForm.reason"
                        rows="2"
                        required
                        placeholder="Contoh: Penyesuaian lebar jalan mengikuti kondisi eksisting bahu jalan"
                        class="mt-1 block w-full rounded-md border border-stroke bg-surface px-3 py-2 text-sm text-content focus:border-primary focus:outline-none"
                    ></textarea>
                    <p v-if="measurementForm.errors.reason" class="mt-1 text-xs text-negative">
                        {{ measurementForm.errors.reason }}
                    </p>
                </div>
            </div>

            <template #actions>
                <div class="flex justify-end gap-2">
                    <Button variant="secondary" @click="measurementDialog = false">Batal</Button>
                    <Button
                        variant="primary"
                        :disabled="measurementForm.processing || !measurementForm.reason"
                        @click="submitMeasurementCco"
                    >
                        {{ measurementForm.processing ? 'Menyimpan...' : 'Simpan Perubahan' }}
                    </Button>
                </div>
            </template>
        </Dialog>
    </AppLayout>
</template>
