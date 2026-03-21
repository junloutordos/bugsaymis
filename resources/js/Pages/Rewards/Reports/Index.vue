<template>
  <AdminLayout title="Rewards Report">
    <div class="space-y-6">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <h1 class="text-xl font-semibold text-slate-800">Recognition Report</h1>
      </div>

      <!-- Filters -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 mb-4 flex flex-wrap items-end gap-3">
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Year</label>
          <select v-model="filters.year" @change="applyFilters" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-28">
            <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Award Type</label>
          <select v-model="filters.reward_type_id" @change="applyFilters" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-48">
            <option value="">All Types</option>
            <option v-for="t in rewardTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
          </select>
        </div>
      </div>

      <!-- Incentive Breakdown -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <div class="px-5 py-4 border-b border-slate-100">
          <h2 class="text-sm font-semibold text-slate-800">Awards by Incentive Type</h2>
        </div>
        <div class="p-5">
          <div v-if="awardsByIncentive.length" class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-sm">
              <thead class="bg-slate-50">
                <tr>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Incentive Type</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Count</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Total Value</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="row in awardsByIncentive" :key="row.incentive_type" class="hover:bg-slate-50/60">
                  <td class="px-4 py-3 text-sm text-slate-700 capitalize">{{ row.incentive_type.replace('_', ' ') }}</td>
                  <td class="px-4 py-3 text-sm font-medium text-slate-800">{{ row.count }}</td>
                  <td class="px-4 py-3 text-sm text-slate-700">
                    {{ row.total_value ? Number(row.total_value).toLocaleString() : '—' }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <p v-else class="text-sm text-slate-400">No award data for selected period.</p>
        </div>
      </div>

      <!-- Nominations List -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <div class="px-5 py-4 border-b border-slate-100">
          <h2 class="text-sm font-semibold text-slate-800">Nominations ({{ nominations.length }})</h2>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Nominee</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Award</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Period</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Status</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Incentive</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Award Date</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="n in nominations" :key="n.id" class="hover:bg-slate-50/60">
                <td class="px-4 py-3 text-sm font-medium text-slate-800">{{ n.nominee?.name }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ n.reward_type?.name }}</td>
                <td class="px-4 py-3 text-sm text-slate-500">{{ n.period ?? '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">
                  <span :class="statusClass(n.status)"
                    class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium capitalize">
                    {{ n.status }}
                  </span>
                </td>
                <td class="px-4 py-3 text-sm text-slate-700 capitalize">
                  {{ n.reward?.incentive_type?.replace('_', ' ') ?? '—' }}
                </td>
                <td class="px-4 py-3 text-sm text-slate-500">
                  {{ n.reward ? formatDate(n.reward.award_date) : '—' }}
                </td>
              </tr>
              <tr v-if="!nominations.length">
                <td colspan="6" class="py-16 text-center text-slate-400 text-sm">
                  No nominations for the selected period.
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
import { reactive } from 'vue'
import { router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  nominations: Array,
  awardsByIncentive: Array,
  rewardTypes: Array,
  filters: Object,
})

const currentYear = new Date().getFullYear()
const years = Array.from({ length: 6 }, (_, i) => currentYear - i)

const filters = reactive({
  year: props.filters.year ?? currentYear,
  reward_type_id: props.filters.typeId ?? '',
})

function applyFilters() {
  router.get(route('rewards.reports'), filters, { preserveState: true, replace: true })
}

function formatDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
}

function statusClass(s) {
  return {
    pending: 'bg-amber-50 text-amber-700',
    screened: 'bg-emerald-50 text-emerald-700',
    evaluated: 'bg-blue-50 text-blue-700',
    approved: 'bg-emerald-50 text-emerald-700',
    rejected: 'bg-red-50 text-red-600',
  }[s] ?? 'bg-slate-100 text-slate-600'
}
</script>
