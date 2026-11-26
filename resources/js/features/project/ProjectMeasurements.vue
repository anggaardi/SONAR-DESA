<script setup lang="ts">
// ProjectMeasurements: "Ukuran di RAB" sebagai gambar teknis + kartu angka + cara ukur.
// PRD P11, P12, F20, F21 (Langkah 7, 9). Gambar lempeng hanya muncul bila panjang & lebar ada.

import { computed } from 'vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import { formatLength } from '@/lib/format';
import { findMeasurement, measurementInMeters } from '@/lib/projectStory';
import type { ProjectDetail, ProjectMeasurementItem } from '@/types/project';

interface Props {
    project: ProjectDetail;
}

const props = defineProps<Props>();

function display(m: ProjectMeasurementItem): string {
    const meters = measurementInMeters(m);
    return meters !== null ? formatLength(meters) : `${m.value} ${m.unit}`;
}

const diagram = computed(() => {
    const length = findMeasurement(props.project.measurements, 'panjang');
    const width = findMeasurement(props.project.measurements, 'lebar');
    if (!length || !width) {
        return null;
    }
    const thickness = findMeasurement(props.project.measurements, 'tebal');
    return {
        length: display(length),
        width: display(width),
        thickness: thickness ? display(thickness) : null,
    };
});
</script>

<template>
    <section id="ukuran" class="scroll-mt-20">
        <h2 class="text-xl font-semibold text-content">Yang bisa kamu cek di lokasi</h2>
        <p class="text-sm text-content-secondary">
            Ukuran di RAB dan cara mencocokkannya di lapangan — langsung bisa dibaca tanpa login.
        </p>

        <div
            v-if="project.measurements.length > 0"
            class="mt-3 rounded-2xl border border-stroke bg-surface-subtle p-4 sm:p-5"
        >
            <div :class="[diagram ? 'md:grid md:grid-cols-12 md:items-center md:gap-6' : '']">
                <!-- Gambar teknis lempeng (proyeksi miring). -->
                <div v-if="diagram" class="md:col-span-5">
                    <svg class="mx-auto block w-full max-w-80" viewBox="0 0 320 128" aria-hidden="true">
                        <path d="M62 70H246L292 36H108Z" class="fill-content-tertiary" />
                        <path d="M62 70H246V86H62Z" class="fill-content-secondary" />
                        <path d="M246 70L292 36V52L246 86Z" class="fill-content" />
                        <path d="M95 53H259" class="stroke-surface" stroke-width="1.6" stroke-dasharray="9 7" />
                        <g class="stroke-primary" stroke-width="1.5" stroke-linecap="round" fill="none">
                            <path d="M62 102H246M62 97V107M246 97V107" />
                            <path d="M260 78L306 44" />
                            <path v-if="diagram.thickness" d="M50 70V86M45 70H55M45 86H55" />
                        </g>
                        <rect x="118" y="91" width="72" height="22" rx="11" class="fill-surface stroke-info-border" />
                        <text x="154" y="106.5" text-anchor="middle" class="fill-primary text-[13px] font-semibold">
                            {{ diagram.length }}
                        </text>
                        <text x="286" y="78" class="fill-primary text-[13px] font-semibold">{{ diagram.width }}</text>
                        <text
                            v-if="diagram.thickness"
                            x="40"
                            y="82.5"
                            text-anchor="end"
                            class="fill-primary text-[13px] font-semibold"
                        >
                            {{ diagram.thickness }}
                        </text>
                    </svg>
                </div>

                <div :class="[diagram ? 'mt-4 md:col-span-7 md:mt-0' : '']">
                    <ul class="grid gap-2.5 sm:grid-cols-2">
                        <li
                            v-for="m in project.measurements"
                            :key="m.id"
                            class="rounded-xl border border-stroke bg-surface p-3.5"
                        >
                            <div class="flex items-baseline justify-between gap-2">
                                <span class="text-xs font-semibold text-content-secondary sm:text-sm">{{
                                    m.name
                                }}</span>
                                <span
                                    class="shrink-0 rounded-md border border-primary/20 bg-primary-subtle px-2 py-0.5 text-xs font-bold text-primary tabular-nums sm:text-sm"
                                >
                                    {{ display(m) }}
                                </span>
                            </div>
                            <p v-if="m.how_to" class="mt-2 text-xs leading-relaxed text-content-secondary">
                                <span class="font-medium text-content">Cara cek:</span> {{ m.how_to }}
                            </p>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div v-else class="mt-3 rounded-lg border border-stroke bg-surface px-4">
            <EmptyState
                illustration="plang-empty"
                compact
                title="Ukuran fisik belum tersedia"
                description="Belum ada ukuran fisik yang tercatat di RAB proyek ini."
            />
        </div>
    </section>
</template>
