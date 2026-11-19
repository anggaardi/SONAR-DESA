<script setup lang="ts">
// Button: base interactive control. docs/DESIGN.md §6.
// - primary: bg-primary, h-11 mobile / h-10 desktop. ONE per screen (PRD 6.1).
// - secondary: border bg-surface text-content.
// - ghost: text-primary only.
// - danger: text-negative border-negative-border. Only for destructive actions.
// If `href` is passed, renders as an Inertia <Link>.

import { Link } from '@inertiajs/vue3';

type Variant = 'primary' | 'secondary' | 'ghost' | 'danger';
type Size = 'md' | 'sm';

interface Props {
    href?: string;
    variant?: Variant;
    size?: Size;
    type?: 'button' | 'submit' | 'reset';
    disabled?: boolean;
    loading?: boolean;
}

withDefaults(defineProps<Props>(), {
    href: undefined,
    variant: 'secondary',
    size: 'md',
    type: 'button',
    disabled: false,
    loading: false,
});

const variantClasses: Record<Variant, string> = {
    primary: 'bg-primary text-content-inverse hover:bg-primary-hover focus-visible:outline-primary',
    secondary:
        'border border-stroke-strong bg-surface text-content hover:bg-surface-muted focus-visible:outline-primary',
    ghost: 'text-primary hover:bg-primary-subtle focus-visible:outline-primary',
    danger: 'border border-negative-border bg-surface text-negative hover:bg-negative-subtle focus-visible:outline-negative-solid',
};

const sizeClasses: Record<Size, string> = {
    md: 'h-11 px-4 text-base',
    sm: 'h-9 px-3 text-sm',
};
</script>

<template>
    <Link
        v-if="href"
        :href="href"
        :class="[
            'inline-flex items-center justify-center gap-2 rounded-md font-medium transition-colors',
            'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2',
            variantClasses[variant],
            sizeClasses[size],
            (disabled || loading) && 'pointer-events-none opacity-50',
        ]"
    >
        <slot />
    </Link>
    <button
        v-else
        :type="type"
        :disabled="disabled || loading"
        :aria-disabled="disabled || loading"
        :class="[
            'inline-flex items-center justify-center gap-2 rounded-md font-medium transition-colors',
            'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2',
            variantClasses[variant],
            sizeClasses[size],
            (disabled || loading) && 'cursor-not-allowed opacity-50',
        ]"
    >
        <!-- Loading spinner: label stays visible for screen readers -->
        <svg v-if="loading" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
        </svg>

        <slot />
    </button>
</template>
