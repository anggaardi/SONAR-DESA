<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Dialog from '@/components/ui/Dialog.vue';

interface Props {
    open: boolean;
    villageName: string;
    villageSlug: string;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    close: [];
    share: [];
    copied: [message: string];
}>();

const refreshing = ref(false);

const formattedVillageName = computed(() =>
    props.villageName.startsWith('Desa ') ? props.villageName : `Desa ${props.villageName}`,
);

const villageUrl = computed(() => {
    if (typeof window === 'undefined') {
        return `/d/${props.villageSlug}`;
    }
    return `${window.location.origin}/d/${props.villageSlug}`;
});

async function copyVillageUrl(): Promise<void> {
    if (typeof navigator === 'undefined' || !navigator.clipboard) {
        return;
    }
    try {
        await navigator.clipboard.writeText(villageUrl.value);
        emit('copied', 'Tautan desa berhasil disalin!');
    } catch {
        emit('copied', 'Gagal menyalin tautan');
    }
}

function checkForUpdates(): void {
    refreshing.value = true;
    router.reload({
        only: ['projects', 'citizenReportsCount', 'plangs'],
        onSuccess: () => {
            emit('copied', 'Daftar proyek desa sudah diperbarui');
            emit('close');
        },
        onFinish: () => {
            refreshing.value = false;
        },
    });
}
</script>

<template>
    <Dialog :open="open" title="Ikuti Kabar Desa" @close="emit('close')">
        <div class="space-y-4 text-sm text-content-secondary">
            <p>
                Pemerintah {{ formattedVillageName }} menerbitkan proyek baru setelah RAB selesai diverifikasi dan
                disetujui BPD.
            </p>
            <p class="text-xs leading-relaxed text-content-tertiary">
                Untuk menjaga privasi warga, Sonar Desa tidak meminta nomor HP atau email langganan. Simpan tautan
                halaman desa ini atau muat ulang daftar proyek di bawah untuk memeriksa penerbitan terbaru.
            </p>

            <div class="space-y-1.5">
                <label for="follow-village-url" class="block text-xs font-semibold text-content">
                    Tautan halaman {{ formattedVillageName }}
                </label>
                <div class="flex gap-2">
                    <input
                        id="follow-village-url"
                        type="text"
                        readonly
                        :value="villageUrl"
                        class="h-10 min-w-0 flex-1 rounded-md border border-stroke-strong bg-surface-subtle px-3 text-xs text-content tabular-nums focus:border-primary focus:outline-none"
                        @focus="($event.target as HTMLInputElement).select()"
                    />
                    <button
                        type="button"
                        class="inline-flex h-10 shrink-0 items-center justify-center rounded-md border border-stroke-strong bg-surface px-3 text-xs font-medium text-content transition-colors hover:bg-surface-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                        @click="copyVillageUrl"
                    >
                        Salin
                    </button>
                </div>
            </div>
        </div>

        <template #actions>
            <div class="flex w-full flex-col gap-2 sm:flex-row sm:justify-end">
                <button
                    type="button"
                    class="inline-flex h-10 items-center justify-center rounded-md border border-stroke-strong bg-surface px-4 text-sm font-medium text-content transition-colors hover:bg-surface-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                    @click="emit('share')"
                >
                    Bagikan tautan
                </button>
                <button
                    type="button"
                    :disabled="refreshing"
                    class="inline-flex h-10 items-center justify-center gap-2 rounded-md bg-primary px-4 text-sm font-medium text-content-inverse transition-colors hover:bg-primary-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary disabled:opacity-60"
                    @click="checkForUpdates"
                >
                    {{ refreshing ? 'Memeriksa...' : 'Periksa pembaruan sekarang' }}
                </button>
            </div>
        </template>
    </Dialog>
</template>
