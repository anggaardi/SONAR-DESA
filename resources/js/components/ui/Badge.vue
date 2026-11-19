<script setup lang="ts">
// Badge: status chip. docs/DESIGN.md §6.
// rounded-full border px-2.5 py-0.5 text-sm font-medium.
// Status colors: positive / negative / warning / info / demo / neutral.
// Always contains visible text; icon (slot `icon`) is optional and decorative.

type Color = 'positive' | 'negative' | 'warning' | 'info' | 'demo' | 'neutral';

interface Props {
    color?: Color;
}

withDefaults(defineProps<Props>(), {
    color: 'neutral',
});

const colorClasses: Record<Color, string> = {
    positive: 'border-positive-border bg-positive-subtle text-positive',
    negative: 'border-negative-border bg-negative-subtle text-negative',
    warning: 'border-warning-border bg-warning-subtle text-warning',
    info: 'border-info-border bg-info-subtle text-info',
    demo: 'border-transparent bg-demo-subtle text-demo',
    neutral: 'border-stroke bg-surface-muted text-content-secondary',
};
</script>

<template>
    <span
        :class="[
            'inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-sm font-medium',
            colorClasses[color],
        ]"
    >
        <slot />
    </span>
</template>
