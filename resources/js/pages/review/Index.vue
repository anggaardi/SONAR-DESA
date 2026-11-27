<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import Alert from '@/components/ui/Alert.vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import BalaiIllustration from '@/components/ui/illustrations/BalaiIllustration.vue';
import bpdEmpty from '@/components/ui/illustrations/bpd-empty.svg';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate, formatDateTime, formatRupiah } from '@/lib/format';
import { checkSpec, reportType, reviewOutcome, type ReviewOutcome } from '@/lib/labels';

interface ProjectRow {
    id: number;
    slug: string;
    name: string;
    public_status: 'none' | 'in_review' | 'reviewed';
    in_review_since: string | null;
    above_standard_count: number;
    valid_mismatch_count: number;
    match_count: number;
    unread_count: number;
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

interface ReportItem {
    id: number;
    reporter_label: string;
    kind: 'mismatch' | 'match' | 'unmeasurable';
    distance_m: number | null;
    note: string | null;
    superseded: boolean;
    invalid_reason: string | null;
    bpd_verdict?: 'valid' | 'invalid' | null;
    is_verified?: boolean;
    bpd_verified_at?: string | null;
    bpd_reply?: string | null;
    bpd_replied_at?: string | null;
    is_new: boolean;
    created_at: string | null;
    answers: ReportAnswerItem[];
    photos: ReportPhotoItem[];
}

interface AboveStandardItem {
    id: number;
    name: string;
    unit_price_rp: number | null;
    over_pct: number | null;
    explanation: string | null;
}

interface ProjectChangeRow {
    id: number;
    item_label: string;
    field: string;
    old_value: string;
    new_value: string;
    reason: string | null;
    changed_at: string;
}

interface FollowUpNote {
    id: number;
    note: string;
    created_at: string | null;
}

interface ClosedCycle {
    id: number;
    result: ReviewOutcome | null;
    note: string | null;
    closed_at: string | null;
    notes: FollowUpNote[];
}

interface SelectedProject {
    id: number;
    slug: string;
    name: string;
    public_status: 'none' | 'in_review' | 'reviewed';
    in_review_since: string | null;
    valid_mismatch_count?: number;
    public_url: string;
    document_url: string;
    above_standard_items?: AboveStandardItem[];
    changes?: ProjectChangeRow[];
    reports: ReportItem[];
    closed_cycles: ClosedCycle[];
}

type ProjectFilterKey = 'all' | 'in_review' | 'unread' | 'above_standard' | 'reviewed';
type ReportFilterKey = 'all' | 'mismatch' | 'match' | 'valid_only';

const props = defineProps<{
    village: { name: string; slug: string; regency?: string };
    projects: ProjectRow[];
    selectedProject: SelectedProject | null;
    threshold: number;
}>();

const projectSearch = ref('');
const projectFilter = ref<ProjectFilterKey>('all');
const reportFilter = ref<ReportFilterKey>('all');

const invalidReasonPresets = ['Foto tidak relevan', 'Bukan proyek ini', 'Duplikat'] as const;
const expandedReportId = ref<number | null>(props.selectedProject?.reports[0]?.id ?? null);
const invalidatingReportId = ref<number | null>(null);
const invalidForm = useForm({ reason: '' });
const replyingReportId = ref<number | null>(null);
const reportReplyForm = useForm({ reply: '' });

const activeLightboxPhoto = ref<{ url: string; label: string } | null>(null);

watch(
    () => props.selectedProject?.id,
    () => {
        reportFilter.value = 'all';
        expandedReportId.value = props.selectedProject?.reports[0]?.id ?? null;
        invalidatingReportId.value = null;
        replyingReportId.value = null;
    },
);

const filteredProjects = computed(() => {
    const query = projectSearch.value.trim().toLowerCase();

    return props.projects.filter((p) => {
        if (query !== '' && !p.name.toLowerCase().includes(query)) {
            return false;
        }
        if (projectFilter.value === 'in_review') {
            return p.public_status === 'in_review';
        }
        if (projectFilter.value === 'unread') {
            return p.unread_count > 0;
        }
        if (projectFilter.value === 'above_standard') {
            return p.above_standard_count > 0;
        }
        if (projectFilter.value === 'reviewed') {
            return p.public_status === 'reviewed';
        }
        return true;
    });
});

const projectFilterTabs = computed(() => [
    { key: 'all' as const, label: 'Semua', count: props.projects.length },
    {
        key: 'in_review' as const,
        label: 'Dalam tinjauan',
        count: props.projects.filter((p) => p.public_status === 'in_review').length,
    },
    {
        key: 'unread' as const,
        label: 'Laporan baru',
        count: props.projects.filter((p) => p.unread_count > 0).length,
    },
    {
        key: 'above_standard' as const,
        label: 'Di atas standar',
        count: props.projects.filter((p) => p.above_standard_count > 0).length,
    },
    {
        key: 'reviewed' as const,
        label: 'Sudah ditinjau',
        count: props.projects.filter((p) => p.public_status === 'reviewed').length,
    },
]);

const filteredReports = computed(() => {
    const list = props.selectedProject?.reports ?? [];
    return list.filter((r) => {
        if (reportFilter.value === 'mismatch') {
            return r.kind === 'mismatch';
        }
        if (reportFilter.value === 'match') {
            return r.kind === 'match';
        }
        if (reportFilter.value === 'valid_only') {
            return r.invalid_reason === null && !r.superseded;
        }
        return true;
    });
});

const selectedUnreadCount = computed(() => {
    const list = props.selectedProject?.reports ?? [];
    return list.filter((r) => r.is_new && !r.superseded).length;
});

const selectedValidMismatchCount = computed(() => {
    if (!props.selectedProject) return 0;
    if (typeof props.selectedProject.valid_mismatch_count === 'number') {
        return props.selectedProject.valid_mismatch_count;
    }
    const row = props.projects.find((p) => p.id === props.selectedProject?.id);
    return row?.valid_mismatch_count ?? 0;
});

const thresholdProgressPct = computed(() => {
    if (props.threshold <= 0) return 100;
    return Math.min(100, Math.round((selectedValidMismatchCount.value / props.threshold) * 100));
});

const thresholdSteps = computed(() => {
    const count = Math.max(1, props.threshold);
    return Array.from({ length: count }, (_, idx) => idx + 1);
});

function toggleReport(reportId: number): void {
    expandedReportId.value = expandedReportId.value === reportId ? null : reportId;
    if (invalidatingReportId.value !== null && invalidatingReportId.value !== reportId) {
        invalidatingReportId.value = null;
    }
}

function markAllOpened(): void {
    if (!props.selectedProject) return;
    router.post(`/bpd/projects/${props.selectedProject.id}/reports/open-all`, {}, { preserveScroll: true });
}

const outcomeForm = useForm<{ result: ReviewOutcome; note: string }>({
    result: 'followed_up',
    note: '',
});

const activeNoteCycleId = ref<number | null>(null);
const followUpForm = useForm({ note: '' });

const stats = computed(() => {
    const totalProjects = props.projects.length;
    const inReviewCount = props.projects.filter((p) => p.public_status === 'in_review').length;
    const reviewedCount = props.projects.filter((p) => p.public_status === 'reviewed').length;
    const unreadReportsCount = props.projects.reduce((sum, p) => sum + p.unread_count, 0);
    const totalMismatchCount = props.projects.reduce((sum, p) => sum + p.valid_mismatch_count, 0);
    const totalMatchCount = props.projects.reduce((sum, p) => sum + p.match_count, 0);

    return {
        totalProjects,
        inReviewCount,
        reviewedCount,
        unreadReportsCount,
        totalMismatchCount,
        totalMatchCount,
    };
});

function selectProject(id: number): void {
    router.get('/bpd', { project: id }, { preserveScroll: true });
}

function markOpened(reportId: number): void {
    router.post(`/bpd/reports/${reportId}/open`, {}, { preserveScroll: true });
}

function openPhotoLightbox(report: ReportItem, photo: ReportPhotoItem): void {
    activeLightboxPhoto.value = {
        url: photo.url,
        label: `${report.reporter_label} — Foto ${photo.position}`,
    };
    if (report.is_new) {
        markOpened(report.id);
    }
}

function closePhotoLightbox(): void {
    activeLightboxPhoto.value = null;
}

function submitInvalid(reportId: number): void {
    invalidForm.post(`/bpd/reports/${reportId}/invalidate`, {
        preserveScroll: true,
        onSuccess: () => {
            invalidForm.reset();
            invalidatingReportId.value = null;
        },
    });
}

function validateReport(reportId: number): void {
    invalidatingReportId.value = null;
    router.post(`/bpd/reports/${reportId}/validate`, {}, { preserveScroll: true });
}

function submitReportReply(reportId: number): void {
    reportReplyForm.post(`/bpd/reports/${reportId}/reply`, {
        preserveScroll: true,
        onSuccess: () => {
            reportReplyForm.reset();
            replyingReportId.value = null;
        },
    });
}

function submitOutcome(): void {
    if (!props.selectedProject) return;
    outcomeForm.post(`/bpd/projects/${props.selectedProject.id}/outcome`, {
        preserveScroll: true,
        onSuccess: () => outcomeForm.reset(),
    });
}

function submitFollowUp(cycleId: number): void {
    followUpForm.post(`/bpd/cycles/${cycleId}/notes`, {
        preserveScroll: true,
        onSuccess: () => {
            followUpForm.reset();
            activeNoteCycleId.value = null;
        },
    });
}

function statusBadgeText(status: ProjectRow['public_status']): string {
    if (status === 'in_review') return 'Dalam tinjauan BPD';
    if (status === 'reviewed') return 'Sudah ditinjau';
    return 'Belum ada tinjauan';
}

function statusBadgeColor(status: ProjectRow['public_status']): 'warning' | 'info' | 'neutral' {
    if (status === 'in_review') return 'warning';
    if (status === 'reviewed') return 'info';
    return 'neutral';
}

function reportKindBadgeColor(kind: ReportItem['kind']): 'negative' | 'positive' | 'neutral' {
    if (kind === 'mismatch') return 'negative';
    if (kind === 'match') return 'positive';
    return 'neutral';
}

function reporterShortCode(label: string): string {
    const parts = label.trim().split(/\s+/);
    if (parts.length >= 2) {
        return `${parts[0]?.[0] ?? 'P'}${parts[parts.length - 1]?.[0] ?? ''}`.toUpperCase();
    }
    return label.slice(0, 2).toUpperCase();
}
</script>

<template>
    <AppLayout>
        <Head :title="`Dashboard BPD — ${village.name}`" />

        <template #breadcrumb>
            <Link href="/" class="transition-colors hover:text-primary">Beranda</Link>
            <span>/</span>
            <span class="font-medium text-content">Dashboard BPD {{ village.name }}</span>
        </template>

        <template #headerActions>

            <Button variant="secondary" size="sm" :href="`/d/${village.slug}`">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
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
            </Button>
        </template>

        <template #nav>
            <Link
                href="/bpd"
                class="flex items-center gap-3 rounded-lg bg-primary-subtle px-3 py-2.5 text-sm font-semibold text-primary transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
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
                href="/bpd/reports"
                class="flex items-center justify-between gap-2 rounded-lg px-3 py-2.5 text-sm font-medium text-content-secondary transition-colors hover:bg-surface-muted hover:text-content focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
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
                <Badge v-if="stats.unreadReportsCount > 0" color="info">{{ stats.unreadReportsCount }}</Badge>
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

        <div class="flex flex-col gap-6">
            <!-- Hero Banner Pengawasan BPD (Hidup, Visual, & Kontekstual) -->
            <section
                class="relative overflow-hidden rounded-2xl border border-info-border/60 bg-primary-subtle p-5 shadow-xs sm:p-6"
                aria-label="Header Dashboard Pengawasan BPD"
            >
                <div class="flex flex-col justify-between gap-5 lg:flex-row lg:items-center">
                    <div class="min-w-0 flex-1 space-y-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                class="inline-flex items-center gap-2 rounded-full border border-info-border bg-surface px-3 py-1 text-xs font-semibold text-primary shadow-2xs"
                            >
                                <span
                                    :class="[
                                        'h-2 w-2 rounded-full',
                                        stats.inReviewCount > 0
                                            ? 'animate-pulse bg-warning-solid'
                                            : 'bg-positive-solid',
                                    ]"
                                    aria-hidden="true"
                                />
                                <span>Badan Permusyawaratan Desa (BPD)</span>
                            </span>
                            <span
                                v-if="village.regency"
                                class="inline-flex items-center gap-1 rounded-full bg-surface/80 px-2.5 py-1 text-xs font-medium text-content-secondary"
                            >
                                <svg
                                    class="h-3.5 w-3.5 text-primary"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    aria-hidden="true"
                                >
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
                                <span>Kabupaten {{ village.regency }}</span>
                            </span>
                        </div>

                        <div>
                            <h1 class="text-2xl font-bold tracking-tight text-content sm:text-3xl">
                                Dashboard BPD {{ village.name }}
                            </h1>
                            <p class="mt-1.5 max-w-2xl text-sm leading-relaxed text-content-secondary">
                                Pantau pembangunan fisik desa per siklus, verifikasi bukti foto dan hasil ukur lapangan
                                dari warga, serta terbitkan keputusan resmi musyawarah BPD ke papan informasi publik.
                            </p>
                        </div>

                        <!-- Quick Status Chips & Mobile Actions -->
                        <div class="flex flex-wrap items-center gap-2 pt-0.5">
                            <Link
                                href="/bpd/reports"
                                class="inline-flex items-center gap-2 rounded-lg bg-primary px-3.5 py-2 text-xs font-semibold text-content-inverse shadow-2xs transition-colors hover:bg-primary-hover"
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
                                        d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"
                                    />
                                </svg>
                                <span>Buka Kotak Masuk Warga</span>
                                <span
                                    v-if="stats.unreadReportsCount > 0"
                                    class="rounded-full bg-surface px-1.5 py-0.5 text-[11px] leading-none font-bold text-primary tabular-nums"
                                >
                                    {{ stats.unreadReportsCount }} baru
                                </span>
                            </Link>

                            <Link
                                :href="`/d/${village.slug}`"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-stroke-strong/30 bg-surface px-3 py-2 text-xs font-semibold text-content transition-colors hover:bg-surface-muted"
                            >
                                <span>Papan Publik Desa</span>
                                <span aria-hidden="true">&rarr;</span>
                            </Link>

                            <span
                                class="inline-flex items-center gap-1.5 rounded-lg bg-surface/90 px-2.5 py-1.5 text-xs text-content-secondary"
                            >
                                <span class="font-semibold text-positive tabular-nums">
                                    {{ stats.totalMatchCount }} sesuai
                                </span>
                                <span class="text-stroke-strong">&middot;</span>
                                <span class="font-semibold text-negative tabular-nums">
                                    {{ stats.totalMismatchCount }} selisih valid
                                </span>
                            </span>
                        </div>
                    </div>

                    <!-- Spot Illustration Area -->
                    <div class="hidden shrink-0 items-center gap-4 sm:flex">
                        <div
                            class="flex flex-col items-end justify-center rounded-xl border border-info-border/50 bg-surface/90 px-3.5 py-2.5 text-right shadow-2xs"
                        >
                            <span class="text-[11px] font-semibold tracking-wider text-content-tertiary uppercase">
                                Ambang Tinjauan
                            </span>
                            <span class="mt-0.5 text-base font-bold text-primary tabular-nums">
                                &ge; {{ threshold }} Laporan Selisih
                            </span>
                            <span class="text-[11px] text-content-secondary">Warga berbeda di siklus aktif</span>
                        </div>
                        <div class="rounded-2xl bg-surface/80 p-2 shadow-2xs">
                            <BalaiIllustration :size="96" />
                        </div>
                    </div>
                </div>
            </section>

            <!-- Alerts Status Siklus & Laporan Baru (IDDS Alert) -->
            <div v-if="stats.inReviewCount > 0 || stats.unreadReportsCount > 0" class="flex flex-col gap-3">
                <Alert
                    v-if="stats.inReviewCount > 0"
                    color="warning"
                    title="Proyek memerlukan keputusan hasil tinjauan BPD"
                >
                    Terdapat {{ stats.inReviewCount }} proyek yang telah mencapai ambang {{ threshold }} laporan selisih
                    warga berbeda dan berstatus Dalam tinjauan BPD. Pilih proyek terkait untuk memeriksa bukti dan
                    menyimpan hasil tinjauan resmi.
                </Alert>
                <Alert
                    v-if="stats.unreadReportsCount > 0"
                    color="info"
                    title="Laporan verifikasi fisik baru dari warga"
                >
                    Ada {{ stats.unreadReportsCount }} laporan Cek Spek warga di siklus berjalan yang belum dibuka.
                    Periksa hasil ukur dan foto lokasi yang dikirim warga.
                </Alert>
            </div>

            <!-- Ringkasan Statistik KPI Interaktif (Dengan Ikon Berwarna & Filter Cepat) -->
            <section aria-labelledby="heading-ringkasan-bpd">
                <h2 id="heading-ringkasan-bpd" class="sr-only">Ringkasan Pengawasan BPD</h2>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <!-- KPI 1: Proyek Terbit -->
                    <button
                        type="button"
                        :class="[
                            'group flex flex-col justify-between rounded-2xl border p-4.5 text-left shadow-xs transition-all',
                            projectFilter === 'all'
                                ? 'border-primary bg-surface ring-2 ring-primary/15'
                                : 'border-stroke bg-surface hover:border-primary/40 hover:bg-surface-subtle',
                        ]"
                        @click="projectFilter = 'all'"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary-subtle text-primary"
                                aria-hidden="true"
                            >
                                <svg
                                    class="h-6 w-6"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"
                                    />
                                </svg>
                            </div>
                            <Badge color="neutral">Semua Proyek</Badge>
                        </div>
                        <div class="mt-3.5">
                            <p class="text-3xl font-bold text-content tabular-nums">{{ stats.totalProjects }}</p>
                            <p class="mt-0.5 text-sm font-semibold text-content">Proyek Terbit</p>
                            <p class="mt-0.5 text-xs text-content-tertiary">Dipantau aktif di {{ village.name }}</p>
                        </div>
                    </button>

                    <!-- KPI 2: Dalam Tinjauan BPD -->
                    <button
                        type="button"
                        :class="[
                            'group flex flex-col justify-between rounded-2xl border p-4.5 text-left shadow-xs transition-all',
                            projectFilter === 'in_review'
                                ? 'border-warning-solid bg-warning-subtle/60 ring-2 ring-warning-border'
                                : stats.inReviewCount > 0
                                  ? 'border-warning-border bg-warning-subtle/35 hover:bg-warning-subtle/60'
                                  : 'border-stroke bg-surface hover:border-primary/40 hover:bg-surface-subtle',
                        ]"
                        @click="projectFilter = projectFilter === 'in_review' ? 'all' : 'in_review'"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-warning-subtle text-warning"
                                aria-hidden="true"
                            >
                                <svg
                                    class="h-6 w-6"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                                    />
                                </svg>
                            </div>
                            <Badge :color="stats.inReviewCount > 0 ? 'warning' : 'neutral'">
                                {{ stats.inReviewCount > 0 ? 'Perlu Keputusan' : 'Aman' }}
                            </Badge>
                        </div>
                        <div class="mt-3.5">
                            <p
                                :class="[
                                    'text-3xl font-bold tabular-nums',
                                    stats.inReviewCount > 0 ? 'text-warning' : 'text-content',
                                ]"
                            >
                                {{ stats.inReviewCount }}
                            </p>
                            <p class="mt-0.5 text-sm font-semibold text-content">Dalam Tinjauan BPD</p>
                            <p class="mt-0.5 text-xs text-content-tertiary">
                                Mencapai &ge; {{ threshold }} laporan selisih
                            </p>
                        </div>
                    </button>

                    <!-- KPI 3: Laporan Belum Dibuka -->
                    <button
                        type="button"
                        :class="[
                            'group flex flex-col justify-between rounded-2xl border p-4.5 text-left shadow-xs transition-all',
                            projectFilter === 'unread'
                                ? 'border-primary bg-primary-subtle/60 ring-2 ring-primary/20'
                                : stats.unreadReportsCount > 0
                                  ? 'border-info-border bg-info-subtle/35 hover:bg-info-subtle/60'
                                  : 'border-stroke bg-surface hover:border-primary/40 hover:bg-surface-subtle',
                        ]"
                        @click="projectFilter = projectFilter === 'unread' ? 'all' : 'unread'"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-info-subtle text-primary"
                                aria-hidden="true"
                            >
                                <svg
                                    class="h-6 w-6"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
                                    />
                                </svg>
                            </div>
                            <Badge :color="stats.unreadReportsCount > 0 ? 'info' : 'neutral'">
                                {{ stats.unreadReportsCount > 0 ? 'Laporan Baru' : 'Terbaca' }}
                            </Badge>
                        </div>
                        <div class="mt-3.5">
                            <p
                                :class="[
                                    'text-3xl font-bold tabular-nums',
                                    stats.unreadReportsCount > 0 ? 'text-primary' : 'text-content',
                                ]"
                            >
                                {{ stats.unreadReportsCount }}
                            </p>
                            <p class="mt-0.5 text-sm font-semibold text-content">Laporan Belum Dibuka</p>
                            <p class="mt-0.5 text-xs text-content-tertiary">Siklus berjalan aktif</p>
                        </div>
                    </button>

                    <!-- KPI 4: Sudah Ditinjau -->
                    <button
                        type="button"
                        :class="[
                            'group flex flex-col justify-between rounded-2xl border p-4.5 text-left shadow-xs transition-all',
                            projectFilter === 'reviewed'
                                ? 'border-positive-solid bg-positive-subtle/50 ring-2 ring-positive-border'
                                : 'border-stroke bg-surface hover:border-primary/40 hover:bg-surface-subtle',
                        ]"
                        @click="projectFilter = projectFilter === 'reviewed' ? 'all' : 'reviewed'"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-positive-subtle text-positive"
                                aria-hidden="true"
                            >
                                <svg
                                    class="h-6 w-6"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"
                                    />
                                </svg>
                            </div>
                            <Badge color="positive">Tampil Publik</Badge>
                        </div>
                        <div class="mt-3.5">
                            <p class="text-3xl font-bold text-positive tabular-nums">
                                {{ stats.reviewedCount }}
                            </p>
                            <p class="mt-0.5 text-sm font-semibold text-content">Sudah Ditinjau</p>
                            <p class="mt-0.5 text-xs text-content-tertiary">Hasil tinjauan resmi terbit</p>
                        </div>
                    </button>
                </div>
            </section>

            <!-- Konten Utama: Daftar Proyek (Kiri) & Panel Detail Tinjauan (Kanan) -->
            <div id="daftar-proyek-bpd" class="grid gap-6 lg:grid-cols-12">
                <!-- Daftar Proyek -->
                <section class="flex flex-col gap-4 lg:col-span-7" aria-labelledby="heading-daftar-proyek">
                    <div class="overflow-hidden rounded-2xl border border-stroke bg-surface shadow-xs">
                        <!-- Header Daftar Proyek -->
                        <div class="space-y-3.5 border-b border-stroke bg-surface-subtle/60 px-5 py-4">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary-subtle text-primary"
                                        aria-hidden="true"
                                    >
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="1.8"
                                                d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"
                                            />
                                        </svg>
                                    </div>
                                    <div>
                                        <h2 id="heading-daftar-proyek" class="text-base font-semibold text-content">
                                            Daftar Proyek Fisik Desa
                                        </h2>
                                        <p class="text-xs text-content-secondary">
                                            Klik proyek untuk membuka laporan warga, rincian RAB, dan keputusan siklus.
                                        </p>
                                    </div>
                                </div>
                                <Badge color="neutral">
                                    {{ filteredProjects.length }} / {{ projects.length }} Proyek
                                </Badge>
                            </div>

                            <!-- Pencarian & Filter Cepat Daftar Proyek -->
                            <div v-if="projects.length > 0" class="space-y-2.5">
                                <div class="relative">
                                    <svg
                                        class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-content-tertiary"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                        aria-hidden="true"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                                        />
                                    </svg>
                                    <input
                                        v-model="projectSearch"
                                        type="search"
                                        placeholder="Cari nama proyek fisik desa..."
                                        aria-label="Cari nama proyek"
                                        class="h-9 w-full rounded-lg border border-stroke-strong/50 bg-surface pr-3 pl-9 text-sm text-content focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                                    />
                                </div>
                                <div class="flex flex-wrap gap-1.5">
                                    <button
                                        v-for="tab in projectFilterTabs"
                                        :key="tab.key"
                                        type="button"
                                        :class="[
                                            'inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1 text-xs font-medium transition-colors',
                                            projectFilter === tab.key
                                                ? 'border-primary bg-primary-subtle font-semibold text-primary'
                                                : 'border-stroke bg-surface text-content-secondary hover:bg-surface-muted hover:text-content',
                                        ]"
                                        @click="projectFilter = tab.key"
                                    >
                                        <span>{{ tab.label }}</span>
                                        <span
                                            :class="[
                                                'py-0.2 rounded-full px-1.5 text-[10px] font-bold tabular-nums',
                                                projectFilter === tab.key
                                                    ? 'bg-primary text-content-inverse'
                                                    : 'bg-surface-muted text-content-secondary',
                                            ]"
                                        >
                                            {{ tab.count }}
                                        </span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div v-if="projects.length === 0" class="px-4 py-6">
                            <EmptyState
                                title="Belum ada proyek terbit di desa ini"
                                description="Proyek yang telah diterbitkan oleh Pemerintah Desa akan otomatis muncul di sini untuk dipantau oleh BPD."
                                illustration="plang-empty"
                            />
                        </div>

                        <div
                            v-else-if="filteredProjects.length === 0"
                            class="px-4 py-10 text-center text-sm text-content-secondary"
                        >
                            Tidak ada proyek yang cocok dengan filter atau kata kunci pencarian.
                        </div>

                        <!-- Daftar Proyek Berbentuk Kartu Baris Interaktif -->
                        <div v-else class="divide-y divide-stroke">
                            <article
                                v-for="p in filteredProjects"
                                :key="p.id"
                                :class="[
                                    'group relative cursor-pointer border-l-4 p-4 transition-all sm:px-5',
                                    selectedProject?.id === p.id
                                        ? 'border-l-primary bg-primary-subtle/50'
                                        : p.public_status === 'in_review'
                                          ? 'border-l-warning-solid bg-warning-subtle/20 hover:bg-warning-subtle/40'
                                          : 'border-l-transparent hover:bg-surface-subtle',
                                ]"
                                @click="selectProject(p.id)"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex min-w-0 items-start gap-3">
                                        <!-- Status Icon Avatar -->
                                        <div
                                            :class="[
                                                'mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl',
                                                p.public_status === 'in_review'
                                                    ? 'bg-warning-subtle text-warning'
                                                    : p.public_status === 'reviewed'
                                                      ? 'bg-positive-subtle text-positive'
                                                      : 'bg-primary-subtle text-primary',
                                            ]"
                                            aria-hidden="true"
                                        >
                                            <svg
                                                v-if="p.public_status === 'in_review'"
                                                class="h-5 w-5"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke="currentColor"
                                                stroke-width="2"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                                                />
                                            </svg>
                                            <svg
                                                v-else-if="p.public_status === 'reviewed'"
                                                class="h-5 w-5"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke="currentColor"
                                                stroke-width="2"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                                                />
                                            </svg>
                                            <svg
                                                v-else
                                                class="h-5 w-5"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke="currentColor"
                                                stroke-width="2"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"
                                                />
                                            </svg>
                                        </div>

                                        <div class="min-w-0 flex-1">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <h3
                                                    :class="[
                                                        'text-sm leading-snug font-semibold sm:text-base',
                                                        selectedProject?.id === p.id
                                                            ? 'text-primary'
                                                            : 'text-content group-hover:text-primary',
                                                    ]"
                                                >
                                                    {{ p.name }}
                                                </h3>
                                                <Badge v-if="p.unread_count > 0" color="info">
                                                    {{ p.unread_count }} baru
                                                </Badge>
                                            </div>

                                            <div class="mt-1.5 flex flex-wrap items-center gap-2">
                                                <Badge :color="statusBadgeColor(p.public_status)">
                                                    {{ statusBadgeText(p.public_status) }}
                                                </Badge>
                                                <span
                                                    v-if="p.in_review_since"
                                                    class="text-xs text-content-tertiary tabular-nums"
                                                >
                                                    sejak {{ formatDate(p.in_review_since) }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Right Active Indicator -->
                                    <div class="flex shrink-0 items-center gap-1.5">
                                        <span
                                            v-if="selectedProject?.id === p.id"
                                            class="hidden rounded-full bg-primary px-2.5 py-0.5 text-[11px] font-semibold text-content-inverse sm:inline-block"
                                        >
                                            Sedang dibuka
                                        </span>
                                        <svg
                                            :class="[
                                                'h-5 w-5 transition-transform',
                                                selectedProject?.id === p.id
                                                    ? 'translate-x-0.5 text-primary'
                                                    : 'text-content-tertiary group-hover:translate-x-0.5 group-hover:text-content',
                                            ]"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                            aria-hidden="true"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M9 5l7 7-7 7"
                                            />
                                        </svg>
                                    </div>
                                </div>

                                <!-- Metric Pills Bar per Proyek -->
                                <div class="mt-3.5 grid grid-cols-3 gap-2 sm:pl-13">
                                    <!-- Pill 1: Selisih Valid vs Ambang -->
                                    <div
                                        :class="[
                                            'rounded-xl border px-3 py-2 text-xs',
                                            p.valid_mismatch_count > 0
                                                ? 'border-negative-border bg-negative-subtle/70 text-negative'
                                                : 'border-stroke bg-surface text-content-secondary',
                                        ]"
                                    >
                                        <div class="flex items-center justify-between gap-1">
                                            <span class="font-medium">Selisih</span>
                                            <span class="font-bold tabular-nums">
                                                {{ p.valid_mismatch_count }}/{{ threshold }}
                                            </span>
                                        </div>
                                        <div class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-surface-muted">
                                            <div
                                                :class="[
                                                    'h-full rounded-full transition-all',
                                                    p.valid_mismatch_count >= threshold
                                                        ? 'bg-warning-solid'
                                                        : p.valid_mismatch_count > 0
                                                          ? 'bg-negative-solid'
                                                          : 'bg-stroke',
                                                ]"
                                                :style="{
                                                    width: `${Math.min(100, Math.round((p.valid_mismatch_count / Math.max(1, threshold)) * 100))}%`,
                                                }"
                                            />
                                        </div>
                                    </div>

                                    <!-- Pill 2: Laporan Sesuai Spek -->
                                    <div
                                        :class="[
                                            'flex flex-col justify-between rounded-xl border px-3 py-2 text-xs',
                                            p.match_count > 0
                                                ? 'border-positive-border bg-positive-subtle/70 text-positive'
                                                : 'border-stroke bg-surface text-content-secondary',
                                        ]"
                                    >
                                        <div class="flex items-center justify-between gap-1">
                                            <span class="font-medium">Sesuai</span>
                                            <span class="font-bold tabular-nums">{{ p.match_count }}</span>
                                        </div>
                                        <span class="mt-1 text-[11px] opacity-80">Laporan warga</span>
                                    </div>

                                    <!-- Pill 3: Di Atas Standar Harga (SSH) -->
                                    <div
                                        :class="[
                                            'flex flex-col justify-between rounded-xl border px-3 py-2 text-xs',
                                            p.above_standard_count > 0
                                                ? 'border-warning-border bg-warning-subtle/70 text-warning'
                                                : 'border-stroke bg-surface text-content-secondary',
                                        ]"
                                    >
                                        <div class="flex items-center justify-between gap-1">
                                            <span class="truncate font-medium">Di atas standar</span>
                                            <span class="font-bold tabular-nums">{{ p.above_standard_count }}</span>
                                        </div>
                                        <span class="mt-1 text-[11px] opacity-80">
                                            {{ p.above_standard_count > 0 ? 'Item RAB > SSH' : 'Sesuai SSH' }}
                                        </span>
                                    </div>
                                </div>
                            </article>
                        </div>
                    </div>

                    <!-- Panduan Visual 3 Tahap Siklus Tinjauan BPD -->
                    <div class="rounded-2xl border border-stroke bg-surface p-4.5 shadow-xs">
                        <div class="mb-3 flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <span
                                    class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-info-subtle text-primary"
                                    aria-hidden="true"
                                >
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                        />
                                    </svg>
                                </span>
                                <h3 class="text-sm font-semibold text-content">
                                    Alur &amp; Aturan Status Publik (Per Siklus Tinjauan)
                                </h3>
                            </div>
                            <Badge color="info">Otomatis</Badge>
                        </div>

                        <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-3">
                            <div class="rounded-xl border border-stroke bg-surface-subtle p-3">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="flex h-5 w-5 items-center justify-center rounded-full bg-surface-muted text-xs font-bold text-content-secondary"
                                    >
                                        1
                                    </span>
                                    <span class="text-xs font-semibold text-content">
                                        &lt; {{ threshold }} Laporan Selisih
                                    </span>
                                </div>
                                <p class="mt-1.5 text-xs leading-relaxed text-content-secondary">
                                    BPD memverifikasi bukti foto warga; halaman publik belum menampilkan penanda
                                    tinjauan.
                                </p>
                            </div>

                            <div class="rounded-xl border border-warning-border bg-warning-subtle/50 p-3">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="flex h-5 w-5 items-center justify-center rounded-full bg-warning-solid text-xs font-bold text-warning"
                                    >
                                        2
                                    </span>
                                    <span class="text-xs font-semibold text-warning">
                                        &ge; {{ threshold }} Selisih Valid
                                    </span>
                                </div>
                                <p class="mt-1.5 text-xs leading-relaxed text-content-secondary">
                                    Dari akun &amp; perangkat berbeda: status otomatis menjadi &ldquo;Dalam tinjauan
                                    BPD&rdquo;.
                                </p>
                            </div>

                            <div class="rounded-xl border border-positive-border bg-positive-subtle/50 p-3">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="flex h-5 w-5 items-center justify-center rounded-full bg-positive-solid text-xs font-bold text-content-inverse"
                                    >
                                        3
                                    </span>
                                    <span class="text-xs font-semibold text-positive">Simpan Keputusan</span>
                                </div>
                                <p class="mt-1.5 text-xs leading-relaxed text-content-secondary">
                                    Hasil musyawarah &amp; catatan resmi tampil di publik, lalu siklus baru dimulai dari
                                    0.
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Panel Detail Proyek & Tinjauan BPD -->
                <section v-if="selectedProject" class="flex flex-col gap-4 lg:col-span-5">
                    <div class="overflow-hidden rounded-2xl border border-stroke bg-surface shadow-xs">
                        <!-- Banner Header Proyek Terpilih -->
                        <div
                            :class="[
                                'border-b border-stroke px-5 py-4',
                                selectedProject.public_status === 'in_review'
                                    ? 'bg-warning-subtle/60'
                                    : 'bg-primary-subtle/50',
                            ]"
                        >
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <Badge :color="statusBadgeColor(selectedProject.public_status)">
                                        {{ statusBadgeText(selectedProject.public_status) }}
                                    </Badge>
                                    <span
                                        v-if="selectedProject.in_review_since"
                                        class="rounded-full bg-surface/80 px-2.5 py-0.5 text-xs font-medium text-content-secondary"
                                    >
                                        sejak {{ formatDate(selectedProject.in_review_since) }}
                                    </span>
                                </div>
                                <span class="text-xs font-medium text-content-tertiary">Panel Tinjauan Aktif</span>
                            </div>

                            <h2 class="mt-2 text-lg leading-snug font-bold text-content sm:text-xl">
                                {{ selectedProject.name }}
                            </h2>

                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                <a
                                    :href="selectedProject.public_url"
                                    target="_blank"
                                    rel="noopener"
                                    class="inline-flex h-8 items-center justify-center gap-1.5 rounded-lg border border-stroke-strong/40 bg-surface px-3 text-xs font-semibold text-content shadow-2xs transition-colors hover:bg-surface-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                                >
                                    <svg
                                        class="h-3.5 w-3.5 text-primary"
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
                                    <span>Halaman publik</span>
                                </a>
                                <a
                                    :href="selectedProject.document_url"
                                    target="_blank"
                                    rel="noopener"
                                    class="inline-flex h-8 items-center justify-center gap-1.5 rounded-lg border border-info-border bg-surface px-3 text-xs font-semibold text-primary shadow-2xs transition-colors hover:bg-primary-subtle focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                                >
                                    <svg
                                        class="h-3.5 w-3.5"
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
                                    <span>Lihat dokumen asli</span>
                                </a>
                            </div>
                        </div>

                        <div class="flex flex-col gap-5 p-5">
                            <!-- Indikator Visual Milestone Ambang Tinjauan BPD -->
                            <div class="space-y-2.5 rounded-xl border border-stroke bg-surface-subtle p-3.5 text-xs">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-semibold text-content">
                                        Ambang pemicu tinjauan BPD (siklus berjalan)
                                    </span>
                                    <span
                                        :class="[
                                            'rounded-full px-2.5 py-0.5 font-bold tabular-nums',
                                            selectedValidMismatchCount >= threshold
                                                ? 'bg-warning-subtle text-warning'
                                                : 'bg-primary-subtle text-primary',
                                        ]"
                                    >
                                        {{ selectedValidMismatchCount }} / {{ threshold }} laporan selisih valid
                                    </span>
                                </div>

                                <!-- Segmented Milestone Bar -->
                                <div class="grid grid-cols-3 gap-1.5">
                                    <div
                                        v-for="step in thresholdSteps"
                                        :key="step"
                                        :class="[
                                            'h-2.5 rounded-full transition-all',
                                            selectedValidMismatchCount >= step
                                                ? selectedValidMismatchCount >= threshold
                                                    ? 'bg-warning-solid'
                                                    : 'bg-primary'
                                                : 'bg-surface-muted',
                                        ]"
                                    />
                                </div>

                                <p class="leading-relaxed text-content-secondary">
                                    <template v-if="selectedProject.public_status === 'in_review'">
                                        <strong class="text-warning"
                                            >Ambang tercapai ({{ thresholdProgressPct }}%).</strong
                                        >
                                        Silakan periksa laporan warga dan simpan keputusan hasil tinjauan BPD di bawah.
                                    </template>
                                    <template v-else>
                                        Butuh
                                        <strong class="text-content">
                                            {{ Math.max(0, threshold - selectedValidMismatchCount) }} laporan selisih
                                            valid lagi
                                        </strong>
                                        dari warga berbeda agar otomatis masuk status &ldquo;Dalam tinjauan BPD&rdquo;.
                                    </template>
                                </p>
                            </div>

                            <!-- Ringkasan Perubahan Pasca-Terbit / CCO (D4/D10) -->
                            <div
                                v-if="selectedProject.changes && selectedProject.changes.length > 0"
                                class="space-y-2.5 rounded-xl border border-info-border bg-info-subtle/70 p-3.5 text-xs"
                            >
                                <div class="flex items-center justify-between gap-2">
                                    <p class="font-semibold text-info">
                                        Riwayat perubahan pasca-terbit / CCO ({{ selectedProject.changes.length }})
                                    </p>
                                    <Badge color="info">Tercatat Publik</Badge>
                                </div>
                                <ul class="max-h-36 divide-y divide-info-border/60 overflow-y-auto pr-1">
                                    <li
                                        v-for="change in selectedProject.changes"
                                        :key="change.id"
                                        class="py-2 first:pt-0 last:pb-0"
                                    >
                                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                                            <span class="font-semibold text-content">
                                                {{ change.item_label }} ({{ change.field }})
                                            </span>
                                            <span class="text-content-tertiary">
                                                {{ formatDateTime(change.changed_at) }}
                                            </span>
                                        </div>
                                        <p class="mt-0.5 text-content-secondary tabular-nums">
                                            Semula: <strong>{{ change.old_value }}</strong> &rarr; Menjadi:
                                            <strong>{{ change.new_value }}</strong>
                                        </p>
                                        <p v-if="change.reason" class="mt-0.5 text-content-secondary">
                                            Alasan desa: &ldquo;{{ change.reason }}&rdquo;
                                        </p>
                                    </li>
                                </ul>
                            </div>

                            <!-- Ringkasan item Di atas standar & penjelasan Pemdes -->
                            <div
                                v-if="
                                    selectedProject.above_standard_items &&
                                    selectedProject.above_standard_items.length > 0
                                "
                                class="space-y-2.5 rounded-xl border border-warning-border bg-warning-subtle/75 p-3.5 text-sm"
                            >
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-xs font-semibold text-warning">
                                        Item RAB di atas standar harga ({{
                                            selectedProject.above_standard_items.length
                                        }})
                                    </p>
                                    <Badge color="warning">Acuan SSH</Badge>
                                </div>
                                <ul class="max-h-44 divide-y divide-warning-border/70 overflow-y-auto pr-1">
                                    <li
                                        v-for="item in selectedProject.above_standard_items"
                                        :key="item.id"
                                        class="py-2 first:pt-0 last:pb-0"
                                    >
                                        <div
                                            class="flex flex-wrap items-baseline justify-between gap-2 text-xs font-semibold text-content"
                                        >
                                            <span>{{ item.name }}</span>
                                            <span class="shrink-0 font-bold text-negative tabular-nums">
                                                <template v-if="item.unit_price_rp !== null">
                                                    {{ formatRupiah(item.unit_price_rp) }}
                                                </template>
                                                <template v-if="item.over_pct !== null">
                                                    (+{{ item.over_pct }}%)
                                                </template>
                                            </span>
                                        </div>
                                        <p v-if="item.explanation" class="mt-1 text-xs text-content-secondary">
                                            Penjelasan desa: &ldquo;{{ item.explanation }}&rdquo;
                                        </p>
                                        <p v-else class="mt-1 text-xs text-content-tertiary">
                                            Belum ada penjelasan tertulis dari Pemerintah Desa.
                                        </p>
                                    </li>
                                </ul>
                            </div>


                            <!-- Form Hasil Tinjauan BPD (hanya saat Dalam tinjauan - B6, satu-satunya tombol Primary di layar) -->
                            <form
                                v-if="selectedProject.public_status === 'in_review'"
                                class="space-y-4 rounded-xl border-2 border-primary/30 bg-primary-subtle/25 p-4"
                                @submit.prevent="submitOutcome"
                            >
                                <div>
                                    <div class="flex items-center justify-between gap-2">
                                        <h3 class="text-base font-bold text-content">Keputusan hasil tinjauan BPD</h3>
                                        <Badge color="warning">Wajib Musyawarah</Badge>
                                    </div>
                                    <p class="mt-0.5 text-xs text-content-secondary">
                                        Hasil dan catatan di bawah akan tampil resmi di halaman publik proyek dan tidak
                                        dapat dihapus.
                                    </p>
                                </div>

                                <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                                    <button
                                        type="button"
                                        :class="[
                                            'rounded-xl border p-3 text-left text-sm font-semibold transition-all',
                                            outcomeForm.result === 'followed_up'
                                                ? 'border-primary bg-surface text-primary shadow-2xs ring-2 ring-primary/20'
                                                : 'border-stroke-strong/40 bg-surface text-content-secondary hover:bg-surface-muted',
                                        ]"
                                        @click="outcomeForm.result = 'followed_up'"
                                    >
                                        <span class="block">{{ reviewOutcome.followed_up }}</span>
                                        <span class="mt-0.5 block text-[11px] font-normal text-content-tertiary">
                                            Perbaikan atau tindak lanjut disepakati bersama Pemdes
                                        </span>
                                    </button>
                                    <button
                                        type="button"
                                        :class="[
                                            'rounded-xl border p-3 text-left text-sm font-semibold transition-all',
                                            outcomeForm.result === 'no_mismatch'
                                                ? 'border-primary bg-surface text-primary shadow-2xs ring-2 ring-primary/20'
                                                : 'border-stroke-strong/40 bg-surface text-content-secondary hover:bg-surface-muted',
                                        ]"
                                        @click="outcomeForm.result = 'no_mismatch'"
                                    >
                                        <span class="block">{{ reviewOutcome.no_mismatch }}</span>
                                        <span class="mt-0.5 block text-[11px] font-normal text-content-tertiary">
                                            Hasil cek lapangan sesuai spesifikasi RAB
                                        </span>
                                    </button>
                                </div>

                                <div class="space-y-1.5">
                                    <label for="bpd-outcome-note" class="block text-sm font-medium text-content">
                                        Catatan resmi hasil tinjauan (wajib, minimal 30 huruf)
                                    </label>
                                    <textarea
                                        id="bpd-outcome-note"
                                        v-model="outcomeForm.note"
                                        rows="3"
                                        placeholder="Jelaskan hasil pengecekan lapangan bersama Pemerintah Desa dan tindak lanjut yang disepakati..."
                                        class="w-full rounded-lg border border-stroke-strong bg-surface p-3 text-sm text-content focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                                    />
                                    <p v-if="outcomeForm.errors.note" class="text-sm text-negative">
                                        {{ outcomeForm.errors.note }}
                                    </p>
                                </div>

                                <Button
                                    type="submit"
                                    variant="primary"
                                    class="w-full"
                                    :loading="outcomeForm.processing"
                                >
                                    Simpan hasil
                                </Button>
                            </form>
                        </div>
                    </div>

                    <!-- Riwayat Siklus Selesai & Catatan Susulan (B9) -->
                    <Card v-if="selectedProject.closed_cycles.length > 0" padding="app">
                        <div class="space-y-4">
                            <div class="flex items-center justify-between gap-2">
                                <h3 class="text-base font-semibold text-content">Riwayat hasil tinjauan selesai</h3>
                                <Badge color="info">{{ selectedProject.closed_cycles.length }} Siklus</Badge>
                            </div>

                            <div class="space-y-4 border-l-2 border-info-border pl-4">
                                <div
                                    v-for="cycle in selectedProject.closed_cycles"
                                    :key="cycle.id"
                                    class="relative space-y-3 rounded-xl border border-stroke bg-surface-subtle/50 p-4 text-sm"
                                >
                                    <span
                                        class="absolute top-4 -left-[23px] h-3 w-3 rounded-full border-2 border-surface bg-primary"
                                        aria-hidden="true"
                                    />
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <Badge color="info">
                                            {{ cycle.result ? reviewOutcome[cycle.result] : '-' }}
                                        </Badge>
                                        <span v-if="cycle.closed_at" class="text-xs text-content-tertiary">
                                            {{ formatDateTime(cycle.closed_at) }}
                                        </span>
                                    </div>
                                    <p class="text-sm leading-relaxed text-content">{{ cycle.note }}</p>

                                    <div
                                        v-if="cycle.notes.length > 0"
                                        class="space-y-1.5 rounded-lg border border-stroke bg-surface p-3"
                                    >
                                        <p class="text-xs font-semibold text-content-secondary">
                                            Catatan tindak lanjut susulan:
                                        </p>
                                        <div v-for="n in cycle.notes" :key="n.id" class="text-xs text-content">
                                            <span v-if="n.created_at" class="text-content-tertiary">
                                                {{ formatDateTime(n.created_at) }} &mdash;
                                            </span>
                                            {{ n.note }}
                                        </div>
                                    </div>

                                    <button
                                        v-if="activeNoteCycleId !== cycle.id"
                                        type="button"
                                        class="text-xs font-semibold text-primary underline underline-offset-2 hover:text-primary-hover"
                                        @click="activeNoteCycleId = cycle.id"
                                    >
                                        Tambah catatan susulan
                                    </button>

                                    <form
                                        v-else
                                        class="space-y-2.5 border-t border-stroke pt-3"
                                        @submit.prevent="submitFollowUp(cycle.id)"
                                    >
                                        <label
                                            :for="`followup-note-${cycle.id}`"
                                            class="block text-xs font-medium text-content"
                                        >
                                            Catatan susulan bertanggal (tampil publik)
                                        </label>
                                        <textarea
                                            :id="`followup-note-${cycle.id}`"
                                            v-model="followUpForm.note"
                                            rows="2"
                                            placeholder="Contoh: Perbaikan ketebalan beton pada ruas RT 02 telah diselesaikan..."
                                            class="w-full rounded-md border border-stroke-strong bg-surface p-2.5 text-sm text-content focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                                        />
                                        <p v-if="followUpForm.errors.note" class="text-xs text-negative">
                                            {{ followUpForm.errors.note }}
                                        </p>
                                        <div class="flex gap-2">
                                            <Button type="submit" variant="secondary" size="sm">Simpan catatan</Button>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                @click="activeNoteCycleId = null"
                                            >
                                                Batal
                                            </Button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </Card>
                </section>
            </div>
        </div>

        <!-- Lightbox Foto Bukti Laporan Warga (Khusus BPD) -->
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
