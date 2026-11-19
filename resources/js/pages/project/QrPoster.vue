<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

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

function handlePrint(): void {
    window.print();
}
</script>

<template>
    <div class="min-h-screen bg-surface-muted py-6 text-content print:min-h-0 print:bg-white print:p-0">
        <Head :title="`Poster QR - ${project.name}`" />

        <!-- Action Bar (Hidden on Print) -->
        <div class="no-print mx-auto mb-6 flex max-w-xl items-center justify-between gap-3 px-4">
            <Link
                :href="`/p/${project.slug}`"
                class="inline-flex h-10 items-center gap-1.5 rounded-md border border-stroke-strong bg-surface px-3 text-sm font-medium text-content transition-colors hover:bg-surface-subtle"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
                <span>Halaman proyek</span>
            </Link>

            <button
                type="button"
                class="inline-flex h-10 items-center gap-2 rounded-md bg-primary px-4 text-sm font-medium text-content-inverse transition-colors hover:bg-primary-hover"
                @click="handlePrint"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"
                    />
                </svg>
                <span>Cetak poster (A4)</span>
            </button>
        </div>

        <main
            class="mx-auto max-w-[210mm] rounded-lg border border-stroke bg-surface p-8 print:max-w-none print:rounded-none print:border-none print:p-10"
        >
            <!-- Poster Header -->
            <div class="border-b-4 border-content pb-5 text-center">
                <p class="text-sm font-semibold text-content-secondary">
                    Pemerintah Kabupaten {{ project.village.regency }}
                </p>
                <h2 class="text-2xl font-bold text-content">Desa {{ project.village.name }}</h2>
                <p
                    class="mt-2 inline-block rounded-full bg-content px-4 py-1 text-sm font-semibold text-content-inverse"
                >
                    Papan Transparansi Proyek Fisik
                </p>
            </div>

            <!-- Project Identity -->
            <div class="mt-8 text-center">
                <p class="text-sm font-semibold text-primary">Nama kegiatan pembangunan</p>
                <h1 class="mt-2 text-3xl font-bold text-content">
                    {{ project.name }}
                </h1>
                <p class="mt-2 text-sm text-content-secondary">
                    Transparansi Rencana Anggaran Biaya (RAB) dan Pembuktian Ukuran Fisik di Lokasi
                </p>
            </div>

            <!-- QR Code Section -->
            <div class="my-8 flex flex-col items-center justify-center">
                <div class="rounded-lg border-2 border-content bg-surface p-4">
                    <!-- eslint-disable-next-line vue/no-v-html -->
                    <div class="h-56 w-56 sm:h-64 sm:w-64 [&>svg]:h-full [&>svg]:w-full" v-html="qrSvg" />
                </div>

                <div class="mt-4 text-center">
                    <p class="text-xl font-bold text-content">
                        {{ ctaText }}
                    </p>
                    <p class="mt-1 text-base font-semibold text-primary tabular-nums">
                        {{ project.shortUrl }}
                    </p>
                </div>
            </div>

            <!-- Instructions for Citizens -->
            <div class="mt-6 rounded-lg border border-stroke bg-surface-subtle p-4">
                <p class="text-base font-semibold text-content">Langkah pembuktian mandiri oleh warga</p>
                <ol class="mt-3 flex flex-col gap-2 text-sm text-content-secondary">
                    <li class="flex items-start gap-2">
                        <span
                            class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-content text-xs font-semibold text-content-inverse"
                        >
                            1
                        </span>
                        <span>Arahkan kamera ponsel cerdas Anda ke kode QR di atas (tanpa perlu unduh aplikasi).</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span
                            class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-content text-xs font-semibold text-content-inverse"
                        >
                            2
                        </span>
                        <span
                            >Buka dan periksa seluruh rincian anggaran material, harga acuan daerah, dan dokumen
                            asli.</span
                        >
                    </li>
                    <li class="flex items-start gap-2">
                        <span
                            class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-content text-xs font-semibold text-content-inverse"
                        >
                            3
                        </span>
                        <span
                            >Gunakan fitur <strong class="font-semibold text-content">Cek Spek</strong> untuk
                            mencocokkan ukuran panjang, lebar, dan tebal fisik di titik lokasi ini.</span
                        >
                    </li>
                </ol>
            </div>

            <!-- Legal Notice & Footnote -->
            <div class="mt-8 border-t border-stroke pt-4 text-center text-xs text-content-secondary">
                <p>
                    Keterbukaan Informasi Publik dijamin oleh UU No. 6 Tahun 2014 tentang Desa (Pasal 68 &amp; 82) serta
                    UU No. 14 Tahun 2008 tentang Keterbukaan Informasi Publik.
                </p>
                <p class="mt-0.5">Didukung oleh sistem pengawasan partisipatif Sonar Desa.</p>
            </div>
        </main>
    </div>
</template>

<style scoped>
@media print {
    .no-print {
        display: none !important;
    }
}
</style>
