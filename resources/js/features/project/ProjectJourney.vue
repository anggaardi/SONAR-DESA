<script setup lang="ts">
// ProjectJourney: "Perjalanan proyek", foto progres (PRD P8, Langkah 5) dan kejadian tinjauan BPD
// dalam satu linimasa, terbaru di atas. Foto terbaru tampil besar. Klik foto membuka lightbox.

import { computed, ref } from 'vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import { formatDate } from '@/lib/format';
import { publicStatus, type ReviewOutcome } from '@/lib/labels';
import type { ProjectDetail } from '@/types/project';

interface Props {
    project: ProjectDetail;
}

const props = defineProps<Props>();

type Tone = 'photo' | 'warning' | 'info';

interface JourneyEvent {
    key: string;
    date: string;
    tone: Tone;
    title: string;
    detail: string | null;
    photo: { url: string; progressPct: number } | null;
}

const events = computed<JourneyEvent[]>(() => {
    const list: JourneyEvent[] = props.project.progress_photos.map((p) => ({
        key: `photo-${p.id}`,
        date: p.taken_at,
        tone: 'photo',
        title: `Foto progres ${p.progress_pct}%`,
        detail: 'Diunggah pemerintah desa',
        photo: { url: p.url, progressPct: p.progress_pct },
    }));

    if (props.project.in_review_since) {
        list.push({
            key: 'in-review',
            date: props.project.in_review_since,
            tone: 'warning',
            title: 'Warga melapor, masuk tinjauan BPD',
            detail: 'BPD memeriksa laporan dari lokasi',
            photo: null,
        });
    }

    const review = props.project.review;
    if (review?.reviewed_at) {
        list.push({
            key: 'reviewed',
            date: review.reviewed_at,
            tone: 'info',
            title: review.outcome ? publicStatus.reviewed(review.outcome as ReviewOutcome) : 'Ditinjau BPD',
            detail: review.note,
            photo: null,
        });
    }
    review?.notes.forEach((n, i) => {
        list.push({
            key: `note-${i}`,
            date: n.created_at,
            tone: 'info',
            title: 'Catatan BPD',
            detail: n.note,
            photo: null,
        });
    });

    return list.sort((a, b) => new Date(b.date).getTime() - new Date(a.date).getTime());
});

const latestPhotoKey = computed(() => events.value.find((e) => e.photo)?.key ?? null);

const toneClasses: Record<Tone, string> = {
    photo: 'bg-primary-subtle text-primary',
    warning: 'bg-warning-subtle text-warning',
    info: 'bg-info-subtle text-info',
};

const icons: Record<Tone, string> = {
    photo: 'M6 7h1.5L9 4h6l1.5 3H18a3 3 0 013 3v7a3 3 0 01-3 3H6a3 3 0 01-3-3v-7a3 3 0 013-3zM12 10a3.5 3.5 0 100 7 3.5 3.5 0 000-7z',
    warning: 'M12 3a9 9 0 100 18 9 9 0 000-18zM12 7v5l3 2',
    info: 'M12 3l8 3v6c0 4.5-3.4 8.3-8 9-4.6-.7-8-4.5-8-9V6zM8.5 12l2.5 2.5 4.5-5',
};

const activePhoto = ref<string | null>(null);
</script>

<template>
    <section id="perjalanan" class="scroll-mt-20">
        <h2 class="text-xl font-semibold text-content">Perjalanan proyek</h2>
        <p class="text-sm text-content-secondary">Foto dari pemerintah desa dan catatan BPD</p>

        <div v-if="events.length === 0" class="mt-3 rounded-lg border border-stroke bg-surface px-4">
            <EmptyState
                illustration="plang-empty"
                compact
                title="Belum ada catatan perjalanan"
                description="Desa belum mengunggah foto progres"
            />
        </div>

        <ol v-else class="mt-4">
            <li v-for="(event, index) in events" :key="event.key" class="relative flex gap-3 pb-6 last:pb-0">
                <span
                    v-if="index < events.length - 1"
                    class="absolute top-9 bottom-0 left-4 w-0.5 -translate-x-1/2 bg-stroke"
                    aria-hidden="true"
                />
                <span
                    class="relative flex h-8 w-8 shrink-0 items-center justify-center rounded-full ring-4 ring-surface-subtle"
                    :class="toneClasses[event.tone]"
                    aria-hidden="true"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="icons[event.tone]" />
                    </svg>
                </span>

                <div class="min-w-0 flex-1">
                    <div class="flex items-start gap-3">
                        <div class="min-w-0 flex-1">
                            <p class="text-xs text-content-tertiary">{{ formatDate(event.date) }}</p>
                            <p class="mt-0.5 text-base font-semibold text-content">{{ event.title }}</p>
                            <p v-if="event.detail" class="mt-0.5 text-sm text-content-secondary">{{ event.detail }}</p>
                        </div>

                        <button
                            v-if="event.photo && event.key !== latestPhotoKey"
                            type="button"
                            class="relative h-18 w-24 shrink-0 overflow-hidden rounded-xl bg-surface-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                            :aria-label="`Perbesar ${event.title}`"
                            @click="activePhoto = event.photo.url"
                        >
                            <img :src="event.photo.url" alt="" class="h-full w-full object-cover" loading="lazy" />
                        </button>
                    </div>

                    <button
                        v-if="event.photo && event.key === latestPhotoKey"
                        type="button"
                        class="relative mt-3 block aspect-4/3 w-full overflow-hidden rounded-2xl bg-surface-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                        :aria-label="`Perbesar ${event.title}`"
                        @click="activePhoto = event.photo.url"
                    >
                        <img
                            :src="event.photo.url"
                            alt=""
                            class="h-full w-full object-cover transition-transform duration-200 hover:scale-105"
                            loading="lazy"
                        />
                        <span
                            class="absolute bottom-3 left-3 rounded-full bg-surface px-2.5 py-1 text-xs font-bold text-content tabular-nums"
                        >
                            {{ event.photo.progressPct }}%
                        </span>
                    </button>
                </div>
            </li>
        </ol>
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
