<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
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
        <Head title="Beranda" />

        <div class="flex flex-col gap-6">
            <div class="flex flex-col gap-2">
                <h1 class="text-3xl font-semibold text-content">Sonar Desa</h1>
                <p class="text-lg text-content-secondary">Yang di papan proyek cuma permukaannya.</p>
            </div>

            <section class="flex flex-col gap-3" aria-labelledby="demo-heading">
                <h2 id="demo-heading" class="text-xl font-semibold text-content">Coba mode demo</h2>
                <p class="text-base text-content-secondary">
                    Tanpa daftar. Setiap pengunjung mendapat salinan Desa Contoh sendiri.
                </p>

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
