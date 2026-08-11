<script setup>
import AppBreadcrumb from '@/Components/AppBreadcrumb.vue'

defineProps({
  title:      { type: String, required: true },
  subtitle:   { type: String, default: null },
  breadcrumb: { type: Array,  default: null }, // [{ label, href? }]
  // Dashboard hero treatment: white card with the indigo gradient accent bar
  hero:       { type: Boolean, default: false },
})
</script>

<template>
  <div :class="hero ? 'relative mb-6 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200/70' : 'mb-6'">
    <div v-if="hero && $slots.cover" class="h-28 w-full">
      <slot name="cover" />
    </div>
    <div v-else-if="hero" class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-indigo-600 via-indigo-500 to-indigo-100" aria-hidden="true"></div>
    <div :class="hero ? 'px-4 py-5 sm:px-6' : ''">
      <AppBreadcrumb v-if="breadcrumb?.length" :items="breadcrumb" />
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 class="font-heading text-xl font-semibold text-slate-900 leading-tight tracking-tight">{{ title }}</h1>
          <p v-if="subtitle" class="mt-0.5 text-sm text-slate-500">{{ subtitle }}</p>
        </div>
        <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2 sm:justify-end">
          <slot name="actions" />
        </div>
      </div>
    </div>
  </div>
</template>
