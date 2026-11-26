<script setup lang="ts">
import { computed, ref } from 'vue';
import Alert from '@/components/ui/Alert.vue';
import Button from '@/components/ui/Button.vue';

interface Props {
    fileName: string;
    fileUrl: string | null;
}

const props = defineProps<Props>();

const zoomLevel = ref(100);

const lowerFileName = computed(() => props.fileName.toLowerCase());
const isPdf = computed(() => Boolean(props.fileUrl && lowerFileName.value.endsWith('.pdf')));
const isImage = computed(() =>
    Boolean(
        props.fileUrl &&
        (lowerFileName.value.endsWith('.png') ||
            lowerFileName.value.endsWith('.jpg') ||
            lowerFileName.value.endsWith('.jpeg')),
    ),
);

function zoomIn(): void {
    if (zoomLevel.value < 200) {
        zoomLevel.value += 25;
    }
}

function zoomOut(): void {
    if (zoomLevel.value > 50) {
        zoomLevel.value -= 25;
    }
}
</script>

<template>
    <div class="flex flex-col overflow-hidden rounded-lg border border-stroke bg-surface">
        <!-- Toolbar -->
        <div
            class="flex flex-wrap items-center justify-between gap-2 border-b border-stroke bg-surface-subtle px-4 py-2.5"
        >
            <span class="text-sm font-semibold text-content">Dokumen RAB asli</span>

            <div class="flex items-center gap-1.5">
                <template v-if="isImage">
                    <Button
                        type="button"
                        variant="secondary"
                        size="sm"
                        aria-label="Perkecil pratinjau"
                        :disabled="zoomLevel <= 50"
                        @click="zoomOut"
                    >
                        &minus;
                    </Button>
                    <span class="px-1.5 text-xs font-medium text-content-secondary tabular-nums">
                        {{ zoomLevel }}%
                    </span>
                    <Button
                        type="button"
                        variant="secondary"
                        size="sm"
                        aria-label="Perbesar pratinjau"
                        :disabled="zoomLevel >= 200"
                        @click="zoomIn"
                    >
                        +
                    </Button>
                </template>

                <a
                    v-if="fileUrl"
                    :href="fileUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex h-9 items-center justify-center gap-1.5 rounded-md border border-stroke-strong bg-surface px-3 text-sm font-medium text-content transition-colors hover:bg-surface-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                >
                    <span>Buka berkas asli</span>
                </a>
            </div>
        </div>

        <!-- Viewer Body -->
        <div class="relative flex max-h-[800px] min-h-[420px] justify-center overflow-auto bg-surface-muted p-4">
            <iframe
                v-if="isPdf && fileUrl"
                :src="fileUrl"
                class="h-[680px] w-full rounded-md border border-stroke bg-surface"
                title="Pratinjau berkas PDF"
            />

            <img
                v-else-if="isImage && fileUrl"
                :src="fileUrl"
                class="max-h-[700px] w-auto rounded-md border border-stroke bg-surface object-contain transition-transform"
                :style="{ transform: `scale(${zoomLevel / 100})`, transformOrigin: 'top center' }"
                alt="Pratinjau berkas dokumen RAB"
            />

            <div v-else class="flex w-full max-w-md items-center justify-center self-center">
                <Alert color="info" title="Pratinjau tidak tersedia">
                    <span>Berkas ini tidak dapat ditampilkan langsung di sini.</span>
                    <div v-if="fileUrl" class="mt-3">
                        <a
                            :href="fileUrl"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex h-10 items-center justify-center rounded-md border border-stroke-strong bg-surface px-3.5 text-sm font-medium text-content transition-colors hover:bg-surface-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                        >
                            Buka berkas asli
                        </a>
                    </div>
                </Alert>
            </div>
        </div>
    </div>
</template>
