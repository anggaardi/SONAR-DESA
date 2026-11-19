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
    <div v-if="demo" class="bg-demo-subtle text-sm font-medium text-demo">
        <div class="flex flex-wrap items-center justify-between gap-x-6 gap-y-1 px-4 py-2">
            <span>{{ demoLabels.banner }} &middot; {{ demoLabels.playingAs(demo.role) }}</span>

            <div class="flex gap-4">
                <button
                    type="button"
                    class="min-h-11 underline-offset-2 hover:underline"
                    :aria-expanded="choosingRole"
                    aria-controls="demo-role-picker"
                    @click="choosingRole = !choosingRole"
                >
                    {{ demoLabels.switchRole }}
                </button>
                <button type="button" class="min-h-11 underline-offset-2 hover:underline" @click="restart">
                    {{ demoLabels.restart }}
                </button>
            </div>
        </div>

        <div v-if="choosingRole" id="demo-role-picker" class="flex flex-wrap gap-2 px-4 pb-3">
            <button
                v-for="role in roles"
                :key="role"
                type="button"
                class="h-11 rounded-md border border-demo bg-surface px-3 text-demo disabled:opacity-50"
                :disabled="role === demo.role"
                @click="switchRole(role)"
            >
                {{ demoLabels.roles[role] }}
            </button>
        </div>
    </div>
</template>
