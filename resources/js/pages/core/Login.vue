<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import loginHero from '@/components/ui/illustrations/login-hero.webp';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const showPassword = ref(false);

function submit(): void {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head title="Masuk" />

    <div class="flex min-h-screen w-full bg-surface font-sans text-content antialiased">
        <div
            class="relative hidden min-h-screen overflow-hidden border-r border-stroke bg-surface-subtle lg:block lg:w-[68%]"
            aria-hidden="true"
        >
            <img
                :src="loginHero"
                alt=""
                width="1023"
                height="767"
                decoding="sync"
                fetchpriority="high"
                class="absolute inset-0 h-full w-full object-cover object-bottom"
            />
        </div>
        <div
            class="flex min-h-screen w-full flex-col justify-between bg-surface px-8 py-12 sm:px-12 lg:w-[32%] xl:px-14"
        >
            <div />

            <div class="mx-auto my-auto flex w-full max-w-[280px] flex-col sm:max-w-[300px]">
                <!-- Logo Slot (Dikosongkan saat ini sesuai instruksi, penempatan dipertahankan) -->
                <div class="mb-8 flex h-14 w-full items-center justify-center" aria-hidden="true"></div>

                <!-- Garis Pemisah Tipis (IDDS stroke) -->
                <div class="mb-6 w-full border-t border-stroke" />

                <!-- Form Login -->
                <form class="flex flex-col" novalidate @submit.prevent="submit">
                    <!-- Email -->
                    <div class="mb-5 flex flex-col">
                        <label for="email" class="mb-1 text-xs font-medium text-content-secondary"> Email </label>
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            autocomplete="username"
                            required
                            placeholder="email"
                            :aria-invalid="form.errors.email ? 'true' : undefined"
                            :aria-describedby="form.errors.email ? 'email-error' : undefined"
                            :class="[
                                'w-full border-0 border-b bg-transparent px-0 py-1.5 text-sm text-content transition-colors placeholder:text-content-tertiary placeholder:italic focus:border-primary focus:ring-0 focus:outline-none',
                                form.errors.email
                                    ? 'border-negative-solid'
                                    : 'border-stroke hover:border-stroke-strong',
                            ]"
                        />
                        <p v-if="form.errors.email" id="email-error" class="mt-1 text-xs text-negative">
                            {{ form.errors.email }}
                        </p>
                    </div>

                    <!-- Password -->
                    <div class="mb-4 flex flex-col">
                        <label for="password" class="mb-1 text-xs font-medium text-content-secondary"> Password </label>
                        <input
                            id="password"
                            v-model="form.password"
                            :type="showPassword ? 'text' : 'password'"
                            autocomplete="current-password"
                            required
                            placeholder="password"
                            :aria-invalid="form.errors.password ? 'true' : undefined"
                            :aria-describedby="form.errors.password ? 'password-error' : undefined"
                            :class="[
                                'w-full border-0 border-b bg-transparent px-0 py-1.5 text-sm text-content transition-colors placeholder:text-content-tertiary placeholder:italic focus:border-primary focus:ring-0 focus:outline-none',
                                form.errors.password
                                    ? 'border-negative-solid'
                                    : 'border-stroke hover:border-stroke-strong',
                            ]"
                        />
                        <p v-if="form.errors.password" id="password-error" class="mt-1 text-xs text-negative">
                            {{ form.errors.password }}
                        </p>
                    </div>

                    <!-- Row: Show Password -->
                    <div class="mt-1 mb-8 flex items-center text-xs">
                        <label
                            class="flex cursor-pointer items-center gap-1.5 text-content-secondary select-none hover:text-content"
                        >
                            <input
                                v-model="showPassword"
                                type="checkbox"
                                class="h-3.5 w-3.5 cursor-pointer rounded-sm border-stroke-strong accent-primary"
                            />
                            <span class="text-xs font-normal">Show Password</span>
                        </label>
                    </div>

                    <!-- Tombol Login (IDDS Primary Button: bg-primary, rounded-md, centered) -->
                    <div class="flex justify-center">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="inline-flex items-center justify-center rounded-md bg-primary px-8 py-2 text-sm font-medium text-content-inverse shadow-xs transition-colors hover:bg-primary-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <span v-if="form.processing">Masuk...</span>
                            <span v-else>Login</span>
                        </button>
                    </div>
                </form>

                <!-- Teks Kebijakan & Ketentuan (IDDS: text-content-tertiary & text-primary) -->
                <div class="mt-6 text-center text-xs leading-relaxed text-content-tertiary">
                    By <span class="font-medium text-primary">logging in</span> and
                    <span class="font-medium text-primary">signing up</span>, you agree to our<br />
                    <span class="font-medium text-primary">Privacy Policy</span> and
                    <span class="font-medium text-primary">Terms & Conditions</span>
                </div>
            </div>

            <!-- Copyright Bawah (IDDS text-content-tertiary) -->
            <div class="text-center text-xs text-content-tertiary">Copyright &copy; Sonar Desa 2026</div>
        </div>
    </div>
</template>
