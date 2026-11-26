<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, onUnmounted, ref, watch } from 'vue';
import Alert from '@/components/ui/Alert.vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Table from '@/components/ui/Table.vue';
import PemdesLayout from '@/layouts/PemdesLayout.vue';
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
    if (!file) {
        return;
    }

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
    if (!file || !auditId) {
        return;
    }

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
    if (pollTimer) {
        return;
    }
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
    if (!ms || ms <= 0) {
        return '';
    }
    if (ms < 1000) {
        return `${ms} ms`;
    }
    return `${(ms / 1000).toFixed(1)} dtk`;
}

function auditBadgeColor(status: string): 'info' | 'warning' | 'positive' | 'negative' {
    if (status === 'reading') {
        return 'info';
    }
    if (status === 'needs_review') {
        return 'warning';
    }
    if (status === 'ready') {
        return 'positive';
    }
    return 'negative';
}

function auditBadgeText(auditItem: InProgressAuditItem): string {
    if (auditItem.status === 'reading') {
        return 'Sedang dibaca';
    }
    if (auditItem.status === 'needs_review') {
        return `${auditItem.needs_check_count} perlu dicek`;
    }
    if (auditItem.status === 'ready') {
        return 'Siap terbit';
    }
    return 'Belum terbaca';
}

const tableColumns = [
    { key: 'name', label: 'Proyek fisik', isRowHeader: true },
    { key: 'fund_source', label: 'Sumber dana' },
    { key: 'progress', label: 'Progres fisik' },
    { key: 'status', label: 'Status' },
    { key: 'actions', label: 'Aksi', align: 'right' as const },
];
</script>

<template>
    <PemdesLayout :village="village" active-nav="dashboard">
        <Head :title="`Dashboard Pemerintah Desa - ${village.name}`" />

        <template #breadcrumb>
            <Link href="/" class="transition-colors hover:text-primary">Beranda</Link>
            <span>/</span>
            <span class="font-medium text-content">Dashboard Pemerintah {{ village.name }}</span>
        </template>

        <div class="flex flex-col gap-6">
            <!-- Header -->
            <div>
                <h1 class="text-2xl font-semibold text-content md:text-3xl">Dashboard Pemerintah {{ village.name }}</h1>
                <p class="mt-1 text-sm text-content-secondary">
                    Kabupaten {{ village.regency }} &middot; Kelola dokumen RAB, penerbitan proyek, dan progres fisik.
                </p>
            </div>

            <!-- Alerts -->
            <div v-if="stats.audits_needs_review > 0 || stats.pending_reports > 0" class="flex flex-col gap-3">
                <Alert v-if="stats.audits_needs_review > 0" color="warning" title="Dokumen RAB perlu diperiksa">
                    Terdapat {{ stats.audits_needs_review }} dokumen RAB dengan baris kuning yang perlu diperiksa
                    sebelum diterbitkan.
                </Alert>
                <Alert v-if="stats.pending_reports > 0" color="info" title="Laporan warga masuk">
                    Ada {{ stats.pending_reports }} laporan Cek Spek dari warga untuk proyek desa ini.
                </Alert>
            </div>

            <!-- Upload Card (Single primary CTA on this screen) -->
            <section aria-labelledby="heading-upload-rab">
                <Card padding="app">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div class="space-y-1.5">
                            <h2 id="heading-upload-rab" class="text-base font-semibold text-content sm:text-lg">
                                Unggah dokumen RAB
                            </h2>
                            <p class="max-w-2xl text-sm text-content-secondary">
                                Unggah PDF atau foto RAB berstempel. Sistem membaca tabelnya dan membandingkan harga
                                dengan standar harga Kabupaten {{ village.regency }}.
                            </p>
                            <p v-if="quickForm.errors.file" class="text-xs font-medium text-negative">
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
                                Unggah dokumen RAB
                            </Button>

                            <Button
                                v-if="ai_reader?.sample_available !== false"
                                type="button"
                                variant="secondary"
                                size="sm"
                                :loading="quickForm.processing && quickForm.use_sample"
                                @click="submitQuickSample"
                            >
                                Uji contoh RAB Melati
                            </Button>

                            <Button variant="ghost" size="sm" href="/audits/create">Isi manual</Button>
                        </div>
                    </div>
                </Card>
            </section>

            <!-- In-Progress Audits -->
            <section
                v-if="in_progress_audits && in_progress_audits.length > 0"
                aria-labelledby="heading-dokumen-proses"
            >
                <Card padding="app">
                    <input
                        ref="replaceFileInputRef"
                        type="file"
                        accept=".pdf,application/pdf,image/jpeg,image/png,image/jpg"
                        class="hidden"
                        @change="handleReplaceAuditFileChange"
                    />
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h2 id="heading-dokumen-proses" class="text-base font-semibold text-content sm:text-lg">
                                Dokumen RAB dalam proses
                            </h2>
                            <p class="mt-0.5 text-xs text-content-secondary">
                                Dokumen yang sedang dibaca atau perlu Anda periksa sebelum terbit.
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <Badge v-if="hasReadingAudits" color="info">Sedang dibaca…</Badge>
                            <Badge color="neutral">{{ in_progress_audits.length }} dokumen</Badge>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <Card
                            v-for="auditItem in in_progress_audits"
                            :key="auditItem.id"
                            padding="base"
                            class="flex flex-col justify-between"
                        >
                            <div>
                                <div class="flex items-start justify-between gap-2">
                                    <h3
                                        class="truncate text-sm font-semibold text-content"
                                        :title="auditItem.file_name"
                                    >
                                        {{ auditItem.file_name }}
                                    </h3>
                                    <Badge :color="auditBadgeColor(auditItem.status)">
                                        {{ auditBadgeText(auditItem) }}
                                    </Badge>
                                </div>

                                <p class="mt-1 text-xs text-content-tertiary tabular-nums">
                                    {{ auditItem.items_count }} baris &middot;
                                    {{
                                        auditItem.written_total_rp
                                            ? formatRupiah(auditItem.written_total_rp)
                                            : 'Total belum dihitung'
                                    }}
                                    <template v-if="auditItem.read_ms">
                                        &middot; {{ formatReadDuration(auditItem.read_ms) }}
                                    </template>
                                </p>

                                <p
                                    v-if="auditItem.status === 'read_failed' && auditItem.error_code"
                                    class="mt-1.5 rounded-sm bg-negative-subtle px-2 py-1 text-xs text-negative"
                                >
                                    Kode: {{ auditItem.error_code }}
                                </p>
                            </div>

                            <div
                                class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-stroke pt-3"
                            >
                                <span class="text-xs text-content-tertiary">
                                    {{ auditItem.created_at ? formatDate(auditItem.created_at) : '' }}
                                </span>
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <Button
                                        v-if="auditItem.status === 'read_failed'"
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        :loading="retryingAuditId === auditItem.id"
                                        @click="retryAuditReading(auditItem.id)"
                                    >
                                        {{ retryingAuditId === auditItem.id ? 'Memproses...' : 'Coba baca ulang' }}
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        :loading="replacingAuditId === auditItem.id"
                                        @click="triggerReplaceAuditFile(auditItem.id)"
                                    >
                                        {{ replacingAuditId === auditItem.id ? 'Mengunggah...' : 'Ganti berkas' }}
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="danger"
                                        size="sm"
                                        :loading="deletingAuditId === auditItem.id"
                                        @click="deleteAuditDocument(auditItem)"
                                    >
                                        {{ deletingAuditId === auditItem.id ? 'Menghapus...' : 'Hapus' }}
                                    </Button>
                                    <Button :href="`/audits/${auditItem.id}`" variant="secondary" size="sm">
                                        {{ auditItem.status === 'ready' ? 'Terbitkan' : 'Lanjutkan' }}
                                    </Button>
                                </div>
                            </div>
                        </Card>
                    </div>
                </Card>
            </section>

            <!-- Summary Stats -->
            <section aria-labelledby="heading-ringkasan">
                <h2 id="heading-ringkasan" class="sr-only">Ringkasan statistik proyek</h2>
                <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <Card padding="app">
                        <p class="text-sm font-medium text-content-secondary">Total proyek</p>
                        <p class="mt-2 text-3xl font-bold text-content tabular-nums">
                            {{ stats.total_projects }}
                        </p>
                        <p class="mt-1 text-xs text-content-tertiary">Semua kegiatan desa</p>
                    </Card>

                    <Card padding="app">
                        <p class="text-sm font-medium text-content-secondary">Proyek terbit</p>
                        <p class="mt-2 text-3xl font-bold text-primary tabular-nums">
                            {{ stats.published_projects }}
                        </p>
                        <p class="mt-1 text-xs text-content-tertiary">Bisa dibuka warga via QR</p>
                    </Card>

                    <Card padding="app">
                        <p class="text-sm font-medium text-content-secondary">Draf proyek</p>
                        <p class="mt-2 text-3xl font-bold text-content-secondary tabular-nums">
                            {{ stats.draft_projects }}
                        </p>
                        <p class="mt-1 text-xs text-content-tertiary">Dalam pemeriksaan RAB</p>
                    </Card>

                    <Card padding="app">
                        <p class="text-sm font-medium text-content-secondary">Laporan warga</p>
                        <p class="mt-2 text-3xl font-bold text-content tabular-nums">
                            {{ stats.pending_reports }}
                        </p>
                        <p class="mt-1 text-xs text-content-tertiary">Hasil Cek Spek lapangan</p>
                    </Card>
                </div>
            </section>

            <!-- Projects List -->
            <section id="daftar-proyek" aria-labelledby="heading-proyek">
                <Card padding="app">
                    <div class="mb-4 flex items-center justify-between">
                        <div>
                            <h2 id="heading-proyek" class="text-lg font-semibold text-content">Daftar proyek fisik</h2>
                            <p class="mt-0.5 text-xs text-content-secondary">
                                Proyek pembangunan fisik yang tercatat di {{ village.name }}.
                            </p>
                        </div>
                        <Badge color="neutral">{{ projects.length }} proyek</Badge>
                    </div>

                    <EmptyState
                        v-if="projects.length === 0"
                        illustration="plang-empty"
                        title="Belum ada proyek terdaftar"
                        description="Mulai dengan mengunggah dokumen RAB proyek desa di atas."
                    >
                        <Button variant="secondary" href="/audits/create">Unggah dokumen RAB</Button>
                    </EmptyState>

                    <template v-else>
                        <!-- Desktop Table -->
                        <div class="hidden sm:block">
                            <Table :columns="tableColumns" caption="Daftar proyek fisik desa">
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
                                            <Link
                                                v-if="project.audit_id && !project.is_published"
                                                :href="`/audits/${project.audit_id}`"
                                                class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-warning-border bg-warning-subtle text-warning transition-colors hover:bg-warning hover:text-content-inverse focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                                                title="Periksa & terbitkan RAB"
                                                aria-label="Periksa dan terbitkan RAB"
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
                                                class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-primary/30 bg-primary-subtle text-primary transition-colors hover:bg-primary hover:text-content-inverse focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                                                title="Kelola proyek"
                                                aria-label="Kelola proyek"
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
                                                class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-stroke-strong bg-surface text-content-secondary transition-colors hover:bg-surface-muted hover:text-content focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                                                title="Lihat halaman publik"
                                                aria-label="Lihat halaman publik"
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
                                                class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-stroke-strong bg-surface text-content-secondary transition-colors hover:bg-surface-muted hover:text-content focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                                                title="Cetak poster QR"
                                                aria-label="Cetak poster QR"
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

                        <!-- Mobile Card List -->
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
                                        <span class="text-content-secondary">Sumber dana:</span>
                                        <p class="font-medium text-content">{{ project.fund_source }}</p>
                                    </div>
                                    <div>
                                        <span class="text-content-secondary">Progres fisik:</span>
                                        <p class="font-medium text-content tabular-nums">
                                            {{ project.progress_pct }}%
                                            <span v-if="project.photos_count > 0" class="text-content-tertiary">
                                                ({{ project.photos_count }} foto)
                                            </span>
                                        </p>
                                    </div>
                                </div>

                                <div
                                    class="mt-3 flex flex-wrap items-center justify-end gap-2 border-t border-stroke pt-2.5"
                                >
                                    <Button
                                        v-if="project.audit_id && !project.is_published"
                                        :href="`/audits/${project.audit_id}`"
                                        variant="secondary"
                                        size="sm"
                                    >
                                        Periksa RAB
                                    </Button>
                                    <Button :href="`/desa/projects/${project.id}`" variant="secondary" size="sm">
                                        Kelola
                                    </Button>
                                    <Button
                                        v-if="project.is_published"
                                        :href="`/p/${project.slug}`"
                                        variant="ghost"
                                        size="sm"
                                    >
                                        Publik
                                    </Button>
                                    <Button
                                        v-if="project.is_published"
                                        :href="`/projects/${project.id}/qr`"
                                        variant="ghost"
                                        size="sm"
                                    >
                                        Poster QR
                                    </Button>
                                </div>
                            </Card>
                        </div>
                    </template>
                </Card>
            </section>
        </div>
    </PemdesLayout>
</template>
