<script setup lang="ts">
// ProjectCheckSpecInvite: ajakan Cek Spek, satu-satunya ilustrasi di halaman rincian proyek.
// Ilustrasi IDDS (figur resmi + gelembung meteran) dibuat oleh design-refs/idds-illustration/to-app.mjs.

import checkSpec from '@/components/ui/illustrations/check-spec.svg';

interface Props {
    citizenCount: number;
}

defineProps<Props>();

defineEmits<{
    checkSpec: [];
}>();
</script>

<template>
    <section aria-labelledby="ajak-cek-spek" class="rounded-3xl border border-info-border bg-primary-subtle p-5 shadow-xs sm:p-6">
        <img
            :src="checkSpec"
            alt=""
            width="280"
            height="210"
            loading="lazy"
            class="mx-auto aspect-4/3 w-full max-w-72"
        />

        <h2 id="ajak-cek-spek" class="mt-4 text-xl font-bold text-content">Lewat lokasi proyek ini?</h2>
        <p class="mt-1.5 text-sm leading-relaxed text-content-secondary sm:text-base">
            Ukur panjang dan lebarnya dengan meteran, ambil foto, lalu bandingkan dengan RAB. Cukup sekitar 5 menit untuk ikut menjaga dana desa.
        </p>

        <!-- Status verifikasi lokasi -->
        <div class="mt-3.5 inline-flex items-center gap-2 rounded-lg border border-positive-border bg-positive-subtle px-3 py-1.5 text-xs font-semibold text-positive">
            <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            <span>Verifikasi GPS aktif (Dalam radius 300 m)</span>
        </div>

        <!-- Tombol Aksi Utama -->
        <button
            type="button"
            class="mt-4 inline-flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 text-base font-semibold text-content-inverse shadow-xs transition-colors hover:bg-primary-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary active:bg-primary-hover"
            @click="$emit('checkSpec')"
        >
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M3.5 15.5 15.5 3.5l5 5-12 12zM7.5 11.5l2 2M10.5 8.5l2 2M13.5 5.5l2 2"
                />
            </svg>
            Buka Cek Spek
        </button>

        <div class="mt-4 flex items-center gap-3">
            <span
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-surface text-primary shadow-xs"
                aria-hidden="true"
            >
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M9 4.5a3.5 3.5 0 100 7 3.5 3.5 0 000-7zM3 20c0-3.3 2.7-6 6-6s6 2.7 6 6M16 4.5a3.5 3.5 0 010 7M18 14.5c1.8.8 3 2.8 3 5.5"
                    />
                </svg>
            </span>
            <p v-if="citizenCount > 0" class="text-xs text-content-secondary sm:text-sm">
                <span class="block font-semibold text-content tabular-nums"
                    >{{ citizenCount }} warga sudah cek</span
                >
                di lokasi proyek ini
            </p>
            <p v-else class="text-xs text-content-secondary sm:text-sm">
                <span class="block font-semibold text-content">Belum ada warga yang cek</span>
                Kamu bisa jadi yang pertama
            </p>
        </div>

        <a
            href="#ukuran"
            class="mt-3 inline-flex min-h-9 items-center gap-1 text-sm font-semibold text-primary hover:text-primary-hover"
        >
            Lihat ukuran yang dicocokkan
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 6l6 6-6 6" />
            </svg>
        </a>
    </section>
</template>
