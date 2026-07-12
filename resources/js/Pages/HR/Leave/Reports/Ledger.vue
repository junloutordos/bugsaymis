<script setup>
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppCard from '@/Components/AppCard.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppButton from '@/Components/AppButton.vue'
import AppTable from '@/Components/AppTable.vue'
import AppBadge from '@/Components/AppBadge.vue'
import EmptyState from '@/Components/EmptyState.vue'
import PaginationControl from '@/Components/PaginationControl.vue'

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

// AppBadge color mapping for the per-row transaction type badge
const txTypeBadgeColor = (type) => ({
  INITIAL:      'purple',
  ACCRUAL:      'green',
  DEDUCTION:    'red',
  ADJUSTMENT:   'blue',
  RESTORATION:  'amber',
  MONETIZATION: 'orange',
  CARRYOVER:    'indigo',
  FORFEITURE:   'slate',
})[type] ?? 'slate'

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

      <AppPageHeader title="Leave Credit Ledger" subtitle="Full audit trail of all leave credit transactions. Filter by employee, type, or date." />

      <!-- Filters -->
      <AppFilterBar>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 w-full">
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
        <template #actions>
          <AppButton size="sm" :loading="isLoading" @click="applyFilters">Apply Filters</AppButton>
          <AppButton size="sm" variant="secondary" @click="reset">Reset</AppButton>
        </template>
      </AppFilterBar>

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
      <AppCard :padded="false">
        <template #header>
          <span class="text-xs text-slate-500">{{ total }} transaction(s)</span>
        </template>
        <AppTable :is-empty="!txData.length" :skeleton-cols="8" :card="false">
          <template #head>
            <tr>
              <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Date</th>
              <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Employee</th>
              <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Type</th>
              <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Leave</th>
              <th class="px-4 py-3 text-right text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Amount</th>
              <th class="px-4 py-3 text-right text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Balance After</th>
              <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Remarks</th>
              <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Recorded By</th>
            </tr>
          </template>

          <tr v-for="tx in txData" :key="tx.id" class="hover:bg-indigo-50/40">
            <td class="px-4 py-3 text-xs text-slate-500 whitespace-nowrap">{{ fmtDt(tx.created_at) }}</td>
            <td class="px-4 py-3">
              <p class="font-medium text-slate-800 text-xs">{{ tx.user?.name ?? '—' }}</p>
              <p class="text-xs text-slate-400">{{ tx.user?.badge_id ?? '' }}</p>
            </td>
            <td class="px-4 py-3">
              <AppBadge :color="txTypeBadgeColor(tx.type)">{{ tx.type }}</AppBadge>
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

          <template #mobileCard>
            <div v-for="tx in txData" :key="tx.id" class="p-4 space-y-1.5">
              <div class="flex items-start justify-between gap-2">
                <div>
                  <p class="font-medium text-slate-800 text-xs">{{ tx.user?.name ?? '—' }}</p>
                  <p class="text-xs text-slate-400">{{ tx.user?.badge_id ?? '' }}</p>
                </div>
                <AppBadge :color="txTypeBadgeColor(tx.type)">{{ tx.type }}</AppBadge>
              </div>
              <p class="text-xs text-slate-500">{{ fmtDt(tx.created_at) }} &middot; {{ tx.leave_type?.code ?? '—' }}</p>
              <div class="flex justify-between text-xs">
                <span class="font-mono font-semibold" :class="Number(tx.amount) >= 0 ? 'text-emerald-600' : 'text-red-600'">{{ signed(tx.amount) }}</span>
                <span class="font-mono text-slate-700">Balance {{ Number(tx.balance_after).toFixed(4) }}</span>
              </div>
              <p v-if="tx.remarks" class="text-xs text-slate-500 truncate" :title="tx.remarks">{{ tx.remarks }}</p>
              <p class="text-xs text-slate-400">Recorded by {{ tx.recorder?.name ?? 'System' }}</p>
            </div>
          </template>

          <template #empty>
            <EmptyState title="No transactions match the selected filters" />
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
