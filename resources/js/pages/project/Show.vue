<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import ProjectChanges from '@/features/project/ProjectChanges.vue';
import ProjectHeader from '@/features/project/ProjectHeader.vue';
import ProjectItems from '@/features/project/ProjectItems.vue';
import ProjectMeasurements from '@/features/project/ProjectMeasurements.vue';
import ProjectProgressPhotos from '@/features/project/ProjectProgressPhotos.vue';
import ProjectSummary from '@/features/project/ProjectSummary.vue';
import type { ProjectDetail } from '@/types/project';

interface Props {
    project: ProjectDetail;
}

const props = defineProps<Props>();

const toastMessage = ref<string | null>(null);
let toastTimer: ReturnType<typeof setTimeout> | null = null;

function showToast(message: string): void {
    toastMessage.value = message;
    if (toastTimer) clearTimeout(toastTimer);
    toastTimer = setTimeout(() => {
        toastMessage.value = null;
    }, 2500);
}

async function handleShare(): Promise<void> {
    const url = window.location.href;
    const shareData = {
        title: props.project.name,
        text: `Transparansi ${props.project.name} - Desa ${props.project.village.name}`,
        url,
    };
    if (navigator.share) {
        try {
            await navigator.share(shareData);
            return;
        } catch {
            // User cancelled or browser fell back to clipboard
        }
    }
    if (navigator.clipboard) {
        try {
            await navigator.clipboard.writeText(url);
            showToast('Tautan proyek berhasil disalin!');
        } catch {
            showToast('Gagal menyalin tautan');
        }
    }
}
</script>

<template>
    <div class="min-h-screen bg-surface-subtle text-content antialiased">
        <Head :title="project.name" />

        <ProjectHeader
            :village-name="project.village.name"
            :village-slug="project.village.slug"
            :project-name="project.name"
            @share="handleShare"
        />

        <main class="mx-auto max-w-md px-4 py-4 sm:max-w-xl sm:px-6 md:max-w-2xl">
            <ProjectSummary :project="project" />
            <ProjectMeasurements :project="project" />
            <ProjectItems :project="project" />
            <ProjectProgressPhotos :photos="project.progress_photos" />
            <ProjectChanges :changes="project.changes" />

            <footer class="mt-10 border-t border-stroke py-6 text-center text-sm text-content-secondary">
                <p>Sonar Desa &middot; Keterbukaan Informasi Publik Desa</p>
                <p class="mt-1 text-xs text-content-tertiary">
                    Berdasarkan UU No. 6/2014 tentang Desa &amp; UU No. 14/2008 tentang KIP
                </p>
            </footer>
        </main>

        <!-- Toast feedback -->
        <div
            v-if="toastMessage"
            class="fixed bottom-6 left-1/2 z-50 -translate-x-1/2 rounded-md bg-content px-4 py-2 text-sm font-medium text-content-inverse shadow-sm"
            role="status"
        >
            {{ toastMessage }}
        </div>
    </div>
</template>
