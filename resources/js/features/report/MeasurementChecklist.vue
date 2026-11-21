<script setup lang="ts">
// MeasurementChecklist: satu kartu per ukuran Cek Spek (C4): nilai RAB, cara ukur, lalu Sesuai / Tidak / Tidak bisa ukur.
// "Tidak" membuka isian hasil ukur dengan satuan yang sama dengan RAB (C5). Target sentuh >= 44 px (DESIGN.md 6).

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
    <ol class="space-y-3">
        <li v-for="(measurement, index) in measurements" :key="measurement.id">
            <fieldset
                class="rounded-2xl border bg-surface p-4"
                :class="showErrors && errorFor(index) ? 'border-warning-border' : 'border-stroke'"
            >
                <legend class="sr-only">{{ measurement.name }}</legend>
                <div class="flex items-baseline justify-between gap-3">
                    <p class="text-base font-semibold text-content" aria-hidden="true">{{ measurement.name }}</p>
                    <p class="shrink-0 text-sm font-semibold text-primary tabular-nums">
                        {{ checkSpec.rabValue(measurement.value, measurement.unit) }}
                    </p>
                </div>
                <p v-if="measurement.how_to" class="mt-1 text-sm text-content-secondary">{{ measurement.how_to }}</p>

                <div class="mt-3 grid grid-cols-3 gap-2">
                    <label
                        v-for="choice in choices"
                        :key="choice"
                        class="flex min-h-11 cursor-pointer items-center justify-center rounded-xl border px-2 text-center text-sm font-medium transition-colors has-[:focus-visible]:outline has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-primary"
                        :class="
                            answers[index]?.answer === choice
                                ? choice === 'mismatch'
                                    ? 'border-warning-border bg-warning-subtle text-warning'
                                    : 'border-primary bg-primary-subtle text-primary'
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
                        {{ checkSpec.answers[choice] }}
                    </label>
                </div>

                <div v-if="answers[index]?.answer === 'mismatch'" class="mt-3">
                    <label :for="`hasil-${measurement.id}`" class="text-sm font-medium text-content">
                        {{ checkSpec.measuredLabel(measurement.unit) }}
                    </label>
                    <div class="mt-1 flex items-center gap-2">
                        <input
                            :id="`hasil-${measurement.id}`"
                            v-model="answers[index]!.measured_value"
                            type="text"
                            inputmode="decimal"
                            autocomplete="off"
                            class="h-11 w-full rounded-md border border-stroke-strong bg-surface px-3 text-base text-content tabular-nums focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                        />
                        <span class="shrink-0 text-base text-content-secondary">{{ measurement.unit }}</span>
                    </div>
                </div>

                <p v-if="showErrors && errorFor(index)" class="mt-2 text-sm text-warning" role="alert">
                    {{ errorFor(index) }}
                </p>
            </fieldset>
        </li>
    </ol>
</template>
