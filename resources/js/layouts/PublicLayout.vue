<script setup lang="ts">
// PublicLayout: single-column layout for citizen-facing screens (warga).
// Max width max-w-md (448px), designed from 360px. See docs/DESIGN.md §4.
// Wraps: DemoBanner (when isDemo=true), <main>, and optional <footer>.

import DemoBanner from '@/layouts/DemoBanner.vue';

interface Props {
    isDemo?: boolean;
}

withDefaults(defineProps<Props>(), {
    isDemo: false,
});
</script>

<template>
    <div class="min-h-screen bg-surface-subtle text-content antialiased">
        <DemoBanner v-if="isDemo" />

        <main class="mx-auto max-w-md px-4 pt-6 pb-12">
            <slot />
        </main>
    </div>
</template>
