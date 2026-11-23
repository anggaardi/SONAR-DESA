<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import Alert from '@/components/ui/Alert.vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Table from '@/components/ui/Table.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { projectType } from '@/lib/labels';

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
    stats: {
        total_projects: number;
        published_projects: number;
        draft_projects: number;
        pending_reports: number;
        audits_needs_review: number;
        audits_read_failed: number;
    };
    projects: ProjectItem[];
}

defineProps<Props>();

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

        <template #logo>
            <div class="flex items-center gap-3">
                <div
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary text-sm font-bold text-content-inverse"
                    aria-hidden="true"
                >
                    SD
                </div>
                <div>
                    <span class="block text-base leading-tight font-bold text-content">Sonar Desa</span>
                    <span class="block text-xs text-content-tertiary">Pemerintah Desa</span>
                </div>
            </div>
        </template>

        <template #nav>
            <Link
                href="/desa"
                class="flex items-center gap-3 rounded-md bg-primary-subtle px-3 py-2.5 text-sm font-semibold text-primary"
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
                :href="`/d/${village.slug}`"
                class="flex items-center gap-3 rounded-md px-3 py-2.5 text-sm font-medium text-content-secondary transition-colors hover:bg-surface-muted hover:text-content"
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
                <div>
                    <Button variant="primary" href="/audits">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
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
            <section aria-labelledby="heading-proyek">
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
                        <Button variant="primary" href="/audits"> Unggah Dokumen RAB </Button>
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
                                        <div class="flex items-center justify-end gap-3">
                                            <Link
                                                v-if="project.is_published"
                                                :href="`/p/${project.slug}`"
                                                class="text-sm font-medium text-primary underline-offset-2 hover:underline"
                                            >
                                                Lihat Publik &rarr;
                                            </Link>
                                            <Link
                                                v-if="project.is_published"
                                                :href="`/projects/${project.id}/qr`"
                                                class="text-sm font-medium text-content-secondary underline-offset-2 hover:text-content hover:underline"
                                            >
                                                Cetak QR
                                            </Link>
                                            <span v-else class="text-xs text-content-tertiary">Belum terbit</span>
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

                                <div
                                    v-if="project.is_published"
                                    class="mt-3 flex items-center justify-between border-t border-stroke pt-2.5 text-xs"
                                >
                                    <Link :href="`/p/${project.slug}`" class="font-medium text-primary hover:underline">
                                        Lihat Publik &rarr;
                                    </Link>
                                    <Link
                                        :href="`/projects/${project.id}/qr`"
                                        class="font-medium text-content-secondary hover:underline"
                                    >
                                        Cetak QR
                                    </Link>
                                </div>
                            </Card>
                        </div>
                    </template>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
