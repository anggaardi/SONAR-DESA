<script setup lang="ts">
// RootLayout: default layout for every page (app.ts). Puts DemoBanner above any page, so all demo
// screens show it without each page opting in (PRD Fitur 7.2, M2). Pages with DesktopSidebar show the
// same controls in the sidebar from md up, so the banner stays on small screens only there.

import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import DemoBanner from '@/layouts/DemoBanner.vue';

const pagesWithDesktopSidebar = ['project/Show', 'project/Village'];

const page = usePage();
const hasDesktopSidebar = computed(() => pagesWithDesktopSidebar.includes(page.component));
</script>

<template>
    <div>
        <DemoBanner :class="{ 'md:hidden': hasDesktopSidebar }" />
        <slot />
    </div>
</template>
