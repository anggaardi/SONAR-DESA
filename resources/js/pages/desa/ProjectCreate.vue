<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import Alert from '@/components/ui/Alert.vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatRupiah } from '@/lib/format';

interface VillageInfo {
    id: number;
    name: string;
    slug: string;
    regency: string;
}

interface ProjectTypeOption {
    value: string;
    label: string;
}

interface MeasurementTemplateItem {
    name: string;
    unit: string;
    default_value: string;
    how_to: string;
}

interface PriceStandardOption {
    id: number;
    code: string;
    name: string;
    unit: string;
    price_rp: number;
}

interface FormItem {
    raw_name: string;
    volume: string | number;
    unit: string;
    unit_price_rp: string | number;
    standard_id: number | null;
}

interface FormMeasurement {
    name: string;
    value: string | number;
    unit: string;
    how_to: string;
    origin: 'template' | 'desa';
}

interface Props {
    village: VillageInfo;
    types: ProjectTypeOption[];
    templates: Record<string, MeasurementTemplateItem[]>;
    fundSources: string[];
    priceStandards: PriceStandardOption[];
}

const props = defineProps<Props>();

const defaultType = props.types[0]?.value || 'rabat_beton';
const defaultFundSource = props.fundSources[0] || 'Dana Desa (DDS)';
const currentYear = new Date().getFullYear();

const initialMeasurements: FormMeasurement[] = (props.templates[defaultType] || []).map((t) => ({
    name: t.name,
    value: t.default_value,
    unit: t.unit,
    how_to: t.how_to,
    origin: 'template' as const,
}));

const initialItems: FormItem[] = [
    {
        raw_name: 'Semen Portland Komposit 50kg',
        volume: '150',
        unit: 'sak',
        unit_price_rp: '72000',
        standard_id: props.priceStandards[0]?.id ?? null,
    },
    {
        raw_name: 'Pasir Pasang / Pasir Cor',
        volume: '40',
        unit: 'm3',
        unit_price_rp: '240000',
        standard_id: props.priceStandards[1]?.id ?? null,
    },
];

const form = useForm({
    file: null as File | null,
    name: '',
    type: defaultType,
    fund_source: defaultFundSource,
    year: currentYear,
    amount_rp: '' as unknown as number,
    lat: '' as unknown as number,
    lng: '' as unknown as number,
    is_published: true,
    items: initialItems,
    measurements: initialMeasurements,
});

// Update measurements template when project type changes
watch(
    () => form.type,
    (newType) => {
        const tpl = props.templates[newType];
        if (tpl && tpl.length > 0) {
            form.measurements = tpl.map((t) => ({
                name: t.name,
                value: t.default_value,
                unit: t.unit,
                how_to: t.how_to,
                origin: 'template' as const,
            }));
        }
    },
);

// Auto-calculate total amount from items
const calculatedTotalRp = computed(() => {
    return form.items.reduce((sum, item) => {
        const vol = Number(item.volume) || 0;
        const price = Number(item.unit_price_rp) || 0;
        return sum + Math.round(vol * price);
    }, 0);
});

watch(
    calculatedTotalRp,
    (total) => {
        form.amount_rp = total;
    },
    { immediate: true },
);

function addItem(): void {
    if (form.items.length >= 50) {
        return;
    }
    form.items.push({
        raw_name: '',
        volume: '',
        unit: '',
        unit_price_rp: '',
        standard_id: null,
    });
}

function removeItem(index: number): void {
    if (form.items.length <= 1) {
        return;
    }
    form.items.splice(index, 1);
}

function onSelectStandard(index: number, standardIdVal: string | number | null): void {
    const item = form.items[index];
    if (!item) return;

    if (!standardIdVal || standardIdVal === '') {
        item.standard_id = null;
        return;
    }

    const std = props.priceStandards.find((s) => s.id === Number(standardIdVal));
    if (std) {
        item.standard_id = std.id;
        if (!item.raw_name) {
            item.raw_name = std.name;
        }
        item.unit = std.unit;
        if (!item.unit_price_rp) {
            item.unit_price_rp = std.price_rp;
        }
    }
}

function addCustomMeasurement(): void {
    if (form.measurements.length >= 10) {
        return;
    }
    form.measurements.push({
        name: '',
        value: '',
        unit: 'meter',
        how_to: '',
        origin: 'desa',
    });
}

function removeMeasurement(index: number): void {
    if (form.measurements.length <= 1) {
        return;
    }
    form.measurements.splice(index, 1);
}

// Geolocation helper
const isLocating = ref(false);
const geoError = ref<string | null>(null);

function getMyLocation(): void {
    if (!navigator.geolocation) {
        geoError.value = 'Perangkat Anda tidak mendukung deteksi lokasi otomatis.';
        return;
    }

    isLocating.value = true;
    geoError.value = null;

    navigator.geolocation.getCurrentPosition(
        (position) => {
            form.lat = Number(position.coords.latitude.toFixed(7));
            form.lng = Number(position.coords.longitude.toFixed(7));
            isLocating.value = false;
        },
        (error) => {
            isLocating.value = false;
            switch (error.code) {
                case error.PERMISSION_DENIED:
                    geoError.value = 'Izin akses lokasi ditolak oleh browser.';
                    break;
                case error.POSITION_UNAVAILABLE:
                    geoError.value = 'Informasi lokasi tidak tersedia saat ini.';
                    break;
                case error.TIMEOUT:
                    geoError.value = 'Waktu permintaan lokasi habis. Coba lagi.';
                    break;
                default:
                    geoError.value = 'Gagal mendeteksi lokasi GPS.';
            }
        },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 },
    );
}

const selectedTypeLabel = computed(() => {
    const found = props.types.find((t) => t.value === form.type);
    return found ? found.label : form.type;
});

const selectedFileName = ref<string | null>(null);

function onFileChange(e: Event): void {
    const target = e.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        form.file = target.files[0];
        selectedFileName.value = target.files[0].name;
    }
}

function submit(publishDirectly: boolean): void {
    form.is_published = publishDirectly;
    form.post('/desa/projects', {
        preserveScroll: true,
        forceFormData: true,
    });
}
</script>

<template>
    <AppLayout>
        <Head :title="`Isi Proyek & RAB Manual - ${village.name}`" />

        <template #breadcrumb>
            <Link href="/" class="transition-colors hover:text-primary">Beranda</Link>
            <span>/</span>
            <Link href="/desa" class="transition-colors hover:text-primary">Pemerintah Desa</Link>
            <span>/</span>
            <span class="font-medium text-content">Tambah Proyek Fisik</span>
        </template>

        <template #nav>
            <Link
                href="/desa"
                class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-content-secondary transition-colors hover:bg-surface-muted hover:text-content focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
            >
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
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
                href="/desa/projects/create"
                class="flex items-center gap-3 rounded-xl bg-primary-subtle px-3 py-2.5 text-sm font-semibold text-primary transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
            >
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4v16m8-8H4" />
                </svg>
                <span>Tambah Proyek Fisik</span>
            </Link>
            <Link
                :href="`/d/${village.slug}`"
                class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-content-secondary transition-colors hover:bg-surface-muted hover:text-content focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
            >
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
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
                <Link
                    href="/logout"
                    method="post"
                    as="button"
                    class="font-medium text-negative underline-offset-2 hover:underline"
                >
                    Keluar
                </Link>
            </div>
        </template>

        <!-- Main Form Container -->
        <div class="mx-auto max-w-6xl space-y-8">
            <!-- Breadcrumb & Header -->
            <div>
                <nav class="mb-2 flex items-center gap-2 text-xs text-content-tertiary" aria-label="Breadcrumb">
                    <Link href="/desa" class="hover:text-primary">Dashboard</Link>
                    <span>/</span>
                    <span class="font-semibold text-content">Input Proyek Fisik & Tabel RAB (Isi Manual)</span>
                </nav>
                <h1 class="text-2xl font-bold tracking-tight text-content sm:text-3xl">
                    Input Proyek Fisik & Tabel RAB
                </h1>
                <p class="mt-1 text-sm text-content-secondary">
                    Jalur Isi Manual: Daftarkan kegiatan proyek beserta berkas fisik asli berstempel dan rincian tabel
                    harga agar dapat dibuktikan warga di lapangan.
                </p>
            </div>

            <!-- Global Error Banner if any -->
            <Alert v-if="Object.keys(form.errors).length > 0" color="negative" title="Periksa kembali isian formulir">
                Terdapat beberapa isian yang belum sesuai. Silakan periksa pesan di bawah masing-masing kolom.
            </Alert>

            <div class="grid grid-cols-1 gap-8 lg:grid-cols-12">
                <!-- Left Column: Form Cards (8 cols) -->
                <div class="space-y-6 lg:col-span-8">
                    <!-- KARTU 1: Berkas Fisik Asli & Informasi Kegiatan -->
                    <Card padding="app">
                        <div class="flex items-start gap-4">
                            <div
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary-subtle text-sm font-bold text-primary"
                                aria-hidden="true"
                            >
                                1
                            </div>
                            <div class="flex-1">
                                <h2 class="text-base font-bold text-content">Berkas Asli RAB & Informasi Kegiatan</h2>
                                <p class="text-xs text-content-tertiary">
                                    Unggah berkas fisik bertanda tangan/stempel basah (D9) dan tentukan identitas
                                    kegiatan proyek.
                                </p>
                            </div>
                        </div>

                        <div class="mt-6 space-y-4">
                            <!-- Berkas Asli Upload (D9) -->
                            <div>
                                <label for="project-file" class="block text-xs font-semibold text-content">
                                    Dokumen Fisik RAB Asli (PDF / JPG / PNG max 10MB)
                                    <span class="text-negative">*</span>
                                </label>
                                <div class="mt-1.5 flex items-center gap-3">
                                    <input
                                        id="project-file"
                                        type="file"
                                        accept=".pdf,.jpg,.jpeg,.png"
                                        class="block w-full text-xs text-content-secondary file:mr-3 file:rounded-md file:border-0 file:bg-surface-muted file:px-3 file:py-2 file:text-xs file:font-semibold file:text-content hover:file:bg-stroke"
                                        required
                                        @change="onFileChange"
                                    />
                                </div>
                                <p v-if="selectedFileName" class="mt-1 text-xs font-medium text-positive">
                                    File dipilih: {{ selectedFileName }}
                                </p>
                                <p v-if="form.errors.file" class="mt-1 text-xs font-medium text-negative">
                                    {{ form.errors.file }}
                                </p>
                            </div>

                            <!-- Nama Proyek -->
                            <div>
                                <label for="project-name" class="block text-xs font-semibold text-content">
                                    Nama Kegiatan Proyek Fisik <span class="text-negative">*</span>
                                </label>
                                <input
                                    id="project-name"
                                    v-model="form.name"
                                    type="text"
                                    placeholder="Contoh: Betonisasi Jalan Usaha Tani Banjar Melinggih"
                                    class="mt-1.5 h-10 w-full rounded-md border border-stroke-strong bg-surface px-3 text-sm text-content placeholder:text-content-tertiary focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                                    :class="form.errors.name ? 'border-negative' : ''"
                                    required
                                />
                                <p v-if="form.errors.name" class="mt-1 text-xs font-medium text-negative">
                                    {{ form.errors.name }}
                                </p>
                            </div>

                            <!-- Jenis Pekerjaan & Tahun -->
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label for="project-type" class="block text-xs font-semibold text-content">
                                        Jenis Pekerjaan Fisik <span class="text-negative">*</span>
                                    </label>
                                    <select
                                        id="project-type"
                                        v-model="form.type"
                                        class="mt-1.5 h-10 w-full rounded-md border border-stroke-strong bg-surface px-3 text-sm text-content focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                                        :class="form.errors.type ? 'border-negative' : ''"
                                    >
                                        <option v-for="t in types" :key="t.value" :value="t.value">
                                            {{ t.label }}
                                        </option>
                                    </select>
                                    <p v-if="form.errors.type" class="mt-1 text-xs font-medium text-negative">
                                        {{ form.errors.type }}
                                    </p>
                                </div>

                                <div>
                                    <label for="project-year" class="block text-xs font-semibold text-content">
                                        Tahun Anggaran <span class="text-negative">*</span>
                                    </label>
                                    <input
                                        id="project-year"
                                        v-model.number="form.year"
                                        type="number"
                                        min="2020"
                                        max="2035"
                                        class="mt-1.5 h-10 w-full rounded-md border border-stroke-strong bg-surface px-3 text-sm text-content tabular-nums focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                                        :class="form.errors.year ? 'border-negative' : ''"
                                        required
                                    />
                                    <p v-if="form.errors.year" class="mt-1 text-xs font-medium text-negative">
                                        {{ form.errors.year }}
                                    </p>
                                </div>
                            </div>

                            <!-- Sumber Dana & Pagu Total Terhitung -->
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label for="project-fund-source" class="block text-xs font-semibold text-content">
                                        Sumber Dana <span class="text-negative">*</span>
                                    </label>
                                    <select
                                        id="project-fund-source"
                                        v-model="form.fund_source"
                                        class="mt-1.5 h-10 w-full rounded-md border border-stroke-strong bg-surface px-3 text-sm text-content focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                                        :class="form.errors.fund_source ? 'border-negative' : ''"
                                    >
                                        <option v-for="fs in fundSources" :key="fs" :value="fs">
                                            {{ fs }}
                                        </option>
                                    </select>
                                    <p v-if="form.errors.fund_source" class="mt-1 text-xs font-medium text-negative">
                                        {{ form.errors.fund_source }}
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-content">
                                        Total Pagu Anggaran (Dihitung dari Item)
                                    </label>
                                    <div
                                        class="mt-1.5 flex h-10 items-center rounded-md border border-stroke bg-surface-muted px-3 text-sm font-bold text-primary tabular-nums"
                                    >
                                        {{ formatRupiah(calculatedTotalRp) }}
                                    </div>
                                    <p class="mt-1 text-[11px] text-content-tertiary">
                                        Otomatis dijumlahkan dari tabel item pekerjaan di bawah.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </Card>

                    <!-- KARTU 2: Tabel Rincian Anggaran (RAB) — Standar Harga SSH (Opsi A) -->
                    <Card padding="app">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex items-start gap-4">
                                <div
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary-subtle text-sm font-bold text-primary"
                                    aria-hidden="true"
                                >
                                    2
                                </div>
                                <div>
                                    <h2 class="text-base font-bold text-content">
                                        Rincian Item Anggaran & Standar Harga SSH
                                    </h2>
                                    <p class="text-xs text-content-tertiary">
                                        Pilih padanan Standar Satuan Harga (SSH) Gianyar agar perbandingan harga dapat
                                        dihitung secara deterministik oleh sistem.
                                    </p>
                                </div>
                            </div>
                            <Button type="button" variant="secondary" size="sm" @click="addItem">
                                + Tambah Item
                            </Button>
                        </div>

                        <p v-if="form.errors.items" class="mt-4 text-xs font-medium text-negative">
                            {{ form.errors.items }}
                        </p>

                        <div class="mt-6 space-y-4">
                            <div
                                v-for="(item, idx) in form.items"
                                :key="idx"
                                class="rounded-lg border border-stroke bg-surface-subtle p-4"
                            >
                                <div class="flex items-center justify-between gap-2 border-b border-stroke pb-2">
                                    <span class="text-xs font-bold text-content"> Item #{{ idx + 1 }} </span>
                                    <button
                                        v-if="form.items.length > 1"
                                        type="button"
                                        class="text-xs font-medium text-negative hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-negative-solid"
                                        @click="removeItem(idx)"
                                    >
                                        Hapus Baris
                                    </button>
                                </div>

                                <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-12">
                                    <!-- Pemilih Standar Harga SSH (Opsi A) -->
                                    <div class="sm:col-span-12">
                                        <label
                                            :for="`item-std-${idx}`"
                                            class="block text-xs font-medium text-content-secondary"
                                        >
                                            Acuan Standar Satuan Harga (SSH) Gianyar
                                        </label>
                                        <select
                                            :id="`item-std-${idx}`"
                                            v-model="item.standard_id"
                                            class="mt-1 h-9 w-full rounded-md border border-stroke bg-surface px-3 text-xs text-content focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                                            @change="
                                                (e) => onSelectStandard(idx, (e.target as HTMLSelectElement).value)
                                            "
                                        >
                                            <option :value="null">-- Tanpa Acuan SSH (Item Khusus / Lokal) --</option>
                                            <option v-for="std in priceStandards" :key="std.id" :value="std.id">
                                                [{{ std.code }}] {{ std.name }} ({{ std.unit }}) — Standar:
                                                {{ formatRupiah(std.price_rp) }}
                                            </option>
                                        </select>
                                    </div>

                                    <!-- Nama Uraian Pekerjaan -->
                                    <div class="sm:col-span-5">
                                        <label
                                            :for="`item-name-${idx}`"
                                            class="block text-xs font-medium text-content-secondary"
                                        >
                                            Uraian Bahan / Pekerjaan <span class="text-negative">*</span>
                                        </label>
                                        <input
                                            :id="`item-name-${idx}`"
                                            v-model="item.raw_name"
                                            type="text"
                                            placeholder="Contoh: Semen Portland 50kg"
                                            class="mt-1 h-9 w-full rounded-md border border-stroke bg-surface px-3 text-xs text-content focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                                            required
                                        />
                                    </div>

                                    <!-- Volume -->
                                    <div class="sm:col-span-2">
                                        <label
                                            :for="`item-vol-${idx}`"
                                            class="block text-xs font-medium text-content-secondary"
                                        >
                                            Volume <span class="text-negative">*</span>
                                        </label>
                                        <input
                                            :id="`item-vol-${idx}`"
                                            v-model="item.volume"
                                            type="number"
                                            step="0.01"
                                            placeholder="100"
                                            class="mt-1 h-9 w-full rounded-md border border-stroke bg-surface px-3 text-xs text-content tabular-nums focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                                            required
                                        />
                                    </div>

                                    <!-- Satuan -->
                                    <div class="sm:col-span-2">
                                        <label
                                            :for="`item-unit-${idx}`"
                                            class="block text-xs font-medium text-content-secondary"
                                        >
                                            Satuan <span class="text-negative">*</span>
                                        </label>
                                        <input
                                            :id="`item-unit-${idx}`"
                                            v-model="item.unit"
                                            type="text"
                                            placeholder="sak / m3"
                                            class="mt-1 h-9 w-full rounded-md border border-stroke bg-surface px-3 text-xs text-content focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                                            required
                                        />
                                    </div>

                                    <!-- Harga Satuan -->
                                    <div class="sm:col-span-3">
                                        <label
                                            :for="`item-price-${idx}`"
                                            class="block text-xs font-medium text-content-secondary"
                                        >
                                            Harga Satuan (Rp) <span class="text-negative">*</span>
                                        </label>
                                        <input
                                            :id="`item-price-${idx}`"
                                            v-model="item.unit_price_rp"
                                            type="number"
                                            step="100"
                                            placeholder="70000"
                                            class="mt-1 h-9 w-full rounded-md border border-stroke bg-surface px-3 text-xs text-content tabular-nums focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                                            required
                                        />
                                    </div>
                                </div>

                                <div class="mt-2 text-right text-xs text-content-tertiary">
                                    Subtotal:
                                    <strong class="font-semibold text-content tabular-nums">
                                        {{
                                            formatRupiah((Number(item.volume) || 0) * (Number(item.unit_price_rp) || 0))
                                        }}
                                    </strong>
                                </div>
                            </div>
                        </div>
                    </Card>

                    <!-- KARTU 3: Titik Koordinat Lokasi Proyek (Geofence) -->
                    <Card padding="app">
                        <div class="flex items-start gap-4">
                            <div
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary-subtle text-sm font-bold text-primary"
                                aria-hidden="true"
                            >
                                3
                            </div>
                            <div class="flex-1">
                                <h2 class="text-base font-bold text-content">
                                    Titik Koordinat Lokasi Proyek (Geofence)
                                </h2>
                                <p class="text-xs text-content-tertiary">
                                    Kunci lokasi proyek agar warga hanya bisa mengirim Cek Spek jika berada dalam radius
                                    &le; 300 meter.
                                </p>
                            </div>
                        </div>

                        <div class="mt-6 space-y-4">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label for="project-lat" class="block text-xs font-semibold text-content">
                                        Latitude (Garis Lintang)
                                    </label>
                                    <input
                                        id="project-lat"
                                        v-model="form.lat"
                                        type="text"
                                        placeholder="Contoh: -8.5412340"
                                        class="mt-1.5 h-10 w-full rounded-md border border-stroke-strong bg-surface px-3 text-sm text-content tabular-nums focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                                        :class="form.errors.lat ? 'border-negative' : ''"
                                    />
                                    <p v-if="form.errors.lat" class="mt-1 text-xs font-medium text-negative">
                                        {{ form.errors.lat }}
                                    </p>
                                </div>

                                <div>
                                    <label for="project-lng" class="block text-xs font-semibold text-content">
                                        Longitude (Garis Bujur)
                                    </label>
                                    <input
                                        id="project-lng"
                                        v-model="form.lng"
                                        type="text"
                                        placeholder="Contoh: 115.3214560"
                                        class="mt-1.5 h-10 w-full rounded-md border border-stroke-strong bg-surface px-3 text-sm text-content tabular-nums focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                                        :class="form.errors.lng ? 'border-negative' : ''"
                                    />
                                    <p v-if="form.errors.lng" class="mt-1 text-xs font-medium text-negative">
                                        {{ form.errors.lng }}
                                    </p>
                                </div>
                            </div>

                            <!-- Action Ambil Lokasi -->
                            <div class="flex flex-wrap items-center gap-3">
                                <Button
                                    type="button"
                                    variant="secondary"
                                    size="sm"
                                    :loading="isLocating"
                                    @click="getMyLocation"
                                >
                                    <svg
                                        class="h-4 w-4 shrink-0 text-content"
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
                                    <span>Ambil Lokasi Saya Saat Ini (GPS)</span>
                                </Button>
                                <span class="text-xs text-content-tertiary">
                                    atau salin koordinat titik tengah langsung dari Google Maps
                                </span>
                            </div>

                            <p v-if="geoError" class="text-xs font-medium text-negative">
                                {{ geoError }}
                            </p>

                            <!-- Geofence Active Indicator -->
                            <div
                                v-if="form.lat && form.lng"
                                class="flex items-center gap-2.5 rounded-md border border-positive-border bg-positive-subtle px-3.5 py-2.5 text-xs font-medium text-positive"
                            >
                                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M5 13l4 4L19 7"
                                    />
                                </svg>
                                <span>
                                    Geofence radius 300m aktif pada koordinat
                                    <strong>{{ form.lat }}, {{ form.lng }}</strong> di {{ village.name }}.
                                </span>
                            </div>
                        </div>
                    </Card>

                    <!-- KARTU 4: Parameter Ukuran Fisik Cek Spek -->
                    <Card padding="app">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex items-start gap-4">
                                <div
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary-subtle text-sm font-bold text-primary"
                                    aria-hidden="true"
                                >
                                    4
                                </div>
                                <div>
                                    <h2 class="text-base font-bold text-content">Parameter Ukuran Fisik Cek Spek</h2>
                                    <p class="text-xs text-content-tertiary">
                                        Dimuat otomatis dari template {{ selectedTypeLabel }}. Nilai target ini yang
                                        wajib diukur warga dengan meteran di lokasi proyek.
                                    </p>
                                </div>
                            </div>
                            <Button type="button" variant="secondary" size="sm" @click="addCustomMeasurement">
                                + Tambah Parameter
                            </Button>
                        </div>

                        <p v-if="form.errors.measurements" class="mt-4 text-xs font-medium text-negative">
                            {{ form.errors.measurements }}
                        </p>

                        <div class="mt-6 space-y-4">
                            <div
                                v-for="(spek, index) in form.measurements"
                                :key="index"
                                class="rounded-lg border border-stroke bg-surface-subtle p-4 transition-colors"
                            >
                                <div class="flex items-center justify-between gap-2 pb-3">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-bold text-content"> {{ index + 1 }}. </span>
                                        <Badge :color="spek.origin === 'template' ? 'info' : 'neutral'">
                                            {{ spek.origin === 'template' ? 'Wajib Template' : 'Parameter Tambahan' }}
                                        </Badge>
                                    </div>
                                    <button
                                        v-if="form.measurements.length > 1"
                                        type="button"
                                        class="text-xs font-medium text-negative hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-negative-solid"
                                        @click="removeMeasurement(index)"
                                    >
                                        Hapus
                                    </button>
                                </div>

                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-12">
                                    <div class="sm:col-span-6">
                                        <label
                                            :for="`spek-name-${index}`"
                                            class="block text-xs font-medium text-content-secondary"
                                        >
                                            Nama Parameter Ukuran <span class="text-negative">*</span>
                                        </label>
                                        <input
                                            :id="`spek-name-${index}`"
                                            v-model="spek.name"
                                            type="text"
                                            placeholder="Contoh: Ketebalan Beton"
                                            class="mt-1 h-9 w-full rounded-md border border-stroke bg-surface px-3 text-xs text-content focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                                            required
                                        />
                                    </div>

                                    <div class="sm:col-span-3">
                                        <label
                                            :for="`spek-val-${index}`"
                                            class="block text-xs font-medium text-content-secondary"
                                        >
                                            Target Nilai <span class="text-negative">*</span>
                                        </label>
                                        <input
                                            :id="`spek-val-${index}`"
                                            v-model="spek.value"
                                            type="number"
                                            step="0.01"
                                            placeholder="12.0"
                                            class="mt-1 h-9 w-full rounded-md border border-stroke bg-surface px-3 text-xs text-content tabular-nums focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                                            required
                                        />
                                    </div>

                                    <div class="sm:col-span-3">
                                        <label
                                            :for="`spek-unit-${index}`"
                                            class="block text-xs font-medium text-content-secondary"
                                        >
                                            Satuan <span class="text-negative">*</span>
                                        </label>
                                        <input
                                            :id="`spek-unit-${index}`"
                                            v-model="spek.unit"
                                            type="text"
                                            placeholder="cm / meter"
                                            class="mt-1 h-9 w-full rounded-md border border-stroke bg-surface px-3 text-xs text-content focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                                            required
                                        />
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <label
                                        :for="`spek-how-${index}`"
                                        class="block text-xs font-medium text-content-secondary"
                                    >
                                        Petunjuk Cara Ukur bagi Warga di Lapangan
                                    </label>
                                    <input
                                        :id="`spek-how-${index}`"
                                        v-model="spek.how_to"
                                        type="text"
                                        placeholder="Contoh: Ukur ketebalan cor di batas bekisting tepi jalan pada minimal 3 titik berbeda."
                                        class="mt-1 h-9 w-full rounded-md border border-stroke bg-surface px-3 text-xs text-content placeholder:text-content-tertiary focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                                    />
                                </div>
                            </div>
                        </div>
                    </Card>

                    <!-- KARTU 5: Publikasikan Proyek & Actions -->
                    <Card padding="app">
                        <div class="flex items-start gap-4">
                            <div
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary-subtle text-sm font-bold text-primary"
                                aria-hidden="true"
                            >
                                5
                            </div>
                            <div class="flex-1">
                                <h2 class="text-base font-bold text-content">Publikasikan Proyek</h2>
                                <p class="text-xs text-content-tertiary">
                                    Pilih apakah proyek langsung diterbitkan melalui gerbang PublishProject atau
                                    disimpan sebagai draf audit.
                                </p>
                            </div>
                        </div>

                        <div class="mt-6 space-y-6">
                            <label class="flex cursor-pointer items-start gap-3">
                                <input
                                    v-model="form.is_published"
                                    type="checkbox"
                                    class="mt-0.5 h-4 w-4 rounded border-stroke-strong text-primary focus:ring-primary"
                                />
                                <span class="text-xs font-medium text-content">
                                    Terbitkan langsung ke publik jika seluruh validasi terpenuhi (0 baris kuning, berkas
                                    fisik terlampir, GPS aktif).
                                </span>
                            </label>

                            <div class="flex flex-wrap items-center gap-3 border-t border-stroke pt-4">
                                <Button
                                    type="button"
                                    variant="secondary"
                                    :loading="form.processing && !form.is_published"
                                    @click="submit(false)"
                                >
                                    Simpan Draf
                                </Button>
                                <Button
                                    type="button"
                                    variant="primary"
                                    :loading="form.processing && form.is_published"
                                    @click="submit(true)"
                                >
                                    <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M5 13l4 4L19 7"
                                        />
                                    </svg>
                                    <span>Terbitkan Proyek Fisik</span>
                                </Button>
                            </div>
                        </div>
                    </Card>
                </div>

                <!-- Right Column: Live Preview (4 cols) -->
                <div class="space-y-6 lg:col-span-4">
                    <!-- Live Preview Card (Pratinjau Kartu Warga) -->
                    <Card padding="app" surface="subtle">
                        <h3 class="text-xs font-bold tracking-wide text-content uppercase">Pratinjau Tampilan Warga</h3>
                        <p class="mt-0.5 text-xs text-content-tertiary">
                            Simulasi bagaimana proyek ini terlihat bagi warga desa.
                        </p>

                        <div class="mt-4 rounded-lg border border-stroke bg-surface p-4 shadow-sm">
                            <div class="flex items-start justify-between gap-2">
                                <h4 class="text-sm font-bold text-content">
                                    {{ form.name.trim() || 'Nama Proyek Pembangunan Fisik' }}
                                </h4>
                                <Badge :color="form.is_published ? 'positive' : 'neutral'">
                                    {{ form.is_published ? 'Terbit' : 'Draf' }}
                                </Badge>
                            </div>

                            <p class="mt-1 text-xs text-content-tertiary">
                                {{ selectedTypeLabel }} &middot; {{ form.fund_source }} {{ form.year }}
                            </p>

                            <div class="mt-3 flex items-baseline justify-between border-t border-stroke pt-3">
                                <span class="text-xs text-content-secondary">Total Pagu (Item RAB)</span>
                                <span class="text-sm font-bold text-primary tabular-nums">
                                    {{ formatRupiah(calculatedTotalRp) }}
                                </span>
                            </div>

                            <!-- Items count preview -->
                            <div class="mt-2 text-[11px] text-content-secondary">
                                {{ form.items.length }} baris item anggaran tercatat
                            </div>

                            <!-- Spek List Preview -->
                            <div
                                v-if="form.measurements.length > 0"
                                class="mt-3 rounded-md bg-surface-subtle p-2.5 text-xs"
                            >
                                <span class="block font-semibold text-content-secondary">Parameter Ukuran Fisik:</span>
                                <ul class="mt-1 space-y-1 text-content">
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

                            <div class="mt-3 flex items-center justify-between text-[11px] text-content-tertiary">
                                <span>{{ village.name }}, {{ village.regency }}</span>
                                <span v-if="form.lat && form.lng" class="text-positive">Geofence Aktif &check;</span>
                                <span v-else class="text-content-tertiary">Tanpa Geofence</span>
                            </div>
                        </div>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
