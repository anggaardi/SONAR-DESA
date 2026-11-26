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

const howTos = computed(() => props.project.measurements.filter((m) => m.how_to));
</script>

<template>
    <section id="ukuran" class="scroll-mt-20">
        <h2 class="text-xl font-semibold text-content">Ukuran di RAB</h2>
        <p class="text-sm text-content-secondary">Ini yang bisa kamu cocokkan di lokasi</p>

        <div
            v-if="project.measurements.length > 0"
            class="mt-3 rounded-2xl border border-stroke bg-surface-subtle p-4 sm:p-5"
        >
            <div :class="[diagram ? 'md:grid md:grid-cols-12 md:items-center md:gap-6' : '']">
                <!-- Gambar teknis lempeng (proyeksi miring). -->
                <div v-if="diagram" class="md:col-span-6">
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

                <div :class="[diagram ? 'mt-4 md:col-span-6 md:mt-0' : '']">
                    <dl
                        class="grid gap-2.5"
                        :class="[
                            project.measurements.length >= 3
                                ? 'grid-cols-2 sm:grid-cols-3 md:grid-cols-2'
                                : 'grid-cols-2',
                        ]"
                    >
                        <div
                            v-for="m in project.measurements"
                            :key="m.id"
                            class="rounded-xl border border-stroke bg-surface p-3"
                        >
                            <dt class="text-xs font-medium text-content-secondary sm:text-sm">{{ m.name }}</dt>
                            <dd class="mt-1 text-lg font-bold text-content tabular-nums sm:text-xl">
                                {{ display(m) }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            <div v-if="howTos.length > 0" class="mt-4 border-t border-stroke pt-3.5">
                <p class="text-sm font-semibold text-content">Cara mengukur</p>
                <ul class="mt-2 flex flex-col gap-1.5 text-sm text-content-secondary">
                    <li v-for="m in howTos" :key="m.id">
                        <span class="font-medium text-content">{{ m.name }}:</span> {{ m.how_to }}
                    </li>
                </ul>
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
