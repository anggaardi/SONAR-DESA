<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import QrScanner from 'qr-scanner';
import { nextTick, onUnmounted, ref, watch } from 'vue';
import Dialog from '@/components/ui/Dialog.vue';
import { formatRupiah } from '@/lib/format';
import { parsePlangQrTarget } from '@/lib/plangQr';

export interface QrQuickProject {
    id: number;
    slug: string;
    name: string;
    total_amount_rp: number;
    progress_pct: number;
}

interface Props {
    open: boolean;
    villageName: string;
    villageSlug: string;
    projects?: QrQuickProject[];
    isProjectPage?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    projects: () => [],
    isProjectPage: false,
});

const emit = defineEmits<{
    close: [];
    openPlang: [];
}>();

const manualInput = ref('');
const manualError = ref<string | null>(null);
const cameraState = ref<'idle' | 'starting' | 'active' | 'error'>('idle');
const cameraError = ref<string | null>(null);
const hasFlash = ref(false);
const flashOn = ref(false);
const videoEl = ref<HTMLVideoElement | null>(null);

let scanner: QrScanner | null = null;
let scanHandled = false;

function stopScanner(): void {
    if (scanner) {
        scanner.destroy();
        scanner = null;
    }
    hasFlash.value = false;
    flashOn.value = false;
    scanHandled = false;
    cameraState.value = 'idle';
}

function handleDecodedQr(raw: string): void {
    if (!raw || scanHandled) {
        return;
    }

    const origin = typeof window !== 'undefined' ? window.location.origin : undefined;
    const target = parsePlangQrTarget(raw, origin);
    if (target) {
        scanHandled = true;
        stopScanner();
        emit('close');
        router.visit(target);
        return;
    }

    cameraError.value = 'QR bukan tautan plang Sonar Desa.';
}

async function startScanner(): Promise<void> {
    if (cameraState.value === 'starting' || cameraState.value === 'active') {
        return;
    }

    cameraError.value = null;
    cameraState.value = 'starting';
    scanHandled = false;

    await nextTick();

    if (!videoEl.value) {
        cameraState.value = 'error';
        cameraError.value = 'Kamera tidak tersedia.';
        return;
    }

    try {
        scanner = new QrScanner(
            videoEl.value,
            (result) => {
                handleDecodedQr(result.data);
            },
            {
                preferredCamera: 'environment',
                highlightScanRegion: false,
                highlightCodeOutline: true,
                returnDetailedScanResult: true,
            },
        );

        await scanner.start();
        cameraState.value = 'active';
        hasFlash.value = await scanner.hasFlash().catch(() => false);
    } catch {
        stopScanner();
        cameraState.value = 'error';
        cameraError.value = 'Kamera tidak dapat diakses.';
    }
}

async function toggleFlash(): Promise<void> {
    if (!scanner || !hasFlash.value) {
        return;
    }

    try {
        await scanner.toggleFlash();
        flashOn.value = scanner.isFlashOn();
    } catch {
        // Ignore flash toggle failure on unsupported hardware
    }
}

function submitManualTarget(): void {
    manualError.value = null;
    const origin = typeof window !== 'undefined' ? window.location.origin : undefined;
    const target = parsePlangQrTarget(manualInput.value, origin);
    if (!target) {
        manualError.value = 'Kode atau tautan tidak valid.';
        return;
    }
    stopScanner();
    emit('close');
    router.visit(target);
}

function handleOpenPlangClick(): void {
    stopScanner();
    emit('close');
    emit('openPlang');
}

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            void startScanner();
        } else {
            stopScanner();
            manualError.value = null;
            cameraError.value = null;
        }
    },
    { immediate: true },
);

onUnmounted(() => {
    stopScanner();
});
</script>

<template>
    <Dialog :open="open" title="Pindai QR" @close="emit('close')">
        <div class="space-y-4">
            <!-- Viewfinder Kamera -->
            <div class="relative overflow-hidden rounded-xl border border-stroke bg-black">
                <div class="relative aspect-4/3 w-full">
                    <video ref="videoEl" playsinline muted class="h-full w-full object-cover" />

                    <!-- Overlay Frame Sudut Pemindaian -->
                    <div
                        v-if="cameraState === 'active'"
                        class="pointer-events-none absolute inset-0 flex items-center justify-center"
                    >
                        <div class="relative h-44 w-44 sm:h-48 sm:w-48">
                            <span
                                class="absolute top-0 left-0 h-6 w-6 rounded-tl-lg border-t-3 border-l-3 border-white"
                            />
                            <span
                                class="absolute top-0 right-0 h-6 w-6 rounded-tr-lg border-t-3 border-r-3 border-white"
                            />
                            <span
                                class="absolute bottom-0 left-0 h-6 w-6 rounded-bl-lg border-b-3 border-l-3 border-white"
                            />
                            <span
                                class="absolute right-0 bottom-0 h-6 w-6 rounded-br-lg border-r-3 border-b-3 border-white"
                            />
                        </div>
                    </div>

                    <!-- Tombol Flash (jika didukung perangkat) -->
                    <button
                        v-if="cameraState === 'active' && hasFlash"
                        type="button"
                        :aria-label="flashOn ? 'Matikan lampu kilat' : 'Nyalakan lampu kilat'"
                        class="absolute top-3 right-3 flex h-10 w-10 items-center justify-center rounded-full bg-black/55 text-white backdrop-blur-xs transition-colors hover:bg-black/75 focus-visible:outline focus-visible:outline-2 focus-visible:outline-white"
                        @click="toggleFlash"
                    >
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </button>

                    <!-- Status Memuat Kamera -->
                    <div
                        v-if="cameraState === 'starting'"
                        class="absolute inset-0 flex items-center justify-center bg-black/70 text-xs font-medium text-white"
                    >
                        Membuka kamera...
                    </div>

                    <!-- Status Error Kamera -->
                    <div
                        v-if="cameraState === 'error'"
                        class="absolute inset-0 flex flex-col items-center justify-center gap-3 bg-surface-subtle px-4 text-center"
                    >
                        <svg
                            class="h-8 w-8 text-content-tertiary"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="1.5"
                            aria-hidden="true"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M15.75 10.5l4.72-4.72a.75.75 0 011.28.53v11.38a.75.75 0 01-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 002.25-2.25v-9a2.25 2.25 0 00-2.25-2.25h-9A2.25 2.25 0 002.25 7.5v9a2.25 2.25 0 002.25 2.25z"
                            />
                        </svg>
                        <p class="text-xs font-medium text-content-secondary">
                            {{ cameraError ?? 'Kamera tidak dapat diakses.' }}
                        </p>
                        <button
                            type="button"
                            class="inline-flex h-9 items-center justify-center rounded-md bg-primary px-3.5 text-xs font-medium text-content-inverse transition-colors hover:bg-primary-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                            @click="startScanner"
                        >
                            Coba lagi
                        </button>
                    </div>
                </div>

                <!-- Pesan jika QR terbaca tapi bukan tautan Sonar Desa -->
                <p
                    v-if="cameraState === 'active' && cameraError"
                    class="border-t border-warning-border bg-warning-subtle px-3 py-2 text-center text-xs font-medium text-warning"
                >
                    {{ cameraError }}
                </p>
            </div>

            <!-- Input Manual Ringkas -->
            <form class="space-y-1.5" @submit.prevent="submitManualTarget">
                <div class="flex gap-2">
                    <input
                        id="manual-qr-input"
                        v-model="manualInput"
                        type="text"
                        aria-label="Kode atau tautan proyek"
                        placeholder="Kode atau tautan proyek"
                        :aria-invalid="Boolean(manualError)"
                        :aria-describedby="manualError ? 'manual-qr-error' : undefined"
                        class="h-11 min-w-0 flex-1 rounded-md border border-stroke-strong bg-surface px-3 text-sm text-content placeholder:text-content-tertiary focus:border-primary focus:outline-none"
                    />
                    <button
                        type="submit"
                        class="inline-flex h-11 shrink-0 items-center justify-center rounded-md border border-stroke-strong bg-surface px-4 text-sm font-medium text-content transition-colors hover:bg-surface-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                    >
                        Buka
                    </button>
                </div>
                <p v-if="manualError" id="manual-qr-error" class="text-xs text-negative">
                    {{ manualError }}
                </p>
            </form>

            <!-- Daftar Proyek Desa / Pintasan Plang -->
            <div v-if="!isProjectPage && projects.length > 0" class="space-y-2 border-t border-stroke pt-3">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-xs font-semibold text-content-secondary">
                        {{ villageName.startsWith('Desa ') ? villageName : `Desa ${villageName}` }}
                    </p>
                    <button
                        type="button"
                        class="text-xs font-medium text-primary hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                        @click="handleOpenPlangClick"
                    >
                        QR Plang &rarr;
                    </button>
                </div>
                <ul class="max-h-40 space-y-1.5 overflow-y-auto pr-0.5">
                    <li v-for="item in projects" :key="item.id">
                        <Link
                            :href="`/p/${item.slug}`"
                            class="flex items-center justify-between gap-3 rounded-lg border border-stroke bg-surface px-3 py-2 text-left transition-colors hover:border-primary hover:bg-primary-subtle"
                            @click="emit('close')"
                        >
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-content">{{ item.name }}</p>
                                <p class="text-xs text-content-tertiary tabular-nums">
                                    {{ formatRupiah(item.total_amount_rp) }} &middot; {{ item.progress_pct }}%
                                </p>
                            </div>
                            <span class="shrink-0 text-xs font-semibold text-primary">Buka</span>
                        </Link>
                    </li>
                </ul>
            </div>

            <div
                v-else-if="isProjectPage"
                class="flex flex-wrap items-center justify-between gap-2 border-t border-stroke pt-3"
            >
                <button
                    type="button"
                    class="inline-flex h-10 items-center justify-center rounded-md border border-stroke-strong bg-surface px-3.5 text-sm font-medium text-content transition-colors hover:bg-surface-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                    @click="handleOpenPlangClick"
                >
                    Lihat QR Plang
                </button>
                <Link
                    :href="`/d/${villageSlug}`"
                    class="inline-flex h-10 items-center text-sm font-medium text-primary hover:underline"
                    @click="emit('close')"
                >
                    Daftar proyek &rarr;
                </Link>
            </div>
        </div>

        <template #actions>
            <button
                type="button"
                class="inline-flex h-10 w-full items-center justify-center rounded-md border border-stroke-strong bg-surface px-4 text-sm font-medium text-content transition-colors hover:bg-surface-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary sm:w-auto"
                @click="emit('close')"
            >
                Tutup
            </button>
        </template>
    </Dialog>
</template>
