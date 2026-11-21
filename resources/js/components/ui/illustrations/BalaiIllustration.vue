<script setup lang="ts">
// BalaiIllustration: Spot illustration of Balai Desa (IDDS flat line-art).
// Sourced from storage/app/design-refs/figcraft/lib.mjs & hp-desa.svg.

interface Props {
    size?: number | string;
}

withDefaults(defineProps<Props>(), {
    size: 112,
});
</script>

<template>
    <svg
        :width="size"
        :height="size"
        viewBox="0 0 120 120"
        fill="none"
        xmlns="http://www.w3.org/2000/svg"
        aria-hidden="true"
        class="shrink-0"
    >
        <path
            d="M38 8H82C100 8 112 20 112 38V104C112 108.4 108.4 112 104 112H16C11.6 112 8 108.4 8 104V38C8 20 20 8 38 8Z"
            fill="#DCEAFE"
        />
        <g transform="translate(20 102) scale(0.9)">
            <path d="M0 0C-8 -6 -10 -18 -4 -28C4 -20 6 -8 0 0Z" fill="#8DC1FF" />
            <path d="M1 0C8 -4 16 -4 22 2C14 8 6 6 1 0Z" fill="#8DC1FF" />
        </g>
        <g transform="translate(100 102) scale(0.9)">
            <path d="M0 0C-8 -6 -10 -18 -4 -28C4 -20 6 -8 0 0Z" fill="#629CEF" />
            <path d="M1 0C8 -4 16 -4 22 2C14 8 6 6 1 0Z" fill="#629CEF" />
        </g>
        <rect x="26" y="54" width="68" height="46" rx="4" fill="#FFFFFF" />
        <path d="M18 58L60 30L102 58Z" fill="#196BCD" stroke="#196BCD" stroke-width="6" stroke-linejoin="round" />
        <rect x="52" y="72" width="16" height="28" rx="4" fill="#196BCD" />
        <rect x="32" y="64" width="14" height="12" rx="3" fill="#8DC1FF" />
        <rect x="74" y="64" width="14" height="12" rx="3" fill="#8DC1FF" />
        <rect x="22" y="98" width="76" height="6" rx="3" fill="#4F5659" />
    </svg>
</template>
