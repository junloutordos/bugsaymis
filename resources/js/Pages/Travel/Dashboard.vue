<script setup>
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppCard from '@/Components/AppCard.vue'
import AppButton from '@/Components/AppButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { useCountUp } from '@/Composables/useCountUp.js'
import { CalendarDaysIcon, ClipboardDocumentListIcon, BanknotesIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  stats: Object,
  recentTravels: Array,
  pendingAction: Array,
  policyRules: Array,
})

const cards = computed(() => [
  { label: 'Total Travel', value: props.stats?.total ?? 0, icon: ClipboardDocumentListIcon, tone: 'bg-slate-100 text-slate-700' },
  { label: 'Upcoming', value: props.stats?.upcoming ?? 0, icon: CalendarDaysIcon, tone: 'bg-sky-100 text-sky-700' },
  { label: 'Pending Action', value: props.stats?.pending_action ?? 0, icon: ExclamationTriangleIcon, tone: 'bg-amber-100 text-amber-700' },
  { label: 'Unliquidated', value: props.stats?.unliquidated ?? 0, icon: BanknotesIcon, tone: 'bg-rose-100 text-rose-700' },
])

const { values: kpiValues } = useCountUp(() => cards.value.map(c => c.value))

const statusBadgeColor = (status) => {
  if (['liquidated', 'completed', 'released'].includes(status)) return 'green'
  if (['returned', 'cancelled'].includes(status)) return 'red'
  if (status === 'draft') return 'slate'
  return 'amber'
}

const fmtDate = (date) => date ? new Date(date).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }) : '-'
</script>

<template>
  <Head title="Travel Dashboard" />
  <AdminLayout title="Travel Dashboard">
    <div class="space-y-6">
      <AppPageHeader hero class="dash-section" style="--stagger: 0" title="Travel Dashboard" subtitle="Official travel, itinerary, transport, cash advance, ORS, DV, and liquidation monitoring.">
        <template #actions>
          <AppButton as="link" :href="route('travel.index')">
            Open Travel Requests
          </AppButton>
        </template>
      </AppPageHeader>

      <div class="dash-section grid gap-4 sm:grid-cols-2 xl:grid-cols-4" style="--stagger: 1">
        <div v-for="(card, i) in cards" :key="card.label" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ card.label }}</p>
              <p class="mt-2 text-2xl font-semibold text-slate-900 tabular-nums">{{ kpiValues[i] ?? card.value }}</p>
            </div>
            <div :class="['rounded-lg p-3', card.tone]">
              <component :is="card.icon" class="h-5 w-5" />
            </div>
          </div>
        </div>
      </div>

      <div class="dash-section grid gap-6 xl:grid-cols-2" style="--stagger: 2">
        <AppCard title="Pending My Action" :padded="false">
          <div class="divide-y divide-slate-100">
            <Link v-for="travel in pendingAction" :key="travel.id" :href="route('travel.show', travel.id)" class="block px-5 py-4 hover:bg-slate-50">
              <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                  <p class="truncate text-sm font-medium text-slate-900">{{ travel.control_no }} - {{ travel.destination }}</p>
                  <p class="mt-1 truncate text-xs text-slate-500">{{ travel.traveler?.name }} · {{ fmtDate(travel.start_date) }} to {{ fmtDate(travel.end_date) }}</p>
                </div>
                <AppBadge :color="statusBadgeColor(travel.status)" class="shrink-0">{{ travel.status_label }}</AppBadge>
              </div>
            </Link>
            <EmptyState v-if="!pendingAction?.length" title="No travel records need your action." />
          </div>
        </AppCard>

        <AppCard title="Recent Travel" :padded="false">
          <div class="divide-y divide-slate-100">
            <Link v-for="travel in recentTravels" :key="travel.id" :href="route('travel.show', travel.id)" class="block px-5 py-4 hover:bg-slate-50">
              <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                  <p class="truncate text-sm font-medium text-slate-900">{{ travel.control_no }} - {{ travel.destination }}</p>
                  <p class="mt-1 truncate text-xs text-slate-500">{{ travel.purpose }}</p>
                </div>
                <AppBadge :color="statusBadgeColor(travel.status)" class="shrink-0">{{ travel.status_label }}</AppBadge>
              </div>
            </Link>
            <EmptyState v-if="!recentTravels?.length" title="No travel records found." />
          </div>
        </AppCard>
      </div>

      <AppCard title="Policy References">
        <div class="grid gap-3 md:grid-cols-2">
          <div v-for="rule in policyRules" :key="rule.id" class="rounded-lg border border-slate-100 bg-slate-50 p-3">
            <p class="text-sm font-medium text-slate-800">{{ rule.label }}</p>
            <p class="mt-1 text-xs text-slate-500">{{ rule.source }}</p>
            <a v-if="rule.source_url" :href="rule.source_url" target="_blank" class="mt-2 inline-block text-xs font-medium text-indigo-600 hover:text-indigo-700">Open source</a>
          </div>
          <p v-if="!policyRules?.length" class="text-sm text-slate-500">No configurable policy rules have been seeded yet.</p>
        </div>
      </AppCard>
    </div>
  </AdminLayout>
</template>
