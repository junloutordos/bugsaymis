<script setup>
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppCard from '@/Components/AppCard.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppButton from '@/Components/AppButton.vue'
import AppModal from '@/Components/AppModal.vue'
import { computed, reactive, ref, watch } from 'vue'
import axios from 'axios'
import FullCalendar from '@fullcalendar/vue3'
import dayGridPlugin from '@fullcalendar/daygrid'
import {
  Chart as ChartJS,
  Title,
  Tooltip,
  Legend,
  ArcElement,
  CategoryScale,
  LinearScale,
  BarElement,
  LineElement,
  PointElement,
  Filler,
} from 'chart.js'
import { Bar, Doughnut, Line } from 'vue-chartjs'
import { BRAND, STATUS, seriesColor } from '@/Utils/chartTheme'
import {
  CalendarDaysIcon,
  ChartBarIcon,
  CheckCircleIcon,
  ClockIcon,
  ExclamationTriangleIcon,
  TruckIcon,
  UserGroupIcon,
  WrenchScrewdriverIcon,
} from '@heroicons/vue/24/outline'

ChartJS.register(
  Title,
  Tooltip,
  Legend,
  ArcElement,
  CategoryScale,
  LinearScale,
  BarElement,
  LineElement,
  PointElement,
  Filler,
)

const props = defineProps({
  generatedAt:        { type: String, default: '' },
  summary:            { type: Object, default: () => ({}) },
  moduleBreakdown:    { type: Array,  default: () => [] },
  statusBreakdown:    { type: Array,  default: () => [] },
  monthlyTrend:       { type: Object, default: () => ({ labels: [], datasets: [] }) },
  driverAnalytics:    { type: Object, default: () => ({ rows: [], unassignedApproved: 0 }) },
  personnelAnalytics: { type: Object, default: () => ({ rows: [], unassignedActive: 0, categoryBreakdown: [] }) },
  operationalQueues:  { type: Object, default: () => ({ overdueWork: [], unassignedVehicles: [], unassignedWork: [] }) },
  upcomingSchedule:   { type: Array,  default: () => [] },
})

const sem = {
  active: STATUS.warning,
  completed: STATUS.success,
  declined: STATUS.danger,
  vehicle: BRAND,
  facility: '#0D9488',
  service: '#7C3AED',
  work: '#D97706',
}

const chartBase = {
  plugins: {
    legend: { position: 'bottom' },
    tooltip: { mode: 'index', intersect: false },
  },
}

const barOptions = {
  ...chartBase,
  scales: {
    y: { beginAtZero: true, ticks: { precision: 0 } },
  },
}

const horizontalBarOptions = {
  ...chartBase,
  indexAxis: 'y',
  scales: {
    x: { beginAtZero: true, ticks: { precision: 0 } },
  },
}

const lineOptions = {
  ...chartBase,
  interaction: { mode: 'index', intersect: false },
  scales: {
    y: { beginAtZero: true, ticks: { precision: 0 } },
  },
}

const doughnutOptions = {
  ...chartBase,
}

const moduleChartData = computed(() => ({
  labels: props.moduleBreakdown.map(row => row.label),
  datasets: [
    { label: 'Active', data: props.moduleBreakdown.map(row => row.active), backgroundColor: sem.active },
    { label: 'Completed', data: props.moduleBreakdown.map(row => row.completed), backgroundColor: sem.completed },
    { label: 'Declined', data: props.moduleBreakdown.map(row => row.declined), backgroundColor: sem.declined },
  ],
}))

const monthlyTrendData = computed(() => ({
  labels: props.monthlyTrend.labels ?? [],
  datasets: (props.monthlyTrend.datasets ?? []).map((row, index) => ({
    label: row.label,
    data: row.data,
    borderColor: seriesColor(index),
    backgroundColor: seriesColor(index),
    fill: false,
  })),
}))

const topDrivers = computed(() => (props.driverAnalytics.rows ?? []).slice(0, 8))
const driverChartData = computed(() => ({
  labels: topDrivers.value.map(row => row.name),
  datasets: [
    { label: 'Assigned', data: topDrivers.value.map(row => row.assigned), backgroundColor: sem.vehicle },
    { label: 'Upcoming', data: topDrivers.value.map(row => row.upcoming), backgroundColor: '#0284C7' },
  ],
}))

const topPersonnel = computed(() => (props.personnelAnalytics.rows ?? []).slice(0, 8))
const personnelChartData = computed(() => ({
  labels: topPersonnel.value.map(row => row.name),
  datasets: [
    { label: 'Active', data: topPersonnel.value.map(row => row.active), backgroundColor: sem.work },
    { label: 'Completed', data: topPersonnel.value.map(row => row.completed), backgroundColor: sem.completed },
  ],
}))

const workCategoryData = computed(() => ({
  labels: (props.personnelAnalytics.categoryBreakdown ?? []).map(row => row.category),
  datasets: [{
    data: (props.personnelAnalytics.categoryBreakdown ?? []).map(row => row.total),
    backgroundColor: (props.personnelAnalytics.categoryBreakdown ?? []).map((_, i) => seriesColor(i)),
  }],
}))

const statusChartRows = computed(() => props.statusBreakdown.slice(0, 8))
const statusChartData = computed(() => ({
  labels: statusChartRows.value.map(row => `${row.module}: ${row.status}`),
  datasets: [{
    data: statusChartRows.value.map(row => row.total),
    backgroundColor: statusChartRows.value.map((_, i) => seriesColor(i)),
  }],
}))

const kpiCards = computed(() => [
  {
    label: 'Total Requests',
    value: props.summary.totalRequests ?? 0,
    sub: `${props.summary.thisMonth ?? 0} filed this month`,
    icon: ChartBarIcon,
    iconClass: 'bg-blue-50 text-blue-700',
  },
  {
    label: 'Active Workload',
    value: props.summary.activeRequests ?? 0,
    sub: `${props.summary.scheduledThisWeek ?? 0} scheduled this week`,
    icon: ClockIcon,
    iconClass: 'bg-amber-50 text-amber-700',
  },
  {
    label: 'Completed',
    value: props.summary.completedRequests ?? 0,
    sub: `${props.summary.scheduledToday ?? 0} calendar items today`,
    icon: CheckCircleIcon,
    iconClass: 'bg-emerald-50 text-emerald-700',
  },
  {
    label: 'Needs Assignment',
    value: (props.summary.unassignedVehicles ?? 0) + (props.summary.unassignedWork ?? 0),
    sub: `${props.summary.overdueWorkRequests ?? 0} overdue work requests`,
    icon: ExclamationTriangleIcon,
    iconClass: 'bg-rose-50 text-rose-700',
  },
])

const calendarRef = ref(null)
const selectedEvent = ref(null)
const loadingEvents = ref(false)
const typeFilters = reactive({
  vehicle: true,
  facility: true,
  service: true,
  work: true,
})
const statusFilter = ref('all')

const typeOptions = [
  { key: 'vehicle', label: 'Vehicles', color: sem.vehicle },
  { key: 'facility', label: 'Facilities', color: sem.facility },
  { key: 'service', label: 'Services', color: sem.service },
  { key: 'work', label: 'Work', color: sem.work },
]

const statusOptions = [
  { value: 'all', label: 'All statuses' },
  { value: 'active', label: 'Active' },
  { value: 'completed', label: 'Completed' },
  { value: 'declined', label: 'Declined' },
]

function baseType(type) {
  return type === 'work-completed' ? 'work' : type
}

function statusBucket(status) {
  const value = (status || '').toLowerCase()
  if (value.includes('declined') || value.includes('rejected') || value.includes('cancelled')) return 'declined'
  if (value.includes('completed') || value.includes('approved')) return 'completed'
  return 'active'
}

function eventAllowed(event) {
  const type = baseType(event.extendedProps?.type)
  if (!typeFilters[type]) return false
  if (statusFilter.value === 'all') return true
  return statusBucket(event.extendedProps?.status) === statusFilter.value
}

async function fetchCalendarEvents(info, success, failure) {
  loadingEvents.value = true
  try {
    const { data } = await axios.get(route('general-services.dashboard.events'), {
      params: { start: info.startStr, end: info.endStr },
    })
    success((data || []).filter(eventAllowed))
  } catch (error) {
    failure(error)
  } finally {
    loadingEvents.value = false
  }
}

const calendarOptions = computed(() => ({
  plugins: [dayGridPlugin],
  initialView: 'dayGridMonth',
  height: 'auto',
  fixedWeekCount: false,
  dayMaxEventRows: 3,
  eventDisplay: 'block',
  headerToolbar: { left: 'prev,next today', center: 'title', right: '' },
  events: fetchCalendarEvents,
  eventClick: ({ event }) => {
    selectedEvent.value = {
      title: event.title,
      start: event.start,
      end: event.end,
      ...event.extendedProps,
    }
  },
}))

watch([() => ({ ...typeFilters }), statusFilter], () => {
  calendarRef.value?.getApi()?.refetchEvents()
})

function formatNumber(value) {
  return Number(value || 0).toLocaleString('en-PH')
}

function formatDate(value) {
  if (!value) return '—'
  return new Date(value).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
}

function formatDateTime(value) {
  if (!value) return '—'
  return new Date(value).toLocaleString('en-PH', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' })
}

// Return AppBadge color keyword for a status (was raw Tailwind ring classes pre-migration)
function statusClass(status) {
  const bucket = statusBucket(status)
  if (bucket === 'completed') return 'green'
  if (bucket === 'declined') return 'red'
  return 'amber'
}
</script>

<template>
  <Head title="General Services Dashboard" />
  <AdminLayout title="General Services Dashboard">
    <div class="space-y-6">
      <AppPageHeader title="General Services Dashboard" subtitle="Operational analytics for vehicle, facility, work, and service requests.">
        <template #actions>
          <span class="text-xs text-slate-500">Updated {{ generatedAt ? formatDateTime(generatedAt) : '—' }}</span>
        </template>
      </AppPageHeader>

      <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        <AppCard v-for="card in kpiCards" :key="card.label">
          <div class="flex items-start justify-between gap-3">
            <div>
              <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ card.label }}</p>
              <p class="mt-2 text-3xl font-semibold text-slate-900">{{ formatNumber(card.value) }}</p>
              <p class="mt-1 text-sm text-slate-500">{{ card.sub }}</p>
            </div>
            <div :class="['rounded-lg p-2.5', card.iconClass]">
              <component :is="card.icon" class="h-5 w-5" />
            </div>
          </div>
        </AppCard>
      </div>

      <div class="grid grid-cols-1 gap-5 xl:grid-cols-3">
        <AppCard title="Monthly Request Trend" subtitle="Last 12 months by request type" class="xl:col-span-2">
          <div class="h-72">
            <Line :data="monthlyTrendData" :options="lineOptions" />
          </div>
        </AppCard>

        <AppCard title="Status Mix" subtitle="Top status groups across services">
          <div class="h-72">
            <Doughnut v-if="statusChartRows.length" :data="statusChartData" :options="doughnutOptions" />
            <div v-else class="flex h-full items-center justify-center text-sm text-slate-400">No request data</div>
          </div>
        </AppCard>
      </div>

      <div class="grid grid-cols-1 gap-5 xl:grid-cols-2">
        <AppCard title="Module Workload" subtitle="Active, completed, and declined totals">
          <div class="h-72">
            <Bar :data="moduleChartData" :options="barOptions" />
          </div>
        </AppCard>

        <AppCard title="Work Request Categories" subtitle="Skilled personnel workload by category">
          <div class="h-72">
            <Doughnut v-if="personnelAnalytics.categoryBreakdown?.length" :data="workCategoryData" :options="doughnutOptions" />
            <div v-else class="flex h-full items-center justify-center text-sm text-slate-400">No category data</div>
          </div>
        </AppCard>
      </div>

      <div class="grid grid-cols-1 gap-5 xl:grid-cols-2">
        <AppCard>
          <div class="mb-4 flex items-center justify-between">
            <div>
              <p class="text-sm font-semibold text-slate-800">Driver Analytics</p>
              <p class="text-xs text-slate-500">{{ driverAnalytics.unassignedApproved ?? 0 }} approved vehicle requests still need a driver</p>
            </div>
            <TruckIcon class="h-5 w-5 text-blue-600" />
          </div>
          <div class="h-72">
            <Bar v-if="topDrivers.length" :data="driverChartData" :options="horizontalBarOptions" />
            <div v-else class="flex h-full items-center justify-center text-sm text-slate-400">No driver assignments yet</div>
          </div>
          <div class="mt-5 overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead>
                <tr class="border-b border-slate-100 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                  <th class="py-2 pr-3">Driver</th>
                  <th class="px-3 py-2 text-right">Assigned</th>
                  <th class="px-3 py-2 text-right">Upcoming</th>
                  <th class="px-3 py-2 text-right">Passengers</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="row in driverAnalytics.rows" :key="row.id">
                  <td class="py-2 pr-3 text-slate-800">{{ row.name }}</td>
                  <td class="px-3 py-2 text-right text-slate-600">{{ row.assigned }}</td>
                  <td class="px-3 py-2 text-right text-slate-600">{{ row.upcoming }}</td>
                  <td class="px-3 py-2 text-right text-slate-600">{{ row.passengers }}</td>
                </tr>
                <tr v-if="!driverAnalytics.rows?.length">
                  <td colspan="4" class="py-6 text-center text-slate-400">No drivers found.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </AppCard>

        <AppCard>
          <div class="mb-4 flex items-center justify-between">
            <div>
              <p class="text-sm font-semibold text-slate-800">Skilled Personnel Analytics</p>
              <p class="text-xs text-slate-500">{{ personnelAnalytics.unassignedActive ?? 0 }} active work requests still need personnel</p>
            </div>
            <UserGroupIcon class="h-5 w-5 text-amber-600" />
          </div>
          <div class="h-72">
            <Bar v-if="topPersonnel.length" :data="personnelChartData" :options="horizontalBarOptions" />
            <div v-else class="flex h-full items-center justify-center text-sm text-slate-400">No personnel assignments yet</div>
          </div>
          <div class="mt-5 overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead>
                <tr class="border-b border-slate-100 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                  <th class="py-2 pr-3">Personnel</th>
                  <th class="px-3 py-2 text-right">Active</th>
                  <th class="px-3 py-2 text-right">Done</th>
                  <th class="px-3 py-2 text-right">Overdue</th>
                  <th class="px-3 py-2 text-right">Avg Days</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="row in personnelAnalytics.rows" :key="row.id">
                  <td class="py-2 pr-3 text-slate-800">{{ row.name }}</td>
                  <td class="px-3 py-2 text-right text-slate-600">{{ row.active }}</td>
                  <td class="px-3 py-2 text-right text-slate-600">{{ row.completed }}</td>
                  <td class="px-3 py-2 text-right text-slate-600">{{ row.overdue }}</td>
                  <td class="px-3 py-2 text-right text-slate-600">{{ row.avgDaysToClose ?? '—' }}</td>
                </tr>
                <tr v-if="!personnelAnalytics.rows?.length">
                  <td colspan="5" class="py-6 text-center text-slate-400">No skilled personnel found.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </AppCard>
      </div>

      <AppCard>
        <div class="mb-4 flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
          <div>
            <div class="flex items-center gap-2">
              <CalendarDaysIcon class="h-5 w-5 text-slate-600" />
              <p class="text-sm font-semibold text-slate-800">Comprehensive Services Calendar</p>
            </div>
            <p class="mt-1 text-xs text-slate-500">Work due dates, completed work, facility bookings, vehicle trips, and requests for services.</p>
          </div>
          <div class="flex flex-wrap items-center gap-3">
            <label v-for="type in typeOptions" :key="type.key" class="inline-flex items-center gap-2 text-xs font-medium text-slate-600">
              <input v-model="typeFilters[type.key]" type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
              <span class="h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: type.color }"></span>
              {{ type.label }}
            </label>
            <select v-model="statusFilter" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
              <option v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
          </div>
        </div>
        <div v-if="loadingEvents" class="mb-2 text-xs text-slate-400">Loading calendar items...</div>
        <div class="overflow-hidden rounded-lg border border-slate-100 text-xs">
          <FullCalendar ref="calendarRef" :options="calendarOptions" />
        </div>
      </AppCard>

      <div class="grid grid-cols-1 gap-5 xl:grid-cols-3">
        <AppCard>
          <div class="mb-4 flex items-center gap-2">
            <ClockIcon class="h-5 w-5 text-slate-600" />
            <p class="text-sm font-semibold text-slate-800">Upcoming 7 Days</p>
          </div>
          <div class="space-y-3">
            <a v-for="item in upcomingSchedule" :key="item.id" :href="item.extendedProps.url" class="block rounded-lg border border-slate-100 p-3 hover:bg-slate-50">
              <div class="flex items-start justify-between gap-3">
                <p class="text-sm font-medium text-slate-800">{{ item.title }}</p>
                <AppBadge :color="statusClass(item.extendedProps.status)">{{ item.extendedProps.status }}</AppBadge>
              </div>
              <p class="mt-1 text-xs text-slate-500">{{ item.extendedProps.module }} · {{ formatDateTime(item.start) }}</p>
            </a>
            <p v-if="!upcomingSchedule.length" class="py-6 text-center text-sm text-slate-400">No upcoming items.</p>
          </div>
        </AppCard>

        <AppCard>
          <div class="mb-4 flex items-center gap-2">
            <ExclamationTriangleIcon class="h-5 w-5 text-rose-600" />
            <p class="text-sm font-semibold text-slate-800">Overdue Work</p>
          </div>
          <div class="space-y-3">
            <a v-for="item in operationalQueues.overdueWork" :key="item.id" :href="item.url" class="block rounded-lg border border-slate-100 p-3 hover:bg-slate-50">
              <p class="text-sm font-medium text-slate-800">{{ item.title }}</p>
              <p class="mt-1 text-xs text-slate-500">Due {{ formatDate(item.date) }} · {{ item.assignee || 'Unassigned' }}</p>
            </a>
            <p v-if="!operationalQueues.overdueWork?.length" class="py-6 text-center text-sm text-slate-400">No overdue work requests.</p>
          </div>
        </AppCard>

        <AppCard>
          <div class="mb-4 flex items-center gap-2">
            <WrenchScrewdriverIcon class="h-5 w-5 text-amber-600" />
            <p class="text-sm font-semibold text-slate-800">Assignment Queue</p>
          </div>
          <div class="space-y-4">
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Vehicles</p>
              <a v-for="item in operationalQueues.unassignedVehicles" :key="`v-${item.id}`" :href="item.url" class="mb-2 block rounded-lg border border-slate-100 p-3 hover:bg-slate-50">
                <p class="text-sm font-medium text-slate-800">{{ item.title }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ formatDate(item.date) }} · {{ item.requester || 'Unknown requester' }}</p>
              </a>
              <p v-if="!operationalQueues.unassignedVehicles?.length" class="text-xs text-slate-400">No vehicle assignment backlog.</p>
            </div>
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Work Requests</p>
              <a v-for="item in operationalQueues.unassignedWork" :key="`w-${item.id}`" :href="item.url" class="mb-2 block rounded-lg border border-slate-100 p-3 hover:bg-slate-50">
                <p class="text-sm font-medium text-slate-800">{{ item.title }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ item.status }} · {{ item.requester || 'Unknown requester' }}</p>
              </a>
              <p v-if="!operationalQueues.unassignedWork?.length" class="text-xs text-slate-400">No work assignment backlog.</p>
            </div>
          </div>
        </AppCard>
      </div>

      <AppModal :show="!!selectedEvent" :title="selectedEvent?.title" :subtitle="selectedEvent?.module" size="lg" @close="selectedEvent = null">
        <div class="space-y-3 text-sm">
          <div class="flex justify-between gap-4">
            <span class="text-slate-500">Schedule</span>
            <span class="text-right font-medium text-slate-800">{{ formatDateTime(selectedEvent?.start) }}</span>
          </div>
          <div class="flex justify-between gap-4">
            <span class="text-slate-500">Status</span>
            <AppBadge :color="statusClass(selectedEvent?.status)">{{ selectedEvent?.status }}</AppBadge>
          </div>
          <div class="flex justify-between gap-4">
            <span class="text-slate-500">Requester</span>
            <span class="text-right font-medium text-slate-800">{{ selectedEvent?.requester || '—' }}</span>
          </div>
          <div class="flex justify-between gap-4">
            <span class="text-slate-500">Assigned</span>
            <span class="text-right font-medium text-slate-800">{{ selectedEvent?.assignee || '—' }}</span>
          </div>
          <div class="flex justify-between gap-4">
            <span class="text-slate-500">Location</span>
            <span class="text-right font-medium text-slate-800">{{ selectedEvent?.location || '—' }}</span>
          </div>
        </div>
        <template #footer>
          <AppButton variant="secondary" @click="selectedEvent = null">Close</AppButton>
          <AppButton as="a" :href="selectedEvent?.url">Open Request</AppButton>
        </template>
      </AppModal>
    </div>
  </AdminLayout>
</template>
