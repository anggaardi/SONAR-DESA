<script setup lang="ts">
// PublicLayout: single-column layout for citizen-facing screens (warga).
// Max width max-w-md (448px), designed from 360px. See docs/DESIGN.md §4.
// DemoBanner comes from RootLayout (app.ts), so it is not repeated here.
</script>

<template>
    <div class="min-h-screen bg-surface-subtle text-content antialiased">
        <main class="mx-auto max-w-md px-4 pt-6 pb-12">
            <slot />
        </main>
    </div>
</template>
