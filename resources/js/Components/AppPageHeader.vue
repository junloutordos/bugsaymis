<script setup>
import AppBreadcrumb from '@/Components/AppBreadcrumb.vue'

defineProps({
  title:      { type: String, required: true },
  subtitle:   { type: String, default: null },
  breadcrumb: { type: Array,  default: null }, // [{ label, href? }]
})
</script>

<template>
  <div class="mb-6">
    <AppBreadcrumb v-if="breadcrumb?.length" :items="breadcrumb" />
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div>
        <h1 class="font-heading text-xl font-semibold text-slate-900 leading-tight tracking-tight">{{ title }}</h1>
        <p v-if="subtitle" class="mt-0.5 text-sm text-slate-500">{{ subtitle }}</p>
      </div>
      <div v-if="$slots.actions" class="flex items-center gap-2 shrink-0">
        <slot name="actions" />
      </div>
    </div>
  </div>
</template>
