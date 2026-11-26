export interface ProjectVillage {
    name: string;
    slug: string;
    regency: string;
}

export interface ProjectMeasurementItem {
    id: number;
    name: string;
    value: string;
    unit: string;
    how_to: string;
    origin: string;
}

export interface ProjectBudgetItem {
    id: number;
    position: number;
    name: string;
    volume: string | null;
    unit: string | null;
    unit_price_rp: number | null;
    amount_rp: number | null;
    standard_price_rp: number | null;
    unit_diff_rp: number | null;
    total_diff_rp: number | null;
    over_pct: number | null;
    label: string;
    standard_source: string | null;
    explanation: string | null;
}

export interface ProjectProgressPhotoItem {
    id: number;
    url: string;
    progress_pct: number;
    taken_at: string;
}

export interface ProjectChangeItem {
    id: number;
    item_label: string;
    field: string;
    old_value: string;
    new_value: string;
    reason?: string | null;
    changed_at: string;
}

export interface ProjectPublishedReportAnswer {
    id: number;
    measurement_name?: string;
    target_value?: string | null;
    target_unit?: string | null;
    answer: string;
    measured_value: string | null;
    measured_unit: string | null;
}

export interface ProjectPublishedReportPhoto {
    id: number;
    position: number;
    url: string;
}

export interface ProjectPublishedReport {
    id: number;
    reporter_label: string;
    kind: 'match' | 'mismatch' | 'unmeasurable';
    note: string | null;
    created_at: string;
    photos?: ProjectPublishedReportPhoto[];
    answers: ProjectPublishedReportAnswer[];
}

export interface ProjectDetail {
    id: number;
    slug: string;
    name: string;
    type: string;
    description?: string | null;
    village: ProjectVillage;
    fund_source: string;
    year: number;
    total_amount_rp: number;
    progress_pct: number;
    last_photo_date: string | null;
    public_status: string;
    in_review_since: string | null;
    review: {
        outcome: string | null;
        note: string | null;
        reviewed_at: string | null;
        notes: Array<{ note: string; created_at: string }>;
    } | null;
    published_reports?: ProjectPublishedReport[];
    published_report_counts: Record<ProjectPublishedReport['kind'], number>;
    label_counts: {
        within_standard: number;
        above_standard: number;
        no_reference: number;
    };
    citizen_reports_count: number;
    has_citizen_reports: boolean;
    measurements: ProjectMeasurementItem[];
    items: ProjectBudgetItem[];
    progress_photos: ProjectProgressPhotoItem[];
    changes: ProjectChangeItem[];
    document_url: string | null;
}

export interface VillageProjectSummary {
    id: number;
    slug: string;
    name: string;
    type: string;
    fund_source: string;
    year: number;
    total_amount_rp: number;
    progress_pct: number;
    public_status: string;
}
