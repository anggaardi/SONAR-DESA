<script setup lang="ts">
// RootLayout: default layout for every page (app.ts). Puts DemoBanner above any page,
// so all demo screens show it without each page opting in (PRD Fitur 7.2, M2).

import DemoBanner from '@/layouts/DemoBanner.vue';
</script>

<template>
    <div>
        <DemoBanner />
        <slot />
    </div>
</template>
