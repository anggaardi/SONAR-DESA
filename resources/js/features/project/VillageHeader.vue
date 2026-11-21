<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

interface Props {
    villageName: string;
    backHref?: string;
}

withDefaults(defineProps<Props>(), {
    backHref: '/',
});

const emit = defineEmits<{
    share: [];
}>();
</script>

<template>
    <header class="sticky top-0 z-20 border-b border-stroke bg-surface">
        <div class="mx-auto flex max-w-md items-center justify-between px-4 py-3 sm:max-w-xl md:max-w-2xl">
            <Link
                :href="backHref"
                class="inline-flex h-10 w-10 items-center justify-center rounded-full text-content transition-colors hover:bg-surface-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                aria-label="Kembali ke beranda"
            >
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 12H5M12 19l-7-7 7-7" />
                </svg>
            </Link>

            <h1 class="text-base font-semibold text-content sm:text-lg">
                {{ villageName.startsWith('Desa ') ? villageName : `Desa ${villageName}` }}
            </h1>

            <button
                type="button"
                class="inline-flex h-10 w-10 items-center justify-center rounded-full text-content transition-colors hover:bg-surface-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                aria-label="Bagikan profil desa"
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
