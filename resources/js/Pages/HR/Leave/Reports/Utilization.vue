<script setup>
import { ref, computed } from 'vue'
import { Head, router, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppCard from '@/Components/AppCard.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppButton from '@/Components/AppButton.vue'
import AppTable from '@/Components/AppTable.vue'
import EmptyState from '@/Components/EmptyState.vue'
import PaginationControl from '@/Components/PaginationControl.vue'

const props = defineProps({
  credits:    Object,
  aggregates: Array,
  leaveTypes: Array,
  categories: Array,
  filters:    Object,
  year:       Number,
  years:      Array,
})

const f = ref({
  year:         props.filters?.year         ?? props.year,
  emp_category: props.filters?.emp_category ?? '',
  leave_type_id: props.filters?.leave_type_id ?? '',
})
const isLoading = ref(false)

const applyFilters = () => {
  isLoading.value = true
  router.get(route('hr.reports.leave-credits.utilization'), {
    year:          f.value.year          || undefined,
    emp_category:  f.value.emp_category  || undefined,
    leave_type_id: f.value.leave_type_id || undefined,
  }, { preserveState: true, replace: true, only: ['credits','aggregates','filters'], onFinish: () => { isLoading.value = false } })
}

const goToPage = (p) => {
  isLoading.value = true
  router.get(route('hr.reports.leave-credits.utilization'), { ...f.value, page: p }, {
    preserveState: true, replace: true, only: ['credits'],
    onFinish: () => { isLoading.value = false },
  })
}

const creditData = computed(() => props.credits?.data ?? [])
const curPage    = computed(() => props.credits?.current_page ?? 1)
const lastPage   = computed(() => props.credits?.last_page ?? 1)
const total      = computed(() => props.credits?.total ?? 0)

// Utilization rate = used / (earned + carried_over), capped at 100%
const utilizationPct = (row) => {
  const available = Number(row.earned) + Number(row.carried_over)
  if (available <= 0) return 0
  return Math.min(100, Math.round((Number(row.used) / available) * 100))
}

const pctColor = (pct) => {
  if (pct >= 80) return 'bg-emerald-500'
  if (pct >= 40) return 'bg-amber-400'
  return 'bg-slate-300'
}

const balanceColor = (bal) => {
  const n = Number(bal)
  if (n <= 0)  return 'text-red-600'
  if (n <= 5)  return 'text-amber-600'
  return 'text-emerald-600'
}
</script>

<template>
  <Head title="Leave Utilization Report" />
  <AdminLayout title="Leave Utilization Report">
    <div class="space-y-5">

      <AppPageHeader title="Leave Utilization Report" :subtitle="`Per-employee leave balance and usage rate for ${year}.`" />

      <!-- Filters -->
      <AppFilterBar>
        <div>
          <label class="block text-xs font-medium text-slate-500 mb-1">Year</label>
          <select v-model="f.year" class="text-sm border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-500 mb-1">Category</label>
          <select v-model="f.emp_category" class="text-sm border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="">All</option>
            <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-500 mb-1">Leave Type</label>
          <select v-model="f.leave_type_id" class="text-sm border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="">All</option>
            <option v-for="lt in leaveTypes" :key="lt.id" :value="lt.id">{{ lt.code }} — {{ lt.name }}</option>
          </select>
        </div>
        <template #actions>
          <AppButton size="sm" :loading="isLoading" @click="applyFilters">Apply</AppButton>
        </template>
      </AppFilterBar>

      <!-- Aggregate summary cards -->
      <div v-if="aggregates.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <AppCard v-for="agg in aggregates" :key="agg.leave_type_id">
          <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">{{ agg.leave_type?.code }} — {{ agg.leave_type?.name }}</p>
          <p class="text-xs text-slate-400 mt-0.5">{{ agg.employee_count }} employee(s)</p>
          <div class="mt-3 space-y-1.5 text-xs">
            <div class="flex justify-between">
              <span class="text-slate-500">Available</span>
              <span class="font-semibold text-slate-700">{{ Number(agg.total_available).toFixed(2) }} d</span>
            </div>
            <div class="flex justify-between">
              <span class="text-slate-500">Used</span>
              <span class="font-semibold text-red-600">{{ Number(agg.total_used).toFixed(2) }} d</span>
            </div>
            <div class="flex justify-between">
              <span class="text-slate-500">Balance</span>
              <span class="font-semibold text-emerald-600">{{ Number(agg.total_balance).toFixed(2) }} d</span>
            </div>
            <div class="flex justify-between">
              <span class="text-slate-500">Forfeited</span>
              <span class="font-semibold text-slate-500">{{ Number(agg.total_forfeited).toFixed(2) }} d</span>
            </div>
          </div>
          <!-- Overall utilization bar -->
          <div class="mt-3">
            <div class="h-1.5 bg-slate-100 rounded-full overflow-hidden">
              <div :class="['h-full rounded-full transition-all', pctColor(
                    Number(agg.total_available) > 0
                      ? Math.round((Number(agg.total_used) / Number(agg.total_available)) * 100)
                      : 0)]"
                   :style="{width: Number(agg.total_available) > 0
                      ? Math.min(100, Math.round((Number(agg.total_used) / Number(agg.total_available)) * 100)) + '%'
                      : '0%' }">
              </div>
            </div>
          </div>
        </AppCard>
      </div>

      <!-- Per-employee table -->
      <AppCard title="Employee Detail" :subtitle="`${total} record(s)`" :padded="false">
        <AppTable :is-empty="!creditData.length" :skeleton-cols="9" :card="false">
          <template #head>
            <tr>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Employee</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Category</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Leave Type</th>
              <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Earned</th>
              <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Carried Over</th>
              <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Used</th>
              <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Balance</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide w-32">Utilization</th>
              <th class="px-4 py-3"></th>
            </tr>
          </template>

          <tr v-for="row in creditData" :key="row.id" class="hover:bg-slate-50/60">
            <td class="px-4 py-3">
              <p class="font-medium text-slate-800 text-xs">{{ row.user?.name ?? '—' }}</p>
              <p class="text-xs text-slate-400">{{ row.user?.badge_id ?? '' }}</p>
            </td>
            <td class="px-4 py-3 text-xs text-slate-500">{{ row.user?.emp_category ?? '—' }}</td>
            <td class="px-4 py-3 text-xs font-medium text-slate-700">{{ row.leave_type?.code ?? '—' }}</td>
            <td class="px-4 py-3 text-right text-slate-600 text-xs">{{ Number(row.earned).toFixed(4) }}</td>
            <td class="px-4 py-3 text-right text-slate-600 text-xs">{{ Number(row.carried_over).toFixed(4) }}</td>
            <td class="px-4 py-3 text-right text-red-600 font-medium text-xs">{{ Number(row.used).toFixed(4) }}</td>
            <td class="px-4 py-3 text-right font-bold text-xs" :class="balanceColor(row.balance)">{{ Number(row.balance).toFixed(4) }}</td>
            <td class="px-4 py-3">
              <div class="flex items-center gap-2">
                <div class="flex-1 h-2 bg-slate-100 rounded-full overflow-hidden">
                  <div :class="['h-full rounded-full', pctColor(utilizationPct(row))]"
                       :style="{ width: utilizationPct(row) + '%' }"></div>
                </div>
                <span class="text-xs font-medium text-slate-600 w-8 text-right">{{ utilizationPct(row) }}%</span>
              </div>
            </td>
            <td class="px-4 py-3 text-right">
              <Link :href="route('hr.leave-credits.ledger', row.user_id)"
                    class="text-xs text-indigo-600 hover:text-indigo-800 font-medium whitespace-nowrap">
                Ledger →
              </Link>
            </td>
          </tr>

          <template #mobileCard>
            <div v-for="row in creditData" :key="row.id" class="p-4 space-y-2">
              <div class="flex items-start justify-between gap-2">
                <div>
                  <p class="font-medium text-slate-800 text-sm">{{ row.user?.name ?? '—' }}</p>
                  <p class="text-xs text-slate-400">{{ row.user?.badge_id ?? '' }} &middot; {{ row.user?.emp_category ?? '—' }}</p>
                </div>
                <span class="text-xs font-medium text-slate-700">{{ row.leave_type?.code ?? '—' }}</span>
              </div>
              <div class="grid grid-cols-3 gap-2 text-xs">
                <div>
                  <p class="text-slate-400">Earned</p>
                  <p class="text-slate-600">{{ Number(row.earned).toFixed(4) }}</p>
                </div>
                <div>
                  <p class="text-slate-400">Used</p>
                  <p class="text-red-600 font-medium">{{ Number(row.used).toFixed(4) }}</p>
                </div>
                <div>
                  <p class="text-slate-400">Balance</p>
                  <p class="font-bold" :class="balanceColor(row.balance)">{{ Number(row.balance).toFixed(4) }}</p>
                </div>
              </div>
              <div class="flex items-center gap-2">
                <div class="flex-1 h-2 bg-slate-100 rounded-full overflow-hidden">
                  <div :class="['h-full rounded-full', pctColor(utilizationPct(row))]"
                       :style="{ width: utilizationPct(row) + '%' }"></div>
                </div>
                <span class="text-xs font-medium text-slate-600 w-8 text-right">{{ utilizationPct(row) }}%</span>
              </div>
              <div class="flex justify-end pt-1">
                <Link :href="route('hr.leave-credits.ledger', row.user_id)"
                      class="text-xs text-indigo-600 hover:text-indigo-800 font-medium whitespace-nowrap">
                  Ledger →
                </Link>
              </div>
            </div>
          </template>

          <template #empty>
            <EmptyState title="No credit records found for the selected filters" />
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
