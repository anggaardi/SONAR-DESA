<script setup lang="ts">
export interface FilterCategory {
    id: string;
    label: string;
}

interface Props {
    searchQuery: string;
    activeCategory: string;
    categories: FilterCategory[];
}

defineProps<Props>();

const emit = defineEmits<{
    'update:searchQuery': [value: string];
    'update:activeCategory': [categoryId: string];
}>();
</script>

<template>
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <!-- Search field -->
        <div class="relative w-full md:max-w-xs lg:max-w-sm">
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-content-tertiary">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                    />
                </svg>
            </div>
            <input
                id="project-search-input"
                type="search"
                :value="searchQuery"
                placeholder="Cari proyek"
                aria-label="Cari proyek"
                class="h-11 w-full rounded-2xl border border-stroke-strong bg-surface pr-4 pl-10 text-sm text-content placeholder:text-content-tertiary focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
                @input="emit('update:searchQuery', ($event.target as HTMLInputElement).value)"
            />
        </div>

        <!-- Category filter chips -->
        <div
            v-if="categories.length > 1"
            class="no-scrollbar -mx-4 flex gap-2 overflow-x-auto px-4 py-1 sm:mx-0 sm:flex-wrap sm:px-0"
            role="tablist"
            aria-label="Filter kategori proyek"
        >
            <button
                v-for="cat in categories"
                :key="cat.id"
                type="button"
                role="tab"
                :aria-selected="activeCategory === cat.id"
                :class="[
                    'inline-flex min-h-9 shrink-0 items-center justify-center rounded-full px-4 text-xs font-medium transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary',
                    activeCategory === cat.id
                        ? 'bg-content text-surface'
                        : 'border border-stroke bg-surface text-content hover:bg-surface-subtle',
                ]"
                @click="emit('update:activeCategory', cat.id)"
            >
                {{ cat.label }}
            </button>
        </div>
    </div>
</template>
