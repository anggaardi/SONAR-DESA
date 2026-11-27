/** Props of pages/report/Check.vue, from CheckSpecResource. */
export interface CheckSpecMeasurement {
    id: number;
    name: string;
    value: string;
    unit: string;
    how_to: string;
}

export type ReportKind = 'mismatch' | 'match' | 'unmeasurable';

export type CitizenReportStatus = 'active' | 'superseded' | 'not_counted';

/** Props of pages/report/Submitted.vue, from SubmittedReportResource. */
export interface SubmittedReportPage {
    project: { name: string; village_name: string };
    report: { kind: ReportKind };
    mismatch_count: { current: number; threshold: number };
    urls: { project: string; check: string; my_reports?: string };
}

export interface MyReportBpdNote {
    note: string;
    created_at: string | null;
}

export interface MyReportBpdReview {
    status: 'none' | 'in_review' | 'reviewed';
    in_review_since: string | null;
    outcome: 'followed_up' | 'no_mismatch' | null;
    note: string | null;
    reviewed_at: string | null;
    notes: MyReportBpdNote[];
    submitted_after_review: boolean;
}

export interface MyReportItem {
    id: number;
    created_at: string | null;
    kind: ReportKind;
    status: CitizenReportStatus;
    note: string | null;
    project: {
        name: string;
        slug: string;
        village_name: string;
        village_slug: string;
        year: number;
    };
    mismatch_count: {
        current: number;
        threshold: number;
    };
    bpd_review: MyReportBpdReview;
    urls: {
        project: string;
        check: string;
    };
}

export interface MyReportsPageProps {
    state: 'guest' | 'authenticated';
    session: {
        auth_type: 'google' | 'demo';
        display_name: string;
        email: string | null;
        village_name: string | null;
        village_slug: string | null;
    } | null;
    summary: {
        total_count: number;
        active_count: number;
        reviewed_or_in_review_count: number;
    };
    reports: MyReportItem[];
    suggested_project: {
        name: string;
        slug: string;
        village_name: string;
        urls: {
            project: string;
            check: string;
        };
    } | null;
    urls: {
        google_login: string;
        demo: string;
        home: string;
        logout: string;
    };
}

export interface CheckSpecPage {
    project: { name: string; slug: string; village_name: string };
    measurements: CheckSpecMeasurement[];
    limits: { radius_m: number; max_accuracy_m: number; max_photos: number; max_note_length: number };
    urls: { project: string; distance: string; submit: string };
}
