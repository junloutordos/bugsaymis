<!-- resources/js/Pages/Hello.vue -->
<script setup>
defineProps({ message: String })
</script>

<template>
  <div class="p-8">
    <h1 class="text-3xl font-bold mb-2">Hello from Inertia + Vue</h1>
    <p class="text-gray-600">{{ message }}</p>
  </div>
</template>
