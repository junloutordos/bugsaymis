<script setup>
defineProps({
  padded: { type: Boolean, default: true },
  title:  { type: String,  default: null },
  subtitle: { type: String, default: null },
})
</script>

<template>
  <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
    <div v-if="title || $slots.header" class="px-5 py-4 border-b border-slate-100 flex items-center justify-between gap-3">
      <div>
        <h3 v-if="title" class="text-sm font-semibold text-slate-700">{{ title }}</h3>
        <p v-if="subtitle" class="text-xs text-slate-500 mt-0.5">{{ subtitle }}</p>
      </div>
      <slot name="header" />
    </div>
    <div :class="padded ? 'p-5' : ''">
      <slot />
    </div>
  </div>
</template>
