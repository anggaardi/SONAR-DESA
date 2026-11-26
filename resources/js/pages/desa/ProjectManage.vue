<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Alert from '@/components/ui/Alert.vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import ChangeHistory, { type ProjectChangeItem } from '@/features/project/ChangeHistory.vue';
import ItemChangeForm, { type ProjectAuditItem } from '@/features/project/ItemChangeForm.vue';
import MeasurementChangeForm, { type ProjectMeasurementItem } from '@/features/project/MeasurementChangeForm.vue';
import ProgressPanel from '@/features/project/ProgressPanel.vue';
import PemdesLayout from '@/layouts/PemdesLayout.vue';
import { projectType } from '@/lib/labels';
import type { PriceStandardItem } from '@/types/audit';

interface PhotoData {
    id: number;
    url: string;
    created_at: string;
}

interface ProjectData {
    id: number;
    name: string;
    slug: string;
    type: string;
    fund_source: string;
    year: number;
    lat: number | string;
    lng: number | string;
    progress_pct: number;
    public_status: 'none' | 'in_review' | 'reviewed';
    is_published: boolean;
    published_at: string | null;
    created_at: string;
    audit: {
        id: number;
        status: string;
        file_path: string;
        written_total_rp: number;
        items: ProjectAuditItem[];
    } | null;
    measurements: ProjectMeasurementItem[];
    progress_photos: PhotoData[];
    changes: ProjectChangeItem[];
    village: {
        id: number;
        name: string;
        slug?: string;
        regency: string;
    };
}

interface Props {
    project: ProjectData;
    standards: PriceStandardItem[];
}

const props = defineProps<Props>();

type ManageTab = 'progress' | 'items' | 'measurements' | 'gps' | 'changes';
const activeTab = ref<ManageTab>('progress');

const isFrozen = computed(() => props.project.public_status === 'in_review');

const projectTypeLabel = computed(
    () => projectType[props.project.type as keyof typeof projectType] ?? props.project.type,
);

const tabs = computed<Array<{ id: ManageTab; label: string }>>(() => [
    { id: 'progress', label: `Progres dan foto (${props.project.progress_pct}%)` },
    { id: 'items', label: `Ubah item RAB (${props.project.audit?.items.length ?? 0})` },
    { id: 'measurements', label: `Ubah ukuran Cek Spek (${props.project.measurements.length})` },
    { id: 'gps', label: 'Ubah lokasi GPS' },
    { id: 'changes', label: `Riwayat perubahan (${props.project.changes.length})` },
]);

const gpsForm = useForm({
    lat: String(props.project.lat),
    lng: String(props.project.lng),
    reason: '',
});

function submitGpsChange(): void {
    if (isFrozen.value) {
        return;
    }
    gpsForm.post(`/desa/projects/${props.project.id}/cco/gps`, {
        preserveScroll: true,
        onSuccess: () => {
            gpsForm.reason = '';
        },
    });
}

function deleteDraftProject(): void {
    if (props.project.is_published) {
        return;
    }
    if (confirm('Yakin ingin menghapus draf proyek ini? Tindakan ini tidak dapat dibatalkan.')) {
        router.delete(`/desa/projects/${props.project.id}`);
    }
}
</script>

<template>
    <PemdesLayout :village="project.village" active-nav="projects">
        <Head :title="`Kelola: ${project.name} - Sonar Desa`" />

        <template #breadcrumb>
            <Link href="/" class="transition-colors hover:text-primary">Beranda</Link>
            <span>/</span>
            <Link href="/desa" class="transition-colors hover:text-primary">Pemerintah Desa</Link>
            <span>/</span>
            <span class="font-medium text-content">{{ project.name }}</span>
        </template>

        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-content sm:text-3xl">
                        {{ project.name }}
                    </h1>
                    <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-content-secondary">
                        <Badge :color="project.is_published ? 'positive' : 'neutral'">
                            {{ project.is_published ? 'Terbit' : 'Draf' }}
                        </Badge>
                        <Badge v-if="project.public_status === 'in_review'" color="warning">Dalam tinjauan BPD</Badge>
                        <Badge v-else-if="project.public_status === 'reviewed'" color="info">Ditinjau BPD</Badge>
                        <span>&middot;</span>
                        <span>{{ projectTypeLabel }} &middot; Tahun {{ project.year }}</span>
                        <span>&middot;</span>
                        <span>{{ project.fund_source }}</span>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <Button v-if="project.is_published" variant="secondary" :href="`/projects/${project.id}/qr`">
                        Cetak poster QR
                    </Button>
                    <Button v-if="project.is_published" variant="secondary" :href="`/p/${project.slug}`">
                        Lihat halaman publik
                    </Button>
                    <Button v-if="!project.is_published" variant="danger" @click="deleteDraftProject">
                        Hapus draf proyek
                    </Button>
                </div>
            </div>

            <!-- Freeze Notice while in BPD review -->
            <Alert v-if="isFrozen" color="warning" title="Perubahan proyek dibekukan selama dalam tinjauan BPD">
                Proyek ini sedang dalam tinjauan BPD. Perubahan item belanja RAB, ukuran Cek Spek, dan lokasi GPS
                dibekukan sementara sampai tinjauan selesai. Pembaruan progres fisik dan foto lapangan tetap bisa
                dilakukan.
            </Alert>

            <!-- Tabs -->
            <div class="border-b border-stroke">
                <nav
                    class="-mb-px flex space-x-6 overflow-x-auto text-sm font-medium"
                    role="tablist"
                    aria-label="Menu pengelolaan proyek"
                >
                    <button
                        v-for="tab in tabs"
                        :key="tab.id"
                        type="button"
                        role="tab"
                        :aria-selected="activeTab === tab.id"
                        :class="[
                            'min-h-11 border-b-2 px-1 py-3 whitespace-nowrap transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary',
                            activeTab === tab.id
                                ? 'border-primary text-primary'
                                : 'border-transparent text-content-secondary hover:border-stroke hover:text-content',
                        ]"
                        @click="activeTab = tab.id"
                    >
                        <span class="tabular-nums">{{ tab.label }}</span>
                    </button>
                </nav>
            </div>

            <!-- Tab 1: Progress & Photos -->
            <ProgressPanel
                v-if="activeTab === 'progress'"
                :project-id="project.id"
                :current-progress-pct="project.progress_pct"
                :photos="project.progress_photos"
            />

            <!-- Tab 2: Item Changes -->
            <ItemChangeForm
                v-else-if="activeTab === 'items'"
                :project-id="project.id"
                :written-total-rp="project.audit?.written_total_rp ?? 0"
                :items="project.audit?.items ?? []"
                :standards="standards"
                :is-frozen="isFrozen"
            />

            <!-- Tab 3: Measurement Changes -->
            <MeasurementChangeForm
                v-else-if="activeTab === 'measurements'"
                :project-id="project.id"
                :measurements="project.measurements"
                :is-frozen="isFrozen"
            />

            <!-- Tab 4: GPS Location Change -->
            <div v-else-if="activeTab === 'gps'">
                <Card padding="app">
                    <h2 class="text-base font-semibold text-content">Ubah titik lokasi proyek (GPS)</h2>
                    <p class="mt-1 text-xs text-content-secondary">
                        Titik lokasi dipakai untuk memeriksa apakah warga mengirim Cek Spek dari sekitar lokasi proyek.
                    </p>

                    <form class="mt-5 max-w-lg space-y-4" @submit.prevent="submitGpsChange">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label for="gps-lat" class="block text-sm font-medium text-content">Latitude *</label>
                                <input
                                    id="gps-lat"
                                    v-model="gpsForm.lat"
                                    type="text"
                                    required
                                    :disabled="isFrozen"
                                    class="mt-1 block h-11 w-full rounded-md border border-stroke-strong bg-surface px-3 text-sm text-content tabular-nums focus:border-primary focus:outline-none disabled:bg-surface-muted"
                                />
                                <p v-if="gpsForm.errors.lat" class="mt-1 text-xs text-negative">
                                    {{ gpsForm.errors.lat }}
                                </p>
                            </div>
                            <div>
                                <label for="gps-lng" class="block text-sm font-medium text-content">Longitude *</label>
                                <input
                                    id="gps-lng"
                                    v-model="gpsForm.lng"
                                    type="text"
                                    required
                                    :disabled="isFrozen"
                                    class="mt-1 block h-11 w-full rounded-md border border-stroke-strong bg-surface px-3 text-sm text-content tabular-nums focus:border-primary focus:outline-none disabled:bg-surface-muted"
                                />
                                <p v-if="gpsForm.errors.lng" class="mt-1 text-xs text-negative">
                                    {{ gpsForm.errors.lng }}
                                </p>
                            </div>
                        </div>

                        <div>
                            <label for="gps-reason" class="block text-sm font-medium text-content">
                                Alasan perubahan lokasi *
                            </label>
                            <input
                                id="gps-reason"
                                v-model="gpsForm.reason"
                                type="text"
                                required
                                :disabled="isFrozen"
                                placeholder="Contoh: Penyesuaian patok lokasi setelah musyawarah"
                                class="mt-1 block h-11 w-full rounded-md border border-stroke-strong bg-surface px-3 text-sm text-content focus:border-primary focus:outline-none disabled:bg-surface-muted"
                            />
                            <p v-if="gpsForm.errors.reason" class="mt-1 text-xs text-negative">
                                {{ gpsForm.errors.reason }}
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-3 pt-2">
                            <a
                                :href="`https://www.google.com/maps?q=${project.lat},${project.lng}`"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="text-sm font-medium text-primary hover:underline"
                            >
                                Buka di Google Maps
                            </a>

                            <Button
                                type="submit"
                                variant="primary"
                                :loading="gpsForm.processing"
                                :disabled="isFrozen || gpsForm.processing || !gpsForm.reason"
                            >
                                {{ gpsForm.processing ? 'Menyimpan...' : 'Simpan koordinat baru' }}
                            </Button>
                        </div>
                    </form>
                </Card>
            </div>

            <!-- Tab 5: Change History -->
            <ChangeHistory v-else :changes="project.changes" />
        </div>
    </PemdesLayout>
</template>
