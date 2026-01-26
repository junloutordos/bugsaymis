<script setup>
defineProps({
  href: String,
  icon: [Object, Function],   // ✅ accepts Vue components like Heroicons
  label: String,
  collapsed: Boolean,
  active: Boolean,
  badge: { type: [Number, String], default: 0 },
  target: { type: String, default: null },
});

</script>

<template>
  <a
    :href="href"
    :target="target"
    :rel="target === '_blank' ? 'noopener noreferrer' : null"
    class="flex items-center px-3 py-2 rounded-md transition font-medium"
    :class="[
      active
        ? 'bg-blue-100 text-blue-600'   // stays blue when active
        : 'text-gray-700 hover:bg-blue-100 hover:text-blue-600'
    ]"
  >
    <component
      v-if="icon"
      :is="icon"
      class="h-5 w-5"
      :class="[collapsed ? 'mx-auto' : 'mr-2']"
    />
    <span v-if="!collapsed" class="flex items-center w-full">
      <span>{{ label }}</span>
      <span v-if="badge && !collapsed" class="ml-2 inline-flex items-center justify-center px-2 py-0.5 text-xs font-semibold leading-none text-white bg-red-500 rounded-full">{{ badge }}</span>
    </span>
    <span v-else-if="badge && collapsed" class="mx-auto inline-block h-2 w-2 rounded-full bg-red-500"></span>
  </a>
</template>
