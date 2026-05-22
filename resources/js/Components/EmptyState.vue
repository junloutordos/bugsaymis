<script setup>
import { InboxIcon } from '@heroicons/vue/24/outline'

defineProps({
  title:    { type: String, default: 'No records found' },
  subtitle: { type: String, default: null },
  icon:     { type: Object, default: null },
})
</script>

<template>
  <div class="py-14 text-center text-slate-400">
    <component
      :is="icon ?? InboxIcon"
      class="h-10 w-10 mx-auto mb-3 text-slate-300" />
    <p class="text-sm font-medium text-slate-500">{{ title }}</p>
    <p v-if="subtitle" class="text-xs text-slate-400 mt-1">{{ subtitle }}</p>
    <slot />
  </div>
</template>
