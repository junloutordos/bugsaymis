<template>
  <div class="flex flex-col sm:flex-row sm:items-start gap-1 sm:gap-3">
    <dt class="text-xs font-medium text-slate-500 sm:w-40 shrink-0">{{ label }}</dt>
    <dd class="text-sm text-slate-800">
      <slot>{{ value ?? '—' }}</slot>
    </dd>
  </div>
</template>

<script setup>
defineProps({ label: String, value: [String, Number] })
</script>
