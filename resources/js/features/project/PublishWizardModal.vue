<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import Alert from '@/components/ui/Alert.vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import CitizenMobilePreview from '@/features/project/CitizenMobilePreview.vue';
import { formatRupiah } from '@/lib/format';
import { fundingLabel } from '@/lib/projectStory';

interface MeasurementItem {
    name: string;
    value: string;
    unit: string;
    how_to: string;
    origin: 'template' | 'desa';
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

const currentStep = ref<1 | 2 | 3 | 4>(1);
const gpsLoading = ref(false);
const gpsError = ref<string | null>(null);
const stepErrors = ref<string[]>([]);

const form = useForm({
    name: props.initialData?.name ?? '',
    type: props.initialData?.type ?? 'rabat_beton',
    description: props.initialData?.description ?? '',
    fund_source: props.initialData?.fund_source ?? 'Dana Desa (DDS)',
    year: props.initialData?.year ?? new Date().getFullYear(),
    lat: props.initialData?.lat ? String(props.initialData.lat) : '-8.4124',
    lng: props.initialData?.lng ? String(props.initialData.lng) : '115.2482',
    measurements: [] as MeasurementItem[],
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

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            currentStep.value = 1;
            stepErrors.value = [];
            if (props.initialData) {
                form.name = props.initialData.name ?? form.name;
                form.type = props.initialData.type ?? form.type;
                form.description = props.initialData.description ?? form.description;
                form.fund_source = props.initialData.fund_source ?? form.fund_source;
                form.year = props.initialData.year ?? form.year;
                if (props.initialData.lat) {
                    form.lat = String(props.initialData.lat);
                }
                if (props.initialData.lng) {
                    form.lng = String(props.initialData.lng);
                }
            }
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
        }
    },
    { immediate: true },
);

watch(
    () => form.type,
    (newType) => {
        syncMeasurementsForType(newType, true);
    },
);

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
    if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
        return null;
    }
    if (lat < -90 || lat > 90 || lng < -180 || lng > 180) {
        return null;
    }

    return { lat, lng };
});

const googleMapsEmbedUrl = computed<string | null>(() => {
    if (!validCoordinates.value) {
        return null;
    }
    const { lat, lng } = validCoordinates.value;
    return `https://maps.google.com/maps?q=${lat},${lng}&hl=id&z=16&output=embed`;
});

const googleMapsExternalUrl = computed<string | null>(() => {
    if (!validCoordinates.value) {
        return null;
    }
    const { lat, lng } = validCoordinates.value;
    return `https://www.google.com/maps?q=${lat},${lng}`;
});

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
        if (!form.name.trim()) {
            stepErrors.value.push('Nama kegiatan proyek wajib diisi.');
        }
        if (!form.type) {
            stepErrors.value.push('Pilih jenis pekerjaan proyek.');
        }
        if (!form.fund_source) {
            stepErrors.value.push('Sumber dana wajib diisi.');
        }
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
            stepErrors.value.push('Ukuran Cek Spek warga wajib diisi.');
        }
        form.measurements.forEach((m, idx) => {
            if (!m.name.trim()) {
                stepErrors.value.push(`Ukuran #${idx + 1}: Nama ukuran wajib diisi.`);
            }
            const val = parseFloat(m.value);
            if (Number.isNaN(val) || val <= 0) {
                stepErrors.value.push(`Ukuran #${idx + 1} (${m.name || 'Ukuran'}): Nilai target harus lebih dari 0.`);
            }
            if (!m.unit.trim()) {
                stepErrors.value.push(`Ukuran #${idx + 1} (${m.name || 'Ukuran'}): Satuan wajib diisi.`);
            }
        });
    }

    return stepErrors.value.length === 0;
}

function nextStep(): void {
    if (validateStep(currentStep.value) && currentStep.value < 4) {
        currentStep.value = (currentStep.value + 1) as 1 | 2 | 3 | 4;
    }
}

function prevStep(): void {
    stepErrors.value = [];
    if (currentStep.value > 1) {
        currentStep.value = (currentStep.value - 1) as 1 | 2 | 3 | 4;
    }
}

function submitPublish(): void {
    if (!validateStep(1) || !validateStep(2) || !validateStep(3)) {
        return;
    }

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
            <div class="fixed inset-0 bg-content/50" aria-hidden="true" @click="emit('close')" />

            <!-- Modal Panel -->
            <div
                class="relative z-10 flex max-h-[92vh] w-full max-w-5xl flex-col overflow-hidden rounded-lg border border-stroke bg-surface shadow-sm lg:max-w-6xl"
                role="dialog"
                aria-modal="true"
                aria-labelledby="wizard-modal-title"
            >
                <!-- Modal Header -->
                <div class="flex items-center justify-between border-b border-stroke bg-surface-subtle px-6 py-4">
                    <div>
                        <h2 id="wizard-modal-title" class="text-base font-semibold text-content sm:text-lg">
                            Terbitkan proyek
                        </h2>
                        <p class="text-xs text-content-secondary">
                            Isi 4 langkah. Setelah terbit, halaman proyek bisa dibuka warga.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-md text-content-secondary transition-colors hover:bg-surface-muted hover:text-content focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                        aria-label="Tutup modal"
                        @click="emit('close')"
                    >
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                    </button>
                </div>

                <!-- Step Indicator Bar (text-only progress without decorative numbered circles) -->
                <div class="border-b border-stroke bg-surface px-6 py-2.5">
                    <div class="grid grid-cols-4 gap-2 text-xs">
                        <span :class="currentStep === 1 ? 'font-semibold text-primary' : 'text-content-tertiary'">
                            1. Identitas &amp; pagu
                        </span>
                        <span :class="currentStep === 2 ? 'font-semibold text-primary' : 'text-content-tertiary'">
                            2. Lokasi proyek
                        </span>
                        <span :class="currentStep === 3 ? 'font-semibold text-primary' : 'text-content-tertiary'">
                            3. Ukuran Cek Spek
                        </span>
                        <span :class="currentStep === 4 ? 'font-semibold text-primary' : 'text-content-tertiary'">
                            4. Konfirmasi
                        </span>
                    </div>
                </div>

                <!-- Validation Error Alert -->
                <div v-if="stepErrors.length > 0" class="border-b border-stroke px-6 py-3">
                    <Alert color="warning">
                        <ul class="list-inside list-disc space-y-0.5">
                            <li v-for="(err, idx) in stepErrors" :key="idx">{{ err }}</li>
                        </ul>
                    </Alert>
                </div>

                <!-- Step Body -->
                <div class="flex-1 overflow-y-auto p-4 text-sm sm:p-6">
                    <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
                        <!-- Left Column: Form Steps -->
                        <div class="space-y-4 lg:col-span-7">
                            <!-- STEP 1: IDENTITAS & PAGU -->
                            <div v-if="currentStep === 1" class="space-y-4">
                                <div>
                                    <p class="text-xs font-medium text-content-secondary">Langkah 1 dari 4</p>
                                    <h3 class="text-base font-semibold text-content">Identitas dan pagu anggaran</h3>
                                    <p class="text-xs text-content-secondary">
                                        Isi nama kegiatan dan sumber dana sesuai APBDes.
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-content" for="wizard-name">
                                        Nama kegiatan <span class="text-negative">*</span>
                                    </label>
                                    <input
                                        id="wizard-name"
                                        v-model="form.name"
                                        type="text"
                                        class="mt-1 block h-11 w-full rounded-md border border-stroke-strong bg-surface px-3 text-sm text-content focus:border-primary focus:outline-none"
                                        placeholder="Contoh: Pembangunan Jalan Rabat Beton Banjar Melati"
                                    />
                                </div>

                                <div>
                                    <div class="flex items-center justify-between">
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
                                        >
                                            <option v-for="t in types" :key="t.value" :value="t.value">
                                                {{ t.label }}
                                            </option>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-content" for="wizard-fund-source">
                                            Sumber dana <span class="text-negative">*</span>
                                        </label>
                                        <select
                                            id="wizard-fund-source"
                                            v-model="form.fund_source"
                                            class="mt-1 block h-11 w-full rounded-md border border-stroke-strong bg-surface px-3 text-sm text-content focus:border-primary focus:outline-none"
                                        >
                                            <option v-for="src in fundSources" :key="src" :value="src">
                                                {{ src }}
                                            </option>
                                        </select>
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
                                            class="mt-1 block h-11 w-full rounded-md border border-stroke-strong bg-surface px-3 text-sm text-content tabular-nums focus:border-primary focus:outline-none"
                                        />
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
                                <div>
                                    <p class="text-xs font-medium text-content-secondary">Langkah 2 dari 4</p>
                                    <h3 class="text-base font-semibold text-content">Lokasi proyek</h3>
                                    <p class="text-xs text-content-secondary">
                                        Warga hanya bisa mengirim Cek Spek dalam radius 300 m dari titik ini.
                                    </p>
                                </div>

                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <span class="text-sm font-medium text-content">Koordinat proyek</span>
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        size="sm"
                                        :loading="gpsLoading"
                                        @click="getCurrentGps"
                                    >
                                        {{ gpsLoading ? 'Mendeteksi...' : 'Gunakan koordinat saat ini' }}
                                    </Button>
                                </div>

                                <Alert v-if="gpsError" color="negative">
                                    {{ gpsError }}
                                </Alert>

                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div>
                                        <label class="block text-sm font-medium text-content" for="wizard-lat">
                                            Latitude <span class="text-negative">*</span>
                                        </label>
                                        <input
                                            id="wizard-lat"
                                            v-model="form.lat"
                                            type="text"
                                            class="mt-1 block h-11 w-full rounded-md border border-stroke-strong bg-surface px-3 text-sm text-content tabular-nums focus:border-primary focus:outline-none"
                                            placeholder="-8.412400"
                                        />
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-content" for="wizard-lng">
                                            Longitude <span class="text-negative">*</span>
                                        </label>
                                        <input
                                            id="wizard-lng"
                                            v-model="form.lng"
                                            type="text"
                                            class="mt-1 block h-11 w-full rounded-md border border-stroke-strong bg-surface px-3 text-sm text-content tabular-nums focus:border-primary focus:outline-none"
                                            placeholder="115.248200"
                                        />
                                    </div>
                                </div>

                                <div class="space-y-1.5">
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs font-medium text-content">Periksa lokasi di peta</span>
                                        <a
                                            v-if="googleMapsExternalUrl"
                                            :href="googleMapsExternalUrl"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="text-xs font-medium text-primary hover:underline"
                                        >
                                            Buka di Google Maps
                                        </a>
                                    </div>

                                    <div
                                        v-if="googleMapsEmbedUrl"
                                        class="overflow-hidden rounded-lg border border-stroke bg-surface-subtle"
                                    >
                                        <iframe
                                            :src="googleMapsEmbedUrl"
                                            title="Peta lokasi proyek"
                                            class="h-64 w-full border-0"
                                            loading="lazy"
                                            referrerpolicy="no-referrer-when-downgrade"
                                            allowfullscreen
                                        />
                                    </div>

                                    <div
                                        v-else
                                        class="flex h-64 items-center justify-center rounded-lg border border-dashed border-stroke-strong bg-surface-subtle px-4 text-center text-xs text-content-secondary"
                                    >
                                        Masukkan Latitude dan Longitude yang valid untuk melihat peta.
                                    </div>
                                </div>
                            </div>

                            <!-- STEP 3: UKURAN CEK SPEK -->
                            <div v-else-if="currentStep === 3" class="space-y-4">
                                <div>
                                    <p class="text-xs font-medium text-content-secondary">Langkah 3 dari 4</p>
                                    <h3 class="text-base font-semibold text-content">Ukuran Cek Spek warga</h3>
                                    <p class="text-xs text-content-secondary">
                                        Ukuran dari template wajib diisi dan tidak bisa dihapus.
                                    </p>
                                </div>

                                <div
                                    class="flex items-center justify-between rounded-lg border border-stroke bg-surface-subtle px-4 py-2.5 text-xs text-content"
                                >
                                    <span class="font-semibold">Jenis: {{ selectedTypeLabel }}</span>
                                    <span class="text-content-secondary tabular-nums">
                                        {{ form.measurements.filter((m) => m.origin === 'template').length }} ukuran
                                        wajib
                                    </span>
                                </div>

                                <div class="space-y-3">
                                    <Card v-for="(item, idx) in form.measurements" :key="idx" padding="base">
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

                                        <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
                                            <div v-if="item.origin === 'desa'" class="sm:col-span-3">
                                                <label class="block text-xs font-medium text-content-secondary">
                                                    Nama ukuran
                                                </label>
                                                <input
                                                    v-model="item.name"
                                                    type="text"
                                                    class="mt-1 block h-10 w-full rounded-md border border-stroke-strong bg-surface px-3 text-sm text-content focus:border-primary focus:outline-none"
                                                    placeholder="Contoh: Kedalaman galian tanah"
                                                />
                                            </div>

                                            <div>
                                                <label class="block text-xs font-medium text-content-secondary">
                                                    Target angka RAB *
                                                </label>
                                                <input
                                                    v-model="item.value"
                                                    type="text"
                                                    class="mt-1 block h-10 w-full rounded-md border border-stroke-strong bg-surface px-3 text-sm font-semibold text-content tabular-nums focus:border-primary focus:outline-none"
                                                    placeholder="Contoh: 12.0"
                                                />
                                            </div>

                                            <div>
                                                <label class="block text-xs font-medium text-content-secondary">
                                                    Satuan *
                                                </label>
                                                <input
                                                    v-model="item.unit"
                                                    type="text"
                                                    class="mt-1 block h-10 w-full rounded-md border border-stroke-strong bg-surface px-3 text-sm text-content focus:border-primary focus:outline-none"
                                                    placeholder="Contoh: cm, meter"
                                                />
                                            </div>

                                            <div class="sm:col-span-3">
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
                                        Tambah ukuran tambahan (maksimal 5)
                                    </Button>
                                </div>
                            </div>

                            <!-- STEP 4: KONFIRMASI TERBIT -->
                            <div v-else-if="currentStep === 4" class="space-y-4">
                                <div>
                                    <p class="text-xs font-medium text-content-secondary">Langkah 4 dari 4</p>
                                    <h3 class="text-base font-semibold text-content">Konfirmasi terbit</h3>
                                    <p class="text-xs text-content-secondary">
                                        Periksa kembali rincian proyek sebelum dibuka untuk warga.
                                    </p>
                                </div>

                                <Card padding="base" surface="subtle" class="space-y-3">
                                    <div class="flex items-start justify-between gap-2 border-b border-stroke pb-2.5">
                                        <div>
                                            <span class="text-xs font-medium text-content-tertiary">Nama kegiatan</span>
                                            <h4 class="text-sm font-semibold text-content">{{ form.name }}</h4>
                                        </div>
                                        <Badge color="positive">Siap terbit</Badge>
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

                                    <div class="border-t border-stroke pt-2.5 text-xs">
                                        <span class="text-content-tertiary">Lokasi dan radius</span>
                                        <p class="font-semibold text-content tabular-nums">
                                            {{ form.lat }}, {{ form.lng }} (radius lokasi 300 m)
                                        </p>
                                    </div>

                                    <div class="border-t border-stroke pt-2.5 text-xs">
                                        <span class="text-content-tertiary">Ukuran Cek Spek warga</span>
                                        <ul class="mt-1 list-inside list-disc font-medium text-content">
                                            <li v-for="(m, i) in form.measurements" :key="i" class="tabular-nums">
                                                {{ m.name }}:
                                                <span class="font-semibold">{{ m.value }} {{ m.unit }}</span>
                                            </li>
                                        </ul>
                                    </div>
                                </Card>

                                <Alert color="warning" title="Setelah terbit, proyek tidak bisa ditarik kembali">
                                    Perubahan berikutnya tercatat dan terlihat warga.
                                </Alert>
                            </div>
                        </div>

                        <!-- Right Column: Live Mobile Preview -->
                        <div class="lg:col-span-5">
                            <div class="sticky top-0">
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
                </div>

                <!-- Modal Footer -->
                <div class="flex items-center justify-between border-t border-stroke bg-surface-subtle px-6 py-4">
                    <Button v-if="currentStep > 1" type="button" variant="secondary" size="sm" @click="prevStep">
                        Kembali
                    </Button>
                    <div v-else />

                    <div class="flex items-center gap-2">
                        <Button type="button" variant="ghost" size="sm" @click="emit('close')">Batal</Button>

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
    </Teleport>
</template>
