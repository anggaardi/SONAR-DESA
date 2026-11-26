<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Alert from '@/components/ui/Alert.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import PemdesLayout from '@/layouts/PemdesLayout.vue';

interface VillageInfo {
    id: number;
    name: string;
    slug: string;
    regency: string;
}

interface Props {
    village: VillageInfo;
    sampleAvailable: boolean;
    sampleFileName?: string;
}

defineProps<Props>();

const fileInputRef = ref<HTMLInputElement | null>(null);
const isDragging = ref(false);
const selectedFile = ref<File | null>(null);

const form = useForm({
    file: null as File | null,
    use_sample: false,
});

function handleFileChange(event: Event): void {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        setFile(target.files[0]);
    }
}

function handleDrop(event: DragEvent): void {
    isDragging.value = false;
    if (event.dataTransfer?.files && event.dataTransfer.files[0]) {
        setFile(event.dataTransfer.files[0]);
    }
}

function setFile(file: File): void {
    const validTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
    if (!validTypes.includes(file.type) && !file.name.match(/\.(pdf|jpe?g|png)$/i)) {
        form.setError('file', 'Format berkas tidak valid. Harap unggah berkas PDF atau gambar (JPG/PNG).');
        return;
    }

    if (file.size > 10 * 1024 * 1024) {
        form.setError('file', 'Ukuran berkas melebihi batas maksimal 10 MB.');
        return;
    }

    form.clearErrors('file');
    selectedFile.value = file;
    form.file = file;
    form.use_sample = false;
}

function removeFile(): void {
    selectedFile.value = null;
    form.file = null;
    if (fileInputRef.value) {
        fileInputRef.value.value = '';
    }
}

function submitUpload(): void {
    if (!form.file) {
        form.setError('file', 'Pilih berkas dokumen RAB terlebih dahulu.');
        return;
    }

    form.use_sample = false;
    form.post('/audits', {
        forceFormData: true,
    });
}

function submitSample(): void {
    form.file = null;
    form.use_sample = true;
    form.post('/audits');
}

function formatBytes(bytes: number): string {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return `${parseFloat((bytes / Math.pow(k, i)).toFixed(2))} ${sizes[i]}`;
}
</script>

<template>
    <PemdesLayout :village="village" active-nav="audits">
        <Head :title="`Unggah dokumen RAB - Pemerintah ${village.name}`" />

        <template #breadcrumb>
            <Link href="/" class="transition-colors hover:text-primary">Beranda</Link>
            <span>/</span>
            <Link href="/desa" class="transition-colors hover:text-primary"
                >Dashboard Pemerintah {{ village.name }}</Link
            >
            <span>/</span>
            <span class="font-medium text-content">Unggah dokumen RAB</span>
        </template>

        <div class="mx-auto max-w-4xl space-y-6">
            <!-- Header -->
            <div class="border-b border-stroke pb-5">
                <h1 class="text-2xl font-semibold tracking-tight text-content sm:text-3xl">Unggah dokumen RAB</h1>
                <p class="mt-1 text-sm text-content-secondary">
                    Setelah diunggah, sistem membaca tabel RAB. Anda lalu memeriksa hasilnya sebelum terbit.
                </p>
            </div>

            <!-- Upload Card -->
            <Card padding="app">
                <form class="space-y-6" @submit.prevent="submitUpload">
                    <!-- Dropzone -->
                    <div
                        :class="[
                            'relative flex flex-col items-center justify-center rounded-lg border-2 border-dashed p-8 text-center transition-colors sm:p-12',
                            isDragging
                                ? 'border-primary bg-primary-subtle/40'
                                : selectedFile
                                  ? 'border-positive-border bg-positive-subtle/30'
                                  : 'border-stroke-strong hover:border-primary hover:bg-surface-muted/50',
                        ]"
                        @dragover.prevent="isDragging = true"
                        @dragleave.prevent="isDragging = false"
                        @drop.prevent="handleDrop"
                    >
                        <input
                            ref="fileInputRef"
                            type="file"
                            accept=".pdf,application/pdf,image/jpeg,image/png,image/jpg"
                            class="hidden"
                            @change="handleFileChange"
                        />

                        <!-- No File Selected -->
                        <template v-if="!selectedFile">
                            <svg
                                class="h-6 w-6 text-primary"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="1.5"
                                    d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"
                                />
                            </svg>

                            <h2 class="mt-3 text-base font-semibold text-content sm:text-lg">
                                Tarik dan lepaskan berkas RAB di sini
                            </h2>
                            <p class="mt-1 text-sm text-content-secondary">
                                Atau pilih berkas PDF atau foto RAB berstempel dari perangkat Anda.
                            </p>

                            <div class="mt-4">
                                <Button type="button" variant="secondary" @click="fileInputRef?.click()">
                                    Pilih berkas dokumen
                                </Button>
                            </div>

                            <p class="mt-4 text-xs text-content-tertiary">
                                Format yang didukung: <strong>PDF, JPG, JPEG, PNG</strong> (maksimal 10 MB).
                            </p>
                        </template>

                        <!-- File Selected -->
                        <template v-else>
                            <svg
                                class="h-6 w-6 text-positive"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="1.5"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                                />
                            </svg>

                            <h2 class="mt-3 text-base font-semibold text-content">{{ selectedFile.name }}</h2>
                            <p class="mt-0.5 text-xs text-content-secondary tabular-nums">
                                {{ formatBytes(selectedFile.size) }}
                            </p>

                            <div class="mt-4 flex flex-wrap items-center justify-center gap-2">
                                <Button type="button" variant="secondary" size="sm" @click="fileInputRef?.click()">
                                    Pilih berkas lain
                                </Button>
                                <Button type="button" variant="danger" size="sm" @click="removeFile">
                                    Hapus berkas
                                </Button>
                            </div>
                        </template>
                    </div>

                    <!-- Error Alert -->
                    <Alert v-if="form.errors.file" color="negative">
                        {{ form.errors.file }}
                    </Alert>

                    <!-- Upload Progress -->
                    <div v-if="form.progress" class="space-y-1">
                        <div class="flex justify-between text-xs font-semibold text-content">
                            <span>Mengunggah dokumen...</span>
                            <span class="tabular-nums">{{ form.progress.percentage }}%</span>
                        </div>
                        <div class="h-2 w-full overflow-hidden rounded-full bg-surface-muted">
                            <div
                                class="h-full rounded-full bg-primary transition-all duration-200"
                                :style="{ width: `${form.progress.percentage}%` }"
                            />
                        </div>
                    </div>

                    <!-- Primary Action -->
                    <div
                        class="flex flex-col items-start justify-between gap-4 border-t border-stroke pt-4 sm:flex-row sm:items-center"
                    >
                        <p class="text-xs text-content-secondary">
                            Berkas disimpan di ruang privat desa dan hanya bisa dibuka aparatur desa berwenang.
                        </p>

                        <Button
                            type="submit"
                            variant="primary"
                            :disabled="!selectedFile || form.processing"
                            :loading="form.processing && !form.use_sample"
                            class="w-full sm:w-auto"
                        >
                            {{
                                form.processing && !form.use_sample ? 'Sedang memproses...' : 'Unggah dan baca dokumen'
                            }}
                        </Button>
                    </div>
                </form>
            </Card>

            <!-- Demo Sample Card -->
            <Card v-if="sampleAvailable" padding="app" surface="subtle">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-sm font-semibold text-content">Coba dengan dokumen contoh</h2>
                        <p class="mt-0.5 text-xs text-content-secondary">
                            Belum menyiapkan berkas fisik? Pakai contoh
                            <strong>Pembangunan Jalan Rabat Beton Banjar Melati (APBDes 2026)</strong>.
                        </p>
                    </div>

                    <Button
                        type="button"
                        variant="secondary"
                        size="sm"
                        :loading="form.processing && form.use_sample"
                        :disabled="form.processing"
                        class="shrink-0"
                        @click="submitSample"
                    >
                        Gunakan contoh RAB Melati
                    </Button>
                </div>
            </Card>

            <!-- Document Notice -->
            <Alert color="info" title="Kenapa harus dokumen asli?">
                Warga bisa mencocokkan tabel di Sonar Desa dengan dokumen berstempel.
            </Alert>
        </div>
    </PemdesLayout>
</template>
