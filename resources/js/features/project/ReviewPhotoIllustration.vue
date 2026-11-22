<script setup lang="ts">
// ReviewPhotoIllustration: vector stand-in for a field photo (dummy, see reviewPhotos.ts).
// The literal fills draw the scene itself, not UI chrome, so they are not design tokens.

import type { ReviewPhotoScene } from './reviewPhotos';

defineProps<{
    scene: ReviewPhotoScene;
}>();
</script>

<template>
    <svg viewBox="0 0 180 100" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
        <g v-if="scene === 'night'">
            <rect width="180" height="100" fill="#0f172a" />
            <path d="M0 100 L54 55 L126 55 L180 100 Z" fill="#1e293b" />
            <path d="M90 60 L90 95" stroke="#eab308" stroke-width="1.5" stroke-dasharray="4 3" />
            <path d="M117 75 V22 Q117 15 103 14" stroke="#94a3b8" stroke-width="2.2" fill="none" />
            <rect x="111" y="10" width="16" height="5" rx="1" fill="#1d4ed8" stroke="#e2e8f0" stroke-width="0.8" />
            <rect x="99" y="14" width="8" height="4" rx="1" fill="#ffffff" />
            <path d="M103 18 L71 82 L109 82 Z" fill="#fef08a" opacity="0.22" />
        </g>

        <g v-else-if="scene === 'crack'">
            <rect width="180" height="100" fill="#e2e8f0" />
            <rect y="55" width="180" height="45" fill="#cbd5e1" />
            <rect x="36" y="25" width="108" height="50" rx="3" fill="#94a3b8" />
            <rect x="50" y="35" width="80" height="30" rx="2" fill="#475569" />
            <circle cx="63" cy="42" r="3" fill="#cbd5e1" />
            <circle cx="117" cy="42" r="3" fill="#cbd5e1" />
            <circle cx="63" cy="58" r="3" fill="#cbd5e1" />
            <circle cx="117" cy="58" r="3" fill="#ef4444" />
            <path d="M117 58 l6 5 l-4 6 l8 5" stroke="#dc2626" stroke-width="1.8" fill="none" />
            <rect x="86" y="68" width="48" height="12" rx="2" fill="#facc15" stroke="#854d0e" stroke-width="0.8" />
            <text x="94" y="77" fill="#854d0e" font-size="8.5" font-weight="bold">2.0 cm</text>
        </g>

        <g v-else-if="scene === 'gate'">
            <rect width="180" height="100" fill="#7dd3fc" />
            <circle cx="18" cy="40" r="18" fill="#15803d" />
            <circle cx="162" cy="40" r="20" fill="#166534" />
            <rect y="55" width="180" height="45" fill="#64748b" />
            <rect x="72" y="25" width="12" height="45" rx="1" fill="#9a3412" />
            <rect x="70" y="28" width="16" height="4" fill="#7c2d12" />
            <rect x="74" y="18" width="8" height="8" fill="#9a3412" />
            <rect x="96" y="25" width="12" height="45" rx="1" fill="#9a3412" />
            <rect x="94" y="28" width="16" height="4" fill="#7c2d12" />
            <rect x="98" y="18" width="8" height="8" fill="#9a3412" />
            <path d="M84 70 L78 100 L102 100 L96 70 Z" fill="#cbd5e1" />
        </g>

        <g v-else-if="scene === 'monument'">
            <rect width="180" height="100" fill="#67e8f9" />
            <rect y="60" width="180" height="40" fill="#475569" />
            <circle cx="30" cy="50" r="16" fill="#15803d" />
            <circle cx="150" cy="50" r="18" fill="#166534" />
            <rect x="70" y="50" width="40" height="25" rx="2" fill="#78716c" />
            <path d="M90 28 L98 38 L114 34 L102 44 L106 50 L90 46 L74 50 L78 44 L66 34 L82 38 Z" fill="#1c1917" />
        </g>

        <g v-else-if="scene === 'bpd'">
            <rect width="180" height="100" fill="#f1f5f9" />
            <rect x="25" y="12" width="130" height="76" rx="3" fill="#ffffff" stroke="#cbd5e1" stroke-width="1" />
            <path d="M35 25 H145 M35 40 H120 M35 55 H135" stroke="#94a3b8" stroke-width="1.6" />
            <circle cx="125" cy="65" r="14" fill="#1d4ed8" opacity="0.15" />
            <circle cx="125" cy="65" r="10" stroke="#1d4ed8" stroke-width="1.2" fill="none" />
            <text x="125" y="69" fill="#1d4ed8" font-size="8" font-weight="bold" text-anchor="middle">BPD</text>
        </g>

        <g v-else>
            <rect width="180" height="100" fill="#93c5fd" />
            <circle cx="158" cy="18" r="8" fill="#fef08a" />
            <circle cx="15" cy="65" r="24" fill="#22c55e" />
            <rect y="70" width="180" height="30" fill="#475569" />
            <path d="M99 80 V18" stroke="#e2e8f0" stroke-width="2.2" />
            <g transform="translate(85 8) rotate(-15)">
                <rect width="24" height="7" rx="1" fill="#1e3a8a" stroke="#e2e8f0" stroke-width="0.8" />
            </g>
            <path d="M99 20 h-10 v4" stroke="#e2e8f0" stroke-width="1.6" fill="none" />
            <rect x="85" y="24" width="8" height="4" rx="1" fill="#f8fafc" />
        </g>
    </svg>
</template>
