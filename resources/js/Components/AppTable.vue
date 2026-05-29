<script setup>
/**
 * AppTable — the authoritative table component for BugSayMis.
 *
 * Provides the full shell: card wrapper, scroll container, thead/tbody
 * semantics, loading skeleton, and optional empty/footer slots.
 *
 * Slots:
 *   head    — <tr> with <th> cells (required)
 *   default — one or more <tr> rows (required)
 *   empty   — shown when isEmpty=true; defaults to a centred "No records" message
 *   footer  — bottom area (pagination, totals, etc.)
 *
 * Props:
 *   loading      — show shimmer skeleton rows instead of slot content
 *   isEmpty      — when true shows the empty slot instead of body rows
 *   skeletonCols — number of skeleton columns while loading (default 5)
 *   card         — wrap in the standard bg-white card shell (default true)
 *
 * Example:
 *   <AppTable :loading="loading" :is-empty="!items.length">
 *     <template #head>
 *       <th :class="TH">Name</th>
 *       <th :class="TH_C">Status</th>
 *     </template>
 *     <tr v-for="item in items" :key="item.id" :class="TR_CLICK" @click="open(item)">
 *       <td :class="TD">{{ item.name }}</td>
 *       <td :class="TD + ' text-center'">{{ item.status }}</td>
 *     </tr>
 *     <template #empty>
 *       <EmptyState title="No items found" />
 *     </template>
 *     <template #footer>
 *       <PaginationControl :current-page="page" :total-pages="total" @prev="page--" @next="page++" />
 *     </template>
 *   </AppTable>
 */
import { computed } from 'vue'
import { InboxIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  loading:      { type: Boolean, default: false },
  isEmpty:      { type: Boolean, default: false },
  skeletonCols: { type: Number,  default: 5 },
  card:         { type: Boolean, default: true },
})

const skeletonWidths = ['w-3/4', 'w-1/2', 'w-2/3', 'w-4/5', 'w-1/3']
const widthFor = (i) => skeletonWidths[i % skeletonWidths.length]
</script>

<template>
  <!-- Outer card shell (optional) -->
  <div :class="card ? 'bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden' : ''">

    <!-- Horizontal scroll container -->
    <div class="overflow-x-auto">
      <table class="min-w-full divide-y divide-slate-100 text-sm">

        <!-- Header -->
        <thead class="bg-slate-50">
          <slot name="head" />
        </thead>

        <!-- Body: loading skeleton -->
        <tbody v-if="loading" class="divide-y divide-slate-100 bg-white">
          <tr v-for="r in 5" :key="r" class="animate-pulse">
            <td v-for="c in skeletonCols" :key="c" class="px-4 py-3">
              <div class="h-3.5 rounded-full bg-slate-200" :class="widthFor(c)" />
            </td>
          </tr>
        </tbody>

        <!-- Body: empty state -->
        <tbody v-else-if="isEmpty" class="bg-white">
          <tr>
            <td :colspan="skeletonCols" class="py-14 text-center">
              <slot name="empty">
                <InboxIcon class="h-10 w-10 mx-auto mb-3 text-slate-300" />
                <p class="text-sm font-medium text-slate-500">No records found</p>
              </slot>
            </td>
          </tr>
        </tbody>

        <!-- Body: normal rows -->
        <tbody v-else class="divide-y divide-slate-100 bg-white">
          <slot />
        </tbody>

      </table>
    </div>

    <!-- Footer (pagination, totals, etc.) -->
    <div v-if="$slots.footer" class="border-t border-slate-100 bg-white">
      <slot name="footer" />
    </div>

  </div>
</template>
