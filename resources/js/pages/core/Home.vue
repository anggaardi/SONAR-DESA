<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Card from '@/components/ui/Card.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';

const page = usePage();
const demo = computed(() => page.props.demo);
const opening = ref(false);

/**
 * Visitors never browse the template itself: it is the source every demo clones,
 * so they get their own copy as warga (PRD Fitur 7.2, decision A9).
 */
function openSampleVillage(): void {
    if (demo.value) {
        router.visit(`/d/${demo.value.village_slug}`);
        return;
    }

    opening.value = true;
    router.post('/demo/start/warga', {}, { onFinish: () => (opening.value = false) });
}
</script>

<template>
    <PublicLayout>
        <Head title="Beranda" />

        <div class="flex flex-col gap-6">
            <div class="flex flex-col gap-2">
                <h1 class="text-3xl font-semibold text-content">Sonar Desa</h1>
                <p class="text-lg font-medium text-primary">Yang di papan proyek cuma permukaannya.</p>
                <p class="text-sm leading-relaxed text-content-secondary">
                    Portal transparansi pembangunan fisik desa. Membaca RAB proyek desa, membandingkan dengan standar
                    harga kabupaten, dan memberi ruang warga untuk memverifikasi ukuran fisik langsung di lapangan.
                </p>
            </div>

            <section class="flex flex-col gap-3.5" aria-labelledby="beranda-aksi">
                <h2 id="beranda-aksi" class="text-xl font-semibold text-content">Akses Portal</h2>

                <!-- Kartu Desa Contoh -->
                <Card>
                    <div class="flex flex-col gap-3">
                        <div>
                            <span
                                class="inline-flex items-center rounded-full bg-primary-subtle px-2.5 py-0.5 text-xs font-semibold text-primary"
                            >
                                Eksplorasi Data
                            </span>
                            <h3 class="mt-2 text-base font-bold text-content">Katalog Proyek Desa Contoh</h3>
                            <p class="mt-1 text-sm text-content-secondary">
                                Lihat langsung transparansi rincian anggaran, komparasi harga material (SSH), dan
                                spesifikasi teknis lempeng jalan di Desa Contoh.
                            </p>
                        </div>
                        <button
                            type="button"
                            :disabled="opening"
                            class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 text-sm font-semibold text-content-inverse transition-colors hover:bg-primary-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary disabled:opacity-60"
                            @click="openSampleVillage"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"
                                />
                            </svg>
                            {{ demo ? 'Kembali ke Desa Aktif' : 'Buka Desa Contoh' }}
                        </button>
                    </div>
                </Card>

                <!-- Kartu Mode Demo Khusus Juri -->
                <Card>
                    <div class="flex flex-col gap-3">
                        <div>
                            <span
                                class="inline-flex items-center rounded-full bg-demo-subtle px-2.5 py-0.5 text-xs font-semibold text-demo"
                            >
                                Khusus Juri &amp; Penguji
                            </span>
                            <h3 class="mt-2 text-base font-bold text-content">Mode Demo &amp; Simulasi Peran</h3>
                            <p class="mt-1 text-sm text-content-secondary">
                                Uji coba sistem tanpa daftar sebagai Warga, Pemerintah Desa, atau BPD dengan salinan
                                data terisolasi.
                            </p>
                        </div>
                        <Link
                            href="/demo"
                            class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl border border-stroke bg-surface px-4 text-sm font-semibold text-content transition-colors hover:bg-surface-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                        >
                            <svg
                                class="h-4 w-4 text-content-secondary"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"
                                />
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                />
                            </svg>
                            Buka Halaman Mode Demo
                        </Link>
                    </div>
                </Card>
            </section>

            <p class="text-sm text-content-secondary">
                Pemerintah Desa atau BPD dengan akun asli?
                <Link href="/login" class="font-medium text-primary underline underline-offset-2">Masuk di sini</Link>
            </p>
        </div>

        <footer class="mt-10 text-xs text-content-tertiary">
            <p>build {{ page.props.app.commit }}</p>
            <p class="mt-1">Berdasarkan amanat UU No. 6/2014 tentang Desa &amp; UU No. 14/2008 tentang KIP</p>
        </footer>
    </PublicLayout>
</template>
