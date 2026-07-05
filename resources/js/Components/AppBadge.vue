<script setup>
const props = defineProps({
  color: { type: String, default: 'slate' }, // slate | indigo | green | amber | red | orange | blue | purple
})

const colorMap = {
  slate:  'bg-slate-100 text-slate-600',
  indigo: 'bg-indigo-50 text-indigo-700',
  blue:   'bg-blue-50 text-blue-700',
  green:  'bg-success-50 text-success-700',
  amber:  'bg-warning-50 text-warning-700',
  orange: 'bg-orange-50 text-orange-700',
  red:    'bg-danger-50 text-danger-600',
  purple: 'bg-purple-50 text-purple-700',
}

const cls = colorMap[props.color] ?? colorMap.slate
</script>

<template>
  <span :class="['inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium leading-none', cls]">
    <slot />
  </span>
</template>
