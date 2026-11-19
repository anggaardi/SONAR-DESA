<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';

const page = usePage();
</script>

<template>
    <Head title="Beranda" />
    <main class="mx-auto max-w-md p-6">
        <h1 class="text-3xl font-semibold text-content">Sonar Desa</h1>
        <p class="mt-2 text-base text-content-secondary">Yang di papan proyek cuma permukaannya.</p>
    </main>
    <footer class="mx-auto max-w-md px-6 text-xs text-content-tertiary">build {{ page.props.app.commit }}</footer>
</template>
