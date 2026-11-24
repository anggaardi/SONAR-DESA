<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, onUnmounted, ref, watch } from 'vue';
import Alert from '@/components/ui/Alert.vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Table from '@/components/ui/Table.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate, formatRupiah } from '@/lib/format';
import { projectType } from '@/lib/labels';

interface InProgressAuditItem {
    id: number;
    status: string;
    source: string;
    file_name: string;
    written_total_rp: number | null;
    items_count: number;
    needs_check_count: number;
    read_ms?: number | null;
    error_code?: string | null;
    created_at: string | null;
}

interface AiReaderConfig {
    driver: string;
    provider: string;
    model: string;
    is_ai_active: boolean;
    sample_available: boolean;
}

interface ProjectItem {
    id: number;
    slug: string;
    name: string;
    type: string;
    fund_source: string;
    year: number;
    progress_pct: number;
    public_status: string;
    is_published: boolean;
    published_at: string | null;
    audit_id?: number | null;
    audit_status?: string | null;
    photos_count: number;
}

interface Props {
    village: {
        id: number;
        name: string;
        slug: string;
        regency: string;
    };
    // eslint-disable-next-line vue/prop-name-casing
    ai_reader?: AiReaderConfig;
    stats: {
        total_projects: number;
        published_projects: number;
        draft_projects: number;
        pending_reports: number;
        audits_reading?: number;
        audits_needs_review: number;
        audits_ready?: number;
        audits_read_failed: number;
    };
    // eslint-disable-next-line vue/prop-name-casing
    in_progress_audits?: InProgressAuditItem[];
    projects: ProjectItem[];
}

const props = defineProps<Props>();

const quickFileInputRef = ref<HTMLInputElement | null>(null);
const quickForm = useForm({
    file: null as File | null,
    use_sample: false,
});

function handleQuickFileSelect(event: Event): void {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (!file) return;

    const validTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
    if (!validTypes.includes(file.type) && !file.name.match(/\.(pdf|jpe?g|png)$/i)) {
        quickForm.setError('file', 'Format berkas harus berupa PDF, JPG, JPEG, atau PNG.');
        return;
    }

    if (file.size > 10 * 1024 * 1024) {
        quickForm.setError('file', 'Ukuran berkas maksimal 10 MB.');
        return;
    }

    quickForm.clearErrors('file');
    quickForm.file = file;
    quickForm.use_sample = false;
    quickForm.post('/audits', {
        forceFormData: true,
    });
}

function submitQuickSample(): void {
    quickForm.clearErrors('file');
    quickForm.file = null;
    quickForm.use_sample = true;
    quickForm.post('/audits');
}

const retryingAuditId = ref<number | null>(null);
function retryAuditReading(auditId: number): void {
    retryingAuditId.value = auditId;
    router.post(
        `/audits/${auditId}/retry`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                retryingAuditId.value = null;
            },
        },
    );
}

const replaceFileInputRef = ref<HTMLInputElement | null>(null);
const targetReplaceAuditId = ref<number | null>(null);
const replacingAuditId = ref<number | null>(null);

function triggerReplaceAuditFile(auditId: number): void {
    targetReplaceAuditId.value = auditId;
    if (replaceFileInputRef.value) {
        replaceFileInputRef.value.value = '';
        replaceFileInputRef.value.click();
    }
}

function handleReplaceAuditFileChange(event: Event): void {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    const auditId = targetReplaceAuditId.value;
    if (!file || !auditId) return;

    replacingAuditId.value = auditId;
    router.post(
        `/audits/${auditId}/replace-file`,
        { file },
        {
            forceFormData: true,
            onFinish: () => {
                replacingAuditId.value = null;
                targetReplaceAuditId.value = null;
            },
        },
    );
}

const deletingAuditId = ref<number | null>(null);
function deleteAuditDocument(auditItem: InProgressAuditItem): void {
    if (!confirm(`Yakin ingin menghapus dokumen RAB "${auditItem.file_name}"?`)) {
        return;
    }

    deletingAuditId.value = auditItem.id;
    router.delete(`/audits/${auditItem.id}`, {
        preserveScroll: true,
        onFinish: () => {
            deletingAuditId.value = null;
        },
    });
}

const hasReadingAudits = computed(() => {
    return (
        (props.stats.audits_reading ?? 0) > 0 ||
        (props.in_progress_audits?.some((a) => a.status === 'reading') ?? false)
    );
});

let pollTimer: ReturnType<typeof setInterval> | null = null;
const pollSeconds = ref(0);

function startPolling(): void {
    if (pollTimer) return;
    pollSeconds.value = 0;
    pollTimer = setInterval(() => {
        pollSeconds.value += 2;
        if (!hasReadingAudits.value || pollSeconds.value >= 180) {
            stopPolling();
            return;
        }
        router.reload({
            only: ['stats', 'in_progress_audits'],
        });
    }, 2000);
}

function stopPolling(): void {
    if (pollTimer) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
}

watch(
    hasReadingAudits,
    (reading) => {
        if (reading) {
            startPolling();
        } else {
            stopPolling();
        }
    },
    { immediate: true },
);

onUnmounted(() => {
    stopPolling();
});

function formatReadDuration(ms?: number | null): string {
    if (!ms || ms <= 0) return '';
    if (ms < 1000) return `${ms} ms`;
    return `${(ms / 1000).toFixed(1)} dtk`;
}

const tableColumns = [
    { key: 'name', label: 'Proyek Fisik', isRowHeader: true },
    { key: 'fund_source', label: 'Sumber Dana' },
    { key: 'progress', label: 'Progres Fisik' },
    { key: 'status', label: 'Status' },
    { key: 'actions', label: 'Aksi', align: 'right' as const },
];
</script>

<template>
    <AppLayout>
        <Head :title="`Dashboard Pemerintah Desa - ${village.name}`" />

        <template #breadcrumb>
            <Link href="/" class="transition-colors hover:text-primary">Beranda</Link>
            <span>/</span>
            <span class="font-medium text-content">Dashboard Pemerintah {{ village.name }}</span>
        </template>

        <template #headerActions>
            <Button variant="primary" href="/audits/create" size="sm">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                    />
                </svg>
                Unggah Dokumen RAB
            </Button>
        </template>

        <template #nav>
            <Link
                href="/desa"
                class="flex items-center gap-3 rounded-xl bg-primary-subtle px-3 py-2.5 text-sm font-semibold text-primary transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
            >
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
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
                class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-content-secondary transition-colors hover:bg-surface-muted hover:text-content focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
            >
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
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
                :href="`/d/${village.slug}`"
                class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-content-secondary transition-colors hover:bg-surface-muted hover:text-content focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
            >
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
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
                    <span class="truncate font-medium text-content">{{ village.name }}</span>
                    <span class="truncate text-content-tertiary">Kab. {{ village.regency }}</span>
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

        <!-- Main Dashboard View -->
        <div class="flex flex-col gap-6">
            <!-- Header Section -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-content md:text-3xl">
                        Dashboard Pemerintah {{ village.name }}
                    </h1>
                    <p class="mt-1 text-sm text-content-secondary">
                        Kabupaten {{ village.regency }} &middot; Kelola dokumen anggaran, publikasi proyek, dan pantau
                        progres fisik.
                    </p>
                </div>
                <!-- Tombol aksi hanya untuk mobile (di desktop sudah ada di sticky header) -->
                <div class="flex flex-wrap items-center gap-2.5 sm:gap-3 md:hidden">
                    <Button variant="primary" href="/audits/create">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                            />
                        </svg>
                        Unggah Dokumen RAB
                    </Button>
                </div>
            </div>

            <!-- Alerts: Perhatian Audit & Laporan Warga -->
            <div v-if="stats.audits_needs_review > 0 || stats.pending_reports > 0" class="flex flex-col gap-3">
                <Alert v-if="stats.audits_needs_review > 0" color="warning" title="Dokumen RAB perlu diperiksa">
                    Terdapat {{ stats.audits_needs_review }} dokumen audit yang memiliki item di luar standar harga
                    kabupaten dan perlu ditinjau sebelum dapat diterbitkan.
                </Alert>
                <Alert v-if="stats.pending_reports > 0" color="info" title="Laporan verifikasi warga masuk">
                    Ada {{ stats.pending_reports }} laporan fisik dari warga di lapangan yang masuk untuk proyek desa
                    ini.
                </Alert>
            </div>

            <!-- Panel Ingesti & Ekstraksi RAB Berbasis AI Vision -->
            <section aria-labelledby="heading-ai-vision">
                <Card
                    padding="app"
                    class="border-primary/30 bg-gradient-to-r from-primary-subtle/20 via-surface to-surface"
                >
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div class="space-y-1.5">
                            <div class="flex flex-wrap items-center gap-2">
                                <Badge :color="ai_reader?.is_ai_active ? 'positive' : 'info'">
                                    {{ ai_reader?.is_ai_active ? 'AI Vision Aktif' : 'Mode Fixture Offline' }}
                                </Badge>
                                <span
                                    v-if="ai_reader?.model"
                                    class="rounded-md border border-stroke bg-surface-subtle px-2 py-0.5 font-mono text-[11px] font-medium text-content-secondary"
                                >
                                    {{ ai_reader.model }} ({{ ai_reader.provider }})
                                </span>
                            </div>
                            <h2 id="heading-ai-vision" class="text-base font-bold text-content sm:text-lg">
                                Ingesti &amp; Audit Otomatis Dokumen RAB dengan AI Vision
                            </h2>
                            <p class="max-w-2xl text-xs text-content-secondary">
                                Unggah berkas PDF atau foto lembar RAB fisik berstempel langsung dari dashboard. Model
                                AI Vision mengekstrak rincian tabel, mendeteksi kolom kabur/ragu, dan mencocokkan harga
                                ke Standar Satuan Harga (SSH) Kabupaten {{ village.regency }} secara deterministik.
                            </p>
                            <p v-if="quickForm.errors.file" class="text-xs font-semibold text-negative">
                                {{ quickForm.errors.file }}
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-2.5">
                            <input
                                ref="quickFileInputRef"
                                type="file"
                                accept=".pdf,application/pdf,image/jpeg,image/png,image/jpg"
                                class="hidden"
                                @change="handleQuickFileSelect"
                            />
                            <Button
                                type="button"
                                variant="primary"
                                size="sm"
                                :loading="quickForm.processing && !quickForm.use_sample"
                                @click="quickFileInputRef?.click()"
                            >
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M13 10V3L4 14h7v7l9-11h-7z"
                                    />
                                </svg>
                                <span>Pilih &amp; Ekstrak RAB (AI)</span>
                            </Button>

                            <Button
                                v-if="ai_reader?.sample_available !== false"
                                type="button"
                                variant="secondary"
                                size="sm"
                                :loading="quickForm.processing && quickForm.use_sample"
                                @click="submitQuickSample"
                            >
                                <span>Uji Contoh RAB Melati</span>
                            </Button>

                            <Button variant="ghost" size="sm" href="/desa/projects/create">
                                <span>Isi Manual</span>
                            </Button>
                        </div>
                    </div>
                </Card>
            </section>

            <!-- Dokumen RAB Dalam Proses (Ingesti & Audit) -->
            <section
                v-if="in_progress_audits && in_progress_audits.length > 0"
                aria-labelledby="heading-dokumen-proses"
            >
                <div class="rounded-lg border border-primary/30 bg-primary-subtle/10 p-4 sm:p-6">
                    <input
                        ref="replaceFileInputRef"
                        type="file"
                        accept=".pdf,application/pdf,image/jpeg,image/png,image/jpg"
                        class="hidden"
                        @change="handleReplaceAuditFileChange"
                    />
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h2
                                id="heading-dokumen-proses"
                                class="flex items-center gap-2 text-base font-bold text-content sm:text-lg"
                            >
                                <svg class="h-5 w-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                                    />
                                </svg>
                                Dokumen RAB Dalam Proses
                            </h2>
                            <p class="mt-0.5 text-xs text-content-secondary">
                                Berkas RAB fisik yang sedang diekstrak AI atau menunggu verifikasi Gatekeeper sebelum
                                diterbitkan.
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span
                                v-if="hasReadingAudits"
                                class="inline-flex items-center gap-1.5 rounded-full border border-primary/30 bg-surface px-3 py-1 text-xs font-semibold text-primary"
                            >
                                <span class="h-2 w-2 animate-ping rounded-full bg-primary" />
                                Memantau Ekstraksi AI...
                            </span>
                            <Badge color="info"> {{ in_progress_audits.length }} Dokumen </Badge>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <div
                            v-for="auditItem in in_progress_audits"
                            :key="auditItem.id"
                            class="flex flex-col justify-between rounded-xl border border-stroke bg-surface p-4 shadow-xs transition-shadow hover:shadow-md"
                        >
                            <div>
                                <div class="flex items-start justify-between gap-2">
                                    <h3
                                        class="truncate text-xs font-bold text-content sm:text-sm"
                                        :title="auditItem.file_name"
                                    >
                                        {{ auditItem.file_name }}
                                    </h3>
                                    <Badge
                                        :color="
                                            auditItem.status === 'reading'
                                                ? 'info'
                                                : auditItem.status === 'needs_review'
                                                  ? 'warning'
                                                  : auditItem.status === 'ready'
                                                    ? 'positive'
                                                    : 'negative'
                                        "
                                    >
                                        {{
                                            auditItem.status === 'reading'
                                                ? 'Sedang Dibaca AI'
                                                : auditItem.status === 'needs_review'
                                                  ? `${auditItem.needs_check_count} Perlu Dicek`
                                                  : auditItem.status === 'ready'
                                                    ? 'Siap Terbit'
                                                    : 'Gagal Ekstraksi'
                                        }}
                                    </Badge>
                                </div>

                                <p class="mt-1 text-[11px] text-content-tertiary">
                                    {{ auditItem.items_count }} item rincian &middot;
                                    {{
                                        auditItem.written_total_rp
                                            ? formatRupiah(auditItem.written_total_rp)
                                            : 'Total belum terhitung'
                                    }}
                                    <template v-if="auditItem.read_ms">
                                        &middot; AI {{ formatReadDuration(auditItem.read_ms) }}
                                    </template>
                                </p>

                                <p
                                    v-if="auditItem.status === 'read_failed' && auditItem.error_code"
                                    class="mt-1.5 rounded bg-negative-subtle/30 px-2 py-1 font-mono text-[10px] text-negative"
                                >
                                    Kode: {{ auditItem.error_code }}
                                </p>
                            </div>

                            <div
                                class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-stroke pt-3"
                            >
                                <span class="text-[11px] text-content-tertiary">
                                    {{ auditItem.created_at ? formatDate(auditItem.created_at) : '' }}
                                </span>
                                <div class="flex flex-wrap items-center gap-2.5">
                                    <button
                                        v-if="auditItem.status === 'read_failed'"
                                        type="button"
                                        class="text-xs font-semibold text-content-secondary hover:text-primary hover:underline"
                                        :disabled="retryingAuditId === auditItem.id"
                                        @click="retryAuditReading(auditItem.id)"
                                    >
                                        {{ retryingAuditId === auditItem.id ? 'Memproses...' : 'Coba Ulang AI' }}
                                    </button>
                                    <button
                                        type="button"
                                        class="text-xs font-semibold text-content-secondary hover:text-primary hover:underline"
                                        :disabled="replacingAuditId === auditItem.id"
                                        @click="triggerReplaceAuditFile(auditItem.id)"
                                    >
                                        {{ replacingAuditId === auditItem.id ? 'Mengunggah...' : 'Ganti File' }}
                                    </button>
                                    <button
                                        type="button"
                                        class="text-xs font-semibold text-negative hover:underline"
                                        :disabled="deletingAuditId === auditItem.id"
                                        @click="deleteAuditDocument(auditItem)"
                                    >
                                        {{ deletingAuditId === auditItem.id ? 'Menghapus...' : 'Hapus' }}
                                    </button>
                                    <Link
                                        :href="`/audits/${auditItem.id}`"
                                        class="inline-flex items-center gap-1 text-xs font-bold text-primary hover:text-primary-hover hover:underline"
                                    >
                                        <span>{{ auditItem.status === 'ready' ? 'Terbitkan' : 'Lanjutkan' }}</span>
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M9 5l7 7-7 7"
                                            />
                                        </svg>
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Ringkasan Statistik IDDS Cards -->
            <section aria-labelledby="heading-ringkasan">
                <h2 id="heading-ringkasan" class="sr-only">Ringkasan Statistik Proyek</h2>
                <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <Card padding="app">
                        <p class="text-sm font-medium text-content-secondary">Total Proyek</p>
                        <p class="mt-2 text-3xl font-bold text-content tabular-nums">
                            {{ stats.total_projects }}
                        </p>
                        <p class="mt-1 text-xs text-content-tertiary">Semua kegiatan pembangunan</p>
                    </Card>

                    <Card padding="app">
                        <p class="text-sm font-medium text-content-secondary">Proyek Terbit</p>
                        <p class="mt-2 text-3xl font-bold text-primary tabular-nums">
                            {{ stats.published_projects }}
                        </p>
                        <p class="mt-1 text-xs text-content-tertiary">Dapat diakses warga via QR</p>
                    </Card>

                    <Card padding="app">
                        <p class="text-sm font-medium text-content-secondary">Draf Proyek</p>
                        <p class="mt-2 text-3xl font-bold text-content-secondary tabular-nums">
                            {{ stats.draft_projects }}
                        </p>
                        <p class="mt-1 text-xs text-content-tertiary">Dalam tahap penyusunan RAB</p>
                    </Card>

                    <Card padding="app">
                        <p class="text-sm font-medium text-content-secondary">Laporan Warga</p>
                        <p class="mt-2 text-3xl font-bold text-content tabular-nums">
                            {{ stats.pending_reports }}
                        </p>
                        <p class="mt-1 text-xs text-content-tertiary">Hasil Cek Spek lapangan</p>
                    </Card>
                </div>
            </section>

            <!-- Tabel Daftar Proyek Fisik -->
            <section id="daftar-proyek" aria-labelledby="heading-proyek">
                <div class="rounded-lg border border-stroke bg-surface p-4 sm:p-6">
                    <div class="mb-4 flex items-center justify-between">
                        <div>
                            <h2 id="heading-proyek" class="text-lg font-semibold text-content">Daftar Proyek Fisik</h2>
                            <p class="mt-0.5 text-xs text-content-secondary">
                                Proyek pembangunan fisik yang tercatat di {{ village.name }}.
                            </p>
                        </div>
                        <Badge color="neutral"> {{ projects.length }} Proyek </Badge>
                    </div>

                    <!-- Empty State jika belum ada proyek -->
                    <EmptyState
                        v-if="projects.length === 0"
                        illustration="plang-empty"
                        title="Belum ada proyek terdaftar"
                        description="Mulai publikasikan keterbukaan anggaran dengan mengunggah dokumen RAB proyek fisik desa."
                    >
                        <Button variant="primary" href="/audits/create"> Unggah Dokumen RAB </Button>
                    </EmptyState>

                    <template v-else>
                        <!-- Desktop Table View (IDDS Table Component) -->
                        <div class="hidden sm:block">
                            <Table :columns="tableColumns" caption="Daftar Proyek Fisik Desa">
                                <tr
                                    v-for="project in projects"
                                    :key="project.id"
                                    class="transition-colors hover:bg-surface-muted"
                                >
                                    <td class="px-4 py-3">
                                        <p class="font-medium text-content">{{ project.name }}</p>
                                        <p class="text-xs text-content-tertiary">
                                            {{ projectType[project.type as keyof typeof projectType] ?? project.type }}
                                            &middot; Tahun {{ project.year }}
                                        </p>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-content-secondary">
                                        {{ project.fund_source }}
                                    </td>
                                    <td class="px-4 py-3 text-sm tabular-nums">
                                        <div class="flex items-center gap-2">
                                            <span class="font-medium text-content">{{ project.progress_pct }}%</span>
                                            <span v-if="project.photos_count > 0" class="text-xs text-content-tertiary">
                                                ({{ project.photos_count }} foto)
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <Badge :color="project.is_published ? 'positive' : 'neutral'">
                                            {{ project.is_published ? 'Terbit' : 'Draf' }}
                                        </Badge>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <!-- Review & Terbitkan (untuk draf yang punya dokumen audit) -->
                                            <Link
                                                v-if="project.audit_id && !project.is_published"
                                                :href="`/audits/${project.audit_id}`"
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-warning-border bg-warning-subtle text-warning transition-colors hover:bg-warning hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-warning"
                                                title="Review &amp; Terbitkan RAB"
                                                aria-label="Review &amp; Terbitkan RAB"
                                            >
                                                <svg
                                                    class="h-4 w-4"
                                                    fill="none"
                                                    viewBox="0 0 24 24"
                                                    stroke="currentColor"
                                                    aria-hidden="true"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"
                                                    />
                                                </svg>
                                            </Link>

                                            <!-- Kelola Proyek & CCO -->
                                            <Link
                                                :href="`/desa/projects/${project.id}`"
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-primary/20 bg-primary-subtle text-primary transition-colors hover:bg-primary hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                                                title="Kelola &amp; CCO"
                                                aria-label="Kelola &amp; CCO"
                                            >
                                                <svg
                                                    class="h-4 w-4"
                                                    fill="none"
                                                    viewBox="0 0 24 24"
                                                    stroke="currentColor"
                                                    aria-hidden="true"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"
                                                    />
                                                </svg>
                                            </Link>

                                            <!-- Lihat Halaman Publik -->
                                            <Link
                                                v-if="project.is_published"
                                                :href="`/p/${project.slug}`"
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-stroke bg-surface text-content-secondary transition-colors hover:border-stroke-strong hover:bg-surface-muted hover:text-content focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                                                title="Lihat Halaman Publik"
                                                aria-label="Lihat Halaman Publik"
                                            >
                                                <svg
                                                    class="h-4 w-4"
                                                    fill="none"
                                                    viewBox="0 0 24 24"
                                                    stroke="currentColor"
                                                    aria-hidden="true"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                                                    />
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                                                    />
                                                </svg>
                                            </Link>

                                            <!-- Cetak Poster QR -->
                                            <Link
                                                v-if="project.is_published"
                                                :href="`/projects/${project.id}/qr`"
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-stroke bg-surface text-content-secondary transition-colors hover:border-stroke-strong hover:bg-surface-muted hover:text-content focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                                                title="Cetak Poster QR"
                                                aria-label="Cetak Poster QR"
                                            >
                                                <svg
                                                    class="h-4 w-4"
                                                    fill="none"
                                                    viewBox="0 0 24 24"
                                                    stroke="currentColor"
                                                    aria-hidden="true"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M4 3h5a1 1 0 011 1v5a1 1 0 01-1 1H4a1 1 0 01-1-1V4a1 1 0 011-1zM15 3h5a1 1 0 011 1v5a1 1 0 01-1 1h-5a1 1 0 01-1-1V4a1 1 0 011-1zM4 14h5a1 1 0 011 1v5a1 1 0 01-1 1H4a1 1 0 01-1-1v-5a1 1 0 011-1zM14 14h3v3M21 14v.01M14 21h.01M17 21h4v-4"
                                                    />
                                                </svg>
                                            </Link>
                                        </div>
                                    </td>
                                </tr>
                            </Table>
                        </div>

                        <!-- Mobile Card List View (No horizontal scroll, IDDS mobile-friendly) -->
                        <div class="flex flex-col gap-3 sm:hidden">
                            <Card v-for="project in projects" :key="project.id" padding="base" surface="subtle">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <p class="font-medium text-content">{{ project.name }}</p>
                                        <p class="mt-0.5 text-xs text-content-tertiary">
                                            {{ projectType[project.type as keyof typeof projectType] ?? project.type }}
                                            &middot; Tahun {{ project.year }}
                                        </p>
                                    </div>
                                    <Badge :color="project.is_published ? 'positive' : 'neutral'">
                                        {{ project.is_published ? 'Terbit' : 'Draf' }}
                                    </Badge>
                                </div>

                                <div class="mt-3 grid grid-cols-2 gap-2 border-t border-stroke pt-2.5 text-xs">
                                    <div>
                                        <span class="text-content-secondary">Sumber Dana:</span>
                                        <p class="font-medium text-content">{{ project.fund_source }}</p>
                                    </div>
                                    <div>
                                        <span class="text-content-secondary">Progres Fisik:</span>
                                        <p class="font-medium text-content tabular-nums">
                                            {{ project.progress_pct }}%
                                            <span v-if="project.photos_count > 0" class="text-content-tertiary">
                                                ({{ project.photos_count }} foto)
                                            </span>
                                        </p>
                                    </div>
                                </div>

                                <div class="mt-3 flex items-center justify-between gap-2 border-t border-stroke pt-2.5">
                                    <span class="text-xs font-medium text-content-secondary">Aksi:</span>
                                    <div class="flex items-center gap-1.5">
                                        <Link
                                            v-if="project.audit_id && !project.is_published"
                                            :href="`/audits/${project.audit_id}`"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-warning-border bg-warning-subtle text-warning transition-colors hover:bg-warning hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-warning"
                                            title="Review &amp; Terbitkan RAB"
                                            aria-label="Review &amp; Terbitkan RAB"
                                        >
                                            <svg
                                                class="h-4 w-4"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke="currentColor"
                                                aria-hidden="true"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"
                                                />
                                            </svg>
                                        </Link>
                                        <Link
                                            :href="`/desa/projects/${project.id}`"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-primary/20 bg-primary-subtle text-primary transition-colors hover:bg-primary hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                                            title="Kelola &amp; CCO"
                                            aria-label="Kelola &amp; CCO"
                                        >
                                            <svg
                                                class="h-4 w-4"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke="currentColor"
                                                aria-hidden="true"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"
                                                />
                                            </svg>
                                        </Link>
                                        <Link
                                            v-if="project.is_published"
                                            :href="`/p/${project.slug}`"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-stroke bg-surface text-content-secondary transition-colors hover:border-stroke-strong hover:bg-surface-muted hover:text-content focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                                            title="Lihat Halaman Publik"
                                            aria-label="Lihat Halaman Publik"
                                        >
                                            <svg
                                                class="h-4 w-4"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke="currentColor"
                                                aria-hidden="true"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                                                />
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                                                />
                                            </svg>
                                        </Link>
                                        <Link
                                            v-if="project.is_published"
                                            :href="`/projects/${project.id}/qr`"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-stroke bg-surface text-content-secondary transition-colors hover:border-stroke-strong hover:bg-surface-muted hover:text-content focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                                            title="Cetak Poster QR"
                                            aria-label="Cetak Poster QR"
                                        >
                                            <svg
                                                class="h-4 w-4"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke="currentColor"
                                                aria-hidden="true"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M4 3h5a1 1 0 011 1v5a1 1 0 01-1 1H4a1 1 0 01-1-1V4a1 1 0 011-1zM15 3h5a1 1 0 011 1v5a1 1 0 01-1 1h-5a1 1 0 01-1-1V4a1 1 0 011-1zM4 14h5a1 1 0 011 1v5a1 1 0 01-1 1H4a1 1 0 01-1-1v-5a1 1 0 011-1zM14 14h3v3M21 14v.01M14 21h.01M17 21h4v-4"
                                                />
                                            </svg>
                                        </Link>
                                    </div>
                                </div>
                            </Card>
                        </div>
                    </template>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
