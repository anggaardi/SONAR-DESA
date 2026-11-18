<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';

const page = usePage();
</script>

<template>
    <Head title="Beranda" />
    <main class="mx-auto max-w-md p-6">
        <h1 class="text-3xl font-semibold">Sonar Desa</h1>
        <p class="mt-2 text-gray-600">Yang di papan proyek cuma permukaannya.</p>
    </main>
    <footer class="mx-auto max-w-md px-6 text-xs text-gray-400">build {{ page.props.app.commit }}</footer>
</template>
