<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import Dialog from '@/components/ui/Dialog.vue';
import EmptyState from '@/components/ui/EmptyState.vue';

export interface ProjectMeasurementItem {
    id: number;
    name: string;
    unit: string;
    value: number | string;
    how_to: string | null;
}

interface Props {
    projectId: number;
    measurements: ProjectMeasurementItem[];
    isFrozen: boolean;
}

const props = defineProps<Props>();

const editingMeasurement = ref<ProjectMeasurementItem | null>(null);
const measurementDialog = ref(false);

const measurementForm = useForm({
    value: '',
    how_to: '',
    reason: '',
});

function openMeasurementChange(m: ProjectMeasurementItem): void {
    if (props.isFrozen) {
        return;
    }
    editingMeasurement.value = m;
    measurementForm.value = String(m.value);
    measurementForm.how_to = m.how_to ?? '';
    measurementForm.reason = '';
    measurementDialog.value = true;
}

function submitMeasurementChange(): void {
    if (!editingMeasurement.value) {
        return;
    }
    measurementForm.post(`/desa/projects/${props.projectId}/cco/measurements/${editingMeasurement.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            measurementDialog.value = false;
            editingMeasurement.value = null;
        },
    });
}
</script>

<template>
    <Card padding="app">
        <div>
            <h2 class="text-base font-semibold text-content">Ubah target ukuran Cek Spek</h2>
            <p class="mt-0.5 text-xs text-content-secondary">
                Ukuran yang diukur warga di lapangan. Nilai target bisa diubah dengan alasan; ukuran tidak bisa dihapus
                setelah terbit.
            </p>
        </div>

        <div v-if="measurements.length === 0" class="mt-4">
            <EmptyState
                title="Belum ada ukuran Cek Spek"
                description="Proyek ini belum memiliki ukuran fisik untuk dicek warga."
            />
        </div>

        <div v-else class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <Card v-for="m in measurements" :key="m.id" padding="base" class="flex flex-col justify-between">
                <div>
                    <span class="text-xs font-medium text-content-secondary">Ukuran</span>
                    <h3 class="text-base font-semibold text-content">{{ m.name }}</h3>
                    <div class="mt-2 text-2xl font-bold text-primary tabular-nums">
                        {{ m.value }}
                        <span class="text-sm font-normal text-content-secondary">{{ m.unit }}</span>
                    </div>
                    <p v-if="m.how_to" class="mt-2 text-xs text-content-tertiary">
                        <strong>Petunjuk:</strong> {{ m.how_to }}
                    </p>
                </div>

                <div class="mt-4 flex justify-end border-t border-stroke pt-3">
                    <Button
                        size="sm"
                        variant="secondary"
                        :disabled="isFrozen"
                        :title="isFrozen ? 'Perubahan dibekukan saat dalam tinjauan BPD' : 'Ubah target ukuran'"
                        @click="openMeasurementChange(m)"
                    >
                        Ubah target
                    </Button>
                </div>
            </Card>
        </div>

        <Dialog :open="measurementDialog" title="Ubah target ukuran Cek Spek" @close="measurementDialog = false">
            <div v-if="editingMeasurement" class="space-y-4">
                <p class="text-xs text-content-secondary">
                    Penyesuaian target ukuran
                    <strong>{{ editingMeasurement.name }} ({{ editingMeasurement.unit }})</strong>.
                </p>

                <div>
                    <label for="m-val" class="block text-sm font-medium text-content">Target nilai baru *</label>
                    <input
                        id="m-val"
                        v-model="measurementForm.value"
                        type="text"
                        required
                        class="mt-1 block h-11 w-full rounded-md border border-stroke-strong bg-surface px-3 text-sm text-content tabular-nums focus:border-primary focus:outline-none"
                    />
                    <p v-if="measurementForm.errors.value" class="mt-1 text-xs text-negative">
                        {{ measurementForm.errors.value }}
                    </p>
                </div>

                <div>
                    <label for="m-howto" class="block text-sm font-medium text-content">
                        Petunjuk pengukuran (opsional)
                    </label>
                    <input
                        id="m-howto"
                        v-model="measurementForm.how_to"
                        type="text"
                        class="mt-1 block h-11 w-full rounded-md border border-stroke-strong bg-surface px-3 text-sm text-content focus:border-primary focus:outline-none"
                    />
                </div>

                <div>
                    <label for="m-reason" class="block text-sm font-medium text-content">Alasan perubahan *</label>
                    <textarea
                        id="m-reason"
                        v-model="measurementForm.reason"
                        rows="2"
                        required
                        placeholder="Contoh: Penyesuaian lebar jalan mengikuti kondisi bahu jalan"
                        class="mt-1 block w-full rounded-md border border-stroke-strong bg-surface px-3 py-2 text-sm text-content focus:border-primary focus:outline-none"
                    />
                    <p v-if="measurementForm.errors.reason" class="mt-1 text-xs text-negative">
                        {{ measurementForm.errors.reason }}
                    </p>
                </div>
            </div>

            <template #actions>
                <Button variant="secondary" @click="measurementDialog = false">Batal</Button>
                <Button
                    variant="primary"
                    :loading="measurementForm.processing"
                    :disabled="measurementForm.processing || !measurementForm.reason"
                    @click="submitMeasurementChange"
                >
                    {{ measurementForm.processing ? 'Menyimpan...' : 'Simpan perubahan' }}
                </Button>
            </template>
        </Dialog>
    </Card>
</template>
