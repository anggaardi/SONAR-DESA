<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import Alert from '@/components/ui/Alert.vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
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

    return {
        totalProjects,
        inReviewCount,
        reviewedCount,
        unreadReportsCount,
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
            <Button variant="primary" size="sm" href="/bpd/reports">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="1.5"
                        d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"
                    />
                </svg>
                <span>Kotak Masuk Laporan Warga</span>
            </Button>
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
            <!-- Header Section (IDDS Typography & Layout) -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="mb-1.5 flex flex-wrap items-center gap-2">
                        <Badge color="info">Badan Permusyawaratan Desa (BPD)</Badge>
                        <span v-if="village.regency" class="text-xs text-content-tertiary">
                            Kabupaten {{ village.regency }}
                        </span>
                    </div>
                    <h1 class="text-2xl font-semibold text-content md:text-3xl">Dashboard BPD {{ village.name }}</h1>
                    <p class="mt-1 text-sm text-content-secondary">
                        Tinjau laporan fisik warga per siklus, periksa bukti foto dan hasil ukur lapangan, serta
                        terbitkan hasil musyawarah BPD ke publik.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2 md:hidden">
                    <Button variant="primary" size="sm" href="/bpd/reports">
                        <span>Kotak Masuk Laporan Warga</span>
                    </Button>
                    <Button variant="secondary" size="sm" :href="`/d/${village.slug}`">
                        <span>Lihat Halaman Publik</span>
                    </Button>
                </div>
            </div>

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

            <!-- Ringkasan Statistik KPI (IDDS Cards) -->
            <section aria-labelledby="heading-ringkasan-bpd">
                <h2 id="heading-ringkasan-bpd" class="sr-only">Ringkasan Pengawasan BPD</h2>
                <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <Card padding="app">
                        <p class="text-sm font-medium text-content-secondary">Proyek Terbit</p>
                        <p class="mt-2 text-3xl font-bold text-content tabular-nums">
                            {{ stats.totalProjects }}
                        </p>
                        <p class="mt-1 text-xs text-content-tertiary">Dipantau di {{ village.name }}</p>
                    </Card>

                    <Card padding="app">
                        <p class="text-sm font-medium text-content-secondary">Dalam Tinjauan BPD</p>
                        <p
                            :class="[
                                'mt-2 text-3xl font-bold tabular-nums',
                                stats.inReviewCount > 0 ? 'text-warning' : 'text-content',
                            ]"
                        >
                            {{ stats.inReviewCount }}
                        </p>
                        <p class="mt-1 text-xs text-content-tertiary">Mencapai &ge; {{ threshold }} laporan selisih</p>
                    </Card>

                    <Card padding="app">
                        <p class="text-sm font-medium text-content-secondary">Laporan Belum Dibuka</p>
                        <p
                            :class="[
                                'mt-2 text-3xl font-bold tabular-nums',
                                stats.unreadReportsCount > 0 ? 'text-primary' : 'text-content',
                            ]"
                        >
                            {{ stats.unreadReportsCount }}
                        </p>
                        <p class="mt-1 text-xs text-content-tertiary">Siklus berjalan aktif</p>
                    </Card>

                    <Card padding="app">
                        <p class="text-sm font-medium text-content-secondary">Sudah Ditinjau</p>
                        <p class="mt-2 text-3xl font-bold text-info tabular-nums">
                            {{ stats.reviewedCount }}
                        </p>
                        <p class="mt-1 text-xs text-content-tertiary">Hasil tinjauan tampil publik</p>
                    </Card>
                </div>
            </section>

            <!-- Konten Utama: Daftar Proyek (Kiri) & Panel Detail Tinjauan (Kanan) -->
            <div id="daftar-proyek-bpd" class="grid gap-6 lg:grid-cols-12">
                <!-- Daftar Proyek -->
                <section class="flex flex-col gap-4 lg:col-span-7" aria-labelledby="heading-daftar-proyek">
                    <div class="overflow-hidden rounded-lg border border-stroke bg-surface">
                        <div class="space-y-3 border-b border-stroke px-5 py-4">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <h2 id="heading-daftar-proyek" class="text-base font-semibold text-content">
                                        Daftar Proyek Fisik Desa
                                    </h2>
                                    <p class="mt-0.5 text-xs text-content-secondary">
                                        Diurutkan otomatis berdasarkan prioritas: Dalam tinjauan BPD, laporan baru, lalu
                                        item di atas standar.
                                    </p>
                                </div>
                                <Badge color="neutral"
                                    >{{ filteredProjects.length }} / {{ projects.length }} Proyek</Badge
                                >
                            </div>

                            <!-- Pencarian & Filter Cepat Daftar Proyek -->
                            <div v-if="projects.length > 0" class="flex flex-col gap-2.5 sm:flex-row sm:items-center">
                                <div class="relative flex-1">
                                    <input
                                        v-model="projectSearch"
                                        type="search"
                                        placeholder="Cari nama proyek..."
                                        aria-label="Cari nama proyek"
                                        class="h-9 w-full rounded-md border border-stroke-strong bg-surface px-3 text-sm text-content focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                                    />
                                </div>
                                <div class="flex flex-wrap gap-1.5">
                                    <button
                                        v-for="tab in [
                                            { key: 'all', label: 'Semua' },
                                            { key: 'in_review', label: 'Dalam tinjauan' },
                                            { key: 'unread', label: 'Laporan baru' },
                                            { key: 'above_standard', label: 'Di atas standar' },
                                            { key: 'reviewed', label: 'Sudah ditinjau' },
                                        ] as const"
                                        :key="tab.key"
                                        type="button"
                                        :class="[
                                            'rounded-md border px-2.5 py-1 text-xs font-medium transition-colors',
                                            projectFilter === tab.key
                                                ? 'border-primary bg-primary-subtle text-primary'
                                                : 'border-stroke bg-surface-subtle text-content-secondary hover:bg-surface-muted hover:text-content',
                                        ]"
                                        @click="projectFilter = tab.key"
                                    >
                                        {{ tab.label }}
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
                            class="px-4 py-8 text-center text-sm text-content-secondary"
                        >
                            Tidak ada proyek yang cocok dengan filter atau kata kunci pencarian.
                        </div>

                        <div v-else class="overflow-x-auto">
                            <table class="w-full border-collapse text-left text-sm">
                                <thead class="border-b border-stroke bg-surface-subtle text-sm text-content-secondary">
                                    <tr>
                                        <th scope="col" class="px-4 py-3 font-medium">Proyek</th>
                                        <th scope="col" class="px-3 py-3 text-right font-medium">Di atas standar</th>
                                        <th scope="col" class="px-3 py-3 text-right font-medium">Selisih</th>
                                        <th scope="col" class="px-3 py-3 text-right font-medium">Sesuai</th>
                                        <th scope="col" class="px-4 py-3 font-medium">Status publik</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-stroke">
                                    <tr
                                        v-for="p in filteredProjects"
                                        :key="p.id"
                                        :class="[
                                            'cursor-pointer transition-colors',
                                            selectedProject?.id === p.id
                                                ? 'bg-primary-subtle'
                                                : 'hover:bg-surface-muted',
                                        ]"
                                        @click="selectProject(p.id)"
                                    >
                                        <th scope="row" class="px-4 py-3.5 font-medium text-content">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span
                                                    :class="
                                                        selectedProject?.id === p.id
                                                            ? 'font-semibold text-primary'
                                                            : 'text-content'
                                                    "
                                                >
                                                    {{ p.name }}
                                                </span>
                                                <Badge v-if="p.unread_count > 0" color="info">
                                                    {{ p.unread_count }} baru
                                                </Badge>
                                            </div>
                                        </th>
                                        <td class="px-3 py-3.5 text-right tabular-nums">
                                            <span
                                                :class="
                                                    p.above_standard_count > 0
                                                        ? 'font-semibold text-negative'
                                                        : 'text-content-secondary'
                                                "
                                            >
                                                {{ p.above_standard_count }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-3.5 text-right tabular-nums">
                                            <span
                                                :class="
                                                    p.valid_mismatch_count > 0
                                                        ? 'font-semibold text-negative'
                                                        : 'text-content-secondary'
                                                "
                                            >
                                                {{ p.valid_mismatch_count }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-3.5 text-right tabular-nums">
                                            <span
                                                :class="
                                                    p.match_count > 0
                                                        ? 'font-semibold text-positive'
                                                        : 'text-content-secondary'
                                                "
                                            >
                                                {{ p.match_count }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3.5">
                                            <Badge :color="statusBadgeColor(p.public_status)">
                                                {{ statusBadgeText(p.public_status) }}
                                            </Badge>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Panduan Aturan Status Publik (IDDS Subtle Card) -->
                    <Card surface="subtle">
                        <div class="flex items-start gap-3">
                            <span
                                class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-info-subtle text-info"
                                aria-hidden="true"
                            >
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                    />
                                </svg>
                            </span>
                            <div class="text-sm text-content-secondary">
                                <p class="font-semibold text-content">Aturan status publik (per siklus tinjauan):</p>
                                <ul class="mt-1.5 list-disc space-y-1 pl-4 text-xs leading-relaxed">
                                    <li>
                                        <strong>&lt; {{ threshold }} laporan selisih valid:</strong> belum menampilkan
                                        penanda tinjauan di halaman publik.
                                    </li>
                                    <li>
                                        <strong>&ge; {{ threshold }} laporan selisih valid (akun berbeda):</strong>
                                        status proyek otomatis berubah menjadi &ldquo;Dalam tinjauan BPD&rdquo;.
                                    </li>
                                    <li>
                                        <strong>Simpan hasil tinjauan:</strong> keputusan dan catatan resmi BPD tampil
                                        di halaman publik, lalu siklus baru dimulai kembali dari 0.
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </Card>
                </section>

                <!-- Panel Detail Proyek & Tinjauan BPD -->
                <section v-if="selectedProject" class="flex flex-col gap-4 lg:col-span-5">
                    <Card padding="app">
                        <div class="flex flex-col gap-5">
                            <!-- Header Proyek Terpilih -->
                            <div
                                class="flex flex-col gap-3 border-b border-stroke pb-4 sm:flex-row sm:items-start sm:justify-between"
                            >
                                <div class="space-y-1.5">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <Badge :color="statusBadgeColor(selectedProject.public_status)">
                                            {{ statusBadgeText(selectedProject.public_status) }}
                                        </Badge>
                                        <span
                                            v-if="selectedProject.in_review_since"
                                            class="text-xs text-content-tertiary"
                                        >
                                            sejak {{ formatDate(selectedProject.in_review_since) }}
                                        </span>
                                    </div>
                                    <h2 class="text-xl font-semibold text-content">{{ selectedProject.name }}</h2>
                                </div>

                                <div class="flex flex-wrap items-center gap-2">
                                    <a
                                        :href="selectedProject.public_url"
                                        target="_blank"
                                        rel="noopener"
                                        class="inline-flex h-9 items-center justify-center gap-1.5 rounded-md border border-stroke-strong bg-surface px-3 text-xs font-medium text-content transition-colors hover:bg-surface-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                                    >
                                        <span>Halaman publik</span>
                                    </a>
                                    <a
                                        :href="selectedProject.document_url"
                                        target="_blank"
                                        rel="noopener"
                                        class="inline-flex h-9 items-center justify-center gap-1.5 rounded-md border border-stroke bg-surface-subtle px-3 text-xs font-medium text-primary transition-colors hover:bg-primary-subtle focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                                    >
                                        <span>Lihat dokumen asli</span>
                                    </a>
                                </div>
                            </div>

                            <!-- Indikator Progres Menuju Ambang Tinjauan BPD -->
                            <div class="space-y-1.5 rounded-lg border border-stroke bg-surface-subtle p-3 text-xs">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-medium text-content">
                                        Ambang pemicu tinjauan BPD (siklus berjalan)
                                    </span>
                                    <span class="font-semibold text-content tabular-nums">
                                        {{ selectedValidMismatchCount }} / {{ threshold }} laporan selisih valid
                                    </span>
                                </div>
                                <div class="h-2 w-full overflow-hidden rounded-full bg-surface-muted">
                                    <div
                                        :class="[
                                            'h-full rounded-full transition-all',
                                            selectedValidMismatchCount >= threshold ? 'bg-warning-solid' : 'bg-primary',
                                        ]"
                                        :style="{ width: `${thresholdProgressPct}%` }"
                                    />
                                </div>
                                <p class="text-content-secondary">
                                    <template v-if="selectedProject.public_status === 'in_review'">
                                        Ambang tercapai. Silakan periksa laporan warga dan simpan keputusan hasil
                                        tinjauan BPD di bawah.
                                    </template>
                                    <template v-else>
                                        Butuh
                                        {{ Math.max(0, threshold - selectedValidMismatchCount) }} laporan selisih valid
                                        lagi dari warga berbeda agar otomatis masuk status &ldquo;Dalam tinjauan
                                        BPD&rdquo;.
                                    </template>
                                </p>
                            </div>

                            <!-- Ringkasan Perubahan Pasca-Terbit / CCO (D4/D10) -->
                            <div
                                v-if="selectedProject.changes && selectedProject.changes.length > 0"
                                class="space-y-2 rounded-lg border border-info-border bg-info-subtle p-3.5 text-xs"
                            >
                                <div class="flex items-center justify-between gap-2">
                                    <p class="font-semibold text-info">
                                        Riwayat perubahan pasca-terbit / CCO ({{ selectedProject.changes.length }})
                                    </p>
                                    <Badge color="info">Tercatat Publik</Badge>
                                </div>
                                <ul class="max-h-36 divide-y divide-info-border overflow-y-auto pr-1">
                                    <li
                                        v-for="change in selectedProject.changes"
                                        :key="change.id"
                                        class="py-1.5 first:pt-0 last:pb-0"
                                    >
                                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                                            <span class="font-medium text-content">
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
                                class="space-y-2 rounded-lg border border-warning-border bg-warning-subtle p-3.5 text-sm"
                            >
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-xs font-semibold text-warning">
                                        Item RAB di atas standar harga ({{
                                            selectedProject.above_standard_items.length
                                        }})
                                    </p>
                                    <Badge color="warning">Acuan SSH</Badge>
                                </div>
                                <ul class="max-h-40 divide-y divide-warning-border overflow-y-auto pr-1">
                                    <li
                                        v-for="item in selectedProject.above_standard_items"
                                        :key="item.id"
                                        class="py-1.5 first:pt-0 last:pb-0"
                                    >
                                        <div
                                            class="flex flex-wrap items-baseline justify-between gap-2 text-xs font-medium text-content"
                                        >
                                            <span>{{ item.name }}</span>
                                            <span class="shrink-0 font-semibold text-negative tabular-nums">
                                                <template v-if="item.unit_price_rp !== null">
                                                    {{ formatRupiah(item.unit_price_rp) }}
                                                </template>
                                                <template v-if="item.over_pct !== null">
                                                    (+{{ item.over_pct }}%)
                                                </template>
                                            </span>
                                        </div>
                                        <p v-if="item.explanation" class="mt-0.5 text-xs text-content-secondary">
                                            Penjelasan desa: &ldquo;{{ item.explanation }}&rdquo;
                                        </p>
                                        <p v-else class="mt-0.5 text-xs text-content-tertiary">
                                            Belum ada penjelasan tertulis dari Pemerintah Desa.
                                        </p>
                                    </li>
                                </ul>
                            </div>

                            <!-- Daftar Laporan Warga di Siklus Berjalan (Accordion + Inner Scroll + Inline Layout) -->
                            <div class="space-y-2.5">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div>
                                        <h3 class="text-sm font-semibold text-content">
                                            Laporan warga siklus berjalan
                                        </h3>
                                        <p
                                            v-if="selectedProject.reports.length > 1"
                                            class="text-xs text-content-tertiary"
                                        >
                                            Klik baris pelapor untuk membuka rincian, memverifikasi, atau membalas
                                            pesan.
                                        </p>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <Link
                                            :href="`/bpd/reports?project=${selectedProject.id}`"
                                            class="rounded-md border border-stroke-strong bg-surface px-2.5 py-1 text-xs font-medium text-content transition-colors hover:bg-surface-muted"
                                        >
                                            Buka Kotak Masuk &rarr;
                                        </Link>
                                        <button
                                            v-if="selectedUnreadCount > 0"
                                            type="button"
                                            class="rounded-md border border-primary/30 bg-primary-subtle px-2.5 py-1 text-xs font-semibold text-primary transition-colors hover:bg-primary hover:text-content-inverse"
                                            @click="markAllOpened"
                                        >
                                            Tandai semua dibuka ({{ selectedUnreadCount }})
                                        </button>
                                        <Badge color="neutral">{{ selectedProject.reports.length }} Laporan</Badge>
                                    </div>
                                </div>

                                <!-- Filter Jenis Laporan Warga -->
                                <div v-if="selectedProject.reports.length > 0" class="flex flex-wrap gap-1.5">
                                    <button
                                        v-for="tab in [
                                            { key: 'all', label: 'Semua' },
                                            { key: 'mismatch', label: 'Selisih' },
                                            { key: 'match', label: 'Sesuai' },
                                            { key: 'valid_only', label: 'Hanya valid' },
                                        ] as const"
                                        :key="tab.key"
                                        type="button"
                                        :class="[
                                            'rounded-md border px-2.5 py-1 text-xs font-medium transition-colors',
                                            reportFilter === tab.key
                                                ? 'border-primary bg-primary-subtle text-primary'
                                                : 'border-stroke bg-surface-subtle text-content-secondary hover:bg-surface-muted hover:text-content',
                                        ]"
                                        @click="reportFilter = tab.key"
                                    >
                                        {{ tab.label }}
                                    </button>
                                </div>

                                <div
                                    v-if="selectedProject.reports.length === 0"
                                    class="flex flex-col items-center justify-center rounded-lg border border-stroke bg-surface-subtle px-4 py-8 text-center"
                                >
                                    <div class="mb-3 aspect-4/3 w-full max-w-40" aria-hidden="true">
                                        <img
                                            :src="bpdEmpty"
                                            alt=""
                                            width="280"
                                            height="210"
                                            class="h-full w-full object-contain"
                                        />
                                    </div>
                                    <p class="text-sm font-semibold text-content">Belum ada laporan di siklus ini</p>
                                    <p class="mt-1 max-w-xs text-xs leading-relaxed text-content-secondary">
                                        Laporan Cek Spek yang dikirim warga dari lokasi proyek akan masuk ke daftar ini.
                                    </p>
                                </div>

                                <div
                                    v-else-if="filteredReports.length === 0"
                                    class="rounded-lg border border-stroke bg-surface-subtle px-4 py-6 text-center text-xs text-content-secondary"
                                >
                                    Tidak ada laporan warga pada kategori filter ini.
                                </div>

                                <div
                                    v-else
                                    class="max-h-96 divide-y divide-stroke overflow-y-auto rounded-lg border border-stroke bg-surface"
                                >
                                    <article
                                        v-for="r in filteredReports"
                                        :key="r.id"
                                        :class="r.invalid_reason ? 'bg-surface-subtle opacity-80' : ''"
                                    >
                                        <button
                                            type="button"
                                            class="flex w-full items-center justify-between gap-2 px-3.5 py-2.5 text-left text-sm transition-colors hover:bg-surface-subtle focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                                            :aria-expanded="expandedReportId === r.id"
                                            @click="toggleReport(r.id)"
                                        >
                                            <div class="flex min-w-0 flex-wrap items-center gap-2">
                                                <svg
                                                    :class="[
                                                        'h-4 w-4 shrink-0 text-content-tertiary transition-transform',
                                                        expandedReportId === r.id ? 'rotate-90 text-primary' : '',
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
                                                <span class="font-semibold text-content">{{ r.reporter_label }}</span>
                                                <Badge :color="reportKindBadgeColor(r.kind)">
                                                    {{ reportType[r.kind] }}
                                                </Badge>
                                                <Badge v-if="r.invalid_reason" color="negative">tidak valid</Badge>
                                                <Badge v-else-if="r.is_verified" color="positive">valid</Badge>
                                                <Badge v-else-if="r.is_new" color="info">baru</Badge>
                                                <Badge v-if="r.superseded" color="neutral">digantikan</Badge>
                                            </div>

                                            <div
                                                class="flex shrink-0 items-center gap-2 text-xs text-content-tertiary tabular-nums"
                                            >
                                                <span v-if="r.distance_m !== null">
                                                    {{ Math.round(r.distance_m) }} m
                                                </span>
                                                <span v-if="r.photos && r.photos.length > 0">
                                                    &middot; {{ r.photos.length }} foto
                                                </span>
                                            </div>
                                        </button>

                                        <div
                                            v-show="expandedReportId === r.id"
                                            class="space-y-2.5 border-t border-stroke bg-surface-subtle/50 px-3.5 py-3 text-sm"
                                        >
                                            <div
                                                v-if="r.answers && r.answers.length > 0"
                                                class="flex flex-wrap gap-1.5"
                                            >
                                                <div
                                                    v-for="ans in r.answers"
                                                    :key="ans.id"
                                                    :class="[
                                                        'inline-flex flex-wrap items-center gap-1 rounded-md border px-2 py-1 text-xs tabular-nums',
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
                                                            {{ ans.measured_value ?? '-' }}
                                                            {{ ans.measured_unit ?? '' }}
                                                        </span>
                                                        <span
                                                            v-if="ans.rab_value !== null"
                                                            class="text-content-secondary"
                                                        >
                                                            (RAB {{ ans.rab_value }} {{ ans.rab_unit ?? '' }})
                                                        </span>
                                                    </template>
                                                    <template v-else-if="ans.answer === 'match'">
                                                        <span class="font-semibold">
                                                            {{ checkSpec.answers.match }}
                                                        </span>
                                                        <span
                                                            v-if="ans.rab_value !== null"
                                                            class="text-content-secondary"
                                                        >
                                                            ({{ ans.rab_value }} {{ ans.rab_unit ?? '' }})
                                                        </span>
                                                    </template>
                                                    <template v-else>
                                                        <span>{{ checkSpec.answers.unmeasurable }}</span>
                                                    </template>
                                                </div>
                                            </div>

                                            <div class="flex items-start justify-between gap-3">
                                                <div class="min-w-0 flex-1 space-y-1">
                                                    <p v-if="r.note" class="text-sm leading-snug text-content">
                                                        &ldquo;{{ r.note }}&rdquo;
                                                    </p>
                                                    <p class="text-xs text-content-tertiary">
                                                        <span v-if="r.distance_m !== null">
                                                            {{ Math.round(r.distance_m) }} m dari titik proyek &middot;
                                                        </span>
                                                        <span v-if="r.created_at">
                                                            {{ formatDateTime(r.created_at) }}
                                                        </span>
                                                    </p>
                                                </div>

                                                <div
                                                    v-if="r.photos && r.photos.length > 0"
                                                    class="flex shrink-0 flex-wrap items-center gap-1.5"
                                                >
                                                    <button
                                                        v-for="photo in r.photos"
                                                        :key="photo.id"
                                                        type="button"
                                                        class="group relative h-14 w-16 overflow-hidden rounded-md border border-stroke bg-surface-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                                                        :aria-label="`Lihat foto ${photo.position} dari ${r.reporter_label}`"
                                                        @click="openPhotoLightbox(r, photo)"
                                                    >
                                                        <img
                                                            :src="photo.url"
                                                            :alt="`Foto ${photo.position} ${r.reporter_label}`"
                                                            loading="lazy"
                                                            class="h-full w-full object-cover transition-transform group-hover:scale-105"
                                                        />
                                                        <span
                                                            class="absolute right-0.5 bottom-0.5 rounded-sm bg-black/75 px-1 py-0.5 text-xs leading-none font-medium text-white"
                                                        >
                                                            #{{ photo.position }}
                                                        </span>
                                                    </button>
                                                </div>
                                            </div>

                                            <div
                                                v-if="r.invalid_reason"
                                                class="rounded-md border border-negative-border bg-negative-subtle px-2.5 py-1.5 text-xs text-negative"
                                            >
                                                Ditandai tidak valid (Terkunci): <strong>{{ r.invalid_reason }}</strong>
                                            </div>
                                            <div
                                                v-else-if="r.is_verified"
                                                class="rounded-md border border-positive-border bg-positive-subtle px-2.5 py-1.5 text-xs text-positive"
                                            >
                                                Status verifikasi final: <strong>Valid</strong> (Terkunci).
                                            </div>

                                            <div
                                                v-if="r.bpd_reply"
                                                class="rounded-md border border-info-border bg-info-subtle px-2.5 py-2 text-xs text-content"
                                            >
                                                <div class="flex items-center justify-between gap-2">
                                                    <span class="font-semibold text-info">Balasan BPD (1/1):</span>
                                                    <span v-if="r.bpd_replied_at" class="text-content-tertiary">
                                                        {{ formatDateTime(r.bpd_replied_at) }}
                                                    </span>
                                                </div>
                                                <p class="mt-0.5 text-content">{{ r.bpd_reply }}</p>
                                            </div>

                                            <div class="flex flex-wrap items-center gap-2.5 pt-0.5 text-xs">
                                                <template v-if="!r.is_verified">
                                                    <button
                                                        type="button"
                                                        class="inline-flex items-center gap-1 rounded-md border border-positive-border bg-positive-subtle px-2.5 py-1 font-semibold text-positive transition-colors hover:bg-positive-solid hover:text-content-inverse"
                                                        @click="validateReport(r.id)"
                                                    >
                                                        <span>Tandai valid</span>
                                                    </button>
                                                    <button
                                                        type="button"
                                                        class="inline-flex items-center gap-1 rounded-md border border-stroke bg-surface px-2.5 py-1 font-medium text-content-secondary transition-colors hover:border-negative-border hover:bg-negative-subtle hover:text-negative"
                                                        @click="invalidatingReportId = r.id"
                                                    >
                                                        <span>Tandai tidak valid</span>
                                                    </button>
                                                </template>

                                                <button
                                                    v-if="!r.bpd_reply && replyingReportId !== r.id"
                                                    type="button"
                                                    class="font-medium text-primary underline underline-offset-2 hover:text-primary-hover"
                                                    @click="
                                                        replyingReportId = r.id;
                                                        reportReplyForm.reset();
                                                    "
                                                >
                                                    Balas pesan pelapor (1x)
                                                </button>
                                            </div>

                                            <form
                                                v-if="!r.is_verified && invalidatingReportId === r.id"
                                                class="mt-2 space-y-2 border-t border-stroke pt-2.5"
                                                @submit.prevent="submitInvalid(r.id)"
                                            >
                                                <label
                                                    :for="`invalid-reason-${r.id}`"
                                                    class="block text-xs font-medium text-content"
                                                >
                                                    Alasan laporan tidak valid (final)
                                                </label>
                                                <div class="flex flex-wrap gap-1.5">
                                                    <button
                                                        v-for="preset in invalidReasonPresets"
                                                        :key="preset"
                                                        type="button"
                                                        :class="[
                                                            'rounded-md border px-2 py-0.5 text-xs transition-colors',
                                                            invalidForm.reason === preset
                                                                ? 'border-primary bg-primary-subtle font-medium text-primary'
                                                                : 'border-stroke bg-surface text-content-secondary hover:bg-surface-muted',
                                                        ]"
                                                        @click="invalidForm.reason = preset"
                                                    >
                                                        {{ preset }}
                                                    </button>
                                                </div>
                                                <input
                                                    :id="`invalid-reason-${r.id}`"
                                                    v-model="invalidForm.reason"
                                                    type="text"
                                                    placeholder="Pilih alasan di atas atau tulis alasan singkat"
                                                    class="h-9 w-full rounded-md border border-stroke-strong bg-surface px-2.5 text-xs text-content focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                                                />
                                                <p v-if="invalidForm.errors.reason" class="text-xs text-negative">
                                                    {{ invalidForm.errors.reason }}
                                                </p>
                                                <div class="flex gap-2">
                                                    <Button type="submit" variant="secondary" size="sm">Simpan</Button>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        @click="invalidatingReportId = null"
                                                    >
                                                        Batal
                                                    </Button>
                                                </div>
                                            </form>

                                            <form
                                                v-if="!r.bpd_reply && replyingReportId === r.id"
                                                class="mt-2 space-y-2 border-t border-stroke pt-2.5"
                                                @submit.prevent="submitReportReply(r.id)"
                                            >
                                                <label
                                                    :for="`report-reply-${r.id}`"
                                                    class="block text-xs font-medium text-content"
                                                >
                                                    Balasan resmi BPD untuk pelapor ini (hanya dapat dikirim 1 kali)
                                                </label>
                                                <input
                                                    :id="`report-reply-${r.id}`"
                                                    v-model="reportReplyForm.reply"
                                                    type="text"
                                                    placeholder="Tulis satu balasan resmi BPD untuk laporan ini..."
                                                    class="h-9 w-full rounded-md border border-stroke-strong bg-surface px-2.5 text-xs text-content focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                                                />
                                                <p v-if="reportReplyForm.errors.reply" class="text-xs text-negative">
                                                    {{ reportReplyForm.errors.reply }}
                                                </p>
                                                <div class="flex gap-2">
                                                    <Button
                                                        type="submit"
                                                        variant="secondary"
                                                        size="sm"
                                                        :loading="reportReplyForm.processing"
                                                    >
                                                        Kirim balasan
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        @click="replyingReportId = null"
                                                    >
                                                        Batal
                                                    </Button>
                                                </div>
                                            </form>
                                        </div>
                                    </article>
                                </div>
                            </div>

                            <!-- Form Hasil Tinjauan BPD (hanya saat Dalam tinjauan - B6, satu-satunya tombol Primary di layar) -->
                            <form
                                v-if="selectedProject.public_status === 'in_review'"
                                class="space-y-4 border-t border-stroke pt-4"
                                @submit.prevent="submitOutcome"
                            >
                                <div>
                                    <h3 class="text-base font-semibold text-content">Keputusan hasil tinjauan BPD</h3>
                                    <p class="mt-0.5 text-xs text-content-secondary">
                                        Hasil dan catatan di bawah akan tampil resmi di halaman publik proyek dan tidak
                                        dapat dihapus.
                                    </p>
                                </div>

                                <div class="grid grid-cols-2 gap-2.5">
                                    <button
                                        type="button"
                                        :class="[
                                            'rounded-md border px-3 py-2.5 text-sm font-medium transition-colors',
                                            outcomeForm.result === 'followed_up'
                                                ? 'border-primary bg-primary-subtle text-primary'
                                                : 'border-stroke-strong bg-surface text-content-secondary hover:bg-surface-muted',
                                        ]"
                                        @click="outcomeForm.result = 'followed_up'"
                                    >
                                        {{ reviewOutcome.followed_up }}
                                    </button>
                                    <button
                                        type="button"
                                        :class="[
                                            'rounded-md border px-3 py-2.5 text-sm font-medium transition-colors',
                                            outcomeForm.result === 'no_mismatch'
                                                ? 'border-primary bg-primary-subtle text-primary'
                                                : 'border-stroke-strong bg-surface text-content-secondary hover:bg-surface-muted',
                                        ]"
                                        @click="outcomeForm.result = 'no_mismatch'"
                                    >
                                        {{ reviewOutcome.no_mismatch }}
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
                                        class="w-full rounded-md border border-stroke-strong bg-surface p-3 text-sm text-content focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
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
                    </Card>

                    <!-- Riwayat Siklus Selesai & Catatan Susulan (B9) -->
                    <Card v-if="selectedProject.closed_cycles.length > 0" padding="app">
                        <div class="space-y-4">
                            <div class="flex items-center justify-between gap-2">
                                <h3 class="text-base font-semibold text-content">Riwayat hasil tinjauan selesai</h3>
                                <Badge color="info">{{ selectedProject.closed_cycles.length }} Siklus</Badge>
                            </div>

                            <div class="divide-y divide-stroke rounded-lg border border-stroke">
                                <div
                                    v-for="cycle in selectedProject.closed_cycles"
                                    :key="cycle.id"
                                    class="space-y-3 p-4 text-sm"
                                >
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
                                        class="space-y-1.5 rounded-md border border-stroke bg-surface-subtle p-3"
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
                                        class="text-xs font-medium text-primary underline underline-offset-2 hover:text-primary-hover"
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
