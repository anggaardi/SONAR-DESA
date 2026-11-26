<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onUnmounted, ref, watch } from 'vue';
import Alert from '@/components/ui/Alert.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import AuditItemsTable from '@/features/audit/AuditItemsTable.vue';
import DocumentViewer from '@/features/audit/DocumentViewer.vue';
import PublishBar from '@/features/audit/PublishBar.vue';
import ReadingStatus from '@/features/audit/ReadingStatus.vue';
import TotalCheck from '@/features/audit/TotalCheck.vue';
import PublishSuccessModal, { type PublishedProjectData } from '@/features/project/PublishSuccessModal.vue';
import PublishWizardModal from '@/features/project/PublishWizardModal.vue';
import PemdesLayout from '@/layouts/PemdesLayout.vue';
import { formatDate } from '@/lib/format';
import type { AuditItemData, AuditItemFormPayload, AuditSummary, PriceStandardItem } from '@/types/audit';

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
    items: AuditItemData[];
    summary: AuditSummary;
    project: {
        id: number;
        slug: string;
        name: string;
        type: string;
        description?: string | null;
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
    priceStandards: PriceStandardItem[];
    types: Array<{ value: string; label: string }>;
    templates: Record<string, Array<{ name: string; unit: string; default_value: string; how_to: string }>>;
    fundSources: string[];
}

const props = defineProps<Props>();
const page = usePage();

// Polling for audit reading status
let pollTimer: ReturnType<typeof setInterval> | null = null;
const pollSeconds = ref(0);

function startPolling(): void {
    if (pollTimer) {
        return;
    }
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

const isEditableAudit = computed(() => props.audit.status !== 'published' && !props.project?.is_published);

const sourceLabel = computed(() => {
    if (props.audit.source === 'ocr_ai' || props.audit.source === 'ocr') {
        return 'Dibaca otomatis';
    }
    if (props.audit.source === 'demo_sample') {
        return 'Contoh RAB';
    }
    return 'Unggah berkas';
});

// Retry reading
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

// Replace & delete file
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
    if (!file) {
        return;
    }

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
    if (!confirm(`Yakin ingin menghapus dokumen RAB "${props.audit.file_name}" beserta draf ini?`)) {
        return;
    }

    isDeletingAudit.value = true;
    router.delete(`/audits/${props.audit.id}`, {
        onFinish: () => {
            isDeletingAudit.value = false;
        },
    });
}

// Acknowledge written total vs calculated total difference (F3)
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

// Add / edit / confirm / delete items
const isAddModalOpen = ref(false);
const isSubmittingAdd = ref(false);
const isSubmittingEdit = ref(false);

function handleAddItem(payload: AuditItemFormPayload): void {
    isSubmittingAdd.value = true;
    router.post(`/audits/${props.audit.id}/items`, payload, {
        preserveScroll: true,
        onFinish: () => {
            isSubmittingAdd.value = false;
            isAddModalOpen.value = false;
        },
    });
}

function handleEditItem(itemId: number, payload: AuditItemFormPayload): void {
    isSubmittingEdit.value = true;
    router.patch(`/audits/${props.audit.id}/items/${itemId}`, payload, {
        preserveScroll: true,
        onFinish: () => {
            isSubmittingEdit.value = false;
        },
    });
}

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

// Modals
const isWizardOpen = ref(false);
const isSuccessOpen = ref(false);
const publishedProjectData = ref<PublishedProjectData | null>(null);

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
</script>

<template>
    <PemdesLayout :village="village" active-nav="audits">
        <Head :title="`Periksa rincian RAB #${audit.id} - ${village.name}`" />

        <template #breadcrumb>
            <Link href="/" class="transition-colors hover:text-primary">Beranda</Link>
            <span>/</span>
            <Link href="/desa" class="transition-colors hover:text-primary">Pemerintah Desa</Link>
            <span>/</span>
            <span class="font-medium text-content">Periksa RAB #{{ audit.id }}</span>
        </template>

        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-content sm:text-3xl">Periksa rincian RAB</h1>
                    <p class="mt-1 text-sm text-content-secondary">
                        Cocokkan tabel dengan dokumen asli. Periksa semua baris kuning sebelum menerbitkan.
                    </p>
                </div>

                <!-- File Info Card -->
                <Card padding="base" surface="subtle" class="shrink-0">
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="min-w-0">
                            <span
                                class="block max-w-[220px] truncate text-xs font-semibold text-content"
                                :title="audit.file_name"
                            >
                                {{ audit.file_name }}
                            </span>
                            <span class="block text-xs text-content-tertiary tabular-nums">
                                {{ audit.created_at ? formatDate(audit.created_at) : 'Hari ini' }} &middot;
                                {{ sourceLabel }}
                                <template v-if="audit.read_ms">
                                    &middot; {{ (audit.read_ms / 1000).toFixed(1) }} dtk
                                </template>
                            </span>
                        </div>

                        <input
                            ref="replaceFileInputRef"
                            type="file"
                            accept=".pdf,application/pdf,image/jpeg,image/png,image/jpg"
                            class="hidden"
                            @change="handleReplaceFileChange"
                        />

                        <div v-if="isEditableAudit" class="flex items-center gap-2 border-l border-stroke pl-3">
                            <Button
                                type="button"
                                variant="secondary"
                                size="sm"
                                :loading="isReplacingFile"
                                @click="triggerReplaceFile"
                            >
                                {{ isReplacingFile ? 'Mengunggah...' : 'Ganti berkas' }}
                            </Button>

                            <Button
                                type="button"
                                variant="danger"
                                size="sm"
                                :loading="isDeletingAudit"
                                @click="deleteAudit"
                            >
                                {{ isDeletingAudit ? 'Menghapus...' : 'Hapus' }}
                            </Button>
                        </div>
                    </div>
                </Card>
            </div>

            <!-- Flash Success -->
            <Alert v-if="page.props.flash?.success" color="positive">
                {{ page.props.flash.success }}
            </Alert>

            <!-- Dual-view Workspace -->
            <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-12">
                <!-- Left Panel: Original Document -->
                <div class="lg:col-span-5">
                    <DocumentViewer :file-name="audit.file_name" :file-url="audit.file_url" />
                </div>

                <!-- Right Panel: Status / Table / Publish Bar -->
                <div class="space-y-4 lg:col-span-7">
                    <ReadingStatus
                        v-if="audit.status === 'reading' || audit.status === 'read_failed'"
                        :status="audit.status"
                        :regency="village.regency"
                        :poll-seconds="pollSeconds"
                        :error-code="audit.error_code"
                        :is-editable="isEditableAudit"
                        :is-retrying="isRetrying"
                        :is-replacing-file="isReplacingFile"
                        @retry="retryReading"
                        @replace-file="triggerReplaceFile"
                        @add-manual="isAddModalOpen = true"
                    />

                    <template v-else>
                        <TotalCheck
                            :written-total-rp="audit.written_total_rp"
                            :calculated-total-rp="summary.total_calculated_rp"
                            :diff-rp="summary.total_diff_rp"
                            :is-match="summary.is_total_match"
                            :total-ack="audit.total_ack"
                            :is-acking="isAckingTotal"
                            @ack-total="ackTotal"
                        />

                        <AuditItemsTable
                            v-model:is-add-modal-open="isAddModalOpen"
                            :items="items"
                            :summary="summary"
                            :price-standards="priceStandards"
                            :is-editable="isEditableAudit"
                            :is-submitting-add="isSubmittingAdd"
                            :is-submitting-edit="isSubmittingEdit"
                            @confirm-item="confirmItem"
                            @delete-item="deleteItem"
                            @submit-add="handleAddItem"
                            @submit-edit="handleEditItem"
                        />

                        <PublishBar :summary="summary" :project="project" @open-wizard="isWizardOpen = true" />
                    </template>
                </div>
            </div>
        </div>

        <!-- Publish Wizard Modal -->
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
            :items="items"
            :summary="summary"
            @close="isWizardOpen = false"
        />

        <!-- Success & QR Poster Modal -->
        <PublishSuccessModal
            :open="isSuccessOpen"
            :project="publishedProjectData"
            :village-name="village.name"
            :village-regency="village.regency"
            @close="isSuccessOpen = false"
        />
    </PemdesLayout>
</template>
