<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Badge from '@/components/ui/Badge.vue';
import { formatRupiah } from '@/lib/format';

interface MeasurementItem {
    name: string;
    value: string;
    unit: string;
    how_to: string;
    origin: 'template' | 'custom';
}

interface Props {
    open: boolean;
    auditId: number;
    initialData?: {
        name?: string;
        type?: string;
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
}

const props = defineProps<Props>();

const emit = defineEmits<{
    close: [];
}>();

const currentStep = ref<1 | 2 | 3 | 4>(1);
const gpsLoading = ref(false);
const gpsError = ref<string | null>(null);
const stepErrors = ref<string[]>([]);

const form = useForm({
    name: props.initialData?.name ?? '',
    type: props.initialData?.type ?? 'rabat_beton',
    fund_source: props.initialData?.fund_source ?? 'Dana Desa (DDS)',
    year: props.initialData?.year ?? new Date().getFullYear(),
    lat: props.initialData?.lat ? String(props.initialData.lat) : '-8.4124',
    lng: props.initialData?.lng ? String(props.initialData.lng) : '115.2482',
    measurements: [] as MeasurementItem[],
});

// Initialize / sync measurements from templates or existing data
function syncMeasurementsForType(projectType: string, keepExistingCustom = true): void {
    const existingCustom = keepExistingCustom ? form.measurements.filter((m) => m.origin === 'custom') : [];

    const templateDefs = props.templates[projectType] ?? props.templates['rabat_beton'] ?? [];
    const templateItems: MeasurementItem[] = templateDefs.map((def) => {
        // Check if already in initial data
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

// Watch open state to reset or initialize form
watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            currentStep.value = 1;
            stepErrors.value = [];
            if (props.initialData) {
                form.name = props.initialData.name ?? form.name;
                form.type = props.initialData.type ?? form.type;
                form.fund_source = props.initialData.fund_source ?? form.fund_source;
                form.year = props.initialData.year ?? form.year;
                if (props.initialData.lat) form.lat = String(props.initialData.lat);
                if (props.initialData.lng) form.lng = String(props.initialData.lng);
            }
            if (props.initialData?.measurements && props.initialData.measurements.length > 0) {
                form.measurements = props.initialData.measurements.map((m) => ({
                    name: m.name,
                    value: m.value,
                    unit: m.unit,
                    how_to: m.how_to ?? '',
                    origin: (m.origin === 'custom' ? 'custom' : 'template') as 'template' | 'custom',
                }));
            } else {
                syncMeasurementsForType(form.type, false);
            }
        }
    },
    { immediate: true },
);

// Watch type change to update template measurements
watch(
    () => form.type,
    (newType) => {
        syncMeasurementsForType(newType, true);
    },
);

const customMeasurementsCount = computed(() => form.measurements.filter((m) => m.origin === 'custom').length);

const selectedTypeLabel = computed(() => {
    return props.types.find((t) => t.value === form.type)?.label ?? form.type;
});

function addCustomMeasurement(): void {
    if (customMeasurementsCount.value >= 5) return;
    form.measurements.push({
        name: '',
        value: '1.0',
        unit: 'meter',
        how_to: '',
        origin: 'custom',
    });
}

function removeMeasurement(index: number): void {
    const item = form.measurements[index];
    if (item && item.origin === 'custom') {
        form.measurements.splice(index, 1);
    }
}

function getCurrentGps(): void {
    if (!navigator.geolocation) {
        gpsError.value = 'Perangkat tidak mendukung GPS geolocation.';
        return;
    }
    gpsLoading.value = true;
    gpsError.value = null;

    navigator.geolocation.getCurrentPosition(
        (pos) => {
            form.lat = pos.coords.latitude.toFixed(6);
            form.lng = pos.coords.longitude.toFixed(6);
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
    stepErrors.value = [];
    if (step === 1) {
        if (!form.name.trim()) stepErrors.value.push('Nama kegiatan proyek fisik wajib diisi.');
        if (!form.type) stepErrors.value.push('Pilih jenis pekerjaan proyek fisik.');
        if (!form.fund_source) stepErrors.value.push('Sumber dana wajib diisi.');
        if (!form.year || form.year < 2020 || form.year > 2035) {
            stepErrors.value.push('Tahun anggaran harus antara 2020 dan 2035.');
        }
    } else if (step === 2) {
        const lat = parseFloat(form.lat);
        const lng = parseFloat(form.lng);
        if (Number.isNaN(lat) || lat < -90 || lat > 90) {
            stepErrors.value.push('Koordinat Latitude harus angka valid antara -90 dan 90.');
        }
        if (Number.isNaN(lng) || lng < -180 || lng > 180) {
            stepErrors.value.push('Koordinat Longitude harus angka valid antara -180 dan 180.');
        }
    } else if (step === 3) {
        if (form.measurements.length === 0) {
            stepErrors.value.push('Parameter ukuran Cek Spek warga wajib diisi.');
        }
        form.measurements.forEach((m, idx) => {
            if (!m.name.trim()) stepErrors.value.push(`Parameter #${idx + 1}: Nama ukuran wajib diisi.`);
            const val = parseFloat(m.value);
            if (Number.isNaN(val) || val <= 0) {
                stepErrors.value.push(
                    `Parameter #${idx + 1} (${m.name || 'Ukuran'}): Nilai target harus angka positif.`,
                );
            }
            if (!m.unit.trim())
                stepErrors.value.push(`Parameter #${idx + 1} (${m.name || 'Ukuran'}): Satuan wajib diisi.`);
        });
    }

    return stepErrors.value.length === 0;
}

function nextStep(): void {
    if (validateStep(currentStep.value)) {
        if (currentStep.value < 4) {
            currentStep.value = (currentStep.value + 1) as 1 | 2 | 3 | 4;
        }
    }
}

function prevStep(): void {
    stepErrors.value = [];
    if (currentStep.value > 1) {
        currentStep.value = (currentStep.value - 1) as 1 | 2 | 3 | 4;
    }
}

function submitPublish(): void {
    if (!validateStep(1) || !validateStep(2) || !validateStep(3)) return;

    form.post(`/audits/${props.auditId}/publish`, {
        preserveScroll: true,
        onSuccess: () => {
            emit('close');
        },
    });
}
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <!-- Backdrop -->
            <div
                class="fixed inset-0 bg-content/60 backdrop-blur-xs transition-opacity"
                aria-hidden="true"
                @click="emit('close')"
            />

            <!-- Modal Panel -->
            <div
                class="relative z-10 flex max-h-[92vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl border border-stroke bg-surface shadow-2xl lg:max-w-6xl"
                role="dialog"
                aria-modal="true"
                aria-labelledby="wizard-modal-title"
            >
                <!-- Modal Header -->
                <div class="flex items-center justify-between border-b border-stroke bg-surface-subtle px-6 py-4">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary-subtle text-primary"
                        >
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"
                                />
                            </svg>
                        </div>
                        <div>
                            <h2 id="wizard-modal-title" class="text-base font-bold text-content sm:text-lg">
                                Wizard Publikasi Proyek Fisik
                            </h2>
                            <p class="text-xs text-content-secondary">
                                Satu pintu penerbitan resmi ke hadapan warga desa (PublishProject &middot; D8)
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg p-1.5 text-content-tertiary transition-colors hover:bg-surface-muted hover:text-content"
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
                </div>

                <!-- Stepper Progress Bar -->
                <div class="border-b border-stroke bg-surface px-6 py-3">
                    <div class="grid grid-cols-4 gap-2 text-center text-xs">
                        <div
                            :class="[
                                'flex items-center gap-2 font-semibold',
                                currentStep >= 1 ? 'text-primary' : 'text-content-tertiary',
                            ]"
                        >
                            <span
                                :class="[
                                    'flex h-6 w-6 items-center justify-center rounded-full text-xs',
                                    currentStep > 1
                                        ? 'bg-positive text-white'
                                        : currentStep === 1
                                          ? 'bg-primary text-white'
                                          : 'bg-surface-muted text-content-tertiary',
                                ]"
                            >
                                <svg
                                    v-if="currentStep > 1"
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
                                <span v-else>1</span>
                            </span>
                            <span class="hidden sm:inline">Identitas & Pagu</span>
                        </div>

                        <div
                            :class="[
                                'flex items-center gap-2 font-semibold',
                                currentStep >= 2 ? 'text-primary' : 'text-content-tertiary',
                            ]"
                        >
                            <span
                                :class="[
                                    'flex h-6 w-6 items-center justify-center rounded-full text-xs',
                                    currentStep > 2
                                        ? 'bg-positive text-white'
                                        : currentStep === 2
                                          ? 'bg-primary text-white'
                                          : 'bg-surface-muted text-content-tertiary',
                                ]"
                            >
                                <svg
                                    v-if="currentStep > 2"
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
                                <span v-else>2</span>
                            </span>
                            <span class="hidden sm:inline">Lokasi Geofence</span>
                        </div>

                        <div
                            :class="[
                                'flex items-center gap-2 font-semibold',
                                currentStep >= 3 ? 'text-primary' : 'text-content-tertiary',
                            ]"
                        >
                            <span
                                :class="[
                                    'flex h-6 w-6 items-center justify-center rounded-full text-xs',
                                    currentStep > 3
                                        ? 'bg-positive text-white'
                                        : currentStep === 3
                                          ? 'bg-primary text-white'
                                          : 'bg-surface-muted text-content-tertiary',
                                ]"
                            >
                                <svg
                                    v-if="currentStep > 3"
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
                                <span v-else>3</span>
                            </span>
                            <span class="hidden sm:inline">Spek Cek Warga</span>
                        </div>

                        <div
                            :class="[
                                'flex items-center gap-2 font-semibold',
                                currentStep === 4 ? 'text-primary' : 'text-content-tertiary',
                            ]"
                        >
                            <span
                                :class="[
                                    'flex h-6 w-6 items-center justify-center rounded-full text-xs',
                                    currentStep === 4
                                        ? 'bg-primary text-white'
                                        : 'bg-surface-muted text-content-tertiary',
                                ]"
                            >
                                4
                            </span>
                            <span class="hidden sm:inline">Konfirmasi Terbit</span>
                        </div>
                    </div>
                </div>

                <!-- Validation Error Banner -->
                <div
                    v-if="stepErrors.length > 0"
                    class="border-b border-warning-border bg-warning-subtle px-6 py-2.5 text-xs text-warning"
                >
                    <ul class="list-inside list-disc space-y-0.5">
                        <li v-for="(err, idx) in stepErrors" :key="idx">{{ err }}</li>
                    </ul>
                </div>

                <!-- Step Body Container (Scrollable) -->
                <div class="flex-1 overflow-y-auto p-6 text-sm">
                    <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
                        <!-- Left Column: Form Steps (lg:col-span-7) -->
                        <div class="space-y-4 lg:col-span-7">
                            <!-- STEP 1: IDENTITAS & PAGU -->
                            <div v-if="currentStep === 1" class="space-y-4">
                                <div>
                                    <h3 class="text-base font-bold text-content">
                                        Langkah 1: Identitas & Pagu Anggaran
                                    </h3>
                                    <p class="text-xs text-content-secondary">
                                        Tentukan nama kegiatan fisik dan sumber dana sesuai dokumen APBDes resmi.
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-content" for="wizard-name">
                                        Nama Kegiatan Proyek Fisik <span class="text-negative">*</span>
                                    </label>
                                    <input
                                        id="wizard-name"
                                        v-model="form.name"
                                        type="text"
                                        class="mt-1 block w-full rounded-lg border border-stroke bg-surface px-3 py-2 text-sm text-content shadow-xs focus:border-primary focus:ring-1 focus:ring-primary"
                                        placeholder="Contoh: Pembangunan Jalan Rabat Beton Banjar Melati"
                                    />
                                </div>

                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div>
                                        <label class="block text-xs font-semibold text-content" for="wizard-type">
                                            Jenis Pekerjaan Proyek <span class="text-negative">*</span>
                                        </label>
                                        <select
                                            id="wizard-type"
                                            v-model="form.type"
                                            class="mt-1 block w-full rounded-lg border border-stroke bg-surface px-3 py-2 text-sm text-content shadow-xs focus:border-primary focus:ring-1 focus:ring-primary"
                                        >
                                            <option v-for="t in types" :key="t.value" :value="t.value">
                                                {{ t.label }}
                                            </option>
                                        </select>
                                    </div>

                                    <div>
                                        <label
                                            class="block text-xs font-semibold text-content"
                                            for="wizard-fund-source"
                                        >
                                            Sumber Dana <span class="text-negative">*</span>
                                        </label>
                                        <select
                                            id="wizard-fund-source"
                                            v-model="form.fund_source"
                                            class="mt-1 block w-full rounded-lg border border-stroke bg-surface px-3 py-2 text-sm text-content shadow-xs focus:border-primary focus:ring-1 focus:ring-primary"
                                        >
                                            <option v-for="src in fundSources" :key="src" :value="src">
                                                {{ src }}
                                            </option>
                                        </select>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div>
                                        <label class="block text-xs font-semibold text-content" for="wizard-year">
                                            Tahun Anggaran <span class="text-negative">*</span>
                                        </label>
                                        <input
                                            id="wizard-year"
                                            v-model.number="form.year"
                                            type="number"
                                            min="2020"
                                            max="2035"
                                            class="mt-1 block w-full rounded-lg border border-stroke bg-surface px-3 py-2 text-sm text-content shadow-xs focus:border-primary focus:ring-1 focus:ring-primary"
                                        />
                                    </div>

                                    <div>
                                        <label class="block text-xs font-semibold text-content">
                                            Pagu Anggaran RAB Terverifikasi
                                        </label>
                                        <div
                                            class="mt-1 rounded-lg border border-stroke bg-surface-subtle px-3 py-2 text-base font-bold text-primary"
                                        >
                                            {{ formatRupiah(writtenTotalRp) }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- STEP 2: LOKASI GEOFENCE (D3) -->
                            <div v-else-if="currentStep === 2" class="space-y-4">
                                <div>
                                    <h3 class="text-base font-bold text-content">
                                        Langkah 2: Titik Koordinat GPS & Geofence (D3)
                                    </h3>
                                    <p class="text-xs text-content-secondary">
                                        Titik GPS ini mengaktifkan radius geofence (&le; 300m) agar warga hanya dapat
                                        melakukan Cek Spek jika berada langsung di lokasi proyek.
                                    </p>
                                </div>

                                <div
                                    class="rounded-lg border border-primary/20 bg-primary-subtle p-3.5 text-xs text-primary"
                                >
                                    <div class="flex items-center justify-between">
                                        <span class="font-semibold"
                                            >Lokasi Desa: {{ village.name }}, Kab. {{ village.regency }}</span
                                        >
                                        <Badge color="info">Geofence 300m Aktif</Badge>
                                    </div>
                                    <p class="mt-1 text-content-secondary">
                                        Pengawasan warga diuji terhadap koordinat ini untuk memvalidasi keberadaan fisik
                                        reporter di lapangan.
                                    </p>
                                </div>

                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-semibold text-content">Koordinat Proyek</span>
                                    <button
                                        type="button"
                                        :disabled="gpsLoading"
                                        class="inline-flex items-center gap-1.5 rounded-md border border-stroke bg-surface px-3 py-1.5 text-xs font-semibold text-primary shadow-xs transition-colors hover:bg-surface-muted"
                                        @click="getCurrentGps"
                                    >
                                        <svg
                                            v-if="gpsLoading"
                                            class="h-4 w-4 animate-spin"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                        >
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
                                        <svg
                                            v-else
                                            class="h-4 w-4"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"
                                            />
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"
                                            />
                                        </svg>
                                        <span>{{
                                            gpsLoading ? 'Mendeteksi...' : 'Gunakan Koordinat GPS Saat Ini'
                                        }}</span>
                                    </button>
                                </div>

                                <div v-if="gpsError" class="text-xs font-medium text-negative">
                                    {{ gpsError }}
                                </div>

                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div>
                                        <label class="block text-xs font-semibold text-content" for="wizard-lat">
                                            Latitude (Garis Lintang) <span class="text-negative">*</span>
                                        </label>
                                        <input
                                            id="wizard-lat"
                                            v-model="form.lat"
                                            type="text"
                                            class="mt-1 block w-full rounded-lg border border-stroke bg-surface px-3 py-2 font-mono text-sm text-content shadow-xs focus:border-primary focus:ring-1 focus:ring-primary"
                                            placeholder="-8.412400"
                                        />
                                    </div>

                                    <div>
                                        <label class="block text-xs font-semibold text-content" for="wizard-lng">
                                            Longitude (Garis Bujur) <span class="text-negative">*</span>
                                        </label>
                                        <input
                                            id="wizard-lng"
                                            v-model="form.lng"
                                            type="text"
                                            class="mt-1 block w-full rounded-lg border border-stroke bg-surface px-3 py-2 font-mono text-sm text-content shadow-xs focus:border-primary focus:ring-1 focus:ring-primary"
                                            placeholder="115.248200"
                                        />
                                    </div>
                                </div>
                            </div>

                            <!-- STEP 3: SPEK CEK WARGA (D2, F19) -->
                            <div v-else-if="currentStep === 3" class="space-y-4">
                                <div>
                                    <h3 class="text-base font-bold text-content">
                                        Langkah 3: Parameter Ukuran Cek Spek Warga
                                    </h3>
                                    <p class="text-xs text-content-secondary">
                                        Tolok ukur fisik ini menjadi instrumen nyata bagi warga untuk menguji
                                        ketebalan/panjang di lokasi. Parameter kunci template baku tidak dapat dihapus
                                        demi akuntabilitas (D2).
                                    </p>
                                </div>

                                <!-- Template Badge Header -->
                                <div
                                    class="flex items-center justify-between rounded-lg border border-primary/30 bg-primary-subtle px-4 py-2.5 text-xs text-primary"
                                >
                                    <span class="font-bold">Jenis: {{ selectedTypeLabel }}</span>
                                    <span class="font-medium">
                                        {{ form.measurements.filter((m) => m.origin === 'template').length }} Parameter
                                        Kunci Wajib
                                    </span>
                                </div>

                                <!-- Measurement Cards -->
                                <div class="space-y-3">
                                    <div
                                        v-for="(item, idx) in form.measurements"
                                        :key="idx"
                                        class="rounded-lg border border-stroke bg-surface p-4 shadow-xs"
                                    >
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center gap-2">
                                                <span class="text-xs font-bold text-content"
                                                    >{{ idx + 1 }}. {{ item.name || 'Ukuran Baru' }}</span
                                                >
                                                <Badge v-if="item.origin === 'template'" color="neutral">
                                                    Template Baku (Terkunci)
                                                </Badge>
                                                <Badge v-else color="info"> Kustom </Badge>
                                            </div>

                                            <button
                                                v-if="item.origin === 'custom'"
                                                type="button"
                                                class="text-xs font-semibold text-negative hover:underline"
                                                @click="removeMeasurement(idx)"
                                            >
                                                Hapus Parameter
                                            </button>
                                        </div>

                                        <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
                                            <div v-if="item.origin === 'custom'" class="sm:col-span-3">
                                                <label class="block text-xs font-medium text-content-secondary"
                                                    >Nama Parameter Ukuran</label
                                                >
                                                <input
                                                    v-model="item.name"
                                                    type="text"
                                                    class="mt-1 block w-full rounded border border-stroke px-2.5 py-1.5 text-xs text-content shadow-xs"
                                                    placeholder="Contoh: Kedalaman Galian Tanah"
                                                />
                                            </div>

                                            <div>
                                                <label class="block text-xs font-medium text-content-secondary"
                                                    >Target Angka RAB *</label
                                                >
                                                <input
                                                    v-model="item.value"
                                                    type="text"
                                                    class="mt-1 block w-full rounded border border-stroke px-2.5 py-1.5 text-xs font-bold text-content shadow-xs"
                                                    placeholder="Contoh: 12.0"
                                                />
                                            </div>

                                            <div>
                                                <label class="block text-xs font-medium text-content-secondary"
                                                    >Satuan Ukur *</label
                                                >
                                                <input
                                                    v-model="item.unit"
                                                    type="text"
                                                    class="mt-1 block w-full rounded border border-stroke px-2.5 py-1.5 text-xs text-content shadow-xs"
                                                    placeholder="Contoh: cm, meter"
                                                />
                                            </div>

                                            <div class="sm:col-span-3">
                                                <label class="block text-xs font-medium text-content-secondary"
                                                    >Panduan Cara Ukur bagi Warga di Lapangan</label
                                                >
                                                <input
                                                    v-model="item.how_to"
                                                    type="text"
                                                    class="mt-1 block w-full rounded border border-stroke px-2.5 py-1.5 text-xs text-content shadow-xs"
                                                    placeholder="Contoh: Ukur ketebalan cor di batas bekisting tepi jalan."
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Add Custom Measurement Button -->
                                <div v-if="customMeasurementsCount < 5">
                                    <button
                                        type="button"
                                        class="flex w-full items-center justify-center gap-2 rounded-lg border border-dashed border-stroke py-2.5 text-xs font-semibold text-primary transition-colors hover:bg-surface-muted"
                                        @click="addCustomMeasurement"
                                    >
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M12 4v16m8-8H4"
                                            />
                                        </svg>
                                        <span>+ Tambah Parameter Ukuran Kustom (Maksimal 5)</span>
                                    </button>
                                </div>
                            </div>

                            <!-- STEP 4: KONFIRMASI TERBIT -->
                            <div v-else-if="currentStep === 4" class="space-y-4">
                                <div>
                                    <h3 class="text-base font-bold text-content">
                                        Langkah 4: Konfirmasi Penerbitan Resmi
                                    </h3>
                                    <p class="text-xs text-content-secondary">
                                        Tinjau kembali seluruh spesifikasi proyek fisik sebelum dibuka untuk pengawasan
                                        publik.
                                    </p>
                                </div>

                                <div class="space-y-3 rounded-xl border border-stroke bg-surface-subtle p-4">
                                    <div class="flex items-start justify-between border-b border-stroke pb-2.5">
                                        <div>
                                            <span class="text-xs font-medium text-content-tertiary">Nama Kegiatan</span>
                                            <h4 class="text-sm font-bold text-content">{{ form.name }}</h4>
                                        </div>
                                        <Badge color="positive">Siap Terbit</Badge>
                                    </div>

                                    <div class="grid grid-cols-2 gap-3 text-xs sm:grid-cols-4">
                                        <div>
                                            <span class="text-content-tertiary">Jenis Pekerjaan</span>
                                            <p class="font-semibold text-content">{{ selectedTypeLabel }}</p>
                                        </div>
                                        <div>
                                            <span class="text-content-tertiary">Sumber Dana</span>
                                            <p class="font-semibold text-content">{{ form.fund_source }}</p>
                                        </div>
                                        <div>
                                            <span class="text-content-tertiary">Tahun Anggaran</span>
                                            <p class="font-semibold text-content">{{ form.year }}</p>
                                        </div>
                                        <div>
                                            <span class="text-content-tertiary">Pagu Anggaran</span>
                                            <p class="font-bold text-primary">{{ formatRupiah(writtenTotalRp) }}</p>
                                        </div>
                                    </div>

                                    <div class="border-t border-stroke pt-2.5 text-xs">
                                        <span class="text-content-tertiary">Titik GPS & Radius</span>
                                        <p class="font-mono font-semibold text-content">
                                            Lat: {{ form.lat }}, Lng: {{ form.lng }} (Geofence &le; 300m)
                                        </p>
                                    </div>

                                    <div class="border-t border-stroke pt-2.5 text-xs">
                                        <span class="text-content-tertiary">Parameter Ukuran Cek Spek Warga</span>
                                        <ul class="mt-1 list-inside list-disc font-medium text-content">
                                            <li v-for="(m, i) in form.measurements" :key="i">
                                                {{ m.name }}: <span class="font-bold">{{ m.value }} {{ m.unit }}</span>
                                            </li>
                                        </ul>
                                    </div>
                                </div>

                                <div
                                    class="rounded-lg border border-warning-border bg-warning-subtle p-3.5 text-xs text-warning"
                                >
                                    <p class="font-bold">Integritas Publik Terjamin (D8 & D10):</p>
                                    <p class="mt-0.5">
                                        Setelah tombol "Terbitkan Proyek Sekarang" ditekan, proyek fisik akan langsung
                                        berstatus publik. Berkas berstempel basah dan kode QR siap dipasang pada plang
                                        proyek di lapangan.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Live Preview Card (lg:col-span-5) -->
                        <div class="space-y-4 lg:col-span-5">
                            <div class="sticky top-0 space-y-3 rounded-xl border border-stroke bg-surface-subtle p-4">
                                <div>
                                    <h4 class="text-xs font-bold tracking-wide text-content uppercase">
                                        Pratinjau Tampilan Warga
                                    </h4>
                                    <p class="mt-0.5 text-xs text-content-tertiary">
                                        Simulasi bagaimana proyek ini terlihat bagi warga desa.
                                    </p>
                                </div>

                                <div class="rounded-lg border border-stroke bg-surface p-4 shadow-sm">
                                    <div class="flex items-start justify-between gap-2">
                                        <h5 class="text-sm leading-snug font-bold text-content">
                                            {{ form.name.trim() || 'Nama Proyek Pembangunan Fisik' }}
                                        </h5>
                                        <Badge color="positive"> Terbit </Badge>
                                    </div>

                                    <p class="mt-1 text-xs text-content-tertiary">
                                        {{ selectedTypeLabel }} &middot; {{ form.fund_source }} {{ form.year }}
                                    </p>

                                    <div class="mt-3 flex items-baseline justify-between border-t border-stroke pt-3">
                                        <span class="text-xs text-content-secondary">Total Pagu (Item RAB)</span>
                                        <span class="text-sm font-bold text-primary tabular-nums">
                                            {{ formatRupiah(writtenTotalRp) }}
                                        </span>
                                    </div>

                                    <!-- Items count preview -->
                                    <div class="mt-2 text-[11px] text-content-secondary">
                                        {{ totalItems || 0 }} baris item anggaran tercatat
                                    </div>

                                    <!-- Spek List Preview -->
                                    <div
                                        v-if="form.measurements.length > 0"
                                        class="mt-3 rounded-md bg-surface-subtle p-2.5 text-xs"
                                    >
                                        <span class="block font-semibold text-content-secondary"
                                            >Parameter Ukuran Fisik:</span
                                        >
                                        <ul class="mt-1.5 space-y-1 text-content">
                                            <li
                                                v-for="(spek, i) in form.measurements.slice(0, 3)"
                                                :key="i"
                                                class="flex items-center justify-between text-xs"
                                            >
                                                <span class="truncate text-content-secondary">{{
                                                    spek.name || 'Parameter'
                                                }}</span>
                                                <span class="shrink-0 font-semibold text-content tabular-nums">
                                                    {{ spek.value || '0' }} {{ spek.unit }}
                                                </span>
                                            </li>
                                        </ul>
                                        <span
                                            v-if="form.measurements.length > 3"
                                            class="mt-1 block text-right text-[10px] text-content-tertiary"
                                        >
                                            +{{ form.measurements.length - 3 }} parameter lainnya
                                        </span>
                                    </div>

                                    <div
                                        class="mt-3 flex items-center justify-between border-t border-stroke pt-2.5 text-[11px] text-content-tertiary"
                                    >
                                        <span class="truncate">{{ village.name }}, {{ village.regency }}</span>
                                        <span v-if="form.lat && form.lng" class="shrink-0 font-medium text-positive">
                                            Geofence Aktif &check;
                                        </span>
                                        <span v-else class="shrink-0 text-content-tertiary"> Tanpa Geofence </span>
                                    </div>
                                </div>

                                <div
                                    class="rounded-lg border border-primary/20 bg-primary-subtle p-2.5 text-[11px] text-primary"
                                >
                                    <span class="font-semibold">Live Preview:</span> Kartu di atas diperbarui seketika
                                    mengikuti isian nama, jenis proyek, GPS, dan ukuran Cek Spek yang Anda tentukan di
                                    wizard.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer Navigation -->
                <div class="flex items-center justify-between border-t border-stroke bg-surface-subtle px-6 py-4">
                    <button
                        v-if="currentStep > 1"
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-stroke bg-surface px-4 py-2 text-xs font-semibold text-content transition-colors hover:bg-surface-muted"
                        @click="prevStep"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                        <span>Kembali</span>
                    </button>
                    <div v-else />

                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="rounded-lg px-3 py-2 text-xs font-medium text-content-secondary hover:bg-surface-muted"
                            @click="emit('close')"
                        >
                            Batal
                        </button>

                        <button
                            v-if="currentStep < 4"
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-primary px-4 py-2 text-xs font-bold text-content-inverse shadow-xs transition-colors hover:bg-primary-hover"
                            @click="nextStep"
                        >
                            <span>Lanjut</span>
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M9 5l7 7-7 7"
                                />
                            </svg>
                        </button>

                        <button
                            v-else
                            type="button"
                            :disabled="form.processing"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-primary px-5 py-2.5 text-xs font-bold text-content-inverse shadow-md transition-colors hover:bg-primary-hover disabled:opacity-50"
                            @click="submitPublish"
                        >
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
                            <span>{{
                                form.processing ? 'Menerbitkan...' : 'Terbitkan Proyek ke Publik Sekarang'
                            }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
