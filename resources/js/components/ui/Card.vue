<script setup lang="ts">
interface Props {
    padding?: 'base' | 'app';
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
