<script setup lang="ts">
// ProjectCheckSpecInvite: ajakan Cek Spek, satu-satunya ilustrasi di halaman rincian proyek.
// Ilustrasi IDDS (figur resmi + gelembung meteran) dibuat oleh design-refs/idds-illustration/to-app.mjs.

import checkSpec from '@/components/ui/illustrations/check-spec.svg';

interface Props {
    citizenCount: number;
}

defineProps<Props>();
</script>

<template>
    <section aria-labelledby="ajak-cek-spek" class="rounded-3xl bg-primary-subtle p-5 sm:p-6">
        <img
            :src="checkSpec"
            alt=""
            width="280"
            height="210"
            loading="lazy"
            class="mx-auto aspect-4/3 w-full max-w-72"
        />

        <h2 id="ajak-cek-spek" class="mt-4 text-xl font-bold text-content">Lewat lokasi proyek ini?</h2>
        <p class="mt-1.5 text-base leading-relaxed text-content-secondary">
            Ukur panjang dan lebarnya dengan meteran, ambil foto, lalu bandingkan dengan RAB. Cukup sekitar 5 menit.
        </p>

        <div class="mt-4 flex items-center gap-3">
            <span
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-surface text-primary"
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
            <p v-if="citizenCount > 0" class="text-sm text-content-secondary">
                <span class="block text-base font-semibold text-content tabular-nums"
                    >{{ citizenCount }} warga sudah cek</span
                >
                di lokasi proyek ini
            </p>
            <p v-else class="text-sm text-content-secondary">
                <span class="block text-base font-semibold text-content">Belum ada warga yang cek</span>
                Kamu bisa jadi yang pertama
            </p>
        </div>

        <a
            href="#ukuran"
            class="mt-3 inline-flex min-h-11 items-center gap-1 text-base font-semibold text-primary hover:text-primary-hover"
        >
            Lihat ukuran yang dicocokkan
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 6l6 6-6 6" />
            </svg>
        </a>
    </section>
</template>
