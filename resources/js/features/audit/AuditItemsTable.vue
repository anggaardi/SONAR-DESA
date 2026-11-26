<script setup lang="ts">
import { computed, ref } from 'vue';
import Alert from '@/components/ui/Alert.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import Dialog from '@/components/ui/Dialog.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import PriceLabel from '@/components/ui/PriceLabel.vue';
import { formatRupiah } from '@/lib/format';
import type { AuditItemData, AuditItemFormPayload, AuditSummary, PriceStandardItem } from '@/types/audit';

type FilterTab = 'all' | 'needs_check' | 'above_standard' | 'within_standard' | 'no_reference';

interface Props {
    items: AuditItemData[];
    summary: AuditSummary;
    priceStandards: PriceStandardItem[];
    isEditable: boolean;
    isAddModalOpen: boolean;
    isSubmittingAdd: boolean;
    isSubmittingEdit: boolean;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    confirmItem: [item: AuditItemData];
    deleteItem: [item: AuditItemData];
    submitAdd: [payload: AuditItemFormPayload];
    submitEdit: [itemId: number, payload: AuditItemFormPayload];
    'update:isAddModalOpen': [open: boolean];
}>();

const activeTab = ref<FilterTab>(props.summary.needs_check_count > 0 ? 'needs_check' : 'all');

const filteredItems = computed(() => {
    if (activeTab.value === 'all') {
        return props.items;
    }
    return props.items.filter((item) => {
        if (activeTab.value === 'needs_check') {
            return item.is_yellow;
        }
        return item.label === activeTab.value;
    });
});

const filterTabs = computed<Array<{ id: FilterTab; label: string; count: number }>>(() => [
    { id: 'all', label: 'Semua', count: props.summary.total_items },
    { id: 'needs_check', label: 'Perlu dicek', count: props.summary.needs_check_count },
    { id: 'above_standard', label: 'Di atas standar', count: props.summary.above_standard_count },
    { id: 'within_standard', label: 'Sesuai', count: props.summary.within_standard_count },
    { id: 'no_reference', label: 'Tanpa acuan', count: props.summary.no_reference_count },
]);

// Add Item state
const addForm = ref({
    raw_name: '',
    volume: '1',
    unit: '',
    unit_price_rp: 0,
    amount_rp: 0,
    standard_id: null as number | null,
});
const addStandardSearch = ref('');

function openAddModal(): void {
    addForm.value = {
        raw_name: '',
        volume: '1',
        unit: '',
        unit_price_rp: 0,
        amount_rp: 0,
        standard_id: null,
    };
    addStandardSearch.value = '';
    emit('update:isAddModalOpen', true);
}

const filteredAddStandards = computed(() => {
    if (!addStandardSearch.value.trim()) {
        return props.priceStandards.slice(0, 50);
    }
    const q = addStandardSearch.value.toLowerCase();
    return props.priceStandards.filter((s) => s.name.toLowerCase().includes(q) || s.code.toLowerCase().includes(q));
});

function selectAddStandard(std: PriceStandardItem): void {
    addForm.value.standard_id = std.id;
    addStandardSearch.value = `${std.name} (${std.unit}) - ${formatRupiah(std.price_rp)}`;
    if (!addForm.value.unit) {
        addForm.value.unit = std.unit;
    }
    if (!addForm.value.unit_price_rp) {
        addForm.value.unit_price_rp = std.price_rp;
    }
}

function submitAddForm(): void {
    emit('submitAdd', {
        raw_name: addForm.value.raw_name,
        volume: addForm.value.volume,
        unit: addForm.value.unit,
        unit_price_rp: addForm.value.unit_price_rp,
        amount_rp: addForm.value.amount_rp || null,
        standard_id: addForm.value.standard_id,
    });
}

// Edit Item state
const isEditModalOpen = ref(false);
const editingItem = ref<AuditItemData | null>(null);
const editForm = ref({
    raw_name: '',
    volume: '',
    unit: '',
    unit_price_rp: 0,
    amount_rp: 0,
    standard_id: null as number | null,
});
const editStandardSearch = ref('');

const filteredEditStandards = computed(() => {
    if (!editStandardSearch.value.trim()) {
        return props.priceStandards.slice(0, 50);
    }
    const q = editStandardSearch.value.toLowerCase();
    return props.priceStandards.filter((s) => s.name.toLowerCase().includes(q) || s.code.toLowerCase().includes(q));
});

function openEditModal(item: AuditItemData): void {
    editingItem.value = item;
    editForm.value = {
        raw_name: item.raw_name,
        volume: item.volume ?? '1',
        unit: item.unit ?? '',
        unit_price_rp: item.unit_price_rp ?? 0,
        amount_rp: item.amount_rp ?? 0,
        standard_id: item.standard_id,
    };
    editStandardSearch.value = item.standard?.name ?? '';
    isEditModalOpen.value = true;
}

function selectEditStandard(std: PriceStandardItem): void {
    editForm.value.standard_id = std.id;
    editStandardSearch.value = `${std.name} (${std.unit}) - ${formatRupiah(std.price_rp)}`;
    if (!editForm.value.unit) {
        editForm.value.unit = std.unit;
    }
}

function clearEditStandard(): void {
    editForm.value.standard_id = null;
    editStandardSearch.value = '';
}

function submitEditForm(): void {
    if (!editingItem.value) {
        return;
    }
    emit('submitEdit', editingItem.value.id, {
        raw_name: editForm.value.raw_name,
        volume: editForm.value.volume,
        unit: editForm.value.unit,
        unit_price_rp: editForm.value.unit_price_rp,
        amount_rp: editForm.value.amount_rp || null,
        standard_id: editForm.value.standard_id,
    });
    isEditModalOpen.value = false;
    editingItem.value = null;
}

function filterTabClass(tabId: FilterTab): string {
    const isActive = activeTab.value === tabId;
    if (!isActive) {
        return 'inline-flex min-h-9 items-center rounded-full border border-stroke bg-surface px-3 py-1 text-xs font-medium text-content-secondary transition-colors hover:bg-surface-muted hover:text-content focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary';
    }
    if (tabId === 'needs_check') {
        return 'inline-flex min-h-9 items-center rounded-full border border-warning-border bg-warning-subtle px-3 py-1 text-xs font-semibold text-warning focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary';
    }
    if (tabId === 'above_standard') {
        return 'inline-flex min-h-9 items-center rounded-full border border-negative-border bg-negative-subtle px-3 py-1 text-xs font-semibold text-negative focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary';
    }
    if (tabId === 'within_standard') {
        return 'inline-flex min-h-9 items-center rounded-full border border-positive-border bg-positive-subtle px-3 py-1 text-xs font-semibold text-positive focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary';
    }
    return 'inline-flex min-h-9 items-center rounded-full border border-primary/40 bg-primary-subtle px-3 py-1 text-xs font-semibold text-primary focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary';
}
</script>

<template>
    <div class="space-y-4">
        <!-- Single Status Message (only shown when yellow rows need attention) -->
        <Alert
            v-if="summary.needs_check_count > 0"
            color="warning"
            :title="`${summary.needs_check_count} baris perlu diperiksa sebelum terbit`"
        >
            Cocokkan baris kuning di bawah dengan dokumen asli, lalu pilih Sudah benar atau Koreksi data.
        </Alert>

        <!-- Filter Tabs & Add Row -->
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="flex flex-wrap items-center gap-2" role="tablist" aria-label="Filter baris RAB">
                <button
                    v-for="tab in filterTabs"
                    :key="tab.id"
                    type="button"
                    role="tab"
                    :aria-selected="activeTab === tab.id"
                    :class="filterTabClass(tab.id)"
                    @click="activeTab = tab.id"
                >
                    <span class="tabular-nums">{{ tab.label }} ({{ tab.count }})</span>
                </button>
            </div>

            <Button v-if="isEditable" type="button" variant="secondary" size="sm" @click="openAddModal">
                Tambah baris
            </Button>
        </div>

        <!-- Empty State -->
        <Card v-if="filteredItems.length === 0" padding="base">
            <EmptyState
                :illustration="items.length === 0 ? 'plang-empty' : 'search-empty'"
                compact
                :title="items.length === 0 ? 'Belum ada baris belanja RAB.' : 'Tidak ada baris pada filter ini.'"
                :description="
                    items.length === 0
                        ? 'Tambahkan baris belanja secara manual atau unggah ulang berkas RAB.'
                        : 'Pilih filter lain atau tampilkan semua baris belanja.'
                "
            >
                <Button
                    v-if="items.length > 0 && activeTab !== 'all'"
                    type="button"
                    variant="secondary"
                    size="sm"
                    @click="activeTab = 'all'"
                >
                    Tampilkan semua
                </Button>
            </EmptyState>
        </Card>

        <template v-else>
            <!-- Desktop Table -->
            <div class="hidden overflow-hidden rounded-lg border border-stroke bg-surface sm:block">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead
                            class="border-b border-stroke bg-surface-subtle text-xs font-medium text-content-secondary"
                        >
                            <tr>
                                <th scope="col" class="w-10 px-3.5 py-3">No</th>
                                <th scope="col" class="px-3.5 py-3">Uraian belanja</th>
                                <th scope="col" class="px-3.5 py-3">Vol / sat</th>
                                <th scope="col" class="px-3.5 py-3 text-right">Harga satuan</th>
                                <th scope="col" class="px-3.5 py-3 text-right">Subtotal</th>
                                <th scope="col" class="px-3.5 py-3 text-right">Evaluasi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stroke">
                            <tr
                                v-for="item in filteredItems"
                                :key="item.id"
                                :class="[
                                    'transition-colors',
                                    item.is_yellow ? 'bg-warning-subtle/50' : 'hover:bg-surface-muted/50',
                                ]"
                            >
                                <td class="px-3.5 py-3 align-top text-xs text-content-tertiary tabular-nums">
                                    {{ String(item.position).padStart(2, '0') }}
                                </td>

                                <td class="px-3.5 py-3 align-top">
                                    <div class="font-medium text-content">
                                        {{ item.raw_name }}
                                    </div>
                                    <div v-if="item.standard" class="mt-0.5 text-xs text-content-tertiary tabular-nums">
                                        Standar harga: {{ formatRupiah(item.standard.price_rp) }} /
                                        {{ item.standard.unit }}
                                    </div>
                                    <div v-else class="mt-0.5 text-xs text-content-tertiary">Tanpa acuan standar</div>

                                    <!-- Yellow Row Inline Resolution -->
                                    <div
                                        v-if="item.is_yellow"
                                        class="mt-2.5 rounded-md border border-warning-border bg-surface p-3"
                                    >
                                        <p class="text-xs font-medium text-warning">
                                            {{ item.reason_text }}
                                        </p>

                                        <div v-if="isEditable" class="mt-2.5 flex flex-wrap items-center gap-2">
                                            <Button
                                                type="button"
                                                variant="secondary"
                                                size="sm"
                                                @click="emit('confirmItem', item)"
                                            >
                                                Sudah benar
                                            </Button>

                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                @click="openEditModal(item)"
                                            >
                                                Koreksi data
                                            </Button>

                                            <Button
                                                type="button"
                                                variant="danger"
                                                size="sm"
                                                @click="emit('deleteItem', item)"
                                            >
                                                Hapus baris
                                            </Button>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-3.5 py-3 align-top whitespace-nowrap text-content tabular-nums">
                                    {{ item.volume }} {{ item.unit }}
                                </td>

                                <td
                                    class="px-3.5 py-3 text-right align-top whitespace-nowrap text-content tabular-nums"
                                >
                                    {{ item.unit_price_rp ? formatRupiah(item.unit_price_rp) : '-' }}
                                </td>

                                <td
                                    class="px-3.5 py-3 text-right align-top font-semibold whitespace-nowrap text-content tabular-nums"
                                >
                                    {{ item.amount_rp ? formatRupiah(item.amount_rp) : '-' }}
                                </td>

                                <td class="px-3.5 py-3 text-right align-top whitespace-nowrap">
                                    <PriceLabel
                                        :label="item.is_yellow ? 'needs_check' : item.label"
                                        :percent-above="item.over_pct ? `+${item.over_pct}%` : null"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Mobile Card List -->
            <div class="flex flex-col gap-3 sm:hidden">
                <Card
                    v-for="item in filteredItems"
                    :key="item.id"
                    padding="base"
                    :class="item.is_yellow ? 'border-warning-border bg-warning-subtle/40' : ''"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-content">
                                <span class="text-content-tertiary tabular-nums"
                                    >{{ String(item.position).padStart(2, '0') }}.</span
                                >
                                {{ item.raw_name }}
                            </p>
                            <p v-if="item.standard" class="mt-0.5 text-xs text-content-tertiary tabular-nums">
                                Standar harga: {{ formatRupiah(item.standard.price_rp) }} / {{ item.standard.unit }}
                            </p>
                            <p v-else class="mt-0.5 text-xs text-content-tertiary">Tanpa acuan standar</p>
                        </div>
                        <PriceLabel
                            :label="item.is_yellow ? 'needs_check' : item.label"
                            :percent-above="item.over_pct ? `+${item.over_pct}%` : null"
                        />
                    </div>

                    <div class="mt-3 grid grid-cols-3 gap-2 border-t border-stroke pt-2.5 text-xs">
                        <div>
                            <span class="text-content-secondary">Vol / sat</span>
                            <p class="font-medium text-content tabular-nums">{{ item.volume }} {{ item.unit }}</p>
                        </div>
                        <div>
                            <span class="text-content-secondary">Harga</span>
                            <p class="font-medium text-content tabular-nums">
                                {{ item.unit_price_rp ? formatRupiah(item.unit_price_rp) : '-' }}
                            </p>
                        </div>
                        <div class="text-right">
                            <span class="text-content-secondary">Subtotal</span>
                            <p class="font-semibold text-content tabular-nums">
                                {{ item.amount_rp ? formatRupiah(item.amount_rp) : '-' }}
                            </p>
                        </div>
                    </div>

                    <div v-if="item.is_yellow" class="mt-3 rounded-md border border-warning-border bg-surface p-3">
                        <p class="text-xs font-medium text-warning">{{ item.reason_text }}</p>
                        <div v-if="isEditable" class="mt-2.5 flex flex-wrap gap-2">
                            <Button type="button" variant="secondary" @click="emit('confirmItem', item)">
                                Sudah benar
                            </Button>
                            <Button type="button" variant="ghost" @click="openEditModal(item)"> Koreksi data </Button>
                            <Button type="button" variant="danger" @click="emit('deleteItem', item)">
                                Hapus baris
                            </Button>
                        </div>
                    </div>
                </Card>
            </div>
        </template>

        <!-- Add Item Dialog -->
        <Dialog
            :open="isAddModalOpen"
            title="Tambah baris belanja manual"
            @close="emit('update:isAddModalOpen', false)"
        >
            <form class="space-y-4" @submit.prevent="submitAddForm">
                <div>
                    <label class="block text-sm font-medium text-content" for="add-raw-name">
                        Uraian material / upah <span class="text-negative">*</span>
                    </label>
                    <input
                        id="add-raw-name"
                        v-model="addForm.raw_name"
                        type="text"
                        required
                        placeholder="Contoh: Semen Portland 50kg (Gresik)"
                        class="mt-1 block h-11 w-full rounded-md border border-stroke-strong bg-surface px-3 text-sm text-content focus:border-primary focus:outline-none"
                    />
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-content" for="add-vol">
                            Volume <span class="text-negative">*</span>
                        </label>
                        <input
                            id="add-vol"
                            v-model="addForm.volume"
                            type="text"
                            required
                            placeholder="Contoh: 100"
                            class="mt-1 block h-11 w-full rounded-md border border-stroke-strong bg-surface px-3 text-sm text-content tabular-nums focus:border-primary focus:outline-none"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-content" for="add-unit">
                            Satuan <span class="text-negative">*</span>
                        </label>
                        <input
                            id="add-unit"
                            v-model="addForm.unit"
                            type="text"
                            required
                            placeholder="Contoh: sak, m3, HOK"
                            class="mt-1 block h-11 w-full rounded-md border border-stroke-strong bg-surface px-3 text-sm text-content focus:border-primary focus:outline-none"
                        />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-content" for="add-price">
                            Harga satuan (Rp) <span class="text-negative">*</span>
                        </label>
                        <input
                            id="add-price"
                            v-model.number="addForm.unit_price_rp"
                            type="number"
                            min="0"
                            required
                            placeholder="Contoh: 68000"
                            class="mt-1 block h-11 w-full rounded-md border border-stroke-strong bg-surface px-3 text-sm text-content tabular-nums focus:border-primary focus:outline-none"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-content" for="add-subtotal">
                            Jumlah subtotal (Rp)
                        </label>
                        <input
                            id="add-subtotal"
                            v-model.number="addForm.amount_rp"
                            type="number"
                            min="0"
                            :placeholder="
                                addForm.volume && addForm.unit_price_rp
                                    ? String(Math.round(Number(addForm.volume) * addForm.unit_price_rp))
                                    : 'Otomatis'
                            "
                            class="mt-1 block h-11 w-full rounded-md border border-stroke-strong bg-surface px-3 text-sm text-content tabular-nums focus:border-primary focus:outline-none"
                        />
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-content" for="add-standard-search">
                        Acuan standar harga
                    </label>
                    <input
                        id="add-standard-search"
                        v-model="addStandardSearch"
                        type="text"
                        placeholder="Ketik untuk mencari standar harga..."
                        class="mt-1 block h-11 w-full rounded-md border border-stroke-strong bg-surface px-3 text-sm text-content focus:border-primary focus:outline-none"
                    />
                    <div
                        v-if="filteredAddStandards.length > 0 && addStandardSearch.trim() && !addForm.standard_id"
                        class="mt-2 max-h-36 overflow-y-auto rounded-md border border-stroke bg-surface p-1 shadow-sm"
                    >
                        <button
                            v-for="std in filteredAddStandards.slice(0, 8)"
                            :key="std.id"
                            type="button"
                            class="flex w-full items-center justify-between rounded-sm px-2.5 py-2 text-left text-xs text-content hover:bg-surface-muted"
                            @click="selectAddStandard(std)"
                        >
                            <span>{{ std.name }} ({{ std.unit }})</span>
                            <span class="font-semibold text-primary tabular-nums">{{
                                formatRupiah(std.price_rp)
                            }}</span>
                        </button>
                    </div>
                </div>

                <div class="flex justify-end gap-3 border-t border-stroke pt-3">
                    <Button type="button" variant="secondary" @click="emit('update:isAddModalOpen', false)">
                        Batal
                    </Button>
                    <Button type="submit" variant="primary" :loading="isSubmittingAdd">
                        {{ isSubmittingAdd ? 'Menyimpan...' : 'Simpan baris' }}
                    </Button>
                </div>
            </form>
        </Dialog>

        <!-- Edit Item Dialog -->
        <Dialog :open="isEditModalOpen" title="Koreksi data baris belanja" @close="isEditModalOpen = false">
            <form class="space-y-4" @submit.prevent="submitEditForm">
                <div>
                    <label class="block text-sm font-medium text-content" for="edit-raw-name">
                        Uraian material / upah <span class="text-negative">*</span>
                    </label>
                    <input
                        id="edit-raw-name"
                        v-model="editForm.raw_name"
                        type="text"
                        required
                        class="mt-1 block h-11 w-full rounded-md border border-stroke-strong bg-surface px-3 text-sm text-content focus:border-primary focus:outline-none"
                    />
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-content" for="edit-vol">
                            Volume <span class="text-negative">*</span>
                        </label>
                        <input
                            id="edit-vol"
                            v-model="editForm.volume"
                            type="text"
                            required
                            class="mt-1 block h-11 w-full rounded-md border border-stroke-strong bg-surface px-3 text-sm text-content tabular-nums focus:border-primary focus:outline-none"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-content" for="edit-unit">
                            Satuan <span class="text-negative">*</span>
                        </label>
                        <input
                            id="edit-unit"
                            v-model="editForm.unit"
                            type="text"
                            required
                            placeholder="Contoh: sak, m3, HOK"
                            class="mt-1 block h-11 w-full rounded-md border border-stroke-strong bg-surface px-3 text-sm text-content focus:border-primary focus:outline-none"
                        />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-content" for="edit-price">
                            Harga satuan (Rp) <span class="text-negative">*</span>
                        </label>
                        <input
                            id="edit-price"
                            v-model.number="editForm.unit_price_rp"
                            type="number"
                            min="0"
                            required
                            class="mt-1 block h-11 w-full rounded-md border border-stroke-strong bg-surface px-3 text-sm text-content tabular-nums focus:border-primary focus:outline-none"
                        />
                    </div>

                    <div>
                        <span class="block text-sm font-medium text-content">Subtotal terhitung</span>
                        <div
                            class="mt-1 flex h-11 items-center rounded-md border border-stroke bg-surface-subtle px-3 text-sm font-semibold text-content tabular-nums"
                        >
                            {{
                                formatRupiah(
                                    Math.round((parseFloat(editForm.volume) || 0) * (editForm.unit_price_rp || 0)),
                                )
                            }}
                        </div>
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between">
                        <label class="block text-sm font-medium text-content" for="edit-standard-search">
                            Acuan standar harga
                        </label>
                        <button
                            v-if="editForm.standard_id"
                            type="button"
                            class="text-xs font-medium text-negative hover:underline"
                            @click="clearEditStandard"
                        >
                            Hapus acuan
                        </button>
                    </div>

                    <input
                        id="edit-standard-search"
                        v-model="editStandardSearch"
                        type="text"
                        placeholder="Ketik untuk mencari standar harga..."
                        class="mt-1 block h-11 w-full rounded-md border border-stroke-strong bg-surface px-3 text-sm text-content focus:border-primary focus:outline-none"
                    />

                    <div
                        v-if="editStandardSearch && !editForm.standard_id && filteredEditStandards.length > 0"
                        class="mt-1 max-h-36 overflow-y-auto rounded-md border border-stroke bg-surface text-xs shadow-sm"
                    >
                        <button
                            v-for="std in filteredEditStandards"
                            :key="std.id"
                            type="button"
                            class="w-full border-b border-stroke px-3 py-2 text-left last:border-0 hover:bg-surface-muted"
                            @click="selectEditStandard(std)"
                        >
                            <span class="font-semibold text-content">{{ std.name }}</span>
                            <span class="block text-xs text-content-tertiary tabular-nums">
                                {{ std.code }} &middot; {{ std.unit }} &middot; {{ formatRupiah(std.price_rp) }}
                            </span>
                        </button>
                    </div>
                </div>

                <div class="flex justify-end gap-2 border-t border-stroke pt-3">
                    <Button type="button" variant="secondary" @click="isEditModalOpen = false">Batal</Button>
                    <Button type="submit" variant="primary" :loading="isSubmittingEdit">
                        {{ isSubmittingEdit ? 'Menyimpan...' : 'Simpan perubahan' }}
                    </Button>
                </div>
            </form>
        </Dialog>
    </div>
</template>
