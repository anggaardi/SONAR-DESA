<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import {
    getVisibleTiles,
    isValidCoordinates,
    latLngToWorldPx,
    metersToPixels,
    worldPxToLatLng,
} from '@/lib/mapTileMath';

interface Props {
    lat: string;
    lng: string;
    hasError?: boolean;
    defaultCenterLat?: number;
    defaultCenterLng?: number;
}

const props = withDefaults(defineProps<Props>(), {
    hasError: false,
    defaultCenterLat: -8.4124,
    defaultCenterLng: 115.2482,
});

const emit = defineEmits<{
    'update:coordinates': [coords: { lat: string; lng: string }];
}>();

const MIN_ZOOM = 12;
const MAX_ZOOM = 18;
const GEOFENCE_RADIUS_METERS = 300;
const DRAG_THRESHOLD_PX = 5;

const viewportEl = ref<HTMLElement | null>(null);
const viewportWidth = ref(600);
const viewportHeight = ref(240);
const zoom = ref(16);

const centerLat = ref(props.defaultCenterLat);
const centerLng = ref(props.defaultCenterLng);

const isDragging = ref(false);
const dragMoved = ref(false);
const dragStartX = ref(0);
const dragStartY = ref(0);
const dragStartWorldX = ref(0);
const dragStartWorldY = ref(0);
const failedTileCount = ref(0);

const selectedCoords = computed<{ lat: number; lng: number } | null>(() => {
    const latText = String(props.lat).trim();
    const lngText = String(props.lng).trim();
    if (!latText || !lngText) {
        return null;
    }

    const parsedLat = Number(latText);
    const parsedLng = Number(lngText);
    if (!isValidCoordinates(parsedLat, parsedLng)) {
        return null;
    }

    return { lat: parsedLat, lng: parsedLng };
});

watch(
    selectedCoords,
    (coords) => {
        if (coords && !isDragging.value) {
            centerLat.value = coords.lat;
            centerLng.value = coords.lng;
        }
    },
    { immediate: true },
);

let resizeObserver: ResizeObserver | null = null;

function updateViewportSize(): void {
    if (!viewportEl.value) {
        return;
    }
    const rect = viewportEl.value.getBoundingClientRect();
    if (rect.width > 0) {
        viewportWidth.value = rect.width;
    }
    if (rect.height > 0) {
        viewportHeight.value = rect.height;
    }
}

onMounted(() => {
    updateViewportSize();
    if (typeof ResizeObserver !== 'undefined' && viewportEl.value) {
        resizeObserver = new ResizeObserver(updateViewportSize);
        resizeObserver.observe(viewportEl.value);
    }
});

onUnmounted(() => {
    resizeObserver?.disconnect();
});

const visibleTiles = computed(() =>
    getVisibleTiles(centerLat.value, centerLng.value, zoom.value, viewportWidth.value, viewportHeight.value),
);

const pinPosition = computed<{ x: number; y: number } | null>(() => {
    if (!selectedCoords.value) {
        return null;
    }
    const centerPx = latLngToWorldPx(centerLat.value, centerLng.value, zoom.value);
    const pinPx = latLngToWorldPx(selectedCoords.value.lat, selectedCoords.value.lng, zoom.value);

    return {
        x: viewportWidth.value / 2 + (pinPx.x - centerPx.x),
        y: viewportHeight.value / 2 + (pinPx.y - centerPx.y),
    };
});

const geofenceRadiusPx = computed(() => {
    const targetLat = selectedCoords.value?.lat ?? centerLat.value;
    return metersToPixels(GEOFENCE_RADIUS_METERS, targetLat, zoom.value);
});

const googleMapsExternalUrl = computed<string | null>(() => {
    if (!selectedCoords.value) {
        return null;
    }
    return `https://www.google.com/maps?q=${selectedCoords.value.lat},${selectedCoords.value.lng}`;
});

function onPointerDown(event: PointerEvent): void {
    if (event.button !== 0 || !viewportEl.value) {
        return;
    }
    const worldCenter = latLngToWorldPx(centerLat.value, centerLng.value, zoom.value);
    isDragging.value = true;
    dragMoved.value = false;
    dragStartX.value = event.clientX;
    dragStartY.value = event.clientY;
    dragStartWorldX.value = worldCenter.x;
    dragStartWorldY.value = worldCenter.y;
    viewportEl.value.setPointerCapture(event.pointerId);
}

function onPointerMove(event: PointerEvent): void {
    if (!isDragging.value) {
        return;
    }
    const dx = event.clientX - dragStartX.value;
    const dy = event.clientY - dragStartY.value;

    if (Math.hypot(dx, dy) > DRAG_THRESHOLD_PX) {
        dragMoved.value = true;
    }

    if (dragMoved.value) {
        const nextCenter = worldPxToLatLng(dragStartWorldX.value - dx, dragStartWorldY.value - dy, zoom.value);
        centerLat.value = nextCenter.lat;
        centerLng.value = nextCenter.lng;
    }
}

function onPointerUp(event: PointerEvent): void {
    if (!isDragging.value || !viewportEl.value) {
        return;
    }
    const wasClick = !dragMoved.value;
    isDragging.value = false;
    if (viewportEl.value.hasPointerCapture(event.pointerId)) {
        viewportEl.value.releasePointerCapture(event.pointerId);
    }

    if (wasClick) {
        const rect = viewportEl.value.getBoundingClientRect();
        const clickOffsetX = event.clientX - rect.left - rect.width / 2;
        const clickOffsetY = event.clientY - rect.top - rect.height / 2;
        const worldCenter = latLngToWorldPx(centerLat.value, centerLng.value, zoom.value);
        const picked = worldPxToLatLng(worldCenter.x + clickOffsetX, worldCenter.y + clickOffsetY, zoom.value);

        emit('update:coordinates', {
            lat: picked.lat.toFixed(6),
            lng: picked.lng.toFixed(6),
        });
    }
}

function adjustZoom(delta: number): void {
    const next = Math.max(MIN_ZOOM, Math.min(MAX_ZOOM, zoom.value + delta));
    if (next !== zoom.value) {
        failedTileCount.value = 0;
        zoom.value = next;
    }
}

function recenterToPin(): void {
    if (!selectedCoords.value) {
        return;
    }
    centerLat.value = selectedCoords.value.lat;
    centerLng.value = selectedCoords.value.lng;
}
</script>

<template>
    <div class="space-y-1.5">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <span class="text-xs font-medium text-content"> Peta lokasi proyek (radius 300 m) </span>
            <div class="flex items-center gap-3 text-xs">
                <button
                    v-if="selectedCoords"
                    type="button"
                    class="font-medium text-content-secondary hover:text-content hover:underline"
                    @click="recenterToPin"
                >
                    Pusatkan ke pin
                </button>
                <a
                    v-if="googleMapsExternalUrl"
                    :href="googleMapsExternalUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="font-medium text-primary hover:underline"
                >
                    Buka di Google Maps
                </a>
            </div>
        </div>

        <div
            ref="viewportEl"
            :class="[
                'relative h-60 w-full touch-none overflow-hidden rounded-lg border bg-surface-subtle select-none',
                hasError ? 'border-negative-solid' : 'border-stroke-strong/50',
                isDragging ? 'cursor-grabbing' : 'cursor-crosshair',
            ]"
            role="application"
            aria-label="Peta pemilih koordinat proyek. Klik peta untuk menandai titik atau geser untuk menggerakkan peta."
            @pointerdown="onPointerDown"
            @pointermove="onPointerMove"
            @pointerup="onPointerUp"
            @pointercancel="isDragging = false"
        >
            <!-- Fallback Subtle Grid (visible while tiles load or if offline) -->
            <div
                class="pointer-events-none absolute inset-0 opacity-30"
                style="
                    background-image:
                        linear-gradient(to right, var(--color-stroke) 1px, transparent 1px),
                        linear-gradient(to bottom, var(--color-stroke) 1px, transparent 1px);
                    background-size: 28px 28px;
                "
            />

            <!-- OSM Slippy Tiles -->
            <img
                v-for="tile in visibleTiles"
                :key="`${tile.z}-${tile.x}-${tile.y}`"
                :src="tile.url"
                alt=""
                draggable="false"
                class="pointer-events-none absolute h-[256px] w-[256px] max-w-none"
                :style="{ left: `${tile.leftPx}px`, top: `${tile.topPx}px` }"
                @error="failedTileCount++"
            />

            <!-- 300 m Geofence Circle + Pin Overlay -->
            <div
                v-if="pinPosition"
                class="pointer-events-none absolute -translate-x-1/2 -translate-y-1/2"
                :style="{ left: `${pinPosition.x}px`, top: `${pinPosition.y}px` }"
            >
                <div
                    class="flex items-center justify-center rounded-full border-2 border-dashed border-primary bg-primary/15"
                    :style="{
                        width: `${Math.max(24, geofenceRadiusPx * 2)}px`,
                        height: `${Math.max(24, geofenceRadiusPx * 2)}px`,
                    }"
                >
                    <div
                        class="relative h-4 w-4 rounded-full border-2 border-surface bg-negative-solid shadow-sm"
                    ></div>
                </div>
            </div>

            <!-- Empty Prompt Overlay -->
            <div v-if="!selectedCoords" class="pointer-events-none absolute inset-x-4 top-3 flex justify-center">
                <span
                    class="rounded-md border border-stroke bg-surface/95 px-3 py-1.5 text-center text-xs font-medium text-content shadow-sm"
                >
                    Belum ada titik dipilih — klik di peta untuk menandai lokasi proyek
                </span>
            </div>

            <!-- Top-Right Zoom Controls -->
            <div
                class="absolute right-2.5 bottom-7 z-10 flex flex-col overflow-hidden rounded-md border border-stroke bg-surface shadow-sm"
                @pointerdown.stop
            >
                <button
                    type="button"
                    class="flex h-8 w-8 items-center justify-center border-b border-stroke text-sm font-semibold text-content hover:bg-surface-muted disabled:opacity-40"
                    :disabled="zoom >= MAX_ZOOM"
                    aria-label="Perbesar peta"
                    @click="adjustZoom(1)"
                >
                    +
                </button>
                <button
                    type="button"
                    class="flex h-8 w-8 items-center justify-center text-sm font-semibold text-content hover:bg-surface-muted disabled:opacity-40"
                    :disabled="zoom <= MIN_ZOOM"
                    aria-label="Perkecil peta"
                    @click="adjustZoom(-1)"
                >
                    &minus;
                </button>
            </div>

            <!-- Bottom Helper & Attribution -->
            <div
                class="pointer-events-none absolute right-2 bottom-1.5 left-2 flex items-center justify-between gap-2 text-xs"
            >
                <span class="rounded bg-surface/90 px-2 py-0.5 font-medium text-content-secondary shadow-sm">
                    {{
                        selectedCoords
                            ? 'Klik peta untuk pindahkan pin · Geser untuk pantau'
                            : 'Geser peta · Zoom ' + zoom
                    }}
                </span>
                <span class="rounded bg-surface/90 px-1.5 py-0.5 text-content-tertiary shadow-sm">
                    &copy; OpenStreetMap
                </span>
            </div>
        </div>
    </div>
</template>
