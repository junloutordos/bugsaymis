<script setup>
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppCard from '@/Components/AppCard.vue'
import AppBadge from '@/Components/AppBadge.vue'
import { BeakerIcon, CalendarDaysIcon, ChartBarIcon, CubeIcon, StarIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  cards: Object,
  upcoming: Array,
  calendar: Array,
  calendarMonth: String,
  csm: Object,
  monthly: Array,
  can: Object,
})

const requestRows = computed(() => [
  { key: 'reservation', label: 'Laboratory Reservation Form (LRF)' },
  { key: 'equipment', label: 'Equipment Request & Accountability Form (LREAF)' },
  { key: 'reagent', label: 'Reagent Request Form (RRF)' },
].map(row => ({ ...row, ...props.cards.requests[row.key] })))

const maxMonthly = computed(() => Math.max(1, ...props.monthly.map(row => row.requests)))
const calendarTitle = computed(() => new Date(`${props.calendarMonth}-01T00:00:00`).toLocaleDateString('en-PH', { month: 'long', year: 'numeric' }))
const calendarCells = computed(() => {
  const [year, month] = props.calendarMonth.split('-').map(Number)
  const leading = new Date(year, month - 1, 1).getDay()
  const days = new Date(year, month, 0).getDate()
  return Array.from({ length: Math.ceil((leading + days) / 7) * 7 }, (_, index) => {
    const day = index - leading + 1
    if (day < 1 || day > days) return null
    const date = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`
    const bookings = props.calendar.filter(item => item.date_start <= date && (item.date_end || item.date_start) >= date)
    return { day, date, bookings }
  })
})
const formatDate = value => value
  ? new Date(`${value}T00:00:00`).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' })
  : '—'
</script>

<template>
  <Head title="Science Laboratory Management" />
  <AdminLayout title="Science Laboratory Management">
    <div class="space-y-6">
      <AppPageHeader hero title="Science Laboratory Management"
        subtitle="Requests, reservations, inventory, laboratory schedules, and client satisfaction at a glance.">
        <template #actions>
          <Link :href="route('science-lab.requests.index')" class="rounded-lg bg-white/15 px-4 py-2 text-sm font-medium text-white hover:bg-white/25">
            Request or Reserve
          </Link>
        </template>
      </AppPageHeader>

      <div class="grid gap-4 sm:grid-cols-2">
        <Link :href="route('science-lab.laboratories.index')" class="rounded-xl border border-slate-200 bg-white p-5 transition hover:shadow-md">
          <div class="flex items-center gap-4">
            <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600"><BeakerIcon class="h-6 w-6" /></span>
            <div><div class="text-3xl font-semibold text-slate-800">{{ cards.laboratories }}</div><div class="text-sm text-slate-500">Laboratories</div></div>
          </div>
        </Link>
        <Link :href="route('science-lab.equipment.index')" class="rounded-xl border border-slate-200 bg-white p-5 transition hover:shadow-md">
          <div class="flex items-center gap-4">
            <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600"><CubeIcon class="h-6 w-6" /></span>
            <div><div class="text-3xl font-semibold text-slate-800">{{ cards.equipment }}</div><div class="text-sm text-slate-500">Equipment</div></div>
          </div>
        </Link>
      </div>

      <AppCard title="Request Status Summary" :padded="false">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
              <tr><th class="px-5 py-3 text-left">Request Form</th><th class="px-5 py-3 text-center">Total</th><th class="px-5 py-3 text-center">Pending</th><th class="px-5 py-3 text-center">Approved / Completed</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="row in requestRows" :key="row.key">
                <td class="px-5 py-3 font-medium text-slate-700">{{ row.label }}</td>
                <td class="px-5 py-3 text-center text-slate-700">{{ row.total }}</td>
                <td class="px-5 py-3 text-center"><AppBadge color="amber">{{ row.pending }}</AppBadge></td>
                <td class="px-5 py-3 text-center"><AppBadge color="green">{{ row.approved }}</AppBadge></td>
              </tr>
            </tbody>
          </table>
        </div>
      </AppCard>

      <div class="grid gap-6 lg:grid-cols-2">
        <AppCard :title="`Laboratory Reservation Calendar — ${calendarTitle}`" :padded="false">
          <template #header><CalendarDaysIcon class="h-5 w-5 text-indigo-500" /></template>
          <div class="grid grid-cols-7 border-t border-slate-100 text-center text-[10px] font-semibold uppercase tracking-wide text-slate-400"><div v-for="day in ['Sun','Mon','Tue','Wed','Thu','Fri','Sat']" :key="day" class="border-r border-slate-100 py-2 last:border-r-0">{{ day }}</div></div>
          <div class="grid grid-cols-7 border-t border-slate-100">
            <div v-for="(cell, index) in calendarCells" :key="index" class="min-h-20 border-b border-r border-slate-100 p-1 last:border-r-0" :class="!cell && 'bg-slate-50/60'">
              <template v-if="cell"><div class="mb-1 text-right text-[10px] font-medium text-slate-400">{{ cell.day }}</div><div v-for="booking in cell.bookings.slice(0, 2)" :key="booking.id" class="mb-1 truncate rounded bg-indigo-50 px-1 py-0.5 text-[9px] text-indigo-700" :title="`${booking.room} — ${booking.subject}`">{{ booking.time_start }} {{ booking.room }}</div><div v-if="cell.bookings.length > 2" class="text-[9px] text-slate-400">+{{ cell.bookings.length - 2 }} more</div></template>
            </div>
          </div>
        </AppCard>

        <AppCard title="Client Satisfaction" :padded="false">
          <template #header><StarIcon class="h-5 w-5 text-amber-500" /></template>
          <div class="grid grid-cols-3 divide-x divide-slate-100 py-6 text-center">
            <div><div class="text-2xl font-semibold text-slate-800">{{ csm.average ?? '—' }}</div><div class="text-xs text-slate-500">Average / 5</div></div>
            <div><div class="text-2xl font-semibold text-slate-800">{{ csm.responses }}</div><div class="text-xs text-slate-500">Responses</div></div>
            <div><div class="text-2xl font-semibold text-amber-600">{{ csm.pending }}</div><div class="text-xs text-slate-500">Pending CSM</div></div>
          </div>
          <p class="border-t border-slate-100 px-5 py-3 text-xs text-slate-500">A completed request must receive client feedback before its requester can submit another laboratory request.</p>
        </AppCard>
      </div>

      <AppCard title="Requests — Last Six Months">
        <template #header><ChartBarIcon class="h-5 w-5 text-indigo-500" /></template>
        <div class="flex h-48 items-end justify-around gap-4 pt-6">
          <div v-for="row in monthly" :key="row.label" class="flex h-full flex-1 flex-col items-center justify-end gap-2">
            <span class="text-xs font-semibold text-slate-600">{{ row.requests }}</span>
            <div class="w-full max-w-16 rounded-t-lg bg-indigo-500 transition-all" :style="{ height: `${Math.max(4, (row.requests / maxMonthly) * 130)}px` }" />
            <span class="text-xs text-slate-500">{{ row.label }}</span>
          </div>
        </div>
      </AppCard>
    </div>
  </AdminLayout>
</template>
