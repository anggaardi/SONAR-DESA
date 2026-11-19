<script setup lang="ts">
// Table: data table with IDDS styling. docs/DESIGN.md §6.
// Header: bg-surface-subtle text-sm font-medium text-content-secondary.
// Rows: divided by border-stroke, hover bg-surface-muted, numbers tabular-nums text-right.
// On mobile (< sm): collapses to card list via `stackOnMobile` prop.
// PriceLabel rows get bg-warning-subtle automatically via `highlightRow` slot prop.

interface Column {
    key: string;
    label: string;
    // 'number' aligns right with tabular-nums
    align?: 'left' | 'right';
    // If true, cell is treated as a row header (th scope="row")
    isRowHeader?: boolean;
}

interface Props {
    columns: Column[];
    // Caption for accessibility (visually hidden unless `showCaption` is true)
    caption: string;
    showCaption?: boolean;
    // Key of a column whose value determines highlighted rows ('above_standard', 'needs_check')
    highlightKey?: string;
    highlightValues?: string[];
}

withDefaults(defineProps<Props>(), {
    showCaption: false,
    highlightKey: undefined,
    highlightValues: () => ['above_standard', 'needs_check'],
});
</script>

<template>
    <div class="overflow-hidden rounded-lg border border-stroke">
        <table class="w-full text-sm">
            <caption :class="showCaption ? 'mb-2 text-left text-sm font-semibold text-content' : 'sr-only'">
                {{
                    caption
                }}
            </caption>
            <thead>
                <tr class="bg-surface-subtle">
                    <th
                        v-for="col in columns"
                        :key="col.key"
                        scope="col"
                        :class="[
                            'px-4 py-3 font-medium text-content-secondary',
                            col.align === 'right' ? 'text-right' : 'text-left',
                        ]"
                    >
                        {{ col.label }}
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stroke">
                <slot />
            </tbody>
            <tfoot v-if="$slots.foot">
                <slot name="foot" />
            </tfoot>
        </table>
    </div>
</template>
