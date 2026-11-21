/** Props of pages/report/Check.vue, from CheckSpecResource. */
export interface CheckSpecMeasurement {
    id: number;
    name: string;
    value: string;
    unit: string;
    how_to: string;
}

export interface CheckSpecPage {
    project: { name: string; village_name: string };
    measurements: CheckSpecMeasurement[];
    limits: { max_accuracy_m: number; max_photos: number; max_note_length: number };
    urls: { project: string; distance: string };
}
