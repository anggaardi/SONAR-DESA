<script setup lang="ts">
import { computed } from 'vue';
import { illustrationSources, type IllustrationType } from './illustrations';

export type { IllustrationType };

interface Props {
    title: string;
    description?: string;
    illustration?: IllustrationType;
    compact?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    description: '',
    illustration: 'plang-empty',
    compact: false,
});

const src = computed(() => illustrationSources[props.illustration]);
</script>

<template>
    <div :class="['flex flex-col items-center justify-center text-center', compact ? 'py-5 sm:py-6' : 'py-8 sm:py-12']">
        <div
            :class="['aspect-4/3 w-full', compact ? 'mb-3 max-w-36 sm:max-w-44' : 'mb-5 max-w-56 sm:max-w-64']"
            aria-hidden="true"
        >
            <slot name="illustration">
                <img :src="src" alt="" width="280" height="210" decoding="sync" class="h-full w-full object-contain" />
            </slot>
        </div>

        <h3 :class="['font-semibold text-content', compact ? 'text-base sm:text-lg' : 'text-lg sm:text-xl']">
            {{ title }}
        </h3>

        <p v-if="description" class="mt-2 max-w-sm text-sm leading-relaxed text-content-secondary sm:text-base">
            {{ description }}
        </p>

        <!-- Aksi / Tombol -->
        <div
            v-if="$slots.default"
            :class="['flex flex-wrap items-center justify-center gap-3', compact ? 'mt-4' : 'mt-6']"
        >
            <slot />
        </div>
    </div>
</template>
