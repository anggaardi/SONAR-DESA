<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import type { ProjectVillage, VillageProjectSummary } from '@/types/project';

interface Props {
    village: ProjectVillage;
    projects: VillageProjectSummary[];
}

defineProps<Props>();
</script>

<template>
    <Head :title="`Proyek ${village.name}`" />
    <main class="mx-auto max-w-xl p-4">
        <h1 class="text-xl font-bold">{{ village.name }}</h1>
        <p class="text-sm text-gray-600">Kabupaten {{ village.regency }}</p>
    </main>
</template>
