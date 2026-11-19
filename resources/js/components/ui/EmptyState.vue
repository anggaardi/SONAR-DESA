<script setup lang="ts">
// EmptyState: Human-friendly empty state with IDDS-inspired spot illustrations.
// docs/DESIGN.md §6.
// Uses semantic SVGs (plang proyek desa, map/compass, search, success seal)
// with tokenized styling so it remains fully accessible and compliant with zero raw colors.

type IllustrationType = 'plang-empty' | 'not-found' | 'search-empty' | 'success-seal';

interface Props {
    title: string;
    description?: string;
    illustration?: IllustrationType;
}

withDefaults(defineProps<Props>(), {
    description: '',
    illustration: 'plang-empty',
});
</script>

<template>
    <div class="flex flex-col items-center justify-center py-8 text-center sm:py-12">
        <div class="mb-5 flex h-36 w-36 items-center justify-center sm:h-40 sm:w-40" aria-hidden="true">
            <slot name="illustration">
                <svg
                    v-if="illustration === 'plang-empty'"
                    class="h-full w-full"
                    viewBox="0 0 160 160"
                    fill="none"
                    xmlns="http://www.w3.org/2000/svg"
                >
                    <line
                        x1="48"
                        y1="95"
                        x2="48"
                        y2="142"
                        class="stroke-stroke-strong"
                        stroke-width="3"
                        stroke-linecap="round"
                    />
                    <line
                        x1="112"
                        y1="95"
                        x2="112"
                        y2="142"
                        class="stroke-stroke-strong"
                        stroke-width="3"
                        stroke-linecap="round"
                    />
                    <path d="M28 142h104" class="stroke-stroke" stroke-width="2" stroke-linecap="round" />
                    <path d="M36 147h24" class="stroke-stroke" stroke-width="2" stroke-linecap="round" />
                    <rect                    <rect

                        x="26"
                        y="28"
                        width="108"
                        height="68"
                        rx="6"
                        class="fill-surface stroke-stroke-strong"
                        stroke-width="2.5"
                    />
                    <path d="M26 44h108" class="stroke-stroke" stroke-width="1.5" />
                    <rect x="36" y="54" width="48" height="6" rx="3" class="fill-surface-muted" />
                    <rect x="36" y="66" width="34" height="6" rx="3" class="fill-surface-muted" />
                    <rect x="36" y="78" width="56" height="6" rx="3" class="fill-surface-muted" />
                    <circle cx="106" cy="68" r="14" class="fill-primary-subtle stroke-primary" stroke-width="2" />
                    <path d="M102 68h8M106 64v8" class="stroke-primary" stroke-width="2" stroke-linecap="round" />
                </svg>

                <svg
                    v-else-if="illustration === 'not-found'"
                    class="h-full w-full"
                    viewBox="0 0 160 160"
                    fill="none"
                    xmlns="http://www.w3.org/2000/svg"
                >
                    <line
                        x1="80"
                        y1="30"
                        x2="80"
                        y2="142"
                        class="stroke-stroke-strong"
                        stroke-width="3"
                        stroke-linecap="round"
                    />
                    <path d="M52 142h56" class="stroke-stroke" stroke-width="2.5" stroke-linecap="round" />
                    <path d="M64 147h32" class="stroke-stroke" stroke-width="2" stroke-linecap="round" />
                    <path d="M42 42h38v24H42l-8-12 8-12z" class="fill-surface stroke-stroke-strong" stroke-width="2" />
                    <circle cx="58" cy="54" r="3" class="fill-content-secondary" />
                    <path
                        d="M80 74h44l8 12-8 12H80V74z"
                        class="fill-surface-subtle stroke-stroke-strong"
                        stroke-width="2"
                    />
                    <circle cx="104" cy="46" r="16" class="fill-primary-subtle stroke-primary" stroke-width="2" />
                    <text x="104" y="52" text-anchor="middle" class="fill-primary text-base font-bold select-none">
                        ?
                    </text>
                </svg>

                <svg
                    v-else-if="illustration === 'search-empty'"
                    class="h-full w-full"
                    viewBox="0 0 160 160"
                    fill="none"
                    xmlns="http://www.w3.org/2000/svg"
                >
                    <rect
                        x="38"
                        y="28"
                        width="84"
                        height="104"
                        rx="6"
                        class="fill-surface stroke-stroke-strong"
                        stroke-width="2"
                    />
                    <line
                        x1="52"
                        y1="46"
                        x2="88"
                        y2="46"
                        class="stroke-stroke"
                        stroke-width="2.5"
                        stroke-linecap="round"
                    />
                    <line
                        x1="52"
                        y1="58"
                        x2="108"
                        y2="58"
                        class="stroke-stroke"
                        stroke-width="2"
                        stroke-linecap="round"
                    />
                    <line
                        x1="52"
                        y1="70"
                        x2="100"
                        y2="70"
                        class="stroke-stroke"
                        stroke-width="2"
                        stroke-linecap="round"
                    />
                    <line
                        x1="52"
                        y1="82"
                        x2="92"
                        y2="82"
                        class="stroke-stroke"
                        stroke-width="2"
                        stroke-linecap="round"
                    />
                    <!-- Kaca pembesar bersahabat -->
                    <circle cx="98" cy="98" r="22" class="fill-surface-subtle stroke-primary" stroke-width="2.5" />
                    <line
                        x1="114"
                        y1="114"
                        x2="132"
                        y2="132"
                        class="stroke-primary"
                        stroke-width="3"
                        stroke-linecap="round"
                    />
                    <circle cx="98" cy="98" r="14" class="fill-primary-subtle" />
                </svg>

                <svg
                    v-else-if="illustration === 'success-seal'"
                    class="h-full w-full"
                    viewBox="0 0 160 160"
                    fill="none"
                    xmlns="http://www.w3.org/2000/svg"
                >
                    <rect
                        x="36"
                        y="24"
                        width="88"
                        height="112"
                        rx="6"
                        class="fill-surface stroke-stroke-strong"
                        stroke-width="2"
                    />
                    <line
                        x1="50"
                        y1="42"
                        x2="86"
                        y2="42"
                        class="stroke-stroke-strong"
                        stroke-width="2.5"
                        stroke-linecap="round"
                    />
                    <line
                        x1="50"
                        y1="56"
                        x2="110"
                        y2="56"
                        class="stroke-stroke"
                        stroke-width="2"
                        stroke-linecap="round"
                    />
                    <line
                        x1="50"
                        y1="68"
                        x2="104"
                        y2="68"
                        class="stroke-stroke"
                        stroke-width="2"
                        stroke-linecap="round"
                    />
                    <!-- Stempel Segel Desa Hijau Lembut -->
                    <circle
                        cx="98"
                        cy="104"
                        r="24"
                        class="fill-positive-subtle stroke-positive-border"
                        stroke-width="2"
                    />
                    <circle cx="98" cy="104" r="18" class="stroke-positive" stroke-width="1.5" stroke-dasharray="3 3" />
                    <path
                        d="M91 104l4 4 10-10"
                        class="stroke-positive"
                        stroke-width="2.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />
                </svg>
            </slot>
        </div>

        <h3 class="text-lg font-semibold text-content sm:text-xl">
            {{ title }}
        </h3>

        <p v-if="description" class="mt-2 max-w-sm text-sm leading-relaxed text-content-secondary sm:text-base">
            {{ description }}
        </p>

        <!-- Aksi / Tombol -->
        <div v-if="$slots.default" class="mt-6 flex flex-wrap items-center justify-center gap-3">
            <slot />
        </div>
    </div>
</template>
