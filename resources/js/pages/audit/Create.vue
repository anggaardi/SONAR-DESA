<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Alert from '@/components/ui/Alert.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import AppLayout from '@/layouts/AppLayout.vue';

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
    aiReader?: {
        driver: string;
        provider: string;
        model: string;
        is_ai_active: boolean;
    };
}

defineProps<Props>();

const fileInputRef = ref<HTMLInputElement | null>(null);
const isDragging = ref(false);
const selectedFile = ref<File | null>(null);

const form = useForm({
    file: null as File | null,
    use_sample: false,
});

function handleFileChange(event: Event) {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        setFile(target.files[0]);
    }
}

function handleDrop(event: DragEvent) {
    isDragging.value = false;
    if (event.dataTransfer?.files && event.dataTransfer.files[0]) {
        setFile(event.dataTransfer.files[0]);
    }
}

function setFile(file: File) {
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

function removeFile() {
    selectedFile.value = null;
    form.file = null;
    if (fileInputRef.value) {
        fileInputRef.value.value = '';
    }
}

function submitUpload() {
    if (!form.file) {
        form.setError('file', 'Pilih berkas dokumen fisik RAB terlebih dahulu.');
        return;
    }

    form.use_sample = false;
    form.post('/audits', {
        forceFormData: true,
    });
}

function submitSample() {
    form.file = null;
    form.use_sample = true;
    form.post('/audits');
}

function formatBytes(bytes: number): string {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}
</script>

<template>
    <AppLayout>
        <Head :title="`Unggah Dokumen RAB - Pemerintah ${village.name}`" />

        <template #breadcrumb>
            <Link href="/" class="transition-colors hover:text-primary">Beranda</Link>
            <span>/</span>
            <Link href="/desa" class="transition-colors hover:text-primary"
                >Dashboard Pemerintah {{ village.name }}</Link
            >
            <span>/</span>
            <span class="font-medium text-content">Unggah Dokumen RAB</span>
        </template>

        <template #nav>
            <Link
                href="/desa"
                class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-content-secondary transition-colors hover:bg-surface-muted hover:text-content focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
            >
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="1.5"
                        d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"
                    />
                </svg>
                <span>Dashboard</span>
            </Link>
            <Link
                href="/audits/create"
                class="flex items-center gap-3 rounded-xl bg-primary-subtle px-3 py-2.5 text-sm font-semibold text-primary transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
            >
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="1.5"
                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                    />
                </svg>
                <span>Audit &amp; Ingesti RAB</span>
            </Link>
            <Link
                href="/desa#daftar-proyek"
                class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-content-secondary transition-colors hover:bg-surface-muted hover:text-content focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
            >
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="1.5"
                        d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"
                    />
                </svg>
                <span>Proyek Fisik</span>
            </Link>
            <Link
                :href="`/d/${village.slug}`"
                class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-content-secondary transition-colors hover:bg-surface-muted hover:text-content focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
            >
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="1.5"
                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                    />
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="1.5"
                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                    />
                </svg>
                <span>Lihat Halaman Publik</span>
            </Link>
        </template>

        <template #sidebarFooter>
            <div class="rounded-xl border border-stroke bg-surface-muted/50 p-3 text-xs text-content-secondary">
                <p class="font-medium text-content">Pemerintah {{ village.name }}</p>
                <p>{{ village.regency }} &middot; Operator Desa</p>
            </div>
        </template>

        <!-- Main Container -->
        <div class="mx-auto max-w-4xl space-y-6">
            <!-- Header Section -->
            <div class="border-b border-stroke pb-5">
                <div class="flex flex-wrap items-center gap-2">
                    <span
                        class="inline-flex items-center gap-1.5 rounded-full bg-primary-subtle px-3 py-1 text-xs font-semibold text-primary"
                    >
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M13 10V3L4 14h7v7l9-11h-7z"
                            />
                        </svg>
                        Fase 1: Ingesti Dokumen &amp; Audit Anggaran
                    </span>
                    <span
                        v-if="aiReader"
                        class="inline-flex items-center gap-1.5 rounded-full border border-stroke bg-surface-subtle px-3 py-1 font-mono text-[11px] font-medium text-content-secondary"
                    >
                        <span :class="['h-2 w-2 rounded-full', aiReader.is_ai_active ? 'bg-positive' : 'bg-info']" />
                        {{ aiReader.is_ai_active ? `AI Vision: ${aiReader.model}` : 'Reader: Fixture Offline' }}
                    </span>
                </div>
                <h1 class="mt-2 text-2xl font-bold tracking-tight text-content sm:text-3xl">
                    Unggah Dokumen RAB Fisik Berstempel
                </h1>
                <p class="mt-1 text-sm text-content-secondary">
                    Unggah berkas PDF atau foto scan lembar Rencana Anggaran Biaya (RAB) fisik yang telah ditandatangani
                    dan berstempel resmi. AI akan membaca tabel rincian belanja dan mencocokkannya ke Standar Harga
                    Kabupaten Gianyar.
                </p>
            </div>

            <!-- Lifecycle Steps Indicator -->
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-4">
                <div class="flex items-center gap-3 rounded-xl border border-primary/30 bg-primary-subtle/50 p-3">
                    <div
                        class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-bold text-content-inverse"
                    >
                        1
                    </div>
                    <div class="text-xs">
                        <p class="font-bold text-primary">Unggah Berkas</p>
                        <p class="text-content-secondary">PDF/Foto Asli</p>
                    </div>
                </div>
                <div
                    class="flex items-center gap-3 rounded-xl border border-stroke bg-surface p-3 text-content-secondary"
                >
                    <div
                        class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-surface-muted text-xs font-bold text-content-tertiary"
                    >
                        2
                    </div>
                    <div class="text-xs">
                        <p class="font-semibold text-content">Ekstraksi AI</p>
                        <p class="text-content-tertiary">Background Job</p>
                    </div>
                </div>
                <div
                    class="flex items-center gap-3 rounded-xl border border-stroke bg-surface p-3 text-content-secondary"
                >
                    <div
                        class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-surface-muted text-xs font-bold text-content-tertiary"
                    >
                        3
                    </div>
                    <div class="text-xs">
                        <p class="font-semibold text-content">Gatekeeper Review</p>
                        <p class="text-content-tertiary">Cek Baris Kuning</p>
                    </div>
                </div>
                <div
                    class="flex items-center gap-3 rounded-xl border border-stroke bg-surface p-3 text-content-secondary"
                >
                    <div
                        class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-surface-muted text-xs font-bold text-content-tertiary"
                    >
                        4
                    </div>
                    <div class="text-xs">
                        <p class="font-semibold text-content">Wizard Terbit</p>
                        <p class="text-content-tertiary">GPS &amp; Spek Live</p>
                    </div>
                </div>
            </div>

            <!-- Upload Area Card -->
            <Card class="p-6">
                <form class="space-y-6" @submit.prevent="submitUpload">
                    <!-- Dropzone -->
                    <div
                        :class="[
                            'relative flex flex-col items-center justify-center rounded-2xl border-2 border-dashed p-8 text-center transition-all duration-150 sm:p-12',
                            isDragging
                                ? 'scale-[1.01] border-primary bg-primary-subtle/40'
                                : selectedFile
                                  ? 'border-positive/50 bg-positive-subtle/30'
                                  : 'border-stroke hover:border-primary/50 hover:bg-surface-muted/50',
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

                        <!-- State: No File Selected -->
                        <template v-if="!selectedFile">
                            <div
                                class="flex h-16 w-16 items-center justify-center rounded-2xl bg-primary-subtle text-primary shadow-sm"
                            >
                                <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="1.5"
                                        d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"
                                    />
                                </svg>
                            </div>

                            <h3 class="mt-4 text-base font-bold text-content sm:text-lg">
                                Tarik &amp; Lepaskan Berkas RAB di Sini
                            </h3>
                            <p class="mt-1 text-xs text-content-secondary sm:text-sm">
                                atau klik tombol di bawah untuk memilih berkas dari komputer Anda
                            </p>

                            <div class="mt-4 flex flex-wrap items-center justify-center gap-3">
                                <Button type="button" variant="secondary" size="sm" @click="fileInputRef?.click()">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M12 4v16m8-8H4"
                                        />
                                    </svg>
                                    Pilih Berkas Dokumen
                                </Button>
                            </div>

                            <p class="mt-4 text-[11px] font-medium text-content-tertiary">
                                Format yang didukung: <strong>PDF, JPG, JPEG, PNG</strong> (Maksimal 10 MB).
                            </p>
                        </template>

                        <!-- State: File Selected -->
                        <template v-else>
                            <div
                                class="flex h-16 w-16 items-center justify-center rounded-2xl bg-positive-subtle text-positive shadow-sm"
                            >
                                <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="1.5"
                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                                    />
                                </svg>
                            </div>

                            <h4 class="mt-4 font-bold text-content">{{ selectedFile.name }}</h4>
                            <p class="mt-0.5 text-xs text-content-secondary">{{ formatBytes(selectedFile.size) }}</p>

                            <div class="mt-4 flex items-center gap-3">
                                <Button type="button" variant="ghost" size="sm" @click="removeFile">
                                    <svg
                                        class="h-4 w-4 text-negative"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                        />
                                    </svg>
                                    <span class="text-negative">Ganti Berkas</span>
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
                            <span>Mengunggah dokumen ke server...</span>
                            <span>{{ form.progress.percentage }}%</span>
                        </div>
                        <div class="h-2 w-full overflow-hidden rounded-full bg-surface-muted">
                            <div
                                class="h-full rounded-full bg-primary transition-all duration-200"
                                :style="{ width: `${form.progress.percentage}%` }"
                            />
                        </div>
                    </div>

                    <!-- Primary Actions -->
                    <div
                        class="flex flex-col items-center justify-between gap-4 border-t border-stroke pt-4 sm:flex-row"
                    >
                        <p class="text-xs text-content-secondary">
                            Berkas tersimpan aman di disk privat dan hanya dapat diakses oleh aparatur desa berwenang.
                        </p>

                        <Button type="submit" variant="primary" size="md" :disabled="!selectedFile || form.processing">
                            <svg v-if="form.processing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle
                                    class="opacity-25"
                                    cx="12"
                                    cy="12"
                                    r="10"
                                    stroke="currentColor"
                                    stroke-width="4"
                                />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
                            </svg>
                            <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M13 10V3L4 14h7v7l9-11h-7z"
                                />
                            </svg>
                            <span>{{ form.processing ? 'Sedang Memproses...' : 'Mulai Ekstraksi AI' }}</span>
                        </Button>
                    </div>
                </form>
            </Card>

            <!-- Demo Quick-Access Card for Evaluators / Judges -->
            <Card
                v-if="sampleAvailable"
                class="border-dashed border-primary/40 bg-gradient-to-r from-primary-subtle/40 via-surface to-primary-subtle/20 p-5"
            >
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-start gap-3">
                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary text-content-inverse shadow-sm"
                        >
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                                />
                            </svg>
                        </div>
                        <div>
                            <h4 class="text-sm font-bold text-content">Mode Demonstrasi Juri &amp; Penguji</h4>
                            <p class="mt-0.5 text-xs text-content-secondary">
                                Tidak memiliki berkas fisik saat pengujian? Gunakan dokumen contoh resmi
                                <strong>Pembangunan Jalan Rabat Beton Banjar Melati (APBDES 2026)</strong>.
                            </p>
                        </div>
                    </div>

                    <Button
                        type="button"
                        variant="secondary"
                        size="sm"
                        :disabled="form.processing"
                        class="shrink-0 border-primary/30 text-primary hover:bg-primary-subtle"
                        @click="submitSample"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
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
                        <span>Gunakan Contoh RAB Melati</span>
                    </Button>
                </div>
            </Card>

            <!-- Invariant Notice -->
            <div class="rounded-xl border border-stroke/80 bg-surface-muted/40 p-4 text-xs text-content-secondary">
                <div class="flex items-start gap-2.5">
                    <svg
                        class="h-4 w-4 shrink-0 text-content-tertiary"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                        />
                    </svg>
                    <div>
                        <strong class="font-semibold text-content">Ketentuan Hukum &amp; Invarian Sistem (D9):</strong>
                        Berkas dokumen asli berstempel wajib diunggah agar tabel rincian anggaran yang diakses warga di
                        halaman publik memiliki dasar bukti fisik yang sah dan dapat diverifikasi langsung.
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
