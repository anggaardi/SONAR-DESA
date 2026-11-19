<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

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

        <main class="mx-auto my-auto max-w-md px-4 py-12 text-center sm:max-w-lg">
            <div
                class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-lg bg-surface-muted text-content-secondary"
                aria-hidden="true"
            >
                <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="1.8"
                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                    />
                </svg>
            </div>

            <h1 class="text-2xl font-semibold text-content">Proyek belum diterbitkan atau tidak ditemukan</h1>

            <p class="mt-2 text-base text-content-secondary">
                {{
                    message ||
                    'Tautan atau kode QR yang Anda buka belum tersedia untuk publik, masih berstatus draf internal desa, atau sudah diarsipkan.'
                }}
            </p>

            <Link
                href="/"
                class="mt-6 inline-flex h-11 items-center justify-center rounded-md bg-primary px-5 text-base font-medium text-content-inverse transition-colors hover:bg-primary-hover"
            >
                Kembali ke beranda
            </Link>
        </main>

        <footer class="border-t border-stroke py-6 text-center text-sm text-content-secondary">
            Sonar Desa &middot; Keterbukaan Anggaran &amp; Realisasi Fisik Desa
        </footer>
    </div>
</template>
