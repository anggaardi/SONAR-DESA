<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import checkSpecSvg from '@/components/ui/illustrations/check-spec.svg';
import { formatLength, formatRupiah, formatRupiahShort } from '@/lib/format';
import { type BudgetGroupKey, fundingLabel, groupBudget } from '@/lib/projectStory';

interface BudgetItem {
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
}

interface DisplayBudgetItem {
    id: number;
    name: string;
    volume?: string | null;
    unit?: string | null;
    unit_price_rp?: number | null;
    amount_rp?: number | null;
    standard_price_rp?: number | null;
    over_pct?: number | null;
    label?: string;
    standard_source?: string | null;
    explanation?: string | null;
}

interface MeasurementItem {
    name: string;
    value: string;
    unit: string;
    how_to?: string;
    origin?: 'template' | 'custom' | string;
}

interface Props {
    projectName: string;
    projectType: string;
    projectTypeLabel: string;
    fundSource: string;
    year: number;
    writtenTotalRp: number;
    totalItems?: number;
    lat: string;
    lng: string;
    description?: string;
    measurements: MeasurementItem[];
    village: {
        id: number;
        name: string;
        slug: string;
        regency: string;
    };
    items?: BudgetItem[];
    summary?: {
        total_items?: number;
        above_standard_count?: number;
        within_standard_count?: number;
        no_reference_count?: number;
        total_calculated_rp?: number;
    };
    currentStep: 1 | 2 | 3 | 4;
}

const props = defineProps<Props>();

const viewMode = ref<'project' | 'check_spec'>('project');
const scrollContainer = ref<HTMLElement | null>(null);
const toastMessage = ref<string | null>(null);
const activeFilter = ref<'all' | 'above' | 'within' | 'no_ref'>('all');

let toastTimer: ReturnType<typeof setTimeout> | null = null;
function showToast(msg: string): void {
    toastMessage.value = msg;
    if (toastTimer) clearTimeout(toastTimer);
    toastTimer = setTimeout(() => {
        toastMessage.value = null;
    }, 2200);
}

const villageDisplayName = computed(() => {
    const name = props.village.name;
    return name.startsWith('Desa ') ? name : `Desa ${name}`;
});

// Fallback sample items if items not passed or empty
const sampleItems: DisplayBudgetItem[] = [
    {
        id: 1,
        name: 'Pembersihan semak & galian',
        volume: '1',
        unit: 'ls',
        unit_price_rp: 500000,
        amount_rp: 500000,
        label: 'no_reference',
    },
    {
        id: 2,
        name: 'Galian tanah saluran tanah biasa',
        volume: '65',
        unit: 'm3',
        unit_price_rp: 65000,
        amount_rp: 4225000,
        label: 'no_reference',
    },
    {
        id: 3,
        name: 'Urugan tanah kembali dipadatkan',
        volume: '40',
        unit: 'm3',
        unit_price_rp: 83000,
        amount_rp: 3320000,
        label: 'no_reference',
    },
    {
        id: 4,
        name: 'Semen Portland 40kg',
        volume: '448',
        unit: 'sak',
        unit_price_rp: 80000,
        standard_price_rp: 72000,
        over_pct: 11,
        amount_rp: 35840000,
        label: 'above_standard',
    },
    {
        id: 5,
        name: 'Pasir pasang / beton',
        volume: '32',
        unit: 'm3',
        unit_price_rp: 275000,
        amount_rp: 8800000,
        label: 'no_reference',
    },
    {
        id: 6,
        name: 'Batu kali belah 15/20 cm',
        volume: '56',
        unit: 'm3',
        unit_price_rp: 258000,
        amount_rp: 14448000,
        label: 'no_reference',
    },
    {
        id: 7,
        name: 'Pekerja biasa',
        volume: '96',
        unit: 'oh',
        unit_price_rp: 100000,
        standard_price_rp: 100000,
        over_pct: 0,
        amount_rp: 9600000,
        label: 'within_standard',
    },
    {
        id: 8,
        name: 'Tukang batu terlatih',
        volume: '36',
        unit: 'oh',
        unit_price_rp: 130000,
        standard_price_rp: 130000,
        over_pct: 0,
        amount_rp: 4680000,
        label: 'within_standard',
    },
    {
        id: 9,
        name: 'Sewa molen beton',
        volume: '14',
        unit: 'hari',
        unit_price_rp: 250000,
        amount_rp: 3500000,
        label: 'no_reference',
    },
];

const displayItems = computed<DisplayBudgetItem[]>(() => {
    if (props.items && props.items.length > 0) {
        return props.items.map((i) => ({
            id: i.id,
            name: (i.name || i.raw_name || 'Item Anggaran').trim(),
            volume: i.volume ?? null,
            unit: i.unit ?? null,
            unit_price_rp: i.unit_price_rp ?? null,
            amount_rp: i.amount_rp ?? 0,
            standard_price_rp: i.standard_price_rp ?? null,
            over_pct: i.over_pct ?? null,
            label: i.label ?? 'no_reference',
            standard_source: i.standard_source ?? null,
            explanation: i.explanation ?? null,
        }));
    }
    return sampleItems;
});

const filteredItems = computed(() => {
    if (activeFilter.value === 'all') return displayItems.value;
    if (activeFilter.value === 'above')
        return displayItems.value.filter((i) => i.label === 'above_standard' || (i.over_pct && i.over_pct > 0));
    if (activeFilter.value === 'within') return displayItems.value.filter((i) => i.label === 'within_standard');
    if (activeFilter.value === 'no_ref')
        return displayItems.value.filter((i) => i.label === 'no_reference' || !i.label);
    return displayItems.value;
});

const aboveCount = computed(() => {
    if (props.summary?.above_standard_count !== undefined) return props.summary.above_standard_count;
    return displayItems.value.filter((i) => i.label === 'above_standard' || (i.over_pct && i.over_pct > 0)).length;
});

const withinCount = computed(() => {
    if (props.summary?.within_standard_count !== undefined) return props.summary.within_standard_count;
    return displayItems.value.filter((i) => i.label === 'within_standard').length;
});

const noRefCount = computed(() => {
    if (props.summary?.no_reference_count !== undefined) return props.summary.no_reference_count;
    return displayItems.value.filter((i) => i.label === 'no_reference' || !i.label).length;
});

// Fallback measurements if none passed or empty
const effectiveMeasurements = computed<MeasurementItem[]>(() => {
    if (props.measurements && props.measurements.length > 0) {
        return props.measurements;
    }
    return [
        {
            name: 'Ketebalan Beton Cor',
            value: '12.0',
            unit: 'cm',
            how_to: 'Ukur ketebalan cor di batas bekisting tepi jalan.',
        },
        {
            name: 'Lebar Badan Jalan',
            value: '2.5',
            unit: 'meter',
            how_to: 'Ukur bentang permukaan jalan beton dari tepi kiri ke tepi kanan.',
        },
        {
            name: 'Panjang Total Pekerjaan',
            value: '450.0',
            unit: 'meter',
            how_to: 'Gunakan rol meteran atau odometrik dari patok awal 0+000 sampai patok akhir.',
        },
    ];
});

// Budget groups for color bar and breakdown
const budgetGroups = computed(() => {
    const formatted = displayItems.value.map((i) => ({
        name: i.name,
        unit: i.unit ?? '',
        amount_rp: i.amount_rp ?? 0,
    }));
    return groupBudget(formatted);
});

const swatch: Record<BudgetGroupKey, string> = {
    material: 'bg-primary',
    labor: 'bg-info-border',
    equipment: 'bg-content-tertiary',
};

const METERS_PER_UNIT: Record<string, number> = {
    m: 1,
    meter: 1,
    metre: 1,
    cm: 0.01,
    km: 1000,
};

function findItem(keyword: string): MeasurementItem | undefined {
    return effectiveMeasurements.value.find((m) => (m.name ?? '').toLowerCase().includes(keyword.toLowerCase()));
}

function itemInMeters(item: MeasurementItem | undefined): number | null {
    if (!item || !item.unit) return null;
    const factor = METERS_PER_UNIT[(item.unit || '').trim().toLowerCase()];
    const value = Number(item.value);
    if (factor === undefined || Number.isNaN(value)) {
        return null;
    }
    return value * factor;
}

function displayMeasurement(m: MeasurementItem): string {
    const meters = itemInMeters(m);
    return meters !== null ? formatLength(meters) : `${m.value ?? ''} ${m.unit ?? ''}`.trim();
}

const diagram = computed(() => {
    const length = findItem('panjang');
    const width = findItem('lebar');
    if (!length || !width) {
        return {
            length: '450.0 m',
            width: '2.5 m',
            thickness: '12.0 cm',
        };
    }
    const thickness = findItem('tebal') || findItem('tinggi') || findItem('kedalaman');
    return {
        length: displayMeasurement(length),
        width: displayMeasurement(width),
        thickness: thickness ? displayMeasurement(thickness) : null,
    };
});

const effectiveWrittenTotalRp = computed(() => {
    if (props.writtenTotalRp && props.writtenTotalRp > 0) return props.writtenTotalRp;
    if (props.summary?.total_calculated_rp && props.summary.total_calculated_rp > 0)
        return props.summary.total_calculated_rp;
    return 105000000;
});

const perMeter = computed(() => {
    const length = itemInMeters(findItem('panjang'));
    if (!length || length < 1 || effectiveWrittenTotalRp.value <= 0) {
        return null;
    }
    return formatRupiahShort(Math.round(effectiveWrittenTotalRp.value / length)).replace('juta', 'jt');
});

const storyText = computed(() => {
    const subject = props.projectType === 'rabat_beton' ? 'Jalan rabat beton' : props.projectTypeLabel;
    const length = itemInMeters(findItem('panjang'));
    const lengthText = length ? ` sepanjang ${new Intl.NumberFormat('id-ID').format(length)} meter` : '';
    const funding = fundingLabel(props.fundSource, props.year);
    return `${subject}${lengthText}, dibiayai ${funding}. Pekerjaan belum dimulai.`;
});

const effectiveStoryText = computed(() => {
    if (props.description && props.description.trim() !== '') {
        return props.description.trim();
    }
    return storyText.value;
});

function scrollToSection(sectionId: string): void {
    if (!scrollContainer.value) return;
    const target = scrollContainer.value.querySelector(`#${sectionId}`);
    if (target) {
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

watch(
    () => props.currentStep,
    (step) => {
        if (step === 1) {
            viewMode.value = 'project';
            nextTick(() => {
                if (scrollContainer.value) {
                    scrollContainer.value.scrollTo({ top: 0, behavior: 'smooth' });
                }
            });
        } else if (step === 2) {
            viewMode.value = 'project';
            nextTick(() => {
                scrollToSection('phone-lokasi');
            });
        } else if (step === 3) {
            viewMode.value = 'project';
            nextTick(() => {
                scrollToSection('phone-ukuran');
            });
        } else if (step === 4) {
            viewMode.value = 'project';
            nextTick(() => {
                if (scrollContainer.value) {
                    scrollContainer.value.scrollTo({ top: 0, behavior: 'smooth' });
                }
            });
        }
    },
);
</script>

<template>
    <div class="flex flex-col items-center">
        <!-- Preview Header with Mode Controls -->
        <div class="mb-3 flex w-full max-w-[340px] flex-wrap items-center justify-between gap-2 px-1">
            <div>
                <h4 class="flex items-center gap-1.5 text-xs font-semibold text-content">
                    <span>Pratinjau Warga</span>
                    <span
                        class="rounded-full border border-stroke bg-surface px-2 py-0.5 text-xs font-medium text-content-secondary"
                    >
                        Simulasi
                    </span>
                </h4>
                <p class="text-xs text-content-tertiary">Tampilan halaman ponsel warga</p>
            </div>

            <!-- View Switcher -->
            <div class="inline-flex rounded-md border border-stroke bg-surface p-0.5">
                <button
                    type="button"
                    :class="[
                        'rounded-sm px-2.5 py-1 text-xs font-medium transition-colors',
                        viewMode === 'project'
                            ? 'bg-primary text-content-inverse'
                            : 'text-content-secondary hover:text-content',
                    ]"
                    @click="viewMode = 'project'"
                >
                    Proyek
                </button>
                <button
                    type="button"
                    :class="[
                        'rounded-sm px-2.5 py-1 text-xs font-medium transition-colors',
                        viewMode === 'check_spec'
                            ? 'bg-primary text-content-inverse'
                            : 'text-content-secondary hover:text-content',
                    ]"
                    @click="viewMode = 'check_spec'"
                >
                    Cek Spek
                </button>
            </div>
        </div>

        <!--
            Phone mockup. The large radius draws a phone body, not a UI card, so the
            DESIGN.md radius scale does not apply here. Colours stay on semantic tokens, no shadow.
        -->
        <div class="relative w-full max-w-[340px] rounded-[2.5rem] bg-content p-2.5">
            <!-- Side buttons -->
            <div class="absolute top-20 -left-1 h-7 w-1 rounded-l-sm bg-content-secondary"></div>
            <div class="absolute top-32 -left-1 h-11 w-1 rounded-l-sm bg-content-secondary"></div>
            <div class="absolute top-24 -right-1 h-12 w-1 rounded-r-sm bg-content-secondary"></div>

            <!-- Screen Viewport -->
            <div class="relative flex h-[510px] flex-col overflow-hidden rounded-[2rem] bg-surface-subtle text-content">
                <!-- Notch -->
                <div class="absolute top-1.5 left-1/2 z-40 h-5 w-24 -translate-x-1/2 rounded-full bg-content"></div>

                <!-- Status Bar -->
                <div
                    class="relative z-30 flex h-8 items-center justify-between bg-surface px-6 pt-1 text-xs font-semibold text-content select-none"
                >
                    <span class="tabular-nums">09:41</span>
                    <div class="flex items-center gap-1.5">
                        <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                            <path
                                d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zm6-4a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zm6-3a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z"
                            />
                        </svg>
                        <div class="flex h-3 w-5 items-center rounded-sm border border-content p-px">
                            <div class="h-full w-3/4 rounded-[1px] bg-content"></div>
                        </div>
                    </div>
                </div>

                <!-- Toast Notification inside phone -->
                <div
                    v-if="toastMessage"
                    class="absolute top-9 right-4 left-4 z-50 rounded-md bg-content px-3 py-1.5 text-center text-xs font-medium text-content-inverse shadow-sm transition-colors"
                >
                    {{ toastMessage }}
                </div>

                <!-- Scrollable Phone Content -->
                <div
                    ref="scrollContainer"
                    class="no-scrollbar flex-1 overflow-y-auto overscroll-contain scroll-smooth text-xs"
                >
                    <!-- ============================================== -->
                    <!-- VIEW 1: FULL CITIZEN VIEW                      -->
                    <!-- ============================================== -->
                    <div v-if="viewMode === 'project'" class="pb-12">
                        <!-- Sticky Mobile Header -->
                        <div
                            class="sticky top-0 z-20 flex items-center justify-between border-b border-stroke bg-surface/95 px-3 py-2 backdrop-blur-xs"
                        >
                            <div class="flex items-center gap-1.5 text-content">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M15 19l-7-7 7-7"
                                    />
                                </svg>
                                <span class="text-xs font-semibold">Rincian proyek</span>
                            </div>

                            <button
                                type="button"
                                class="rounded-full p-1 text-content-secondary transition-colors hover:bg-surface-muted"
                                title="Bagikan"
                                @click="showToast('Tautan proyek disalin!')"
                            >
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"
                                    />
                                </svg>
                            </button>
                        </div>

                        <!-- 1. Project Hero -->
                        <section class="border-b border-primary/20 bg-primary-subtle px-3.5 pt-3 pb-3.5">
                            <p class="flex items-center gap-1 text-xs font-medium text-content-secondary">
                                <svg class="h-3.5 w-3.5 shrink-0 text-primary" fill="currentColor" viewBox="0 0 20 20">
                                    <path
                                        fill-rule="evenodd"
                                        d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                                <span>{{ villageDisplayName }} &middot; Kab. {{ village.regency }}</span>
                            </p>

                            <h1 class="mt-1 text-sm leading-snug font-bold text-content">
                                {{ (projectName || '').trim() || 'Nama Proyek Pembangunan Fisik' }}
                            </h1>

                            <p id="phone-hero-desc" class="mt-1 text-xs leading-relaxed text-content-secondary">
                                {{ effectiveStoryText }}
                            </p>

                            <div class="mt-2.5 flex flex-wrap items-center gap-1.5">
                                <span
                                    class="inline-flex items-center rounded-full border border-stroke bg-surface px-2.5 py-0.5 text-xs font-medium text-content"
                                >
                                    {{ projectTypeLabel }}
                                </span>
                                <span
                                    class="inline-flex items-center rounded-full border border-info-border bg-surface px-2.5 py-0.5 text-xs font-medium text-primary"
                                >
                                    {{ fundSource }} {{ year }}
                                </span>
                                <span
                                    class="inline-flex items-center rounded-full border border-positive-border bg-positive-subtle px-2.5 py-0.5 text-xs font-semibold text-positive"
                                >
                                    &check; Terbit resmi
                                </span>
                            </div>
                        </section>

                        <!-- 2. Progres Dilaporkan Card -->
                        <div class="p-3 pb-0">
                            <div class="rounded-lg border border-stroke bg-surface p-3">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <p class="text-xs font-medium text-content-secondary">Progres dilaporkan</p>
                                        <div class="mt-0.5 flex items-baseline gap-1.5">
                                            <span class="text-xl font-bold text-content tabular-nums">0%</span>
                                            <span class="text-xs text-content-tertiary">Pekerjaan belum dimulai</span>
                                        </div>
                                    </div>
                                    <span
                                        class="rounded-full bg-surface-muted px-2 py-0.5 text-xs font-medium text-content-secondary"
                                    >
                                        Siap pelaksanaan
                                    </span>
                                </div>
                                <!-- Progress Track Bar -->
                                <div class="mt-3">
                                    <div class="h-2 w-full overflow-hidden rounded-full bg-surface-muted">
                                        <div class="h-full bg-primary" style="width: 4%"></div>
                                    </div>
                                    <div class="mt-1 flex justify-between text-xs text-content-tertiary tabular-nums">
                                        <span>0%</span>
                                        <span>Target 100%</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 3. Section Navigation Pills -->
                        <div
                            class="sticky top-9 z-10 my-2.5 no-scrollbar flex gap-1.5 overflow-x-auto border-y border-stroke bg-surface px-3 py-1.5"
                        >
                            <button
                                type="button"
                                class="shrink-0 rounded-full border border-stroke bg-surface-subtle px-2.5 py-1 text-xs font-medium text-content hover:bg-surface-muted"
                                @click="scrollToSection('phone-anggaran')"
                            >
                                Anggaran
                            </button>
                            <button
                                type="button"
                                class="shrink-0 rounded-full border border-stroke bg-surface-subtle px-2.5 py-1 text-xs font-medium text-content hover:bg-surface-muted"
                                @click="scrollToSection('phone-harga')"
                            >
                                Harga
                            </button>
                            <button
                                type="button"
                                class="shrink-0 rounded-full border border-stroke bg-surface-subtle px-2.5 py-1 text-xs font-medium text-content hover:bg-surface-muted"
                                @click="scrollToSection('phone-ukuran')"
                            >
                                Ukuran
                            </button>
                            <button
                                type="button"
                                class="shrink-0 rounded-full border border-stroke bg-surface-subtle px-2.5 py-1 text-xs font-medium text-content hover:bg-surface-muted"
                                @click="scrollToSection('phone-lokasi')"
                            >
                                Lokasi
                            </button>
                            <button
                                type="button"
                                class="shrink-0 rounded-full border border-stroke bg-surface-subtle px-2.5 py-1 text-xs font-medium text-content hover:bg-surface-muted"
                                @click="scrollToSection('phone-perjalanan')"
                            >
                                Perjalanan
                            </button>
                        </div>

                        <div class="space-y-4 px-3">
                            <!-- 4. Uangnya untuk apa saja? -->
                            <section id="phone-anggaran" class="scroll-mt-20">
                                <h3 class="text-sm font-semibold text-content">Uangnya untuk apa saja?</h3>
                                <p class="text-xs text-content-secondary">
                                    {{ displayItems.length }} baris di RAB, dikelompokkan otomatis
                                </p>

                                <div class="mt-2 rounded-lg border border-stroke bg-surface p-3">
                                    <p class="text-xl font-bold text-content tabular-nums">
                                        {{ formatRupiah(effectiveWrittenTotalRp) }}
                                    </p>
                                    <p class="text-xs text-content-secondary">Total anggaran (RAB)</p>

                                    <p
                                        v-if="perMeter"
                                        class="mt-2 inline-flex items-center rounded-full border border-info-border bg-info-subtle px-2.5 py-0.5 text-xs font-medium text-info tabular-nums"
                                    >
                                        &plusmn; {{ perMeter }} per meter
                                    </p>

                                    <!-- Three-color budget allocation bar -->
                                    <div
                                        v-if="budgetGroups.length > 1"
                                        class="mt-3 flex h-2.5 gap-0.5 overflow-hidden rounded-full bg-surface-muted"
                                        aria-hidden="true"
                                    >
                                        <span
                                            v-for="group in budgetGroups"
                                            :key="group.key"
                                            :class="swatch[group.key]"
                                            :style="{ width: `${group.percent}%` }"
                                        />
                                    </div>

                                    <!-- Group breakdown list -->
                                    <ul v-if="budgetGroups.length > 1" class="mt-3 flex flex-col gap-2">
                                        <li
                                            v-for="group in budgetGroups"
                                            :key="group.key"
                                            class="flex items-start gap-2"
                                        >
                                            <span
                                                class="mt-1 h-2 w-2 shrink-0 rounded-xs"
                                                :class="swatch[group.key]"
                                                aria-hidden="true"
                                            />
                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-baseline justify-between text-xs">
                                                    <span class="font-semibold text-content">
                                                        {{ group.label }}
                                                        <span class="font-normal text-content-tertiary tabular-nums">
                                                            ({{ group.percent }}%)
                                                        </span>
                                                    </span>
                                                    <span class="font-semibold text-content tabular-nums">
                                                        {{ formatRupiahShort(group.amountRp) }}
                                                    </span>
                                                </div>
                                                <p class="truncate text-xs text-content-tertiary">
                                                    {{ group.itemNames.slice(0, 3).join(', ') }}
                                                </p>
                                            </div>
                                        </li>
                                    </ul>
                                </div>
                            </section>

                            <!-- 5. Dibandingkan harga standar -->
                            <section id="phone-harga" class="scroll-mt-20">
                                <h3 class="text-sm font-semibold text-content">Dibandingkan harga standar</h3>
                                <p class="text-xs text-content-secondary">
                                    Acuan Standar Harga Kab. {{ village.regency }} {{ year }}
                                </p>

                                <!-- 3 Stat Summary Tiles -->
                                <div class="mt-2 grid grid-cols-3 gap-1.5 text-center">
                                    <button
                                        type="button"
                                        :class="[
                                            'rounded-lg border p-2 transition-colors',
                                            activeFilter === 'above'
                                                ? 'border-negative bg-negative-subtle'
                                                : 'border-negative-border bg-negative-subtle',
                                        ]"
                                        @click="activeFilter = activeFilter === 'above' ? 'all' : 'above'"
                                    >
                                        <div class="flex items-center justify-center gap-1 text-negative">
                                            <span class="text-base font-bold tabular-nums">{{ aboveCount }}</span>
                                            <svg
                                                class="h-3.5 w-3.5"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke="currentColor"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2.5"
                                                    d="M12 19V5m-6 6 6-6 6 6"
                                                />
                                            </svg>
                                        </div>
                                        <p class="mt-0.5 text-xs leading-tight font-medium text-negative">
                                            Di atas standar
                                        </p>
                                    </button>

                                    <button
                                        type="button"
                                        :class="[
                                            'rounded-lg border p-2 transition-colors',
                                            activeFilter === 'within'
                                                ? 'border-positive bg-positive-subtle'
                                                : 'border-positive-border bg-positive-subtle',
                                        ]"
                                        @click="activeFilter = activeFilter === 'within' ? 'all' : 'within'"
                                    >
                                        <div class="flex items-center justify-center gap-1 text-positive">
                                            <span class="text-base font-bold tabular-nums">{{ withinCount }}</span>
                                            <svg
                                                class="h-3.5 w-3.5"
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
                                        </div>
                                        <p class="mt-0.5 text-xs leading-tight font-medium text-positive">Sesuai</p>
                                    </button>

                                    <button
                                        type="button"
                                        :class="[
                                            'rounded-lg border p-2 transition-colors',
                                            activeFilter === 'no_ref'
                                                ? 'border-stroke-strong bg-surface-muted'
                                                : 'border-stroke bg-surface-muted',
                                        ]"
                                        @click="activeFilter = activeFilter === 'no_ref' ? 'all' : 'no_ref'"
                                    >
                                        <div class="flex items-center justify-center gap-1 text-content-secondary">
                                            <span class="text-base font-bold tabular-nums">{{ noRefCount }}</span>
                                            <span class="text-xs">&minus;</span>
                                        </div>
                                        <p class="mt-0.5 text-xs leading-tight font-medium text-content-secondary">
                                            Tanpa acuan
                                        </p>
                                    </button>
                                </div>

                                <p class="mt-1.5 text-xs leading-relaxed text-content-tertiary">
                                    <span v-if="aboveCount > 0">
                                        {{ aboveCount }} item di atas standar acuan; BPD dapat meminta penjelasan desa.
                                    </span>
                                    <span v-else> Semua item berada dalam batas atau di bawah standar acuan. </span>
                                </p>

                                <!-- RAB Items Cards List -->
                                <div class="mt-2.5 space-y-1.5">
                                    <div
                                        v-for="item in filteredItems"
                                        :key="item.id"
                                        class="rounded-lg border border-stroke bg-surface p-2.5 transition-colors hover:border-primary/40"
                                    >
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="min-w-0 flex-1">
                                                <h4 class="truncate text-xs font-semibold text-content">
                                                    {{ item.name }}
                                                </h4>
                                                <p class="text-xs text-content-tertiary tabular-nums">
                                                    {{ item.volume || '1' }} {{ item.unit || 'unit' }}
                                                    <template v-if="item.unit_price_rp">
                                                        &middot; @{{ formatRupiahShort(item.unit_price_rp) }}
                                                    </template>
                                                </p>
                                            </div>
                                            <span class="shrink-0 text-xs font-semibold text-content tabular-nums">
                                                {{ formatRupiah(item.amount_rp ?? 0) }}
                                            </span>
                                        </div>

                                        <!-- Badge comparison tag -->
                                        <div
                                            class="mt-2 flex items-center justify-between border-t border-stroke pt-1.5"
                                        >
                                            <span
                                                v-if="
                                                    item.label === 'above_standard' ||
                                                    (item.over_pct && item.over_pct > 0)
                                                "
                                                class="inline-flex items-center gap-1 rounded-full border border-negative-border bg-negative-subtle px-2 py-0.5 text-xs font-medium text-negative"
                                            >
                                                <svg
                                                    class="h-3 w-3"
                                                    fill="none"
                                                    viewBox="0 0 24 24"
                                                    stroke="currentColor"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        stroke-width="2.5"
                                                        d="M12 19V5m-6 6 6-6 6 6"
                                                    />
                                                </svg>
                                                Di atas standar (+{{ item.over_pct || 10 }}%)
                                            </span>
                                            <span
                                                v-else-if="item.label === 'within_standard'"
                                                class="inline-flex items-center gap-1 rounded-full border border-positive-border bg-positive-subtle px-2 py-0.5 text-xs font-medium text-positive"
                                            >
                                                &check; Sesuai
                                            </span>
                                            <span
                                                v-else
                                                class="inline-flex items-center rounded-full border border-stroke bg-surface-muted px-2 py-0.5 text-xs font-medium text-content-secondary"
                                            >
                                                Tanpa acuan
                                            </span>

                                            <span class="text-xs text-content-tertiary">RAB resmi &rsaquo;</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Official PDF Document Button -->
                                <div class="mt-3 rounded-lg border border-stroke bg-surface p-3 text-center">
                                    <button
                                        type="button"
                                        class="inline-flex h-9 w-full items-center justify-center gap-2 rounded-md border border-stroke bg-surface-subtle text-xs font-medium text-content hover:bg-surface-muted"
                                        @click="showToast('Dokumen RAB resmi tersedia setelah terbit!')"
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
                                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                                            />
                                        </svg>
                                        <span>Unduh dokumen asli</span>
                                    </button>
                                    <p class="mt-1.5 text-xs text-content-tertiary">
                                        Total {{ displayItems.length }} baris item APBDes resmi tercatat
                                    </p>
                                </div>
                            </section>

                            <!-- 6. Ukuran di RAB -->
                            <section id="phone-ukuran" class="scroll-mt-20">
                                <div>
                                    <h3 class="text-sm font-semibold text-content">Ukuran di RAB</h3>
                                    <p class="text-xs text-content-secondary">Ini yang bisa kamu cocokkan di lokasi</p>
                                </div>

                                <div class="mt-2 rounded-lg border border-stroke bg-surface p-3">
                                    <!-- 3D Technical Slab Diagram -->
                                    <div v-if="diagram" class="rounded-md bg-surface-subtle p-2">
                                        <svg
                                            class="mx-auto block w-full max-w-64"
                                            viewBox="0 0 320 128"
                                            aria-hidden="true"
                                        >
                                            <path d="M62 70H246L292 36H108Z" class="fill-content-tertiary" />
                                            <path d="M62 70H246V86H62Z" class="fill-content-secondary" />
                                            <path d="M246 70L292 36V52L246 86Z" class="fill-content" />
                                            <path
                                                d="M95 53H259"
                                                class="stroke-surface"
                                                stroke-width="1.6"
                                                stroke-dasharray="9 7"
                                            />
                                            <g
                                                class="stroke-primary"
                                                stroke-width="1.5"
                                                stroke-linecap="round"
                                                fill="none"
                                            >
                                                <path d="M62 102H246M62 97V107M246 97V107" />
                                                <path d="M260 78L306 44" />
                                                <path v-if="diagram.thickness" d="M50 70V86M45 70H55M45 86H55" />
                                            </g>
                                            <rect
                                                x="118"
                                                y="91"
                                                width="72"
                                                height="22"
                                                rx="11"
                                                class="fill-surface stroke-info-border"
                                            />
                                            <text
                                                x="154"
                                                y="106.5"
                                                text-anchor="middle"
                                                class="fill-primary text-xs font-semibold"
                                            >
                                                {{ diagram.length }}
                                            </text>
                                            <text x="286" y="78" class="fill-primary text-xs font-semibold">
                                                {{ diagram.width }}
                                            </text>
                                            <text
                                                v-if="diagram.thickness"
                                                x="40"
                                                y="82.5"
                                                text-anchor="end"
                                                class="fill-primary text-xs font-semibold"
                                            >
                                                {{ diagram.thickness }}
                                            </text>
                                        </svg>
                                    </div>

                                    <!-- Dimension cards grid -->
                                    <div class="mt-2.5 grid grid-cols-2 gap-2">
                                        <div
                                            v-for="(item, idx) in effectiveMeasurements"
                                            :key="idx"
                                            class="rounded-md border border-stroke bg-surface-subtle p-2"
                                        >
                                            <p class="truncate text-xs text-content-secondary">
                                                {{ item.name || 'Ukuran' }}
                                            </p>
                                            <p class="mt-0.5 text-xs font-semibold text-content tabular-nums">
                                                {{ item.value }} {{ item.unit }}
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Cara Mengukur -->
                                    <div
                                        v-if="effectiveMeasurements.some((m) => m.how_to)"
                                        class="mt-3 border-t border-stroke pt-2.5"
                                    >
                                        <p class="text-xs font-semibold text-content">Cara mengukur:</p>
                                        <ul class="mt-1 space-y-1 text-xs text-content-secondary">
                                            <li
                                                v-for="(item, idx) in effectiveMeasurements.filter((m) => m.how_to)"
                                                :key="idx"
                                            >
                                                <span class="font-semibold text-content">{{ item.name }}:</span>
                                                {{ item.how_to }}
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </section>

                            <!-- 7. Lokasi & Radius (Step 2 Focus) -->
                            <section id="phone-lokasi" class="scroll-mt-20">
                                <div class="rounded-lg border border-stroke bg-surface p-3">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-xs font-semibold text-content">
                                            Lokasi proyek (radius 300 m)
                                        </span>
                                        <span
                                            v-if="lat && lng"
                                            class="inline-flex items-center gap-1 rounded-full bg-positive-subtle px-2 py-0.5 text-xs font-medium text-positive"
                                        >
                                            <span class="h-1.5 w-1.5 rounded-full bg-positive"></span>
                                            Aktif
                                        </span>
                                        <span
                                            v-else
                                            class="rounded-full bg-warning-subtle px-2 py-0.5 text-xs font-medium text-warning"
                                        >
                                            Belum diisi
                                        </span>
                                    </div>

                                    <!-- Coordinate Box -->
                                    <div class="mt-2 rounded-md border border-info-border bg-primary-subtle p-2.5">
                                        <div class="flex items-start gap-2.5">
                                            <div
                                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-primary text-content-inverse"
                                            >
                                                <svg
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
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <div class="text-xs font-semibold text-content tabular-nums">
                                                    {{
                                                        lat && lng ? `${lat}, ${lng}` : 'Titik koordinat belum dipilih'
                                                    }}
                                                </div>
                                                <p class="mt-0.5 text-xs leading-tight text-content-secondary">
                                                    {{ villageDisplayName }}, Kab. {{ village.regency }}
                                                </p>
                                            </div>
                                        </div>

                                        <div
                                            class="mt-2 border-t border-info-border pt-2 text-xs text-content-secondary"
                                        >
                                            <div class="flex items-center gap-1 font-semibold text-primary">
                                                <svg
                                                    class="h-3.5 w-3.5 shrink-0"
                                                    fill="none"
                                                    viewBox="0 0 24 24"
                                                    stroke="currentColor"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"
                                                    />
                                                </svg>
                                                <span>Radius lokasi: &le; 300 meter</span>
                                            </div>
                                            <p class="mt-0.5 text-xs text-content-tertiary">
                                                Warga hanya dapat mengirim Cek Spek jika berada di dalam radius ini.
                                            </p>
                                        </div>
                                    </div>

                                    <button
                                        type="button"
                                        class="mt-2.5 flex h-9 w-full items-center justify-center gap-1.5 rounded-md border border-info-border bg-primary-subtle text-xs font-medium text-primary transition-colors hover:bg-surface-muted"
                                        @click="viewMode = 'check_spec'"
                                    >
                                        <span>Buka simulasi Cek Spek &amp; lokasi</span>
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M9 5l7 7-7 7"
                                            />
                                        </svg>
                                    </button>
                                </div>
                            </section>

                            <!-- 8. Lewat lokasi proyek ini? -->
                            <section
                                id="phone-invite"
                                class="rounded-lg border border-info-border bg-primary-subtle p-3 text-center"
                            >
                                <img
                                    :src="checkSpecSvg"
                                    alt=""
                                    class="mx-auto h-24 w-auto object-contain"
                                    loading="lazy"
                                />

                                <h4 class="mt-2 text-xs font-bold text-content">Lewat lokasi proyek ini?</h4>
                                <p class="mt-1 text-xs leading-relaxed text-content-secondary">
                                    Ukur panjang dan lebarnya dengan meteran, ambil foto, lalu bandingkan dengan RAB.
                                    Cukup sekitar 5 menit untuk ikut menjaga dana desa.
                                </p>

                                <button
                                    type="button"
                                    class="mt-2.5 inline-flex h-10 w-full items-center justify-center gap-1.5 rounded-md bg-primary px-3 text-xs font-medium text-content-inverse transition-colors hover:bg-primary-hover"
                                    @click="viewMode = 'check_spec'"
                                >
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M3.5 15.5 15.5 3.5l5 5-12 12zM7.5 11.5l2 2M10.5 8.5l2 2M13.5 5.5l2 2"
                                        />
                                    </svg>
                                    <span>Buka Cek Spek</span>
                                </button>

                                <p class="mt-2 text-xs text-content-tertiary">
                                    Belum ada warga yang cek &middot; Kamu bisa jadi yang pertama
                                </p>
                            </section>

                            <!-- 9. Perjalanan proyek -->
                            <section
                                id="phone-perjalanan"
                                class="scroll-mt-20 rounded-lg border border-stroke bg-surface p-3"
                            >
                                <h3 class="text-sm font-semibold text-content">Perjalanan proyek</h3>
                                <p class="text-xs text-content-secondary">Dari perencanaan desa sampai diawasi warga</p>

                                <div class="mt-2.5 space-y-2 border-l-2 border-primary/30 pl-3">
                                    <div class="relative">
                                        <span
                                            class="absolute top-1.5 -left-[17px] h-2 w-2 rounded-full bg-primary"
                                        ></span>
                                        <p class="text-xs font-semibold text-content">Perencanaan APBDes</p>
                                        <p class="text-xs text-content-tertiary">Disahkan musyawarah desa</p>
                                    </div>
                                    <div class="relative">
                                        <span
                                            class="absolute top-1.5 -left-[17px] h-2 w-2 rounded-full bg-primary"
                                        ></span>
                                        <p class="text-xs font-semibold text-content">Publikasi transparansi</p>
                                        <p class="text-xs text-content-tertiary">Penerbitan resmi di Sonar Desa</p>
                                    </div>
                                    <div class="relative">
                                        <span
                                            class="absolute top-1.5 -left-[17px] h-2 w-2 rounded-full border border-stroke bg-surface-muted"
                                        ></span>
                                        <p class="text-xs font-semibold text-content-secondary">
                                            Pengawasan fisik warga
                                        </p>
                                        <p class="text-xs text-content-tertiary">Menunggu partisipasi warga</p>
                                    </div>
                                </div>
                            </section>

                            <!-- 10. Footer -->
                            <div class="border-t border-stroke pt-3 text-center text-xs text-content-tertiary">
                                <p class="font-semibold text-content-secondary">
                                    Sonar Desa &middot; Sistem Transparansi Publik Desa
                                </p>
                                <p class="mt-0.5">Kementerian Desa &middot; Kemendagri &middot; BPKP</p>
                            </div>
                        </div>

                        <!-- Sticky Mobile Action Button at bottom (muted inside simulation) -->
                        <div
                            class="sticky bottom-0 z-20 mt-4 border-t border-stroke bg-surface/95 p-2 backdrop-blur-xs"
                        >
                            <button
                                type="button"
                                class="flex h-9 w-full items-center justify-center gap-1.5 rounded-md border border-stroke bg-surface-subtle text-xs font-medium text-content-secondary transition-colors hover:bg-surface-muted hover:text-content"
                                @click="showToast('Simulasi: Tautan proyek siap dibagikan!')"
                            >
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"
                                    />
                                </svg>
                                <span>Bagikan rincian proyek</span>
                            </button>
                        </div>
                    </div>

                    <!-- ============================================== -->
                    <!-- VIEW 2: LAYAR CEK SPEK WARGA (LOCATION GATE)    -->
                    <!-- ============================================== -->
                    <div v-else class="p-3">
                        <!-- Top back button -->
                        <button
                            type="button"
                            class="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline"
                            @click="viewMode = 'project'"
                        >
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M15 19l-7-7 7-7"
                                />
                            </svg>
                            <span>Kembali ke Halaman Proyek</span>
                        </button>

                        <div class="mt-2.5">
                            <h2 class="text-sm font-semibold text-content">Cek Spek: Verifikasi lokasi</h2>
                            <p class="text-xs text-content-secondary">
                                Memastikan pengawasan dilakukan langsung di lapangan
                            </p>
                        </div>

                        <!-- Stepper Mock -->
                        <div class="mt-2 grid grid-cols-3 gap-1 rounded-md bg-surface-subtle p-1.5 text-center">
                            <div class="rounded-sm bg-primary py-1 text-xs font-medium text-content-inverse">
                                1. Lokasi
                            </div>
                            <div class="rounded-sm py-1 text-xs font-medium text-content-tertiary">2. Ukur</div>
                            <div class="rounded-sm py-1 text-xs font-medium text-content-tertiary">3. Foto</div>
                        </div>

                        <!-- Location Gate Trust Card (Matches LocationGate.vue) -->
                        <div class="mt-3 rounded-lg border border-stroke bg-surface p-3">
                            <div class="flex items-center gap-2">
                                <div
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-primary-subtle text-primary"
                                >
                                    <svg
                                        class="h-4 w-4"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                        stroke-width="2"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M12 21s-7-6.2-7-11.5a7 7 0 0114 0C19 14.8 12 21 12 21z"
                                        />
                                        <circle cx="12" cy="9.5" r="2.5" />
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="text-xs font-semibold text-content">Verifikasi lokasi GPS</h4>
                                    <p class="text-xs text-content-secondary">Radius lokasi maksimal 300 meter</p>
                                </div>
                            </div>

                            <!-- Live Simulation Status -->
                            <div class="mt-2.5 rounded-md border border-positive-border bg-positive-subtle p-2">
                                <div class="flex items-center gap-1.5">
                                    <span class="h-2 w-2 rounded-full bg-positive-solid"></span>
                                    <span class="text-xs font-semibold text-positive">Posisi dalam radius</span>
                                </div>
                                <p class="mt-1 text-xs text-positive tabular-nums">
                                    {{
                                        lat && lng
                                            ? `Jarak terdeteksi: ~15 meter dari titik proyek (${lat}, ${lng})`
                                            : 'Pilih titik lokasi proyek di langkah 2 untuk mengaktifkan radius lokasi.'
                                    }}
                                </p>
                            </div>

                            <!-- IDDS 3 Principles -->
                            <div
                                class="mt-2.5 space-y-1.5 rounded-md bg-surface-subtle p-2 text-xs text-content-secondary"
                            >
                                <div class="flex items-start gap-1.5">
                                    <span class="font-bold text-primary">1.</span>
                                    <span>GPS hanya dibaca saat memeriksa keberadaan di lokasi</span>
                                </div>
                                <div class="flex items-start gap-1.5">
                                    <span class="font-bold text-primary">2.</span>
                                    <span>Tidak ada perekaman rute perjalanan warga</span>
                                </div>
                                <div class="flex items-start gap-1.5">
                                    <span class="font-bold text-primary">3.</span>
                                    <span>Bukti pengawasan terhubung langsung ke arsip APBDes</span>
                                </div>
                            </div>

                            <!-- Target Parameters Checklist -->
                            <div class="mt-3 border-t border-stroke pt-2.5">
                                <span class="text-xs font-semibold text-content">Parameter yang akan diuji warga:</span>
                                <ul class="mt-1 space-y-1 text-xs">
                                    <li
                                        v-for="(item, idx) in effectiveMeasurements"
                                        :key="idx"
                                        class="flex items-center justify-between text-content-secondary"
                                    >
                                        <span>&bull; {{ item.name || 'Parameter' }}</span>
                                        <span class="font-semibold text-content tabular-nums">
                                            {{ item.value }} {{ item.unit }}
                                        </span>
                                    </li>
                                </ul>
                            </div>

                            <button
                                type="button"
                                class="mt-3 h-9 w-full rounded-md bg-primary text-center text-xs font-medium text-content-inverse transition-colors hover:bg-primary-hover"
                                @click="showToast('Simulasi pengujian selesai!')"
                            >
                                Lanjut uji meteran (simulasi)
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Home indicator -->
                <div class="relative z-30 flex items-center justify-center bg-surface pt-1.5 pb-2 select-none">
                    <div class="h-1 w-24 rounded-full bg-content"></div>
                </div>
            </div>
        </div>
    </div>
</template>
