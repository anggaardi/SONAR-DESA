<script setup lang="ts">
// Halaman saat masuk Google tidak selesai (PRD Fitur Inti 5.1 step 4: batal masuk = tidak bisa lanjut).
// "Coba lagi" kembali ke Google; setelah berhasil warga dikembalikan ke checklist yang sama (F11).

import { Head } from '@inertiajs/vue3';

import Alert from '@/components/ui/Alert.vue';
import Button from '@/components/ui/Button.vue';
import { googleSignIn, type GoogleSignInFailure } from '@/lib/labels';

interface Props {
    reason: GoogleSignInFailure;
    retryUrl: string;
}

const props = defineProps<Props>();

const message = googleSignIn.reasons[props.reason];
</script>

<template>
    <div class="flex min-h-screen flex-col bg-surface-subtle text-content antialiased">
        <Head :title="googleSignIn.heading" />

        <main class="mx-auto w-full max-w-md space-y-4 px-4 py-10">
            <h1 class="text-xl font-bold">{{ googleSignIn.heading }}</h1>

            <Alert color="warning" :title="message.title">{{ message.body }}</Alert>

            <p class="text-sm text-content-secondary">{{ googleSignIn.privacy }}</p>

            <div class="flex flex-wrap gap-3">
                <!-- Plain link: the retry URL redirects off-site to Google, so it must be a full page visit. -->
                <a
                    :href="retryUrl"
                    class="inline-flex h-11 items-center justify-center rounded-md bg-primary px-4 text-base font-medium text-content-inverse hover:bg-primary-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                >
                    {{ googleSignIn.retry }}
                </a>
                <Button href="/" variant="secondary">{{ googleSignIn.backHome }}</Button>
            </div>
        </main>
    </div>
</template>
