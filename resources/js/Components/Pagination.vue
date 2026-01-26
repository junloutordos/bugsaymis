<template>
  <nav v-if="links && links.length" class="inline-flex -space-x-px">
    <a v-for="(link, idx) in links" :key="idx" :href="link.url || '#'" v-html="link.label" class="px-3 py-1 border bg-white text-sm" :class="{ 'opacity-50': !link.url }"></a>
  </nav>
</template>

<script setup>
defineProps({
  links: { type: Array, default: () => [] }
})
</script>

<style scoped>
.opacity-50 { opacity: 0.5 }
</style>
