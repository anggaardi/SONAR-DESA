<script setup lang="ts">
// Card: content container. docs/DESIGN.md §6.
// rounded-lg border border-stroke bg-surface.
// Padding p-4 (mobile / citizen) or p-6 (desktop / app). No shadow. No nested cards.

interface Props {
    // 'base' = p-4 for citizen screens; 'app' = p-6 for desktop app screens
    padding?: 'base' | 'app';
    // 'default' = white card; 'subtle' = slightly grey background
    surface?: 'default' | 'subtle';
}

withDefaults(defineProps<Props>(), {
    padding: 'base',
    surface: 'default',
});
</script>

<template>
    <div
        :class="[
            'rounded-lg border border-stroke',
            padding === 'app' ? 'p-6' : 'p-4',
            surface === 'subtle' ? 'bg-surface-subtle' : 'bg-surface',
        ]"
    >
        <slot />
    </div>
</template>
