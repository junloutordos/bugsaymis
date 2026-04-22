<template>
  <Head title="Faculty Load Report" />
  <AdminLayout title="Faculty Load Report">
    <div class="space-y-5">

      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Faculty Load Report</h1>
          <p class="text-sm text-slate-500 mt-0.5">Load summary per faculty for the selected academic term</p>
        </div>
        <a :href="exportUrl" target="_blank"
          class="inline-flex items-center gap-2 px-4 py-2 text-sm bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium transition-colors shadow-sm shrink-0">
          <ArrowDownTrayIcon class="h-4 w-4" /> Export CSV
        </a>
      </div>

      <!-- Filters -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm px-4 py-3 flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-[180px]">
          <label class="block text-xs font-medium text-slate-500 mb-1">Academic Term</label>
          <select v-model="filters.term_id" @change="applyFilters"
            class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            <option value="">All Terms</option>
            <option v-for="t in terms" :key="t.id" :value="t.id">
              {{ t.label }}{{ t.is_current ? ' (current)' : '' }}
            </option>
          </select>
        </div>
        <div class="w-40">
          <label class="block text-xs font-medium text-slate-500 mb-1">Load Status</label>
          <select v-model="filters.status" @change="applyFilters"
            class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            <option value="">All Statuses</option>
            <option value="underload">Underload</option>
            <option value="full_load">Full Load</option>
            <option value="overload">Overload</option>
          </select>
        </div>
        <div class="flex-1 min-w-[180px]">
          <label class="block text-xs font-medium text-slate-500 mb-1">Search Faculty</label>
          <input v-model="filters.search" @input="debouncedApply" type="text" placeholder="Name..."
            class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
        </div>
        <button v-if="hasActiveFilter" @click="clearFilters"
          class="px-3 py-2 text-sm text-slate-500 hover:text-slate-700 border border-slate-200 rounded-lg">
          Clear
        </button>
      </div>

      <!-- Summary cards -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm px-4 py-3 text-center">
          <p class="text-2xl font-bold text-slate-800">{{ summary.total }}</p>
          <p class="text-xs text-slate-500 mt-0.5">Total Faculty</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm px-4 py-3 text-center">
          <p class="text-2xl font-bold text-red-600">{{ summary.overload }}</p>
          <p class="text-xs text-slate-500 mt-0.5">Overload</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm px-4 py-3 text-center">
          <p class="text-2xl font-bold text-emerald-600">{{ summary.full_load }}</p>
          <p class="text-xs text-slate-500 mt-0.5">Full Load</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm px-4 py-3 text-center">
          <p class="text-2xl font-bold text-amber-500">{{ summary.underload }}</p>
          <p class="text-xs text-slate-500 mt-0.5">Underload</p>
        </div>
      </div>

      <!-- Table -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
        <div v-if="loads.length === 0" class="py-16 text-center">
          <TableCellsIcon class="mx-auto h-12 w-12 text-slate-200 mb-3" />
          <p class="text-sm font-medium text-slate-500">No load records found</p>
          <p class="text-xs text-slate-400 mt-1">Try adjusting the filters above.</p>
        </div>
        <div v-else class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead>
              <tr class="text-xs text-slate-400 uppercase tracking-wide bg-slate-50">
                <th class="px-4 py-2.5 text-left w-8">#</th>
                <th class="px-4 py-2.5 text-left">Faculty</th>
                <th class="px-4 py-2.5 text-center">SG</th>
                <th class="px-4 py-2.5 text-right">Teaching</th>
                <th class="px-4 py-2.5 text-right">Research</th>
                <th class="px-4 py-2.5 text-right">Admin</th>
                <th class="px-4 py-2.5 text-right">Co-Curr.</th>
                <th class="px-4 py-2.5 text-right">Cmte.</th>
                <th class="px-4 py-2.5 text-right font-semibold">Total</th>
                <th class="px-4 py-2.5 text-right">Overload</th>
                <th class="px-4 py-2.5 text-center">Status</th>
                <th class="px-4 py-2.5 text-center">OL Approved</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
              <tr v-for="(l, i) in loads" :key="l.id" class="hover:bg-slate-50/50">
                <td class="px-4 py-2.5 text-slate-400 text-xs">{{ i + 1 }}</td>
                <td class="px-4 py-2.5">
                  <p class="font-medium text-slate-800">{{ l.faculty_name }}</p>
                  <p v-if="l.position" class="text-xs text-slate-400">{{ l.position }}</p>
                </td>
                <td class="px-4 py-2.5 text-center text-slate-600">{{ l.salary_grade ?? '—' }}</td>
                <td class="px-4 py-2.5 text-right text-slate-600">{{ l.teaching_units }}</td>
                <td class="px-4 py-2.5 text-right text-slate-600">{{ l.research_units }}</td>
                <td class="px-4 py-2.5 text-right text-slate-600">{{ l.admin_units }}</td>
                <td class="px-4 py-2.5 text-right text-slate-600">{{ l.cocurricular_units }}</td>
                <td class="px-4 py-2.5 text-right text-slate-600">{{ l.committee_units }}</td>
                <td class="px-4 py-2.5 text-right font-semibold text-slate-800">{{ l.total_units }}</td>
                <td class="px-4 py-2.5 text-right">
                  <span v-if="l.overload_units > 0" class="text-red-600 font-medium">+{{ l.overload_units }}</span>
                  <span v-else class="text-slate-300">—</span>
                </td>
                <td class="px-4 py-2.5 text-center">
                  <span :class="statusClass(l.load_status)"
                    class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium">
                    {{ statusLabel(l.load_status) }}
                  </span>
                </td>
                <td class="px-4 py-2.5 text-center">
                  <span v-if="l.load_status === 'overload'">
                    <CheckCircleIcon v-if="l.overload_approved" class="h-4 w-4 text-emerald-500 mx-auto" />
                    <span v-else class="text-xs text-amber-500 font-medium">Pending</span>
                  </span>
                  <span v-else class="text-slate-300 text-xs">—</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ArrowDownTrayIcon, CheckCircleIcon, TableCellsIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  loads:       { type: Array,  default: () => [] },
  terms:       { type: Array,  default: () => [] },
  currentTerm: { type: Object, default: null },
  filters:     { type: Object, default: () => ({}) },
  summary:     { type: Object, default: () => ({ total: 0, overload: 0, full_load: 0, underload: 0 }) },
})

const filters = ref({
  term_id: props.filters.term_id ?? props.currentTerm?.id ?? '',
  status:  props.filters.status ?? '',
  search:  props.filters.search ?? '',
})

const hasActiveFilter = computed(() =>
  filters.value.term_id !== (props.currentTerm?.id ?? '') ||
  filters.value.status !== '' ||
  filters.value.search !== ''
)

const exportUrl = computed(() => {
  const p = new URLSearchParams()
  if (filters.value.term_id) p.set('term_id', filters.value.term_id)
  if (filters.value.status)  p.set('status',  filters.value.status)
  if (filters.value.search)  p.set('search',  filters.value.search)
  return route('faculty-loading.reports.loads.export') + (p.toString() ? '?' + p.toString() : '')
})

function applyFilters() {
  router.get(route('faculty-loading.reports.loads'), {
    term_id: filters.value.term_id || undefined,
    status:  filters.value.status  || undefined,
    search:  filters.value.search  || undefined,
  }, { preserveState: true, replace: true })
}

let debounceTimer = null
function debouncedApply() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(applyFilters, 400)
}

function clearFilters() {
  filters.value = { term_id: props.currentTerm?.id ?? '', status: '', search: '' }
  applyFilters()
}

function statusLabel(s) {
  return { underload: 'Underload', full_load: 'Full Load', overload: 'Overload' }[s] ?? s
}

function statusClass(s) {
  return {
    underload: 'bg-amber-50 text-amber-700',
    full_load: 'bg-emerald-50 text-emerald-700',
    overload:  'bg-red-50 text-red-700',
  }[s] ?? 'bg-slate-50 text-slate-600'
}
</script>
