<template>
  <Head title="Overload Pay Report" />
  <AdminLayout title="Overload Pay Report">
    <div class="space-y-5">

      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Overload Pay Report</h1>
          <p class="text-sm text-slate-500 mt-0.5">PHTR-based overload pay computations per faculty and term</p>
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
        <div class="w-44">
          <label class="block text-xs font-medium text-slate-500 mb-1">Status</label>
          <select v-model="filters.status" @change="applyFilters"
            class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            <option value="">All Statuses</option>
            <option value="for_approval">For Approval</option>
            <option value="approved">Approved</option>
            <option value="paid">Paid</option>
            <option value="rejected">Rejected</option>
          </select>
        </div>
        <button v-if="hasActiveFilter" @click="clearFilters"
          class="px-3 py-2 text-sm text-slate-500 hover:text-slate-700 border border-slate-200 rounded-lg">
          Clear
        </button>
      </div>

      <!-- Grand Total banner -->
      <div v-if="computations.length" class="bg-indigo-50 border border-indigo-100 rounded-xl px-4 py-3 flex items-center justify-between">
        <div>
          <p class="text-xs font-semibold text-indigo-700 uppercase tracking-wide">Total Overload Pay</p>
          <p class="text-2xl font-bold text-indigo-900 mt-0.5">{{ phpFmt(grandTotal) }}</p>
        </div>
        <div class="text-right">
          <p class="text-xs text-indigo-600">{{ computations.length }} computation{{ computations.length !== 1 ? 's' : '' }}</p>
          <p class="text-xs text-indigo-500 mt-0.5">
            {{ computations.filter(c => c.status === 'paid').length }} paid ·
            {{ computations.filter(c => c.status === 'approved').length }} approved
          </p>
        </div>
      </div>

      <!-- Table -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
        <div v-if="computations.length === 0" class="py-16 text-center">
          <BanknotesIcon class="mx-auto h-12 w-12 text-slate-200 mb-3" />
          <p class="text-sm font-medium text-slate-500">No overload computations found</p>
          <p class="text-xs text-slate-400 mt-1">Try adjusting the filters above.</p>
        </div>
        <div v-else class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead>
              <tr class="text-xs text-slate-400 uppercase tracking-wide bg-slate-50">
                <th class="px-4 py-2.5 text-left w-8">#</th>
                <th class="px-4 py-2.5 text-left">Faculty</th>
                <th class="px-4 py-2.5 text-center">SG</th>
                <th class="px-4 py-2.5 text-right">Annual Rate</th>
                <th class="px-4 py-2.5 text-right">PHTR</th>
                <th class="px-4 py-2.5 text-center">OL Units</th>
                <th class="px-4 py-2.5 text-center">Hrs/Week</th>
                <th class="px-4 py-2.5 text-center">Weeks</th>
                <th class="px-4 py-2.5 text-right font-semibold">Total Pay</th>
                <th class="px-4 py-2.5 text-center">Status</th>
                <th class="px-4 py-2.5 text-center">Approved</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
              <tr v-for="(c, i) in computations" :key="c.id" class="hover:bg-slate-50/50">
                <td class="px-4 py-2.5 text-slate-400 text-xs">{{ i + 1 }}</td>
                <td class="px-4 py-2.5">
                  <p class="font-medium text-slate-800">{{ c.faculty_name }}</p>
                  <p v-if="c.position" class="text-xs text-slate-400">{{ c.position }}</p>
                </td>
                <td class="px-4 py-2.5 text-center text-slate-600">{{ c.salary_grade ?? '—' }}</td>
                <td class="px-4 py-2.5 text-right text-slate-600 font-mono text-xs">{{ phpFmt(c.annual_rate) }}</td>
                <td class="px-4 py-2.5 text-right text-emerald-700 font-mono text-xs">{{ phpFmt(c.phtr) }}</td>
                <td class="px-4 py-2.5 text-center text-slate-600">{{ c.overload_units }}</td>
                <td class="px-4 py-2.5 text-center text-slate-600">{{ c.overload_hours }}</td>
                <td class="px-4 py-2.5 text-center text-slate-600">{{ c.term_weeks }}</td>
                <td class="px-4 py-2.5 text-right font-semibold text-slate-800">{{ phpFmt(c.total_overload_pay) }}</td>
                <td class="px-4 py-2.5 text-center">
                  <span :class="statusClass(c.status)"
                    class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium">
                    {{ statusLabel(c.status) }}
                  </span>
                </td>
                <td class="px-4 py-2.5 text-center text-xs text-slate-500">
                  {{ c.approved_at ?? '—' }}
                </td>
              </tr>
            </tbody>
            <tfoot>
              <tr class="bg-slate-50 border-t border-slate-200">
                <td colspan="8" class="px-4 py-2.5 text-right text-xs font-semibold text-slate-600 uppercase tracking-wide">
                  Grand Total
                </td>
                <td class="px-4 py-2.5 text-right font-bold text-slate-800">{{ phpFmt(grandTotal) }}</td>
                <td colspan="2"></td>
              </tr>
            </tfoot>
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
import { ArrowDownTrayIcon, BanknotesIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  computations: { type: Array,  default: () => [] },
  terms:        { type: Array,  default: () => [] },
  currentTerm:  { type: Object, default: null },
  filters:      { type: Object, default: () => ({}) },
  grandTotal:   { type: Number, default: 0 },
})

const filters = ref({
  term_id: props.filters.term_id ?? props.currentTerm?.id ?? '',
  status:  props.filters.status ?? '',
})

const hasActiveFilter = computed(() =>
  filters.value.term_id !== (props.currentTerm?.id ?? '') ||
  filters.value.status !== ''
)

const exportUrl = computed(() => {
  const p = new URLSearchParams()
  if (filters.value.term_id) p.set('term_id', filters.value.term_id)
  if (filters.value.status)  p.set('status',  filters.value.status)
  return route('faculty-loading.reports.overload-pay.export') + (p.toString() ? '?' + p.toString() : '')
})

function applyFilters() {
  router.get(route('faculty-loading.reports.overload-pay'), {
    term_id: filters.value.term_id || undefined,
    status:  filters.value.status  || undefined,
  }, { preserveState: true, replace: true })
}

function clearFilters() {
  filters.value = { term_id: props.currentTerm?.id ?? '', status: '' }
  applyFilters()
}

function phpFmt(val) {
  if (val == null) return '—'
  return '₱' + Number(val).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function statusLabel(s) {
  return {
    for_approval: 'For Approval',
    approved:     'Approved',
    paid:         'Paid',
    rejected:     'Rejected',
    pending:      'Pending',
  }[s] ?? s
}

function statusClass(s) {
  return {
    for_approval: 'bg-amber-50 text-amber-700',
    approved:     'bg-emerald-50 text-emerald-700',
    paid:         'bg-indigo-50 text-indigo-700',
    rejected:     'bg-red-50 text-red-600',
    pending:      'bg-slate-50 text-slate-600',
  }[s] ?? 'bg-slate-50 text-slate-600'
}
</script>
