<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { formatRupiah, formatRupiahShort } from '@/lib/format';
import { projectType } from '@/lib/labels';
import type { ProjectVillage, VillageProjectSummary } from '@/types/project';

interface Props {
    village: ProjectVillage;
    projects: VillageProjectSummary[];
}

const props = defineProps<Props>();

const searchQuery = ref('');

const filteredProjects = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) {
        return props.projects;
    }
    return props.projects.filter(
        (p) =>
            p.name.toLowerCase().includes(q) ||
            p.fund_source.toLowerCase().includes(q) ||
            p.type.toLowerCase().includes(q),
    );
});

const totalVillageBudget = computed(() => {
    return props.projects.reduce((acc, p) => acc + p.total_amount_rp, 0);
});
</script>

<template>
    <div class="min-h-screen bg-surface-subtle text-content antialiased">
        <Head :title="`Proyek Pembangunan Desa ${village.name}`" />

        <!-- Header -->
        <header class="border-b border-stroke bg-surface">
            <div class="mx-auto flex max-w-md items-center justify-between px-4 py-3 sm:max-w-xl md:max-w-2xl">
                <Link href="/" class="inline-flex min-h-11 items-center text-base font-semibold text-content">
                    Sonar Desa
                </Link>
                <span class="text-sm text-content-secondary">Portal publik desa</span>
            </div>
        </header>

        <main class="mx-auto max-w-md px-4 py-6 sm:max-w-xl sm:px-6 md:max-w-2xl">
            <!-- Village Hero -->
            <section class="rounded-lg border border-stroke bg-surface p-4">
                <p class="text-sm text-content-secondary">Kabupaten {{ village.regency }}</p>
                <h1 class="mt-1 text-2xl font-semibold text-content">Desa {{ village.name }}</h1>
                <p class="mt-1 text-base text-content-secondary">
                    Transparansi daftar pembangunan fisik desa yang dibiayai anggaran publik.
                </p>

                <!-- Aggregates -->
                <dl class="mt-4 grid grid-cols-2 gap-3 border-t border-stroke pt-4">
                    <div class="rounded-md bg-surface-subtle p-3">
                        <dt class="text-sm text-content-secondary">Total proyek terbit</dt>
                        <dd class="mt-0.5 text-lg font-bold text-content tabular-nums">{{ projects.length }} proyek</dd>
                    </div>
                    <div class="rounded-md bg-surface-subtle p-3">
                        <dt class="text-sm text-content-secondary">Total nilai anggaran</dt>
                        <dd class="mt-0.5 text-lg font-bold text-content tabular-nums">
                            {{ formatRupiahShort(totalVillageBudget) }}
                        </dd>
                    </div>
                </dl>
            </section>

            <!-- Search / Filter -->
            <div v-if="projects.length > 2" class="mt-6 flex flex-col gap-1.5">
                <label for="project-search" class="text-sm font-medium text-content">Cari proyek</label>
                <input
                    id="project-search"
                    v-model="searchQuery"
                    type="search"
                    placeholder="Nama proyek, sumber dana, atau jenis"
                    class="h-11 w-full rounded-md border border-stroke-strong bg-surface px-3 text-base text-content placeholder:text-content-tertiary"
                />
            </div>

            <!-- Project Cards List -->
            <section class="mt-4 flex flex-col gap-3" aria-label="Daftar proyek">
                <div
                    v-if="filteredProjects.length === 0"
                    class="rounded-lg border border-dashed border-stroke-strong bg-surface p-8 text-center"
                >
                    <p class="text-base font-medium text-content">Tidak ada proyek yang sesuai.</p>
                    <p class="mt-1 text-sm text-content-secondary">
                        Belum ada proyek yang diterbitkan atau kata kunci tidak cocok.
                    </p>
                </div>

                <Link
                    v-for="p in filteredProjects"
                    :key="p.id"
                    :href="`/p/${p.slug}`"
                    class="group block rounded-lg border border-stroke bg-surface p-4 transition-colors hover:border-primary hover:bg-surface-subtle"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="rounded-full border border-stroke bg-surface-muted px-2.5 py-0.5 text-sm font-medium text-content-secondary"
                                >
                                    {{ projectType[p.type as keyof typeof projectType] ?? p.type }}
                                </span>
                                <span class="text-sm text-content-secondary">
                                    {{ p.fund_source }} &middot; {{ p.year }}
                                </span>
                            </div>

                            <h2 class="mt-2 text-lg font-semibold text-content group-hover:text-primary">
                                {{ p.name }}
                            </h2>
                        </div>

                        <svg
                            class="mt-1 h-5 w-5 shrink-0 text-content-tertiary group-hover:text-primary"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            aria-hidden="true"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </div>

                    <div class="mt-3 flex flex-col gap-2 border-t border-stroke pt-3 text-sm">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-content-secondary">Anggaran</span>
                            <span class="text-base font-semibold text-content tabular-nums">
                                {{ formatRupiah(p.total_amount_rp) }}
                            </span>
                        </div>

                        <div>
                            <div class="flex items-center justify-between text-content-secondary">
                                <span>Progres fisik</span>
                                <span class="font-semibold text-content tabular-nums">{{ p.progress_pct }}%</span>
                            </div>
                            <div class="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-stroke" aria-hidden="true">
                                <div
                                    class="h-full rounded-full bg-primary"
                                    :style="{ width: `${Math.min(100, Math.max(0, p.progress_pct))}%` }"
                                ></div>
                            </div>
                        </div>

                        <!-- Status publik: none = tanpa lencana (PRD Fitur 4.3) -->
                        <span
                            v-if="p.public_status === 'in_review'"
                            class="inline-flex items-center gap-1.5 self-start rounded-full border border-warning-border bg-warning-subtle px-2.5 py-0.5 font-medium text-warning"
                        >
                            <span class="h-2 w-2 rounded-full bg-warning-solid" aria-hidden="true"></span>
                            <span>Dalam tinjauan BPD</span>
                        </span>
                        <span
                            v-else-if="p.public_status === 'reviewed'"
                            class="inline-flex items-center gap-1.5 self-start rounded-full border border-info-border bg-info-subtle px-2.5 py-0.5 font-medium text-info"
                        >
                            <span class="h-2 w-2 rounded-full bg-info-solid" aria-hidden="true"></span>
                            <span>Telah ditinjau BPD</span>
                        </span>
                    </div>
                </Link>
            </section>

            <!-- Footer -->
            <footer class="mt-10 border-t border-stroke py-6 text-center text-sm text-content-secondary">
                Sonar Desa &middot; Keterbukaan Informasi Publik Desa
            </footer>
        </main>
    </div>
</template>
