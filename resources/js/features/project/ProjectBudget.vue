<script setup lang="ts">
// ProjectBudget: "Uangnya untuk apa saja?" Total RAB, harga per meter, dan kelompok bahan/upah/sewa alat.
// Kelompok diturunkan dari nama & satuan baris (lib/projectStory), jadi disebut "dikelompokkan otomatis".

import { computed } from 'vue';
import { formatRupiah, formatRupiahShort } from '@/lib/format';
import { type BudgetGroupKey, findMeasurement, groupBudget, measurementInMeters } from '@/lib/projectStory';
import type { ProjectDetail } from '@/types/project';

interface Props {
    project: ProjectDetail;
}

const props = defineProps<Props>();

const groups = computed(() => groupBudget(props.project.items));

const perMeter = computed(() => {
    const length = measurementInMeters(findMeasurement(props.project.measurements, 'panjang'));
    if (!length || length < 1 || props.project.total_amount_rp <= 0) {
        return null;
    }
    return formatRupiahShort(Math.round(props.project.total_amount_rp / length)).replace('juta', 'jt');
});

const swatch: Record<BudgetGroupKey, string> = {
    material: 'bg-primary',
    labor: 'bg-info-border',
    equipment: 'bg-content-tertiary',
};
</script>

<template>
    <section id="anggaran" class="scroll-mt-20">
        <h2 class="text-xl font-semibold text-content">Uangnya untuk apa saja?</h2>
        <p class="text-sm text-content-secondary">
            {{ project.items.length }} baris di RAB<template v-if="groups.length > 1"
                >, dikelompokkan otomatis</template
            >
        </p>

        <div class="mt-3 rounded-2xl border border-stroke bg-surface p-4 shadow-2xs sm:p-5">
            <p class="text-3xl font-bold tracking-tight text-content tabular-nums">
                {{ formatRupiah(project.total_amount_rp) }}
            </p>
            <p class="mt-0.5 text-sm text-content-secondary">Total anggaran (RAB)</p>
            <p
                v-if="perMeter"
                class="mt-3 inline-flex items-center rounded-full border border-info-border bg-info-subtle px-3 py-1 text-sm font-medium text-info tabular-nums"
            >
                &plusmn; {{ perMeter }} per meter
            </p>

            <template v-if="groups.length > 1">
                <div class="mt-5 flex h-3.5 gap-0.5 overflow-hidden rounded-full" aria-hidden="true">
                    <span
                        v-for="group in groups"
                        :key="group.key"
                        :class="swatch[group.key]"
                        :style="{ width: `${group.percent}%` }"
                    />
                </div>

                <!-- Desktop Cards Grid (md:grid) -->
                <div class="mt-4 hidden md:grid md:grid-cols-3 md:gap-3">
                    <div
                        v-for="group in groups"
                        :key="group.key"
                        class="rounded-xl border border-stroke bg-surface-subtle p-3.5"
                    >
                        <div class="flex items-center gap-2">
                            <span class="h-2.5 w-2.5 shrink-0 rounded-sm" :class="swatch[group.key]" aria-hidden="true" />
                            <span class="text-xs font-medium text-content-secondary">{{ group.label }}</span>
                        </div>
                        <p class="mt-2 text-xl font-bold tracking-tight text-content tabular-nums">
                            {{ formatRupiahShort(group.amountRp) }}
                        </p>
                        <p class="mt-1 truncate text-xs text-content-tertiary">
                            <span class="font-semibold text-content-secondary">{{ group.percent }}%</span> &middot; {{ group.itemNames.join(', ') }}
                        </p>
                    </div>
                </div>

                <!-- Mobile Compact List (md:hidden) -->
                <ul class="mt-4 flex flex-col gap-3 md:hidden">
                    <li v-for="group in groups" :key="group.key" class="flex items-start gap-3">
                        <span
                            class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-sm"
                            :class="swatch[group.key]"
                            aria-hidden="true"
                        />
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-content">
                                {{ group.label }}
                                <span class="font-normal text-content-tertiary tabular-nums"
                                    >&middot; {{ group.percent }}%</span
                                >
                            </p>
                            <p class="truncate text-xs text-content-secondary">{{ group.itemNames.join(', ') }}</p>
                        </div>
                        <p class="shrink-0 text-sm font-semibold text-content tabular-nums">
                            {{ formatRupiahShort(group.amountRp) }}
                        </p>
                    </li>
                </ul>
            </template>
        </div>
    </section>
</template>
