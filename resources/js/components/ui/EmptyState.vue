<script setup lang="ts">
import { computed } from 'vue';
import notFound from './illustrations/not-found.svg';
import plangEmpty from './illustrations/plang-empty.svg';
import searchEmpty from './illustrations/search-empty.svg';
import successSeal from './illustrations/success-seal.svg';

type IllustrationType = 'plang-empty' | 'not-found' | 'search-empty' | 'success-seal';

interface Props {
    title: string;
    description?: string;
    illustration?: IllustrationType;
}

const props = withDefaults(defineProps<Props>(), {
    description: '',
    illustration: 'plang-empty',
});

const sources: Record<IllustrationType, string> = {
    'plang-empty': plangEmpty,
    'not-found': notFound,
    'search-empty': searchEmpty,
    'success-seal': successSeal,
};

const src = computed(() => sources[props.illustration]);
</script>

<template>
    <div class="flex flex-col items-center justify-center py-8 text-center sm:py-12">
        <div class="mb-5 aspect-4/3 w-full max-w-56 sm:max-w-64" aria-hidden="true">
            <slot name="illustration">
                <img :src="src" alt="" width="280" height="210" class="h-full w-full" />
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
