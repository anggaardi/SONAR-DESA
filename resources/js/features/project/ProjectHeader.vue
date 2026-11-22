<script setup lang="ts">
// ProjectHeader: sticky top nav (back link + title + share button) for the public project page.
// Matches mobile mock: [←] "Rincian proyek" [...] [Share]

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
    <header class="border-b border-stroke bg-surface/95 backdrop-blur-sm">
        <div class="mx-auto flex max-w-md items-center justify-between px-4 py-3 sm:max-w-xl md:max-w-2xl">
            <div class="flex items-center gap-3">
                <Link
                    :href="`/d/${villageSlug}`"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-full text-content transition-colors hover:bg-surface-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                    :aria-label="`Kembali ke profil Desa ${villageName}`"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M19 12H5M12 19l-7-7 7-7"
                        />
                    </svg>
                </Link>

                <p class="text-base font-semibold text-content sm:text-lg">Rincian proyek</p>
            </div>

            <button
                type="button"
                class="inline-flex h-10 w-10 items-center justify-center rounded-full text-content transition-colors hover:bg-surface-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                aria-label="Bagikan rincian proyek"
                @click="emit('share')"
            >
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"
                    />
                </svg>
            </button>
        </div>
    </header>
</template>
