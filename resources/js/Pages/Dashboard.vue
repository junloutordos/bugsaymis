<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import {
  Chart as ChartJS,
  Title,
  Tooltip,
  Legend,
  ArcElement,
  CategoryScale,
  LinearScale,
  PointElement,
  BarElement,
} from 'chart.js'
import { Pie, Bar, Doughnut, Scatter } from 'vue-chartjs'
import { computed, ref, onMounted } from 'vue'
import axios from 'axios'
import FullCalendar from '@fullcalendar/vue3'
import dayGridPlugin from '@fullcalendar/daygrid'
import {
  UsersIcon,
  AcademicCapIcon,
  ClockIcon,
  DocumentCheckIcon,
} from '@heroicons/vue/24/solid'

ChartJS.register(Title, Tooltip, Legend, ArcElement, CategoryScale, LinearScale, PointElement, BarElement)

const props = defineProps({
  // Employee stats
  totalEmployees:      { type: Number, default: 0 },
  facultyCount:        { type: Number, default: 0 },
  staffCount:          { type: Number, default: 0 },
  employeeMaleCount:   { type: Number, default: 0 },
  employeeFemaleCount: { type: Number, default: 0 },
  activeDivisions:     { type: Number, default: 0 },
  employeesByDivision: { type: Array,  default: () => [] },

  // Scholar stats
  scholarsCount: { type: Number, default: 0 },
  maleCount:     { type: Number, default: 0 },
  femaleCount:   { type: Number, default: 0 },

  // Library
  libraryAttendanceByGrade:       { type: Array, default: () => [0,0,0,0,0,0] },
  libraryAttendanceMaleByGrade:   { type: Array, default: () => [0,0,0,0,0,0] },
  libraryAttendanceFemaleByGrade: { type: Array, default: () => [0,0,0,0,0,0] },

  // IPCR
  ipcrByStatus:  { type: Array,  default: () => [] },
  ipcrForReview: { type: Number, default: 0 },
  recentIPCRs:   { type: Array,  default: () => [] },

  // IT Job Requests
  itjrByCategory:  { type: Array, default: () => [] },

  // Requests
  requestOverview:      { type: Array,  default: () => [] },
  totalPendingRequests: { type: Number, default: 0 },

  // Monthly trends
  monthlyTrends: { type: Object, default: () => ({ labels: [], datasets: [] }) },
})

// ── Chart Options ───────────────────────────────────────────────────────────
const legendLabels = { usePointStyle: true, pointStyleWidth: 8, padding: 14, font: { size: 11 } }

const pieOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { position: 'bottom', labels: legendLabels } },
}

const hBarOptions = {
  responsive: true,
  maintainAspectRatio: false,
  indexAxis: 'y',
  plugins: { legend: { display: false } },
  scales: {
    x: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#f1f5f9' } },
    y: { grid: { display: false }, ticks: { font: { size: 11 } } },
  },
}

const stackedBarOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { position: 'bottom', labels: legendLabels } },
  scales: {
    x: { stacked: true, grid: { display: false } },
    y: { stacked: true, beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#f1f5f9' } },
  },
}

const barOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { position: 'bottom', labels: legendLabels } },
  scales: {
    x: { grid: { display: false } },
    y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#f1f5f9' } },
  },
}

// ── Chart Data ──────────────────────────────────────────────────────────────
// Blue + amber interleaved palette
const chartPalette = ['#1d4ed8', '#f59e0b', '#2563eb', '#fbbf24', '#3b82f6', '#fcd34d', '#60a5fa', '#fde68a']
const moduleColors = ['#1d4ed8', '#f59e0b', '#2563eb', '#fbbf24', '#3b82f6', '#fcd34d', '#60a5fa']
const moduleColorsLight = ['#93c5fd80', '#fcd34d80', '#60a5fa80', '#fde68a80', '#bfdbfe80', '#fef9c380', '#dbeafe80']

const requestOverviewData = computed(() => ({
  labels: props.requestOverview.map(r => r.label),
  datasets: [
    {
      label: 'Pending / In Progress',
      data: props.requestOverview.map(r => r.pending),
      backgroundColor: moduleColorsLight,
    },
    {
      label: 'Completed',
      data: props.requestOverview.map(r => r.completed ?? 0),
      backgroundColor: moduleColors,
    },
  ],
}))

const monthlyTrendsData = computed(() => ({
  labels: props.monthlyTrends.labels ?? [],
  datasets: (props.monthlyTrends.datasets ?? []).map((d, i) => ({
    label: d.label,
    data: d.data,
    backgroundColor: chartPalette[i % chartPalette.length],
    stack: 'stack',
  })),
}))

const ipcrStatusData = computed(() => ({
  labels: props.ipcrByStatus.map(r => r.status),
  datasets: [{
    data: props.ipcrByStatus.map(r => r.total),
    backgroundColor: chartPalette,
  }],
}))

const itjrCategoryData = computed(() => ({
  labels: props.itjrByCategory.map(r => r.category),
  datasets: [{
    data: props.itjrByCategory.map(r => r.total),
    backgroundColor: chartPalette,
  }],
}))

const gsModules = ['Vehicle Requests', 'Facility Requests', 'Service Requests', 'Work Requests']
const generalServicesData = computed(() => {
  const rows = props.requestOverview.filter(r => gsModules.includes(r.label))
  return {
    labels: rows.map(r => r.label),
    datasets: [{ data: rows.map(r => r.total), backgroundColor: chartPalette }],
  }
})

const employeesByDivisionData = computed(() => ({
  labels: props.employeesByDivision.map(d => d.division),
  datasets: [{
    label: 'Employees',
    data: props.employeesByDivision.map(d => d.count),
    backgroundColor: props.employeesByDivision.map((_, i) => chartPalette[i % chartPalette.length]),
  }],
}))

const employeeGenderData = computed(() => ({
  labels: ['Male', 'Female'],
  datasets: [{
    data: [props.employeeMaleCount || 0, props.employeeFemaleCount || 0],
    backgroundColor: ['#3b82f6', '#f59e0b'],
  }],
}))

const studentGenderData = computed(() => ({
  labels: ['Male', 'Female'],
  datasets: [{
    data: [props.maleCount || 0, props.femaleCount || 0],
    backgroundColor: ['#3b82f6', '#f59e0b'],
  }],
}))

const libraryAttendanceData = computed(() => ({
  labels: ['Grade 7','Grade 8','Grade 9','Grade 10','Grade 11','Grade 12'],
  datasets: [
    { label: 'Male',   data: props.libraryAttendanceMaleByGrade,   backgroundColor: '#3b82f6' },
    { label: 'Female', data: props.libraryAttendanceFemaleByGrade, backgroundColor: '#f59e0b' },
  ],
}))

// ── Calendar ────────────────────────────────────────────────────────────────
const calendarOptions = {
  plugins: [dayGridPlugin],
  initialView: 'dayGridMonth',
  height: 'auto',
  fixedWeekCount: false,
  headerToolbar: { left: 'prev,next', center: 'title', right: '' },
  eventDisplay: 'block',
  dayMaxEventRows: 2,
  events: [],
}

// ── Helpers ──────────────────────────────────────────────────────────────────


// Modal state for date click
const showDateModal = ref(false)
const selectedDate = ref('')
const dateBookings = ref([])
const loadingBookings = ref(false)
const bookingsError = ref(null)
const calendarContainer = ref(null)

async function openDateModal(dateStr) {
  selectedDate.value = dateStr
  showDateModal.value = true
  loadingBookings.value = true
  bookingsError.value = null
  try {
    const url = route('facility-requests.byDate')
    console.log('[Dashboard] fetching bookings for', dateStr, '->', url)
    const res = await axios.get(url, { params: { date: dateStr } })
    dateBookings.value = res.data || []
  } catch (e) {
    bookingsError.value = 'Failed to load bookings'
    dateBookings.value = []
    console.error('[Dashboard] failed to fetch bookings', e)
  } finally {
    loadingBookings.value = false
  }
}

// attach handler for FullCalendar date clicks
// Register dateClick after mount via calendar API to avoid FullCalendar warning
onMounted(() => {
  // Use DOM delegation: calendar day cells include a data-date attribute
  try {
    const handler = (ev) => {
      let el = ev.target
      const root = calendarContainer.value
      while (el && el !== root) {
        if (el.dataset && el.dataset.date) {
          const dateStr = el.dataset.date
          console.log('[Dashboard] day cell clicked', dateStr)
          openDateModal(dateStr)
          return
        }
        el = el.parentElement
      }
    }
    if (calendarContainer.value) {
      calendarContainer.value.addEventListener('click', handler)
      console.log('[Dashboard] attached DOM click handler to calendar container')
    } else {
      console.warn('[Dashboard] calendar container not found to attach click handler')
    }
  } catch (e) {
    console.error('[Dashboard] failed to attach DOM click handler', e)
  }
})
</script>

<template>
  <AdminLayout title="Dashboard">
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">

      <!-- ── Left Main Content ─────────────────────────────────────────── -->
      <div class="lg:col-span-3 space-y-6">

        <!-- Stat Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div class="bg-gradient-to-br from-blue-500 to-indigo-600 p-5 rounded-2xl shadow-md hover:shadow-lg transition-all duration-200 text-white">
            <div class="h-10 w-10 rounded-xl bg-white/20 flex items-center justify-center mb-3">
              <UsersIcon class="h-5 w-5" />
            </div>
            <h2 class="text-3xl font-bold tracking-tight">{{ totalEmployees.toLocaleString() }}</h2>
            <p class="text-xs font-medium opacity-90 mt-0.5">Total Employees</p>
            <p class="text-xs opacity-60 mt-0.5">{{ activeDivisions }} division{{ activeDivisions !== 1 ? 's' : '' }}</p>
          </div>

          <div class="bg-gradient-to-br from-blue-500 to-indigo-600 p-5 rounded-2xl shadow-md hover:shadow-lg transition-all duration-200 text-white">
            <div class="h-10 w-10 rounded-xl bg-white/20 flex items-center justify-center mb-3">
              <AcademicCapIcon class="h-5 w-5" />
            </div>
            <h2 class="text-3xl font-bold tracking-tight">{{ scholarsCount.toLocaleString() }}</h2>
            <p class="text-xs font-medium opacity-90 mt-0.5">Scholars</p>
            <p class="text-xs opacity-60 mt-0.5">{{ (maleCount + femaleCount).toLocaleString() }} enrolled</p>
          </div>

          <div class="bg-gradient-to-br from-amber-400 to-yellow-500 p-5 rounded-2xl shadow-md hover:shadow-lg transition-all duration-200 text-white">
            <div class="h-10 w-10 rounded-xl bg-white/20 flex items-center justify-center mb-3">
              <ClockIcon class="h-5 w-5" />
            </div>
            <h2 class="text-3xl font-bold tracking-tight">{{ totalPendingRequests.toLocaleString() }}</h2>
            <p class="text-xs font-medium opacity-90 mt-0.5">Pending Requests</p>
            <p class="text-xs opacity-60 mt-0.5">across all modules</p>
          </div>

          <div class="bg-gradient-to-br from-amber-400 to-yellow-500 p-5 rounded-2xl shadow-md hover:shadow-lg transition-all duration-200 text-white">
            <div class="h-10 w-10 rounded-xl bg-white/20 flex items-center justify-center mb-3">
              <DocumentCheckIcon class="h-5 w-5" />
            </div>
            <h2 class="text-3xl font-bold tracking-tight">{{ ipcrForReview.toLocaleString() }}</h2>
            <p class="text-xs font-medium opacity-90 mt-0.5">IPCR For Review</p>
            <p class="text-xs opacity-60 mt-0.5">awaiting review</p>
          </div>
        </div>

        <!-- Request Overview + IPCR Status -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <div class="lg:col-span-2 bg-white p-6 rounded-2xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow duration-200">
            <div class="flex items-center gap-2 mb-1">
              <span class="h-2.5 w-2.5 rounded-full bg-amber-400 shrink-0"></span>
              <h3 class="text-sm font-semibold text-gray-800 tracking-tight">Request Overview</h3>
            </div>
            <p class="text-xs text-gray-400 mb-4 pl-[18px]">Active / pending requests per module</p>
            <div class="min-h-[260px]">
              <Bar v-if="requestOverview.length" :data="requestOverviewData" :options="hBarOptions" />
              <p v-else class="text-sm text-gray-400 text-center pt-10">No request data available</p>
            </div>
          </div>

          <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow duration-200">
            <div class="flex items-center gap-2 mb-1">
              <span class="h-2.5 w-2.5 rounded-full bg-amber-400 shrink-0"></span>
              <h3 class="text-sm font-semibold text-gray-800 tracking-tight">IPCR Status</h3>
            </div>
            <p class="text-xs text-gray-400 mb-4 pl-[18px]">Distribution by status</p>
            <div class="min-h-[260px]">
              <Doughnut v-if="ipcrByStatus.length" :data="ipcrStatusData" :options="pieOptions" />
              <p v-else class="text-sm text-gray-400 text-center pt-10">No IPCR data</p>
            </div>
          </div>
        </div>

        <!-- Monthly Trends -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow duration-200">
          <div class="flex items-center gap-2 mb-1">
            <span class="h-2.5 w-2.5 rounded-full bg-blue-500 shrink-0"></span>
            <h3 class="text-sm font-semibold text-gray-800 tracking-tight">Monthly Request Trends</h3>
          </div>
          <p class="text-xs text-gray-400 mb-4 pl-[18px]">Requests submitted over the last 6 months</p>
          <div class="min-h-[280px]">
            <Bar
              v-if="monthlyTrends.labels && monthlyTrends.labels.length"
              :data="monthlyTrendsData"
              :options="stackedBarOptions"
            />
            <p v-else class="text-sm text-gray-400 text-center pt-10">No trend data available</p>
          </div>
        </div>

        <!-- IT Job by Category + General Services -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow duration-200">
            <div class="flex items-center gap-2 mb-1">
              <span class="h-2.5 w-2.5 rounded-full bg-blue-500 shrink-0"></span>
              <h3 class="text-sm font-semibold text-gray-800 tracking-tight">IT Job Requests by Category</h3>
            </div>
            <p class="text-xs text-gray-400 mb-4 pl-[18px]">All-time breakdown</p>
            <div class="min-h-[220px]">
              <Pie v-if="itjrByCategory.length" :data="itjrCategoryData" :options="pieOptions" />
              <p v-else class="text-sm text-gray-400 text-center pt-10">No IT Job Request data</p>
            </div>
          </div>

          <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow duration-200">
            <div class="flex items-center gap-2 mb-1">
              <span class="h-2.5 w-2.5 rounded-full bg-amber-400 shrink-0"></span>
              <h3 class="text-sm font-semibold text-gray-800 tracking-tight">General Services Breakdown</h3>
            </div>
            <p class="text-xs text-gray-400 mb-4 pl-[18px]">All-time breakdown</p>
            <div class="min-h-[220px]">
              <Pie v-if="generalServicesData.datasets[0].data.some(v => v > 0)" :data="generalServicesData" :options="pieOptions" />
              <p v-else class="text-sm text-gray-400 text-center pt-10">No General Services data</p>
            </div>
          </div>
        </div>

        <!-- Employees by Division + Employee Gender -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <div v-if="employeesByDivision.length" class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow duration-200">
            <div class="flex items-center gap-2 mb-1">
              <span class="h-2.5 w-2.5 rounded-full bg-blue-500 shrink-0"></span>
              <h3 class="text-sm font-semibold text-gray-800 tracking-tight">Employees by Division</h3>
            </div>
            <p class="text-xs text-gray-400 mb-4 pl-[18px]">Top 10 divisions by headcount</p>
            <div class="min-h-[240px]">
              <Bar :data="employeesByDivisionData" :options="hBarOptions" />
            </div>
          </div>

          <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow duration-200">
            <div class="flex items-center gap-2 mb-1">
              <span class="h-2.5 w-2.5 rounded-full bg-amber-400 shrink-0"></span>
              <h3 class="text-sm font-semibold text-gray-800 tracking-tight">Employee Gender</h3>
            </div>
            <p class="text-xs text-gray-400 mb-4 pl-[18px]">{{ totalEmployees }} total employees</p>
            <div class="min-h-[240px]">
              <Pie
                v-if="employeeMaleCount + employeeFemaleCount > 0"
                :data="employeeGenderData"
                :options="pieOptions"
              />
              <p v-else class="text-sm text-gray-400 text-center pt-10">No employee gender data</p>
            </div>
          </div>
        </div>

        <!-- Student Analytics -->
        <div class="space-y-4">
          <div class="flex items-center gap-3">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-700 tracking-widest uppercase">Student Analytics</span>
          </div>
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow duration-200">
              <div class="flex items-center gap-2 mb-1">
                <span class="h-2.5 w-2.5 rounded-full bg-amber-400 shrink-0"></span>
                <h3 class="text-sm font-semibold text-gray-800 tracking-tight">Student Gender</h3>
              </div>
              <p class="text-xs text-gray-400 mb-4 pl-[18px]">{{ (maleCount + femaleCount).toLocaleString() }} enrolled</p>
              <div class="min-h-[220px]">
                <Pie
                  v-if="maleCount + femaleCount > 0"
                  :data="studentGenderData"
                  :options="pieOptions"
                />
                <p v-else class="text-sm text-gray-400 text-center pt-10">No student gender data</p>
              </div>
            </div>

            <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow duration-200">
              <div class="flex items-center gap-2 mb-1">
                <span class="h-2.5 w-2.5 rounded-full bg-blue-500 shrink-0"></span>
                <h3 class="text-sm font-semibold text-gray-800 tracking-tight">Library Traffic</h3>
              </div>
              <p class="text-xs text-gray-400 mb-4 pl-[18px]">Current month by grade level</p>
              <div class="min-h-[220px]">
                <Bar :data="libraryAttendanceData" :options="barOptions" />
              </div>
            </div>
          </div>
        </div>

      </div>

      <!-- ── Right Sidebar ─────────────────────────────────────────────── -->
      <div class="space-y-6">

        <!-- Calendar -->
        <div class="bg-white p-4 rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
          <div class="flex items-center gap-2 mb-3">
            <span class="h-2.5 w-2.5 rounded-full bg-blue-500 shrink-0"></span>
            <h3 class="text-sm font-semibold text-gray-800 tracking-tight">Calendar</h3>
          </div>
          <div class="rounded-lg overflow-hidden border border-gray-100">
            <div ref="calendarContainer">
              <FullCalendar :options="calendarOptions" />
            </div>
            <!-- Date bookings modal -->
            <div v-if="showDateModal" class="fixed inset-0 flex items-center justify-center z-50">
              <div class="absolute inset-0 bg-black bg-opacity-40" @click="showDateModal = false"></div>
              <div class="bg-white rounded-xl shadow-lg max-w-2xl w-full mx-4 p-4 z-10">
                <div class="flex items-center justify-between mb-3">
                  <h4 class="font-semibold text-gray-700">Facility Requests — {{ selectedDate }}</h4>
                  <button class="text-gray-400 hover:text-gray-600 text-sm" @click="showDateModal = false">Close</button>
                </div>
                <div v-if="loadingBookings" class="py-6 text-center text-sm text-gray-500">Loading…</div>
                <div v-else>
                  <div v-if="bookingsError" class="text-red-500 mb-2 text-sm">{{ bookingsError }}</div>
                  <div v-if="dateBookings.length === 0" class="text-sm text-gray-500">No facility requests for this date.</div>
                  <ul v-else class="space-y-3">
                    <li v-for="b in dateBookings" :key="b.id" class="p-3 border border-gray-100 rounded-lg">
                      <div class="flex items-start justify-between">
                        <div>
                          <div class="text-sm text-gray-500">Requestor: <span class="font-medium text-gray-700">{{ b.requester_name || '—' }}</span> <small class="text-gray-400">{{ b.requester_unit || '' }}</small></div>
                          <div class="text-base font-semibold text-gray-800">{{ b.activity || '—' }}</div>
                          <div class="text-sm text-gray-500">Venue: <span class="font-medium">{{ (b.venue && b.venue.length) ? b.venue.join(', ') : '—' }}</span></div>
                          <div class="text-sm text-gray-500">Time: <span class="font-medium">{{ b.time_start || '—' }} — {{ b.time_end || '—' }}</span></div>
                        </div>
                        <div class="text-right space-y-1">
                          <div :class="{'text-blue-600': b.status && b.status.includes('Approved'), 'text-gray-500': b.status && b.status.includes('Pending'), 'text-gray-400': !b.status}" class="text-sm font-medium">{{ b.status || '—' }}</div>
                          <div class="text-sm">
                            <span v-if="b.has_it_job" class="inline-flex items-center px-2 py-1 rounded bg-blue-50 text-xs text-blue-700">IT Job: <strong class="ml-1">{{ b.it_job_status || 'Unknown' }}</strong></span>
                            <span v-else class="inline-flex items-center px-2 py-1 rounded bg-gray-50 text-xs text-gray-400">No IT job</span>
                          </div>
                        </div>
                      </div>
                    </li>
                  </ul>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Pending Requests Summary -->
        <div class="bg-gradient-to-b from-blue-600 to-indigo-700 p-4 rounded-2xl shadow-md">
          <div class="flex items-center gap-2 mb-3">
            <span class="h-2.5 w-2.5 rounded-full bg-yellow-400 shrink-0"></span>
            <h3 class="text-sm font-semibold text-white tracking-tight">Pending Summary</h3>
          </div>
          <ul class="space-y-2">
            <li
              v-for="item in requestOverview"
              :key="item.label"
              class="flex items-center justify-between text-sm"
            >
              <span class="text-blue-100 truncate mr-2">{{ item.label }}</span>
              <span
                v-if="item.pending > 0"
                class="shrink-0 text-xs font-semibold px-2 py-0.5 rounded-full bg-yellow-400 text-gray-900"
              >
                {{ item.pending }}
              </span>
              <span v-else class="shrink-0 text-xs text-blue-300">0</span>
            </li>
          </ul>
        </div>

        <!-- Recent IPCR Activity -->
        <div class="bg-gradient-to-b from-blue-600 to-indigo-700 p-4 rounded-2xl shadow-md">
          <div class="flex items-center gap-2 mb-3">
            <span class="h-2.5 w-2.5 rounded-full bg-yellow-400 shrink-0"></span>
            <h3 class="text-sm font-semibold text-white tracking-tight">Recent IPCR Activity</h3>
          </div>
          <ul v-if="recentIPCRs.length" class="space-y-3">
            <li
              v-for="ipcr in recentIPCRs"
              :key="ipcr.id"
              class="text-sm border-l-2 border-yellow-400 pl-3"
            >
              <p class="font-medium text-white truncate">{{ ipcr.user }}</p>
              <p class="text-xs text-blue-200">{{ ipcr.rating_period }}</p>
              <p class="text-xs mt-0.5 text-blue-100">
                {{ ipcr.status }}
                <span class="text-blue-300 ml-1">· {{ ipcr.updated_at }}</span>
              </p>
            </li>
          </ul>
          <p v-else class="text-sm text-blue-200">No recent IPCR submissions</p>
        </div>

        <!-- HR Summary -->
        <div class="bg-gradient-to-b from-blue-600 to-indigo-700 p-4 rounded-2xl shadow-md">
          <div class="flex items-center gap-2 mb-3">
            <span class="h-2.5 w-2.5 rounded-full bg-yellow-400 shrink-0"></span>
            <h3 class="text-sm font-semibold text-white tracking-tight">HR Summary</h3>
          </div>
          <ul class="space-y-2 text-sm text-blue-100">
            <li class="flex justify-between">
              <span>Total Employees</span>
              <span class="font-semibold text-white">{{ totalEmployees.toLocaleString() }}</span>
            </li>
            <li class="flex justify-between">
              <span>Faculty</span>
              <span class="font-semibold text-white">{{ facultyCount.toLocaleString() }}</span>
            </li>
            <li class="flex justify-between">
              <span>Staff</span>
              <span class="font-semibold text-white">{{ staffCount.toLocaleString() }}</span>
            </li>
            <li class="flex justify-between">
              <span>Active Divisions</span>
              <span class="font-semibold text-white">{{ activeDivisions }}</span>
            </li>
            <li class="flex justify-between border-t border-white/20 pt-2 mt-1">
              <span>Male Employees</span>
              <span class="font-semibold text-white">{{ employeeMaleCount.toLocaleString() }}</span>
            </li>
            <li class="flex justify-between">
              <span>Female Employees</span>
              <span class="font-semibold text-white">{{ employeeFemaleCount.toLocaleString() }}</span>
            </li>
          </ul>
        </div>

      </div>
    </div>
  </AdminLayout>
</template>
