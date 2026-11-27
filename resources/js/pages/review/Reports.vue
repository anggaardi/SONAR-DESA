<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate, formatDateTime } from '@/lib/format';
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

const showInvalidateBox = ref(false);
const confirmingValid = ref(false);
const invalidReasonPresets = ['Foto tidak relevan', 'Bukan proyek ini', 'Duplikat'] as const;
const invalidForm = useForm({ reason: '' });

const replyForm = useForm({
    reply: '',
});

const activeLightboxPhoto = ref<{ url: string; label: string } | null>(null);

const quickReplyTemplates = [
    {
        title: 'Terima & Cek Lokasi',
        subtitle: 'Jadwalkan tinjauan fisik bersama Pemdes',
        text: 'Terima kasih atas laporan Cek Spek Anda. BPD telah menerima bukti foto dan menjadwalkan pengecekan fisik di lokasi proyek bersama Pemerintah Desa.',
    },
    {
        title: 'Masuk Tinjauan BPD',
        subtitle: 'Catat sebagai bahan musyawarah siklus aktif',
        text: 'Laporan dan hasil ukur lapangan ini dinyatakan valid serta dimasukkan sebagai bahan tinjauan resmi BPD pada siklus berjalan.',
    },
    {
        title: 'Sesuai Spesifikasi RAB',
        subtitle: 'Konfirmasi ukuran sudah sesuai dokumen',
        text: 'Terima kasih atas partisipasinya menjaga transparansi desa. Hasil pengukuran lapangan telah sesuai dengan spesifikasi teknis pada RAB proyek.',
    },
    {
        title: 'Minta Foto Lebih Jelas',
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
        showInvalidateBox.value = false;
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

function statusBadgeText(status: ProjectOption['public_status']): string {
    if (status === 'in_review') return 'Dalam tinjauan BPD';
    if (status === 'reviewed') return 'Sudah ditinjau';
    return 'Belum ada tinjauan';
}

function statusBadgeColor(status: ProjectOption['public_status']): 'warning' | 'info' | 'neutral' {
    if (status === 'in_review') return 'warning';
    if (status === 'reviewed') return 'info';
    return 'neutral';
}

function startValid(): void {
    showInvalidateBox.value = false;
    confirmingValid.value = true;
}

function startInvalid(): void {
    confirmingValid.value = false;
    showInvalidateBox.value = true;
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
                showInvalidateBox.value = false;
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

function openPhotoLightbox(report: InboxReportItem, photo: ReportPhotoItem): void {
    activeLightboxPhoto.value = {
        url: photo.url,
        label: `${report.reporter_name} (${report.reporter_label}) — Foto ${photo.position}`,
    };
}

function closePhotoLightbox(): void {
    activeLightboxPhoto.value = null;
}

function reportKindBadgeColor(kind: InboxReportItem['kind']): 'negative' | 'positive' | 'neutral' {
    if (kind === 'mismatch') return 'negative';
    if (kind === 'match') return 'positive';
    return 'neutral';
}
</script>

<template>
    <AppLayout>
        <Head :title="`Kotak Masuk Laporan Warga — BPD ${village.name}`" />

        <template #breadcrumb>
            <Link href="/" class="transition-colors hover:text-primary">Beranda</Link>
            <span>/</span>
            <Link href="/bpd" class="transition-colors hover:text-primary">Dashboard BPD</Link>
            <span>/</span>
            <span class="font-medium text-content">Laporan Warga</span>
        </template>

        <template #headerActions>
            <Button variant="secondary" size="sm" :href="`/d/${village.slug}`">
                <span>Lihat Halaman Publik</span>
            </Button>
        </template>

        <template #nav>
            <Link
                href="/bpd"
                class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-content-secondary transition-colors hover:bg-surface-muted hover:text-content focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
            >
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="1.5"
                        d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"
                    />
                </svg>
                <span>Tinjauan &amp; Siklus BPD</span>
            </Link>
            <Link
                href="/bpd#daftar-proyek-bpd"
                class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-content-secondary transition-colors hover:bg-surface-muted hover:text-content focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
            >
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="1.5"
                        d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"
                    />
                </svg>
                <span>Daftar Proyek Desa</span>
            </Link>
            <Link
                href="/bpd/reports"
                class="flex items-center justify-between gap-2 rounded-lg bg-primary-subtle px-3 py-2.5 text-sm font-semibold text-primary transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
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
                            stroke-width="1.5"
                            d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"
                        />
                    </svg>
                    <span>Laporan Warga</span>
                </span>
                <Badge v-if="unreadCount > 0" color="info">{{ unreadCount }}</Badge>
            </Link>
            <Link
                :href="`/d/${village.slug}`"
                class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-content-secondary transition-colors hover:bg-surface-muted hover:text-content focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
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
                    <span class="truncate font-medium text-content">{{ village.name }}</span>
                    <span class="truncate text-content-tertiary">
                        {{ village.regency ? `Kab. ${village.regency}` : 'Badan Permusyawaratan Desa' }}
                    </span>
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

        <div class="flex flex-col gap-5">
            <!-- Header Halaman Kotak Masuk Laporan Warga -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold text-content">Kotak Masuk Laporan Warga</h1>
                    <p class="mt-0.5 text-sm text-content-secondary">
                        Periksa laporan fisik warga di kiri, berikan verifikasi valid/tidak valid beserta catatan
                        balasan BPD, dan pilih pelapor dari daftar akun Google di kanan.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Badge color="neutral">{{ reports.length }} Total Laporan</Badge>
                    <Badge v-if="unreadCount > 0" color="info">{{ unreadCount }} Belum Dibuka</Badge>
                </div>
            </div>

            <!-- Daftar Proyek Desa (tetap tampil di halaman laporan; klik untuk menyaring laporan per proyek) -->
            <section
                v-if="projects.length > 0"
                class="rounded-lg border border-stroke bg-surface"
                aria-labelledby="heading-daftar-proyek-laporan"
            >
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-stroke px-5 py-3.5">
                    <div>
                        <h2 id="heading-daftar-proyek-laporan" class="text-base font-semibold text-content">
                            Daftar Proyek Desa
                        </h2>
                        <p class="mt-0.5 text-xs text-content-secondary">
                            Pilih proyek untuk menyaring laporan warga. Klik lagi untuk menampilkan semua proyek.
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <Badge color="neutral">{{ projects.length }} Proyek</Badge>
                        <Link
                            href="/bpd#daftar-proyek-bpd"
                            class="text-xs font-medium text-primary underline underline-offset-2 hover:text-primary-hover"
                        >
                            Buka ringkasan siklus BPD
                        </Link>
                    </div>
                </div>
                <div class="grid gap-2.5 p-4 sm:grid-cols-2 xl:grid-cols-3">
                    <button
                        v-for="proj in projects"
                        :key="proj.id"
                        type="button"
                        :aria-pressed="selectedProjectFilter === proj.id"
                        :class="[
                            'flex flex-col gap-1.5 rounded-lg border p-3 text-left transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary',
                            selectedProjectFilter === proj.id
                                ? 'border-primary bg-primary-subtle'
                                : 'border-stroke bg-surface hover:bg-surface-subtle',
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
                            <Badge :color="statusBadgeColor(proj.public_status)">
                                {{ statusBadgeText(proj.public_status) }}
                            </Badge>
                        </div>
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-content-secondary">
                            <span>{{ proj.reports_count }} laporan</span>
                            <span v-if="proj.unread_count > 0" class="font-semibold text-primary">
                                {{ proj.unread_count }} baru
                            </span>
                            <span v-if="proj.pending_count > 0" class="text-warning">
                                {{ proj.pending_count }} belum diverifikasi
                            </span>
                        </div>
                    </button>
                </div>
            </section>

            <!-- Empty State bila belum ada laporan warga sama sekali -->
            <div v-if="reports.length === 0" class="rounded-lg border border-stroke bg-surface p-8">
                <EmptyState
                    title="Belum ada laporan warga yang masuk"
                    description="Laporan Cek Spek yang dikirim warga melalui akun Google mereka akan muncul di halaman kotak masuk ini."
                    illustration="search-empty"
                >
                    <Button variant="secondary" href="/bpd">Kembali ke Ringkasan BPD</Button>
                </EmptyState>
            </div>

            <!-- Layout 2 Kolom: KIRI (Detail Laporan, Validasi & Balasan Note BPD) | KANAN (Daftar Pelapor & Profil Google) -->
            <div v-else class="grid items-start gap-5 lg:grid-cols-12">
                <!-- KOLOM KIRI (8 Kolom): Detail Laporan Warga, Valid / Tidak Valid, & Balasan Catatan BPD -->
                <section
                    v-if="selectedReport"
                    class="flex flex-col overflow-hidden rounded-lg border border-stroke bg-surface lg:col-span-8"
                    aria-label="Detail laporan dan balasan BPD"
                >
                    <!-- 1. Top Bar Profil Pelapor & Info Proyek -->
                    <header
                        class="flex flex-wrap items-center justify-between gap-3 border-b border-stroke bg-surface px-5 py-4"
                    >
                        <div class="flex min-w-0 items-center gap-3.5">
                            <!-- Avatar Akun Google + Badge Google -->
                            <div class="relative shrink-0">
                                <div
                                    class="flex h-11 w-11 items-center justify-center rounded-full border border-primary/20 bg-primary-subtle text-sm font-bold text-primary"
                                >
                                    {{ selectedReport.reporter_initials }}
                                </div>
                                <span
                                    class="absolute -right-0.5 -bottom-0.5 flex h-4 w-4 items-center justify-center rounded-full border border-stroke bg-surface text-[10px] font-bold text-primary"
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
                                    <span class="text-xs font-medium text-content-tertiary">
                                        {{ selectedReport.reporter_username }}
                                    </span>
                                    <Badge color="neutral">{{ selectedReport.reporter_label }}</Badge>
                                </div>
                                <p class="mt-0.5 truncate text-xs text-content-secondary">
                                    Akun Google: {{ selectedReport.reporter_email }} &middot; Waktu laporan:
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
                                {{ isInvalidVerdict(selectedReport) ? 'Tidak Valid' : 'Valid' }}
                            </Badge>
                            <Badge v-else-if="selectedReport.is_new" color="info">Baru</Badge>
                            <Badge v-else color="warning">Menunggu verifikasi</Badge>
                        </div>
                    </header>

                    <!-- Sub-Header Konteks Proyek -->
                    <div
                        class="flex flex-wrap items-center justify-between gap-2 border-b border-stroke bg-surface-subtle px-5 py-2.5 text-xs"
                    >
                        <div class="flex flex-wrap items-center gap-2 text-content-secondary">
                            <span class="font-medium text-content">Proyek: {{ selectedReport.project_name }}</span>
                            <span v-if="selectedReport.distance_m !== null">
                                &middot; Jarak GPS: <strong>{{ Math.round(selectedReport.distance_m) }} m</strong> dari
                                titik proyek
                            </span>
                        </div>
                        <div class="flex items-center gap-3">
                            <a
                                :href="selectedReport.project_public_url"
                                target="_blank"
                                rel="noopener"
                                class="font-medium text-primary hover:underline"
                            >
                                Lihat proyek publik
                            </a>
                            <a
                                :href="selectedReport.project_document_url"
                                target="_blank"
                                rel="noopener"
                                class="font-medium text-primary hover:underline"
                            >
                                Dokumen RAB asli
                            </a>
                        </div>
                    </div>

                    <!-- 2. Thread Laporan Warga (Kiri) & Balasan Catatan BPD (Kanan) -->
                    <div class="flex flex-col gap-5 p-5">
                        <!-- Gelembung Pesan Kiri: Isi Laporan Warga -->
                        <div class="flex flex-col items-start gap-1.5">
                            <span class="text-xs text-content-tertiary">
                                {{ selectedReport.reporter_name }} ({{ selectedReport.reporter_username }}) &middot;
                                {{ selectedReport.created_at ? formatDateTime(selectedReport.created_at) : '' }}
                            </span>

                            <div
                                class="w-full max-w-2xl space-y-3 rounded-lg border border-stroke bg-surface-subtle p-4"
                            >
                                <!-- Komentar / Catatan Warga -->
                                <p v-if="selectedReport.note" class="text-sm leading-relaxed text-content">
                                    {{ selectedReport.note }}
                                </p>
                                <p v-else class="text-sm text-content-secondary">
                                    Mengirim hasil pengecekan spesifikasi fisik dari lokasi proyek.
                                </p>

                                <!-- Chip Perbandingan Hasil Ukur vs RAB -->
                                <div v-if="selectedReport.answers.length > 0" class="space-y-1.5">
                                    <p class="text-xs font-semibold text-content-secondary">
                                        Hasil ukur lapangan vs dokumen RAB:
                                    </p>
                                    <div class="flex flex-wrap gap-1.5">
                                        <div
                                            v-for="ans in selectedReport.answers"
                                            :key="ans.id"
                                            :class="[
                                                'inline-flex flex-wrap items-center gap-1 rounded-md border px-2.5 py-1 text-xs tabular-nums',
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
                                                    Ukur {{ ans.measured_value ?? '-' }} {{ ans.measured_unit ?? '' }}
                                                </span>
                                                <span v-if="ans.rab_value !== null" class="text-content-secondary">
                                                    (RAB {{ ans.rab_value }} {{ ans.rab_unit ?? '' }})
                                                </span>
                                            </template>
                                            <template v-else-if="ans.answer === 'match'">
                                                <span class="font-semibold">{{ checkSpec.answers.match }}</span>
                                                <span v-if="ans.rab_value !== null" class="text-content-secondary">
                                                    ({{ ans.rab_value }} {{ ans.rab_unit ?? '' }})
                                                </span>
                                            </template>
                                            <template v-else>
                                                <span>{{ checkSpec.answers.unmeasurable }}</span>
                                            </template>
                                        </div>
                                    </div>
                                </div>

                                <!-- Foto Bukti Kamera Warga -->
                                <div v-if="selectedReport.photos.length > 0" class="space-y-1.5">
                                    <p class="text-xs font-semibold text-content-secondary">
                                        Lampiran foto lokasi ({{ selectedReport.photos.length }}):
                                    </p>
                                    <div class="flex flex-wrap gap-2">
                                        <button
                                            v-for="photo in selectedReport.photos"
                                            :key="photo.id"
                                            type="button"
                                            class="group relative h-20 w-28 overflow-hidden rounded-md border border-stroke bg-surface-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                                            @click="openPhotoLightbox(selectedReport, photo)"
                                        >
                                            <img
                                                :src="photo.url"
                                                :alt="`Foto ${photo.position}`"
                                                loading="lazy"
                                                class="h-full w-full object-cover transition-transform group-hover:scale-105"
                                            />
                                            <span
                                                class="absolute right-1 bottom-1 rounded-sm bg-black/75 px-1.5 py-0.5 text-xs font-medium text-white"
                                            >
                                                Foto {{ photo.position }}
                                            </span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Gelembung Pesan Kanan: Status Verifikasi & Balasan Catatan BPD -->
                        <div
                            v-if="selectedReport.bpd_reply || selectedReport.is_verified"
                            class="flex flex-col items-end gap-1.5"
                        >
                            <span class="text-xs text-content-tertiary">
                                BPD {{ village.name }}
                                <template v-if="selectedReport.bpd_replied_at">
                                    &middot; {{ formatDateTime(selectedReport.bpd_replied_at) }}
                                </template>
                            </span>

                            <div
                                class="w-full max-w-xl space-y-2 rounded-lg border border-primary/25 bg-primary-subtle p-4 text-sm text-content"
                            >
                                <div class="flex flex-wrap items-center gap-2">
                                    <Badge
                                        v-if="selectedReport.is_verified && isInvalidVerdict(selectedReport)"
                                        color="negative"
                                    >
                                        Status: Ditandai Tidak Valid<template v-if="selectedReport.invalid_reason">
                                            ({{ selectedReport.invalid_reason }})</template
                                        >
                                    </Badge>
                                    <Badge v-else-if="selectedReport.is_verified" color="positive">
                                        Status: Diverifikasi Valid oleh BPD
                                    </Badge>
                                    <Badge v-else color="info">Balasan Resmi BPD</Badge>
                                </div>

                                <p v-if="selectedReport.bpd_reply" class="leading-relaxed text-content">
                                    {{ selectedReport.bpd_reply }}
                                </p>
                                <p v-else class="text-xs text-content-secondary">
                                    Belum ada pesan balasan tertulis untuk pelapor ini. Anda masih dapat mengirim 1
                                    pesan balasan di bawah.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- 3. Panel Keputusan Verifikasi (Valid / Tidak Valid — Terkunci setelah dipilih) -->
                    <div
                        v-if="selectedReport.is_verified"
                        class="flex flex-wrap items-center justify-between gap-3 border-t border-stroke bg-surface-subtle px-5 py-3.5 text-xs text-content-secondary"
                    >
                        <span class="max-w-2xl leading-relaxed">
                            <strong class="text-content">Status verifikasi final:</strong>
                            Laporan ini sudah ditandai
                            <strong>{{ isInvalidVerdict(selectedReport) ? 'tidak valid' : 'valid' }}</strong>
                            oleh BPD<template v-if="selectedReport.bpd_verified_at">
                                pada {{ formatDateTime(selectedReport.bpd_verified_at) }}</template
                            >
                            dan statusnya tidak dapat diubah lagi.
                        </span>
                        <Badge :color="isInvalidVerdict(selectedReport) ? 'negative' : 'positive'">
                            Verifikasi Terkunci
                        </Badge>
                    </div>

                    <div v-else class="space-y-3 border-t border-stroke bg-surface-subtle/60 px-5 py-3.5">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="text-xs font-semibold text-content">
                                Keputusan verifikasi laporan warga (pilih salah satu, bersifat final):
                            </span>
                            <div class="flex flex-wrap items-center gap-2">
                                <Button type="button" variant="secondary" size="sm" @click="startValid">
                                    Tandai Laporan Valid
                                </Button>
                                <Button type="button" variant="danger" size="sm" @click="startInvalid">
                                    Tandai Tidak Valid
                                </Button>
                            </div>
                        </div>

                        <!-- Form Alasan Tidak Valid -->
                        <form
                            v-if="showInvalidateBox"
                            class="space-y-2.5 rounded-lg border border-negative-border bg-surface p-3.5"
                            @submit.prevent="submitInvalid(selectedReport.id)"
                        >
                            <label for="inbox-invalid-reason" class="block text-xs font-semibold text-content">
                                Alasan laporan ditandai tidak valid (final, tidak dapat dibatalkan):
                            </label>
                            <div class="flex flex-wrap gap-1.5">
                                <button
                                    v-for="preset in invalidReasonPresets"
                                    :key="preset"
                                    type="button"
                                    :class="[
                                        'rounded-md border px-2.5 py-1 text-xs font-medium transition-colors',
                                        invalidForm.reason === preset
                                            ? 'border-primary bg-primary-subtle text-primary'
                                            : 'border-stroke bg-surface-subtle text-content-secondary hover:bg-surface-muted',
                                    ]"
                                    @click="invalidForm.reason = preset"
                                >
                                    {{ preset }}
                                </button>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <input
                                    id="inbox-invalid-reason"
                                    v-model="invalidForm.reason"
                                    type="text"
                                    placeholder="Tulis alasan singkat (mis. foto tidak relevan / duplikat)..."
                                    class="h-9 min-w-56 flex-1 rounded-md border border-stroke-strong bg-surface px-3 text-xs text-content focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                                />
                                <Button type="submit" variant="danger" size="sm" :loading="invalidForm.processing">
                                    Ya, tandai tidak valid
                                </Button>
                                <Button type="button" variant="ghost" size="sm" @click="showInvalidateBox = false">
                                    Batal
                                </Button>
                            </div>
                            <p v-if="invalidForm.errors.reason" class="text-xs text-negative">
                                {{ invalidForm.errors.reason }}
                            </p>
                        </form>

                        <!-- Konfirmasi Valid -->
                        <div
                            v-if="confirmingValid"
                            class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-positive-border bg-positive-subtle p-3.5 text-xs text-positive"
                        >
                            <span>Tandai laporan ini valid? Pilihan ini final dan tidak dapat diubah.</span>
                            <div class="flex gap-2">
                                <Button
                                    type="button"
                                    variant="secondary"
                                    size="sm"
                                    @click="submitValid(selectedReport.id)"
                                >
                                    Ya, tandai valid
                                </Button>
                                <Button type="button" variant="ghost" size="sm" @click="confirmingValid = false">
                                    Batal
                                </Button>
                            </div>
                        </div>
                    </div>

                    <!-- 4. Balas Pesan Pelapor (1x Balasan Resmi BPD per Laporan — tetap bisa dikirim bila belum membalas) -->
                    <div
                        v-if="hasSentReply"
                        class="flex flex-wrap items-center justify-between gap-3 border-t border-stroke bg-surface-subtle px-5 py-3.5 text-xs text-content-secondary"
                    >
                        <span>
                            <strong class="text-content">Pesan balasan terkirim (1/1):</strong> Setiap laporan warga
                            hanya dapat diberikan 1 pesan balasan resmi BPD.
                        </span>
                        <Badge color="info">1 / 1 Balasan Terkirim</Badge>
                    </div>

                    <div v-else class="space-y-3 border-t border-stroke bg-surface px-5 py-4">
                        <div class="space-y-2">
                            <p class="text-xs font-semibold text-content-secondary">
                                Template balasan cepat (klik untuk mengisi pesan balasan):
                            </p>
                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                <button
                                    v-for="(tpl, idx) in quickReplyTemplates"
                                    :key="idx"
                                    type="button"
                                    class="flex flex-col items-start gap-0.5 rounded-lg border border-stroke bg-surface-subtle/60 p-2.5 text-left transition-colors hover:border-primary/40 hover:bg-primary-subtle/40 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                                    @click="applyTemplate(tpl.text)"
                                >
                                    <span class="text-xs font-semibold text-content">{{ tpl.title }}</span>
                                    <span class="text-xs text-content-secondary">{{ tpl.subtitle }}</span>
                                </button>
                            </div>
                        </div>

                        <form class="space-y-2" @submit.prevent="submitReply">
                            <div class="flex items-center justify-between text-xs text-content-tertiary">
                                <label for="bpd-reply-input" class="font-medium text-content-secondary">
                                    Balas pesan pelapor (hanya dapat dikirim 1 kali untuk laporan ini)
                                </label>
                                <span>0 / 1 balasan</span>
                            </div>
                            <div class="flex items-center gap-2.5">
                                <input
                                    id="bpd-reply-input"
                                    v-model="replyForm.reply"
                                    type="text"
                                    placeholder="Tulis pesan balasan atau catatan tanggapan BPD untuk pelapor ini..."
                                    class="h-11 flex-1 rounded-md border border-stroke-strong bg-surface px-3.5 text-sm text-content focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                                />
                                <Button
                                    type="submit"
                                    variant="primary"
                                    :disabled="replyForm.reply.trim().length < 3"
                                    :loading="replyForm.processing"
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
                                            d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"
                                        />
                                    </svg>
                                    <span>Kirim Balasan</span>
                                </Button>
                            </div>
                            <p v-if="replyForm.errors.reply" class="text-xs text-negative">
                                {{ replyForm.errors.reply }}
                            </p>
                        </form>
                    </div>
                </section>

                <!-- KOLOM KANAN (4 Kolom): Daftar Semua Laporan & Profil Akun Google Pelapor -->
                <aside
                    class="flex flex-col overflow-hidden rounded-lg border border-stroke bg-surface lg:col-span-4"
                    aria-label="Daftar pelapor akun Google"
                >
                    <div class="space-y-3 border-b border-stroke p-4">
                        <div class="flex items-center justify-between gap-2">
                            <h2 class="text-base font-semibold text-content">Semua Laporan Warga</h2>
                            <select
                                v-model="selectedProjectFilter"
                                aria-label="Filter berdasarkan proyek"
                                class="h-8 max-w-44 truncate rounded-md border border-stroke-strong bg-surface px-2 text-xs text-content focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                            >
                                <option :value="0">Semua Proyek ({{ reports.length }})</option>
                                <option v-for="proj in projects" :key="proj.id" :value="proj.id">
                                    {{ proj.name }} ({{ proj.reports_count }})
                                </option>
                            </select>
                        </div>

                        <!-- Pencarian Pelapor / Username Google -->
                        <input
                            v-model="searchQuery"
                            type="search"
                            placeholder="Cari nama Google, @username, atau isi..."
                            aria-label="Cari pelapor"
                            class="h-9 w-full rounded-md border border-stroke-strong bg-surface px-3 text-xs text-content focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                        />

                        <!-- Filter Status Cepat -->
                        <div class="flex flex-wrap gap-1.5">
                            <button
                                v-for="tab in [
                                    { key: 'all', label: 'Semua' },
                                    { key: 'new', label: 'Baru' },
                                    { key: 'pending', label: 'Belum diverifikasi' },
                                    { key: 'valid', label: 'Valid' },
                                    { key: 'invalid', label: 'Tidak valid' },
                                ] as const"
                                :key="tab.key"
                                type="button"
                                :class="[
                                    'rounded-md border px-2.5 py-1 text-xs font-medium transition-colors',
                                    statusFilter === tab.key
                                        ? 'border-primary bg-primary-subtle text-primary'
                                        : 'border-stroke bg-surface-subtle text-content-secondary hover:bg-surface-muted',
                                ]"
                                @click="statusFilter = tab.key"
                            >
                                {{ tab.label }}
                            </button>
                        </div>
                    </div>

                    <div v-if="filteredReports.length === 0" class="p-6 text-center text-xs text-content-secondary">
                        Tidak ada laporan warga yang cocok dengan pencarian atau filter.
                    </div>

                    <div v-else class="max-h-[680px] divide-y divide-stroke overflow-y-auto">
                        <button
                            v-for="item in filteredReports"
                            :key="item.id"
                            type="button"
                            :class="[
                                'flex w-full items-start gap-3 p-3.5 text-left transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary',
                                selectedReport?.id === item.id ? 'bg-primary-subtle' : 'hover:bg-surface-subtle',
                            ]"
                            @click="selectReport(item)"
                        >
                            <!-- Avatar Lingkaran Pelapor + Ikon Google -->
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
                                    class="absolute -right-0.5 -bottom-0.5 flex h-4 w-4 items-center justify-center rounded-full border border-stroke bg-surface text-[9px] font-bold text-primary"
                                    title="Akun Google"
                                >
                                    G
                                </span>
                            </div>

                            <!-- Info Nama Akun Google, Username, Proyek, & Cuplikan Laporan -->
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
                                    <span class="shrink-0 text-xs text-content-tertiary">
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

                                <p class="truncate text-xs font-medium text-content-secondary">
                                    {{ item.project_name }}
                                </p>

                                <div class="flex items-center justify-between gap-2 pt-0.5">
                                    <p class="truncate text-xs text-content-tertiary">
                                        {{ item.note || `${reportType[item.kind]} (${item.answers.length} ukuran)` }}
                                    </p>

                                    <div class="flex shrink-0 items-center gap-1">
                                        <span
                                            v-if="item.is_new"
                                            class="inline-flex h-5 items-center rounded-full bg-primary px-2 text-xs font-semibold text-content-inverse"
                                        >
                                            baru
                                        </span>
                                        <span
                                            v-else-if="item.is_verified && isInvalidVerdict(item)"
                                            class="inline-flex h-5 items-center rounded-full border border-negative-border bg-negative-subtle px-2 text-xs font-medium text-negative"
                                        >
                                            tidak valid
                                        </span>
                                        <span
                                            v-else-if="item.is_verified"
                                            class="inline-flex h-5 items-center rounded-full border border-positive-border bg-positive-subtle px-2 text-xs font-medium text-positive"
                                        >
                                            valid
                                        </span>
                                        <span
                                            v-else-if="!item.superseded"
                                            class="inline-flex h-5 items-center rounded-full border border-warning-border bg-warning-subtle px-2 text-xs font-medium text-warning"
                                        >
                                            menunggu
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </button>
                    </div>
                </aside>
            </div>
        </div>

        <!-- Lightbox Foto Bukti Laporan Warga -->
        <div
            v-if="activeLightboxPhoto"
            class="fixed inset-0 z-50 flex items-center justify-center bg-content/50 p-4"
            role="dialog"
            aria-modal="true"
            :aria-label="activeLightboxPhoto.label"
            @click.self="closePhotoLightbox"
        >
            <div class="w-full max-w-2xl overflow-hidden rounded-lg border border-stroke bg-surface shadow-sm">
                <div class="flex items-center justify-between border-b border-stroke px-5 py-3.5">
                    <h3 class="text-sm font-semibold text-content">{{ activeLightboxPhoto.label }}</h3>
                    <Button type="button" variant="secondary" size="sm" @click="closePhotoLightbox">Tutup</Button>
                </div>
                <div class="flex max-h-[75vh] items-center justify-center bg-black p-2">
                    <img
                        :src="activeLightboxPhoto.url"
                        :alt="activeLightboxPhoto.label"
                        class="max-h-[70vh] w-auto max-w-full object-contain"
                    />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
