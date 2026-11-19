<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

interface Props {
    project: {
        id: number;
        name: string;
        slug: string;
        shortUrl: string;
        village: {
            name: string;
            regency: string;
        };
    };
    qrSvg: string;
    ctaText: string;
}

defineProps<Props>();
</script>

<template>
    <Head :title="`Poster QR - ${project.name}`" />
    <main class="mx-auto max-w-lg p-6 text-center">
        <h1 class="text-2xl font-bold">{{ project.name }}</h1>
        <p class="mt-2 text-sm text-gray-600">{{ project.village.name }}, {{ project.village.regency }}</p>
        <!-- eslint-disable-next-line vue/no-v-html -->
        <div class="my-6 flex justify-center" v-html="qrSvg" />
        <p class="font-semibold">{{ ctaText }}</p>
        <p class="mt-2 text-sm text-gray-500">{{ project.shortUrl }}</p>
    </main>
</template>
