import { describe, expect, it } from 'vitest';

import {
    answerIssue,
    emptyAnswers,
    isChecklistComplete,
    parseMeasuredValue,
    type ChecklistAnswer,
} from './checkSpecRules';

const answer = (overrides: Partial<ChecklistAnswer> = {}): ChecklistAnswer => ({
    measurement_id: 1,
    answer: 'match',
    measured_value: '',
    ...overrides,
});

describe('parseMeasuredValue', () => {
    it.each([
        ['8', 8],
        ['8.5', 8.5],
        ['8,5', 8.5],
        [' 12 ', 12],
        ['0.1', 0.1],
    ])('reads %j as %d', (raw, expected) => {
        expect(parseMeasuredValue(raw)).toBe(expected);
    });

    it.each(['', '0', '0,0', '-3', 'abc', '8 cm', '1.000,5', '8.'])('rejects %j', (raw) => {
        expect(parseMeasuredValue(raw)).toBeNull();
    });
});

describe('answerIssue', () => {
    it('asks for an answer when none is chosen', () => {
        expect(answerIssue(answer({ answer: null }))).toBe('unanswered');
    });

    it('requires a measured value for Tidak (C5)', () => {
        expect(answerIssue(answer({ answer: 'mismatch', measured_value: '' }))).toBe('measured_value_invalid');
        expect(answerIssue(answer({ answer: 'mismatch', measured_value: '0' }))).toBe('measured_value_invalid');
        expect(answerIssue(answer({ answer: 'mismatch', measured_value: '8' }))).toBeNull();
    });

    it.each(['match', 'unmeasurable'] as const)('needs no measured value for %s', (choice) => {
        expect(answerIssue(answer({ answer: choice, measured_value: '' }))).toBeNull();
    });
});

describe('isChecklistComplete', () => {
    it('is complete only when every measurement has a valid answer', () => {
        expect(isChecklistComplete([answer(), answer({ measurement_id: 2, answer: 'unmeasurable' })])).toBe(true);
        expect(isChecklistComplete([answer(), answer({ measurement_id: 2, answer: null })])).toBe(false);
        expect(isChecklistComplete([answer({ answer: 'mismatch', measured_value: '' })])).toBe(false);
    });

    it('is never complete without measurements', () => {
        expect(isChecklistComplete([])).toBe(false);
    });
});

describe('emptyAnswers', () => {
    it('starts every measurement unanswered', () => {
        expect(emptyAnswers([4, 7])).toEqual([
            { measurement_id: 4, answer: null, measured_value: '' },
            { measurement_id: 7, answer: null, measured_value: '' },
        ]);
    });
});
