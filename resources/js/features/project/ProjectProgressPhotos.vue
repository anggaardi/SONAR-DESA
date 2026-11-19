<script setup lang="ts">
// ProjectProgressPhotos: photo grid with lightbox. PRD P8 (Langkah 5).

import { ref } from 'vue';
import { formatDate } from '@/lib/format';
import type { ProjectDetail } from '@/types/project';

interface Props {
    photos: ProjectDetail['progress_photos'];
}

defineProps<Props>();

const activePhoto = ref<string | null>(null);
</script>

<template>
    <section class="mt-8 border-t border-stroke pt-6">
        <h2 class="text-xl font-semibold text-content">Foto progres lapangan</h2>
        <p class="text-sm text-content-secondary">Diunggah oleh Pemerintah Desa</p>

        <div v-if="photos.length === 0" class="mt-3 rounded-lg bg-surface-muted p-4 text-center">
            <p class="text-sm text-content-secondary">Desa belum mengunggah foto progres</p>
        </div>

        <div v-else class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
            <div
                v-for="photo in photos"
                :key="photo.id"
                class="overflow-hidden rounded-lg border border-stroke bg-surface"
            >
                <button type="button" class="block w-full text-left" @click="activePhoto = photo.url">
                    <div class="aspect-4/3 w-full overflow-hidden bg-surface-muted">
                        <img
                            :src="photo.url"
                            :alt="`Progres ${photo.progress_pct}%`"
                            class="h-full w-full object-cover"
                            loading="lazy"
                        />
                    </div>
                    <div class="flex items-center justify-between p-2">
                        <span class="text-sm font-semibold text-content tabular-nums">{{ photo.progress_pct }}%</span>
                        <span class="text-xs text-content-secondary">{{ formatDate(photo.taken_at) }}</span>
                    </div>
                </button>
            </div>
        </div>
    </section>

    <!-- Lightbox -->
    <div
        v-if="activePhoto"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/90 p-4"
        role="dialog"
        aria-modal="true"
        aria-label="Tampilan penuh foto progres"
        @click.self="activePhoto = null"
    >
        <div class="relative max-h-[90vh] max-w-lg">
            <button
                type="button"
                class="absolute -top-12 right-0 inline-flex h-11 w-11 items-center justify-center rounded-full bg-white/20 text-white hover:bg-white/30 focus-visible:outline focus-visible:outline-2 focus-visible:outline-white"
                aria-label="Tutup foto"
                @click="activePhoto = null"
            >
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
            <img
                :src="activePhoto"
                alt="Foto progres diperbesar"
                class="max-h-[80vh] w-auto rounded-lg object-contain"
            />
        </div>
    </div>
</template>
