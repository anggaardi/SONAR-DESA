<script setup lang="ts">
import BalaiIllustration from '@/components/ui/illustrations/BalaiIllustration.vue';

interface Props {
    regency: string;
    name: string;
    description?: string;
}

withDefaults(defineProps<Props>(), {
    description: 'Transparansi pembangunan fisik yang dibiayai anggaran publik.',
});
</script>

<template>
    <section class="w-full bg-primary-subtle px-6 py-6 md:px-8 lg:px-10 lg:py-8">
        <div class="mx-auto flex items-center justify-between gap-6">
            <div class="min-w-0 flex-1">
                <p class="text-xs font-medium text-content-secondary sm:text-sm">Kab. {{ regency }}</p>
                <h2 class="mt-1 text-2xl font-bold tracking-tight text-content sm:text-3xl lg:text-4xl">
                    {{ name.startsWith('Desa ') ? name : `Desa ${name}` }}
                </h2>
                <p class="mt-2 text-sm leading-relaxed text-content-secondary sm:text-base">
                    {{ description }}
                </p>
            </div>
            <div class="shrink-0">
                <BalaiIllustration :size="120" />
            </div>
        </div>
    </section>
</template>
