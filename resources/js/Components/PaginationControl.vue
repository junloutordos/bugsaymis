<script setup>
defineProps({
  currentPage: { type: Number, required: true },
  totalPages:  { type: Number, required: true },
  total:       { type: Number, default: null },
})
const emit = defineEmits(['prev', 'next'])
</script>

<template>
  <div v-if="totalPages > 1"
    class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-xs text-slate-500">
    <span>
      Page {{ currentPage }} of {{ totalPages }}
      <span v-if="total !== null"> · {{ total.toLocaleString('en-PH') }} records</span>
    </span>
    <div class="flex gap-2">
      <button
        @click="emit('prev')"
        :disabled="currentPage === 1"
        class="px-3 py-1.5 border border-slate-200 rounded-lg hover:bg-slate-50 disabled:opacity-40 transition-colors">
        Prev
      </button>
      <button
        @click="emit('next')"
        :disabled="currentPage === totalPages"
        class="px-3 py-1.5 border border-slate-200 rounded-lg hover:bg-slate-50 disabled:opacity-40 transition-colors">
        Next
      </button>
    </div>
  </div>
</template>
