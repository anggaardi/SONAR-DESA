<script setup lang="ts">
// MeasurementChecklist: satu pertanyaan penutup untuk seluruh laporan ("Apa yang kamu temukan?").
// Target sentuh >= 44 px (DESIGN.md 6) sesuai INA Digital Design System (IDDS).

import { computed } from 'vue';
import { checkSpec } from '@/lib/labels';
import type { CheckSpecMeasurement } from '@/types/report';

import {
    findingIssue,
    isOptionalMeasuredValueInvalid,
    type ChecklistState,
    type OverallFinding,
} from './checkSpecRules';

interface Props {
    measurements: CheckSpecMeasurement[];
    /** Show validation errors after the visitor tried to submit. */
    showErrors?: boolean;
}

withDefaults(defineProps<Props>(), { showErrors: false });

const state = defineModel<ChecklistState>({ required: true });

const overallOptions: OverallFinding[] = ['match', 'mismatch', 'unmeasurable'];

const currentIssue = computed(() => findingIssue(state.value));

function chooseOverall(choice: OverallFinding): void {
    state.value = { ...state.value, overall: choice };
}

function isMismatchSelected(id: number): boolean {
    return state.value.mismatchIds.includes(id);
}

function toggleMismatchItem(id: number): void {
    const next = isMismatchSelected(id)
        ? state.value.mismatchIds.filter((item) => item !== id)
        : [...state.value.mismatchIds, id];

    state.value = { ...state.value, mismatchIds: next };
}

function updateMeasuredValue(id: number, value: string): void {
    state.value = {
        ...state.value,
        measuredValues: {
            ...state.value.measuredValues,
            [id]: value,
        },
    };
}
</script>

<template>
    <fieldset
        class="rounded-2xl border bg-surface p-4 transition-colors sm:p-5"
        :class="showErrors && currentIssue ? 'border-warning-border bg-warning-subtle/10' : 'border-stroke'"
    >
        <div class="flex flex-wrap items-center justify-between gap-2">
            <legend class="text-base font-bold text-content sm:text-lg">
                {{ checkSpec.finding.question }}
            </legend>
            <span
                class="rounded-full border border-stroke bg-surface-subtle px-2.5 py-0.5 text-xs font-semibold text-content-secondary"
            >
                {{ checkSpec.finding.requiredBadge }}
            </span>
        </div>

        <div class="mt-3.5 space-y-2.5">
            <label
                v-for="option in overallOptions"
                :key="option"
                class="flex min-h-12 cursor-pointer items-start gap-3 rounded-xl border p-3.5 transition-all select-none has-[:focus-visible]:outline has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-primary"
                :class="
                    state.overall === option
                        ? option === 'mismatch'
                            ? 'border-warning-border bg-warning-subtle/40 ring-2 ring-warning/25'
                            : option === 'match'
                              ? 'border-primary bg-primary-subtle/70 ring-2 ring-primary/25'
                              : 'border-stroke-strong bg-surface-muted ring-2 ring-content/15'
                        : 'border-stroke bg-surface hover:bg-surface-subtle'
                "
            >
                <input
                    type="radio"
                    class="sr-only"
                    name="overall_finding"
                    :value="option"
                    :checked="state.overall === option"
                    @change="chooseOverall(option)"
                />
                <span
                    class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2"
                    :class="
                        state.overall === option
                            ? option === 'mismatch'
                                ? 'border-warning bg-warning'
                                : option === 'match'
                                  ? 'border-primary bg-primary'
                                  : 'border-content bg-content'
                            : 'border-stroke-strong bg-surface'
                    "
                    aria-hidden="true"
                >
                    <span v-if="state.overall === option" class="h-2 w-2 rounded-full bg-surface" />
                </span>
                <div class="min-w-0">
                    <p class="text-sm font-bold text-content sm:text-base">
                        {{ checkSpec.finding.options[option].title }}
                    </p>
                    <p class="mt-0.5 text-xs leading-relaxed text-content-secondary sm:text-sm">
                        {{ checkSpec.finding.options[option].description }}
                    </p>
                </div>
            </label>
        </div>

        <!-- Sub-panel saat memilih "Ada yang tidak sesuai" -->
        <div
            v-if="state.overall === 'mismatch'"
            class="mt-4 space-y-3 rounded-xl border border-warning-border/60 bg-warning-subtle/15 p-3.5 sm:p-4"
        >
            <div>
                <p class="text-xs font-bold text-content sm:text-sm">
                    {{ checkSpec.finding.mismatchPrompt }}
                </p>
                <p class="mt-0.5 text-xs text-content-secondary">
                    {{ checkSpec.finding.mismatchHint }}
                </p>
            </div>

            <ul class="space-y-2.5">
                <li
                    v-for="measurement in measurements"
                    :key="measurement.id"
                    class="rounded-xl border border-stroke bg-surface p-3"
                >
                    <label class="flex min-h-8 cursor-pointer items-center gap-3 select-none">
                        <input
                            type="checkbox"
                            class="h-5 w-5 rounded border-stroke-strong text-warning focus:ring-warning"
                            :checked="isMismatchSelected(measurement.id)"
                            @change="toggleMismatchItem(measurement.id)"
                        />
                        <div class="flex min-w-0 flex-1 flex-wrap items-baseline justify-between gap-2">
                            <span class="text-sm font-semibold text-content">{{ measurement.name }}</span>
                            <span class="text-xs font-medium text-content-secondary tabular-nums">
                                {{ checkSpec.rabValue(measurement.value, measurement.unit) }}
                            </span>
                        </div>
                    </label>

                    <div v-if="isMismatchSelected(measurement.id)" class="mt-3 border-t border-stroke pt-3">
                        <label
                            :for="`temuan-${measurement.id}`"
                            class="block text-xs font-semibold text-content-secondary"
                        >
                            {{ checkSpec.finding.optionalMeasuredLabel(measurement.unit) }}
                        </label>
                        <div class="mt-1.5 flex items-center gap-2">
                            <input
                                :id="`temuan-${measurement.id}`"
                                :value="state.measuredValues[measurement.id] ?? ''"
                                type="text"
                                inputmode="decimal"
                                autocomplete="off"
                                placeholder="Contoh: 8,5"
                                class="h-11 w-full rounded-xl border border-stroke-strong bg-surface px-3.5 text-base font-semibold text-content tabular-nums focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                                @input="updateMeasuredValue(measurement.id, ($event.target as HTMLInputElement).value)"
                            />
                            <span
                                class="shrink-0 rounded-xl border border-stroke bg-surface-muted px-3 py-2.5 text-sm font-semibold text-content-secondary"
                            >
                                {{ measurement.unit }}
                            </span>
                        </div>
                        <p
                            v-if="isOptionalMeasuredValueInvalid(state.measuredValues[measurement.id])"
                            class="mt-1.5 text-xs font-medium text-warning"
                            role="alert"
                        >
                            {{ checkSpec.finding.measuredInvalidOptional }}
                        </p>
                    </div>
                </li>
            </ul>
        </div>

        <!-- Pesan Error Validasi -->
        <div
            v-if="showErrors && currentIssue"
            class="mt-3 flex items-center gap-1.5 text-xs font-medium text-warning sm:text-sm"
            role="alert"
        >
            <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 3L2 17h16L10 3zm0 5v4m0 2.5h.01" />
            </svg>
            <span v-if="currentIssue === 'missing_overall'">
                {{ checkSpec.finding.missingOverall }}
            </span>
            <span v-else-if="currentIssue === 'missing_mismatch_items'">
                {{ checkSpec.finding.missingMismatchItems }}
            </span>
            <span v-else>
                {{ checkSpec.finding.measuredInvalidOptional }}
            </span>
        </div>
    </fieldset>
</template>
