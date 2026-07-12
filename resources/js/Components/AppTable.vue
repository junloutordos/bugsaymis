<script setup>
/**
 * AppTable — the authoritative table component for Atlas.
 *
 * Provides the full shell: card wrapper, scroll container, thead/tbody
 * semantics, loading skeleton, and optional empty/footer slots.
 *
 * Slots:
 *   head       — <tr> with <th> cells (required)
 *   default    — one or more <tr> rows (required)
 *   empty      — shown when isEmpty=true; defaults to a centred "No records" message
 *   footer     — bottom area (pagination, totals, etc.)
 *   mobileCard — optional; when provided, the <table> is hidden below the `sm`
 *                breakpoint and this slot (a stack of cards) renders instead,
 *                so the table stays desktop-only while mobile gets a card list.
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
  <div :class="card ? 'bg-white rounded-2xl shadow-sm ring-1 ring-slate-200/70 overflow-hidden' : ''">

    <!-- Horizontal scroll container -->
    <div :class="['overflow-x-auto', $slots.mobileCard ? 'hidden sm:block' : '']">
      <table class="min-w-full divide-y divide-slate-100 text-sm">

        <!-- Header -->
        <thead class="bg-slate-50/80 border-b border-slate-200/60">
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
                <span class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-slate-50 ring-1 ring-slate-100">
                  <InboxIcon class="h-7 w-7 text-slate-300" />
                </span>
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

    <!-- Mobile card list (opt-in via the mobileCard slot) -->
    <div v-if="$slots.mobileCard" class="sm:hidden divide-y divide-slate-100 bg-white">
      <div v-if="loading" class="p-4 space-y-4">
        <div v-for="r in 4" :key="r" class="animate-pulse space-y-2">
          <div class="h-3.5 w-2/3 rounded-full bg-slate-200" />
          <div class="h-3 w-1/2 rounded-full bg-slate-200" />
        </div>
      </div>
      <div v-else-if="isEmpty" class="py-14 text-center px-4">
        <slot name="empty">
          <span class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-slate-50 ring-1 ring-slate-100">
            <InboxIcon class="h-7 w-7 text-slate-300" />
          </span>
          <p class="text-sm font-medium text-slate-500">No records found</p>
        </slot>
      </div>
      <slot v-else name="mobileCard" />
    </div>

    <!-- Footer (pagination, totals, etc.) -->
    <div v-if="$slots.footer" class="border-t border-slate-100 bg-white">
      <slot name="footer" />
    </div>

  </div>
</template>
