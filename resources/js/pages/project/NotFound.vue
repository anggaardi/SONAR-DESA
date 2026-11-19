<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

interface Props {
    message: string;
}

defineProps<Props>();
</script>

<template>
    <Head title="Proyek Tidak Ditemukan" />
    <main class="mx-auto max-w-md p-6 text-center">
        <h1 class="text-xl font-bold text-gray-800">Perhatian</h1>
        <p class="mt-2 text-gray-600">{{ message }}</p>
    </main>
</template>
