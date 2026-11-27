<script setup lang="ts">
// Dialog: native <dialog> element with focus trap and overlay. docs/DESIGN.md §6.
// rounded-lg shadow-sm. Title at top, default slot for body, actions slot at bottom.
// Close via button, Escape key, or overlay click.

import { onMounted, onUnmounted, ref, watch } from 'vue';

interface Props {
    open: boolean;
    title: string;
    // Prevents closing on overlay click and hides the X button when true.
    // Use for destructive confirmations.
    persistent?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    persistent: false,
});

const emit = defineEmits<{
    close: [];
}>();

const dialogEl = ref<HTMLDialogElement | null>(null);

function close(): void {
    if (!props.persistent) {
        emit('close');
    }
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        close();
    }
}

watch(
    () => props.open,
    (isOpen) => {
        if (!dialogEl.value) return;
        if (isOpen) {
            dialogEl.value.showModal?.();
        } else {
            dialogEl.value.close?.();
        }
    },
);

onMounted(() => {
    document.addEventListener('keydown', onKeydown);
});

onUnmounted(() => {
    document.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <Teleport to="body">
        <!-- Overlay -->
        <div v-if="open" class="fixed inset-0 z-40 bg-content/50" aria-hidden="true" @click="close" />

        <!-- Dialog panel -->
        <dialog
            ref="dialogEl"
            :open="open"
            :aria-label="title"
            class="fixed top-1/2 left-1/2 z-50 m-0 w-[calc(100%-2rem)] max-w-md -translate-x-1/2 -translate-y-1/2 overflow-hidden rounded-lg bg-surface p-0 shadow-sm"
            @cancel.prevent="close"
        >
            <div class="flex max-h-[88dvh] flex-col">
                <!-- Header -->
                <div class="flex shrink-0 items-start justify-between border-b border-stroke px-5 py-4 sm:px-6">
                    <h2 class="text-base font-semibold text-content">{{ title }}</h2>
                    <button
                        v-if="!persistent"
                        type="button"
                        class="ml-4 flex-shrink-0 rounded-md p-1 text-content-secondary transition-colors hover:bg-surface-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                        aria-label="Tutup"
                        @click="close"
                    >
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 20 20" stroke="currentColor" aria-hidden="true">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="1.5"
                                d="M5 5l10 10M15 5L5 15"
                            />
                        </svg>
                    </button>
                </div>

                <!-- Body -->
                <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-5 py-4 text-sm text-content sm:px-6">
                    <slot />
                </div>

                <!-- Actions -->
                <div
                    v-if="$slots.actions"
                    class="flex shrink-0 justify-end gap-3 border-t border-stroke bg-surface px-5 py-4 sm:px-6"
                >
                    <slot name="actions" />
                </div>
            </div>
        </dialog>
    </Teleport>
</template>
