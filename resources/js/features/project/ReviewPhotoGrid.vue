<script setup lang="ts">
// ReviewPhotoGrid: 2x2 field-photo collage on a review card. The last tile carries "+N" for photos not shown.

import { projectReviews } from '@/lib/labels';
import ReviewPhotoIllustration from './ReviewPhotoIllustration.vue';
import type { ReviewPhoto } from './reviewPhotos';

const props = defineProps<{
    photos: ReviewPhoto[];
}>();

const emit = defineEmits<{
    (e: 'open', photo: ReviewPhoto): void;
}>();

function isLastTile(index: number): boolean {
    return index === props.photos.length - 1;
}
</script>

<template>
    <div
        class="grid aspect-[2/1] grid-cols-2 gap-1 overflow-hidden rounded-lg border border-stroke bg-surface-muted sm:aspect-[16/9]"
    >
        <button
            v-for="(photo, index) in photos"
            :key="`${photo.scene}-${index}`"
            type="button"
            class="group relative h-full w-full overflow-hidden bg-surface-muted focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-primary"
            :aria-label="projectReviews.photos.open(photo.title)"
            @click="emit('open', photo)"
        >
            <ReviewPhotoIllustration
                :scene="photo.scene"
                class="h-full w-full transition-transform duration-300 group-hover:scale-105"
            />
            <span
                v-if="photo.tag"
                class="absolute top-1.5 left-1.5 rounded-sm bg-black/55 px-1.5 py-0.5 text-xs font-semibold text-white"
            >
                {{ photo.tag }}
            </span>
            <span
                v-if="isLastTile(index) && photo.moreCount"
                class="absolute inset-0 flex items-center justify-center bg-black/65 text-lg font-bold text-white tabular-nums transition-colors group-hover:bg-black/75"
            >
                +{{ photo.moreCount }}
            </span>
        </button>
    </div>
</template>
