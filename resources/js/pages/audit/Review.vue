<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import Dialog from '@/components/ui/Dialog.vue';
import PriceLabel from '@/components/ui/PriceLabel.vue';
import PublishSuccessModal, { type PublishedProjectData } from '@/features/project/PublishSuccessModal.vue';
import PublishWizardModal from '@/features/project/PublishWizardModal.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate, formatRupiah } from '@/lib/format';

interface AuditItemData {
    id: number;
    position: number;
    raw_name: string;
    volume: string | null;
    unit: string | null;
    unit_price_rp: number | null;
    amount_rp: number | null;
    standard_id: number | null;
    standard: {
        id: number;
        code: string;
        name: string;
        unit: string;
        price_rp: number;
    } | null;
    label: 'within_standard' | 'above_standard' | 'needs_check' | 'no_reference';
    over_pct: number | null;
    diff_rp: number | null;
    corrected: boolean;
    is_yellow: boolean;
    reason_text: string | null;
    doubt_fields?: string[];
}

interface Props {
    audit: {
        id: number;
        status: string;
        source: string;
        file_path: string | null;
        file_name: string;
        file_url: string | null;
        written_total_rp: number | null;
        total_ack: boolean;
        read_ms?: number | null;
        error_code?: string | null;
        created_at: string | null;
    };
    aiReader?: {
        driver: string;
        provider: string;
        model: string;
        is_ai_active: boolean;
    };
    items: AuditItemData[];
    summary: {
        total_items: number;
        needs_check_count: number;
        above_standard_count: number;
        within_standard_count: number;
        no_reference_count: number;
        total_calculated_rp: number;
        is_total_match: boolean;
        total_diff_rp: number;
        can_publish: boolean;
        publish_block_reason?: string | null;
    };
    project: {
        id: number;
        slug: string;
        name: string;
        type: string;
        fund_source: string;
        year: number;
        lat: string | null;
        lng: string | null;
        is_published: boolean;
        measurements: Array<{
            name: string;
            value: string;
            unit: string;
            how_to: string | null;
            origin: string;
        }>;
    } | null;
    village: {
        id: number;
        name: string;
        slug: string;
        regency: string;
    };
    priceStandards: Array<{
        id: number;
        code: string;
        name: string;
        unit: string;
        price_rp: number;
    }>;
    types: Array<{ value: string; label: string }>;
    templates: Record<string, Array<{ name: string; unit: string; default_value: string; how_to: string }>>;
    fundSources: string[];
}

const props = defineProps<Props>();

const page = usePage();

// Filter Tabs
type FilterTab = 'all' | 'needs_check' | 'above_standard' | 'within_standard' | 'no_reference';
const activeTab = ref<FilterTab>(props.summary.needs_check_count > 0 ? 'needs_check' : 'all');

const filteredItems = computed(() => {
    if (activeTab.value === 'all') return props.items;
    return props.items.filter((item) => {
        if (activeTab.value === 'needs_check') {
            return item.is_yellow;
        }
        return item.label === activeTab.value;
    });
});

// Polling for Audit Reading Status
let pollTimer: ReturnType<typeof setInterval> | null = null;
const pollSeconds = ref(0);

function startPolling(): void {
    if (pollTimer) return;
    pollSeconds.value = 0;
    pollTimer = setInterval(() => {
        pollSeconds.value += 2;
        if (props.audit.status !== 'reading' || pollSeconds.value >= 180) {
            stopPolling();
            return;
        }
        router.reload({
            only: ['audit', 'items', 'summary', 'project'],
        });
    }, 2000);
}

function stopPolling(): void {
    if (pollTimer) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
}

watch(
    () => props.audit.status,
    (newStatus) => {
        if (newStatus === 'reading') {
            startPolling();
        } else {
            stopPolling();
        }
    },
    { immediate: true },
);

onUnmounted(() => {
    stopPolling();
});

// Retry Reading (OCR)
const isRetrying = ref(false);
function retryReading(): void {
    isRetrying.value = true;
    router.post(
        `/audits/${props.audit.id}/retry`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                isRetrying.value = false;
            },
        },
    );
}

const isEditableAudit = computed(() => {
    return props.audit.status !== 'published' && !props.project?.is_published;
});

const replaceFileInputRef = ref<HTMLInputElement | null>(null);
const isReplacingFile = ref(false);

function triggerReplaceFile(): void {
    if (replaceFileInputRef.value) {
        replaceFileInputRef.value.value = '';
        replaceFileInputRef.value.click();
    }
}

function handleReplaceFileChange(event: Event): void {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (!file) return;

    isReplacingFile.value = true;
    router.post(
        `/audits/${props.audit.id}/replace-file`,
        { file },
        {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => {
                isReplacingFile.value = false;
            },
        },
    );
}

const isDeletingAudit = ref(false);
function deleteAudit(): void {
    if (!confirm(`Yakin ingin menghapus dokumen RAB "${props.audit.file_name}" beserta draf audit ini?`)) {
        return;
    }

    isDeletingAudit.value = true;
    router.delete(`/audits/${props.audit.id}`, {
        onFinish: () => {
            isDeletingAudit.value = false;
        },
    });
}

// Ack Total Check (F3)
const isAckingTotal = ref(false);
function ackTotal(): void {
    isAckingTotal.value = true;
    router.post(
        `/audits/${props.audit.id}/ack-total`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                isAckingTotal.value = false;
            },
        },
    );
}

// Add Item Modal
const isAddModalOpen = ref(false);
const isSubmittingAdd = ref(false);
const addForm = ref({
    raw_name: '',
    volume: '1',
    unit: '',
    unit_price_rp: 0,
    amount_rp: 0,
    standard_id: null as number | null,
});

function openAddModal(): void {
    addForm.value = {
        raw_name: '',
        volume: '1',
        unit: '',
        unit_price_rp: 0,
        amount_rp: 0,
        standard_id: null,
    };
    standardSearch.value = '';
    isAddModalOpen.value = true;
}

function selectAddStandard(std: (typeof props.priceStandards)[0]): void {
    addForm.value.standard_id = std.id;
    standardSearch.value = `${std.name} (${std.unit}) - ${formatRupiah(std.price_rp)}`;
    if (!addForm.value.unit) {
        addForm.value.unit = std.unit;
    }
    if (!addForm.value.unit_price_rp) {
        addForm.value.unit_price_rp = std.price_rp;
    }
}

function submitAdd(): void {
    isSubmittingAdd.value = true;
    router.post(
        `/audits/${props.audit.id}/items`,
        {
            raw_name: addForm.value.raw_name,
            volume: addForm.value.volume,
            unit: addForm.value.unit,
            unit_price_rp: addForm.value.unit_price_rp,
            amount_rp: addForm.value.amount_rp || null,
            standard_id: addForm.value.standard_id,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                isSubmittingAdd.value = false;
                isAddModalOpen.value = false;
            },
        },
    );
}

// Wizard Modal State
const isWizardOpen = ref(false);

// Success Modal State
const isSuccessOpen = ref(false);
const publishedProjectData = ref<PublishedProjectData | null>(null);

// Watch session flash for published project
watch(
    () => page.props.flash?.published_project as PublishedProjectData | undefined,
    (flashProject) => {
        if (flashProject) {
            publishedProjectData.value = flashProject;
            isSuccessOpen.value = true;
        }
    },
    { immediate: true },
);

// Edit Item Inline Modal
const isEditModalOpen = ref(false);
const editingItem = ref<AuditItemData | null>(null);
const editForm = ref({
    raw_name: '',
    volume: '',
    unit: '',
    unit_price_rp: 0,
    amount_rp: 0,
    standard_id: null as number | null,
});
const standardSearch = ref('');
const isSubmittingEdit = ref(false);

const filteredStandards = computed(() => {
    if (!standardSearch.value.trim()) return props.priceStandards.slice(0, 50);
    const q = standardSearch.value.toLowerCase();
    return props.priceStandards.filter((s) => s.name.toLowerCase().includes(q) || s.code.toLowerCase().includes(q));
});

function openEditModal(item: AuditItemData): void {
    editingItem.value = item;
    editForm.value = {
        raw_name: item.raw_name,
        volume: item.volume ?? '1',
        unit: item.unit ?? '',
        unit_price_rp: item.unit_price_rp ?? 0,
        amount_rp: item.amount_rp ?? 0,
        standard_id: item.standard_id,
    };
    standardSearch.value = item.standard?.name ?? '';
    isEditModalOpen.value = true;
}

function selectStandard(std: (typeof props.priceStandards)[0]): void {
    editForm.value.standard_id = std.id;
    standardSearch.value = `${std.name} (${std.unit}) - ${formatRupiah(std.price_rp)}`;
    if (!editForm.value.unit) {
        editForm.value.unit = std.unit;
    }
}

function clearStandard(): void {
    editForm.value.standard_id = null;
    standardSearch.value = '';
}

function submitEdit(): void {
    if (!editingItem.value) return;
    isSubmittingEdit.value = true;

    router.patch(
        `/audits/${props.audit.id}/items/${editingItem.value.id}`,
        {
            raw_name: editForm.value.raw_name,
            volume: editForm.value.volume,
            unit: editForm.value.unit,
            unit_price_rp: editForm.value.unit_price_rp,
            amount_rp: editForm.value.amount_rp || null,
            standard_id: editForm.value.standard_id,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                isSubmittingEdit.value = false;
                isEditModalOpen.value = false;
                editingItem.value = null;
            },
        },
    );
}

// Quick Actions for Yellow Rows
function confirmItem(item: AuditItemData): void {
    router.post(
        `/audits/${props.audit.id}/items/${item.id}/confirm`,
        {},
        {
            preserveScroll: true,
        },
    );
}

function deleteItem(item: AuditItemData): void {
    if (confirm(`Yakin ingin menghapus baris belanja "${item.raw_name}"?`)) {
        router.delete(`/audits/${props.audit.id}/items/${item.id}`, {
            preserveScroll: true,
        });
    }
}

// Zoom control simulation
const zoomLevel = ref(100);
function zoomIn(): void {
    if (zoomLevel.value < 200) zoomLevel.value += 25;
}
function zoomOut(): void {
    if (zoomLevel.value > 50) zoomLevel.value -= 25;
}
</script>

<template>
    <AppLayout>
        <Head :title="`Review Dokumen RAB #${audit.id} - ${village.name}`" />

        <template #breadcrumb>
            <Link href="/" class="transition-colors hover:text-primary">Beranda</Link>
            <span>/</span>
            <Link href="/desa" class="transition-colors hover:text-primary">Pemerintah Desa</Link>
            <span>/</span>
            <span class="font-medium text-content">Review RAB #{{ audit.id }}</span>
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
            <div class="flex items-center justify-between text-xs text-content-secondary">
                <div class="flex min-w-0 flex-col">
                    <span class="truncate font-medium text-content">{{ village.name }}</span>
                    <span class="truncate text-content-tertiary">Kab. {{ village.regency }}</span>
                </div>
                <Link href="/logout" method="post" as="button" class="font-semibold text-negative hover:underline">
                    Keluar
                </Link>
            </div>
        </template>

        <!-- Main Review Content -->
        <div class="space-y-6">
            <!-- Header Section -->
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-content sm:text-3xl">
                        Verifikasi Hasil Ekstraksi RAB & Gatekeeper
                    </h1>
                    <p class="mt-1 text-sm text-content-secondary">
                        Cocokkan data tabel digital terhadap berkas fisik berstempel. Seluruh baris kuning wajib
                        diselesaikan sebelum terbit (D1).
                    </p>
                </div>

                <!-- File Info Badge -->
                <div
                    class="flex shrink-0 items-center gap-3 rounded-xl border border-stroke bg-surface-subtle p-3 shadow-xs"
                >
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary-subtle text-primary">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="1.5"
                                d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"
                            />
                        </svg>
                    </div>
                    <div>
                        <span
                            class="block max-w-[200px] truncate text-xs font-bold text-content"
                            :title="audit.file_name"
                        >
                            {{ audit.file_name }}
                        </span>
                        <span class="block text-[11px] text-content-tertiary">
                            {{ audit.created_at ? formatDate(audit.created_at) : 'Hari ini' }} &middot;
                            {{
                                audit.source === 'ocr_ai' || audit.source === 'ocr'
                                    ? 'Sumber: AI Vision'
                                    : audit.source === 'demo_sample'
                                      ? 'Sumber: Contoh RAB (AI)'
                                      : 'Unggah Fisik'
                            }}
                            <template v-if="audit.read_ms">
                                &middot; {{ (audit.read_ms / 1000).toFixed(1) }} dtk
                            </template>
                        </span>
                        <span
                            v-if="aiReader?.model"
                            class="mt-0.5 inline-block rounded bg-surface px-1.5 py-0.5 font-mono text-[10px] text-content-secondary"
                        >
                            Model: {{ aiReader.model }}
                        </span>
                    </div>

                    <input
                        ref="replaceFileInputRef"
                        type="file"
                        accept=".pdf,application/pdf,image/jpeg,image/png,image/jpg"
                        class="hidden"
                        @change="handleReplaceFileChange"
                    />

                    <div v-if="isEditableAudit" class="ml-2 flex items-center gap-1.5 border-l border-stroke pl-3">
                        <button
                            type="button"
                            class="inline-flex items-center gap-1 rounded-lg border border-stroke bg-surface px-2.5 py-1.5 text-xs font-semibold text-content transition-colors hover:border-primary/40 hover:bg-surface-muted hover:text-primary"
                            :disabled="isReplacingFile"
                            title="Ganti berkas dokumen RAB dan ekstrak ulang dengan AI"
                            @click="triggerReplaceFile"
                        >
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"
                                />
                            </svg>
                            <span>{{ isReplacingFile ? 'Mengunggah...' : 'Ganti File' }}</span>
                        </button>

                        <button
                            type="button"
                            class="inline-flex items-center gap-1 rounded-lg border border-negative/30 bg-negative-subtle/20 px-2.5 py-1.5 text-xs font-semibold text-negative transition-colors hover:bg-negative hover:text-white"
                            :disabled="isDeletingAudit"
                            title="Hapus dokumen RAB ini"
                            @click="deleteAudit"
                        >
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                />
                            </svg>
                            <span>{{ isDeletingAudit ? 'Menghapus...' : 'Hapus' }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Flash Alert Success -->
            <div
                v-if="page.props.flash?.success"
                class="rounded-lg border border-positive-border bg-positive-subtle p-3.5 text-xs font-semibold text-positive"
            >
                {{ page.props.flash.success }}
            </div>

            <!-- DUAL-VIEW WORKSPACE CONTAINER -->
            <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-12">
                <!-- PANEL KIRI: PENAMPIL DOKUMEN ASLI BERSTEMPEL (D9) -->
                <div
                    class="flex flex-col overflow-hidden rounded-xl border border-stroke bg-surface shadow-xs lg:col-span-5"
                >
                    <!-- Viewer Toolbar -->
                    <div class="flex items-center justify-between border-b border-stroke bg-surface-subtle px-4 py-2.5">
                        <div class="flex items-center gap-2">
                            <svg class="h-4 w-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                                />
                            </svg>
                            <span class="text-xs font-bold text-content">Berkas RAB Asli Berstempel</span>
                        </div>

                        <div class="flex items-center gap-1.5">
                            <button
                                type="button"
                                class="rounded border border-stroke bg-surface px-2 py-0.5 text-xs font-bold text-content hover:bg-surface-muted"
                                @click="zoomOut"
                            >
                                &minus;
                            </button>
                            <span class="px-1 font-mono text-[11px] font-medium text-content-secondary">
                                {{ zoomLevel }}%
                            </span>
                            <button
                                type="button"
                                class="rounded border border-stroke bg-surface px-2 py-0.5 text-xs font-bold text-content hover:bg-surface-muted"
                                @click="zoomIn"
                            >
                                +
                            </button>
                            <a
                                v-if="audit.file_url"
                                :href="audit.file_url"
                                target="_blank"
                                class="ml-1 rounded border border-stroke bg-surface p-1 text-content-secondary hover:bg-surface-muted hover:text-content"
                                title="Buka berkas di tab baru"
                            >
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"
                                    />
                                </svg>
                            </a>
                        </div>
                    </div>

                    <!-- Document Display Area -->
                    <div
                        class="relative flex max-h-[800px] min-h-[580px] justify-center overflow-auto bg-surface-muted p-4"
                    >
                        <!-- Direct Iframe if URL is PDF -->
                        <iframe
                            v-if="audit.file_url && audit.file_name.toLowerCase().endsWith('.pdf')"
                            :src="audit.file_url"
                            class="h-[680px] w-full rounded border border-stroke bg-white shadow-sm"
                            title="Pratinjau Berkas PDF"
                        />

                        <!-- Direct Image Preview if URL is Image -->
                        <img
                            v-else-if="
                                audit.file_url &&
                                (audit.file_name.toLowerCase().endsWith('.png') ||
                                    audit.file_name.toLowerCase().endsWith('.jpg') ||
                                    audit.file_name.toLowerCase().endsWith('.jpeg'))
                            "
                            :src="audit.file_url"
                            class="max-h-[700px] w-auto rounded border border-stroke bg-white object-contain shadow-sm"
                            :style="{ transform: `scale(${zoomLevel / 100})`, transformOrigin: 'top center' }"
                            alt="Pratinjau Berkas Dokumen RAB"
                        />

                        <!-- Fallback Paper Simulation (matching FigCraft design) -->
                        <div
                            v-else
                            class="border-stone-300 text-stone-900 w-full max-w-sm rounded-sm border bg-white p-5 shadow-md transition-transform"
                            :style="{ transform: `scale(${zoomLevel / 100})`, transformOrigin: 'top center' }"
                        >
                            <!-- Kop Surat Fisik -->
                            <div class="border-stone-800 border-b-2 pb-2 text-center">
                                <h4 class="text-stone-900 text-[11px] font-bold">
                                    PEMERINTAH KABUPATEN {{ village.regency.toUpperCase() }}
                                </h4>
                                <h5 class="text-stone-700 text-[10px] font-semibold">
                                    KECAMATAN PAYANGAN - DESA {{ village.name.toUpperCase() }}
                                </h5>
                                <p class="text-stone-900 text-[10px] font-bold">
                                    RENCANA ANGGARAN BIAYA (RAB) APBDES 2026
                                </p>
                            </div>

                            <div class="text-stone-800 mt-3 text-[9.5px]">
                                <p class="font-semibold">
                                    Kegiatan: {{ project?.name ?? 'Pembangunan Infrastruktur Desa' }}
                                </p>
                                <p class="text-stone-600">Lokasi: Banjar Melati, {{ village.name }}</p>
                            </div>

                            <!-- Mini Items Table -->
                            <div class="border-stone-200 mt-3 border">
                                <div class="bg-stone-100 text-stone-700 grid grid-cols-12 p-1 text-[8px] font-bold">
                                    <div class="col-span-1">No</div>
                                    <div class="col-span-6">Uraian Belanja</div>
                                    <div class="col-span-2">Vol</div>
                                    <div class="col-span-3 text-right">Subtotal</div>
                                </div>
                                <div
                                    v-for="(it, i) in items.slice(0, 8)"
                                    :key="it.id"
                                    class="border-stone-100 text-stone-700 grid grid-cols-12 border-t p-1 text-[8px]"
                                >
                                    <div class="col-span-1">{{ i + 1 }}</div>
                                    <div class="col-span-6 truncate">{{ it.raw_name }}</div>
                                    <div class="col-span-2">{{ it.volume }} {{ it.unit }}</div>
                                    <div class="col-span-3 text-right font-semibold">
                                        {{ it.amount_rp ? formatRupiah(it.amount_rp) : '-' }}
                                    </div>
                                </div>
                            </div>

                            <!-- Stempel Basah Simulation -->
                            <div class="mt-8 flex items-end justify-between px-2">
                                <div class="text-stone-600 text-center text-[8.5px]">
                                    <p>Mengetahui,</p>
                                    <p class="text-stone-800 font-bold">Perbekel / Kepala Desa</p>
                                    <div class="my-2 flex justify-center">
                                        <!-- Stempel Ungu -->
                                        <div
                                            class="border-purple-800 text-purple-800 flex h-14 w-14 flex-col items-center justify-center rounded-full border-2 border-dashed p-1 text-center text-[5.5px] leading-tight font-bold"
                                        >
                                            <span>PEMERINTAH</span>
                                            <span>GIANYAR</span>
                                            <span class="text-[5px]">* {{ village.name.toUpperCase() }} *</span>
                                        </div>
                                    </div>
                                    <p class="text-stone-900 font-semibold">( I Made Sudarma )</p>
                                </div>

                                <div class="text-stone-600 text-center text-[8.5px]">
                                    <p>Pelaksana,</p>
                                    <p class="text-stone-800 font-bold">TPK {{ village.name }}</p>
                                    <div class="h-14" />
                                    <p class="text-stone-900 font-semibold">( Wayan Arjana )</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Dokumen Terverifikasi D9 Badge -->
                    <div class="border-t border-stroke bg-surface px-4 py-2.5">
                        <div class="flex items-center gap-2 text-xs font-semibold text-primary">
                            <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"
                                />
                            </svg>
                            <span>Dokumen Berstempel Basah Terverifikasi (D9)</span>
                        </div>
                    </div>
                </div>

                <!-- PANEL KANAN: TABEL HASIL EKSTRAKSI & GATEKEEPER -->
                <div class="space-y-4 lg:col-span-7">
                    <!-- State A: Reading (Loading & Polling) -->
                    <div
                        v-if="audit.status === 'reading'"
                        class="rounded-2xl border border-primary/30 bg-surface p-8 text-center shadow-xs"
                    >
                        <div
                            class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-primary-subtle text-primary shadow-xs"
                        >
                            <svg class="h-8 w-8 animate-spin" fill="none" viewBox="0 0 24 24">
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
                        </div>
                        <h3 class="mt-4 text-lg font-bold text-content">AI Sedang Mengekstrak Dokumen RAB...</h3>
                        <p class="mx-auto mt-1 max-w-md text-xs text-content-secondary">
                            Sistem AI Vision sedang membaca teks dan angka tabel rincian belanja dari lembar berkas
                            fisik, lalu mencocokkannya ke Standar Harga (SSH) Gianyar. Halaman ini akan diperbarui
                            secara otomatis.
                        </p>
                        <div
                            class="mt-5 inline-flex items-center gap-2 rounded-full border border-stroke bg-surface-muted px-4 py-1.5 text-xs font-semibold text-content-secondary"
                        >
                            <span class="relative flex h-2 w-2">
                                <span
                                    class="absolute inline-flex h-full w-full animate-ping rounded-full bg-primary opacity-75"
                                ></span>
                                <span class="relative inline-flex h-2 w-2 rounded-full bg-primary"></span>
                            </span>
                            <span>Memproses background job (± {{ pollSeconds }} detik)...</span>
                        </div>
                    </div>

                    <!-- State B: Read Failed (Error State & Recovery) -->
                    <div
                        v-else-if="audit.status === 'read_failed'"
                        class="rounded-2xl border border-negative/30 bg-negative-subtle/20 p-6 shadow-xs"
                    >
                        <div class="flex items-start gap-4">
                            <div
                                class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-negative text-content-inverse shadow-xs"
                            >
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                                    />
                                </svg>
                            </div>
                            <div class="flex-1">
                                <h3 class="text-base font-bold text-content">
                                    Ekstraksi Otomatis AI Mengalami Kendala
                                </h3>
                                <p class="mt-1 text-xs text-content-secondary">
                                    Dokumen tidak dapat diekstrak otomatis (Kode:
                                    <code
                                        class="rounded bg-surface px-1.5 py-0.5 font-mono text-[11px] text-negative"
                                        >{{ audit.error_code || 'EXTRACTION_FAILED' }}</code>).
                                    <template v-if="audit.error_code === 'NO_RAB_ITEMS_FOUND'">
                                        AI Vision tidak menemukan baris rincian tabel RAB pada berkas ini. Pastikan
                                        berkas yang diunggah adalah lembar dokumen/foto tabel RAB yang jelas.
                                    </template>
                                    <template v-else-if="audit.error_code === 'AI_KEY_MISSING'">
                                        Kunci API AI Vision belum dikonfigurasi pada server.
                                    </template>
                                    <template v-else>
                                        Berkas asli Anda tetap tersimpan aman. Anda dapat mencoba ekstraksi ulang atau
                                        menginput rincian belanja secara manual.
                                    </template>
                                </p>
                                <div class="mt-4 flex flex-wrap gap-3">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1.5 rounded-lg bg-primary px-4 py-2 text-xs font-bold text-content-inverse shadow-xs transition-colors hover:bg-primary-hover"
                                        :disabled="isRetrying"
                                        @click="retryReading"
                                    >
                                        <svg
                                            class="h-4 w-4"
                                            :class="{ 'animate-spin': isRetrying }"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                                            />
                                        </svg>
                                        <span>{{ isRetrying ? 'Memulai Ulang...' : 'Coba Ekstraksi Ulang' }}</span>
                                    </button>
                                    <button
                                        v-if="isEditableAudit"
                                        type="button"
                                        class="inline-flex items-center gap-1.5 rounded-lg border border-stroke bg-surface px-4 py-2 text-xs font-semibold text-content shadow-xs transition-colors hover:bg-surface-muted"
                                        :disabled="isReplacingFile"
                                        @click="triggerReplaceFile"
                                    >
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"
                                            />
                                        </svg>
                                        <span>{{ isReplacingFile ? 'Mengunggah...' : 'Ganti Berkas RAB' }}</span>
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1.5 rounded-lg border border-stroke bg-surface px-4 py-2 text-xs font-semibold text-content shadow-xs transition-colors hover:bg-surface-muted"
                                        @click="openAddModal"
                                    >
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M12 4v16m8-8H4"
                                            />
                                        </svg>
                                        <span>Isi Baris Manual</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- State C: Normal Audit Review Flow -->
                    <template v-else>
                        <!-- 1. Banner Gatekeeper Baris Kuning (D1) -->
                        <div
                            v-if="summary.needs_check_count > 0"
                            class="rounded-xl border border-warning-border bg-warning-subtle p-4 text-warning"
                        >
                            <div class="flex items-start gap-3">
                                <svg
                                    class="mt-0.5 h-5 w-5 shrink-0"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                                    />
                                </svg>
                                <div>
                                    <h3 class="text-sm font-bold">
                                        Publikasi Terkunci: {{ summary.needs_check_count }} Baris Perlu Diperiksa (D1)
                                    </h3>
                                    <p class="mt-0.5 text-xs text-warning/90">
                                        Terdapat ketidaksesuaian satuan atau deviasi hitung. Selesaikan seluruh baris
                                        kuning di bawah agar tombol publikasi terbuka.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div
                            v-else
                            class="rounded-xl border border-positive-border bg-positive-subtle p-4 text-positive"
                        >
                            <div class="flex items-start gap-3">
                                <svg
                                    class="mt-0.5 h-5 w-5 shrink-0"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2.5"
                                        d="M5 13l4 4L19 7"
                                    />
                                </svg>
                                <div>
                                    <h3 class="text-sm font-bold">Gatekeeper D1 Lolos: 0 Baris Kuning</h3>
                                    <p class="mt-0.5 text-xs text-positive/90">
                                        Semua rincian belanja telah terverifikasi. Anda dapat melanjutkan ke wizard
                                        penerbitan proyek fisik ke hadapan warga desa.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- 2. Banner Cek Konsistensi Total (F3) -->
                        <div
                            class="flex flex-col gap-2 rounded-lg border border-stroke bg-surface px-4 py-3 shadow-xs sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div class="flex items-center gap-2.5">
                                <span
                                    :class="[
                                        'flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-bold',
                                        summary.is_total_match
                                            ? 'bg-positive-subtle text-positive'
                                            : 'bg-warning-subtle text-warning',
                                    ]"
                                >
                                    <svg
                                        v-if="summary.is_total_match"
                                        class="h-3.5 w-3.5"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="3"
                                            d="M5 13l4 4L19 7"
                                        />
                                    </svg>
                                    <span v-else>!</span>
                                </span>
                                <div>
                                    <span class="text-xs font-bold text-content">Konsistensi Total (F3):</span>
                                    <span class="ml-1 text-xs text-content-secondary">
                                        <template v-if="summary.is_total_match">
                                            Total Tertulis
                                            {{ formatRupiah(audit.written_total_rp ?? summary.total_calculated_rp) }}
                                            cocok dengan Jumlah Baris
                                            <span class="font-semibold text-positive">(Sesuai)</span>
                                        </template>
                                        <template v-else>
                                            Total Tertulis {{ formatRupiah(audit.written_total_rp ?? 0) }} vs Jumlah
                                            Baris {{ formatRupiah(summary.total_calculated_rp) }}
                                            <span class="font-semibold text-warning"
                                                >(Selisih {{ formatRupiah(summary.total_diff_rp) }})</span
                                            >
                                            <span
                                                v-if="audit.total_ack"
                                                class="ml-1 rounded bg-positive-subtle px-1.5 py-0.5 text-[10px] font-semibold text-positive"
                                            >
                                                Dikonfirmasi
                                            </span>
                                        </template>
                                    </span>
                                </div>
                            </div>

                            <div v-if="!summary.is_total_match && !audit.total_ack" class="shrink-0">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-warning/40 bg-warning-subtle px-3 py-1.5 text-xs font-semibold text-warning hover:bg-warning-subtle/80"
                                    :disabled="isAckingTotal"
                                    @click="ackTotal"
                                >
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M5 13l4 4L19 7"
                                        />
                                    </svg>
                                    <span>{{
                                        isAckingTotal ? 'Menyimpan...' : 'Saya Sudah Memeriksa Selisih Ini'
                                    }}</span>
                                </button>
                            </div>
                        </div>

                        <!-- 3. Tab Filter Status Evaluasi & Tambah Baris Manual -->
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <button
                                    type="button"
                                    :class="[
                                        'rounded-full px-3 py-1 text-xs font-semibold transition-colors',
                                        activeTab === 'all'
                                            ? 'bg-primary text-content-inverse'
                                            : 'border border-stroke bg-surface text-content-secondary hover:bg-surface-muted hover:text-content',
                                    ]"
                                    @click="activeTab = 'all'"
                                >
                                    Semua ({{ summary.total_items }})
                                </button>

                                <button
                                    type="button"
                                    :class="[
                                        'rounded-full px-3 py-1 text-xs font-semibold transition-colors',
                                        activeTab === 'needs_check'
                                            ? 'bg-warning text-white'
                                            : 'border border-warning-border bg-warning-subtle text-warning hover:bg-warning-subtle/80',
                                    ]"
                                    @click="activeTab = 'needs_check'"
                                >
                                    Perlu Dicek ({{ summary.needs_check_count }})
                                </button>

                                <button
                                    type="button"
                                    :class="[
                                        'rounded-full px-3 py-1 text-xs font-semibold transition-colors',
                                        activeTab === 'above_standard'
                                            ? 'bg-negative text-white'
                                            : 'border border-stroke bg-surface text-content-secondary hover:bg-surface-muted hover:text-content',
                                    ]"
                                    @click="activeTab = 'above_standard'"
                                >
                                    Di Atas Standar ({{ summary.above_standard_count }})
                                </button>

                                <button
                                    type="button"
                                    :class="[
                                        'rounded-full px-3 py-1 text-xs font-semibold transition-colors',
                                        activeTab === 'within_standard'
                                            ? 'bg-positive text-white'
                                            : 'border border-stroke bg-surface text-content-secondary hover:bg-surface-muted hover:text-content',
                                    ]"
                                    @click="activeTab = 'within_standard'"
                                >
                                    Sesuai ({{ summary.within_standard_count }})
                                </button>

                                <button
                                    type="button"
                                    :class="[
                                        'rounded-full px-3 py-1 text-xs font-semibold transition-colors',
                                        activeTab === 'no_reference'
                                            ? 'bg-stone-600 text-white'
                                            : 'border border-stroke bg-surface text-content-secondary hover:bg-surface-muted hover:text-content',
                                    ]"
                                    @click="activeTab = 'no_reference'"
                                >
                                    Tanpa Acuan ({{ summary.no_reference_count }})
                                </button>
                            </div>

                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-stroke bg-surface px-3 py-1.5 text-xs font-semibold text-content transition-colors hover:border-primary/50 hover:bg-surface-muted"
                                @click="openAddModal"
                            >
                                <svg
                                    class="h-3.5 w-3.5 text-primary"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M12 4v16m8-8H4"
                                    />
                                </svg>
                                <span>Tambah Baris</span>
                            </button>
                        </div>

                        <!-- 4. Tabel Audit Items -->
                        <div class="overflow-hidden rounded-xl border border-stroke bg-surface shadow-xs">
                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-xs">
                                    <thead
                                        class="border-b border-stroke bg-surface-subtle text-[11px] font-semibold tracking-wider text-content-tertiary uppercase"
                                    >
                                        <tr>
                                            <th class="w-10 px-3.5 py-3">No</th>
                                            <th class="px-3.5 py-3">Uraian Belanja & Acuan SSH</th>
                                            <th class="px-3.5 py-3">Vol / Sat</th>
                                            <th class="px-3.5 py-3">Harga Satuan</th>
                                            <th class="px-3.5 py-3">Subtotal</th>
                                            <th class="px-3.5 py-3 text-right">Evaluasi</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-stroke">
                                        <tr v-if="filteredItems.length === 0">
                                            <td colspan="6" class="px-4 py-8 text-center text-xs text-content-tertiary">
                                                Tidak ada baris belanja pada kategori filter ini.
                                            </td>
                                        </tr>

                                        <template v-for="item in filteredItems" :key="item.id">
                                            <!-- Normal or Yellow Row Body -->
                                            <tr
                                                :class="[
                                                    'transition-colors',
                                                    item.is_yellow
                                                        ? 'bg-warning-subtle/40'
                                                        : 'hover:bg-surface-muted/50',
                                                ]"
                                            >
                                                <td class="px-3.5 py-3 align-top font-mono text-content-tertiary">
                                                    {{ String(item.position).padStart(2, '0') }}
                                                </td>

                                                <td class="px-3.5 py-3 align-top">
                                                    <div class="font-bold text-content">
                                                        {{ item.raw_name }}
                                                    </div>
                                                    <div
                                                        v-if="item.standard"
                                                        class="mt-0.5 text-[11px] text-content-tertiary"
                                                    >
                                                        SSH Gianyar: {{ formatRupiah(item.standard.price_rp) }} /
                                                        {{ item.standard.unit }}
                                                    </div>
                                                    <div v-else class="mt-0.5 text-[11px] text-content-tertiary">
                                                        Kearifan Lokal (Non-SSH)
                                                    </div>

                                                    <!-- Yellow Row Alert Detail & Action Buttons -->
                                                    <div
                                                        v-if="item.is_yellow"
                                                        class="mt-2.5 rounded-lg border border-warning-border bg-white p-3 shadow-xs"
                                                    >
                                                        <div class="flex items-start gap-2 text-warning">
                                                            <svg
                                                                class="mt-0.5 h-4 w-4 shrink-0"
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
                                                            <span class="text-xs leading-snug font-semibold">
                                                                {{ item.reason_text }}
                                                            </span>
                                                        </div>

                                                        <!-- Interactive Resolution Actions -->
                                                        <div class="mt-2.5 flex flex-wrap items-center gap-2">
                                                            <button
                                                                type="button"
                                                                class="inline-flex items-center gap-1 rounded border border-stroke-strong bg-surface px-2.5 py-1 text-xs font-bold text-content shadow-xs transition-colors hover:bg-surface-muted"
                                                                @click="confirmItem(item)"
                                                            >
                                                                <svg
                                                                    class="h-3.5 w-3.5 text-positive"
                                                                    fill="none"
                                                                    viewBox="0 0 24 24"
                                                                    stroke="currentColor"
                                                                >
                                                                    <path
                                                                        stroke-linecap="round"
                                                                        stroke-linejoin="round"
                                                                        stroke-width="2.5"
                                                                        d="M5 13l4 4L19 7"
                                                                    />
                                                                </svg>
                                                                <span>Sudah Benar</span>
                                                            </button>

                                                            <button
                                                                type="button"
                                                                class="inline-flex items-center gap-1 rounded bg-primary-subtle px-2.5 py-1 text-xs font-bold text-primary transition-colors hover:bg-primary-subtle/80"
                                                                @click="openEditModal(item)"
                                                            >
                                                                <span>Koreksi Data</span>
                                                            </button>

                                                            <button
                                                                type="button"
                                                                class="px-2 py-1 text-xs font-semibold text-negative hover:underline"
                                                                @click="deleteItem(item)"
                                                            >
                                                                Hapus Baris
                                                            </button>
                                                        </div>
                                                    </div>
                                                </td>

                                                <td class="px-3.5 py-3 align-top whitespace-nowrap text-content">
                                                    {{ item.volume }} {{ item.unit }}
                                                </td>

                                                <td
                                                    class="px-3.5 py-3 align-top font-mono whitespace-nowrap text-content"
                                                >
                                                    {{ item.unit_price_rp ? formatRupiah(item.unit_price_rp) : '-' }}
                                                </td>

                                                <td
                                                    class="px-3.5 py-3 align-top font-mono font-bold whitespace-nowrap text-content"
                                                >
                                                    {{ item.amount_rp ? formatRupiah(item.amount_rp) : '-' }}
                                                </td>

                                                <td class="px-3.5 py-3 text-right align-top whitespace-nowrap">
                                                    <span
                                                        v-if="item.is_yellow"
                                                        class="inline-flex items-center gap-1 rounded-full border border-warning-border bg-white px-2.5 py-0.5 text-xs font-bold text-warning"
                                                    >
                                                        <svg
                                                            class="h-3 w-3"
                                                            fill="none"
                                                            viewBox="0 0 24 24"
                                                            stroke="currentColor"
                                                        >
                                                            <circle cx="12" cy="12" r="9" stroke-width="2" />
                                                            <path
                                                                stroke-linecap="round"
                                                                stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M12 8v4m0 4h.01"
                                                            />
                                                        </svg>
                                                        <span>Perlu Dicek</span>
                                                    </span>
                                                    <PriceLabel
                                                        v-else
                                                        :label="item.label"
                                                        :percent-above="item.over_pct ? `+${item.over_pct}%` : null"
                                                    />
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- 5. Sticky Bottom Action Bar (Gatekeeper Button) -->
                        <div
                            class="sticky bottom-4 z-20 flex flex-col gap-3 rounded-xl border border-stroke bg-surface p-4 shadow-lg sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div>
                                <span class="block text-xs text-content-tertiary">Total Pagu Terhitung:</span>
                                <div class="flex items-baseline gap-2">
                                    <span class="text-xl font-bold text-content">
                                        {{ formatRupiah(summary.total_calculated_rp) }}
                                    </span>
                                    <span class="text-xs text-content-tertiary">
                                        ({{ summary.total_items }} item belanja)
                                    </span>
                                </div>
                            </div>

                            <div class="flex flex-col items-end gap-1">
                                <!-- If already published -->
                                <template
                                    v-if="summary.publish_block_reason === 'already_published' || project?.is_published"
                                >
                                    <Link
                                        v-if="project"
                                        :href="`/desa/projects/${project.id}`"
                                        class="inline-flex items-center gap-2 rounded-lg bg-primary px-5 py-3 text-xs font-bold text-content-inverse shadow-md transition-colors hover:bg-primary-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                                    >
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"
                                            />
                                        </svg>
                                        <span>Kelola Proyek Fisik</span>
                                    </Link>
                                    <span class="text-[11px] font-semibold text-positive">
                                        Dokumen ini telah diterbitkan ke hadapan warga desa.
                                    </span>
                                </template>

                                <!-- Normal Publish flow -->
                                <template v-else>
                                    <button
                                        type="button"
                                        :disabled="!summary.can_publish"
                                        :class="[
                                            'inline-flex items-center gap-2 rounded-lg px-5 py-3 text-xs font-bold shadow-md transition-colors',
                                            summary.can_publish
                                                ? 'bg-primary text-content-inverse hover:bg-primary-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary'
                                                : 'cursor-not-allowed border border-stroke bg-surface-muted text-content-tertiary',
                                        ]"
                                        @click="isWizardOpen = true"
                                    >
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"
                                            />
                                        </svg>
                                        <span>Lanjut Terbitkan Proyek</span>
                                    </button>

                                    <span v-if="!summary.can_publish" class="text-[11px] font-semibold text-negative">
                                        <template v-if="summary.publish_block_reason === 'has_yellow_rows'">
                                            Terkunci: selesaikan {{ summary.needs_check_count }} baris kuning (D1)
                                        </template>
                                        <template v-else-if="summary.publish_block_reason === 'unacknowledged_diff'">
                                            Terkunci: konfirmasi ketidakcocokan total tertulis (F3)
                                        </template>
                                        <template v-else-if="summary.publish_block_reason === 'no_items'">
                                            Terkunci: tambahkan rincian belanja terlebih dahulu
                                        </template>
                                        <template v-else-if="summary.publish_block_reason === 'reading'">
                                            AI sedang mengekstrak data...
                                        </template>
                                        <template v-else-if="summary.publish_block_reason === 'read_failed'">
                                            Ekstraksi AI gagal: silakan coba lagi atau isi manual
                                        </template>
                                        <template v-else> Terkunci: prasyarat publikasi belum lengkap </template>
                                    </span>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- Add Item Modal Dialog -->
        <Dialog :open="isAddModalOpen" title="Tambah Baris Rincian Belanja Manual" @close="isAddModalOpen = false">
            <form class="space-y-4" @submit.prevent="submitAdd">
                <div>
                    <label class="block text-xs font-semibold text-content" for="add-raw-name">
                        Uraian Material / Upah <span class="text-negative">*</span>
                    </label>
                    <input
                        id="add-raw-name"
                        v-model="addForm.raw_name"
                        type="text"
                        required
                        placeholder="Contoh: Semen Portland 50kg (Gresik)"
                        class="mt-1 block w-full rounded-md border border-stroke px-3 py-2 text-xs text-content shadow-xs focus:border-primary focus:ring-1 focus:ring-primary"
                    />
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-content" for="add-vol">
                            Volume <span class="text-negative">*</span>
                        </label>
                        <input
                            id="add-vol"
                            v-model="addForm.volume"
                            type="text"
                            required
                            placeholder="Contoh: 100"
                            class="mt-1 block w-full rounded-md border border-stroke px-3 py-2 text-xs text-content shadow-xs focus:border-primary focus:ring-1 focus:ring-primary"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-content" for="add-unit">
                            Satuan <span class="text-negative">*</span>
                        </label>
                        <input
                            id="add-unit"
                            v-model="addForm.unit"
                            type="text"
                            required
                            placeholder="Contoh: sak, m3, HOK"
                            class="mt-1 block w-full rounded-md border border-stroke px-3 py-2 text-xs text-content shadow-xs focus:border-primary focus:ring-1 focus:ring-primary"
                        />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-content" for="add-price">
                            Harga Satuan (Rp) <span class="text-negative">*</span>
                        </label>
                        <input
                            id="add-price"
                            v-model.number="addForm.unit_price_rp"
                            type="number"
                            min="0"
                            required
                            placeholder="Contoh: 68000"
                            class="mt-1 block w-full rounded-md border border-stroke px-3 py-2 text-xs text-content shadow-xs focus:border-primary focus:ring-1 focus:ring-primary"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-content" for="add-subtotal">
                            Jumlah Subtotal (Rp)
                        </label>
                        <input
                            id="add-subtotal"
                            v-model.number="addForm.amount_rp"
                            type="number"
                            min="0"
                            :placeholder="
                                addForm.volume && addForm.unit_price_rp
                                    ? String(Math.round(Number(addForm.volume) * addForm.unit_price_rp))
                                    : 'Otomatis'
                            "
                            class="mt-1 block w-full rounded-md border border-stroke px-3 py-2 text-xs text-content shadow-xs focus:border-primary focus:ring-1 focus:ring-primary"
                        />
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-content">
                        Pencocokan Acuan Standar SSH Gianyar
                    </label>
                    <input
                        v-model="standardSearch"
                        type="text"
                        placeholder="Ketik untuk mencari standar harga resmi..."
                        class="mt-1 block w-full rounded-md border border-stroke px-3 py-2 text-xs text-content shadow-xs focus:border-primary focus:ring-1 focus:ring-primary"
                    />
                    <div
                        v-if="filteredStandards.length > 0 && standardSearch.trim()"
                        class="mt-2 max-h-36 overflow-y-auto rounded-md border border-stroke bg-surface p-1 shadow-xs"
                    >
                        <button
                            v-for="std in filteredStandards.slice(0, 8)"
                            :key="std.id"
                            type="button"
                            class="flex w-full items-center justify-between rounded px-2 py-1.5 text-left text-xs text-content hover:bg-surface-muted"
                            @click="selectAddStandard(std)"
                        >
                            <span>{{ std.name }} ({{ std.unit }})</span>
                            <span class="font-semibold text-primary">{{ formatRupiah(std.price_rp) }}</span>
                        </button>
                    </div>
                </div>

                <div class="flex justify-end gap-3 border-t border-stroke pt-3">
                    <button
                        type="button"
                        class="rounded-lg border border-stroke px-4 py-2 text-xs font-semibold text-content hover:bg-surface-muted"
                        @click="isAddModalOpen = false"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        class="rounded-lg bg-primary px-4 py-2 text-xs font-bold text-content-inverse shadow-xs hover:bg-primary-hover disabled:opacity-50"
                        :disabled="isSubmittingAdd"
                    >
                        {{ isSubmittingAdd ? 'Menyimpan...' : 'Simpan Baris Belanja' }}
                    </button>
                </div>
            </form>
        </Dialog>

        <!-- Inline Edit Modal Dialog -->
        <Dialog :open="isEditModalOpen" title="Koreksi Data Rincian Belanja" @close="isEditModalOpen = false">
            <form class="space-y-4" @submit.prevent="submitEdit">
                <div>
                    <label class="block text-xs font-semibold text-content" for="edit-raw-name">
                        Uraian Material / Upah <span class="text-negative">*</span>
                    </label>
                    <input
                        id="edit-raw-name"
                        v-model="editForm.raw_name"
                        type="text"
                        required
                        class="mt-1 block w-full rounded-md border border-stroke px-3 py-2 text-xs text-content shadow-xs focus:border-primary focus:ring-1 focus:ring-primary"
                    />
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-content" for="edit-vol">
                            Volume <span class="text-negative">*</span>
                        </label>
                        <input
                            id="edit-vol"
                            v-model="editForm.volume"
                            type="text"
                            required
                            class="mt-1 block w-full rounded-md border border-stroke px-3 py-2 text-xs text-content shadow-xs focus:border-primary focus:ring-1 focus:ring-primary"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-content" for="edit-unit">
                            Satuan <span class="text-negative">*</span>
                        </label>
                        <input
                            id="edit-unit"
                            v-model="editForm.unit"
                            type="text"
                            required
                            class="mt-1 block w-full rounded-md border border-stroke px-3 py-2 text-xs text-content shadow-xs focus:border-primary focus:ring-1 focus:ring-primary"
                            placeholder="Contoh: sak, m3, HOK"
                        />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-content" for="edit-price">
                            Harga Satuan (Rp) <span class="text-negative">*</span>
                        </label>
                        <input
                            id="edit-price"
                            v-model.number="editForm.unit_price_rp"
                            type="number"
                            min="0"
                            required
                            class="mt-1 block w-full rounded-md border border-stroke px-3 py-2 font-mono text-xs text-content shadow-xs focus:border-primary focus:ring-1 focus:ring-primary"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-content"> Subtotal Terhitung </label>
                        <div
                            class="mt-1 rounded-md border border-stroke bg-surface-subtle px-3 py-2 font-mono text-xs font-bold text-content"
                        >
                            {{
                                formatRupiah(
                                    Math.round((parseFloat(editForm.volume) || 0) * (editForm.unit_price_rp || 0)),
                                )
                            }}
                        </div>
                    </div>
                </div>

                <!-- SSH Standard Selector -->
                <div>
                    <div class="flex items-center justify-between">
                        <label class="block text-xs font-semibold text-content" for="standard-search">
                            Pencocokan Acuan SSH Gianyar
                        </label>
                        <button
                            v-if="editForm.standard_id"
                            type="button"
                            class="text-[11px] font-semibold text-negative hover:underline"
                            @click="clearStandard"
                        >
                            Hapus Acuan (Kearifan Lokal)
                        </button>
                    </div>

                    <input
                        id="standard-search"
                        v-model="standardSearch"
                        type="text"
                        placeholder="Ketik untuk mencari standar harga..."
                        class="mt-1 block w-full rounded-md border border-stroke px-3 py-2 text-xs text-content shadow-xs focus:border-primary focus:ring-1 focus:ring-primary"
                    />

                    <!-- Dropdown match list -->
                    <div
                        v-if="standardSearch && !editForm.standard_id"
                        class="mt-1 max-h-36 overflow-y-auto rounded-md border border-stroke bg-surface text-xs shadow-md"
                    >
                        <button
                            v-for="std in filteredStandards"
                            :key="std.id"
                            type="button"
                            class="w-full border-b border-stroke px-3 py-2 text-left last:border-0 hover:bg-surface-muted"
                            @click="selectStandard(std)"
                        >
                            <span class="font-bold text-content">{{ std.name }}</span>
                            <span class="block text-[10px] text-content-tertiary">
                                {{ std.code }} &middot; {{ std.unit }} &middot; {{ formatRupiah(std.price_rp) }}
                            </span>
                        </button>
                    </div>
                </div>

                <div class="flex justify-end gap-2 border-t border-stroke pt-3">
                    <button
                        type="button"
                        class="rounded-md border border-stroke px-3 py-1.5 text-xs font-semibold text-content hover:bg-surface-muted"
                        @click="isEditModalOpen = false"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        :disabled="isSubmittingEdit"
                        class="rounded-md bg-primary px-4 py-1.5 text-xs font-bold text-content-inverse shadow-xs hover:bg-primary-hover disabled:opacity-50"
                    >
                        {{ isSubmittingEdit ? 'Menyimpan...' : 'Simpan Perubahan' }}
                    </button>
                </div>
            </form>
        </Dialog>

        <!-- 4-Step Publish Wizard Modal -->
        <PublishWizardModal
            :open="isWizardOpen"
            :audit-id="audit.id"
            :initial-data="project"
            :written-total-rp="audit.written_total_rp ?? summary.total_calculated_rp"
            :total-items="summary.total_items"
            :types="types"
            :templates="templates"
            :fund-sources="fundSources"
            :village="village"
            @close="isWizardOpen = false"
        />

        <!-- Success & A4 Poster Modal -->
        <PublishSuccessModal
            :open="isSuccessOpen"
            :project="publishedProjectData"
            :village-name="village.name"
            :village-regency="village.regency"
            @close="isSuccessOpen = false"
        />
    </AppLayout>
</template>
