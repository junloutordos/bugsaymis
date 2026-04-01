<script setup>
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  transactions: Object,
  summary:      Array,
  leaveTypes:   Array,
  employees:    Array,
  txTypes:      Array,
  filters:      Object,
  currentYear:  Number,
  years:        Array,
})

const f = ref({
  year:          props.filters?.year         ?? props.currentYear,
  user_id:       props.filters?.user_id      ?? '',
  leave_type_id: props.filters?.leave_type_id ?? '',
  type:          props.filters?.type         ?? '',
  date_from:     props.filters?.date_from    ?? '',
  date_to:       props.filters?.date_to      ?? '',
})

const isLoading = ref(false)

const applyFilters = () => {
  isLoading.value = true
  router.get(route('hr.reports.leave-credits.ledger'), {
    year:          f.value.year          || undefined,
    user_id:       f.value.user_id       || undefined,
    leave_type_id: f.value.leave_type_id || undefined,
    type:          f.value.type          || undefined,
    date_from:     f.value.date_from     || undefined,
    date_to:       f.value.date_to       || undefined,
  }, { preserveState: true, replace: true, only: ['transactions','summary','filters'], onFinish: () => { isLoading.value = false } })
}

const reset = () => {
  f.value = { year: props.currentYear, user_id: '', leave_type_id: '', type: '', date_from: '', date_to: '' }
  applyFilters()
}

const goToPage = (p) => {
  isLoading.value = true
  router.get(route('hr.reports.leave-credits.ledger'), { ...f.value, page: p }, {
    preserveState: true, replace: true, only: ['transactions'],
    onFinish: () => { isLoading.value = false },
  })
}

const txTypeClass = (type) => ({
  INITIAL:      'bg-purple-100 text-purple-700',
  ACCRUAL:      'bg-emerald-100 text-emerald-700',
  DEDUCTION:    'bg-red-100 text-red-700',
  ADJUSTMENT:   'bg-blue-100 text-blue-700',
  RESTORATION:  'bg-teal-100 text-teal-700',
  MONETIZATION: 'bg-orange-100 text-orange-700',
  CARRYOVER:    'bg-indigo-100 text-indigo-700',
  FORFEITURE:   'bg-slate-100 text-slate-600',
})[type] ?? 'bg-slate-100 text-slate-600'

const signed = (n) => { const v = Number(n); return v >= 0 ? `+${v.toFixed(4)}` : v.toFixed(4) }
const fmtDt  = (d) => d ? new Date(d).toLocaleDateString('en-PH', { year:'numeric', month:'short', day:'numeric', hour:'2-digit', minute:'2-digit' }) : '—'

const txData    = props.transactions?.data ?? []
const curPage   = props.transactions?.current_page ?? 1
const lastPage  = props.transactions?.last_page ?? 1
const total     = props.transactions?.total ?? 0
</script>

<template>
  <Head title="Leave Credit Ledger" />
  <AdminLayout title="Leave Credit Ledger">
    <div class="space-y-5">

      <div>
        <h1 class="text-xl font-semibold text-slate-800">Leave Credit Ledger</h1>
        <p class="text-sm text-slate-500 mt-0.5">Full audit trail of all leave credit transactions. Filter by employee, type, or date.</p>
      </div>

      <!-- Filters -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 space-y-3">
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
          <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Year</label>
            <select v-model="f.year" class="w-full text-sm border border-slate-200 rounded-lg px-2 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-500">
              <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Employee</label>
            <select v-model="f.user_id" class="w-full text-sm border border-slate-200 rounded-lg px-2 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-500">
              <option value="">All</option>
              <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Leave Type</label>
            <select v-model="f.leave_type_id" class="w-full text-sm border border-slate-200 rounded-lg px-2 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-500">
              <option value="">All</option>
              <option v-for="lt in leaveTypes" :key="lt.id" :value="lt.id">{{ lt.code }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Tx Type</label>
            <select v-model="f.type" class="w-full text-sm border border-slate-200 rounded-lg px-2 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-500">
              <option value="">All</option>
              <option v-for="t in txTypes" :key="t" :value="t">{{ t }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">From</label>
            <input v-model="f.date_from" type="date" class="w-full text-sm border border-slate-200 rounded-lg px-2 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">To</label>
            <input v-model="f.date_to" type="date" class="w-full text-sm border border-slate-200 rounded-lg px-2 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
        </div>
        <div class="flex gap-2">
          <button @click="applyFilters" :disabled="isLoading"
                  class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors disabled:opacity-50">
            Apply Filters
          </button>
          <button @click="reset" class="border border-slate-200 bg-white hover:bg-slate-50 text-slate-600 px-4 py-2 rounded-lg text-sm font-medium transition-colors">
            Reset
          </button>
        </div>
      </div>

      <!-- Summary chips -->
      <div v-if="summary.length" class="flex flex-wrap gap-2">
        <div v-for="s in summary" :key="s.type"
             :class="['inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-medium border', txTypeClass(s.type)]">
          <span>{{ s.type }}</span>
          <span class="font-bold">{{ s.count }}</span>
          <span class="opacity-70">txns</span>
          <span class="font-bold">{{ Number(s.total_amount) >= 0 ? '+' : '' }}{{ Number(s.total_amount).toFixed(2) }}d</span>
        </div>
      </div>

      <!-- Table -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <div class="px-4 py-3 border-b border-slate-100 text-xs text-slate-500">{{ total }} transaction(s)</div>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Date</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Employee</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Type</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Leave</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Amount</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Balance After</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Remarks</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Recorded By</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="tx in txData" :key="tx.id" class="hover:bg-slate-50/60">
                <td class="px-4 py-3 text-xs text-slate-500 whitespace-nowrap">{{ fmtDt(tx.created_at) }}</td>
                <td class="px-4 py-3">
                  <p class="font-medium text-slate-800 text-xs">{{ tx.user?.name ?? '—' }}</p>
                  <p class="text-xs text-slate-400">{{ tx.user?.badge_id ?? '' }}</p>
                </td>
                <td class="px-4 py-3">
                  <span :class="['inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold', txTypeClass(tx.type)]">{{ tx.type }}</span>
                </td>
                <td class="px-4 py-3 text-slate-600 text-xs">{{ tx.leave_type?.code ?? '—' }}</td>
                <td class="px-4 py-3 text-right font-mono font-semibold text-xs"
                    :class="Number(tx.amount) >= 0 ? 'text-emerald-600' : 'text-red-600'">
                  {{ signed(tx.amount) }}
                </td>
                <td class="px-4 py-3 text-right font-mono text-slate-700 text-xs">{{ Number(tx.balance_after).toFixed(4) }}</td>
                <td class="px-4 py-3 text-xs text-slate-500 max-w-xs truncate" :title="tx.remarks">{{ tx.remarks ?? '—' }}</td>
                <td class="px-4 py-3 text-xs text-slate-500">{{ tx.recorder?.name ?? 'System' }}</td>
              </tr>
              <tr v-if="!txData.length">
                <td colspan="8" class="py-12 text-center text-slate-400 text-sm">No transactions match the selected filters.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="lastPage > 1" class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-sm text-slate-600">
          <span>Page {{ curPage }} of {{ lastPage }}</span>
          <div class="flex gap-2">
            <button @click="goToPage(curPage - 1)" :disabled="curPage === 1 || isLoading"
                    class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-sm disabled:opacity-50">Prev</button>
            <button @click="goToPage(curPage + 1)" :disabled="curPage === lastPage || isLoading"
                    class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-sm disabled:opacity-50">Next</button>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
