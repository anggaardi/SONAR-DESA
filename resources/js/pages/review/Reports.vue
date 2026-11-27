<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate, formatDateTime, formatNumber } from '@/lib/format';
import { checkSpec, reportType } from '@/lib/labels';

interface ProjectOption {
    id: number;
    name: string;
    slug: string;
    public_status: 'none' | 'in_review' | 'reviewed';
    reports_count: number;
    unread_count: number;
    pending_count: number;
}

interface ReportAnswerItem {
    id: number;
    measurement_name: string;
    rab_value: string | null;
    rab_unit: string | null;
    answer: 'match' | 'mismatch' | 'unmeasurable';
    measured_value: string | null;
    measured_unit: string | null;
}

interface ReportPhotoItem {
    id: number;
    position: number;
    url: string;
}

interface InboxReportItem {
    id: number;
    project_id: number;
    project_name: string;
    project_slug: string;
    project_public_status: 'none' | 'in_review' | 'reviewed';
    project_public_url: string;
    project_document_url: string;
    reporter_label: string;
    reporter_name: string;
    reporter_username: string;
    reporter_email: string;
    reporter_initials: string;
    is_google_account: boolean;
    kind: 'mismatch' | 'match' | 'unmeasurable';
    distance_m: number | null;
    note: string | null;
    superseded: boolean;
    invalid_reason: string | null;
    bpd_verdict: 'valid' | 'invalid' | null;
    is_verified: boolean;
    bpd_verified_at: string | null;
    bpd_reply: string | null;
    bpd_replied_at: string | null;
    is_new: boolean;
    created_at: string | null;
    answers: ReportAnswerItem[];
    photos: ReportPhotoItem[];
}

type StatusFilterKey = 'all' | 'new' | 'pending' | 'valid' | 'invalid';

const props = defineProps<{
    village: { name: string; slug: string; regency?: string };
    projects: ProjectOption[];
    reports: InboxReportItem[];
    selectedReportId: number | null;
}>();

const activeReportId = ref<number | null>(props.selectedReportId ?? props.reports[0]?.id ?? null);
const searchQuery = ref('');
const selectedProjectFilter = ref<number>(0);
const statusFilter = ref<StatusFilterKey>('all');

const confirmingValid = ref(false);
const invalidReasonPresets = ['Foto tidak relevan', 'Bukan proyek ini', 'Duplikat'] as const;
const invalidForm = useForm({ reason: '' });

const replyForm = useForm({
    reply: '',
});

// Native <dialog> untuk Konfirmasi Pembatalan Laporan & Lightbox Foto Bukti (DESIGN.md §6B)
const invalidateDialogRef = ref<HTMLDialogElement | null>(null);
const lightboxDialogRef = ref<HTMLDialogElement | null>(null);
const isDialogOpen = ref(false);
const activeLightboxPhoto = ref<{
    url: string;
    label: string;
    distance_m: number | null;
    created_at: string | null;
} | null>(null);

const quickReplyTemplates = [
    {
        title: 'Terima & cek lokasi',
        subtitle: 'Jadwalkan tinjauan fisik bersama Pemdes',
        text: 'Terima kasih atas laporan Cek Spek Anda. BPD telah menerima bukti foto dan menjadwalkan pengecekan fisik di lokasi proyek bersama Pemerintah Desa.',
    },
    {
        title: 'Masuk sidang pleno BPD',
        subtitle: 'Catat sebagai bahan musyawarah siklus aktif',
        text: 'Laporan dan hasil ukur lapangan ini dinyatakan valid serta dimasukkan sebagai bahan tinjauan resmi BPD pada siklus berjalan.',
    },
    {
        title: 'Sesuai spesifikasi RAB',
        subtitle: 'Konfirmasi ukuran sudah sesuai dokumen',
        text: 'Terima kasih atas partisipasinya menjaga transparansi desa. Hasil pengukuran lapangan telah sesuai dengan spesifikasi teknis pada RAB proyek.',
    },
    {
        title: 'Minta foto lebih jelas',
        subtitle: 'Titik ukur belum terlihat jelas di foto',
        text: 'Foto dokumentasi belum memperlihatkan titik pengukuran secara jelas. Silakan kirim pembaruan laporan dari lokasi proyek bila diperlukan.',
    },
] as const;

const filteredReports = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();

    return props.reports.filter((r) => {
        if (selectedProjectFilter.value > 0 && r.project_id !== selectedProjectFilter.value) {
            return false;
        }
        if (statusFilter.value === 'new' && !r.is_new) {
            return false;
        }
        if (statusFilter.value === 'valid' && r.bpd_verdict !== 'valid') {
            return false;
        }
        if (statusFilter.value === 'invalid' && !isInvalidVerdict(r)) {
            return false;
        }
        if (statusFilter.value === 'pending' && (r.is_verified || r.superseded)) {
            return false;
        }
        if (q !== '') {
            const haystack = [
                r.reporter_name,
                r.reporter_username,
                r.reporter_email,
                r.reporter_label,
                r.project_name,
                r.note ?? '',
            ]
                .join(' ')
                .toLowerCase();
            return haystack.includes(q);
        }
        return true;
    });
});

const selectedReport = computed<InboxReportItem | null>(() => {
    if (props.reports.length === 0) return null;
    const found = props.reports.find((r) => r.id === activeReportId.value);
    return found ?? filteredReports.value[0] ?? props.reports[0] ?? null;
});

watch(
    () => selectedReport.value?.id,
    () => {
        confirmingValid.value = false;
        invalidForm.reset();
        replyForm.reset();
        replyForm.clearErrors();
    },
    { immediate: true },
);

const hasSentReply = computed(() => {
    const reply = selectedReport.value?.bpd_reply;
    return typeof reply === 'string' && reply.trim() !== '';
});

const unreadCount = computed(() => props.reports.filter((r) => r.is_new && !r.superseded).length);

function selectReport(report: InboxReportItem): void {
    activeReportId.value = report.id;
}

function isInvalidVerdict(report: InboxReportItem): boolean {
    return report.bpd_verdict === 'invalid' || report.invalid_reason !== null;
}

function selectProject(projectId: number): void {
    if (selectedProjectFilter.value === projectId) {
        selectedProjectFilter.value = 0;
        return;
    }
    selectedProjectFilter.value = projectId;
    const first = props.reports.find((r) => r.project_id === projectId);
    if (first) {
        activeReportId.value = first.id;
    }
}

function startValid(): void {
    confirmingValid.value = true;
}

async function openInvalidateDialog(): Promise<void> {
    confirmingValid.value = false;
    invalidForm.reset();
    invalidForm.clearErrors();
    isDialogOpen.value = true;
    await nextTick();
    invalidateDialogRef.value?.showModal();
}

function closeInvalidateDialog(): void {
    invalidateDialogRef.value?.close();
    isDialogOpen.value = false;
}

function submitValid(reportId: number): void {
    confirmingValid.value = false;
    const trimmedNote = !hasSentReply.value && replyForm.reply.trim().length >= 3 ? replyForm.reply.trim() : null;
    router.post(`/bpd/reports/${reportId}/validate`, trimmedNote ? { note: trimmedNote } : {}, {
        preserveScroll: true,
        onSuccess: () => {
            if (trimmedNote) {
                replyForm.reset();
            }
        },
    });
}

function submitInvalid(reportId: number): void {
    const trimmedNote = !hasSentReply.value && replyForm.reply.trim().length >= 3 ? replyForm.reply.trim() : null;
    invalidForm
        .transform((data) => (trimmedNote ? { ...data, note: trimmedNote } : data))
        .post(`/bpd/reports/${reportId}/invalidate`, {
            preserveScroll: true,
            onSuccess: () => {
                invalidForm.reset();
                if (trimmedNote) {
                    replyForm.reset();
                }
                closeInvalidateDialog();
            },
        });
}

function applyTemplate(text: string): void {
    if (hasSentReply.value) return;
    replyForm.reply = text;
}

function submitReply(): void {
    if (!selectedReport.value || hasSentReply.value) return;
    replyForm.post(`/bpd/reports/${selectedReport.value.id}/reply`, {
        preserveScroll: true,
        onSuccess: () => {
            replyForm.reset();
        },
    });
}

async function openPhotoLightbox(report: InboxReportItem, photo: ReportPhotoItem): Promise<void> {
    activeLightboxPhoto.value = {
        url: photo.url,
        label: `${report.reporter_name} (${report.reporter_label}) — Foto ${photo.position}`,
        distance_m: report.distance_m,
        created_at: report.created_at,
    };
    isDialogOpen.value = true;
    await nextTick();
    lightboxDialogRef.value?.showModal();
}

function closePhotoLightbox(): void {
    lightboxDialogRef.value?.close();
    activeLightboxPhoto.value = null;
    isDialogOpen.value = false;
}

function handleBackdropClick(event: MouseEvent, closeFn: () => void): void {
    if (event.target === event.currentTarget) {
        closeFn();
    }
}

function reportKindBadgeColor(kind: InboxReportItem['kind']): 'negative' | 'positive' | 'neutral' {
    if (kind === 'mismatch') return 'negative';
    if (kind === 'match') return 'positive';
    return 'neutral';
}
</script>

<template>
    <AppLayout>
        <Head :title="`Kotak Warga — BPD ${village.name}`" />

        <template #breadcrumb>
            <Link href="/" class="transition-colors hover:text-primary">Beranda</Link>
            <span>/</span>
            <Link href="/bpd" class="transition-colors hover:text-primary">Pengawasan BPD</Link>
            <span>/</span>
            <span class="font-semibold text-content">Kotak warga</span>
        </template>

        <template #headerActions>
            <Button variant="secondary" size="sm" :href="`/d/${village.slug}`">
                <span>Papan publik desa</span>
            </Button>
        </template>

        <template #nav>
            <Link
                href="/bpd"
                class="flex min-h-11 items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-content-secondary transition-colors hover:bg-surface-muted hover:text-content focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
            >
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="1.8"
                        d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"
                    />
                </svg>
                <span>Siklus tinjauan BPD</span>
            </Link>
            <Link
                href="/bpd/reports"
                class="flex min-h-11 items-center justify-between gap-2 rounded-lg bg-primary-subtle px-3 py-2 text-sm font-semibold text-primary transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
            >
                <span class="flex items-center gap-3">
                    <svg
                        class="h-5 w-5 shrink-0"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="1.8"
                            d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"
                        />
                    </svg>
                    <span>Kotak warga</span>
                </span>
                <Badge v-if="unreadCount > 0" color="info">{{ unreadCount }} baru</Badge>
            </Link>
            <Link
                href="/bpd#daftar-proyek-bpd"
                class="flex min-h-11 items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-content-secondary transition-colors hover:bg-surface-muted hover:text-content focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
            >
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="1.8"
                        d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"
                    />
                </svg>
                <span>Daftar proyek desa</span>
            </Link>
            <Link
                :href="`/d/${village.slug}`"
                class="flex min-h-11 items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-content-secondary transition-colors hover:bg-surface-muted hover:text-content focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
            >
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="1.8"
                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                    />
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="1.8"
                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                    />
                </svg>
                <span>Papan publik desa</span>
            </Link>
        </template>

        <template #sidebarFooter>
            <div class="flex items-center justify-between gap-2 text-xs text-content-secondary">
                <div class="flex min-w-0 flex-col">
                    <span class="truncate font-semibold text-content">{{ village.name }}</span>
                    <span class="truncate text-content-tertiary">
                        {{ village.regency ? `Kab. ${village.regency}` : 'Badan Permusyawaratan Desa' }}
                    </span>
                </div>
                <Link
                    href="/logout"
                    method="post"
                    as="button"
                    class="font-semibold text-negative underline-offset-2 hover:underline"
                >
                    Keluar
                </Link>
            </div>
        </template>

        <div class="flex flex-col gap-6">
            <!-- HeroBanner SIM-BPD Venice Blue (DESIGN.md §6C) -->
            <section class="rounded-2xl bg-primary p-6 text-content-inverse shadow-xs" aria-labelledby="heading-inbox">
                <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-center">
                    <div class="space-y-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                class="inline-flex items-center gap-2 rounded-full border border-stroke-strong bg-primary-hover px-3 py-1 text-sm font-semibold text-content-inverse"
                            >
                                <span>Verifikasi Laporan Fisik Warga</span>
                            </span>
                            <span
                                class="inline-flex items-center gap-1.5 rounded-full bg-primary-hover px-3 py-1 text-sm font-medium text-content-inverse tabular-nums"
                            >
                                <span>{{ reports.length }} laporan masuk &middot; {{ unreadCount }} belum dibuka</span>
                            </span>
                        </div>
                        <h1 id="heading-inbox" class="text-2xl font-bold text-content-inverse sm:text-3xl">
                            Kotak warga BPD {{ village.name }}
                        </h1>
                        <p class="max-w-2xl text-sm font-medium text-content-inverse/90">
                            Verifikasi ukuran fisik dan bukti foto ber-geotag di panel kiri, serta tinjau identitas
                            pelapor akun Google di daftar kanan.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <Button variant="secondary" href="/bpd">
                            <span>Kembali ke siklus BPD</span>
                        </Button>
                        <Button variant="secondary" :href="`/d/${village.slug}`">
                            <span>Papan publik</span>
                        </Button>
                    </div>
                </div>
            </section>

            <!-- Daftar Proyek Desa gaya ProjectItem + MetricPill (DESIGN.md §6 ProjectItem & MetricPill) -->
            <section
                v-if="projects.length > 0"
                class="rounded-2xl border border-stroke bg-surface p-6 shadow-xs"
                aria-labelledby="heading-daftar-proyek-laporan"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h2 id="heading-daftar-proyek-laporan" class="text-lg font-semibold text-content">
                            Filter per proyek fisik desa
                        </h2>
                        <p class="mt-0.5 text-sm text-content-secondary">
                            Pilih kartu proyek untuk menyaring laporan warga. Klik kembali untuk menampilkan semua.
                        </p>
                    </div>
                    <Badge color="neutral">{{ projects.length }} proyek</Badge>
                </div>

                <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    <button
                        v-for="proj in projects"
                        :key="proj.id"
                        type="button"
                        :aria-pressed="selectedProjectFilter === proj.id"
                        :class="[
                            'flex flex-col gap-3 rounded-xl border border-l-4 p-4 text-left transition-colors',
                            selectedProjectFilter === proj.id
                                ? 'border-primary border-l-primary bg-primary-subtle/50'
                                : proj.public_status === 'in_review'
                                  ? 'border-stroke border-l-warning-solid bg-surface hover:bg-surface-muted/60'
                                  : proj.public_status === 'reviewed'
                                    ? 'border-stroke border-l-positive-solid bg-surface hover:bg-surface-muted/60'
                                    : 'border-stroke border-l-primary bg-surface hover:bg-surface-muted/60',
                        ]"
                        @click="selectProject(proj.id)"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <span
                                :class="[
                                    'text-sm font-semibold',
                                    selectedProjectFilter === proj.id ? 'text-primary' : 'text-content',
                                ]"
                            >
                                {{ proj.name }}
                            </span>
                            <Badge v-if="proj.public_status === 'in_review'" color="warning">Dalam tinjauan</Badge>
                            <Badge v-else-if="proj.public_status === 'reviewed'" color="info">Ditinjau BPD</Badge>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                class="rounded-full border border-stroke bg-surface-muted px-2.5 py-0.5 text-sm font-medium text-content-secondary tabular-nums"
                            >
                                {{ proj.reports_count }} laporan
                            </span>
                            <span
                                v-if="proj.unread_count > 0"
                                class="rounded-full border border-info-border bg-info-subtle px-2.5 py-0.5 text-sm font-semibold text-info tabular-nums"
                            >
                                {{ proj.unread_count }} baru
                            </span>
                            <span
                                v-if="proj.pending_count > 0"
                                class="rounded-full border border-warning-border bg-warning-subtle px-2.5 py-0.5 text-sm font-semibold text-warning tabular-nums"
                            >
                                {{ proj.pending_count }} menunggu
                            </span>
                        </div>
                    </button>
                </div>
            </section>

            <!-- Empty State bila belum ada laporan warga sama sekali -->
            <div v-if="reports.length === 0" class="rounded-2xl border border-stroke bg-surface p-8">
                <EmptyState
                    title="Belum ada laporan warga yang masuk"
                    description="Laporan Cek Spek yang dikirim warga melalui akun Google akan tampil di halaman kotak warga ini."
                    illustration="search-empty"
                >
                    <Button variant="secondary" href="/bpd">Kembali ke siklus BPD</Button>
                </EmptyState>
            </div>

            <!-- Layout 2 Kolom: KIRI (Detail Laporan, Foto Geotag, Validasi & Balasan BPD) | KANAN (Daftar Pelapor Google) -->
            <div v-else class="grid items-start gap-6 lg:grid-cols-12">
                <!-- KOLOM KIRI (8 Kolom): Detail Laporan Warga -->
                <section
                    v-if="selectedReport"
                    class="flex flex-col overflow-hidden rounded-2xl border border-stroke bg-surface shadow-xs lg:col-span-8"
                    aria-label="Detail laporan dan balasan BPD"
                >
                    <!-- 1. Header Profil Pelapor -->
                    <header
                        class="flex flex-wrap items-center justify-between gap-3 border-b border-stroke bg-surface px-6 py-4"
                    >
                        <div class="flex min-w-0 items-center gap-3.5">
                            <div class="relative shrink-0">
                                <div
                                    class="flex h-11 w-11 items-center justify-center rounded-full border border-stroke bg-primary-subtle text-sm font-bold text-primary"
                                >
                                    {{ selectedReport.reporter_initials }}
                                </div>
                                <span
                                    class="absolute -right-0.5 -bottom-0.5 flex h-4 w-4 items-center justify-center rounded-full border border-stroke bg-surface text-xs font-bold text-primary"
                                    title="Terverifikasi Masuk dengan Google"
                                >
                                    G
                                </span>
                            </div>

                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="truncate text-base font-semibold text-content">
                                        {{ selectedReport.reporter_name }}
                                    </h2>
                                    <span class="text-sm font-medium text-content-tertiary">
                                        {{ selectedReport.reporter_username }}
                                    </span>
                                    <Badge color="neutral">{{ selectedReport.reporter_label }}</Badge>
                                </div>
                                <p class="mt-0.5 truncate text-sm text-content-secondary tabular-nums">
                                    {{ selectedReport.reporter_email }} &middot;
                                    {{
                                        selectedReport.created_at
                                            ? formatDateTime(selectedReport.created_at)
                                            : 'Baru saja'
                                    }}
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <Badge :color="reportKindBadgeColor(selectedReport.kind)">
                                {{ reportType[selectedReport.kind] }}
                            </Badge>
                            <Badge
                                v-if="selectedReport.is_verified"
                                :color="isInvalidVerdict(selectedReport) ? 'negative' : 'positive'"
                            >
                                {{ isInvalidVerdict(selectedReport) ? 'Tidak valid' : 'Valid' }}
                            </Badge>
                            <Badge v-else-if="selectedReport.is_new" color="info">Baru</Badge>
                            <Badge v-else color="warning">Menunggu verifikasi</Badge>
                        </div>
                    </header>

                    <!-- Sub-Header Konteks Proyek di atas Merino bg-surface-subtle -->
                    <div
                        class="flex flex-wrap items-center justify-between gap-2 border-b border-stroke bg-surface-subtle px-6 py-3 text-sm"
                    >
                        <div class="flex flex-wrap items-center gap-2 text-content-secondary">
                            <span class="font-semibold text-content">Proyek: {{ selectedReport.project_name }}</span>
                            <Badge
                                v-if="selectedReport.distance_m !== null"
                                :color="selectedReport.distance_m <= 300 ? 'positive' : 'warning'"
                            >
                                GPS {{ Math.round(selectedReport.distance_m) }} m
                            </Badge>
                        </div>
                        <div class="flex items-center gap-4">
                            <a
                                :href="selectedReport.project_public_url"
                                target="_blank"
                                rel="noopener"
                                class="font-semibold text-primary hover:underline"
                            >
                                Lihat halaman publik
                            </a>
                            <a
                                :href="selectedReport.project_document_url"
                                target="_blank"
                                rel="noopener"
                                class="font-semibold text-primary hover:underline"
                            >
                                Dokumen RAB asli
                            </a>
                        </div>
                    </div>

                    <!-- 2. Isi Laporan Warga & Bukti Foto Ber-geotag (DESIGN.md §6C: foto bukti dengan geotag & stempel tanggal rounded-xl) -->
                    <div class="flex flex-col gap-5 p-6">
                        <div class="flex flex-col items-start gap-2">
                            <span class="text-xs text-content-tertiary tabular-nums">
                                {{ selectedReport.reporter_name }} ({{ selectedReport.reporter_username }}) &middot;
                                {{ selectedReport.created_at ? formatDateTime(selectedReport.created_at) : '' }}
                            </span>

                            <div
                                class="w-full max-w-2xl space-y-4 rounded-xl border border-stroke bg-surface-muted/50 p-4"
                            >
                                <p v-if="selectedReport.note" class="text-base text-content">
                                    {{ selectedReport.note }}
                                </p>
                                <p v-else class="text-sm text-content-secondary">
                                    Mengirim hasil pengecekan spesifikasi fisik dari lokasi proyek.
                                </p>

                                <!-- Chip Perbandingan Hasil Ukur vs RAB -->
                                <div v-if="selectedReport.answers.length > 0" class="space-y-2">
                                    <p class="text-sm font-semibold text-content-secondary">
                                        Perbandingan ukuran fisik lapangan vs RAB:
                                    </p>
                                    <div class="flex flex-wrap gap-2">
                                        <div
                                            v-for="ans in selectedReport.answers"
                                            :key="ans.id"
                                            :class="[
                                                'inline-flex flex-wrap items-center gap-1.5 rounded-lg border px-3 py-1.5 text-sm tabular-nums',
                                                ans.answer === 'mismatch'
                                                    ? 'border-negative-border bg-negative-subtle text-negative'
                                                    : ans.answer === 'match'
                                                      ? 'border-positive-border bg-positive-subtle text-positive'
                                                      : 'border-stroke bg-surface text-content-secondary',
                                            ]"
                                        >
                                            <span class="font-medium">{{ ans.measurement_name }}:</span>
                                            <template v-if="ans.answer === 'mismatch'">
                                                <span class="font-semibold">
                                                    {{
                                                        ans.measured_value !== null
                                                            ? `Ukur ${formatNumber(ans.measured_value)} ${ans.measured_unit ?? ''}`.trim()
                                                            : 'Tidak sesuai'
                                                    }}
                                                </span>
                                                <span v-if="ans.rab_value !== null" class="text-content-secondary">
                                                    &rarr; RAB {{ formatNumber(ans.rab_value) }}
                                                    {{ ans.rab_unit ?? '' }}
                                                </span>
                                            </template>
                                            <template v-else-if="ans.answer === 'match'">
                                                <span class="font-semibold">{{ checkSpec.answers.match }}</span>
                                                <span v-if="ans.rab_value !== null" class="text-content-secondary">
                                                    ({{ formatNumber(ans.rab_value) }} {{ ans.rab_unit ?? '' }})
                                                </span>
                                            </template>
                                            <template v-else>
                                                <span>{{ checkSpec.answers.unmeasurable }}</span>
                                            </template>
                                        </div>
                                    </div>
                                </div>

                                <!-- Foto Bukti Kamera dengan Geotag & Stempel Tanggal (DESIGN.md §6C: rounded-xl) -->
                                <div v-if="selectedReport.photos.length > 0" class="space-y-2">
                                    <p class="text-sm font-semibold text-content-secondary">
                                        Foto bukti lapangan ({{ selectedReport.photos.length }}):
                                    </p>
                                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                        <button
                                            v-for="photo in selectedReport.photos"
                                            :key="photo.id"
                                            type="button"
                                            class="group relative overflow-hidden rounded-xl border border-stroke bg-surface text-left transition-colors hover:border-primary focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                                            @click="openPhotoLightbox(selectedReport, photo)"
                                        >
                                            <div class="aspect-video w-full overflow-hidden bg-surface-muted">
                                                <img
                                                    :src="photo.url"
                                                    :alt="`Foto bukti ${photo.position} dari ${selectedReport.reporter_name}`"
                                                    loading="lazy"
                                                    class="h-full w-full object-cover transition-transform group-hover:scale-105"
                                                />
                                            </div>
                                            <div
                                                class="flex flex-wrap items-center justify-between gap-2 border-t border-stroke bg-surface px-3 py-2 text-xs text-content-secondary tabular-nums"
                                            >
                                                <span class="font-semibold text-content"
                                                    >Foto #{{ photo.position }}</span
                                                >
                                                <span>
                                                    <template v-if="selectedReport.distance_m !== null">
                                                        GPS {{ Math.round(selectedReport.distance_m) }} m &middot;
                                                    </template>
                                                    {{
                                                        selectedReport.created_at
                                                            ? formatDate(selectedReport.created_at)
                                                            : ''
                                                    }}
                                                </span>
                                            </div>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Balasan Catatan Resmi BPD -->
                        <div
                            v-if="selectedReport.bpd_reply || selectedReport.is_verified"
                            class="flex flex-col items-end gap-1.5"
                        >
                            <span class="text-xs text-content-tertiary tabular-nums">
                                BPD {{ village.name }}
                                <template v-if="selectedReport.bpd_replied_at">
                                    &middot; {{ formatDateTime(selectedReport.bpd_replied_at) }}
                                </template>
                            </span>

                            <div
                                class="w-full max-w-xl space-y-2 rounded-xl border border-stroke bg-primary-subtle p-4 text-sm text-content"
                            >
                                <div class="flex flex-wrap items-center gap-2">
                                    <Badge
                                        v-if="selectedReport.is_verified && isInvalidVerdict(selectedReport)"
                                        color="negative"
                                    >
                                        Ditandai tidak valid<template v-if="selectedReport.invalid_reason">
                                            ({{ selectedReport.invalid_reason }})</template
                                        >
                                    </Badge>
                                    <Badge v-else-if="selectedReport.is_verified" color="positive">
                                        Diverifikasi valid oleh BPD
                                    </Badge>
                                    <Badge v-else color="info">Catatan resmi BPD</Badge>
                                </div>

                                <p v-if="selectedReport.bpd_reply" class="text-sm text-content">
                                    {{ selectedReport.bpd_reply }}
                                </p>
                                <p v-else class="text-sm text-content-secondary">
                                    Belum ada catatan tertulis untuk pelapor ini. Anda masih dapat mengirim 1 catatan
                                    balasan di bawah.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- 3. Action Bar Keputusan Verifikasi (Valid / Tidak Valid) -->
                    <div
                        v-if="selectedReport.is_verified"
                        class="flex flex-wrap items-center justify-between gap-3 border-t border-stroke bg-surface-subtle/60 px-6 py-4 text-sm text-content-secondary"
                    >
                        <span>
                            <strong class="font-semibold text-content">Status verifikasi final:</strong>
                            Laporan ditandai
                            <strong>{{ isInvalidVerdict(selectedReport) ? 'tidak valid' : 'valid' }}</strong>
                            <template v-if="selectedReport.bpd_verified_at">
                                pada {{ formatDateTime(selectedReport.bpd_verified_at) }}
                            </template>
                            dan terkunci permanen.
                        </span>
                        <Badge :color="isInvalidVerdict(selectedReport) ? 'negative' : 'positive'">
                            Verifikasi terkunci
                        </Badge>
                    </div>

                    <div v-else class="space-y-3 border-t border-stroke bg-surface-subtle/60 px-6 py-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <span class="text-sm font-semibold text-content">
                                Keputusan verifikasi laporan warga (bersifat final):
                            </span>
                            <div class="flex flex-wrap items-center gap-2">
                                <Button type="button" variant="secondary" size="sm" @click="startValid">
                                    <span>Tandai laporan valid</span>
                                </Button>
                                <Button type="button" variant="danger" size="sm" @click="openInvalidateDialog">
                                    <span>Tandai tidak valid</span>
                                </Button>
                            </div>
                        </div>

                        <!-- Konfirmasi Valid -->
                        <div
                            v-if="confirmingValid"
                            class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-positive-border bg-positive-subtle p-4 text-sm text-positive"
                        >
                            <span>Tetapkan laporan ini valid? Pilihan bersifat final dan tidak dapat diubah.</span>
                            <div class="flex gap-2">
                                <Button
                                    type="button"
                                    variant="secondary"
                                    size="sm"
                                    @click="submitValid(selectedReport.id)"
                                >
                                    <span>Ya, tetapkan valid</span>
                                </Button>
                                <Button type="button" variant="ghost" size="sm" @click="confirmingValid = false">
                                    <span>Batal</span>
                                </Button>
                            </div>
                        </div>
                    </div>

                    <!-- 4. Balas Pesan Pelapor (1x Catatan Resmi BPD per Laporan, satu-satunya tombol Primary di layar saat dialog tertutup) -->
                    <div
                        v-if="hasSentReply"
                        class="flex flex-wrap items-center justify-between gap-3 border-t border-stroke bg-surface-muted/50 px-6 py-4 text-sm text-content-secondary"
                    >
                        <span>
                            <strong class="font-semibold text-content">Catatan balasan terkirim (1/1):</strong> Setiap
                            laporan warga hanya menerima 1 catatan balasan resmi BPD.
                        </span>
                        <Badge color="info">1 / 1 terkirim</Badge>
                    </div>

                    <div v-else class="space-y-4 border-t border-stroke bg-surface px-6 py-5">
                        <div class="space-y-2">
                            <p class="text-sm font-medium text-content-secondary">
                                Template balasan cepat (klik untuk mengisi kolom catatan):
                            </p>
                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                <button
                                    v-for="(tpl, idx) in quickReplyTemplates"
                                    :key="idx"
                                    type="button"
                                    class="flex min-h-11 flex-col items-start gap-0.5 rounded-xl border border-stroke bg-surface-muted/40 p-3 text-left transition-colors hover:border-primary hover:bg-primary-subtle/40"
                                    @click="applyTemplate(tpl.text)"
                                >
                                    <span class="text-sm font-semibold text-content">{{ tpl.title }}</span>
                                    <span class="text-xs text-content-secondary">{{ tpl.subtitle }}</span>
                                </button>
                            </div>
                        </div>

                        <form class="space-y-2" @submit.prevent="submitReply">
                            <div class="flex items-center justify-between text-sm">
                                <label for="bpd-reply-input" class="font-medium text-content">
                                    Kirim catatan balasan BPD (maksimal 1 kali untuk laporan ini)
                                </label>
                                <span class="text-xs text-content-tertiary tabular-nums">0 / 1 catatan</span>
                            </div>
                            <div class="flex flex-col gap-2.5 sm:flex-row sm:items-center">
                                <input
                                    id="bpd-reply-input"
                                    v-model="replyForm.reply"
                                    type="text"
                                    placeholder="Tulis catatan tanggapan resmi BPD untuk pelapor ini..."
                                    :aria-describedby="replyForm.errors.reply ? 'bpd-reply-error' : undefined"
                                    :class="[
                                        'h-11 flex-1 rounded-lg border bg-surface px-3.5 text-base text-content focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary',
                                        replyForm.errors.reply ? 'border-negative-solid' : 'border-stroke-strong',
                                    ]"
                                />
                                <Button
                                    type="submit"
                                    :variant="isDialogOpen ? 'secondary' : 'primary'"
                                    :disabled="replyForm.reply.trim().length < 3"
                                    :loading="replyForm.processing"
                                >
                                    <span>Kirim balasan</span>
                                </Button>
                            </div>
                            <p v-if="replyForm.errors.reply" id="bpd-reply-error" class="text-sm text-negative">
                                {{ replyForm.errors.reply }}
                            </p>
                        </form>
                    </div>
                </section>

                <!-- KOLOM KANAN (4 Kolom): Daftar Semua Laporan & Profil Akun Google Pelapor -->
                <aside
                    class="flex flex-col overflow-hidden rounded-2xl border border-stroke bg-surface shadow-xs lg:col-span-4"
                    aria-label="Daftar pelapor akun Google"
                >
                    <div class="space-y-3 border-b border-stroke p-5">
                        <div class="flex items-center justify-between gap-2">
                            <h2 class="text-base font-semibold text-content">Daftar pelapor warga</h2>
                            <Badge color="neutral">{{ filteredReports.length }} laporan</Badge>
                        </div>

                        <div>
                            <label for="inbox-project-select" class="block text-sm font-medium text-content">
                                Proyek fisik
                            </label>
                            <select
                                id="inbox-project-select"
                                v-model="selectedProjectFilter"
                                class="mt-1 h-11 w-full rounded-lg border border-stroke-strong bg-surface px-3 text-sm text-content focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                            >
                                <option :value="0">Semua proyek ({{ reports.length }})</option>
                                <option v-for="proj in projects" :key="proj.id" :value="proj.id">
                                    {{ proj.name }} ({{ proj.reports_count }})
                                </option>
                            </select>
                        </div>

                        <div>
                            <label for="inbox-reporter-search" class="block text-sm font-medium text-content">
                                Cari pelapor Google
                            </label>
                            <input
                                id="inbox-reporter-search"
                                v-model="searchQuery"
                                type="search"
                                placeholder="Nama Google, @username, atau isi..."
                                class="mt-1 h-11 w-full rounded-lg border border-stroke-strong bg-surface px-3 text-sm text-content focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                            />
                        </div>

                        <div class="flex flex-wrap gap-1.5">
                            <button
                                v-for="tab in [
                                    { key: 'all', label: 'Semua' },
                                    { key: 'new', label: 'Baru' },
                                    { key: 'pending', label: 'Menunggu' },
                                    { key: 'valid', label: 'Valid' },
                                    { key: 'invalid', label: 'Tidak valid' },
                                ] as const"
                                :key="tab.key"
                                type="button"
                                :aria-pressed="statusFilter === tab.key"
                                :class="[
                                    'min-h-11 rounded-full border px-3 py-1.5 text-sm font-semibold transition-colors',
                                    statusFilter === tab.key
                                        ? 'border-primary bg-primary-subtle text-primary'
                                        : 'border-stroke bg-surface-muted text-content-secondary hover:border-stroke-strong hover:text-content',
                                ]"
                                @click="statusFilter = tab.key"
                            >
                                {{ tab.label }}
                            </button>
                        </div>
                    </div>

                    <div v-if="filteredReports.length === 0" class="p-6 text-center text-sm text-content-secondary">
                        Tidak ada laporan warga yang cocok dengan pencarian atau filter.
                    </div>

                    <div v-else class="max-h-[680px] divide-y divide-stroke overflow-y-auto">
                        <button
                            v-for="item in filteredReports"
                            :key="item.id"
                            type="button"
                            :class="[
                                'flex w-full items-start gap-3 border-l-4 p-4 text-left transition-colors',
                                selectedReport?.id === item.id
                                    ? 'border-l-primary bg-primary-subtle/50'
                                    : 'border-l-transparent hover:bg-surface-muted/60',
                            ]"
                            @click="selectReport(item)"
                        >
                            <div class="relative mt-0.5 shrink-0">
                                <div
                                    :class="[
                                        'flex h-10 w-10 items-center justify-center rounded-full border text-xs font-bold',
                                        selectedReport?.id === item.id
                                            ? 'border-primary bg-primary text-content-inverse'
                                            : 'border-stroke bg-surface-muted text-content',
                                    ]"
                                >
                                    {{ item.reporter_initials }}
                                </div>
                                <span
                                    class="absolute -right-0.5 -bottom-0.5 flex h-4 w-4 items-center justify-center rounded-full border border-stroke bg-surface text-[10px] font-bold text-primary"
                                    title="Akun Google"
                                >
                                    G
                                </span>
                            </div>

                            <div class="min-w-0 flex-1 space-y-1">
                                <div class="flex items-baseline justify-between gap-2">
                                    <span
                                        :class="[
                                            'truncate text-sm font-semibold',
                                            selectedReport?.id === item.id ? 'text-primary' : 'text-content',
                                        ]"
                                    >
                                        {{ item.reporter_name }}
                                    </span>
                                    <span class="shrink-0 text-xs text-content-tertiary tabular-nums">
                                        {{ item.created_at ? formatDate(item.created_at) : '' }}
                                    </span>
                                </div>

                                <div class="flex flex-wrap items-center gap-1.5 text-xs text-content-tertiary">
                                    <span class="truncate font-medium text-content-secondary">
                                        {{ item.reporter_username }}
                                    </span>
                                    <span>&middot;</span>
                                    <span>{{ item.reporter_label }}</span>
                                </div>

                                <p class="truncate text-sm font-medium text-content-secondary">
                                    {{ item.project_name }}
                                </p>

                                <div class="flex items-center justify-between gap-2 pt-1">
                                    <p class="truncate text-xs text-content-tertiary">
                                        {{ item.note || `${reportType[item.kind]} (${item.answers.length} ukuran)` }}
                                    </p>

                                    <Badge v-if="item.is_new" color="info">Baru</Badge>
                                    <Badge v-else-if="item.is_verified && isInvalidVerdict(item)" color="negative">
                                        Tidak valid
                                    </Badge>
                                    <Badge v-else-if="item.is_verified" color="positive">Valid</Badge>
                                    <Badge v-else-if="!item.superseded" color="warning">Menunggu</Badge>
                                </div>
                            </div>
                        </button>
                    </div>
                </aside>
            </div>
        </div>

        <!-- Dialog Native Konfirmasi Pembatalan / Tidak Valid Laporan (DESIGN.md §6B: max-w-lg, shadow-xl, footer bg-surface-subtle/50) -->
        <dialog
            ref="invalidateDialogRef"
            class="m-auto w-full max-w-lg overflow-hidden rounded-2xl border border-stroke bg-surface p-0 text-content shadow-xl"
            aria-labelledby="dialog-invalidate-title"
            @close="isDialogOpen = false"
            @click="handleBackdropClick($event, closeInvalidateDialog)"
        >
            <form v-if="selectedReport" @submit.prevent="submitInvalid(selectedReport.id)">
                <div class="flex items-center justify-between border-b border-stroke px-6 py-4">
                    <div class="flex items-center gap-2.5">
                        <h3 id="dialog-invalidate-title" class="text-base font-bold text-content">
                            Tandai laporan tidak valid
                        </h3>
                        <Badge color="negative">Keputusan final</Badge>
                    </div>
                    <button
                        type="button"
                        class="inline-flex h-11 w-11 items-center justify-center rounded-lg text-content-secondary transition-colors hover:bg-surface-muted hover:text-content"
                        aria-label="Tutup"
                        @click="closeInvalidateDialog"
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

                <div class="max-h-[calc(85vh-130px)] space-y-4 overflow-y-auto p-6 text-sm">
                    <p class="text-content-secondary">
                        Pilih atau tulis alasan mengapa laporan dari
                        <strong class="font-semibold text-content">{{ selectedReport.reporter_name }}</strong> tidak
                        dihitung dalam ambang siklus BPD. Status ini tidak dapat dibatalkan.
                    </p>

                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="preset in invalidReasonPresets"
                            :key="preset"
                            type="button"
                            :class="[
                                'min-h-11 rounded-lg border px-3.5 py-2 text-sm font-semibold transition-colors',
                                invalidForm.reason === preset
                                    ? 'border-primary bg-primary-subtle text-primary'
                                    : 'border-stroke bg-surface-muted text-content-secondary hover:text-content',
                            ]"
                            @click="invalidForm.reason = preset"
                        >
                            {{ preset }}
                        </button>
                    </div>

                    <div>
                        <label for="inbox-invalid-reason" class="block text-sm font-medium text-content">
                            Alasan pembatalan laporan
                        </label>
                        <input
                            id="inbox-invalid-reason"
                            v-model="invalidForm.reason"
                            type="text"
                            placeholder="Contoh: Foto tidak memperlihatkan objek proyek..."
                            :aria-describedby="invalidForm.errors.reason ? 'inbox-invalid-error' : undefined"
                            :class="[
                                'mt-1 h-11 w-full rounded-lg border bg-surface px-3 text-base text-content focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary',
                                invalidForm.errors.reason ? 'border-negative-solid' : 'border-stroke-strong',
                            ]"
                        />
                        <p v-if="invalidForm.errors.reason" id="inbox-invalid-error" class="mt-1 text-sm text-negative">
                            {{ invalidForm.errors.reason }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-stroke bg-surface-subtle/50 px-6 py-4">
                    <Button type="button" variant="secondary" @click="closeInvalidateDialog">
                        <span>Batal</span>
                    </Button>
                    <Button type="submit" variant="primary" :loading="invalidForm.processing">
                        <span>Konfirmasi tidak valid</span>
                    </Button>
                </div>
            </form>
        </dialog>

        <!-- Dialog Native Lightbox Foto Bukti Laporan Warga (DESIGN.md §6B: max-w-4xl lightbox foto, shadow-xl) -->
        <dialog
            ref="lightboxDialogRef"
            class="m-auto w-full max-w-4xl overflow-hidden rounded-2xl border border-stroke bg-surface p-0 text-content shadow-xl"
            aria-labelledby="dialog-lightbox-title"
            @close="isDialogOpen = false"
            @click="handleBackdropClick($event, closePhotoLightbox)"
        >
            <div v-if="activeLightboxPhoto">
                <div class="flex items-center justify-between border-b border-stroke px-6 py-4">
                    <div class="flex flex-wrap items-center gap-2.5">
                        <h3 id="dialog-lightbox-title" class="text-base font-bold text-content">
                            {{ activeLightboxPhoto.label }}
                        </h3>
                        <Badge v-if="activeLightboxPhoto.distance_m !== null" color="info">
                            GPS {{ Math.round(activeLightboxPhoto.distance_m) }} m
                        </Badge>
                    </div>
                    <button
                        type="button"
                        class="inline-flex h-11 w-11 items-center justify-center rounded-lg text-content-secondary transition-colors hover:bg-surface-muted hover:text-content"
                        aria-label="Tutup"
                        @click="closePhotoLightbox"
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

                <div class="flex max-h-[calc(85vh-130px)] items-center justify-center overflow-y-auto bg-black p-4">
                    <img
                        :src="activeLightboxPhoto.url"
                        :alt="activeLightboxPhoto.label"
                        class="max-h-[70vh] w-auto max-w-full object-contain"
                    />
                </div>

                <div
                    class="flex flex-wrap items-center justify-between gap-3 border-t border-stroke bg-surface-subtle/50 px-6 py-4"
                >
                    <span class="text-sm text-content-secondary tabular-nums">
                        <template v-if="activeLightboxPhoto.created_at">
                            Waktu unggah: {{ formatDateTime(activeLightboxPhoto.created_at) }}
                        </template>
                    </span>
                    <Button type="button" variant="primary" @click="closePhotoLightbox">
                        <span>Tutup pratinjau</span>
                    </Button>
                </div>
            </div>
        </dialog>
    </AppLayout>
</template>
