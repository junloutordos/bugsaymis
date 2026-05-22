<script setup>
import { XMarkIcon } from '@heroicons/vue/24/outline'
defineEmits(['click'])
</script>

<template>
  <button
    type="button"
    @click="$emit('click')"
    class="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors"
    aria-label="Close">
    <XMarkIcon class="h-4 w-4" />
  </button>
</template>
