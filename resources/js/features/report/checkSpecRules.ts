// Aturan isian checklist Cek Spek di layar (jalan tengah: rekomendasi opsional + satu pertanyaan penutup).
// Server memvalidasi ulang semuanya saat laporan dikirim (SubmitReportRequest, SubmitReport).

export type MeasurementAnswer = 'match' | 'mismatch' | 'unmeasurable';
export type OverallFinding = 'match' | 'mismatch' | 'unmeasurable';

export interface ChecklistAnswer {
    measurement_id: number;
    answer: MeasurementAnswer | null;
    measured_value: string;
}

export interface ChecklistState {
    /** Optional recommendation checkboxes ticked by the citizen while checking on site. */
    checkedIds: number[];
    /** Single overall finding for the whole report ("Apa yang kamu temukan?"). */
    overall: OverallFinding | null;
    /** Measurement IDs selected as mismatched when overall === 'mismatch'. */
    mismatchIds: number[];
    /** Optional field measurement value per measurement ID when overall === 'mismatch'. */
    measuredValues: Record<number, string>;
}

export type AnswerIssue = 'unanswered' | 'measured_value_invalid';
export type FindingIssue = 'missing_overall' | 'missing_mismatch_items' | 'measured_value_invalid';

export function emptyAnswers(measurementIds: number[]): ChecklistAnswer[] {
    return measurementIds.map((id) => ({ measurement_id: id, answer: null, measured_value: '' }));
}

export function emptyChecklistState(): ChecklistState {
    return {
        checkedIds: [],
        overall: null,
        mismatchIds: [],
        measuredValues: {},
    };
}

/** Accepts "8", "8.5" and "8,5" (Indonesian decimal comma); returns null unless it is a number above 0. */
export function parseMeasuredValue(raw: string): number | null {
    const trimmed = raw.trim();

    if (!/^\d+([.,]\d+)?$/.test(trimmed)) {
        return null;
    }

    const value = Number(trimmed.replace(',', '.'));

    return value > 0 ? value : null;
}

export function answerIssue(answer: ChecklistAnswer): AnswerIssue | null {
    if (answer.answer === null) {
        return 'unanswered';
    }

    if (
        answer.answer === 'mismatch' &&
        answer.measured_value.trim() !== '' &&
        parseMeasuredValue(answer.measured_value) === null
    ) {
        return 'measured_value_invalid';
    }

    return null;
}

export function isChecklistComplete(answers: ChecklistAnswer[]): boolean {
    return answers.length > 0 && answers.every((answer) => answerIssue(answer) === null);
}

export function isOptionalMeasuredValueInvalid(raw: string | undefined): boolean {
    const trimmed = (raw ?? '').trim();

    return trimmed !== '' && parseMeasuredValue(trimmed) === null;
}

export function findingIssue(state: ChecklistState): FindingIssue | null {
    if (state.overall === null) {
        return 'missing_overall';
    }

    if (state.overall === 'mismatch') {
        if (state.mismatchIds.length === 0) {
            return 'missing_mismatch_items';
        }

        if (state.mismatchIds.some((id) => isOptionalMeasuredValueInvalid(state.measuredValues[id]))) {
            return 'measured_value_invalid';
        }
    }

    return null;
}

export function isFindingComplete(state: ChecklistState, measurementCount: number): boolean {
    return measurementCount > 0 && findingIssue(state) === null;
}

/** Maps the lightweight checklist + single overall finding into server-expected per-measurement answers. */
export function buildSubmissionAnswers(measurementIds: number[], state: ChecklistState): ChecklistAnswer[] {
    if (state.overall === 'match') {
        return measurementIds.map((id) => ({
            measurement_id: id,
            answer: 'match',
            measured_value: '',
        }));
    }

    if (state.overall === 'mismatch') {
        const mismatchSet = new Set(state.mismatchIds);

        return measurementIds.map((id) => ({
            measurement_id: id,
            answer: mismatchSet.has(id) ? 'mismatch' : 'unmeasurable',
            measured_value: mismatchSet.has(id) ? (state.measuredValues[id] ?? '').trim() : '',
        }));
    }

    return measurementIds.map((id) => ({
        measurement_id: id,
        answer: 'unmeasurable',
        measured_value: '',
    }));
}
