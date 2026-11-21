<script setup lang="ts">
// PriceLabel: Badge variant for price-check results. docs/DESIGN.md §6.
// Always shows text + color + icon (PRD 6.1: status never color alone).
// Values mirror backend PriceLabel enum: within_standard | above_standard |
// needs_check | no_reference.

import { computed } from 'vue';

type Label = 'within_standard' | 'above_standard' | 'needs_check' | 'no_reference';

interface Props {
    label: Label;
    // Optional percentage shown for above_standard (e.g. "+12%")
    percentAbove?: string | null;
}

const props = withDefaults(defineProps<Props>(), {
    percentAbove: null,
});

type Config = {
    color: 'positive' | 'negative' | 'warning' | 'neutral';
    text: string;
    icon: 'check' | 'up' | 'question' | 'minus';
};

const configs: Record<Label, Config> = {
    within_standard: { color: 'positive', text: 'Sesuai', icon: 'check' },
    above_standard: { color: 'negative', text: 'Di atas standar', icon: 'up' },
    needs_check: { color: 'warning', text: 'Perlu dicek', icon: 'question' },
    no_reference: { color: 'neutral', text: 'Tanpa acuan', icon: 'minus' },
};

const text = computed(() =>
    props.label === 'above_standard' && props.percentAbove
        ? `${configs[props.label].text} ${props.percentAbove}`
        : configs[props.label].text,
);

const colorClasses: Record<Config['color'], string> = {
    positive: 'border-positive-border bg-positive-subtle text-positive',
    negative: 'border-negative-border bg-negative-subtle text-negative',
    warning: 'border-warning-border bg-warning-subtle text-warning',
    neutral: 'border-stroke bg-surface-muted text-content-secondary',
};
</script>

<template>
    <span
        :class="[
            'inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-sm font-medium',
            colorClasses[configs[label].color],
        ]"
    >
        <!-- check -->
        <svg
            v-if="configs[label].icon === 'check'"
            class="h-3.5 w-3.5"
            viewBox="0 0 16 16"
            fill="none"
            stroke="currentColor"
            aria-hidden="true"
        >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l3.5 3.5L13 4" />
        </svg>

        <!-- up arrow -->
        <svg
            v-else-if="configs[label].icon === 'up'"
            class="h-3.5 w-3.5"
            viewBox="0 0 16 16"
            fill="none"
            stroke="currentColor"
            aria-hidden="true"
        >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12V4m0 0L4 8m4-4l4 4" />
        </svg>

        <!-- question mark -->
        <svg
            v-else-if="configs[label].icon === 'question'"
            class="h-3.5 w-3.5"
            viewBox="0 0 16 16"
            fill="none"
            stroke="currentColor"
            aria-hidden="true"
        >
            <circle cx="8" cy="8" r="6" stroke-width="1.5" />
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="1.5"
                d="M6.5 6a1.5 1.5 0 013 .6c0 1-1.5 1.4-1.5 2.4M8 11.5h.01"
            />
        </svg>

        <!-- minus / dash -->
        <svg v-else class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-width="2" d="M4 8h8" />
        </svg>

        <span>{{ text }}</span>
    </span>
</template>
