<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Props {
    activeTab?: string;
    villageName?: string;
    villageSlug?: string;
    regency?: string;
}

const props = withDefaults(defineProps<Props>(), {
    activeTab: 'cari-desa',
    villageName: '',
    villageSlug: '',
    regency: '',
});

const emit = defineEmits<{
    tabClick: [tabId: string];
    share: [];
}>();

const formattedVillageName = computed(() => {
    if (!props.villageName) return '';
    return props.villageName.startsWith('Desa ') ? props.villageName : `Desa ${props.villageName}`;
});
</script>

<template>
    <aside
        class="sticky top-0 hidden h-screen overflow-y-auto border-r border-stroke bg-surface md:flex md:w-64 md:shrink-0 md:flex-col lg:w-72"
        aria-label="Navigasi desktop"
    >
        <!-- Logo & Branding -->
        <div class="flex items-center gap-3 border-b border-stroke px-6 py-5">
            <Link href="/" class="flex items-center gap-3 text-content transition-colors hover:text-primary">
                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary text-content-inverse shadow-xs"
                    aria-hidden="true"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9 9 0 100-18 9 9 0 000 18z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 15a3 3 0 100-6 3 3 0 000 6z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v3m0 12v3m9-9h-3M6 12H3" />
                    </svg>
                </div>
                <div>
                    <span class="block text-base font-bold tracking-tight text-content">Sonar Desa</span>
                    <span class="block text-xs text-content-tertiary">Papan Informasi Publik</span>
                </div>
            </Link>
        </div>

        <!-- Village Context Card (shown when looking at a specific village) -->
        <div v-if="villageName" class="px-4 pt-4 pb-2">
            <div class="rounded-xl border border-stroke bg-surface-subtle p-3.5">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-semibold tracking-wider text-content-tertiary uppercase">
                        Desa Aktif
                    </span>
                    <span
                        class="inline-flex items-center rounded-full bg-positive-subtle px-2 py-0.5 text-[10px] font-medium text-positive"
                    >
                        Publik
                    </span>
                </div>
                <p class="mt-1.5 truncate text-sm font-semibold text-content">
                    {{ formattedVillageName }}
                </p>
                <p v-if="regency" class="text-xs text-content-secondary">Kab. {{ regency }}</p>

                <button
                    type="button"
                    class="mt-3 inline-flex w-full items-center justify-center gap-1.5 rounded-lg border border-stroke bg-surface px-3 py-1.5 text-xs font-medium text-content transition-colors hover:bg-surface-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                    @click="emit('share')"
                >
                    <svg
                        class="h-3.5 w-3.5 text-content-secondary"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"
                        />
                    </svg>
                    <span>Bagikan profil</span>
                </button>
            </div>
        </div>

        <!-- Navigation Links -->
        <nav class="flex flex-1 flex-col gap-1 overflow-y-auto px-3 py-3" aria-label="Menu navigasi">
            <p class="px-3 pt-2 pb-1 text-[11px] font-semibold tracking-wider text-content-tertiary uppercase">
                Menu Utama
            </p>

            <!-- Beranda -->
            <Link
                href="/"
                :class="[
                    'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary',
                    activeTab === 'beranda'
                        ? 'bg-primary-subtle font-semibold text-primary'
                        : 'text-content-secondary hover:bg-surface-muted hover:text-content',
                ]"
            >
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M3 10.5 12 3l9 7.5M5 9v11a1 1 0 001 1h4v-6h4v6h4a1 1 0 001-1V9"
                    />
                </svg>
                <span>Beranda</span>
            </Link>

            <!-- Cari Desa -->
            <Link
                href="/"
                :class="[
                    'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary',
                    activeTab === 'cari-desa'
                        ? 'bg-primary-subtle font-semibold text-primary'
                        : 'text-content-secondary hover:bg-surface-muted hover:text-content',
                ]"
            >
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M11 4a7 7 0 100 14 7 7 0 000-14zM20 20l-4-4"
                    />
                </svg>
                <span>Cari desa</span>
            </Link>

            <!-- Pindai QR -->
            <button
                type="button"
                class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-medium text-content-secondary transition-colors hover:bg-surface-muted hover:text-content focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                @click="emit('tabClick', 'pindai-qr')"
            >
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M4 3h5a1 1 0 011 1v5a1 1 0 01-1 1H4a1 1 0 01-1-1V4a1 1 0 011-1zM15 3h5a1 1 0 011 1v5a1 1 0 01-1 1h-5a1 1 0 01-1-1V4a1 1 0 011-1zM4 14h5a1 1 0 011 1v5a1 1 0 01-1 1H4a1 1 0 01-1-1v-5a1 1 0 011-1zM14 14h3v3M21 14v.01M14 21h.01M17 21h4v-4"
                    />
                </svg>
                <span>Pindai QR</span>
            </button>

            <!-- Bantuan & KIP -->
            <button
                type="button"
                class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-medium text-content-secondary transition-colors hover:bg-surface-muted hover:text-content focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                @click="emit('tabClick', 'bantuan')"
            >
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M12 3a9 9 0 100 18 9 9 0 000-18zM9.5 9.5a2.5 2.5 0 113.5 2.3c-.6.3-1 .9-1 1.6V14M12 17h.01"
                    />
                </svg>
                <span>Bantuan &amp; KIP</span>
            </button>
        </nav>

        <!-- Sidebar Footer -->
        <div class="border-t border-stroke p-4 text-xs text-content-tertiary">
            <p class="font-medium text-content-secondary">Keterbukaan Informasi</p>
            <p class="mt-0.5 leading-relaxed">UU Desa No. 6/2014 &amp; UU KIP No. 14/2008</p>
            <a href="/login" class="mt-3 inline-block font-medium text-primary hover:underline">
                Pamong / BPD? Masuk &rarr;
            </a>
        </div>
    </aside>
</template>
