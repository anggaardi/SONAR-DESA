<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Alert from '@/components/ui/Alert.vue';
import Badge from '@/components/ui/Badge.vue';
import BottomNav from '@/components/ui/BottomNav.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import DesktopSidebar from '@/components/ui/DesktopSidebar.vue';
import Dialog from '@/components/ui/Dialog.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import checkSpecIllustration from '@/components/ui/illustrations/check-spec.svg';
import QrScannerDialog from '@/features/project/QrScannerDialog.vue';
import ReportHistoryCard from '@/features/report/ReportHistoryCard.vue';
import { myReports as text } from '@/lib/labels';
import type { MyReportsPageProps } from '@/types/report';

const props = defineProps<{
    myReports: MyReportsPageProps;
}>();

type FilterKey = 'all' | 'active' | 'bpd';

const activeFilter = ref<FilterKey>('all');
const loggingOut = ref(false);
const showHelpDialog = ref(false);
const showQrDialog = ref(false);

const backHref = computed(() => {
    const villageSlug = props.myReports.session?.village_slug;
    return villageSlug ? `/d/${villageSlug}` : '/';
});

const heroEyebrow = computed(() => {
    const villageName = props.myReports.session?.village_name;
    return villageName ? text.eyebrowVillage(villageName) : text.eyebrow;
});

const activeCount = computed(() => props.myReports.reports.filter((item) => item.status === 'active').length);
const bpdCount = computed(() => props.myReports.reports.filter((item) => item.bpd_review.status !== 'none').length);

const filterCounts = computed<Record<FilterKey, number>>(() => ({
    all: props.myReports.reports.length,
    active: activeCount.value,
    bpd: bpdCount.value,
}));

const filteredReports = computed(() => {
    if (activeFilter.value === 'active') {
        return props.myReports.reports.filter((item) => item.status === 'active');
    }
    if (activeFilter.value === 'bpd') {
        return props.myReports.reports.filter((item) => item.bpd_review.status !== 'none');
    }
    return props.myReports.reports;
});

function handleTabClick(tabId: string): void {
    if (tabId === 'bantuan') {
        showHelpDialog.value = true;
    } else if (tabId === 'pindai-qr' || tabId === 'plang') {
        showQrDialog.value = true;
    }
}

function logout(): void {
    loggingOut.value = true;
    router.post(props.myReports.urls.logout, {}, { onFinish: () => (loggingOut.value = false) });
}
</script>

<template>
    <div class="min-h-screen bg-surface-subtle text-content antialiased">
        <Head :title="text.title" />

        <div class="flex min-h-screen">
            <DesktopSidebar
                active-tab="laporan-saya"
                :village-name="myReports.session?.village_name ?? ''"
                :village-slug="myReports.session?.village_slug ?? ''"
                @tab-click="handleTabClick"
            />

            <div class="min-w-0 flex-1 pb-24 md:pb-12">
                <!-- Mobile sticky header -->
                <header class="sticky top-0 z-20 border-b border-stroke bg-surface/95 backdrop-blur-sm md:hidden">
                    <div class="mx-auto flex max-w-md items-center justify-between px-4 py-3 sm:max-w-xl">
                        <Link
                            :href="backHref"
                            class="inline-flex h-11 w-11 items-center justify-center rounded-full text-content transition-colors hover:bg-surface-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                            aria-label="Kembali"
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
                                    d="M19 12H5M12 19l-7-7 7-7"
                                />
                            </svg>
                        </Link>
                        <span class="text-base font-semibold text-content sm:text-lg">{{ text.title }}</span>
                        <span class="w-11" aria-hidden="true" />
                    </div>
                </header>

                <!-- Desktop breadcrumb header -->
                <header
                    class="sticky top-0 z-20 hidden items-center justify-between border-b border-stroke bg-surface/95 px-6 py-3.5 backdrop-blur-sm md:flex lg:px-8"
                >
                    <div class="flex items-center gap-2 text-sm text-content-secondary">
                        <Link :href="myReports.urls.home" class="transition-colors hover:text-primary">
                            {{ text.backHome }}
                        </Link>
                        <span>/</span>
                        <span class="font-medium text-content">{{ text.title }}</span>
                    </div>

                    <div v-if="myReports.session?.village_slug" class="flex items-center gap-2.5">
                        <Button :href="`/d/${myReports.session.village_slug}`" variant="secondary" size="sm">
                            {{ text.viewVillageProjects }}
                        </Button>
                    </div>
                </header>

                <!-- Hero Band (IDDS bg-primary-subtle header) -->
                <section class="border-b border-stroke bg-primary-subtle px-4 py-5 sm:px-6 md:px-8 lg:px-10 lg:py-8">
                    <div class="mx-auto max-w-7xl">
                        <p class="flex items-center gap-1.5 text-sm font-medium text-content-secondary">
                            <svg
                                class="h-4 w-4 shrink-0 text-primary"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"
                                />
                            </svg>
                            <span>{{ heroEyebrow }}</span>
                        </p>
                        <h1 class="mt-1.5 text-2xl font-bold tracking-tight text-content sm:text-3xl">
                            {{ text.title }}
                        </h1>
                        <p class="mt-1.5 max-w-2xl text-sm leading-relaxed text-content-secondary sm:text-base">
                            {{ text.subtitle }}
                        </p>
                    </div>
                </section>

                <main class="mx-auto max-w-7xl space-y-6 px-4 pt-6 pb-8 sm:px-6 md:px-8 lg:px-10">
                    <!-- State Tamu (belum login & belum demo) -->
                    <Card v-if="myReports.state === 'guest'" class="mx-auto max-w-4xl p-4 sm:p-6">
                        <div class="grid items-center gap-6 md:grid-cols-12">
                            <div class="md:col-span-5" aria-hidden="true">
                                <img
                                    :src="checkSpecIllustration"
                                    alt=""
                                    width="280"
                                    height="210"
                                    class="mx-auto aspect-4/3 w-full max-w-60 object-contain"
                                />
                            </div>
                            <div class="space-y-4 md:col-span-7">
                                <div class="space-y-1.5">
                                    <h2 class="text-xl font-semibold text-content">{{ text.guest.title }}</h2>
                                    <p class="text-sm leading-relaxed text-content-secondary sm:text-base">
                                        {{ text.guest.description }}
                                    </p>
                                </div>

                                <Alert color="info">{{ text.guest.privacy }}</Alert>

                                <!-- Plain link for full OAuth redirect (PRD F11, LS-R1) -->
                                <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                                    <a
                                        :href="myReports.urls.google_login"
                                        class="inline-flex h-11 items-center justify-center gap-2 rounded-md bg-primary px-4 text-base font-medium whitespace-nowrap text-content-inverse transition-colors hover:bg-primary-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                                    >
                                        {{ text.guest.loginGoogle }}
                                    </a>
                                    <Button
                                        :href="myReports.urls.demo"
                                        variant="secondary"
                                        size="md"
                                        class="whitespace-nowrap"
                                    >
                                        {{ text.guest.tryDemo }}
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </Card>

                    <!-- State Warga Terautentikasi (Google asli / Warga Demo) -->
                    <template v-else>
                        <!-- Info sesi warga Google -->
                        <Card v-if="myReports.session?.auth_type === 'google'" class="p-4 sm:p-5">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                <div class="flex min-w-0 items-start gap-3.5">
                                    <span
                                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-info-subtle text-info"
                                        aria-hidden="true"
                                    >
                                        <svg
                                            class="h-5 w-5"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                            stroke-width="2"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.5 20.118a7.5 7.5 0 0115 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.5-1.632z"
                                            />
                                        </svg>
                                    </span>
                                    <div class="min-w-0 space-y-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <Badge color="info">{{ text.session.googleTitle }}</Badge>
                                            <span class="text-sm font-semibold text-content">
                                                {{ myReports.session.display_name }}
                                            </span>
                                        </div>
                                        <p
                                            v-if="myReports.session.email"
                                            class="truncate text-sm font-medium text-content-secondary"
                                        >
                                            {{ myReports.session.email }}
                                        </p>
                                        <p class="text-xs leading-relaxed text-content-secondary">
                                            {{ text.session.googleHint }}
                                        </p>
                                    </div>
                                </div>

                                <Button
                                    variant="secondary"
                                    size="md"
                                    class="w-full shrink-0 sm:w-auto"
                                    :loading="loggingOut"
                                    @click="logout"
                                >
                                    {{ loggingOut ? text.session.loggingOut : text.session.logout }}
                                </Button>
                            </div>
                        </Card>

                        <!-- Empty state (mitigasi juri 30 detik) -->
                        <template v-if="myReports.reports.length === 0">
                            <Card class="p-4 sm:p-6">
                                <EmptyState
                                    illustration="plang-empty"
                                    :title="text.empty.title"
                                    :description="text.empty.description"
                                >
                                    <template v-if="myReports.suggested_project">
                                        <Button
                                            :href="myReports.suggested_project.urls.check"
                                            variant="primary"
                                            size="md"
                                        >
                                            {{ text.empty.demoCta(myReports.suggested_project.name) }}
                                        </Button>
                                        <Button
                                            v-if="myReports.session?.village_slug"
                                            :href="`/d/${myReports.session.village_slug}`"
                                            variant="secondary"
                                            size="md"
                                        >
                                            {{ text.empty.demoVillageCta }}
                                        </Button>
                                    </template>
                                    <Button v-else :href="myReports.urls.home" variant="primary" size="md">
                                        {{ text.empty.exploreCta }}
                                    </Button>
                                </EmptyState>
                            </Card>
                        </template>

                        <!-- Ringkasan & daftar riwayat laporan -->
                        <template v-else>
                            <!-- 3 KPI Stat Cards (pola IDDS KPI & VillageStatSummary) -->
                            <section aria-label="Ringkasan laporan saya">
                                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4">
                                    <Card class="flex items-center justify-between gap-3 p-4 sm:gap-4 sm:p-5">
                                        <div class="min-w-0">
                                            <p class="text-sm font-medium text-content-secondary">
                                                {{ text.summary.total }}
                                            </p>
                                            <p class="mt-1 text-2xl font-bold text-content tabular-nums sm:text-3xl">
                                                {{ myReports.summary.total_count }}
                                            </p>
                                            <p class="mt-1 hidden text-xs text-content-secondary sm:block">
                                                {{ text.summaryHelpers.total }}
                                            </p>
                                        </div>
                                        <span
                                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary-subtle text-primary sm:h-11 sm:w-11"
                                            aria-hidden="true"
                                        >
                                            <svg
                                                class="h-5 w-5 sm:h-6 sm:w-6"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke="currentColor"
                                                stroke-width="2"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"
                                                />
                                            </svg>
                                        </span>
                                    </Card>

                                    <Card class="flex items-center justify-between gap-3 p-4 sm:gap-4 sm:p-5">
                                        <div class="min-w-0">
                                            <p class="text-sm font-medium text-content-secondary">
                                                {{ text.summary.active }}
                                            </p>
                                            <p
                                                class="mt-1 text-2xl font-bold tabular-nums sm:text-3xl"
                                                :class="
                                                    myReports.summary.active_count > 0
                                                        ? 'text-positive'
                                                        : 'text-content'
                                                "
                                            >
                                                {{ myReports.summary.active_count }}
                                            </p>
                                            <p class="mt-1 hidden text-xs text-content-secondary sm:block">
                                                {{ text.summaryHelpers.active }}
                                            </p>
                                        </div>
                                        <span
                                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-positive-subtle text-positive sm:h-11 sm:w-11"
                                            aria-hidden="true"
                                        >
                                            <svg
                                                class="h-5 w-5 sm:h-6 sm:w-6"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke="currentColor"
                                                stroke-width="2"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                                                />
                                            </svg>
                                        </span>
                                    </Card>

                                    <Card
                                        class="col-span-2 flex items-center justify-between gap-3 p-4 sm:col-span-1 sm:gap-4 sm:p-5"
                                    >
                                        <div class="min-w-0">
                                            <p class="text-sm font-medium text-content-secondary">
                                                {{ text.summary.bpd }}
                                            </p>
                                            <p
                                                class="mt-1 text-2xl font-bold tabular-nums sm:text-3xl"
                                                :class="
                                                    myReports.summary.reviewed_or_in_review_count > 0
                                                        ? 'text-info'
                                                        : 'text-content'
                                                "
                                            >
                                                {{ myReports.summary.reviewed_or_in_review_count }}
                                            </p>
                                            <p class="mt-1 hidden text-xs text-content-secondary sm:block">
                                                {{ text.summaryHelpers.bpd }}
                                            </p>
                                        </div>
                                        <span
                                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-info-subtle text-info sm:h-11 sm:w-11"
                                            aria-hidden="true"
                                        >
                                            <svg
                                                class="h-5 w-5 sm:h-6 sm:w-6"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke="currentColor"
                                                stroke-width="2"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    d="M12 3l8 3v6c0 4.5-3.4 8.3-8 9-4.6-.7-8-4.5-8-9V6z"
                                                />
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    d="M8.5 12l2.5 2.5 4.5-5"
                                                />
                                            </svg>
                                        </span>
                                    </Card>
                                </div>
                            </section>

                            <!-- Desktop 2-column workspace: Reports (8 col) + Sticky Guide Rail (4 col) -->
                            <div class="grid grid-cols-1 gap-6 lg:grid-cols-12 lg:items-start">
                                <section class="space-y-4 lg:col-span-8" aria-labelledby="heading-riwayat-laporan">
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                        <h2
                                            id="heading-riwayat-laporan"
                                            class="text-lg font-semibold text-content sm:text-xl"
                                        >
                                            {{ text.listHeading(filteredReports.length) }}
                                        </h2>

                                        <div
                                            v-if="myReports.reports.length > 1"
                                            class="no-scrollbar flex gap-2 overflow-x-auto py-0.5 sm:flex-wrap"
                                            role="group"
                                            aria-label="Filter riwayat laporan"
                                        >
                                            <button
                                                v-for="key in ['all', 'active', 'bpd'] as const"
                                                :key="key"
                                                type="button"
                                                :aria-pressed="activeFilter === key"
                                                :class="[
                                                    'inline-flex min-h-11 shrink-0 items-center rounded-full px-4 text-sm transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary sm:min-h-9',
                                                    activeFilter === key
                                                        ? 'bg-content font-semibold text-surface'
                                                        : 'border border-stroke bg-surface font-medium text-content hover:bg-surface-muted',
                                                ]"
                                                @click="activeFilter = key"
                                            >
                                                {{ text.filters[key] }} ({{ filterCounts[key] }})
                                            </button>
                                        </div>
                                    </div>

                                    <Card v-if="filteredReports.length === 0" class="p-4 sm:p-6">
                                        <EmptyState
                                            illustration="search-empty"
                                            compact
                                            :title="text.emptyFiltered.title"
                                            :description="text.emptyFiltered.description"
                                        >
                                            <Button variant="secondary" size="md" @click="activeFilter = 'all'">
                                                {{ text.emptyFiltered.reset }}
                                            </Button>
                                        </EmptyState>
                                    </Card>

                                    <div v-else class="space-y-4">
                                        <ReportHistoryCard
                                            v-for="item in filteredReports"
                                            :key="item.id"
                                            :item="item"
                                        />
                                    </div>
                                </section>

                                <!-- Panduan Status & Privasi (Sticky rail di desktop, di bawah daftar di mobile) -->
                                <aside class="lg:sticky lg:top-20 lg:col-span-4" aria-label="Panduan tindak lanjut BPD">
                                    <Card surface="subtle" class="p-4 sm:p-5">
                                        <div class="flex items-center gap-2.5">
                                            <span
                                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-info-subtle text-info"
                                                aria-hidden="true"
                                            >
                                                <svg
                                                    class="h-5 w-5"
                                                    fill="none"
                                                    viewBox="0 0 24 24"
                                                    stroke="currentColor"
                                                    stroke-width="2"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                                    />
                                                </svg>
                                            </span>
                                            <h3 class="text-base font-semibold text-content">
                                                {{ text.guide.title }}
                                            </h3>
                                        </div>

                                        <dl class="mt-4 divide-y divide-stroke">
                                            <div
                                                v-for="guideItem in text.guide.items"
                                                :key="guideItem.term"
                                                class="py-3 first:pt-0 last:pb-0"
                                            >
                                                <dt class="text-sm font-semibold text-content">
                                                    {{ guideItem.term }}
                                                </dt>
                                                <dd class="mt-0.5 text-sm leading-relaxed text-content-secondary">
                                                    {{ guideItem.detail }}
                                                </dd>
                                            </div>
                                        </dl>

                                        <p
                                            class="mt-4 border-t border-stroke pt-3 text-xs leading-relaxed text-content-secondary"
                                        >
                                            {{ text.guide.privacy }}
                                        </p>
                                    </Card>
                                </aside>
                            </div>
                        </template>
                    </template>
                </main>
            </div>
        </div>

        <BottomNav
            active-tab="laporan-saya"
            :village-slug="myReports.session?.village_slug ?? ''"
            @tab-click="handleTabClick"
        />

        <!-- Dialog: Bantuan -->
        <Dialog :open="showHelpDialog" title="Bantuan & Keterbukaan Informasi" @close="showHelpDialog = false">
            <div class="space-y-3 text-sm text-content-secondary">
                <p>
                    <strong class="font-semibold text-content">Sonar Desa</strong> adalah portal transparansi
                    pembangunan fisik desa yang dibiayai dana publik (Dana Desa, ADD, dll).
                </p>
                <p>
                    Di halaman Laporan Saya, kamu dapat memantau status setiap laporan Cek Spek dan tindak lanjut resmi
                    dari BPD.
                </p>
            </div>
            <template #actions>
                <Button variant="primary" size="md" class="w-full" @click="showHelpDialog = false"> Tutup </Button>
            </template>
        </Dialog>

        <!-- Dialog: Pindai QR -->
        <QrScannerDialog
            :open="showQrDialog"
            :village-name="myReports.session?.village_name ?? 'Desa Contoh'"
            :village-slug="myReports.session?.village_slug ?? ''"
            @close="showQrDialog = false"
        />
    </div>
</template>
