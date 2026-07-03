<script setup>
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
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

const statusClass = (status) => {
  if (['liquidated', 'completed', 'released'].includes(status)) return 'bg-emerald-100 text-emerald-700'
  if (['returned', 'cancelled'].includes(status)) return 'bg-red-100 text-red-700'
  if (status === 'draft') return 'bg-slate-100 text-slate-600'
  return 'bg-amber-100 text-amber-700'
}

const fmtDate = (date) => date ? new Date(date).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }) : '-'
</script>

<template>
  <Head title="Travel Dashboard" />
  <AdminLayout title="Travel Dashboard">
    <div class="space-y-6">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="text-xl font-semibold text-slate-900">Travel Dashboard</h1>
          <p class="text-sm text-slate-500">Official travel, itinerary, transport, cash advance, ORS, DV, and liquidation monitoring.</p>
        </div>
        <Link :href="route('travel.index')" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
          Open Travel Requests
        </Link>
      </div>

      <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div v-for="card in cards" :key="card.label" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ card.label }}</p>
              <p class="mt-2 text-2xl font-semibold text-slate-900">{{ card.value }}</p>
            </div>
            <div :class="['rounded-lg p-3', card.tone]">
              <component :is="card.icon" class="h-5 w-5" />
            </div>
          </div>
        </div>
      </div>

      <div class="grid gap-6 xl:grid-cols-2">
        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
          <div class="border-b border-slate-100 px-5 py-4">
            <h2 class="text-sm font-semibold text-slate-900">Pending My Action</h2>
          </div>
          <div class="divide-y divide-slate-100">
            <Link v-for="travel in pendingAction" :key="travel.id" :href="route('travel.show', travel.id)" class="block px-5 py-4 hover:bg-slate-50">
              <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                  <p class="truncate text-sm font-medium text-slate-900">{{ travel.control_no }} - {{ travel.destination }}</p>
                  <p class="mt-1 truncate text-xs text-slate-500">{{ travel.traveler?.name }} · {{ fmtDate(travel.start_date) }} to {{ fmtDate(travel.end_date) }}</p>
                </div>
                <span :class="['shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium', statusClass(travel.status)]">{{ travel.status_label }}</span>
              </div>
            </Link>
            <div v-if="!pendingAction?.length" class="px-5 py-10 text-center text-sm text-slate-400">No travel records need your action.</div>
          </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
          <div class="border-b border-slate-100 px-5 py-4">
            <h2 class="text-sm font-semibold text-slate-900">Recent Travel</h2>
          </div>
          <div class="divide-y divide-slate-100">
            <Link v-for="travel in recentTravels" :key="travel.id" :href="route('travel.show', travel.id)" class="block px-5 py-4 hover:bg-slate-50">
              <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                  <p class="truncate text-sm font-medium text-slate-900">{{ travel.control_no }} - {{ travel.destination }}</p>
                  <p class="mt-1 truncate text-xs text-slate-500">{{ travel.purpose }}</p>
                </div>
                <span :class="['shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium', statusClass(travel.status)]">{{ travel.status_label }}</span>
              </div>
            </Link>
            <div v-if="!recentTravels?.length" class="px-5 py-10 text-center text-sm text-slate-400">No travel records found.</div>
          </div>
        </section>
      </div>

      <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-sm font-semibold text-slate-900">Policy References</h2>
        <div class="mt-3 grid gap-3 md:grid-cols-2">
          <div v-for="rule in policyRules" :key="rule.id" class="rounded-lg border border-slate-100 bg-slate-50 p-3">
            <p class="text-sm font-medium text-slate-800">{{ rule.label }}</p>
            <p class="mt-1 text-xs text-slate-500">{{ rule.source }}</p>
            <a v-if="rule.source_url" :href="rule.source_url" target="_blank" class="mt-2 inline-block text-xs font-medium text-indigo-600 hover:text-indigo-700">Open source</a>
          </div>
          <p v-if="!policyRules?.length" class="text-sm text-slate-500">No configurable policy rules have been seeded yet.</p>
        </div>
      </section>
    </div>
  </AdminLayout>
</template>
