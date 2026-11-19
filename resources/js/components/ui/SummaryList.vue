<script setup lang="ts">
// SummaryList: GOV.UK-style dt/dd list with stroke dividers.
// docs/DESIGN.md §6. Used for project details and check-answers screens.
// Consumer provides items as an array of { term, detail } or uses the default slot.

interface SummaryItem {
    term: string;
    detail: string;
}

interface Props {
    items?: SummaryItem[];
}

withDefaults(defineProps<Props>(), {
    items: () => [],
});
</script>

<template>
    <dl class="divide-y divide-stroke">
        <!-- Structured items via prop -->
        <template v-if="items.length">
            <div v-for="item in items" :key="item.term" class="flex flex-col gap-1 py-3 sm:flex-row sm:gap-4">
                <dt class="w-full text-sm font-medium text-content-secondary sm:w-40 sm:flex-shrink-0">
                    {{ item.term }}
                </dt>
                <dd class="text-sm text-content">
                    {{ item.detail }}
                </dd>
            </div>
        </template>

        <!-- Freeform rows via slot -->
        <slot />
    </dl>
</template>
