<script setup>
/**
 * PaginationControl — unified pagination for both local and server-side data.
 *
 * LOCAL pagination (client-side slice):
 *   <PaginationControl :current-page="page" :total-pages="pages" :total="items.length"
 *                      @prev="page--" @next="page++" @page="page = $event" />
 *
 * SERVER-SIDE pagination (Laravel paginator):
 *   <PaginationControl :links="paginator.links" :current-page="paginator.current_page"
 *                      :total-pages="paginator.last_page" :total="paginator.total"
 *                      @page="goToPage" />
 *   (links is the `data.links` array from a Laravel paginate() response)
 */
import { computed, ref, useAttrs, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'

const props = defineProps({
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

const emit = defineEmits(['prev', 'next', 'page'])
const attrs = useAttrs()

const jumpPage = ref('')

const normalizedCurrentPage = computed(() => {
  if (props.currentPage) return props.currentPage
  const active = props.links?.find((link) => link.active && /^\d+$/.test(stripHtml(link.label)))
  return active ? Number(stripHtml(active.label)) : null
})

const normalizedTotalPages = computed(() => {
  if (props.totalPages) return props.totalPages
  const pages = props.links
    ?.map((link) => stripHtml(link.label))
    .filter((label) => /^\d+$/.test(label))
    .map(Number) ?? []
  return pages.length ? Math.max(...pages) : null
})

const canJump = computed(() => Number(normalizedTotalPages.value) > 1)

/** Strip HTML from "Previous" / "Next" labels, keep &laquo;/&raquo; readable */
const isNavLink = (label) => label.includes('&laquo;') || label.includes('&raquo;')
const stripHtml = (label) => String(label ?? '').replace(/<[^>]*>/g, '').replace(/&laquo;|&raquo;/g, '').trim()

function submitJump() {
  const totalPages = Number(normalizedTotalPages.value)
  if (!totalPages) return

  const parsed = Number.parseInt(String(jumpPage.value), 10)
  if (Number.isNaN(parsed)) {
    jumpPage.value = String(normalizedCurrentPage.value ?? 1)
    return
  }

  const page = Math.min(totalPages, Math.max(1, parsed))
  jumpPage.value = String(page)
  if (page === normalizedCurrentPage.value) return

  if (attrs.onPage) {
    emit('page', page)
    return
  }

  const target = props.links?.find((link) => stripHtml(link.label) === String(page))
  if (target?.url) router.visit(target.url, { preserveState: true, replace: true })
}

watch(normalizedCurrentPage, (page) => {
  jumpPage.value = page ? String(page) : ''
}, { immediate: true })
</script>

<template>
  <!-- ── SERVER-SIDE mode ──────────────────────────────────────────────────── -->
  <div v-if="links"
    class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-xs text-slate-500 flex-wrap gap-2">

    <span v-if="total !== null">{{ total.toLocaleString('en-PH') }} records</span>
    <span v-else />

    <form v-if="canJump" class="flex items-center gap-2" @submit.prevent="submitJump">
      <label class="text-xs text-slate-500" for="pagination-jump-server">Page</label>
      <input
        id="pagination-jump-server"
        v-model="jumpPage"
        type="number"
        min="1"
        :max="normalizedTotalPages"
        class="h-8 w-16 rounded-lg border border-slate-200 bg-white px-2 text-center text-xs font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500"
      />
      <span class="text-xs text-slate-400">of {{ normalizedTotalPages }}</span>
      <button
        type="submit"
        class="px-2.5 py-1.5 border border-slate-200 rounded-lg text-xs font-medium text-slate-600 hover:bg-slate-50 transition-colors">
        Go
      </button>
    </form>

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
      Page {{ normalizedCurrentPage }} of {{ normalizedTotalPages }}
      <span v-if="total !== null"> · {{ total.toLocaleString('en-PH') }} records</span>
    </span>
    <div class="flex items-center gap-2">
      <button
        @click="emit('prev')"
        :disabled="normalizedCurrentPage === 1"
        class="px-3 py-1.5 border border-slate-200 rounded-lg text-xs font-medium hover:bg-slate-50 disabled:opacity-40 transition-colors">
        ← Prev
      </button>
      <form class="flex items-center gap-1.5" @submit.prevent="submitJump">
        <label class="sr-only" for="pagination-jump-local">Page</label>
        <input
          id="pagination-jump-local"
          v-model="jumpPage"
          type="number"
          min="1"
          :max="normalizedTotalPages"
          class="h-8 w-16 rounded-lg border border-slate-200 bg-white px-2 text-center text-xs font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500"
        />
        <button
          type="submit"
          class="px-2.5 py-1.5 border border-slate-200 rounded-lg text-xs font-medium hover:bg-slate-50 transition-colors">
          Go
        </button>
      </form>
      <button
        @click="emit('next')"
        :disabled="normalizedCurrentPage === normalizedTotalPages"
        class="px-3 py-1.5 border border-slate-200 rounded-lg text-xs font-medium hover:bg-slate-50 disabled:opacity-40 transition-colors">
        Next →
      </button>
    </div>
  </div>
</template>
