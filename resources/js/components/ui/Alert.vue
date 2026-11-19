<script setup lang="ts">
// Alert: status message box. docs/DESIGN.md §6.
// rounded-lg border with *-subtle background, icon, title (optional), and description.
// Always states what happened and what can be done next (ENGINEERING.md §2).

type Color = 'positive' | 'negative' | 'warning' | 'info';

interface Props {
    color: Color;
    title?: string;
}

withDefaults(defineProps<Props>(), {
    title: '',
});

type Config = {
    classes: string;
    icon: 'check' | 'x' | 'warn' | 'info';
};

const configs: Record<Color, Config> = {
    positive: { classes: 'border-positive-border bg-positive-subtle text-positive', icon: 'check' },
    negative: { classes: 'border-negative-border bg-negative-subtle text-negative', icon: 'x' },
    warning: { classes: 'border-warning-border bg-warning-subtle text-warning', icon: 'warn' },
    info: { classes: 'border-info-border bg-info-subtle text-info', icon: 'info' },
};
</script>

<template>
    <div :class="['rounded-lg border p-4', configs[color].classes]" role="alert">
        <div class="flex gap-3">
            <!-- Icon -->
            <span class="mt-0.5 flex-shrink-0">
                <svg
                    v-if="configs[color].icon === 'check'"
                    class="h-5 w-5"
                    viewBox="0 0 20 20"
                    fill="none"
                    stroke="currentColor"
                    aria-hidden="true"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 10l4 4 8-8" />
                </svg>
                <svg
                    v-else-if="configs[color].icon === 'x'"
                    class="h-5 w-5"
                    viewBox="0 0 20 20"
                    fill="none"
                    stroke="currentColor"
                    aria-hidden="true"
                >
                    <circle cx="10" cy="10" r="8" stroke-width="1.5" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 13l6-6M13 13L7 7" />
                </svg>
                <svg
                    v-else-if="configs[color].icon === 'warn'"
                    class="h-5 w-5"
                    viewBox="0 0 20 20"
                    fill="none"
                    stroke="currentColor"
                    aria-hidden="true"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="1.5"
                        d="M10 3L2 17h16L10 3zm0 5v4m0 2.5h.01"
                    />
                </svg>
                <svg v-else class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" aria-hidden="true">
                    <circle cx="10" cy="10" r="8" stroke-width="1.5" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10 9v5m0-7.5h.01" />
                </svg>
            </span>

            <div class="min-w-0 flex-1">
                <p v-if="title" class="font-semibold">{{ title }}</p>
                <p class="text-sm">
                    <slot />
                </p>
            </div>
        </div>
    </div>
</template>
