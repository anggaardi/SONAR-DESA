<script setup lang="ts">
// MeasurementChecklist: satu kartu per ukuran Cek Spek (C4): nilai RAB, cara ukur, lalu Sesuai / Tidak / Tidak bisa ukur.
// "Tidak" membuka isian hasil ukur dengan satuan yang sama dengan RAB (C5). Target sentuh >= 44 px (DESIGN.md 6).
// Mengikuti standar INA Digital Design System (IDDS) untuk tampilan mobile yang bersahabat dan jelas.

import { checkSpec } from '@/lib/labels';
import type { CheckSpecMeasurement } from '@/types/report';

import { answerIssue, type ChecklistAnswer, type MeasurementAnswer } from './checkSpecRules';

interface Props {
    measurements: CheckSpecMeasurement[];
    /** Show the per-measurement errors (after the visitor tried to continue). */
    showErrors?: boolean;
}

withDefaults(defineProps<Props>(), { showErrors: false });

const answers = defineModel<ChecklistAnswer[]>({ required: true });

const choices: MeasurementAnswer[] = ['match', 'mismatch', 'unmeasurable'];

function choose(index: number, choice: MeasurementAnswer): void {
    const current = answers.value[index];

    if (current) {
        answers.value[index] = {
            ...current,
            answer: choice,
            measured_value: choice === 'mismatch' ? current.measured_value : '',
        };
    }
}

function errorFor(index: number): string | null {
    const current = answers.value[index];
    const issue = current ? answerIssue(current) : 'unanswered';

    if (issue === 'unanswered') {
        return checkSpec.unanswered;
    }

    return issue === 'measured_value_invalid' ? checkSpec.measuredInvalid : null;
}
</script>

<template>
    <ol class="space-y-4">
        <li v-for="(measurement, index) in measurements" :key="measurement.id">
            <fieldset
                class="rounded-2xl border bg-surface p-4 transition-colors sm:p-5"
                :class="showErrors && errorFor(index) ? 'border-warning-border bg-warning-subtle/10' : 'border-stroke'"
            >
                <legend class="sr-only">{{ measurement.name }}</legend>

                <!-- Header Baris Ukuran & Target RAB IDDS -->
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <p class="text-base font-bold text-content sm:text-lg" aria-hidden="true">
                        {{ index + 1 }}. {{ measurement.name }}
                    </p>
                    <span
                        class="inline-flex items-center rounded-lg border border-primary/20 bg-primary-subtle px-2.5 py-1 text-xs font-bold text-primary tabular-nums sm:text-sm"
                    >
                        {{ checkSpec.rabValue(measurement.value, measurement.unit) }}
                    </span>
                </div>

                <!-- Panduan Cara Ukur Lapangan -->
                <p v-if="measurement.how_to" class="mt-1.5 text-xs leading-relaxed text-content-secondary sm:text-sm">
                    {{ measurement.how_to }}
                </p>

                <!-- Tombol Pilihan 3-Arah (Segmented Control IDDS - min 44px) -->
                <div class="mt-3.5 grid grid-cols-3 gap-2 sm:gap-3">
                    <label
                        v-for="choice in choices"
                        :key="choice"
                        class="flex min-h-12 cursor-pointer items-center justify-center rounded-xl border px-2 py-2 text-center text-xs font-medium transition-all select-none has-[:focus-visible]:outline has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-primary sm:text-sm"
                        :class="
                            answers[index]?.answer === choice
                                ? choice === 'mismatch'
                                    ? 'border-warning-border bg-warning-subtle font-bold text-warning ring-2 ring-warning/30'
                                    : choice === 'match'
                                      ? 'border-primary bg-primary-subtle font-bold text-primary ring-2 ring-primary/30'
                                      : 'border-stroke-strong bg-surface-muted font-bold text-content ring-2 ring-content/20'
                                : 'border-stroke bg-surface text-content hover:bg-surface-muted'
                        "
                    >
                        <input
                            type="radio"
                            class="sr-only"
                            :name="`ukuran-${measurement.id}`"
                            :value="choice"
                            :checked="answers[index]?.answer === choice"
                            @change="choose(index, choice)"
                        />
                        <span class="flex items-center gap-1.5">
                            <span v-if="choice === 'match'" class="text-sm">✓</span>
                            <span v-else-if="choice === 'mismatch'" class="text-sm">✕</span>
                            <span v-else class="text-sm">—</span>
                            <span>{{ checkSpec.answers[choice] }}</span>
                        </span>
                    </label>
                </div>

                <!-- Progressive Disclosure: Input Hasil Ukur Riil jika Tidak Sesuai -->
                <div
                    v-if="answers[index]?.answer === 'mismatch'"
                    class="mt-4 rounded-xl border border-stroke bg-surface-subtle p-3.5 sm:p-4"
                >
                    <label :for="`hasil-${measurement.id}`" class="block text-xs font-bold text-content sm:text-sm">
                        {{ checkSpec.measuredLabel(measurement.unit) }}
                    </label>
                    <div class="mt-2 flex items-center gap-2">
                        <input
                            :id="`hasil-${measurement.id}`"
                            v-model="answers[index]!.measured_value"
                            type="text"
                            inputmode="decimal"
                            autocomplete="off"
                            placeholder="0"
                            class="h-11 w-full rounded-xl border border-stroke-strong bg-surface px-3.5 text-base font-semibold text-content tabular-nums focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary sm:h-12"
                        />
                        <span
                            class="shrink-0 rounded-xl border border-stroke bg-surface-muted px-3.5 py-2.5 text-sm font-semibold text-content-secondary"
                        >
                            {{ measurement.unit }}
                        </span>
                    </div>
                    <p class="mt-1.5 text-xs text-content-secondary">
                        Selisih ukuran akan dicatat sebagai bahan verifikasi audit BPD.
                    </p>
                </div>

                <!-- Pesan Error Validasi Per Item -->
                <div
                    v-if="showErrors && errorFor(index)"
                    class="mt-2.5 flex items-center gap-1.5 text-xs text-warning sm:text-sm"
                    role="alert"
                >
                    <svg
                        class="h-4 w-4 shrink-0"
                        viewBox="0 0 20 20"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 3L2 17h16L10 3zm0 5v4m0 2.5h.01" />
                    </svg>
                    <span>{{ errorFor(index) }}</span>
                </div>
            </fieldset>
        </li>
    </ol>
</template>
