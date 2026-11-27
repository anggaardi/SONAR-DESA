<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';
import Alert from '@/components/ui/Alert.vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate, formatDateTime, formatRupiah } from '@/lib/format';
import { priceLabel, publicStatus, reviewOutcome, type ReviewOutcome } from '@/lib/labels';

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

interface ReportSummaryItem {
    id: number;
    kind: 'mismatch' | 'match' | 'unmeasurable';
    superseded: boolean;
    invalid_reason: string | null;
    is_new: boolean;
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
    reports: ReportSummaryItem[];
    closed_cycles: ClosedCycle[];
}

type ProjectFilterKey = 'all' | 'in_review' | 'unread' | 'above_standard' | 'reviewed';

const props = defineProps<{
    village: { name: string; slug: string; regency?: string };
    projects: ProjectRow[];
    selectedProject: SelectedProject | null;
    threshold: number;
}>();

const currentBudgetYear = new Date().getFullYear();

const projectSearch = ref('');
const projectFilter = ref<ProjectFilterKey>('all');

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
        label: 'Sidang pleno',
        count: props.projects.filter((p) => p.public_status === 'in_review').length,
    },
    {
        key: 'unread' as const,
        label: 'Laporan baru',
        count: props.projects.filter((p) => p.unread_count > 0).length,
    },
    {
        key: 'above_standard' as const,
        label: 'Di atas SSH',
        count: props.projects.filter((p) => p.above_standard_count > 0).length,
    },
    {
        key: 'reviewed' as const,
        label: 'Selesai ditinjau',
        count: props.projects.filter((p) => p.public_status === 'reviewed').length,
    },
]);

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

const thresholdSteps = computed(() => {
    const count = Math.max(1, props.threshold);
    return Array.from({ length: count }, (_, idx) => idx + 1);
});

const outcomeForm = useForm<{ result: ReviewOutcome; note: string }>({
    result: 'followed_up',
    note: '',
});

// Native <dialog> untuk Catatan Susulan & Rincian Revisi CCO (DESIGN.md §6B)
const followUpDialogRef = ref<HTMLDialogElement | null>(null);
const activeNoteCycleId = ref<number | null>(null);
const followUpForm = useForm({ note: '' });

const ccoDialogRef = ref<HTMLDialogElement | null>(null);
const isDialogOpen = ref(false);

// DESIGN.md §6 & §12: Hanya satu tombol `primary` per layar.
const hasOutcomePrimaryButton = computed(
    () => props.selectedProject?.public_status === 'in_review' && !isDialogOpen.value,
);

const stats = computed(() => {
    const totalProjects = props.projects.length;
    const inReviewCount = props.projects.filter((p) => p.public_status === 'in_review').length;
    const reviewedCount = props.projects.filter((p) => p.public_status === 'reviewed').length;
    const unreadReportsCount = props.projects.reduce((sum, p) => sum + p.unread_count, 0);
    const aboveStandardProjectsCount = props.projects.filter((p) => p.above_standard_count > 0).length;

    return {
        totalProjects,
        inReviewCount,
        reviewedCount,
        unreadReportsCount,
        aboveStandardProjectsCount,
    };
});

function toggleKpiFilter(target: ProjectFilterKey): void {
    projectFilter.value = projectFilter.value === target ? 'all' : target;
}

function selectProject(id: number): void {
    router.get('/bpd', { project: id }, { preserveScroll: true });
}

function submitOutcome(): void {
    if (!props.selectedProject) return;
    outcomeForm.post(`/bpd/projects/${props.selectedProject.id}/outcome`, {
        preserveScroll: true,
        onSuccess: () => outcomeForm.reset(),
    });
}

async function openFollowUpDialog(cycleId: number): Promise<void> {
    activeNoteCycleId.value = cycleId;
    followUpForm.reset();
    followUpForm.clearErrors();
    isDialogOpen.value = true;
    await nextTick();
    followUpDialogRef.value?.showModal();
}

function closeFollowUpDialog(): void {
    followUpDialogRef.value?.close();
    activeNoteCycleId.value = null;
    isDialogOpen.value = false;
}

function submitFollowUp(): void {
    if (activeNoteCycleId.value === null) return;
    followUpForm.post(`/bpd/cycles/${activeNoteCycleId.value}/notes`, {
        preserveScroll: true,
        onSuccess: () => {
            followUpForm.reset();
            closeFollowUpDialog();
        },
    });
}

async function openCcoDialog(): Promise<void> {
    isDialogOpen.value = true;
    await nextTick();
    ccoDialogRef.value?.showModal();
}

function closeCcoDialog(): void {
    ccoDialogRef.value?.close();
    isDialogOpen.value = false;
}

function handleDialogBackdropClick(event: MouseEvent, closeFn: () => void): void {
    if (event.target === event.currentTarget) {
        closeFn();
    }
}
</script>

<template>
    <AppLayout>
        <Head :title="`Dashboard Pengawasan BPD — ${village.name}`" />

        <template #breadcrumb>
            <Link href="/" class="transition-colors hover:text-primary">Beranda</Link>
            <span>/</span>
            <span class="font-semibold text-content">Pengawasan BPD {{ village.name }}</span>
        </template>

        <template #headerActions>
            <Button variant="secondary" size="sm" :href="`/d/${village.slug}`">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
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
            </Button>
        </template>

        <template #nav>
            <Link
                href="/bpd"
                class="flex min-h-11 items-center gap-3 rounded-lg bg-primary-subtle px-3 py-2 text-sm font-semibold text-primary transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
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
                class="flex min-h-11 items-center justify-between gap-2 rounded-lg px-3 py-2 text-sm font-medium text-content-secondary transition-colors hover:bg-surface-muted hover:text-content focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
            >
                <span class="flex items-center gap-3"
                    >-
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
                <Badge v-if="stats.unreadReportsCount > 0" color="info">{{ stats.unreadReportsCount }} baru</Badge>
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
            <!-- HeroBanner SIM-BPD Venice Blue (DESIGN.md §6C: rounded-2xl bg-primary text-content-inverse p-6, tombol di atas banner memakai secondary putih) -->
            <section
                class="rounded-2xl bg-primary p-6 text-content-inverse shadow-xs"
                aria-labelledby="heading-hero-bpd"
            >
                <div class="flex flex-col justify-between gap-6 lg:flex-row lg:items-center">
                    <div class="space-y-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                class="inline-flex items-center gap-2 rounded-full border border-stroke-strong bg-primary-hover px-3 py-1 text-sm font-semibold text-content-inverse"
                            >
                                <span
                                    :class="[
                                        'h-2 w-2 rounded-full',
                                        stats.inReviewCount > 0 ? 'bg-warning-solid' : 'bg-positive-solid',
                                    ]"
                                    aria-hidden="true"
                                />
                                <span>SK-BPD Aktif &middot; TA {{ currentBudgetYear }}</span>
                            </span>
                            <span
                                v-if="village.regency"
                                class="inline-flex items-center gap-1.5 rounded-full bg-primary-hover px-3 py-1 text-sm font-medium text-content-inverse"
                            >
                                <svg
                                    class="h-4 w-4 text-content-inverse"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    aria-hidden="true"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="1.8"
                                        d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"
                                    />
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="1.8"
                                        d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"
                                    />
                                </svg>
                                <span>Kab. {{ village.regency }}</span>
                            </span>
                            <span
                                class="inline-flex items-center gap-1.5 rounded-full bg-primary-hover px-3 py-1 text-sm font-medium text-content-inverse tabular-nums"
                            >
                                <span>Ambang tinjauan: &ge; {{ threshold }} selisih valid</span>
                            </span>
                        </div>

                        <div>
                            <h1 id="heading-hero-bpd" class="text-2xl font-bold text-content-inverse sm:text-3xl">
                                Pengawasan BPD {{ village.name }}
                            </h1>
                            <p class="mt-1 max-w-2xl text-sm font-medium text-content-inverse/90">
                                Pantau ambang siklus laporan fisik, deviasi standar harga kabupaten, dan penerbitan
                                berita acara hasil musyawarah ke papan publik.
                            </p>
                        </div>
                    </div>

                    <!-- Pintasan Hero memakai varian secondary putih agar tetap satu primary per layar (DESIGN.md §6C) -->
                    <div class="flex flex-wrap items-center gap-3">
                        <Button variant="secondary" href="/bpd/reports">
                            <svg
                                class="h-4 w-4 text-primary"
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
                            <span>Kotak warga ({{ stats.unreadReportsCount }} baru)</span>
                        </Button>
                        <Button variant="secondary" :href="`/d/${village.slug}`">
                            <span>Papan publik</span>
                        </Button>
                    </div>
                </div>
            </section>

            <!-- Alerts Status Siklus & Laporan Baru -->
            <div v-if="stats.inReviewCount > 0 || stats.unreadReportsCount > 0" class="flex flex-col gap-3">
                <Alert
                    v-if="stats.inReviewCount > 0"
                    color="warning"
                    title="Ambang siklus tercapai — butuh sidang pleno BPD"
                >
                    Terdapat {{ stats.inReviewCount }} proyek yang telah mencapai &ge; {{ threshold }} laporan selisih
                    valid dari warga berbeda. Pilih proyek di tabel bawah untuk menerbitkan keputusan berita acara BPD.
                </Alert>
                <Alert v-if="stats.unreadReportsCount > 0" color="info" title="Laporan Cek Spek warga belum dibuka">
                    Ada {{ stats.unreadReportsCount }} laporan fisik warga baru. Buka halaman Kotak warga untuk
                    memeriksa bukti foto dan memberikan verifikasi valid atau tidak valid.
                </Alert>
            </div>

            <!-- 4 KpiCard sebagai Filter Sekali-Klik (DESIGN.md §6 KpiCard: Card rounded-2xl + ikon SVG status + angka text-3xl font-bold tabular-nums + badge status + aria-pressed) -->
            <section aria-labelledby="heading-kpi-bpd">
                <h2 id="heading-kpi-bpd" class="sr-only">Metrik pengawasan BPD</h2>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <!-- KPI 1: Proyek Terbit -->
                    <button
                        type="button"
                        :aria-pressed="projectFilter === 'all'"
                        :class="[
                            'flex flex-col justify-between rounded-2xl border p-6 text-left transition-colors',
                            projectFilter === 'all'
                                ? 'border-primary bg-surface shadow-xs'
                                : 'border-stroke bg-surface hover:border-stroke hover:bg-surface-muted/40',
                        ]"
                        @click="projectFilter = 'all'"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary-subtle text-primary"
                                aria-hidden="true"
                            >
                                <svg
                                    class="h-5 w-5"
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
                            <Badge color="neutral">Terpublikasi</Badge>
                        </div>
                        <div class="mt-4">
                            <p class="text-3xl font-bold text-content tabular-nums">{{ stats.totalProjects }}</p>
                            <p class="mt-1 text-sm font-semibold text-content-secondary">Proyek fisik terbit</p>
                        </div>
                    </button>

                    <!-- KPI 2: Butuh Sidang Pleno (In Review) -->
                    <button
                        type="button"
                        :aria-pressed="projectFilter === 'in_review'"
                        :class="[
                            'flex flex-col justify-between rounded-2xl border p-6 text-left transition-colors',
                            projectFilter === 'in_review'
                                ? 'border-warning-solid bg-surface shadow-xs'
                                : 'border-stroke bg-surface hover:border-stroke hover:bg-surface-muted/40',
                        ]"
                        @click="toggleKpiFilter('in_review')"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-xl bg-warning-subtle text-warning"
                                aria-hidden="true"
                            >
                                <svg
                                    class="h-5 w-5"
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
                                {{ stats.inReviewCount > 0 ? 'Butuh pleno' : 'Terkendali' }}
                            </Badge>
                        </div>
                        <div class="mt-4">
                            <p
                                :class="[
                                    'text-3xl font-bold tabular-nums',
                                    stats.inReviewCount > 0 ? 'text-warning' : 'text-content',
                                ]"
                            >
                                {{ stats.inReviewCount }}
                            </p>
                            <p class="mt-1 text-sm font-semibold text-content-secondary">Dalam tinjauan BPD</p>
                        </div>
                    </button>

                    <!-- KPI 3: Deviasi Standar Harga (Di Atas SSH) -->
                    <button
                        type="button"
                        :aria-pressed="projectFilter === 'above_standard'"
                        :class="[
                            'flex flex-col justify-between rounded-2xl border p-6 text-left transition-colors',
                            projectFilter === 'above_standard'
                                ? 'border-negative-solid bg-surface shadow-xs'
                                : 'border-stroke bg-surface hover:border-stroke hover:bg-surface-muted/40',
                        ]"
                        @click="toggleKpiFilter('above_standard')"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-xl bg-negative-subtle text-negative"
                                aria-hidden="true"
                            >
                                <svg
                                    class="h-5 w-5"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"
                                    />
                                </svg>
                            </div>
                            <Badge :color="stats.aboveStandardProjectsCount > 0 ? 'negative' : 'positive'">
                                {{ stats.aboveStandardProjectsCount > 0 ? 'Di atas SSH' : 'Sesuai SSH' }}
                            </Badge>
                        </div>
                        <div class="mt-4">
                            <p
                                :class="[
                                    'text-3xl font-bold tabular-nums',
                                    stats.aboveStandardProjectsCount > 0 ? 'text-negative' : 'text-content',
                                ]"
                            >
                                {{ stats.aboveStandardProjectsCount }}
                            </p>
                            <p class="mt-1 text-sm font-semibold text-content-secondary">Proyek di atas SSH</p>
                        </div>
                    </button>

                    <!-- KPI 4: Tinjauan Selesai (Berita Acara Terbit) -->
                    <button
                        type="button"
                        :aria-pressed="projectFilter === 'reviewed'"
                        :class="[
                            'flex flex-col justify-between rounded-2xl border p-6 text-left transition-colors',
                            projectFilter === 'reviewed'
                                ? 'border-info-solid bg-surface shadow-xs'
                                : 'border-stroke bg-surface hover:border-stroke hover:bg-surface-muted/40',
                        ]"
                        @click="toggleKpiFilter('reviewed')"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-xl bg-info-subtle text-info"
                                aria-hidden="true"
                            >
                                <svg
                                    class="h-5 w-5"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                                    />
                                </svg>
                            </div>
                            <Badge color="info">Berita acara</Badge>
                        </div>
                        <div class="mt-4">
                            <p class="text-3xl font-bold text-info tabular-nums">{{ stats.reviewedCount }}</p>
                            <p class="mt-1 text-sm font-semibold text-content-secondary">Tinjauan selesai</p>
                        </div>
                    </button>
                </div>
            </section>

            <!-- Konten Utama: Tabel Matriks Proyek (Kiri) & Panel Sidang / Detail Proyek (Kanan) -->
            <div id="daftar-proyek-bpd" class="grid items-start gap-6 lg:grid-cols-12">
                <!-- Kolom Kiri: Daftar & Matriks Proyek Fisik Desa -->
                <section class="flex flex-col gap-6 lg:col-span-7" aria-labelledby="heading-daftar-proyek">
                    <div class="rounded-2xl border border-stroke bg-surface p-6 shadow-xs">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h2 id="heading-daftar-proyek" class="text-xl font-semibold text-content">
                                    Matriks pengawasan proyek desa
                                </h2>
                                <p class="mt-0.5 text-sm text-content-secondary">
                                    Pilih baris proyek untuk memeriksa rincian deviasi SSH, revisi CCO, dan keputusan
                                    siklus.
                                </p>
                            </div>
                            <Badge color="neutral">{{ filteredProjects.length }} / {{ projects.length }} proyek</Badge>
                        </div>

                        <!-- Pencarian & Filter Chip (DESIGN.md §6 Input & §6A Filter Chip) -->
                        <div v-if="projects.length > 0" class="mt-4 space-y-3 border-t border-stroke pt-4">
                            <div>
                                <label for="bpd-project-search" class="block text-sm font-medium text-content">
                                    Cari proyek fisik
                                </label>
                                <input
                                    id="bpd-project-search"
                                    v-model="projectSearch"
                                    type="search"
                                    placeholder="Ketik nama proyek desa..."
                                    class="mt-1 h-11 w-full rounded-lg border border-stroke-strong bg-surface px-3 text-base text-content focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                                />
                            </div>

                            <div class="flex flex-wrap gap-2" role="group" aria-label="Filter status proyek">
                                <button
                                    v-for="tab in projectFilterTabs"
                                    :key="tab.key"
                                    type="button"
                                    :aria-pressed="projectFilter === tab.key"
                                    :class="[
                                        'inline-flex min-h-11 items-center gap-2 rounded-full border px-3.5 py-1.5 text-sm font-semibold transition-colors',
                                        projectFilter === tab.key
                                            ? 'border-primary bg-primary-subtle text-primary'
                                            : 'border-stroke bg-surface-muted text-content-secondary hover:border-stroke-strong hover:text-content',
                                    ]"
                                    @click="projectFilter = tab.key"
                                >
                                    <span>{{ tab.label }}</span>
                                    <span class="tabular-nums">({{ tab.count }})</span>
                                </button>
                            </div>
                        </div>

                        <div v-if="projects.length === 0" class="mt-4">
                            <EmptyState
                                title="Belum ada proyek terbit di desa ini"
                                description="Proyek yang diterbitkan oleh Pemerintah Desa akan langsung tercatat di matriks pengawasan BPD."
                                illustration="plang-empty"
                            />
                        </div>

                        <div
                            v-else-if="filteredProjects.length === 0"
                            class="mt-4 rounded-xl border border-stroke bg-surface-muted p-6 text-center text-sm text-content-secondary"
                        >
                            Tidak ada proyek yang sesuai dengan kata kunci atau filter terpilih.
                        </div>

                        <template v-else>
                            <!-- Tabel Matriks Desktop (DESIGN.md §6A: rounded-2xl border-stroke shadow-xs, th bg-surface-subtle/80 uppercase tracking-wider text-xs) -->
                            <div
                                class="mt-4 hidden overflow-hidden rounded-2xl border border-stroke bg-surface shadow-xs md:block"
                            >
                                <table class="w-full text-sm">
                                    <caption class="sr-only">
                                        Matriks pengawasan proyek fisik desa
                                    </caption>
                                    <thead class="sticky top-0">
                                        <tr class="border-b border-stroke bg-surface-subtle/80">
                                            <th
                                                scope="col"
                                                class="px-4 py-3.5 text-left text-xs font-semibold tracking-wider text-content-secondary uppercase"
                                            >
                                                Proyek fisik
                                            </th>
                                            <th
                                                scope="col"
                                                class="px-4 py-3.5 text-right text-xs font-semibold tracking-wider text-content-secondary uppercase"
                                            >
                                                Item &gt; SSH
                                            </th>
                                            <th
                                                scope="col"
                                                class="px-4 py-3.5 text-right text-xs font-semibold tracking-wider text-content-secondary uppercase"
                                            >
                                                Selisih
                                            </th>
                                            <th
                                                scope="col"
                                                class="px-4 py-3.5 text-right text-xs font-semibold tracking-wider text-content-secondary uppercase"
                                            >
                                                Sesuai
                                            </th>
                                            <th
                                                scope="col"
                                                class="px-4 py-3.5 text-left text-xs font-semibold tracking-wider text-content-secondary uppercase"
                                            >
                                                Status siklus
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="p in filteredProjects"
                                            :key="p.id"
                                            tabindex="0"
                                            :class="[
                                                'cursor-pointer border-b border-stroke transition-colors last:border-b-0',
                                                selectedProject?.id === p.id
                                                    ? 'border-l-4 border-l-primary bg-primary-subtle/40'
                                                    : p.above_standard_count > 0 || p.public_status === 'in_review'
                                                      ? 'bg-warning-subtle/25 hover:bg-surface-muted/60'
                                                      : 'hover:bg-surface-muted/60',
                                            ]"
                                            @click="selectProject(p.id)"
                                            @keydown.enter.prevent="selectProject(p.id)"
                                            @keydown.space.prevent="selectProject(p.id)"
                                        >
                                            <th
                                                scope="row"
                                                class="px-4 py-3.5 text-left align-middle font-semibold text-content"
                                            >
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <span
                                                        :class="
                                                            selectedProject?.id === p.id
                                                                ? 'text-primary'
                                                                : 'text-content'
                                                        "
                                                    >
                                                        {{ p.name }}
                                                    </span>
                                                    <Badge v-if="p.unread_count > 0" color="info">
                                                        {{ p.unread_count }} baru
                                                    </Badge>
                                                </div>
                                                <div class="mt-1.5 flex items-center gap-2">
                                                    <div
                                                        class="h-1.5 w-20 overflow-hidden rounded-full bg-surface-muted"
                                                    >
                                                        <div
                                                            :class="[
                                                                'h-full rounded-full',
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
                                                    <span
                                                        class="text-xs font-normal text-content-tertiary tabular-nums"
                                                    >
                                                        Ambang {{ p.valid_mismatch_count }}/{{ threshold }}
                                                    </span>
                                                </div>
                                            </th>
                                            <td class="px-4 py-3.5 text-right align-middle tabular-nums">
                                                <Badge v-if="p.above_standard_count > 0" color="negative">
                                                    {{ p.above_standard_count }} item
                                                </Badge>
                                                <span v-else class="text-sm text-content-secondary">0</span>
                                            </td>
                                            <td class="px-4 py-3.5 text-right align-middle tabular-nums">
                                                <span
                                                    :class="
                                                        p.valid_mismatch_count > 0
                                                            ? 'font-semibold text-negative'
                                                            : 'text-content-secondary'
                                                    "
                                                >
                                                    {{ p.valid_mismatch_count }}/{{ threshold }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3.5 text-right align-middle tabular-nums">
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
                                            <td class="px-4 py-3.5 align-middle">
                                                <Badge v-if="p.public_status === 'in_review'" color="warning">
                                                    Dalam tinjauan BPD
                                                </Badge>
                                                <Badge v-else-if="p.public_status === 'reviewed'" color="info">
                                                    Ditinjau BPD
                                                </Badge>
                                                <span v-else class="text-sm text-content-secondary"
                                                    >Aktif dipantau</span
                                                >
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Daftar Kartu Mobile (< 768px) gaya ProjectItem + 3 MetricPill (DESIGN.md §6 & §6A) -->
                            <div class="mt-4 flex flex-col gap-3 md:hidden">
                                <button
                                    v-for="p in filteredProjects"
                                    :key="p.id"
                                    type="button"
                                    :class="[
                                        'flex w-full flex-col gap-3 rounded-xl border border-l-4 p-4 text-left transition-colors',
                                        selectedProject?.id === p.id
                                            ? 'border-primary border-l-primary bg-primary-subtle/40'
                                            : p.public_status === 'in_review'
                                              ? 'border-stroke border-l-warning-solid bg-surface hover:bg-surface-muted/60'
                                              : p.public_status === 'reviewed'
                                                ? 'border-stroke border-l-positive-solid bg-surface hover:bg-surface-muted/60'
                                                : 'border-stroke border-l-primary bg-surface hover:bg-surface-muted/60',
                                    ]"
                                    @click="selectProject(p.id)"
                                >
                                    <div class="flex items-start justify-between gap-2">
                                        <span class="text-base font-semibold text-content">{{ p.name }}</span>
                                        <Badge v-if="p.public_status === 'in_review'" color="warning">
                                            Dalam tinjauan
                                        </Badge>
                                        <Badge v-else-if="p.public_status === 'reviewed'" color="info">
                                            Ditinjau BPD
                                        </Badge>
                                    </div>

                                    <!-- 3 MetricPill: Selisih, Kesesuaian Spek, Kesesuaian SSH (DESIGN.md §6 MetricPill) -->
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span
                                            :class="[
                                                'rounded-full border px-2.5 py-0.5 text-sm font-medium tabular-nums',
                                                p.valid_mismatch_count > 0
                                                    ? 'border-negative-border bg-negative-subtle text-negative'
                                                    : 'border-stroke bg-surface-muted text-content-secondary',
                                            ]"
                                        >
                                            Selisih {{ p.valid_mismatch_count }}/{{ threshold }}
                                        </span>
                                        <span
                                            :class="[
                                                'rounded-full border px-2.5 py-0.5 text-sm font-medium tabular-nums',
                                                p.match_count > 0
                                                    ? 'border-positive-border bg-positive-subtle text-positive'
                                                    : 'border-stroke bg-surface-muted text-content-secondary',
                                            ]"
                                        >
                                            Sesuai {{ p.match_count }}
                                        </span>
                                        <span
                                            :class="[
                                                'rounded-full border px-2.5 py-0.5 text-sm font-medium tabular-nums',
                                                p.above_standard_count > 0
                                                    ? 'border-negative-border bg-negative-subtle text-negative'
                                                    : 'border-stroke bg-surface-muted text-content-secondary',
                                            ]"
                                        >
                                            {{
                                                p.above_standard_count > 0
                                                    ? `${p.above_standard_count} di atas SSH`
                                                    : 'Sesuai SSH'
                                            }}
                                        </span>
                                    </div>
                                </button>
                            </div>
                        </template>
                    </div>

                    <!-- Ringkasan Mekanisme Siklus (SummaryList ringkas anti-artikel, DESIGN.md §1 & §6) -->
                    <div class="rounded-2xl border border-stroke bg-surface p-6">
                        <h3 class="text-base font-semibold text-content">Ketentuan siklus tinjauan BPD</h3>
                        <dl class="mt-3 divide-y divide-stroke text-sm">
                            <div class="flex flex-col gap-1 py-2.5 sm:flex-row sm:justify-between">
                                <dt class="font-medium text-content-secondary">Di bawah ambang</dt>
                                <dd class="font-medium text-content sm:text-right">
                                    &lt; {{ threshold }} selisih valid &mdash; tanpa lencana di halaman publik
                                </dd>
                            </div>
                            <div class="flex flex-col gap-1 py-2.5 sm:flex-row sm:justify-between">
                                <dt class="font-medium text-content-secondary">Sidang pleno aktif</dt>
                                <dd class="font-medium text-warning sm:text-right">
                                    &ge; {{ threshold }} selisih valid (akun &amp; perangkat berbeda) &mdash; revisi RAB
                                    dibekukan
                                </dd>
                            </div>
                            <div class="flex flex-col gap-1 pt-2.5 sm:flex-row sm:justify-between">
                                <dt class="font-medium text-content-secondary">Berita acara terbit</dt>
                                <dd class="font-medium text-info sm:text-right">
                                    Tampil permanen di papan publik &mdash; siklus baru mulai dari 0
                                </dd>
                            </div>
                        </dl>
                    </div>
                </section>

                <!-- Kolom Kanan: Kartu Ambang Merino & Panel Detail / Keputusan BPD -->
                <section v-if="selectedProject" class="flex flex-col gap-6 lg:col-span-5">
                    <!-- Kartu Ambang Tinjauan Merino (DESIGN.md §6C: bg-surface-subtle Merino, rounded-2xl) -->
                    <div class="rounded-2xl border border-stroke bg-surface-subtle p-6">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="text-sm font-semibold text-content">Ambang tinjauan siklus berjalan</span>
                            <Badge v-if="selectedProject.public_status === 'in_review'" color="warning">
                                Sidang pleno diperlukan
                            </Badge>
                            <Badge v-else-if="selectedProject.public_status === 'reviewed'" color="info">
                                Berita acara terbit
                            </Badge>
                            <span v-else class="text-sm font-semibold text-primary tabular-nums">
                                {{ selectedValidMismatchCount }} / {{ threshold }} selisih valid
                            </span>
                        </div>

                        <div class="mt-3 flex items-baseline justify-between gap-3">
                            <p class="text-3xl font-bold text-content tabular-nums">
                                {{ selectedValidMismatchCount }}
                                <span class="text-lg font-semibold text-content-secondary">/ {{ threshold }}</span>
                            </p>
                            <p class="text-sm text-content-secondary tabular-nums">
                                {{ selectedProject.reports.length }} total laporan warga
                                <template v-if="selectedUnreadCount > 0">
                                    &middot;
                                    <strong class="font-semibold text-primary">{{ selectedUnreadCount }} baru</strong>
                                </template>
                            </p>
                        </div>

                        <!-- Pill Progres Ambang -->
                        <div class="mt-3 grid grid-cols-3 gap-2">
                            <div
                                v-for="step in thresholdSteps"
                                :key="step"
                                :class="[
                                    'h-2.5 rounded-full transition-colors',
                                    selectedValidMismatchCount >= step
                                        ? selectedValidMismatchCount >= threshold
                                            ? 'bg-warning-solid'
                                            : 'bg-primary'
                                        : 'bg-surface',
                                ]"
                            />
                        </div>

                        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-stroke pt-4">
                            <span class="text-sm text-content-secondary">
                                Verifikasi bukti foto &amp; balas warga di halaman tersendiri.
                            </span>
                            <Button
                                :variant="!hasOutcomePrimaryButton && !isDialogOpen ? 'primary' : 'secondary'"
                                size="sm"
                                :href="`/bpd/reports?project=${selectedProject.id}`"
                            >
                                <span>Buka kotak warga proyek</span>
                            </Button>
                        </div>
                    </div>

                    <!-- Panel Detail Proyek & Sidang Pleno BPD (DESIGN.md §6C) -->
                    <div class="rounded-2xl border border-stroke bg-surface p-6 shadow-xs">
                        <div class="divide-y divide-stroke">
                            <!-- Header Proyek Terpilih -->
                            <div class="space-y-3 pb-5">
                                <div class="flex flex-wrap items-center gap-2">
                                    <Badge v-if="selectedProject.public_status === 'in_review'" color="warning">
                                        {{
                                            selectedProject.in_review_since
                                                ? publicStatus.in_review(formatDate(selectedProject.in_review_since))
                                                : 'Dalam tinjauan BPD'
                                        }}
                                    </Badge>
                                    <Badge v-else-if="selectedProject.public_status === 'reviewed'" color="info">
                                        Ditinjau BPD
                                    </Badge>
                                </div>

                                <h2 class="text-xl font-semibold text-content">{{ selectedProject.name }}</h2>

                                <div class="flex flex-wrap items-center gap-2 pt-1">
                                    <Button variant="secondary" size="sm" :href="selectedProject.public_url">
                                        <span>Lihat halaman publik</span>
                                    </Button>
                                    <Button variant="secondary" size="sm" :href="selectedProject.document_url">
                                        <span>Dokumen RAB asli</span>
                                    </Button>
                                </div>
                            </div>

                            <!-- Perbandingan Revisi Pasca-Terbit / CCO: Nilai lama -> Nilai baru (DESIGN.md §6C & §6B) -->
                            <div
                                v-if="selectedProject.changes && selectedProject.changes.length > 0"
                                class="space-y-3 py-5"
                            >
                                <div class="flex items-center justify-between gap-2">
                                    <h3 class="text-base font-semibold text-content">
                                        Revisi pasca-terbit / CCO ({{ selectedProject.changes.length }})
                                    </h3>
                                    <Button type="button" variant="ghost" size="sm" @click="openCcoDialog">
                                        <span>Rincian tabel</span>
                                    </Button>
                                </div>

                                <div class="space-y-2">
                                    <div
                                        v-for="change in selectedProject.changes.slice(0, 3)"
                                        :key="change.id"
                                        class="rounded-xl border border-stroke bg-surface-muted/50 p-3 text-sm"
                                    >
                                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                                            <span class="font-semibold text-content">
                                                {{ change.item_label }} ({{ change.field }})
                                            </span>
                                            <span class="text-xs text-content-tertiary">
                                                {{ formatDate(change.changed_at) }}
                                            </span>
                                        </div>
                                        <p class="mt-1 font-medium text-content tabular-nums">
                                            <span class="text-content-secondary">{{ change.old_value }}</span>
                                            <span class="mx-1.5 text-primary" aria-hidden="true">&rarr;</span>
                                            <span class="font-semibold text-content">{{ change.new_value }}</span>
                                        </p>
                                        <p v-if="change.reason" class="mt-1 text-sm text-content-secondary">
                                            Alasan: &ldquo;{{ change.reason }}&rdquo;
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Item RAB di Atas Standar Harga / SSH (DESIGN.md §2 & §9: sebut sumber angka di dekat angka) -->
                            <div
                                v-if="
                                    selectedProject.above_standard_items &&
                                    selectedProject.above_standard_items.length > 0
                                "
                                class="space-y-3 py-5"
                            >
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div>
                                        <h3 class="text-base font-semibold text-content">
                                            Item RAB di atas SSH ({{ selectedProject.above_standard_items.length }})
                                        </h3>
                                        <p class="text-xs text-content-tertiary">
                                            Standar harga
                                            {{ village.regency ? `Kab. ${village.regency}` : 'kabupaten' }}
                                            {{ currentBudgetYear }}
                                        </p>
                                    </div>
                                    <Badge color="negative">Perlu perhatian</Badge>
                                </div>

                                <div class="max-h-60 space-y-2 overflow-y-auto pr-1">
                                    <div
                                        v-for="item in selectedProject.above_standard_items"
                                        :key="item.id"
                                        class="rounded-xl border border-stroke bg-warning-subtle/25 p-3 text-sm"
                                    >
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <span class="font-semibold text-content">{{ item.name }}</span>
                                            <Badge color="negative">
                                                <template v-if="item.unit_price_rp !== null">
                                                    {{ formatRupiah(item.unit_price_rp) }}
                                                </template>
                                                <template v-if="item.over_pct !== null">
                                                    &middot; +{{ item.over_pct }}%
                                                </template>
                                            </Badge>
                                        </div>
                                        <p v-if="item.explanation" class="mt-1.5 text-sm text-content-secondary">
                                            Keterangan desa: &ldquo;{{ item.explanation }}&rdquo;
                                        </p>
                                        <p v-else class="mt-1.5 text-sm text-content-tertiary">
                                            {{ priceLabel.above_standard(item.over_pct ?? 0) }} &mdash; belum ada
                                            keterangan tertulis Pemerintah Desa.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Form Keputusan Sidang Pleno BPD memakai RadioCard + Kolom Berita Acara + Satu Tombol Primary (DESIGN.md §6 & §6C) -->
                            <form
                                v-if="selectedProject.public_status === 'in_review'"
                                class="space-y-4 pt-5"
                                @submit.prevent="submitOutcome"
                            >
                                <div>
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <h3 class="text-base font-semibold text-content">
                                            Keputusan sidang pleno &amp; berita acara BPD
                                        </h3>
                                        <Badge color="warning">Wajib diisi</Badge>
                                    </div>
                                    <p class="mt-1 text-sm text-content-secondary">
                                        Pilih ketetapan hasil pengecekan fisik. Berita acara tampil resmi di papan
                                        publik.
                                    </p>
                                </div>

                                <!-- RadioCard (DESIGN.md §6 & §6C: rounded-xl border, terpilih border-primary bg-primary-subtle) -->
                                <div
                                    class="grid grid-cols-1 gap-3"
                                    role="radiogroup"
                                    aria-label="Pilihan ketetapan hasil tinjauan BPD"
                                >
                                    <button
                                        type="button"
                                        role="radio"
                                        :aria-checked="outcomeForm.result === 'followed_up'"
                                        :class="[
                                            'flex min-h-11 items-start gap-3 rounded-xl border p-4 text-left transition-colors',
                                            outcomeForm.result === 'followed_up'
                                                ? 'border-primary bg-primary-subtle'
                                                : 'border-stroke bg-surface hover:border-stroke hover:bg-surface-muted/50',
                                        ]"
                                        @click="outcomeForm.result = 'followed_up'"
                                    >
                                        <span
                                            :class="[
                                                'mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2',
                                                outcomeForm.result === 'followed_up'
                                                    ? 'border-primary bg-primary'
                                                    : 'border-stroke-strong bg-surface',
                                            ]"
                                            aria-hidden="true"
                                        >
                                            <span
                                                v-if="outcomeForm.result === 'followed_up'"
                                                class="h-2 w-2 rounded-full bg-content-inverse"
                                            />
                                        </span>
                                        <div>
                                            <span class="block text-sm font-semibold text-content">
                                                {{ reviewOutcome.followed_up }}
                                            </span>
                                            <span class="mt-0.5 block text-sm text-content-secondary">
                                                Tindak lanjut perbaikan disepakati bersama Pemerintah Desa
                                            </span>
                                        </div>
                                    </button>

                                    <button
                                        type="button"
                                        role="radio"
                                        :aria-checked="outcomeForm.result === 'no_mismatch'"
                                        :class="[
                                            'flex min-h-11 items-start gap-3 rounded-xl border p-4 text-left transition-colors',
                                            outcomeForm.result === 'no_mismatch'
                                                ? 'border-primary bg-primary-subtle'
                                                : 'border-stroke bg-surface hover:border-stroke hover:bg-surface-muted/50',
                                        ]"
                                        @click="outcomeForm.result = 'no_mismatch'"
                                    >
                                        <span
                                            :class="[
                                                'mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2',
                                                outcomeForm.result === 'no_mismatch'
                                                    ? 'border-primary bg-primary'
                                                    : 'border-stroke-strong bg-surface',
                                            ]"
                                            aria-hidden="true"
                                        >
                                            <span
                                                v-if="outcomeForm.result === 'no_mismatch'"
                                                class="h-2 w-2 rounded-full bg-content-inverse"
                                            />
                                        </span>
                                        <div>
                                            <span class="block text-sm font-semibold text-content">
                                                {{ reviewOutcome.no_mismatch }}
                                            </span>
                                            <span class="mt-0.5 block text-sm text-content-secondary">
                                                Ukuran fisik lapangan dinyatakan sesuai spesifikasi RAB
                                            </span>
                                        </div>
                                    </button>
                                </div>

                                <!-- Kolom Berita Acara (DESIGN.md §6 Textarea) -->
                                <div>
                                    <label for="bpd-outcome-note" class="block text-sm font-medium text-content">
                                        Ringkasan berita acara hasil tinjauan (minimal 30 huruf)
                                    </label>
                                    <textarea
                                        id="bpd-outcome-note"
                                        v-model="outcomeForm.note"
                                        rows="3"
                                        placeholder="Catat tanggal cek lapangan, pihak yang hadir, dan kesepakatan tindak lanjut..."
                                        :aria-describedby="outcomeForm.errors.note ? 'bpd-outcome-error' : undefined"
                                        :class="[
                                            'mt-1 w-full rounded-lg border bg-surface p-3 text-base text-content focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary',
                                            outcomeForm.errors.note ? 'border-negative-solid' : 'border-stroke-strong',
                                        ]"
                                    />
                                    <p
                                        v-if="outcomeForm.errors.note"
                                        id="bpd-outcome-error"
                                        class="mt-1 text-sm text-negative"
                                    >
                                        {{ outcomeForm.errors.note }}
                                    </p>
                                </div>

                                <Button
                                    type="submit"
                                    :variant="isDialogOpen ? 'secondary' : 'primary'"
                                    class="w-full"
                                    :loading="outcomeForm.processing"
                                >
                                    <span>Terbitkan berita acara tinjauan</span>
                                </Button>
                            </form>
                        </div>
                    </div>

                    <!-- Riwayat Berita Acara Siklus Selesai (DESIGN.md §2 info = Tinjauan selesai, Berita Acara terbit) -->
                    <div
                        v-if="selectedProject.closed_cycles.length > 0"
                        class="rounded-2xl border border-stroke bg-surface p-6"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="text-base font-semibold text-content">Arsip berita acara tinjauan</h3>
                            <Badge color="info">{{ selectedProject.closed_cycles.length }} selesai</Badge>
                        </div>

                        <div class="mt-4 space-y-3">
                            <div
                                v-for="cycle in selectedProject.closed_cycles"
                                :key="cycle.id"
                                class="space-y-3 rounded-xl border border-stroke bg-surface-muted/40 p-4 text-sm"
                            >
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <Badge color="info">
                                        {{ cycle.result ? reviewOutcome[cycle.result] : '-' }}
                                    </Badge>
                                    <span v-if="cycle.closed_at" class="text-xs text-content-tertiary tabular-nums">
                                        {{ formatDateTime(cycle.closed_at) }}
                                    </span>
                                </div>
                                <p class="text-sm text-content">{{ cycle.note }}</p>

                                <div
                                    v-if="cycle.notes.length > 0"
                                    class="space-y-2 border-t border-stroke pt-3 text-sm"
                                >
                                    <p class="font-semibold text-content-secondary">Catatan tindak lanjut susulan:</p>
                                    <div v-for="n in cycle.notes" :key="n.id" class="text-sm text-content">
                                        <span v-if="n.created_at" class="text-xs text-content-tertiary tabular-nums">
                                            {{ formatDateTime(n.created_at) }} &mdash;
                                        </span>
                                        {{ n.note }}
                                    </div>
                                </div>

                                <div class="pt-1">
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        size="sm"
                                        @click="openFollowUpDialog(cycle.id)"
                                    >
                                        <span>Tambah catatan susulan</span>
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <!-- Dialog Native Catatan Susulan (DESIGN.md §6B: <dialog> native, rounded-2xl border-stroke bg-surface shadow-xl, max-w-lg, footer bg-surface-subtle/50) -->
        <dialog
            ref="followUpDialogRef"
            class="m-auto w-full max-w-lg overflow-hidden rounded-2xl border border-stroke bg-surface p-0 text-content shadow-xl"
            aria-labelledby="dialog-followup-title"
            @close="isDialogOpen = false"
            @click="handleDialogBackdropClick($event, closeFollowUpDialog)"
        >
            <form @submit.prevent="submitFollowUp">
                <div class="flex items-center justify-between border-b border-stroke px-6 py-4">
                    <div class="flex items-center gap-2.5">
                        <h3 id="dialog-followup-title" class="text-base font-bold text-content">
                            Tambah catatan susulan
                        </h3>
                        <Badge color="info">Tampil publik</Badge>
                    </div>
                    <button
                        type="button"
                        class="inline-flex h-11 w-11 items-center justify-center rounded-lg text-content-secondary transition-colors hover:bg-surface-muted hover:text-content"
                        aria-label="Tutup"
                        @click="closeFollowUpDialog"
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
                    <div>
                        <label for="dialog-followup-note" class="block text-sm font-medium text-content">
                            Isi catatan tindak lanjut bertanggal
                        </label>
                        <textarea
                            id="dialog-followup-note"
                            v-model="followUpForm.note"
                            rows="4"
                            placeholder="Contoh: Pengecoran ulang bahu jalan sepanjang 15 meter telah selesai diperiksa..."
                            :aria-describedby="followUpForm.errors.note ? 'dialog-followup-error' : undefined"
                            :class="[
                                'mt-1 w-full rounded-lg border bg-surface p-3 text-base text-content focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary',
                                followUpForm.errors.note ? 'border-negative-solid' : 'border-stroke-strong',
                            ]"
                        />
                        <p
                            v-if="followUpForm.errors.note"
                            id="dialog-followup-error"
                            class="mt-1 text-sm text-negative"
                        >
                            {{ followUpForm.errors.note }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-stroke bg-surface-subtle/50 px-6 py-4">
                    <Button type="button" variant="secondary" @click="closeFollowUpDialog">
                        <span>Batal</span>
                    </Button>
                    <Button type="submit" variant="primary" :loading="followUpForm.processing">
                        <span>Simpan catatan susulan</span>
                    </Button>
                </div>
            </form>
        </dialog>

        <!-- Dialog Native Rincian Revisi CCO (DESIGN.md §6B: max-w-3xl tabel rincian, shadow-xl) -->
        <dialog
            ref="ccoDialogRef"
            class="m-auto w-full max-w-3xl overflow-hidden rounded-2xl border border-stroke bg-surface p-0 text-content shadow-xl"
            aria-labelledby="dialog-cco-title"
            @close="isDialogOpen = false"
            @click="handleDialogBackdropClick($event, closeCcoDialog)"
        >
            <div class="flex items-center justify-between border-b border-stroke px-6 py-4">
                <div class="flex items-center gap-2.5">
                    <h3 id="dialog-cco-title" class="text-base font-bold text-content">
                        Rincian revisi pasca-terbit (CCO)
                    </h3>
                    <Badge color="info">{{ selectedProject?.changes?.length ?? 0 }} perubahan</Badge>
                </div>
                <button
                    type="button"
                    class="inline-flex h-11 w-11 items-center justify-center rounded-lg text-content-secondary transition-colors hover:bg-surface-muted hover:text-content"
                    aria-label="Tutup"
                    @click="closeCcoDialog"
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
                <div
                    v-if="selectedProject?.changes && selectedProject.changes.length > 0"
                    class="overflow-hidden rounded-2xl border border-stroke bg-surface shadow-xs"
                >
                    <table class="w-full text-sm">
                        <caption class="sr-only">
                            Daftar perubahan pasca-terbit proyek
                        </caption>
                        <thead>
                            <tr class="border-b border-stroke bg-surface-subtle/80">
                                <th
                                    scope="col"
                                    class="px-4 py-3 text-left text-xs font-semibold tracking-wider text-content-secondary uppercase"
                                >
                                    Komponen
                                </th>
                                <th
                                    scope="col"
                                    class="px-4 py-3 text-right text-xs font-semibold tracking-wider text-content-secondary uppercase"
                                >
                                    Nilai lama &rarr; baru
                                </th>
                                <th
                                    scope="col"
                                    class="px-4 py-3 text-left text-xs font-semibold tracking-wider text-content-secondary uppercase"
                                >
                                    Alasan &amp; waktu
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="change in selectedProject.changes"
                                :key="change.id"
                                class="border-b border-stroke last:border-b-0"
                            >
                                <td class="px-4 py-3 align-top font-semibold text-content">
                                    {{ change.item_label }}
                                    <span class="block text-xs font-normal text-content-tertiary">
                                        Kolom: {{ change.field }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right align-top tabular-nums">
                                    <span class="text-content-secondary">{{ change.old_value }}</span>
                                    <span class="mx-1 text-primary" aria-hidden="true">&rarr;</span>
                                    <span class="font-semibold text-content">{{ change.new_value }}</span>
                                </td>
                                <td class="px-4 py-3 align-top text-content-secondary">
                                    <p v-if="change.reason">&ldquo;{{ change.reason }}&rdquo;</p>
                                    <p class="mt-0.5 text-xs text-content-tertiary tabular-nums">
                                        {{ formatDateTime(change.changed_at) }}
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 border-t border-stroke bg-surface-subtle/50 px-6 py-4">
                <Button type="button" variant="primary" @click="closeCcoDialog">
                    <span>Selesai melihat</span>
                </Button>
            </div>
        </dialog>
    </AppLayout>
</template>
