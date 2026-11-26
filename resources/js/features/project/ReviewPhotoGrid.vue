<script setup lang="ts">
// ReviewPhotoGrid: grid foto lapangan asli dari laporan warga yang diterbitkan setelah tinjauan BPD.

import { computed } from 'vue';
import { projectReviews } from '@/lib/labels';
import type { ProjectPublishedReportPhoto } from '@/types/project';

const props = defineProps<{
    photos: ProjectPublishedReportPhoto[];
    reporterLabel: string;
}>();

const emit = defineEmits<{
    (e: 'open', photo: ProjectPublishedReportPhoto): void;
}>();

const visiblePhotos = computed(() => props.photos.slice(0, 4));
const overflowCount = computed(() => Math.max(0, props.photos.length - 4));

const gridClass = computed(() => {
    if (visiblePhotos.value.length === 1) {
        return 'grid-cols-1';
    }
    if (visiblePhotos.value.length === 3) {
        return 'grid-cols-3';
    }
    return 'grid-cols-2';
});

function photoLabel(photo: ProjectPublishedReportPhoto, index: number): string {
    return `${props.reporterLabel} — Foto ${photo.position || index + 1}`;
}
</script>

<template>
    <div v-if="visiblePhotos.length > 0" class="grid gap-1.5 overflow-hidden rounded-xl" :class="gridClass">
        <button
            v-for="(photo, index) in visiblePhotos"
            :key="photo.id"
            type="button"
            class="group relative overflow-hidden rounded-lg border border-stroke bg-surface-muted focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-primary"
            :class="visiblePhotos.length === 1 ? 'aspect-video max-h-64 w-full' : 'aspect-4/3 w-full'"
            :aria-label="projectReviews.photos.open(photoLabel(photo, index))"
            @click="emit('open', photo)"
        >
            <img
                :src="photo.url"
                :alt="photoLabel(photo, index)"
                loading="lazy"
                class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
            />
            <span
                v-if="index === visiblePhotos.length - 1 && overflowCount > 0"
                class="absolute inset-0 flex items-center justify-center bg-black/65 text-lg font-bold text-white tabular-nums transition-colors group-hover:bg-black/75"
            >
                +{{ overflowCount }}
            </span>
        </button>
    </div>
</template>
