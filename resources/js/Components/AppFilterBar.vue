<script setup>
defineProps({
  resultLabel: { type: String, default: null },
})
</script>

<template>
  <div class="mb-4 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70">
    <div class="flex flex-wrap items-end gap-3">
      <slot />
      <div v-if="$slots.actions || resultLabel" class="ml-auto flex items-center gap-3 self-end">
        <span v-if="resultLabel" class="pb-0.5 text-xs text-slate-400">{{ resultLabel }}</span>
        <slot name="actions" />
      </div>
    </div>
  </div>
</template>
