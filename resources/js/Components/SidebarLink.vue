<script setup>
defineProps({
  href: String,
  icon: [Object, Function],   // ✅ accepts Vue components like Heroicons
  label: String,
  collapsed: Boolean,
  active: Boolean,
});

</script>

<template>
  <a
    :href="href"
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
    <span v-if="!collapsed">{{ label }}</span>
  </a>
</template>
