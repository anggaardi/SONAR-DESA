// Dummy field photos for the review sheet. Placeholder until citizen report photos are published
// through BPD (opsi 2); then these come from the project props instead.

import type { ProjectPublishedReport } from '@/types/project';

export type ReviewPhotoScene = 'night' | 'crack' | 'gate' | 'solar' | 'monument' | 'bpd';

export interface ReviewPhoto {
    scene: ReviewPhotoScene;
    title: string;
    subtitle: string;
    /** Short caption shown on the grid tile. */
    tag?: string;
    /** Photos not shown in the grid; rendered as "+N" on the last tile. */
    moreCount?: number;
}

export const bpdReviewPhotos: ReviewPhoto[] = [
    {
        scene: 'solar',
        title: 'Uji Petik Fisik 20 Titik Tiang PJU',
        subtitle: 'Pemeriksaan fisik tiang oktagonal 6 meter & baterai LiFePO4',
        tag: 'Uji Tiang 6M',
    },
    {
        scene: 'bpd',
        title: 'Berita Acara Pengawasan Lapangan BPD',
        subtitle: 'Pemeriksaan fisik bersama perwakilan warga',
        tag: 'Berita Acara',
    },
    {
        scene: 'gate',
        title: 'Jalur Pemasangan PJU Tenaga Surya Desa',
        subtitle: 'Peninjauan jalur penerangan dari Dusun Kaja ke Kangin',
    },
    {
        scene: 'night',
        title: 'Uji Coba Nyala Lampu Malam Hari',
        subtitle: 'Baterai dan modul surya berfungsi normal',
        moreCount: 4,
    },
];

const mismatchPhotos: ReviewPhoto[] = [
    {
        scene: 'night',
        title: 'Tiang #14 Padam Saat Malam',
        subtitle: 'Lampu jalan tenaga surya tidak menyala pada malam hari',
        tag: 'Tiang #14',
    },
    {
        scene: 'crack',
        title: 'Pondasi Retak Baut Angkur 2cm',
        subtitle: 'Pemeriksaan fisik menunjukkan retakan pada sambungan baut',
    },
    {
        scene: 'solar',
        title: 'Inspeksi Fisik Tiang #14',
        subtitle: 'Pengukuran tiang oktagonal 6M di Dusun Kangin',
    },
    {
        scene: 'gate',
        title: 'Lokasi Jalan Dusun Kangin',
        subtitle: 'Dokumentasi jalan lingkungan desa',
        moreCount: 2,
    },
];

const matchPhotos: ReviewPhoto[] = [
    { scene: 'gate', title: 'Gapura Lingkungan Desa', subtitle: 'Penerangan jalan tertata rapi di jalur utama' },
    {
        scene: 'monument',
        title: 'Monumen Seni Lingkungan',
        subtitle: 'Pemandangan taman dan fasilitas umum desa',
        tag: 'Monumen',
    },
    {
        scene: 'solar',
        title: 'Tiang PJU Solar LED 60W',
        subtitle: 'Tiang tegak 6 meter dan panel surya terpasang kokoh',
    },
    {
        scene: 'night',
        title: 'Penerangan Terang Malam Hari',
        subtitle: 'Aktivitas warga dan anak-anak sekolah',
        moreCount: 6,
    },
];

const unmeasurablePhotos: ReviewPhoto[] = [
    { scene: 'solar', title: 'Tiang Penerangan Jalan', subtitle: 'Tinjauan lapangan Dusun Kelod' },
    { scene: 'night', title: 'Penerangan Lampu Jalan', subtitle: 'Membantu patroli ronda malam desa' },
];

export function citizenReviewPhotos(kind: ProjectPublishedReport['kind']): ReviewPhoto[] {
    if (kind === 'mismatch') {
        return mismatchPhotos;
    }
    if (kind === 'match') {
        return matchPhotos;
    }
    return unmeasurablePhotos;
}
