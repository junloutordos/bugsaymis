<script setup>
/**
 * PaginationControl — unified pagination for both local and server-side data.
 *
 * LOCAL pagination (client-side slice):
 *   <PaginationControl :current-page="page" :total-pages="pages" :total="items.length"
 *                      @prev="page--" @next="page++" />
 *
 * SERVER-SIDE pagination (Laravel paginator):
 *   <PaginationControl :links="paginator.links" :total="paginator.total" />
 *   (links is the `data.links` array from a Laravel paginate() response)
 */
import { Link } from '@inertiajs/vue3'

defineProps({
  // ── Local pagination ──────────────────────────────────────────────────────
  currentPage: { type: Number, default: null },
  totalPages:  { type: Number, default: null },

  // ── Server-side pagination ────────────────────────────────────────────────
  /** Array of { url, label, active } from Laravel paginator */
  links: { type: Array, default: null },

  // ── Shared ────────────────────────────────────────────────────────────────
  /** Total record count shown as "N records" label */
  total: { type: Number, default: null },
})

const emit = defineEmits(['prev', 'next'])

/** Strip HTML from "Previous" / "Next" labels, keep &laquo;/&raquo; readable */
const isNavLink = (label) => label.includes('&laquo;') || label.includes('&raquo;')
</script>

<template>
  <!-- ── SERVER-SIDE mode ──────────────────────────────────────────────────── -->
  <div v-if="links"
    class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-xs text-slate-500 flex-wrap gap-2">

    <span v-if="total !== null">{{ total.toLocaleString('en-PH') }} records</span>
    <span v-else />

    <div class="flex gap-1 flex-wrap">
      <Link v-for="link in links" :key="link.label"
        :href="link.url ?? '#'"
        v-html="link.label"
        :class="[
          'inline-flex items-center justify-center min-w-[32px] px-2.5 py-1.5 rounded-lg text-xs font-medium border transition-colors',
          link.active
            ? 'bg-indigo-600 text-white border-indigo-600 pointer-events-none'
            : link.url
              ? 'border-slate-200 text-slate-600 hover:bg-slate-50'
              : 'border-slate-100 text-slate-300 pointer-events-none',
        ]"
      />
    </div>
  </div>

  <!-- ── LOCAL mode ────────────────────────────────────────────────────────── -->
  <div v-else-if="totalPages > 1"
    class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-xs text-slate-500">
    <span>
      Page {{ currentPage }} of {{ totalPages }}
      <span v-if="total !== null"> · {{ total.toLocaleString('en-PH') }} records</span>
    </span>
    <div class="flex gap-2">
      <button
        @click="emit('prev')"
        :disabled="currentPage === 1"
        class="px-3 py-1.5 border border-slate-200 rounded-lg text-xs font-medium hover:bg-slate-50 disabled:opacity-40 transition-colors">
        ← Prev
      </button>
      <button
        @click="emit('next')"
        :disabled="currentPage === totalPages"
        class="px-3 py-1.5 border border-slate-200 rounded-lg text-xs font-medium hover:bg-slate-50 disabled:opacity-40 transition-colors">
        Next →
      </button>
    </div>
  </div>
</template>
