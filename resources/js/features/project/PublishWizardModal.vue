<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import Alert from '@/components/ui/Alert.vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import CitizenMobilePreview from '@/features/project/CitizenMobilePreview.vue';
import GeofenceMapPicker from '@/features/project/GeofenceMapPicker.vue';
import { formatRupiah } from '@/lib/format';
import { isValidCoordinates, parseCoordinateText } from '@/lib/mapTileMath';
import { fundingLabel } from '@/lib/projectStory';

interface MeasurementItem {
    name: string;
    value: string;
    unit: string;
    how_to: string;
    origin: 'template' | 'desa';
}

interface MeasurementFieldError {
    name?: string;
    value?: string;
    unit?: string;
}

interface Props {
    open: boolean;
    auditId: number;
    initialData?: {
        name?: string;
        type?: string;
        description?: string | null;
        fund_source?: string;
        year?: number;
        lat?: number | string | null;
        lng?: number | string | null;
        measurements?: Array<{
            name: string;
            value: string;
            unit: string;
            how_to?: string | null;
            origin?: string | null;
        }>;
    } | null;
    writtenTotalRp: number;
    totalItems?: number;
    types: Array<{ value: string; label: string }>;
    templates: Record<string, Array<{ name: string; unit: string; default_value: string; how_to: string }>>;
    fundSources: string[];
    village: {
        id: number;
        name: string;
        slug: string;
        regency: string;
    };
    items?: Array<{
        id: number;
        name?: string;
        raw_name?: string;
        volume?: string | null;
        unit?: string | null;
        unit_price_rp?: number | null;
        amount_rp?: number | null;
        standard_price_rp?: number | null;
        over_pct?: number | null;
        label?: string;
        standard_source?: string | null;
        explanation?: string | null;
    }>;
    summary?: {
        total_items?: number;
        above_standard_count?: number;
        within_standard_count?: number;
        no_reference_count?: number;
        total_calculated_rp?: number;
    };
}

const props = defineProps<Props>();

const emit = defineEmits<{
    close: [];
}>();

const STEP_META = [
    { id: 1 as const, label: 'Identitas & pagu' },
    { id: 2 as const, label: 'Lokasi proyek' },
    { id: 3 as const, label: 'Ukuran Cek Spek' },
    { id: 4 as const, label: 'Konfirmasi' },
];

const currentStep = ref<1 | 2 | 3 | 4>(1);
const gpsLoading = ref(false);
const gpsError = ref<string | null>(null);
const smartCoordInput = ref('');
const smartCoordError = ref<string | null>(null);
const showCloseConfirm = ref(false);
const mobilePreviewOpen = ref(false);
const initialSnapshot = ref('');
const isHydrating = ref(false);

const modalPanelRef = ref<HTMLElement | null>(null);
const closeConfirmPanelRef = ref<HTMLElement | null>(null);
const leftScrollRef = ref<HTMLElement | null>(null);

const fieldErrors = ref<{
    name?: string;
    type?: string;
    fund_source?: string;
    year?: string;
    lat?: string;
    lng?: string;
    coordinates?: string;
    measurementsGeneral?: string;
}>({});
const measurementErrors = ref<Record<number, MeasurementFieldError>>({});

const form = useForm({
    name: props.initialData?.name ?? '',
    type: props.initialData?.type ?? 'rabat_beton',
    description: props.initialData?.description ?? '',
    fund_source: props.initialData?.fund_source ?? 'Dana Desa (DDS)',
    year: props.initialData?.year ?? new Date().getFullYear(),
    lat: props.initialData?.lat ? String(props.initialData.lat) : '',
    lng: props.initialData?.lng ? String(props.initialData.lng) : '',
    measurements: [] as MeasurementItem[],
});

function serializeFormState(): string {
    return JSON.stringify({
        name: form.name,
        type: form.type,
        description: form.description,
        fund_source: form.fund_source,
        year: form.year,
        lat: form.lat,
        lng: form.lng,
        measurements: form.measurements,
    });
}

const isDirty = computed(() => {
    if (!props.open || !initialSnapshot.value) {
        return false;
    }
    return serializeFormState() !== initialSnapshot.value;
});

function syncMeasurementsForType(projectType: string, keepExistingCustom = true): void {
    const existingCustom = keepExistingCustom ? form.measurements.filter((m) => m.origin === 'desa') : [];

    const templateDefs = props.templates[projectType] ?? props.templates['rabat_beton'] ?? [];
    const templateItems: MeasurementItem[] = templateDefs.map((def) => {
        const match = props.initialData?.measurements?.find((m) => m.name === def.name);
        return {
            name: def.name,
            value: match?.value ?? def.default_value,
            unit: match?.unit ?? def.unit,
            how_to: match?.how_to ?? def.how_to,
            origin: 'template',
        };
    });

    form.measurements = [...templateItems, ...existingCustom];
}

function resetErrors(): void {
    fieldErrors.value = {};
    measurementErrors.value = {};
    smartCoordError.value = null;
}

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            isHydrating.value = true;
            currentStep.value = 1;
            mobilePreviewOpen.value = false;
            showCloseConfirm.value = false;
            gpsError.value = null;
            resetErrors();
            form.clearErrors();

            form.name = props.initialData?.name ?? '';
            form.type = props.initialData?.type ?? 'rabat_beton';
            form.description = props.initialData?.description ?? '';
            form.fund_source = props.initialData?.fund_source ?? 'Dana Desa (DDS)';
            form.year = props.initialData?.year ?? new Date().getFullYear();
            form.lat = props.initialData?.lat ? String(props.initialData.lat) : '';
            form.lng = props.initialData?.lng ? String(props.initialData.lng) : '';
            smartCoordInput.value = form.lat && form.lng ? `${form.lat}, ${form.lng}` : '';

            if (props.initialData?.measurements && props.initialData.measurements.length > 0) {
                // Backend MeasurementOrigin accepts 'template' | 'desa' (Story S6).
                form.measurements = props.initialData.measurements.map((m) => ({
                    name: m.name,
                    value: m.value,
                    unit: m.unit,
                    how_to: m.how_to ?? '',
                    origin: m.origin === 'desa' || m.origin === 'custom' ? 'desa' : 'template',
                }));
            } else {
                syncMeasurementsForType(form.type, false);
            }

            initialSnapshot.value = serializeFormState();
            document.body.style.overflow = 'hidden';
            nextTick(() => {
                isHydrating.value = false;
                modalPanelRef.value?.focus();
            });
        } else {
            showCloseConfirm.value = false;
            document.body.style.overflow = '';
        }
    },
    { immediate: true },
);

watch(
    () => form.type,
    (newType) => {
        if (isHydrating.value) {
            return;
        }
        syncMeasurementsForType(newType, true);
    },
);

function handleKeydown(event: KeyboardEvent): void {
    if (!props.open || event.key !== 'Escape') {
        return;
    }
    event.preventDefault();
    if (showCloseConfirm.value) {
        dismissCloseConfirm();
        return;
    }
    requestClose();
}

onMounted(() => {
    document.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
    document.removeEventListener('keydown', handleKeydown);
    document.body.style.overflow = '';
});

const customMeasurementsCount = computed(() => form.measurements.filter((m) => m.origin === 'desa').length);

const selectedTypeLabel = computed(() => {
    return props.types.find((t) => t.value === form.type)?.label ?? form.type;
});

const autoStoryPlaceholder = computed(() => {
    const subject = form.type === 'rabat_beton' ? 'Jalan rabat beton' : selectedTypeLabel.value;
    const funding = fundingLabel(form.fund_source, form.year);
    return `Contoh: ${subject}, dibiayai ${funding}. Pekerjaan belum dimulai.`;
});

const validCoordinates = computed<{ lat: number; lng: number } | null>(() => {
    const latText = String(form.lat).trim();
    const lngText = String(form.lng).trim();
    if (!latText || !lngText) {
        return null;
    }

    const lat = Number(latText);
    const lng = Number(lngText);
    if (!isValidCoordinates(lat, lng)) {
        return null;
    }

    return { lat, lng };
});

const serverErrorMessages = computed<string[]>(() => {
    return Object.values(form.errors).filter((msg): msg is string => Boolean(msg));
});

function handleSmartCoordInput(raw: string): void {
    smartCoordInput.value = raw;
    smartCoordError.value = null;

    if (!raw.trim()) {
        return;
    }

    const parsed = parseCoordinateText(raw);
    if (parsed) {
        form.lat = parsed.lat.toFixed(6);
        form.lng = parsed.lng.toFixed(6);
        fieldErrors.value.lat = undefined;
        fieldErrors.value.lng = undefined;
        fieldErrors.value.coordinates = undefined;
    } else if (raw.includes(',') || raw.includes('http')) {
        smartCoordError.value = 'Format koordinat belum terbaca. Contoh: -8.412400, 115.248200';
    }
}

function handleMapPick(coords: { lat: string; lng: string }): void {
    form.lat = coords.lat;
    form.lng = coords.lng;
    smartCoordInput.value = `${coords.lat}, ${coords.lng}`;
    smartCoordError.value = null;
    fieldErrors.value.lat = undefined;
    fieldErrors.value.lng = undefined;
    fieldErrors.value.coordinates = undefined;
}

function clearCoordinates(): void {
    form.lat = '';
    form.lng = '';
    smartCoordInput.value = '';
    smartCoordError.value = null;
}

function syncSmartInputFromFields(): void {
    if (validCoordinates.value) {
        smartCoordInput.value = `${form.lat.trim()}, ${form.lng.trim()}`;
        fieldErrors.value.coordinates = undefined;
    }
}

function addCustomMeasurement(): void {
    if (customMeasurementsCount.value >= 5) {
        return;
    }
    form.measurements.push({
        name: '',
        value: '1.0',
        unit: 'meter',
        how_to: '',
        origin: 'desa',
    });
}

function removeMeasurement(index: number): void {
    const item = form.measurements[index];
    if (item && item.origin === 'desa') {
        form.measurements.splice(index, 1);
        delete measurementErrors.value[index];
    }
}

function getCurrentGps(): void {
    if (!navigator.geolocation) {
        gpsError.value = 'Perangkat tidak mendukung lokasi GPS.';
        return;
    }
    gpsLoading.value = true;
    gpsError.value = null;

    navigator.geolocation.getCurrentPosition(
        (pos) => {
            const lat = pos.coords.latitude.toFixed(6);
            const lng = pos.coords.longitude.toFixed(6);
            handleMapPick({ lat, lng });
            gpsLoading.value = false;
        },
        (err) => {
            gpsError.value = `Gagal mendeteksi lokasi GPS: ${err.message}`;
            gpsLoading.value = false;
        },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 },
    );
}

function validateStep(step: number): boolean {
    if (step === 1) {
        fieldErrors.value.name = !form.name.trim() ? 'Nama kegiatan proyek wajib diisi.' : undefined;
        fieldErrors.value.type = !form.type ? 'Pilih jenis pekerjaan proyek.' : undefined;
        fieldErrors.value.fund_source = !form.fund_source ? 'Sumber dana wajib diisi.' : undefined;
        fieldErrors.value.year =
            !form.year || form.year < 2020 || form.year > 2035
                ? 'Tahun anggaran harus antara 2020 dan 2035.'
                : undefined;

        return (
            !fieldErrors.value.name &&
            !fieldErrors.value.type &&
            !fieldErrors.value.fund_source &&
            !fieldErrors.value.year
        );
    }

    if (step === 2) {
        const latText = String(form.lat).trim();
        const lngText = String(form.lng).trim();
        const lat = Number(latText);
        const lng = Number(lngText);

        fieldErrors.value.lat =
            !latText || !Number.isFinite(lat) || lat < -90 || lat > 90
                ? 'Latitude harus angka valid antara -90 dan 90.'
                : undefined;
        fieldErrors.value.lng =
            !lngText || !Number.isFinite(lng) || lng < -180 || lng > 180
                ? 'Longitude harus angka valid antara -180 dan 180.'
                : undefined;
        fieldErrors.value.coordinates =
            fieldErrors.value.lat || fieldErrors.value.lng
                ? 'Pilih titik proyek pada peta atau isi koordinat Latitude & Longitude yang valid.'
                : undefined;

        return !fieldErrors.value.lat && !fieldErrors.value.lng;
    }

    if (step === 3) {
        fieldErrors.value.measurementsGeneral =
            form.measurements.length === 0 ? 'Minimal satu ukuran Cek Spek wajib tersedia.' : undefined;

        const nextMeasurementErrors: Record<number, MeasurementFieldError> = {};
        let firstInvalidIdx: number | null = null;

        form.measurements.forEach((m, idx) => {
            const entryError: MeasurementFieldError = {};
            if (!m.name.trim()) {
                entryError.name = 'Nama ukuran wajib diisi.';
            }
            const val = Number(m.value);
            if (!Number.isFinite(val) || val <= 0) {
                entryError.value = 'Target angka harus lebih dari 0.';
            }
            if (!m.unit.trim()) {
                entryError.unit = 'Satuan wajib diisi.';
            }

            if (Object.keys(entryError).length > 0) {
                nextMeasurementErrors[idx] = entryError;
                if (firstInvalidIdx === null) {
                    firstInvalidIdx = idx;
                }
            }
        });

        measurementErrors.value = nextMeasurementErrors;

        if (firstInvalidIdx !== null) {
            nextTick(() => {
                const el = leftScrollRef.value?.querySelector(`[data-measurement-card="${firstInvalidIdx}"]`);
                el?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            });
        }

        return !fieldErrors.value.measurementsGeneral && Object.keys(nextMeasurementErrors).length === 0;
    }

    return true;
}

function goToStep(targetStep: 1 | 2 | 3 | 4): void {
    mobilePreviewOpen.value = false;
    if (targetStep <= currentStep.value) {
        currentStep.value = targetStep;
        nextTick(() => leftScrollRef.value?.scrollTo({ top: 0 }));
        return;
    }

    for (let s = currentStep.value; s < targetStep; s++) {
        if (!validateStep(s)) {
            currentStep.value = s as 1 | 2 | 3 | 4;
            return;
        }
    }

    currentStep.value = targetStep;
    nextTick(() => leftScrollRef.value?.scrollTo({ top: 0 }));
}

function nextStep(): void {
    if (validateStep(currentStep.value) && currentStep.value < 4) {
        currentStep.value = (currentStep.value + 1) as 1 | 2 | 3 | 4;
        mobilePreviewOpen.value = false;
        nextTick(() => leftScrollRef.value?.scrollTo({ top: 0 }));
    }
}

function prevStep(): void {
    if (currentStep.value > 1) {
        currentStep.value = (currentStep.value - 1) as 1 | 2 | 3 | 4;
        mobilePreviewOpen.value = false;
        nextTick(() => leftScrollRef.value?.scrollTo({ top: 0 }));
    }
}

function requestClose(): void {
    if (form.processing) {
        return;
    }
    if (isDirty.value) {
        showCloseConfirm.value = true;
        nextTick(() => closeConfirmPanelRef.value?.focus());
        return;
    }
    emit('close');
}

function dismissCloseConfirm(): void {
    showCloseConfirm.value = false;
    nextTick(() => modalPanelRef.value?.focus());
}

function confirmDiscardClose(): void {
    showCloseConfirm.value = false;
    emit('close');
}

function submitPublish(): void {
    for (const s of [1, 2, 3] as const) {
        if (!validateStep(s)) {
            currentStep.value = s;
            mobilePreviewOpen.value = false;
            return;
        }
    }

    form.post(`/audits/${props.auditId}/publish`, {
        preserveScroll: true,
        onSuccess: () => {
            showCloseConfirm.value = false;
            emit('close');
        },
    });
}
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center p-2 sm:p-4">
            <!-- Backdrop (protected by dirty-state guard) -->
            <div class="fixed inset-0 bg-content/50" aria-hidden="true" @click="requestClose" />

            <!-- Modal Panel -->
            <div
                ref="modalPanelRef"
                tabindex="-1"
                class="relative z-10 flex h-[94vh] max-h-[820px] w-full max-w-6xl flex-col overflow-hidden rounded-lg border border-stroke bg-surface shadow-sm focus:outline-none"
                role="dialog"
                aria-modal="true"
                aria-labelledby="wizard-modal-title"
            >
                <!-- Modal Header -->
                <div
                    class="flex shrink-0 items-center justify-between gap-3 border-b border-stroke bg-surface-subtle px-4 py-3.5 sm:px-6"
                >
                    <div class="min-w-0">
                        <h2 id="wizard-modal-title" class="text-base font-semibold text-content sm:text-lg">
                            Terbitkan proyek
                        </h2>
                        <p class="truncate text-xs text-content-secondary">
                            Lengkapi 4 bagian sebelum rincian proyek dibuka untuk warga.
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <Button
                            type="button"
                            variant="secondary"
                            size="sm"
                            class="lg:hidden"
                            @click="mobilePreviewOpen = !mobilePreviewOpen"
                        >
                            {{ mobilePreviewOpen ? 'Kembali ke form' : 'Lihat pratinjau' }}
                        </Button>

                        <button
                            type="button"
                            class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-md text-content-secondary transition-colors hover:bg-surface-muted hover:text-content focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                            aria-label="Tutup modal"
                            @click="requestClose"
                        >
                            <svg
                                class="h-5 w-5"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12"
                                />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Interactive Stepper -->
                <nav class="shrink-0 border-b border-stroke bg-surface px-4 sm:px-6" aria-label="Langkah terbit">
                    <div class="grid grid-cols-2 gap-1 sm:grid-cols-4">
                        <button
                            v-for="step in STEP_META"
                            :key="step.id"
                            type="button"
                            :aria-current="currentStep === step.id ? 'step' : undefined"
                            :class="[
                                'flex items-center gap-2 border-b-2 px-2 py-2.5 text-left text-xs transition-colors',
                                currentStep === step.id
                                    ? 'border-primary font-semibold text-primary'
                                    : step.id < currentStep
                                      ? 'border-transparent font-medium text-content hover:bg-surface-subtle'
                                      : 'border-transparent text-content-tertiary hover:bg-surface-subtle hover:text-content-secondary',
                            ]"
                            @click="goToStep(step.id)"
                        >
                            <span
                                :class="[
                                    'inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-xs font-semibold tabular-nums',
                                    step.id < currentStep
                                        ? 'bg-positive-subtle text-positive'
                                        : currentStep === step.id
                                          ? 'bg-primary text-content-inverse'
                                          : 'border border-stroke bg-surface-subtle text-content-tertiary',
                                ]"
                            >
                                <svg
                                    v-if="step.id < currentStep"
                                    class="h-3 w-3"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    aria-hidden="true"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2.5"
                                        d="M5 13l4 4L19 7"
                                    />
                                </svg>
                                <span v-else>{{ step.id }}</span>
                            </span>
                            <span class="truncate">{{ step.label }}</span>
                        </button>
                    </div>
                </nav>

                <!-- Split Body: Left Form Scrolls Independently on Desktop -->
                <div class="min-h-0 flex-1 overflow-hidden">
                    <div class="grid h-full grid-cols-1 lg:grid-cols-12">
                        <!-- Left Column: Form Steps -->
                        <div
                            ref="leftScrollRef"
                            :class="[
                                'h-full overflow-y-auto p-4 text-sm sm:p-6 lg:col-span-7',
                                mobilePreviewOpen ? 'hidden lg:block' : 'block',
                            ]"
                        >
                            <!-- STEP 1: IDENTITAS & PAGU -->
                            <div v-if="currentStep === 1" class="space-y-4">
                                <p class="text-xs text-content-secondary">
                                    Isi nama kegiatan dan sumber dana sesuai dokumen APBDes.
                                </p>

                                <div>
                                    <label class="block text-sm font-medium text-content" for="wizard-name">
                                        Nama kegiatan <span class="text-negative">*</span>
                                    </label>
                                    <input
                                        id="wizard-name"
                                        v-model="form.name"
                                        type="text"
                                        :class="[
                                            'mt-1 block h-11 w-full rounded-md border bg-surface px-3 text-sm text-content focus:outline-none',
                                            fieldErrors.name
                                                ? 'border-negative-solid focus:border-negative-solid'
                                                : 'border-stroke-strong focus:border-primary',
                                        ]"
                                        placeholder="Contoh: Pembangunan Jalan Rabat Beton Banjar Melati"
                                        @input="fieldErrors.name = undefined"
                                    />
                                    <p v-if="fieldErrors.name" class="mt-1 text-xs text-negative">
                                        {{ fieldErrors.name }}
                                    </p>
                                </div>

                                <div>
                                    <div class="flex items-center justify-between gap-2">
                                        <label class="block text-sm font-medium text-content" for="wizard-description">
                                            Deskripsi untuk warga
                                        </label>
                                        <Button
                                            v-if="form.description && form.description.trim() !== ''"
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            @click="form.description = ''"
                                        >
                                            Reset otomatis
                                        </Button>
                                    </div>
                                    <textarea
                                        id="wizard-description"
                                        v-model="form.description"
                                        rows="2"
                                        class="mt-1 block w-full rounded-md border border-stroke-strong bg-surface px-3 py-2 text-sm leading-relaxed text-content placeholder:text-content-tertiary focus:border-primary focus:outline-none"
                                        :placeholder="autoStoryPlaceholder"
                                    />
                                </div>

                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div>
                                        <label class="block text-sm font-medium text-content" for="wizard-type">
                                            Jenis pekerjaan <span class="text-negative">*</span>
                                        </label>
                                        <select
                                            id="wizard-type"
                                            v-model="form.type"
                                            class="mt-1 block h-11 w-full rounded-md border border-stroke-strong bg-surface px-3 text-sm text-content focus:border-primary focus:outline-none"
                                            @change="fieldErrors.type = undefined"
                                        >
                                            <option v-for="t in types" :key="t.value" :value="t.value">
                                                {{ t.label }}
                                            </option>
                                        </select>
                                        <p v-if="fieldErrors.type" class="mt-1 text-xs text-negative">
                                            {{ fieldErrors.type }}
                                        </p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-content" for="wizard-fund-source">
                                            Sumber dana <span class="text-negative">*</span>
                                        </label>
                                        <select
                                            id="wizard-fund-source"
                                            v-model="form.fund_source"
                                            class="mt-1 block h-11 w-full rounded-md border border-stroke-strong bg-surface px-3 text-sm text-content focus:border-primary focus:outline-none"
                                            @change="fieldErrors.fund_source = undefined"
                                        >
                                            <option v-for="src in fundSources" :key="src" :value="src">
                                                {{ src }}
                                            </option>
                                        </select>
                                        <p v-if="fieldErrors.fund_source" class="mt-1 text-xs text-negative">
                                            {{ fieldErrors.fund_source }}
                                        </p>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div>
                                        <label class="block text-sm font-medium text-content" for="wizard-year">
                                            Tahun anggaran <span class="text-negative">*</span>
                                        </label>
                                        <input
                                            id="wizard-year"
                                            v-model.number="form.year"
                                            type="number"
                                            min="2020"
                                            max="2035"
                                            :class="[
                                                'mt-1 block h-11 w-full rounded-md border bg-surface px-3 text-sm text-content tabular-nums focus:outline-none',
                                                fieldErrors.year
                                                    ? 'border-negative-solid focus:border-negative-solid'
                                                    : 'border-stroke-strong focus:border-primary',
                                            ]"
                                            @input="fieldErrors.year = undefined"
                                        />
                                        <p v-if="fieldErrors.year" class="mt-1 text-xs text-negative">
                                            {{ fieldErrors.year }}
                                        </p>
                                    </div>

                                    <div>
                                        <span class="block text-sm font-medium text-content">Total pagu RAB</span>
                                        <div
                                            class="mt-1 flex h-11 items-center rounded-md border border-stroke bg-surface-subtle px-3 text-base font-semibold text-primary tabular-nums"
                                        >
                                            {{ formatRupiah(writtenTotalRp) }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- STEP 2: LOKASI PROYEK -->
                            <div v-else-if="currentStep === 2" class="space-y-4">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <p class="max-w-md text-xs text-content-secondary">
                                        Warga hanya bisa mengirim Cek Spek dalam <strong>radius 300 m</strong> dari
                                        titik yang ditandai pada peta.
                                    </p>
                                    <div class="flex flex-col items-end">
                                        <Button
                                            type="button"
                                            variant="secondary"
                                            size="sm"
                                            :loading="gpsLoading"
                                            @click="getCurrentGps"
                                        >
                                            {{ gpsLoading ? 'Mendeteksi...' : 'Gunakan lokasi saya' }}
                                        </Button>
                                        <span class="mt-1 text-xs text-content-tertiary">
                                            Cocok jika Anda berada di lokasi proyek
                                        </span>
                                    </div>
                                </div>

                                <Alert v-if="gpsError" color="negative">
                                    {{ gpsError }}
                                </Alert>

                                <!-- Smart Coordinate Paste -->
                                <div>
                                    <div class="flex items-center justify-between gap-2">
                                        <label class="block text-xs font-medium text-content" for="wizard-smart-coord">
                                            Tempel koordinat Google Maps atau pilih langsung di peta
                                        </label>
                                        <button
                                            v-if="form.lat || form.lng"
                                            type="button"
                                            class="text-xs font-medium text-content-secondary hover:text-content hover:underline"
                                            @click="clearCoordinates"
                                        >
                                            Reset titik
                                        </button>
                                    </div>
                                    <input
                                        id="wizard-smart-coord"
                                        :value="smartCoordInput"
                                        type="text"
                                        :class="[
                                            'mt-1 block h-10 w-full rounded-md border bg-surface px-3 text-sm text-content tabular-nums focus:outline-none',
                                            smartCoordError || fieldErrors.coordinates
                                                ? 'border-negative-solid focus:border-negative-solid'
                                                : 'border-stroke-strong focus:border-primary',
                                        ]"
                                        placeholder="Tempel -8.412400, 115.248200 atau tautan Google Maps"
                                        @input="handleSmartCoordInput(($event.target as HTMLInputElement).value)"
                                    />
                                    <p v-if="smartCoordError" class="mt-1 text-xs text-negative">
                                        {{ smartCoordError }}
                                    </p>
                                    <p v-else-if="fieldErrors.coordinates" class="mt-1 text-xs text-negative">
                                        {{ fieldErrors.coordinates }}
                                    </p>
                                </div>

                                <!-- Interactive Map Picker with 300 m Radius Circle -->
                                <GeofenceMapPicker
                                    :lat="form.lat"
                                    :lng="form.lng"
                                    :has-error="Boolean(fieldErrors.coordinates)"
                                    @update:coordinates="handleMapPick"
                                />

                                <!-- Precision Numeric Fields -->
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div>
                                        <label
                                            class="block text-xs font-medium text-content-secondary"
                                            for="wizard-lat"
                                        >
                                            Latitude <span class="text-negative">*</span>
                                        </label>
                                        <input
                                            id="wizard-lat"
                                            v-model="form.lat"
                                            type="text"
                                            :class="[
                                                'mt-1 block h-10 w-full rounded-md border bg-surface px-3 text-sm text-content tabular-nums focus:outline-none',
                                                fieldErrors.lat
                                                    ? 'border-negative-solid focus:border-negative-solid'
                                                    : 'border-stroke-strong focus:border-primary',
                                            ]"
                                            placeholder="Belum dipilih"
                                            @input="
                                                fieldErrors.lat = undefined;
                                                syncSmartInputFromFields();
                                            "
                                        />
                                        <p v-if="fieldErrors.lat" class="mt-1 text-xs text-negative">
                                            {{ fieldErrors.lat }}
                                        </p>
                                    </div>

                                    <div>
                                        <label
                                            class="block text-xs font-medium text-content-secondary"
                                            for="wizard-lng"
                                        >
                                            Longitude <span class="text-negative">*</span>
                                        </label>
                                        <input
                                            id="wizard-lng"
                                            v-model="form.lng"
                                            type="text"
                                            :class="[
                                                'mt-1 block h-10 w-full rounded-md border bg-surface px-3 text-sm text-content tabular-nums focus:outline-none',
                                                fieldErrors.lng
                                                    ? 'border-negative-solid focus:border-negative-solid'
                                                    : 'border-stroke-strong focus:border-primary',
                                            ]"
                                            placeholder="Belum dipilih"
                                            @input="
                                                fieldErrors.lng = undefined;
                                                syncSmartInputFromFields();
                                            "
                                        />
                                        <p v-if="fieldErrors.lng" class="mt-1 text-xs text-negative">
                                            {{ fieldErrors.lng }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- STEP 3: UKURAN CEK SPEK -->
                            <div v-else-if="currentStep === 3" class="space-y-4">
                                <p class="text-xs text-content-secondary">
                                    Ukuran dari template wajib diisi untuk verifikasi warga. Anda dapat menambah hingga
                                    5 ukuran tambahan.
                                </p>

                                <div
                                    class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-stroke bg-surface-subtle px-4 py-2.5 text-xs text-content"
                                >
                                    <span class="font-semibold">Jenis: {{ selectedTypeLabel }}</span>
                                    <span class="text-content-secondary tabular-nums">
                                        {{ form.measurements.filter((m) => m.origin === 'template').length }} wajib ·
                                        {{ customMeasurementsCount }}/5 tambahan
                                    </span>
                                </div>

                                <Alert v-if="fieldErrors.measurementsGeneral" color="negative">
                                    {{ fieldErrors.measurementsGeneral }}
                                </Alert>

                                <div class="space-y-3">
                                    <Card
                                        v-for="(item, idx) in form.measurements"
                                        :key="idx"
                                        :data-measurement-card="idx"
                                        padding="base"
                                        :class="measurementErrors[idx] ? 'border-negative-solid' : ''"
                                    >
                                        <div class="flex items-center justify-between gap-2">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="text-sm font-semibold text-content tabular-nums">
                                                    {{ idx + 1 }}. {{ item.name || 'Ukuran baru' }}
                                                </span>
                                                <Badge v-if="item.origin === 'template'" color="neutral">
                                                    Template wajib
                                                </Badge>
                                                <Badge v-else color="info">Kustom</Badge>
                                            </div>

                                            <Button
                                                v-if="item.origin === 'desa'"
                                                type="button"
                                                variant="danger"
                                                size="sm"
                                                @click="removeMeasurement(idx)"
                                            >
                                                Hapus
                                            </Button>
                                        </div>

                                        <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                                            <div v-if="item.origin === 'desa'" class="sm:col-span-2">
                                                <label class="block text-xs font-medium text-content-secondary">
                                                    Nama ukuran <span class="text-negative">*</span>
                                                </label>
                                                <input
                                                    v-model="item.name"
                                                    type="text"
                                                    :class="[
                                                        'mt-1 block h-10 w-full rounded-md border bg-surface px-3 text-sm text-content focus:outline-none',
                                                        measurementErrors[idx]?.name
                                                            ? 'border-negative-solid'
                                                            : 'border-stroke-strong focus:border-primary',
                                                    ]"
                                                    placeholder="Contoh: Kedalaman galian tanah"
                                                    @input="
                                                        if (measurementErrors[idx]) {
                                                            measurementErrors[idx].name = undefined;
                                                        }
                                                    "
                                                />
                                                <p
                                                    v-if="measurementErrors[idx]?.name"
                                                    class="mt-1 text-xs text-negative"
                                                >
                                                    {{ measurementErrors[idx].name }}
                                                </p>
                                            </div>

                                            <div>
                                                <label class="block text-xs font-medium text-content-secondary">
                                                    Target angka RAB <span class="text-negative">*</span>
                                                </label>
                                                <input
                                                    v-model="item.value"
                                                    type="text"
                                                    :class="[
                                                        'mt-1 block h-10 w-full rounded-md border bg-surface px-3 text-sm font-semibold text-content tabular-nums focus:outline-none',
                                                        measurementErrors[idx]?.value
                                                            ? 'border-negative-solid'
                                                            : 'border-stroke-strong focus:border-primary',
                                                    ]"
                                                    placeholder="Contoh: 12.0"
                                                    @input="
                                                        if (measurementErrors[idx]) {
                                                            measurementErrors[idx].value = undefined;
                                                        }
                                                    "
                                                />
                                                <p
                                                    v-if="measurementErrors[idx]?.value"
                                                    class="mt-1 text-xs text-negative"
                                                >
                                                    {{ measurementErrors[idx].value }}
                                                </p>
                                            </div>

                                            <div>
                                                <label class="block text-xs font-medium text-content-secondary">
                                                    Satuan <span class="text-negative">*</span>
                                                </label>
                                                <input
                                                    v-model="item.unit"
                                                    type="text"
                                                    :class="[
                                                        'mt-1 block h-10 w-full rounded-md border bg-surface px-3 text-sm text-content focus:outline-none',
                                                        measurementErrors[idx]?.unit
                                                            ? 'border-negative-solid'
                                                            : 'border-stroke-strong focus:border-primary',
                                                    ]"
                                                    placeholder="Contoh: cm, meter"
                                                    @input="
                                                        if (measurementErrors[idx]) {
                                                            measurementErrors[idx].unit = undefined;
                                                        }
                                                    "
                                                />
                                                <p
                                                    v-if="measurementErrors[idx]?.unit"
                                                    class="mt-1 text-xs text-negative"
                                                >
                                                    {{ measurementErrors[idx].unit }}
                                                </p>
                                            </div>

                                            <div class="sm:col-span-2">
                                                <label class="block text-xs font-medium text-content-secondary">
                                                    Panduan cara ukur untuk warga
                                                </label>
                                                <input
                                                    v-model="item.how_to"
                                                    type="text"
                                                    class="mt-1 block h-10 w-full rounded-md border border-stroke-strong bg-surface px-3 text-sm text-content focus:border-primary focus:outline-none"
                                                    placeholder="Contoh: Ukur ketebalan cor di tepi jalan."
                                                />
                                            </div>
                                        </div>
                                    </Card>
                                </div>

                                <div v-if="customMeasurementsCount < 5">
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        size="sm"
                                        class="w-full"
                                        @click="addCustomMeasurement"
                                    >
                                        Tambah ukuran tambahan ({{ customMeasurementsCount }}/5)
                                    </Button>
                                </div>
                            </div>

                            <!-- STEP 4: KONFIRMASI TERBIT -->
                            <div v-else-if="currentStep === 4" class="space-y-4">
                                <p class="text-xs text-content-secondary">
                                    Periksa kembali rincian proyek sebelum dibuka untuk warga. Klik
                                    <strong>Ubah</strong>
                                    jika ada data yang perlu disesuaikan.
                                </p>

                                <Alert
                                    v-if="serverErrorMessages.length > 0"
                                    color="negative"
                                    title="Gagal menerbitkan proyek"
                                >
                                    <ul class="list-inside list-disc space-y-0.5">
                                        <li v-for="(msg, i) in serverErrorMessages" :key="i">{{ msg }}</li>
                                    </ul>
                                </Alert>

                                <!-- Section 1 Summary -->
                                <Card padding="base" surface="subtle" class="space-y-3">
                                    <div class="flex items-start justify-between gap-2 border-b border-stroke pb-2.5">
                                        <div>
                                            <span class="text-xs font-medium text-content-tertiary">
                                                1. Identitas &amp; pagu
                                            </span>
                                            <h4 class="text-sm font-semibold text-content">{{ form.name }}</h4>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <Badge color="positive">Siap terbit</Badge>
                                            <button
                                                type="button"
                                                class="text-xs font-medium text-primary hover:underline"
                                                @click="goToStep(1)"
                                            >
                                                Ubah
                                            </button>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-2 gap-3 text-xs sm:grid-cols-4">
                                        <div>
                                            <span class="text-content-tertiary">Jenis pekerjaan</span>
                                            <p class="font-semibold text-content">{{ selectedTypeLabel }}</p>
                                        </div>
                                        <div>
                                            <span class="text-content-tertiary">Sumber dana</span>
                                            <p class="font-semibold text-content">{{ form.fund_source }}</p>
                                        </div>
                                        <div>
                                            <span class="text-content-tertiary">Tahun</span>
                                            <p class="font-semibold text-content tabular-nums">{{ form.year }}</p>
                                        </div>
                                        <div>
                                            <span class="text-content-tertiary">Total pagu</span>
                                            <p class="font-semibold text-primary tabular-nums">
                                                {{ formatRupiah(writtenTotalRp) }}
                                            </p>
                                        </div>
                                    </div>
                                </Card>

                                <!-- Section 2 Summary -->
                                <Card padding="base" surface="subtle" class="space-y-1.5 text-xs">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="font-medium text-content-tertiary"> 2. Lokasi proyek </span>
                                        <button
                                            type="button"
                                            class="text-xs font-medium text-primary hover:underline"
                                            @click="goToStep(2)"
                                        >
                                            Ubah
                                        </button>
                                    </div>
                                    <p class="font-semibold text-content tabular-nums">
                                        {{
                                            validCoordinates
                                                ? `${form.lat}, ${form.lng} (radius lokasi 300 m)`
                                                : 'Titik lokasi belum dipilih'
                                        }}
                                    </p>
                                </Card>

                                <!-- Section 3 Summary -->
                                <Card padding="base" surface="subtle" class="space-y-1.5 text-xs">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="font-medium text-content-tertiary">
                                            3. Ukuran Cek Spek warga ({{ form.measurements.length }})
                                        </span>
                                        <button
                                            type="button"
                                            class="text-xs font-medium text-primary hover:underline"
                                            @click="goToStep(3)"
                                        >
                                            Ubah
                                        </button>
                                    </div>
                                    <ul class="mt-1 list-inside list-disc space-y-0.5 font-medium text-content">
                                        <li v-for="(m, i) in form.measurements" :key="i" class="tabular-nums">
                                            {{ m.name }}:
                                            <span class="font-semibold">{{ m.value }} {{ m.unit }}</span>
                                        </li>
                                    </ul>
                                </Card>

                                <Alert color="warning" title="Setelah terbit, proyek tidak bisa ditarik kembali">
                                    Perubahan berikutnya tercatat dan terlihat warga.
                                </Alert>
                            </div>
                        </div>

                        <!-- Right Column: Toned-Down Live Mobile Preview -->
                        <div
                            :class="[
                                'h-full overflow-y-auto border-t border-stroke bg-surface-subtle p-4 sm:p-5 lg:col-span-5 lg:border-t-0 lg:border-l',
                                mobilePreviewOpen ? 'block' : 'hidden lg:block',
                            ]"
                        >
                            <CitizenMobilePreview
                                :description="form.description"
                                :project-name="form.name"
                                :project-type="form.type"
                                :project-type-label="selectedTypeLabel"
                                :fund-source="form.fund_source"
                                :year="form.year"
                                :written-total-rp="writtenTotalRp"
                                :total-items="totalItems"
                                :lat="form.lat"
                                :lng="form.lng"
                                :measurements="form.measurements"
                                :village="village"
                                :items="items"
                                :summary="summary"
                                :current-step="currentStep"
                            />
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div
                    class="flex shrink-0 items-center justify-between border-t border-stroke bg-surface-subtle px-4 py-3.5 sm:px-6"
                >
                    <Button v-if="currentStep > 1" type="button" variant="secondary" size="sm" @click="prevStep">
                        Kembali
                    </Button>
                    <div v-else />

                    <div class="flex items-center gap-2">
                        <Button type="button" variant="ghost" size="sm" @click="requestClose">Batal</Button>

                        <Button v-if="currentStep < 4" type="button" variant="primary" size="sm" @click="nextStep">
                            Lanjut
                        </Button>

                        <Button
                            v-else
                            type="button"
                            variant="primary"
                            size="sm"
                            :loading="form.processing"
                            @click="submitPublish"
                        >
                            {{ form.processing ? 'Menerbitkan...' : 'Terbitkan proyek' }}
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Unsaved Changes Confirmation Modal (IDDS Dialog + Alert) -->
        <div v-if="open && showCloseConfirm" class="fixed inset-0 z-60 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-content/50" aria-hidden="true" @click="dismissCloseConfirm" />

            <div
                ref="closeConfirmPanelRef"
                tabindex="-1"
                class="relative z-10 w-full max-w-md rounded-lg border border-stroke bg-surface shadow-sm focus:outline-none"
                role="alertdialog"
                aria-modal="true"
                aria-labelledby="close-confirm-title"
                aria-describedby="close-confirm-desc"
            >
                <!-- Header -->
                <div class="flex items-center justify-between border-b border-stroke px-6 py-4">
                    <h3 id="close-confirm-title" class="text-base font-semibold text-content">
                        Tutup tanpa menyimpan?
                    </h3>
                    <button
                        type="button"
                        class="-mr-2 inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-md text-content-secondary transition-colors hover:bg-surface-muted hover:text-content focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                        aria-label="Tutup konfirmasi"
                        @click="dismissCloseConfirm"
                    >
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 20 20" stroke="currentColor" aria-hidden="true">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="1.5"
                                d="M5 5l10 10M15 5L5 15"
                            />
                        </svg>
                    </button>
                </div>

                <!-- Body -->
                <div id="close-confirm-desc" class="px-6 py-4">
                    <Alert color="warning" title="Perubahan belum diterbitkan">
                        Yakin ingin menutup tanpa menyimpan? Isian yang belum diterbitkan tidak akan tersimpan.
                    </Alert>
                </div>

                <!-- Actions -->
                <div class="flex flex-col-reverse gap-3 border-t border-stroke px-6 py-4 sm:flex-row sm:justify-end">
                    <Button type="button" variant="danger" @click="confirmDiscardClose"> Tutup &amp; buang </Button>
                    <Button type="button" variant="primary" @click="dismissCloseConfirm"> Lanjut mengisi </Button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
