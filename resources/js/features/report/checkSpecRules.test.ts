import { describe, expect, it } from 'vitest';

import {
    answerIssue,
    buildSubmissionAnswers,
    emptyAnswers,
    emptyChecklistState,
    findingIssue,
    isChecklistComplete,
    isFindingComplete,
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

    it('treats measured value as optional for mismatch but rejects invalid numbers when provided', () => {
        expect(answerIssue(answer({ answer: 'mismatch', measured_value: '' }))).toBeNull();
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
        expect(isChecklistComplete([answer({ answer: 'mismatch', measured_value: '0' })])).toBe(false);
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

describe('findingIssue and isFindingComplete', () => {
    it('requires choosing an overall finding', () => {
        const state = emptyChecklistState();
        expect(findingIssue(state)).toBe('missing_overall');
        expect(isFindingComplete(state, 2)).toBe(false);
    });

    it('accepts match or unmeasurable even when recommendation checkboxes are untouched', () => {
        expect(isFindingComplete({ ...emptyChecklistState(), overall: 'match' }, 2)).toBe(true);
        expect(isFindingComplete({ ...emptyChecklistState(), overall: 'unmeasurable' }, 2)).toBe(true);
    });

    it('requires at least one mismatch item when overall is mismatch, with optional measured values', () => {
        const noItems = { ...emptyChecklistState(), overall: 'mismatch' as const };
        expect(findingIssue(noItems)).toBe('missing_mismatch_items');
        expect(isFindingComplete(noItems, 2)).toBe(false);

        const optionalEmpty = { ...noItems, mismatchIds: [1] };
        expect(findingIssue(optionalEmpty)).toBeNull();
        expect(isFindingComplete(optionalEmpty, 2)).toBe(true);

        const invalidNumber = { ...noItems, mismatchIds: [1], measuredValues: { 1: '0' } };
        expect(findingIssue(invalidNumber)).toBe('measured_value_invalid');

        const validNumber = { ...noItems, mismatchIds: [1], measuredValues: { 1: '8,5' } };
        expect(findingIssue(validNumber)).toBeNull();
    });

    it('is never complete without project measurements', () => {
        expect(isFindingComplete({ ...emptyChecklistState(), overall: 'match' }, 0)).toBe(false);
    });
});

describe('buildSubmissionAnswers', () => {
    it('maps overall match to match for all measurements', () => {
        expect(buildSubmissionAnswers([10, 20], { ...emptyChecklistState(), overall: 'match' })).toEqual([
            { measurement_id: 10, answer: 'match', measured_value: '' },
            { measurement_id: 20, answer: 'match', measured_value: '' },
        ]);
    });

    it('maps overall unmeasurable to unmeasurable for all measurements even if recommendations were checked', () => {
        expect(
            buildSubmissionAnswers([10, 20], {
                ...emptyChecklistState(),
                checkedIds: [10],
                overall: 'unmeasurable',
            }),
        ).toEqual([
            { measurement_id: 10, answer: 'unmeasurable', measured_value: '' },
            { measurement_id: 20, answer: 'unmeasurable', measured_value: '' },
        ]);
    });

    it('maps selected mismatch items to mismatch with optional measured value and others to unmeasurable', () => {
        expect(
            buildSubmissionAnswers([10, 20, 30], {
                checkedIds: [10, 20],
                overall: 'mismatch',
                mismatchIds: [20, 30],
                measuredValues: { 20: ' 8,5 ', 10: '99' },
            }),
        ).toEqual([
            { measurement_id: 10, answer: 'unmeasurable', measured_value: '' },
            { measurement_id: 20, answer: 'mismatch', measured_value: '8,5' },
            { measurement_id: 30, answer: 'mismatch', measured_value: '' },
        ]);
    });
});
