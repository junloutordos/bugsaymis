<script setup>
defineProps({
  label:    { type: String, required: true },
  variant:  { type: String, default: 'ghost' }, // ghost | primary | secondary | danger | success | warning
  size:     { type: String, default: 'md' },    // sm | md | lg
  type:     { type: String, default: 'button' },
  disabled: { type: Boolean, default: false },
})

const variantMap = {
  ghost:     'text-slate-500 hover:text-slate-700 hover:bg-slate-100',
  primary:   'bg-indigo-600 text-white hover:bg-indigo-700 shadow-sm',
  secondary: 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-50 shadow-sm',
  danger:    'text-slate-500 hover:text-red-600 hover:bg-red-50',
  success:   'text-slate-500 hover:text-emerald-600 hover:bg-emerald-50',
  warning:   'text-slate-500 hover:text-amber-600 hover:bg-amber-50',
}

const sizeMap = {
  sm: 'h-7 w-7 rounded-md',
  md: 'h-8 w-8 rounded-lg',
  lg: 'h-9 w-9 rounded-lg',
}
</script>

<template>
  <button
    :type="type"
    :disabled="disabled"
    :aria-label="label"
    :title="label"
    :class="[
      'inline-flex shrink-0 items-center justify-center transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1 disabled:cursor-not-allowed disabled:opacity-50',
      variantMap[variant] ?? variantMap.ghost,
      sizeMap[size] ?? sizeMap.md,
    ]"
  >
    <slot />
  </button>
</template>
