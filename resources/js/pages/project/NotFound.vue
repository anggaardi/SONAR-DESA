<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import Button from '@/components/ui/Button.vue';
import EmptyState from '@/components/ui/EmptyState.vue';

interface Props {
    message: string;
}

defineProps<Props>();
</script>

<template>
    <div class="flex min-h-screen flex-col justify-between bg-surface-subtle text-content antialiased">
        <Head title="Proyek Tidak Ditemukan" />

        <header class="border-b border-stroke bg-surface">
            <div class="mx-auto flex max-w-md items-center justify-between px-4 py-3 sm:max-w-xl">
                <Link href="/" class="inline-flex min-h-11 items-center text-base font-semibold text-content">
                    Sonar Desa
                </Link>
                <span class="text-sm text-content-secondary">Keterbukaan informasi</span>
            </div>
        </header>

        <main class="mx-auto my-auto max-w-md px-4 py-10 sm:max-w-lg">
            <EmptyState
                illustration="not-found"
                title="Plang Proyek Belum Terpasang"
                :description="
                    message ||
                    'Tautan atau kode QR yang kamu buka belum tersedia untuk publik, masih berstatus draf musyawarah desa, atau sudah diarsipkan.'
                "
            >
                <Button href="/" variant="primary" size="md"> Kembali ke beranda </Button>
            </EmptyState>
        </main>

        <footer class="border-t border-stroke py-6 text-center text-sm text-content-secondary">
            Sonar Desa &middot; Keterbukaan Anggaran &amp; Realisasi Fisik Desa
        </footer>
    </div>
</template>
