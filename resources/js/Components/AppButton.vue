<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
  variant: { type: String, default: 'primary' }, // primary | secondary | danger | success | warning | ghost
  size:    { type: String, default: 'md' },        // sm | md | lg
  type:    { type: String, default: 'button' },
  disabled:{ type: Boolean, default: false },
  loading: { type: Boolean, default: false },
  block:   { type: Boolean, default: false },
  as:      { type: String, default: 'button' },     // button | a | link (Inertia <Link>)
  href:    { type: String, default: null },
  target:  { type: String, default: null },
})

const variantMap = {
  primary:   'bg-indigo-600 hover:bg-indigo-700 text-white shadow-sm shadow-indigo-600/25',
  secondary: 'bg-white border border-slate-200 hover:bg-slate-50 hover:border-slate-300 text-slate-700 shadow-sm',
  danger:    'bg-danger-600 hover:bg-danger-700 text-white shadow-sm shadow-danger-600/25',
  success:   'bg-success-600 hover:bg-success-700 text-white shadow-sm shadow-success-600/25',
  warning:   'bg-warning-500 hover:bg-warning-600 text-white shadow-sm shadow-warning-500/25',
  ghost:     'text-slate-600 hover:bg-slate-100',
}
const sizeMap = {
  sm: 'px-3 py-1.5 text-xs rounded-lg gap-1.5',
  md: 'px-4 py-2 text-sm rounded-lg gap-2',
  lg: 'px-5 py-2.5 text-sm rounded-lg gap-2',
}
</script>

<template>
  <component
    :is="as === 'link' ? Link : as === 'a' ? 'a' : 'button'"
    :type="as === 'button' ? type : null"
    :href="as === 'button' ? null : href"
    :target="target"
    :rel="target === '_blank' ? 'noopener noreferrer' : null"
    :disabled="as === 'button' ? (disabled || loading) : null"
    :aria-disabled="disabled || loading ? 'true' : null"
    :class="[
      'inline-flex items-center font-medium transition-all duration-150 active:scale-[0.98] focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-1 disabled:opacity-50 disabled:cursor-not-allowed',
      variantMap[variant] ?? variantMap.primary,
      sizeMap[size] ?? sizeMap.md,
      block ? 'w-full justify-center' : '',
      disabled || loading ? 'pointer-events-none opacity-50' : '',
    ]"
  >
    <svg v-if="loading" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4l3-3-3-3v4a8 8 0 00-8 8h4z"/>
    </svg>
    <slot />
  </component>
</template>
