<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Alert from '@/components/ui/Alert.vue';
import Button from '@/components/ui/Button.vue';

interface Props {
    project?: {
        name: string;
        slug: string;
    } | null;
}

withDefaults(defineProps<Props>(), {
    project: null,
});

const page = usePage();
const role = computed(() => page.props.auth?.user?.role ?? page.props.demo?.role ?? null);
const isDemo = computed(() => page.props.demo !== null);

const dashboardHref = computed(() => {
    if (role.value === 'desa') {
        return '/desa';
    }
    if (role.value === 'bpd') {
        return '/bpd';
    }
    return null;
});

function switchToWarga(): void {
    router.post('/demo/switch/warga');
}
</script>

<template>
    <div class="flex min-h-screen flex-col justify-between bg-surface-subtle text-content antialiased">
        <Head :title="project ? `Cek Spek · ${project.name}` : 'Khusus Warga'" />

        <header class="border-b border-stroke bg-surface">
            <div class="mx-auto flex max-w-md items-center justify-between px-4 py-3 sm:max-w-xl">
                <Link href="/" class="inline-flex min-h-11 items-center text-base font-semibold text-content">
                    Sonar Desa
                </Link>
                <span class="text-sm text-content-secondary">{{ project ? 'Cek Spek Lapangan' : 'Laporan Saya' }}</span>
            </div>
        </header>

        <main class="mx-auto my-auto w-full max-w-md space-y-5 px-4 py-10 sm:max-w-lg">
            <div class="space-y-1">
                <h1 class="text-xl font-bold text-content">
                    {{ project ? 'Halaman Cek Spek Khusus Warga' : 'Halaman Laporan Saya Khusus Warga' }}
                </h1>
                <p v-if="project" class="text-sm text-content-secondary">{{ project.name }}</p>
            </div>

            <Alert color="warning" title="Tidak dapat diakses dengan akun saat ini">
                <template v-if="project">
                    Laporan Cek Spek di lokasi hanya dapat dikirim oleh <strong>Warga</strong>. Akun Pemerintah Desa dan
                    BPD tidak dapat mengirim laporan Cek Spek untuk menjaga objektivitas pengawasan.
                </template>
                <template v-else>
                    Riwayat laporan Cek Spek hanya tersedia untuk akun <strong>Warga</strong>. Gunakan dashboard resmi
                    untuk memantau atau menindaklanjuti laporan pada proyek desa.
                </template>
            </Alert>

            <div class="flex flex-col gap-3 sm:flex-row">
                <Button v-if="project" :href="`/p/${project.slug}`" variant="primary" size="md">
                    Kembali ke halaman proyek
                </Button>
                <Button v-else-if="dashboardHref" :href="dashboardHref" variant="primary" size="md">
                    Kembali ke Dashboard
                </Button>
                <Button v-else href="/" variant="primary" size="md"> Kembali ke Beranda </Button>

                <Button v-if="isDemo" variant="secondary" size="md" @click="switchToWarga">
                    Ganti ke peran Warga
                </Button>
                <Button v-else-if="project && dashboardHref" :href="dashboardHref" variant="secondary" size="md">
                    Kembali ke Dashboard
                </Button>
            </div>
        </main>

        <footer class="border-t border-stroke py-6 text-center text-sm text-content-secondary">
            Sonar Desa &middot; Keterbukaan Anggaran &amp; Realisasi Fisik Desa
        </footer>
    </div>
</template>
