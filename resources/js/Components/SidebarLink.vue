<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'

const props = defineProps({
  href:      String,
  icon:      [Object, Function],
  label:     String,
  collapsed: Boolean,
  active:    Boolean,
  badge:     { type: [Number, String], default: 0 },
  target:    { type: String, default: null },
})

const badgeCount = computed(() => {
  const n = parseInt(props.badge, 10)
  return isNaN(n) || n <= 0 ? 0 : Math.min(n, 99)
})

const badgeLabel = computed(() => badgeCount.value > 0 ? String(badgeCount.value) : '')
</script>

<template>
  <Link
    :href="href"
    :target="target"
    :rel="target === '_blank' ? 'noopener noreferrer' : null"
    :title="collapsed ? label : null"
    class="group relative flex items-center rounded-lg px-3 py-2 text-sm font-medium transition-all duration-150"
    :class="[
      active
        ? 'bg-indigo-600 text-white shadow-sm shadow-indigo-600/30'
        : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
    ]"
  >
    <component
      v-if="icon"
      :is="icon"
      class="h-4 w-4 shrink-0 transition-colors"
      :class="[
        collapsed ? 'mx-auto' : 'mr-2.5',
        active ? 'text-white' : 'text-slate-400 group-hover:text-slate-600'
      ]"
    />
    <span v-if="!collapsed" class="flex flex-1 items-center min-w-0">
      <span class="truncate">{{ label }}</span>
      <span
        v-if="badgeCount > 0"
        class="ml-auto shrink-0 inline-flex items-center justify-center rounded-full px-1.5 py-0.5 text-[10px] font-bold leading-none"
        :class="active ? 'bg-white/25 text-white' : 'bg-amber-400 text-slate-900'"
      >{{ badgeLabel }}</span>
    </span>
    <span
      v-else-if="badgeCount > 0"
      class="absolute top-1 right-1 h-1.5 w-1.5 rounded-full"
      :class="active ? 'bg-white' : 'bg-amber-400'"
    ></span>
  </Link>
</template>
