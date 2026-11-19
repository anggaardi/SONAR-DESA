<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import type { ProjectDetail } from '@/types/project';

interface Props {
    project: ProjectDetail;
}

defineProps<Props>();
</script>

<template>
    <Head :title="project.name" />
    <main class="mx-auto max-w-md p-4">
        <h1 class="text-xl font-bold">{{ project.name }}</h1>
        <p class="text-sm text-gray-600">{{ project.village.name }} &middot; {{ project.fund_source }}</p>
    </main>
</template>
