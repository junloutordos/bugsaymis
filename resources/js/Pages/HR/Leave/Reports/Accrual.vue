<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  rows:      Object,
  monthMap:  Object,
  year:      Number,
  month:     [String, null],
  years:     Array,
  months:    Object,
  filters:   Object,
})

const f = ref({
  year:  props.filters?.year  ?? props.year,
  month: props.filters?.month ?? '',
})
const isLoading = ref(false)

const applyFilters = () => {
  isLoading.value = true
  router.get(route('hr.reports.leave-credits.accrual'), {
    year:  f.value.year  || undefined,
    month: f.value.month || undefined,
  }, { preserveState: true, replace: true, only: ['rows','monthMap','filters'], onFinish: () => { isLoading.value = false } })
}

const goToPage = (p) => {
  isLoading.value = true
  router.get(route('hr.reports.leave-credits.accrual'), { ...f.value, page: p }, {
    preserveState: true, replace: true, only: ['rows'],
    onFinish: () => { isLoading.value = false },
  })
}

// Build a display-friendly monthly summary grid
const summaryMonths = computed(() => {
  return Object.entries(props.months).map(([num, name]) => {
    const data = props.monthMap?.[num] ?? {}
    return { num, name, vl: data.VL ?? null, sl: data.SL ?? null }
  })
})

const rowData  = computed(() => props.rows?.data ?? [])
const curPage  = computed(() => props.rows?.current_page ?? 1)
const lastPage = computed(() => props.rows?.last_page ?? 1)
const total    = computed(() => props.rows?.total ?? 0)

const fmtDt = (d) => d ? new Date(d).toLocaleDateString('en-PH', { month:'short', day:'numeric', year:'numeric' }) : '—'
</script>

<template>
  <Head title="Monthly Accrual Report" />
  <AdminLayout title="Monthly Accrual Report">
    <div class="space-y-5">

      <div>
        <h1 class="text-xl font-semibold text-slate-800">Monthly Accrual Report</h1>
        <p class="text-sm text-slate-500 mt-0.5">VL + SL accrued monthly for Non-Teaching employees (1.25 days each per month).</p>
      </div>

      <!-- Filters -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 flex flex-wrap gap-3 items-end">
        <div>
          <label class="block text-xs font-medium text-slate-500 mb-1">Year</label>
          <select v-model="f.year" class="text-sm border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-500 mb-1">Month</label>
          <select v-model="f.month" class="text-sm border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="">All months</option>
            <option v-for="(name, num) in months" :key="num" :value="num">{{ name }}</option>
          </select>
        </div>
        <button @click="applyFilters" :disabled="isLoading"
                class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors disabled:opacity-50">
          Apply
        </button>
      </div>

      <!-- Monthly summary grid -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100">
          <h2 class="text-sm font-semibold text-slate-800">Monthly Summary — {{ year }}</h2>
          <p class="text-xs text-slate-400 mt-0.5">Number of employees accrued and total days credited per month.</p>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Month</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide" colspan="2">VL</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide" colspan="2">SL</th>
              </tr>
              <tr class="bg-slate-50/80 border-t border-slate-100">
                <th class="px-4 py-2"></th>
                <th class="px-4 py-2 text-center text-xs font-medium text-slate-400">Employees</th>
                <th class="px-4 py-2 text-center text-xs font-medium text-slate-400">Total Days</th>
                <th class="px-4 py-2 text-center text-xs font-medium text-slate-400">Employees</th>
                <th class="px-4 py-2 text-center text-xs font-medium text-slate-400">Total Days</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="m in summaryMonths" :key="m.num"
                  :class="['hover:bg-slate-50/60', (!m.vl && !m.sl) ? 'opacity-40' : '']">
                <td class="px-4 py-3 font-medium text-slate-700">{{ m.name }}</td>
                <td class="px-4 py-3 text-center text-slate-600">{{ m.vl?.employees ?? '—' }}</td>
                <td class="px-4 py-3 text-center font-semibold text-emerald-600">{{ m.vl ? m.vl.total_days.toFixed(2) + ' d' : '—' }}</td>
                <td class="px-4 py-3 text-center text-slate-600">{{ m.sl?.employees ?? '—' }}</td>
                <td class="px-4 py-3 text-center font-semibold text-emerald-600">{{ m.sl ? m.sl.total_days.toFixed(2) + ' d' : '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Detail rows -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
          <div>
            <h2 class="text-sm font-semibold text-slate-800">Transaction Detail</h2>
            <p class="text-xs text-slate-400 mt-0.5">{{ total }} accrual transaction(s)</p>
          </div>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Date</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Employee</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Leave Type</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Amount</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Balance After</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="row in rowData" :key="row.id" class="hover:bg-slate-50/60">
                <td class="px-4 py-3 text-xs text-slate-500 whitespace-nowrap">{{ fmtDt(row.created_at) }}</td>
                <td class="px-4 py-3">
                  <p class="font-medium text-slate-800 text-xs">{{ row.user?.name ?? '—' }}</p>
                  <p class="text-xs text-slate-400">{{ row.user?.badge_id ?? '' }}</p>
                </td>
                <td class="px-4 py-3 text-slate-600 text-xs">{{ row.leave_type?.code ?? '—' }} — {{ row.leave_type?.name ?? '' }}</td>
                <td class="px-4 py-3 text-right font-mono font-semibold text-emerald-600 text-xs">+{{ Number(row.amount).toFixed(4) }}</td>
                <td class="px-4 py-3 text-right font-mono text-slate-700 text-xs">{{ Number(row.balance_after).toFixed(4) }}</td>
              </tr>
              <tr v-if="!rowData.length">
                <td colspan="5" class="py-12 text-center text-slate-400 text-sm">No accrual transactions found.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="lastPage > 1" class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-sm text-slate-600">
          <span>Page {{ curPage }} of {{ lastPage }}</span>
          <div class="flex gap-2">
            <button @click="goToPage(curPage-1)" :disabled="curPage===1||isLoading" class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 disabled:opacity-50">Prev</button>
            <button @click="goToPage(curPage+1)" :disabled="curPage===lastPage||isLoading" class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 disabled:opacity-50">Next</button>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
