<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import Dialog from '@/components/ui/Dialog.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import PriceLabel from '@/components/ui/PriceLabel.vue';
import { formatRupiah } from '@/lib/format';
import type { PriceLabelType, PriceStandardItem } from '@/types/audit';

export interface ProjectAuditItem {
    id: number;
    raw_name: string;
    volume: number | string;
    unit: string;
    unit_price_rp: number;
    amount_rp: number;
    label: PriceLabelType;
    diff_rp: number;
    over_pct: number | null;
    standard_id: number | null;
    standard_price_rp?: number | null;
    standard?: {
        id: number;
        name: string;
        code: string;
        unit: string;
        price_rp: number;
    } | null;
}

interface Props {
    projectId: number;
    writtenTotalRp: number;
    items: ProjectAuditItem[];
    standards: PriceStandardItem[];
    isFrozen: boolean;
}

const props = defineProps<Props>();

const editingItem = ref<ProjectAuditItem | null>(null);
const itemDialog = ref(false);

const itemForm = useForm({
    volume: '',
    unit: '',
    unit_price_rp: 0,
    standard_id: null as number | null,
    reason: '',
});

function openItemChange(item: ProjectAuditItem): void {
    if (props.isFrozen) {
        return;
    }
    editingItem.value = item;
    itemForm.volume = String(item.volume);
    itemForm.unit = item.unit;
    itemForm.unit_price_rp = item.unit_price_rp;
    itemForm.standard_id = item.standard_id;
    itemForm.reason = '';
    itemDialog.value = true;
}

function submitItemChange(): void {
    if (!editingItem.value) {
        return;
    }
    itemForm.post(`/desa/projects/${props.projectId}/cco/items/${editingItem.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            itemDialog.value = false;
            editingItem.value = null;
        },
    });
}
</script>

<template>
    <Card padding="app">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-base font-semibold text-content">Ubah volume atau harga (addendum/CCO)</h2>
                <p class="mt-0.5 text-xs text-content-secondary">
                    Setiap perubahan wajib diberi alasan dan langsung tampil di riwayat yang bisa dibaca warga. Label
                    harga dihitung ulang otomatis.
                </p>
            </div>
            <div class="shrink-0 text-sm text-content-secondary">
                Total anggaran:
                <strong class="text-content tabular-nums">{{ formatRupiah(writtenTotalRp) }}</strong>
            </div>
        </div>

        <div v-if="items.length === 0" class="mt-4">
            <EmptyState
                illustration="document-error"
                compact
                title="Tidak ada item belanja RAB"
                description="Dokumen RAB ini belum memiliki baris rincian belanja."
            />
        </div>

        <template v-else>
            <!-- Desktop Table -->
            <div class="mt-4 hidden overflow-x-auto sm:block">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-stroke bg-surface-subtle text-xs font-medium text-content-secondary">
                        <tr>
                            <th scope="col" class="px-3 py-2.5">Nama item belanja</th>
                            <th scope="col" class="px-3 py-2.5 text-right">Volume</th>
                            <th scope="col" class="px-3 py-2.5 text-right">Harga satuan</th>
                            <th scope="col" class="px-3 py-2.5 text-right">Subtotal</th>
                            <th scope="col" class="px-3 py-2.5 text-center">Label harga</th>
                            <th scope="col" class="px-3 py-2.5 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stroke">
                        <tr v-for="item in items" :key="item.id" class="hover:bg-surface-muted">
                            <td class="px-3 py-3">
                                <p class="font-medium text-content">{{ item.raw_name }}</p>
                                <p
                                    v-if="item.standard || item.standard_price_rp"
                                    class="text-xs text-content-tertiary tabular-nums"
                                >
                                    <template v-if="item.standard">
                                        Standar: {{ item.standard.name }} ({{
                                            formatRupiah(item.standard_price_rp ?? item.standard.price_rp)
                                        }}/{{ item.standard.unit }})
                                    </template>
                                    <template v-else>
                                        Standar: {{ formatRupiah(item.standard_price_rp ?? 0) }}
                                    </template>
                                </p>
                            </td>
                            <td class="px-3 py-3 text-right text-sm text-content tabular-nums">
                                {{ item.volume }} {{ item.unit }}
                            </td>
                            <td class="px-3 py-3 text-right text-sm text-content tabular-nums">
                                {{ formatRupiah(item.unit_price_rp) }}
                            </td>
                            <td class="px-3 py-3 text-right text-sm font-semibold text-content tabular-nums">
                                {{ formatRupiah(item.amount_rp) }}
                            </td>
                            <td class="px-3 py-3 text-center">
                                <PriceLabel
                                    :label="item.label"
                                    :percent-above="item.over_pct ? `+${item.over_pct}%` : null"
                                />
                            </td>
                            <td class="px-3 py-3 text-right">
                                <Button
                                    size="sm"
                                    variant="secondary"
                                    :disabled="isFrozen"
                                    :title="isFrozen ? 'Perubahan dibekukan saat dalam tinjauan BPD' : 'Ubah item'"
                                    @click="openItemChange(item)"
                                >
                                    Ubah item
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Mobile Card List -->
            <div class="mt-4 divide-y divide-stroke border-t border-stroke sm:hidden">
                <div v-for="item in items" :key="item.id" class="space-y-2 py-3">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="text-sm font-medium text-content">{{ item.raw_name }}</p>
                            <p
                                v-if="item.standard || item.standard_price_rp"
                                class="text-xs text-content-tertiary tabular-nums"
                            >
                                <template v-if="item.standard">
                                    Standar: {{ item.standard.name }} ({{
                                        formatRupiah(item.standard_price_rp ?? item.standard.price_rp)
                                    }}/{{ item.standard.unit }})
                                </template>
                                <template v-else> Standar: {{ formatRupiah(item.standard_price_rp ?? 0) }} </template>
                            </p>
                        </div>
                        <PriceLabel :label="item.label" :percent-above="item.over_pct ? `+${item.over_pct}%` : null" />
                    </div>
                    <div class="grid grid-cols-3 gap-2 text-xs">
                        <div>
                            <span class="text-content-secondary">Volume</span>
                            <p class="font-medium text-content tabular-nums">{{ item.volume }} {{ item.unit }}</p>
                        </div>
                        <div>
                            <span class="text-content-secondary">Harga</span>
                            <p class="font-medium text-content tabular-nums">{{ formatRupiah(item.unit_price_rp) }}</p>
                        </div>
                        <div class="text-right">
                            <span class="text-content-secondary">Subtotal</span>
                            <p class="font-semibold text-content tabular-nums">{{ formatRupiah(item.amount_rp) }}</p>
                        </div>
                    </div>
                    <div class="flex justify-end">
                        <Button size="sm" variant="secondary" :disabled="isFrozen" @click="openItemChange(item)">
                            Ubah item
                        </Button>
                    </div>
                </div>
            </div>
        </template>

        <!-- Edit Item Dialog -->
        <Dialog :open="itemDialog" title="Ubah item belanja (addendum/CCO)" @close="itemDialog = false">
            <div v-if="editingItem" class="space-y-4">
                <p class="text-xs text-content-secondary">
                    Perubahan volume atau harga satuan untuk <strong>{{ editingItem.raw_name }}</strong>.
                </p>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="item-vol" class="block text-sm font-medium text-content">Volume baru *</label>
                        <input
                            id="item-vol"
                            v-model="itemForm.volume"
                            type="text"
                            required
                            class="mt-1 block h-11 w-full rounded-md border border-stroke-strong bg-surface px-3 text-sm text-content tabular-nums focus:border-primary focus:outline-none"
                        />
                        <p v-if="itemForm.errors.volume" class="mt-1 text-xs text-negative">
                            {{ itemForm.errors.volume }}
                        </p>
                    </div>
                    <div>
                        <label for="item-unit" class="block text-sm font-medium text-content">Satuan *</label>
                        <input
                            id="item-unit"
                            v-model="itemForm.unit"
                            type="text"
                            required
                            class="mt-1 block h-11 w-full rounded-md border border-stroke-strong bg-surface px-3 text-sm text-content focus:border-primary focus:outline-none"
                        />
                        <p v-if="itemForm.errors.unit" class="mt-1 text-xs text-negative">{{ itemForm.errors.unit }}</p>
                    </div>
                </div>

                <div>
                    <label for="item-price" class="block text-sm font-medium text-content">Harga satuan (Rp) *</label>
                    <input
                        id="item-price"
                        v-model.number="itemForm.unit_price_rp"
                        type="number"
                        min="1"
                        required
                        class="mt-1 block h-11 w-full rounded-md border border-stroke-strong bg-surface px-3 text-sm text-content tabular-nums focus:border-primary focus:outline-none"
                    />
                    <p v-if="itemForm.errors.unit_price_rp" class="mt-1 text-xs text-negative">
                        {{ itemForm.errors.unit_price_rp }}
                    </p>
                </div>

                <div>
                    <label for="item-standard" class="block text-sm font-medium text-content">
                        Acuan standar harga (opsional)
                    </label>
                    <select
                        id="item-standard"
                        v-model.number="itemForm.standard_id"
                        class="mt-1 block h-11 w-full rounded-md border border-stroke-strong bg-surface px-3 text-sm text-content focus:border-primary focus:outline-none"
                    >
                        <option :value="null">Tanpa standar acuan</option>
                        <option v-for="std in standards" :key="std.id" :value="std.id">
                            {{ std.name }} ({{ formatRupiah(std.price_rp) }}/{{ std.unit }})
                        </option>
                    </select>
                </div>

                <div>
                    <label for="item-reason" class="block text-sm font-medium text-content"> Alasan perubahan * </label>
                    <textarea
                        id="item-reason"
                        v-model="itemForm.reason"
                        rows="2"
                        required
                        placeholder="Contoh: Penyesuaian volume akibat kondisi tanah di lapangan"
                        class="mt-1 block w-full rounded-md border border-stroke-strong bg-surface px-3 py-2 text-sm text-content focus:border-primary focus:outline-none"
                    />
                    <p v-if="itemForm.errors.reason" class="mt-1 text-xs text-negative">{{ itemForm.errors.reason }}</p>
                </div>
            </div>

            <template #actions>
                <Button variant="secondary" @click="itemDialog = false">Batal</Button>
                <Button
                    variant="primary"
                    :loading="itemForm.processing"
                    :disabled="itemForm.processing || !itemForm.reason"
                    @click="submitItemChange"
                >
                    {{ itemForm.processing ? 'Menyimpan...' : 'Simpan perubahan' }}
                </Button>
            </template>
        </Dialog>
    </Card>
</template>
