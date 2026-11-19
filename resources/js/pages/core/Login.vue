<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

function submit(): void {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <PublicLayout>
        <Head title="Masuk" />

        <div class="flex flex-col gap-6">
            <div class="flex flex-col gap-1">
                <p class="text-sm text-content-secondary">Sonar Desa</p>
                <h1 class="text-2xl font-semibold text-content">Masuk sebagai Pemerintah Desa atau BPD</h1>
                <p class="text-base text-content-secondary">
                    Akun dibuat oleh tim Sonar Desa. Warga tidak perlu masuk untuk melihat proyek.
                </p>
            </div>

            <Card>
                <form class="flex flex-col gap-4" novalidate @submit.prevent="submit">
                    <div class="flex flex-col gap-1.5">
                        <label for="email" class="text-sm font-medium text-content">Email</label>
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            autocomplete="username"
                            required
                            :aria-invalid="form.errors.email ? 'true' : undefined"
                            :aria-describedby="form.errors.email ? 'email-error' : undefined"
                            :class="[
                                'h-11 w-full rounded-md border bg-surface px-3 text-base text-content',
                                form.errors.email ? 'border-negative-solid' : 'border-stroke-strong',
                            ]"
                        />
                        <p v-if="form.errors.email" id="email-error" class="text-sm text-negative">
                            {{ form.errors.email }}
                        </p>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label for="password" class="text-sm font-medium text-content">Kata sandi</label>
                        <input
                            id="password"
                            v-model="form.password"
                            type="password"
                            autocomplete="current-password"
                            required
                            :aria-invalid="form.errors.password ? 'true' : undefined"
                            :aria-describedby="form.errors.password ? 'password-error' : undefined"
                            :class="[
                                'h-11 w-full rounded-md border bg-surface px-3 text-base text-content',
                                form.errors.password ? 'border-negative-solid' : 'border-stroke-strong',
                            ]"
                        />
                        <p v-if="form.errors.password" id="password-error" class="text-sm text-negative">
                            {{ form.errors.password }}
                        </p>
                    </div>

                    <label class="flex min-h-11 items-center gap-3 text-base text-content">
                        <input
                            v-model="form.remember"
                            type="checkbox"
                            class="h-5 w-5 rounded-sm border-stroke-strong accent-primary"
                        />
                        <span>Ingat saya di perangkat ini</span>
                    </label>

                    <Button type="submit" variant="primary" :loading="form.processing">Masuk</Button>
                </form>
            </Card>
        </div>
    </PublicLayout>
</template>
