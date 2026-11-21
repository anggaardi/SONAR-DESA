<script setup lang="ts">
// DemoBanner: shown on every demo screen (PRD Fitur 7.2, M2). Color `demo` (D-006).
// Renders only when the shared `demo` prop is set, so layouts can include it unconditionally.

import { router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { demo as demoLabels } from '@/lib/labels';
import type { Role } from '@/types/inertia';

const page = usePage();
const demo = computed(() => page.props.demo);
const choosingRole = ref(false);

const roles: Role[] = ['warga', 'desa', 'bpd'];

function switchRole(role: Role): void {
    choosingRole.value = false;
    router.post(`/demo/switch/${role}`);
}

function restart(): void {
    router.post('/demo/reset');
}
</script>

<template>
    <div v-if="demo" class="border-b border-demo/15 bg-demo-subtle text-xs font-medium text-demo sm:text-sm">
        <div
            class="mx-auto flex max-w-md items-center justify-between px-4 py-2 sm:max-w-xl md:max-w-full md:px-6 lg:px-8"
        >
            <div class="flex items-center gap-1.5">
                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                    />
                </svg>
                <span>{{ demoLabels.banner }}</span>
                <span class="hidden text-demo/80 sm:inline">&middot; {{ demoLabels.playingAs(demo.role) }}</span>
            </div>

            <div class="flex items-center gap-3">
                <button
                    type="button"
                    class="min-h-8 rounded font-medium underline-offset-2 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-demo"
                    :aria-expanded="choosingRole"
                    aria-controls="demo-role-picker"
                    @click="choosingRole = !choosingRole"
                >
                    {{ demoLabels.switchRole }}
                </button>
                <button
                    type="button"
                    class="hidden min-h-8 rounded font-medium underline-offset-2 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-demo sm:inline"
                    @click="restart"
                >
                    {{ demoLabels.restart }}
                </button>
            </div>
        </div>

        <div
            v-if="choosingRole"
            id="demo-role-picker"
            class="mx-auto flex max-w-md flex-wrap gap-2 px-4 pb-3 sm:max-w-xl md:max-w-2xl"
        >
            <button
                v-for="role in roles"
                :key="role"
                type="button"
                class="h-9 rounded-md border border-demo bg-surface px-3 text-xs text-demo transition-colors hover:bg-demo/10 disabled:opacity-50 sm:text-sm"
                :disabled="role === demo.role"
                @click="switchRole(role)"
            >
                {{ demoLabels.roles[role] }}
            </button>
            <button
                type="button"
                class="h-9 rounded-md px-3 text-xs font-medium text-demo underline underline-offset-2 sm:hidden"
                @click="restart"
            >
                {{ demoLabels.restart }}
            </button>
        </div>
    </div>
</template>
