<script setup lang="ts">
import BalaiIllustration from '@/components/ui/illustrations/BalaiIllustration.vue';

interface Props {
    regency: string;
    name: string;
    description?: string;
}

withDefaults(defineProps<Props>(), {
    description: 'Daftar pembangunan fisik yang dibiayai anggaran publik.',
});
</script>

<template>
    <section class="bg-primary-subtle px-4 pt-5 pb-7 sm:px-6 sm:pt-6">
        <div class="mx-auto flex max-w-md items-center justify-between gap-4 sm:max-w-xl md:max-w-2xl">
            <div class="min-w-0 flex-1">
                <p class="text-xs font-medium text-content-secondary sm:text-sm">Kab. {{ regency }}</p>
                <h2 class="mt-1 text-2xl font-bold tracking-tight text-content sm:text-3xl">
                    {{ name.startsWith('Desa ') ? name : `Desa ${name}` }}
                </h2>
                <p class="mt-1 text-sm leading-snug text-content-secondary sm:text-base">
                    {{ description }}
                </p>
            </div>
            <BalaiIllustration :size="108" class="sm:scale-105" />
        </div>
    </section>
</template>
