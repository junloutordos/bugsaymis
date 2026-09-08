<template>
  <AdminLayout title="Gantimpala Agad Award">
    <div class="space-y-6">
      <AppPageHeader title="Gantimpala Agad Award" subtitle="Instant / spot recognition nominations — PRAISE Form 4">
        <template #actions>
          <AppButton as="link" variant="secondary" :href="route('rewards.gantimpala.create')">+ Nominate (In-App)</AppButton>
          <AppButton as="link" :href="route('kiosk.gantimpala.index')" target="_blank">Open Public Kiosk ↗</AppButton>
        </template>
      </AppPageHeader>

      <!-- Stat Cards -->
      <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
        <StatCard label="Total" :value="stats.total" color="slate" />
        <StatCard label="Pending" :value="stats.pending" color="amber" />
        <StatCard label="Under Review" :value="stats.under_review" color="blue" />
        <StatCard label="Endorsed" :value="stats.endorsed" color="indigo" />
        <StatCard label="Approved" :value="stats.approved" color="green" />
        <StatCard label="Via Kiosk" :value="stats.kiosk_count" color="purple" />
      </div>

      <!-- Filters -->
      <AppFilterBar>
        <select v-model="filters.status" @change="applyFilters" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-44">
          <option value="">All Status</option>
          <option value="pending">Pending</option>
          <option value="under_review">Under Review</option>
          <option value="endorsed">Endorsed</option>
          <option value="approved">Approved</option>
          <option value="rejected">Rejected</option>
          <option value="archived">Archived</option>
        </select>
        <select v-model="filters.source" @change="applyFilters" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-40">
          <option value="">All Sources</option>
          <option value="atlas">Atlas (In-App)</option>
          <option value="kiosk">Public Kiosk</option>
        </select>
        <input v-model="filters.search" @keyup.enter="applyFilters" placeholder="Search nominee, nominator, ref no…"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-64" />
      </AppFilterBar>

      <!-- Table -->
      <AppTable :is-empty="!nominations.data.length" :skeleton-cols="7">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Reference No.</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Nominee</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Nominator</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Source</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Status</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Date</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Actions</th>
          </tr>
        </template>

        <tr v-for="n in nominations.data" :key="n.id" class="hover:bg-indigo-50/40">
          <td class="px-4 py-3 text-sm font-medium text-slate-800">{{ n.reference_no ?? '—' }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">
            <p class="font-medium text-slate-800">{{ n.nominee_name }}</p>
            <p v-if="n.nominee_department" class="text-xs text-slate-500">{{ n.nominee_department }}</p>
          </td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ n.nominator_name }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">
            <AppBadge :color="n.source === 'kiosk' ? 'purple' : 'slate'" class="capitalize">{{ n.source === 'kiosk' ? 'Kiosk' : 'Atlas' }}</AppBadge>
          </td>
          <td class="px-4 py-3 text-sm text-slate-700">
            <AppBadge :color="statusColor(n.status)" class="capitalize">{{ n.status.replace('_', ' ') }}</AppBadge>
          </td>
          <td class="px-4 py-3 text-xs text-slate-400">{{ formatDate(n.date_submitted) }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">
            <AppButton as="link" size="sm" variant="secondary" :href="route('rewards.gantimpala.show', n.id)">View</AppButton>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="n in nominations.data" :key="n.id" class="p-4 space-y-1">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="font-medium text-slate-800">{{ n.nominee_name }}</p>
                <p class="text-xs text-slate-500">{{ n.reference_no ?? '—' }}</p>
              </div>
              <AppBadge :color="statusColor(n.status)" class="capitalize">{{ n.status.replace('_', ' ') }}</AppBadge>
            </div>
            <p class="text-xs text-slate-500">By {{ n.nominator_name }} &middot; {{ n.source === 'kiosk' ? 'Kiosk' : 'Atlas' }}</p>
            <p class="text-xs text-slate-400">{{ formatDate(n.date_submitted) }}</p>
            <div class="flex gap-2 pt-1">
              <AppButton as="link" size="sm" variant="secondary" :href="route('rewards.gantimpala.show', n.id)">View</AppButton>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No Gantimpala Agad nominations found" />
        </template>

        <template #footer>
          <PaginationControl :links="nominations.links" :total="nominations.total" />
        </template>
      </AppTable>
    </div>
  </AdminLayout>
</template>

<script setup>
import { reactive, h } from 'vue'
import { router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import EmptyState from '@/Components/EmptyState.vue'
import PaginationControl from '@/Components/PaginationControl.vue'
import AppCard from '@/Components/AppCard.vue'

const props = defineProps({
  nominations: Object,
  stats: Object,
  filters: Object,
})

const filters = reactive({ ...props.filters })

function applyFilters() {
  router.get(route('rewards.gantimpala.index'), filters, { preserveState: true, replace: true })
}

function formatDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
}

function statusColor(s) {
  return {
    pending: 'amber',
    under_review: 'blue',
    endorsed: 'indigo',
    approved: 'green',
    rejected: 'red',
    archived: 'slate',
  }[s] ?? 'slate'
}

// Lightweight inline stat card (kept local — no new shared component needed for a 6-tile row)
const StatCard = {
  props: { label: String, value: [Number, String], color: { type: String, default: 'slate' } },
  setup(props) {
    const colorMap = {
      slate: 'text-slate-700 bg-slate-50 border-slate-100',
      amber: 'text-amber-700 bg-amber-50 border-amber-100',
      blue: 'text-blue-700 bg-blue-50 border-blue-100',
      indigo: 'text-indigo-700 bg-indigo-50 border-indigo-100',
      green: 'text-emerald-700 bg-emerald-50 border-emerald-100',
      purple: 'text-purple-700 bg-purple-50 border-purple-100',
    }
    return () => h(AppCard, { padded: false, class: 'h-full' }, () =>
      h('div', { class: `rounded-xl border p-4 h-full ${colorMap[props.color] ?? colorMap.slate}` }, [
        h('p', { class: 'text-2xl font-bold' }, String(props.value ?? 0)),
        h('p', { class: 'text-xs font-medium uppercase tracking-wide opacity-80 mt-0.5' }, props.label),
      ])
    )
  },
}
</script>
