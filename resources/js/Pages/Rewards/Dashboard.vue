<template>
  <AdminLayout title="Rewards & Recognition Dashboard">
    <div class="space-y-6">
      <AppPageHeader hero class="dash-section" style="--stagger: 0" title="Rewards & Recognition (PRAISE)">
        <template #actions>
          <AppButton as="link" :href="route('rewards.nominations.create')">+ Nominate Employee</AppButton>
        </template>
      </AppPageHeader>

      <!-- Stats Grid -->
      <div class="dash-section grid grid-cols-2 gap-4 sm:grid-cols-4 lg:grid-cols-7" style="--stagger: 1">
        <StatCard label="Total Nominations" :value="kpiValues[0] ?? stats.total_nominations" color="indigo" />
        <StatCard label="Pending" :value="kpiValues[1] ?? stats.pending" color="amber" />
        <StatCard label="Screened" :value="kpiValues[2] ?? stats.screened" color="blue" />
        <StatCard label="Evaluated" :value="kpiValues[3] ?? stats.evaluated" color="blue" />
        <StatCard label="Approved" :value="kpiValues[4] ?? stats.approved" color="success" />
        <StatCard label="Rejected" :value="kpiValues[5] ?? stats.rejected" color="red" />
        <StatCard label="Awarded" :value="kpiValues[6] ?? stats.total_awarded" color="success" />
      </div>

      <div class="dash-section grid grid-cols-1 gap-6 lg:grid-cols-2" style="--stagger: 2">
        <!-- Nominations by Type -->
        <AppCard title="Nominations by Type" :padded="false">
          <div class="p-5">
            <div v-if="byType.length" class="space-y-2">
              <div v-for="row in byType" :key="row.reward_type_id"
                class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-sm">
                <span class="text-slate-700">{{ row.reward_type?.name ?? '—' }}</span>
                <span class="font-semibold text-indigo-700">{{ row.total }}</span>
              </div>
            </div>
            <p v-else class="text-sm text-slate-400">No data yet.</p>
          </div>
        </AppCard>

        <!-- Recent Awards -->
        <AppCard title="Recent Awards" :padded="false">
          <div class="p-5">
            <div v-if="recentAwards.length" class="space-y-2">
              <div v-for="award in recentAwards" :key="award.id"
                class="flex items-start justify-between rounded-lg bg-slate-50 px-3 py-2 text-sm">
                <div>
                  <p class="font-medium text-slate-800">{{ award.nomination?.nominee?.name }}</p>
                  <p class="text-xs text-slate-500">{{ award.nomination?.reward_type?.name }}</p>
                </div>
                <div class="text-right">
                  <AppBadge color="green">{{ award.incentive_type.replace('_', ' ') }}</AppBadge>
                  <p class="mt-0.5 text-xs text-slate-400">{{ formatDate(award.award_date) }}</p>
                </div>
              </div>
            </div>
            <p v-else class="text-sm text-slate-400">No awards recorded yet.</p>
          </div>
        </AppCard>
      </div>

      <!-- Quick Links -->
      <div class="dash-section grid grid-cols-2 gap-4 sm:grid-cols-4" style="--stagger: 3">
        <QuickLink :href="route('rewards.nominations.index')" label="All Nominations" icon="📋" />
        <QuickLink :href="route('rewards.evaluations.panel')" label="Evaluation Panel" icon="📊" />
        <QuickLink :href="route('rewards.approvals.index')" label="Approvals" icon="✅" />
        <QuickLink :href="route('rewards.reports')" label="Reports" icon="📈" />
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppCard from '@/Components/AppCard.vue'
import AppBadge from '@/Components/AppBadge.vue'
import { Link } from '@inertiajs/vue3'
import { computed } from 'vue'
import { useCountUp } from '@/Composables/useCountUp.js'

const props = defineProps({
  stats: Object,
  byType: Array,
  recentAwards: Array,
})

const { values: kpiValues } = useCountUp(() => [
  props.stats.total_nominations, props.stats.pending, props.stats.screened,
  props.stats.evaluated, props.stats.approved, props.stats.rejected, props.stats.total_awarded,
])

function formatDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
}
</script>

<script>
// Sub-components defined locally
const StatCard = {
  props: ['label', 'value', 'color'],
  template: `
    <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200/70 p-4 text-center">
      <p class="text-2xl font-bold" :class="'text-' + color + '-600'">{{ value }}</p>
      <p class="mt-1 text-xs text-slate-500">{{ label }}</p>
    </div>
  `,
}
const QuickLink = {
  props: ['href', 'label', 'icon'],
  template: `
    <a :href="href"
      class="flex items-center gap-3 bg-white rounded-2xl shadow-sm ring-1 ring-slate-200/70 p-4 hover:bg-slate-50 transition-colors">
      <span class="text-2xl">{{ icon }}</span>
      <span class="text-sm font-medium text-slate-700">{{ label }}</span>
    </a>
  `,
}
export default { components: { StatCard, QuickLink } }
</script>
