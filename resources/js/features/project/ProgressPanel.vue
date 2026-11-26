<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import { formatDateTime } from '@/lib/format';

interface PhotoData {
    id: number;
    url: string;
    created_at: string;
}

interface Props {
    projectId: number;
    currentProgressPct: number;
    photos: PhotoData[];
}

const props = defineProps<Props>();

const progressForm = useForm({
    progress_pct: props.currentProgressPct,
    photos: [] as File[],
    decrease_reason: '',
});

const isDecrease = computed(() => progressForm.progress_pct < props.currentProgressPct);

function handlePhotoSelect(e: Event): void {
    const target = e.target as HTMLInputElement;
    if (target.files) {
        progressForm.photos = Array.from(target.files);
    }
}

function submitProgress(): void {
    progressForm.post(`/projects/${props.projectId}/progress`, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            progressForm.photos = [];
            progressForm.decrease_reason = '';
        },
    });
}
</script>

<template>
    <div class="space-y-6">
        <Card padding="app">
            <h2 class="text-base font-semibold text-content">Pembaruan progres dan foto lapangan</h2>
            <p class="mt-1 text-xs text-content-secondary">
                Unggah foto dokumentasi lapangan dan perbarui persentase capaian pekerjaan.
            </p>

            <form class="mt-5 space-y-4" @submit.prevent="submitProgress">
                <div>
                    <div class="flex items-center justify-between text-sm">
                        <label for="progress-pct" class="font-medium text-content"> Persentase progres (%) </label>
                        <span class="font-bold text-primary tabular-nums">{{ progressForm.progress_pct }}%</span>
                    </div>
                    <input
                        id="progress-pct"
                        v-model.number="progressForm.progress_pct"
                        type="range"
                        min="0"
                        max="100"
                        step="1"
                        class="mt-2 h-2 w-full cursor-pointer appearance-none rounded-lg bg-surface-muted accent-primary"
                    />
                    <div class="mt-1 flex justify-between text-xs text-content-tertiary tabular-nums">
                        <span>0% (Mulai)</span>
                        <span>50%</span>
                        <span>100% (Selesai)</span>
                    </div>
                    <p v-if="progressForm.errors.progress_pct" class="mt-1 text-xs text-negative">
                        {{ progressForm.errors.progress_pct }}
                    </p>
                </div>

                <!-- Reason required if progress decreased -->
                <div v-if="isDecrease" class="rounded-lg border border-warning-border bg-warning-subtle p-3">
                    <label for="decrease-reason" class="block text-xs font-semibold text-warning">
                        Alasan penurunan progres <span class="text-negative">*</span>
                    </label>
                    <p class="mt-0.5 text-xs text-content-secondary tabular-nums">
                        Progres turun dari {{ currentProgressPct }}% ke {{ progressForm.progress_pct }}%. Tulis alasan
                        penurunan dan perubahan ini akan tampil di riwayat warga.
                    </p>
                    <input
                        id="decrease-reason"
                        v-model="progressForm.decrease_reason"
                        type="text"
                        required
                        placeholder="Contoh: Koreksi pengukuran ulang konsultan pengawas"
                        class="mt-2 block h-11 w-full rounded-md border border-stroke-strong bg-surface px-3 text-sm text-content focus:border-primary focus:outline-none"
                    />
                    <p v-if="progressForm.errors.decrease_reason" class="mt-1 text-xs text-negative">
                        {{ progressForm.errors.decrease_reason }}
                    </p>
                </div>

                <div>
                    <label for="progress-photos" class="block text-sm font-medium text-content">
                        Foto dokumentasi lapangan (1 sampai 5 foto) <span class="text-negative">*</span>
                    </label>
                    <input
                        id="progress-photos"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        multiple
                        required
                        class="mt-1 block w-full text-sm text-content-secondary file:mr-4 file:rounded-md file:border file:border-stroke-strong file:bg-surface file:px-3 file:py-2 file:text-xs file:font-medium file:text-content hover:file:bg-surface-muted"
                        @change="handlePhotoSelect"
                    />
                    <p v-if="progressForm.errors.photos" class="mt-1 text-xs text-negative">
                        {{ progressForm.errors.photos }}
                    </p>
                </div>

                <div class="flex justify-end pt-2">
                    <Button
                        type="submit"
                        variant="primary"
                        :loading="progressForm.processing"
                        :disabled="
                            progressForm.processing ||
                            progressForm.photos.length === 0 ||
                            (isDecrease && !progressForm.decrease_reason)
                        "
                    >
                        {{ progressForm.processing ? 'Menyimpan...' : 'Simpan progres dan foto' }}
                    </Button>
                </div>
            </form>
        </Card>

        <!-- Photo Gallery -->
        <div class="space-y-3">
            <h3 class="text-sm font-semibold text-content tabular-nums">
                Galeri foto dokumentasi ({{ photos.length }})
            </h3>
            <Card v-if="photos.length === 0" padding="app" class="text-center text-sm text-content-secondary">
                Belum ada foto progres yang diunggah.
            </Card>
            <div v-else class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                <div
                    v-for="photo in photos"
                    :key="photo.id"
                    class="overflow-hidden rounded-lg border border-stroke bg-surface"
                >
                    <img
                        :src="photo.url"
                        alt="Foto progres fisik"
                        class="aspect-4/3 w-full object-cover"
                        loading="lazy"
                    />
                    <div class="border-t border-stroke p-2.5 text-xs text-content-secondary">
                        {{ formatDateTime(photo.created_at) }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
