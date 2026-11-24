<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDateTime } from '@/lib/format';
import { reportType, reviewOutcome, type ReviewOutcome } from '@/lib/labels';

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

interface ReportItem {
    id: number;
    reporter_label: string;
    kind: 'mismatch' | 'match' | 'unmeasurable';
    distance_m: number | null;
    note: string | null;
    superseded: boolean;
    invalid_reason: string | null;
    is_new: boolean;
    created_at: string | null;
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
    public_url: string;
    document_url: string;
    reports: ReportItem[];
    closed_cycles: ClosedCycle[];
}

const props = defineProps<{
    village: { name: string; slug: string };
    projects: ProjectRow[];
    selectedProject: SelectedProject | null;
    threshold: number;
}>();

const invalidatingReportId = ref<number | null>(null);
const invalidForm = useForm({ reason: '' });

const outcomeForm = useForm<{ result: ReviewOutcome; note: string }>({
    result: 'followed_up',
    note: '',
});

const activeNoteCycleId = ref<number | null>(null);
const followUpForm = useForm({ note: '' });

function selectProject(id: number): void {
    router.get('/bpd', { project: id }, { preserveScroll: true });
}

function markOpened(reportId: number): void {
    router.post(`/bpd/reports/${reportId}/open`, {}, { preserveScroll: true });
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

function restoreReport(reportId: number): void {
    router.post(`/bpd/reports/${reportId}/restore`, {}, { preserveScroll: true });
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
    if (status === 'in_review') return 'Dalam tinjauan';
    if (status === 'reviewed') return 'Sudah ditinjau';
    return 'Belum ada';
}
</script>

<template>
    <AppLayout>
        <Head :title="`Dashboard BPD — ${village.name}`" />

        <template #nav>
            <Link href="/bpd" class="rounded-md bg-surface-muted px-3 py-2 text-sm font-medium text-content">
                Dashboard BPD
            </Link>
            <Link
                :href="`/d/${village.slug}`"
                class="rounded-md px-3 py-2 text-sm font-medium text-content-secondary hover:bg-surface-muted"
            >
                Halaman Publik Desa
            </Link>
        </template>

        <div class="space-y-6">
            <header class="flex flex-wrap items-center justify-between gap-2 border-b border-stroke pb-4">
                <div>
                    <h1 class="text-xl font-semibold text-content">Dashboard BPD — {{ village.name }}</h1>
                    <p class="text-sm text-content-secondary">
                        Tinjau laporan warga per siklus dan simpan hasil tinjauan yang tampil publik.
                    </p>
                </div>
                <span class="rounded-md border border-stroke bg-surface px-3 py-1 text-xs font-medium text-content">
                    Peran: BPD
                </span>
            </header>

            <div class="grid gap-6 lg:grid-cols-12">
                <!-- Daftar proyek -->
                <section class="space-y-3 lg:col-span-7">
                    <div class="overflow-x-auto rounded-lg border border-stroke bg-surface">
                        <table class="w-full text-left text-sm">
                            <thead class="border-b border-stroke bg-surface-subtle text-xs text-content-secondary">
                                <tr>
                                    <th class="px-4 py-3 font-medium">Proyek (urut: perlu perhatian)</th>
                                    <th class="px-3 py-3 text-right font-medium">Di atas</th>
                                    <th class="px-3 py-3 text-right font-medium">Selisih</th>
                                    <th class="px-3 py-3 text-right font-medium">Sesuai</th>
                                    <th class="px-4 py-3 font-medium">Status publik</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-stroke">
                                <tr
                                    v-for="p in projects"
                                    :key="p.id"
                                    :class="[
                                        'cursor-pointer transition-colors hover:bg-surface-subtle',
                                        selectedProject?.id === p.id ? 'bg-surface-muted' : '',
                                    ]"
                                    @click="selectProject(p.id)"
                                >
                                    <td class="px-4 py-3 font-medium text-content">
                                        <span>{{ p.name }}</span>
                                        <span
                                            v-if="p.unread_count > 0"
                                            class="ml-2 inline-block rounded border border-stroke px-1.5 py-0.5 text-xs text-content"
                                        >
                                            baru
                                        </span>
                                    </td>
                                    <td class="px-3 py-3 text-right tabular-nums">{{ p.above_standard_count }}</td>
                                    <td class="px-3 py-3 text-right tabular-nums">{{ p.valid_mismatch_count }}</td>
                                    <td class="px-3 py-3 text-right tabular-nums">{{ p.match_count }}</td>
                                    <td class="px-4 py-3">
                                        <span class="rounded border border-stroke px-2 py-0.5 text-xs">
                                            {{ statusBadgeText(p.public_status) }}
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="projects.length === 0">
                                    <td colspan="5" class="px-4 py-6 text-center text-content-secondary">
                                        Belum ada proyek terbit di desa ini.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="rounded-lg border border-stroke bg-surface p-4 text-xs text-content-secondary">
                        <p class="font-medium text-content">Aturan status publik (per siklus):</p>
                        <ul class="mt-1 list-disc space-y-1 pl-4">
                            <li>&lt; {{ threshold }} selisih valid: belum ada penanda publik.</li>
                            <li>&ge; {{ threshold }} selisih valid: otomatis &ldquo;Dalam tinjauan BPD&rdquo;.</li>
                            <li>Simpan hasil: tampil publik, siklus baru dimulai dari 0.</li>
                        </ul>
                    </div>
                </section>

                <!-- Panel detail proyek -->
                <section v-if="selectedProject" class="space-y-4 lg:col-span-5">
                    <div class="space-y-3 rounded-lg border border-stroke bg-surface p-4">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <h2 class="text-base font-semibold text-content">{{ selectedProject.name }}</h2>
                                <p class="text-xs text-content-secondary">
                                    Status: {{ statusBadgeText(selectedProject.public_status) }}
                                </p>
                            </div>
                            <div class="flex gap-2 text-xs">
                                <a :href="selectedProject.public_url" target="_blank" class="text-primary underline">
                                    Halaman publik
                                </a>
                                <a :href="selectedProject.document_url" target="_blank" class="text-primary underline">
                                    Lihat dokumen asli
                                </a>
                            </div>
                        </div>

                        <!-- Laporan siklus berjalan -->
                        <div class="space-y-2">
                            <h3 class="text-xs font-semibold tracking-wide text-content-secondary uppercase">
                                Laporan siklus berjalan ({{ selectedProject.reports.length }})
                            </h3>
                            <p v-if="selectedProject.reports.length === 0" class="text-sm text-content-secondary">
                                Belum ada laporan di siklus ini.
                            </p>
                            <div
                                v-for="r in selectedProject.reports"
                                :key="r.id"
                                class="space-y-1.5 rounded-md border border-stroke p-3 text-sm"
                            >
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-medium text-content">
                                        {{ r.reporter_label }} · {{ reportType[r.kind] }}
                                    </span>
                                    <div class="flex items-center gap-1.5 text-xs">
                                        <span v-if="r.is_new" class="rounded border border-stroke px-1.5 py-0.5">
                                            baru
                                        </span>
                                        <span v-if="r.superseded" class="text-content-secondary">digantikan</span>
                                    </div>
                                </div>
                                <p class="text-xs text-content-secondary">
                                    <span v-if="r.distance_m !== null"
                                        >{{ Math.round(r.distance_m) }} m dari proyek ·
                                    </span>
                                    <span v-if="r.created_at">{{ formatDateTime(r.created_at) }}</span>
                                </p>
                                <p v-if="r.note" class="text-sm text-content">{{ r.note }}</p>
                                <p v-if="r.invalid_reason" class="text-xs text-content-secondary">
                                    Ditandai tidak valid: {{ r.invalid_reason }}
                                </p>

                                <div class="flex flex-wrap gap-3 pt-1 text-xs">
                                    <button
                                        v-if="r.is_new"
                                        type="button"
                                        class="text-primary underline"
                                        @click="markOpened(r.id)"
                                    >
                                        Tandai sudah dibuka
                                    </button>
                                    <button
                                        v-if="!r.invalid_reason"
                                        type="button"
                                        class="text-content-secondary underline"
                                        @click="invalidatingReportId = r.id"
                                    >
                                        Tandai tidak valid
                                    </button>
                                    <button
                                        v-else
                                        type="button"
                                        class="text-primary underline"
                                        @click="restoreReport(r.id)"
                                    >
                                        Batalkan tidak valid
                                    </button>
                                </div>

                                <form
                                    v-if="invalidatingReportId === r.id"
                                    class="mt-2 space-y-2 border-t border-stroke pt-2"
                                    @submit.prevent="submitInvalid(r.id)"
                                >
                                    <input
                                        v-model="invalidForm.reason"
                                        type="text"
                                        placeholder="Alasan (foto tidak relevan / bukan proyek ini / duplikat)"
                                        class="w-full rounded-md border border-stroke bg-surface px-2.5 py-1.5 text-xs"
                                    />
                                    <p v-if="invalidForm.errors.reason" class="text-danger text-xs">
                                        {{ invalidForm.errors.reason }}
                                    </p>
                                    <div class="flex gap-2">
                                        <button
                                            type="submit"
                                            class="rounded-md bg-primary px-3 py-1 text-xs font-medium text-white"
                                        >
                                            Simpan
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-md border border-stroke px-3 py-1 text-xs"
                                            @click="invalidatingReportId = null"
                                        >
                                            Batal
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Form hasil tinjauan BPD (hanya saat Dalam tinjauan - B6) -->
                        <form
                            v-if="selectedProject.public_status === 'in_review'"
                            class="space-y-3 border-t border-stroke pt-4"
                            @submit.prevent="submitOutcome"
                        >
                            <h3 class="text-sm font-semibold text-content">Hasil tinjauan BPD</h3>
                            <div class="flex gap-2">
                                <button
                                    type="button"
                                    :class="[
                                        'flex-1 rounded-md border px-3 py-2 text-xs font-medium',
                                        outcomeForm.result === 'followed_up'
                                            ? 'border-primary bg-surface-muted text-content'
                                            : 'border-stroke text-content-secondary',
                                    ]"
                                    @click="outcomeForm.result = 'followed_up'"
                                >
                                    {{ reviewOutcome.followed_up }}
                                </button>
                                <button
                                    type="button"
                                    :class="[
                                        'flex-1 rounded-md border px-3 py-2 text-xs font-medium',
                                        outcomeForm.result === 'no_mismatch'
                                            ? 'border-primary bg-surface-muted text-content'
                                            : 'border-stroke text-content-secondary',
                                    ]"
                                    @click="outcomeForm.result = 'no_mismatch'"
                                >
                                    {{ reviewOutcome.no_mismatch }}
                                </button>
                            </div>
                            <div>
                                <textarea
                                    v-model="outcomeForm.note"
                                    rows="3"
                                    placeholder="Catatan hasil tinjauan (wajib, minimal 30 huruf; tampil publik, tidak bisa dihapus)"
                                    class="w-full rounded-md border border-stroke bg-surface p-2.5 text-sm"
                                />
                                <p v-if="outcomeForm.errors.note" class="text-danger mt-1 text-xs">
                                    {{ outcomeForm.errors.note }}
                                </p>
                            </div>
                            <button
                                type="submit"
                                :disabled="outcomeForm.processing"
                                class="w-full rounded-md bg-primary px-4 py-2 text-sm font-medium text-white"
                            >
                                Simpan hasil
                            </button>
                        </form>
                    </div>

                    <!-- Riwayat siklus selesai & catatan susulan (B9) -->
                    <div
                        v-if="selectedProject.closed_cycles.length > 0"
                        class="space-y-3 rounded-lg border border-stroke bg-surface p-4"
                    >
                        <h3 class="text-sm font-semibold text-content">Riwayat hasil tinjauan</h3>
                        <div
                            v-for="cycle in selectedProject.closed_cycles"
                            :key="cycle.id"
                            class="space-y-2 rounded-md border border-stroke p-3 text-sm"
                        >
                            <div class="flex items-center justify-between text-xs text-content-secondary">
                                <span class="font-medium text-content">
                                    {{ cycle.result ? reviewOutcome[cycle.result] : '-' }}
                                </span>
                                <span v-if="cycle.closed_at">{{ formatDateTime(cycle.closed_at) }}</span>
                            </div>
                            <p class="text-sm text-content">{{ cycle.note }}</p>

                            <div v-if="cycle.notes.length > 0" class="space-y-1 border-t border-stroke pt-2">
                                <p class="text-xs font-medium text-content-secondary">Catatan susulan:</p>
                                <div v-for="n in cycle.notes" :key="n.id" class="text-xs text-content">
                                    <span v-if="n.created_at" class="text-content-secondary">
                                        {{ formatDateTime(n.created_at) }} —
                                    </span>
                                    {{ n.note }}
                                </div>
                            </div>

                            <button
                                v-if="activeNoteCycleId !== cycle.id"
                                type="button"
                                class="text-xs text-primary underline"
                                @click="activeNoteCycleId = cycle.id"
                            >
                                Tambah catatan susulan
                            </button>

                            <form
                                v-else
                                class="space-y-2 border-t border-stroke pt-2"
                                @submit.prevent="submitFollowUp(cycle.id)"
                            >
                                <textarea
                                    v-model="followUpForm.note"
                                    rows="2"
                                    placeholder="Tulis catatan susulan (tampil publik, bertanggal)"
                                    class="w-full rounded-md border border-stroke bg-surface p-2 text-xs"
                                />
                                <p v-if="followUpForm.errors.note" class="text-danger text-xs">
                                    {{ followUpForm.errors.note }}
                                </p>
                                <div class="flex gap-2">
                                    <button
                                        type="submit"
                                        class="rounded-md bg-primary px-3 py-1 text-xs font-medium text-white"
                                    >
                                        Simpan catatan
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-md border border-stroke px-3 py-1 text-xs"
                                        @click="activeNoteCycleId = null"
                                    >
                                        Batal
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
