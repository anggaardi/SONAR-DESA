<script setup lang="ts">
// ProjectHeader: sticky top nav (back link + share button) for the public project page.

import { Link } from '@inertiajs/vue3';

interface Props {
    villageName: string;
    villageSlug: string;
    projectName: string;
}

defineProps<Props>();

const emit = defineEmits<{
    share: [];
}>();
</script>

<template>
    <header class="sticky top-0 z-20 border-b border-stroke bg-surface">
        <div class="mx-auto flex max-w-md items-center justify-between px-4 py-3 sm:max-w-xl md:max-w-2xl">
            <Link
                :href="`/d/${villageSlug}`"
                class="inline-flex min-h-11 items-center gap-1.5 text-sm font-medium text-content-secondary transition-colors hover:text-primary"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
                <span>Desa {{ villageName }}</span>
            </Link>

            <button
                type="button"
                class="inline-flex h-11 items-center gap-1.5 rounded-md border border-stroke-strong bg-surface px-3 text-sm font-medium text-content transition-colors hover:bg-surface-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                aria-label="Bagikan tautan proyek"
                @click="emit('share')"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"
                    />
                </svg>
                <span>Bagikan</span>
            </button>
        </div>
    </header>
</template>
