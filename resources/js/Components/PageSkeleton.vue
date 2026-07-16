<template>
  <div role="status" aria-label="Loading..." class="animate-skeleton relative w-full">

    <!-- Branded focal indicator over the skeleton -->
    <div class="pointer-events-none absolute inset-x-0 top-24 z-10 flex justify-center">
      <AtlasMarkLoader caption="Wayfinding" />
    </div>

    <!-- Page header -->
    <div class="mb-6 space-y-2">
      <div class="skeleton-block h-6 w-2/5 rounded-lg" />
      <div class="skeleton-block h-4 w-1/4 rounded-md" />
    </div>

    <!-- Table variant (default) -->
    <template v-if="type === 'table'">
      <!-- Toolbar -->
      <div class="mb-4 flex items-center gap-3">
        <div class="skeleton-block h-8 w-24 rounded-lg" />
        <div class="skeleton-block h-8 w-20 rounded-lg" />
        <div class="skeleton-block ml-auto h-8 w-48 rounded-lg" />
      </div>

      <!-- Table card -->
      <div class="rounded-xl border border-slate-100 bg-white shadow-sm overflow-hidden">
        <!-- Header row -->
        <div class="flex items-center gap-4 border-b border-slate-100 bg-slate-50 px-4 py-3">
          <div class="skeleton-block h-3 w-8 rounded" />
          <div class="skeleton-block h-3 w-32 rounded" />
          <div class="skeleton-block h-3 w-24 rounded" />
          <div class="skeleton-block h-3 w-20 rounded ml-auto" />
          <div class="skeleton-block h-3 w-16 rounded" />
        </div>

        <!-- Data rows -->
        <div v-for="(row, i) in rows" :key="i"
          class="flex items-center gap-4 border-b border-slate-50 px-4 py-3 last:border-0">
          <div class="skeleton-block h-3 rounded" :style="{ width: row[0] }" />
          <div class="skeleton-block h-3 rounded" :style="{ width: row[1] }" />
          <div class="skeleton-block h-3 rounded" :style="{ width: row[2] }" />
          <div class="skeleton-block h-3 rounded ml-auto" :style="{ width: row[3] }" />
          <div class="skeleton-block h-3 w-16 rounded" />
        </div>
      </div>
    </template>

    <!-- Form variant -->
    <template v-else-if="type === 'form'">
      <div class="rounded-xl border border-slate-100 bg-white shadow-sm p-6 space-y-6">
        <div v-for="n in 5" :key="n" class="space-y-2">
          <div class="skeleton-block h-3 w-24 rounded" />
          <div class="skeleton-block h-9 w-full rounded-lg" />
        </div>
        <div class="flex gap-3 pt-2">
          <div class="skeleton-block h-9 w-28 rounded-lg" />
          <div class="skeleton-block h-9 w-20 rounded-lg" />
        </div>
      </div>
    </template>

    <!-- Cards variant -->
    <template v-else-if="type === 'cards'">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <div v-for="n in 6" :key="n"
          class="rounded-xl border border-slate-100 bg-white shadow-sm p-5 space-y-3">
          <div class="skeleton-block h-4 w-3/4 rounded-md" />
          <div class="skeleton-block h-3 w-full rounded" />
          <div class="skeleton-block h-3 w-5/6 rounded" />
          <div class="skeleton-block h-3 w-2/3 rounded" />
          <div class="flex gap-2 pt-2">
            <div class="skeleton-block h-6 w-16 rounded-full" />
            <div class="skeleton-block h-6 w-14 rounded-full" />
          </div>
        </div>
      </div>
    </template>

  </div>
</template>

<script setup>
import AtlasMarkLoader from '@/Components/AtlasMarkLoader.vue'

defineProps({
  type: { type: String, default: 'table' },
})

// Row widths vary to look natural, not mechanical
const rows = [
  ['2rem',  '9rem',  '6rem',  '4rem'],
  ['2rem',  '11rem', '5rem',  '3.5rem'],
  ['2rem',  '7rem',  '8rem',  '5rem'],
  ['2rem',  '10rem', '4rem',  '4.5rem'],
  ['2rem',  '8rem',  '7rem',  '3rem'],
  ['2rem',  '6rem',  '9rem',  '4rem'],
]
</script>

<style scoped>
.skeleton-block {
  background: linear-gradient(
    90deg,
    #f1f5f9 0%,
    #e2e8f0 40%,
    #f1f5f9 80%
  );
  background-size: 200% 100%;
  animation: shimmer 1.6s ease-in-out infinite;
  flex-shrink: 0;
}

@keyframes shimmer {
  0%   { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}
</style>
