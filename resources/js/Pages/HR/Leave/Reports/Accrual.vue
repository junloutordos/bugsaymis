<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppCard from '@/Components/AppCard.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppButton from '@/Components/AppButton.vue'
import AppTable from '@/Components/AppTable.vue'
import EmptyState from '@/Components/EmptyState.vue'
import PaginationControl from '@/Components/PaginationControl.vue'

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

      <AppPageHeader title="Monthly Accrual Report" subtitle="VL + SL accrued monthly for Non-Teaching employees (1.25 days each per month)." />

      <!-- Filters -->
      <AppFilterBar>
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
        <template #actions>
          <AppButton size="sm" :loading="isLoading" @click="applyFilters">Apply</AppButton>
        </template>
      </AppFilterBar>

      <!-- Monthly summary grid -->
      <AppCard :title="`Monthly Summary — ${year}`" subtitle="Number of employees accrued and total days credited per month." :padded="false">
        <AppTable :card="false">
          <template #head>
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
          </template>

          <tr v-for="m in summaryMonths" :key="m.num"
              :class="['hover:bg-slate-50/60', (!m.vl && !m.sl) ? 'opacity-40' : '']">
            <td class="px-4 py-3 font-medium text-slate-700">{{ m.name }}</td>
            <td class="px-4 py-3 text-center text-slate-600">{{ m.vl?.employees ?? '—' }}</td>
            <td class="px-4 py-3 text-center font-semibold text-emerald-600">{{ m.vl ? m.vl.total_days.toFixed(2) + ' d' : '—' }}</td>
            <td class="px-4 py-3 text-center text-slate-600">{{ m.sl?.employees ?? '—' }}</td>
            <td class="px-4 py-3 text-center font-semibold text-emerald-600">{{ m.sl ? m.sl.total_days.toFixed(2) + ' d' : '—' }}</td>
          </tr>
        </AppTable>
      </AppCard>

      <!-- Detail rows -->
      <AppCard title="Transaction Detail" :subtitle="`${total} accrual transaction(s)`" :padded="false">
        <AppTable :is-empty="!rowData.length" :skeleton-cols="5" :card="false">
          <template #head>
            <tr>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Date</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Employee</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Leave Type</th>
              <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Amount</th>
              <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Balance After</th>
            </tr>
          </template>

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

          <template #mobileCard>
            <div v-for="row in rowData" :key="row.id" class="p-4 space-y-1">
              <div class="flex items-start justify-between gap-2">
                <div>
                  <p class="font-medium text-slate-800 text-sm">{{ row.user?.name ?? '—' }}</p>
                  <p class="text-xs text-slate-400">{{ row.user?.badge_id ?? '' }}</p>
                </div>
                <span class="text-xs text-slate-500 whitespace-nowrap">{{ fmtDt(row.created_at) }}</span>
              </div>
              <p class="text-xs text-slate-600">{{ row.leave_type?.code ?? '—' }} — {{ row.leave_type?.name ?? '' }}</p>
              <div class="flex justify-between text-xs pt-1">
                <span class="font-mono font-semibold text-emerald-600">+{{ Number(row.amount).toFixed(4) }}</span>
                <span class="font-mono text-slate-700">Balance {{ Number(row.balance_after).toFixed(4) }}</span>
              </div>
            </div>
          </template>

          <template #empty>
            <EmptyState title="No accrual transactions found" />
          </template>

          <template #footer>
            <PaginationControl
              :current-page="curPage"
              :total-pages="lastPage"
              :total="total"
              @prev="goToPage(curPage - 1)"
              @next="goToPage(curPage + 1)"
              @page="goToPage"
            />
          </template>
        </AppTable>
      </AppCard>

    </div>
  </AdminLayout>
</template>
