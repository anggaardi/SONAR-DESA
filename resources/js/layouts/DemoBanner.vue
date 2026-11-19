<script setup lang="ts">
// DemoBanner: shown on every demo screen. PRD Fitur 7.2 / D-006 color `demo`.
// Provides "Ganti peran" and "Mulai ulang demo" actions via emits.

const emit = defineEmits<{
    changeRole: [];
    restartDemo: [];
}>();
</script>

<template>
    <div
        role="banner"
        class="flex flex-wrap items-center justify-between gap-x-6 gap-y-2 bg-demo-subtle px-4 py-2 text-sm font-medium text-demo"
    >
        <span>Data demo &mdash; desa &amp; angka fiktif</span>

        <div class="flex gap-4">
            <button
                type="button"
                class="underline-offset-2 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                @click="emit('changeRole')"
            >
                Ganti peran
            </button>
            <button
                type="button"
                class="underline-offset-2 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                @click="emit('restartDemo')"
            >
                Mulai ulang demo
            </button>
        </div>
    </div>
</template>
