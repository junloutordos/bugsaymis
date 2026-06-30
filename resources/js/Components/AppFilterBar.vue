<script setup>
defineProps({
  resultLabel: { type: String, default: null },
})
</script>

<template>
  <div class="mb-4 rounded-xl border border-slate-100 bg-white p-4 shadow-sm">
    <div class="flex flex-wrap items-end gap-3">
      <slot />
      <div v-if="$slots.actions || resultLabel" class="ml-auto flex items-center gap-3 self-end">
        <span v-if="resultLabel" class="pb-0.5 text-xs text-slate-400">{{ resultLabel }}</span>
        <slot name="actions" />
      </div>
    </div>
  </div>
</template>
