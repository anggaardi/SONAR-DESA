<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { demo } from '@/lib/labels';
import type { Role } from '@/types/inertia';

const page = usePage();
const starting = ref<Role | null>(null);

const doors: { role: Role; description: string }[] = [
    { role: 'warga', description: 'Lihat proyek Desa Contoh dan rincian anggarannya.' },
    { role: 'desa', description: 'Terbitkan proyek dan unggah foto progres.' },
    { role: 'bpd', description: 'Tinjau laporan warga untuk proyek desa.' },
];

function start(role: Role): void {
    starting.value = role;
    router.post(`/demo/start/${role}`, {}, { onFinish: () => (starting.value = null) });
}
</script>

<template>
    <PublicLayout>
        <Head title="Mode Demo" />

        <div class="flex flex-col gap-6">
            <div>
                <Link
                    href="/"
                    class="inline-flex items-center gap-1.5 text-sm font-medium text-primary transition-colors hover:text-primary-hover"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                    Kembali ke Beranda
                </Link>
            </div>

            <div class="flex flex-col gap-2">
                <span
                    class="inline-flex w-fit items-center rounded-full bg-demo-subtle px-3 py-1 text-xs font-semibold text-demo"
                >
                    Simulasi &amp; Pengujian Juri
                </span>
                <h1 class="text-3xl font-semibold text-content">Mode Demo Sonar Desa</h1>
                <p class="text-base text-content-secondary">
                    Tanpa daftar. Setiap pengunjung mendapat salinan Desa Contoh sendiri.
                </p>
            </div>

            <section class="flex flex-col gap-3" aria-labelledby="demo-heading">
                <h2 id="demo-heading" class="sr-only">Pilihan peran demo</h2>

                <Card v-for="door in doors" :key="door.role">
                    <div class="flex flex-col gap-3">
                        <p class="text-base text-content-secondary">{{ door.description }}</p>
                        <Button
                            :variant="door.role === 'warga' ? 'primary' : 'secondary'"
                            :loading="starting === door.role"
                            :disabled="starting !== null"
                            @click="start(door.role)"
                        >
                            {{ demo.tryAs(door.role) }}
                        </Button>
                    </div>
                </Card>
            </section>

            <p class="text-sm text-content-secondary">
                Pemerintah Desa atau BPD dengan akun asli?
                <a href="/login" class="font-medium text-primary underline underline-offset-2">Masuk di sini</a>
            </p>
        </div>

        <footer class="mt-10 text-xs text-content-tertiary">build {{ page.props.app.commit }}</footer>
    </PublicLayout>
</template>
