/** Props of pages/report/Check.vue, from CheckSpecResource. */
export interface CheckSpecMeasurement {
    id: number;
    name: string;
    value: string;
    unit: string;
    how_to: string;
}

export type ReportKind = 'mismatch' | 'match' | 'unmeasurable';

/** Props of pages/report/Submitted.vue, from SubmittedReportResource. */
export interface SubmittedReportPage {
    project: { name: string; village_name: string };
    report: { kind: ReportKind };
    mismatch_count: { current: number; threshold: number };
    urls: { project: string; check: string };
}

export interface CheckSpecPage {
    project: { name: string; village_name: string };
    measurements: CheckSpecMeasurement[];
    limits: { radius_m: number; max_accuracy_m: number; max_photos: number; max_note_length: number };
    urls: { project: string; distance: string; submit: string };
}
