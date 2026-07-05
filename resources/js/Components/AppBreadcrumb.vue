<script setup>
import { ChevronRightIcon } from '@heroicons/vue/24/outline'
import { Link } from '@inertiajs/vue3'

defineProps({
  items: { type: Array, default: () => [] }, // [{ label, href? }]
})
</script>

<template>
  <nav aria-label="Breadcrumb" class="mb-1">
    <ol class="flex flex-wrap items-center gap-1 text-xs text-slate-500">
      <li v-for="(item, i) in items" :key="i" class="flex items-center gap-1">
        <ChevronRightIcon v-if="i > 0" class="h-3 w-3 text-slate-300 shrink-0" aria-hidden="true" />
        <Link
          v-if="item.href && i < items.length - 1"
          :href="item.href"
          class="hover:text-indigo-600 hover:underline transition-colors"
        >
          {{ item.label }}
        </Link>
        <span v-else :aria-current="i === items.length - 1 ? 'page' : null" class="text-slate-700 font-medium">
          {{ item.label }}
        </span>
      </li>
    </ol>
  </nav>
</template>
