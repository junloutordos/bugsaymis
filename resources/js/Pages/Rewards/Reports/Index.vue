<template>
  <AdminLayout title="Rewards Report">
    <div class="space-y-6">
      <AppPageHeader title="Recognition Report" />

      <!-- Filters -->
      <AppFilterBar>
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
      </AppFilterBar>

      <!-- Incentive Breakdown -->
      <AppCard title="Awards by Incentive Type" :padded="false">
        <div class="p-5">
          <AppTable v-if="awardsByIncentive.length" :is-empty="false" :card="false">
            <template #head>
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Incentive Type</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Count</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Total Value</th>
              </tr>
            </template>
            <tr v-for="row in awardsByIncentive" :key="row.incentive_type" class="hover:bg-slate-50/60">
              <td class="px-4 py-3 text-sm text-slate-700 capitalize">{{ row.incentive_type.replace('_', ' ') }}</td>
              <td class="px-4 py-3 text-sm font-medium text-slate-800">{{ row.count }}</td>
              <td class="px-4 py-3 text-sm text-slate-700">
                {{ row.total_value ? Number(row.total_value).toLocaleString() : '—' }}
              </td>
            </tr>
          </AppTable>
          <p v-else class="text-sm text-slate-400">No award data for selected period.</p>
        </div>
      </AppCard>

      <!-- Nominations List -->
      <AppTable :is-empty="!nominations.length" :skeleton-cols="6">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Nominee</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Award</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Period</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Status</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Incentive</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Award Date</th>
          </tr>
        </template>

        <tr v-for="n in nominations" :key="n.id" class="hover:bg-slate-50/60">
          <td class="px-4 py-3 text-sm font-medium text-slate-800">{{ n.nominee?.name }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ n.reward_type?.name }}</td>
          <td class="px-4 py-3 text-sm text-slate-500">{{ n.period ?? '—' }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">
            <AppBadge :color="statusColor(n.status)" class="capitalize">{{ n.status }}</AppBadge>
          </td>
          <td class="px-4 py-3 text-sm text-slate-700 capitalize">
            {{ n.reward?.incentive_type?.replace('_', ' ') ?? '—' }}
          </td>
          <td class="px-4 py-3 text-sm text-slate-500">
            {{ n.reward ? formatDate(n.reward.award_date) : '—' }}
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="n in nominations" :key="n.id" class="p-4 space-y-1">
            <div class="flex items-start justify-between gap-2">
              <p class="font-medium text-slate-800">{{ n.nominee?.name }}</p>
              <AppBadge :color="statusColor(n.status)" class="capitalize">{{ n.status }}</AppBadge>
            </div>
            <p class="text-xs text-slate-500">{{ n.reward_type?.name }} &middot; {{ n.period ?? '—' }}</p>
            <p class="text-xs text-slate-400">{{ n.reward?.incentive_type?.replace('_', ' ') ?? '—' }} &middot; {{ n.reward ? formatDate(n.reward.award_date) : '—' }}</p>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No nominations for the selected period" />
        </template>
      </AppTable>
    </div>
  </AdminLayout>
</template>

<script setup>
import { reactive } from 'vue'
import { router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppCard from '@/Components/AppCard.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import AppBadge from '@/Components/AppBadge.vue'
import EmptyState from '@/Components/EmptyState.vue'

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

function statusColor(s) {
  return {
    pending: 'amber',
    screened: 'green',
    evaluated: 'blue',
    approved: 'green',
    rejected: 'red',
  }[s] ?? 'slate'
}
</script>
