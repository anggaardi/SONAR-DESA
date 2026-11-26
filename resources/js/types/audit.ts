export type PriceLabelType = 'within_standard' | 'above_standard' | 'needs_check' | 'no_reference';

export interface PriceStandardItem {
    id: number;
    code: string;
    name: string;
    unit: string;
    price_rp: number;
}

export interface AuditItemData {
    id: number;
    position: number;
    raw_name: string;
    volume: string | null;
    unit: string | null;
    unit_price_rp: number | null;
    amount_rp: number | null;
    standard_id: number | null;
    standard: PriceStandardItem | null;
    label: PriceLabelType;
    over_pct: number | null;
    diff_rp: number | null;
    corrected: boolean;
    is_yellow: boolean;
    reason_text: string | null;
    doubt_fields?: string[];
}

export interface AuditSummary {
    total_items: number;
    needs_check_count: number;
    above_standard_count: number;
    within_standard_count: number;
    no_reference_count: number;
    total_calculated_rp: number;
    is_total_match: boolean;
    total_diff_rp: number;
    can_publish: boolean;
    publish_block_reason?: string | null;
}

export type AuditItemFormPayload = {
    raw_name: string;
    volume: string;
    unit: string;
    unit_price_rp: number;
    amount_rp: number | null;
    standard_id: number | null;
};
