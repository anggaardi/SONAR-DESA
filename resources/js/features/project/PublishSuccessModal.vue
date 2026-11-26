<script setup lang="ts">
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { formatRupiah } from '@/lib/format';

export interface PublishedProjectData {
    id: number;
    slug: string;
    name: string;
    type: string;
    fund_source: string;
    year: number;
    total_amount_rp: number;
    public_url: string;
    qr_svg: string;
    qr_poster_url: string;
}

interface Props {
    open: boolean;
    project: PublishedProjectData | null;
    villageName?: string;
    villageRegency?: string;
}

const props = withDefaults(defineProps<Props>(), {
    villageName: 'Desa Melinggih',
    villageRegency: 'Gianyar',
});

const emit = defineEmits<{
    close: [];
}>();

const copied = ref(false);

async function copyUrl(): Promise<void> {
    if (!props.project?.public_url) return;
    try {
        await navigator.clipboard.writeText(props.project.public_url);
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 2000);
    } catch {
        // Fallback for clipboard in unsupported environments
    }
}
</script>

<template>
    <Teleport to="body">
        <div v-if="open && project" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-content/50" aria-hidden="true" @click="emit('close')" />

            <!-- Modal Panel -->
            <div
                class="relative z-10 max-h-[95vh] w-full max-w-2xl overflow-y-auto rounded-lg border border-stroke bg-surface p-6 shadow-sm sm:p-8"
                role="dialog"
                aria-modal="true"
                aria-labelledby="success-modal-title"
            >
                <!-- Close Button -->
                <button
                    type="button"
                    class="absolute top-5 right-5 rounded-lg p-1.5 text-content-tertiary transition-colors hover:bg-surface-muted hover:text-content"
                    aria-label="Tutup modal"
                    @click="emit('close')"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M6 18L18 6M6 6l12 12"
                        />
                    </svg>
                </button>

                <!-- Header Icon & Title -->
                <div class="flex flex-col items-center text-center">
                    <div
                        class="mb-3 flex h-16 w-16 items-center justify-center rounded-full border-2 border-positive-border bg-positive-subtle text-positive"
                    >
                        <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2.5"
                                d="M5 13l4 4L19 7"
                            />
                        </svg>
                    </div>

                    <h2 id="success-modal-title" class="text-xl font-bold tracking-tight text-content sm:text-2xl">
                        Proyek Fisik Berhasil Diterbitkan ke Publik!
                    </h2>
                    <p class="mt-1 text-sm text-content-secondary">
                        Dokumen sah APBDes dan instrumen Cek Spek warga telah live di platform Sonar Desa.
                    </p>
                </div>

                <!-- Public Link Box -->
                <div
                    class="mt-6 flex items-center justify-between gap-3 rounded-lg border border-stroke bg-surface-subtle p-3"
                >
                    <div class="flex min-w-0 items-center gap-2.5">
                        <svg
                            class="h-5 w-5 shrink-0 text-primary"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="1.5"
                                d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"
                            />
                        </svg>
                        <span class="truncate text-xs font-medium text-content sm:text-sm">
                            {{ project.public_url }}
                        </span>
                    </div>
                    <button
                        type="button"
                        class="shrink-0 rounded-md border border-stroke bg-surface px-3 py-1.5 text-xs font-semibold text-primary transition-colors hover:bg-surface-muted"
                        @click="copyUrl"
                    >
                        {{ copied ? 'Tersalin!' : 'Salin Tautan' }}
                    </button>
                </div>

                <!-- A4 Poster Preview Card -->
                <div class="mt-6 flex justify-center">
                    <div
                        class="w-full max-w-sm rounded-lg border-2 border-stroke-strong bg-surface p-5 text-center text-content"
                    >
                        <!-- A4 Header -->
                        <div class="border-b-2 border-content pb-2">
                            <h3 class="text-xs font-bold tracking-wider text-content uppercase">
                                PEMERINTAH {{ villageName }}
                            </h3>
                            <p class="text-xs font-semibold text-content-secondary">
                                KABUPATEN {{ villageRegency.toUpperCase() }} · APBDES TAHUN {{ project.year }}
                            </p>
                        </div>

                        <!-- Project Name & Location -->
                        <div class="mt-3">
                            <h4 class="text-sm font-bold text-content">
                                {{ project.name }}
                            </h4>
                            <p class="text-xs text-content-secondary">{{ villageName }}, Kab. {{ villageRegency }}</p>
                        </div>

                        <!-- Pagu Box -->
                        <div class="my-3 rounded-md border border-stroke bg-surface-subtle px-3 py-2">
                            <p class="text-xs font-bold text-primary tabular-nums">
                                PAGU ANGGARAN: {{ formatRupiah(project.total_amount_rp) }}
                            </p>
                            <p class="text-xs text-content-secondary">Sumber: {{ project.fund_source }}</p>
                        </div>

                        <!-- QR Code Area -->
                        <div class="my-3 flex justify-center">
                            <div
                                class="flex h-36 w-36 items-center justify-center rounded-lg border-2 border-content bg-surface p-2"
                            >
                                <!-- eslint-disable vue/no-v-html -->
                                <div
                                    v-if="project.qr_svg"
                                    class="h-full w-full [&>svg]:h-full [&>svg]:w-full"
                                    v-html="project.qr_svg"
                                />
                                <!-- eslint-enable vue/no-v-html -->
                                <div v-else class="text-xs font-bold text-content-tertiary">QR CODE</div>
                            </div>
                        </div>

                        <!-- CTA Pengawasan Warga -->
                        <div class="mt-2 text-content">
                            <p class="text-xs font-bold tracking-wide uppercase">Ajakan Pengawasan Warga (Cek Spek)</p>
                            <p class="mt-0.5 text-xs text-content-secondary">
                                Pindai kode QR ini di lokasi proyek dengan kamera HP untuk menguji ketebalan, lebar, dan
                                transparansi anggaran fisik.
                            </p>
                            <p
                                class="mt-2 border-t border-dashed border-stroke-strong pt-1 text-xs font-medium text-content-secondary"
                            >
                                Dipasang pada Plang Fisik Proyek Desa · Pasang di Lokasi
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="mt-6 flex flex-col items-center gap-3">
                    <a
                        :href="project.qr_poster_url"
                        target="_blank"
                        class="inline-flex h-10 w-full max-w-sm items-center justify-center gap-2 rounded-md bg-primary px-5 text-sm font-medium text-content-inverse transition-colors hover:bg-primary-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                    >
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"
                            />
                        </svg>
                        <span>Cetak Poster QR Code A4</span>
                    </a>

                    <div class="flex flex-wrap items-center justify-center gap-4 text-xs font-semibold text-primary">
                        <a :href="project.public_url" target="_blank" class="hover:underline">
                            Buka Halaman Publik Warga (/p/{{ project.slug }}) &rarr;
                        </a>
                        <span class="text-stroke-strong" aria-hidden="true">&bull;</span>
                        <Link href="/desa" class="hover:underline"> Kembali ke Dashboard Pemdes </Link>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
