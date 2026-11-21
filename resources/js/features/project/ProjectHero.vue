<script setup lang="ts">
// ProjectHero: pita biru pembuka rincian proyek. Satu kalimat cerita, bukan deretan label.
// Mock FigCraft "7 · Rincian proyek v2" bagian 1.

import { computed } from 'vue';
import { projectType } from '@/lib/labels';
import { fundingLabel, projectStory } from '@/lib/projectStory';
import type { ProjectDetail } from '@/types/project';

interface Props {
    project: ProjectDetail;
}

const props = defineProps<Props>();

const villageName = computed(() => {
    const name = props.project.village.name;
    return name.startsWith('Desa ') ? name : `Desa ${name}`;
});

const typeName = computed(() => projectType[props.project.type as keyof typeof projectType] ?? props.project.type);

const story = computed(() => projectStory(props.project));
const funding = computed(() => fundingLabel(props.project.fund_source, props.project.year));
</script>

<template>
    <section class="bg-primary-subtle px-4 pt-5 pb-12 sm:px-6">
        <div class="mx-auto max-w-md sm:max-w-xl md:max-w-2xl">
            <p class="flex items-center gap-1.5 text-sm font-medium text-content-secondary">
                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M12 21s-7-6.2-7-11.5a7 7 0 0114 0C19 14.8 12 21 12 21z"
                    />
                    <circle cx="12" cy="9.5" r="2.5" stroke-width="2" />
                </svg>
                {{ villageName }} &middot; Kab. {{ project.village.regency }}
            </p>

            <h1 class="mt-2 text-2xl leading-tight font-bold tracking-tight text-content sm:text-3xl">
                {{ project.name }}
            </h1>

            <p class="mt-2 text-base leading-relaxed text-content-secondary sm:text-lg">
                {{ story }}
            </p>

            <div class="mt-4 flex flex-wrap items-center gap-2">
                <span
                    class="inline-flex items-center rounded-full border border-stroke bg-surface px-3 py-1 text-sm font-medium text-content-secondary"
                >
                    {{ typeName }}
                </span>
                <span
                    class="inline-flex items-center rounded-full border border-info-border bg-surface px-3 py-1 text-sm font-medium text-primary-hover"
                >
                    {{ funding }}
                </span>
            </div>
        </div>
    </section>
</template>
