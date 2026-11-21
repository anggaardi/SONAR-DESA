// Aturan isian checklist Cek Spek di layar (PRD Fitur Inti 5.1 step 5, C5). Hanya untuk membantu warga mengisi;
// server memvalidasi ulang semuanya saat laporan dikirim (M4-06). Nilai jawaban sama dengan enum MeasurementAnswer.

export type MeasurementAnswer = 'match' | 'mismatch' | 'unmeasurable';

export interface ChecklistAnswer {
    measurement_id: number;
    answer: MeasurementAnswer | null;
    measured_value: string;
}

export type AnswerIssue = 'unanswered' | 'measured_value_invalid';

export function emptyAnswers(measurementIds: number[]): ChecklistAnswer[] {
    return measurementIds.map((id) => ({ measurement_id: id, answer: null, measured_value: '' }));
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

    // C5: "Tidak" needs the measured value, in the same unit as the RAB.
    if (answer.answer === 'mismatch' && parseMeasuredValue(answer.measured_value) === null) {
        return 'measured_value_invalid';
    }

    return null;
}

export function isChecklistComplete(answers: ChecklistAnswer[]): boolean {
    return answers.length > 0 && answers.every((answer) => answerIssue(answer) === null);
}
