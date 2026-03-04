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
  itjrByCategory: { type: Array, default: () => [] },

  // Requests
  requestOverview:      { type: Array,  default: () => [] },
  totalPendingRequests: { type: Number, default: 0 },

  // Monthly trends
  monthlyTrends: { type: Object, default: () => ({ labels: [], datasets: [] }) },
})

// ── Chart Options ───────────────────────────────────────────────────────────
const pieOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { position: 'bottom' } },
}

const hBarOptions = {
  responsive: true,
  maintainAspectRatio: false,
  indexAxis: 'y',
  plugins: { legend: { display: false } },
  scales: { x: { beginAtZero: true, ticks: { precision: 0 } } },
}

const stackedBarOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { position: 'bottom' } },
  scales: {
    x: { stacked: true },
    y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } },
  },
}

const barOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { position: 'bottom' } },
  scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
}

// ── Chart Data ──────────────────────────────────────────────────────────────
const moduleColors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#f97316']
const chartPalette = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#f97316', '#a3e635']

const requestOverviewData = computed(() => ({
  labels: props.requestOverview.map(r => r.label),
  datasets: [{
    label: 'Active / Pending',
    data: props.requestOverview.map(r => r.pending),
    backgroundColor: moduleColors,
  }],
}))

const monthlyTrendsData = computed(() => ({
  labels: props.monthlyTrends.labels ?? [],
  datasets: (props.monthlyTrends.datasets ?? []).map(d => ({
    label: d.label,
    data: d.data,
    backgroundColor: d.color,
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

const employeesByDivisionData = computed(() => ({
  labels: props.employeesByDivision.map(d => d.division),
  datasets: [{
    label: 'Employees',
    data: props.employeesByDivision.map(d => d.count),
    backgroundColor: '#3b82f6',
  }],
}))

const employeeGenderData = computed(() => ({
  labels: ['Male', 'Female'],
  datasets: [{
    data: [props.employeeMaleCount || 0, props.employeeFemaleCount || 0],
    backgroundColor: ['#3b82f6', '#facc15'],
  }],
}))

const studentGenderData = computed(() => ({
  labels: ['Male', 'Female'],
  datasets: [{
    data: [props.maleCount || 0, props.femaleCount || 0],
    backgroundColor: ['#3b82f6', '#facc15'],
  }],
}))

const libraryAttendanceData = computed(() => ({
  labels: ['Grade 7','Grade 8','Grade 9','Grade 10','Grade 11','Grade 12'],
  datasets: [
    { label: 'Male',   data: props.libraryAttendanceMaleByGrade,   backgroundColor: '#3b82f6' },
    { label: 'Female', data: props.libraryAttendanceFemaleByGrade, backgroundColor: '#facc15' },
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
const statusColor = (status) => {
  const map = {
    'Draft':        'text-gray-500',
    'For Review':   'text-yellow-600',
    'For Rating':   'text-blue-600',
    'Rated':        'text-green-600',
    'Completed':    'text-emerald-600',
    'Returned':     'text-red-500',
    'Disapproved':  'text-red-600',
  }
  return map[status] ?? 'text-gray-500'
}

const requestBadgeColor = (label) => {
  const map = {
    'IT Job Requests':   'bg-blue-100 text-blue-700',
    'Vehicle Requests':  'bg-green-100 text-green-700',
    'Facility Requests': 'bg-yellow-100 text-yellow-700',
    'Service Requests':  'bg-purple-100 text-purple-700',
    'Work Requests':     'bg-red-100 text-red-700',
    'Messengerial':      'bg-cyan-100 text-cyan-700',
    'Consultations':     'bg-orange-100 text-orange-700',
  }
  return map[label] ?? 'bg-gray-100 text-gray-700'
}

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
          <div class="bg-gradient-to-r from-blue-500 to-indigo-600 p-4 rounded-xl shadow hover:shadow-lg transition text-white flex items-center space-x-3">
            <UsersIcon class="h-10 w-10 opacity-90 shrink-0" />
            <div>
              <p class="text-xs opacity-80">Total Employees</p>
              <h2 class="text-2xl font-bold">{{ totalEmployees.toLocaleString() }}</h2>
              <p class="text-xs opacity-70">{{ activeDivisions }} division{{ activeDivisions !== 1 ? 's' : '' }}</p>
            </div>
          </div>

          <div class="bg-gradient-to-r from-blue-500 to-indigo-600 p-4 rounded-xl shadow hover:shadow-lg transition text-white flex items-center space-x-3">
            <AcademicCapIcon class="h-10 w-10 opacity-90 shrink-0" />
            <div>
              <p class="text-xs opacity-80">Scholars</p>
              <h2 class="text-2xl font-bold">{{ scholarsCount.toLocaleString() }}</h2>
              <p class="text-xs opacity-70">{{ (maleCount + femaleCount).toLocaleString() }} enrolled</p>
            </div>
          </div>

          <div class="bg-gradient-to-r from-orange-500 to-red-500 p-4 rounded-xl shadow hover:shadow-lg transition text-white flex items-center space-x-3">
            <ClockIcon class="h-10 w-10 opacity-90 shrink-0" />
            <div>
              <p class="text-xs opacity-80">Pending Requests</p>
              <h2 class="text-2xl font-bold">{{ totalPendingRequests.toLocaleString() }}</h2>
              <p class="text-xs opacity-70">across all modules</p>
            </div>
          </div>

          <div class="bg-gradient-to-r from-yellow-400 to-yellow-600 p-4 rounded-xl shadow hover:shadow-lg transition text-white flex items-center space-x-3">
            <DocumentCheckIcon class="h-10 w-10 opacity-90 shrink-0" />
            <div>
              <p class="text-xs opacity-80">IPCR For Review</p>
              <h2 class="text-2xl font-bold">{{ ipcrForReview.toLocaleString() }}</h2>
              <p class="text-xs opacity-70">awaiting review</p>
            </div>
          </div>
        </div>

        <!-- Request Overview + IPCR Status -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <div class="lg:col-span-2 bg-white p-6 rounded-xl shadow">
            <h3 class="text-lg font-semibold mb-1">Request Overview</h3>
            <p class="text-xs text-gray-400 mb-4">Active / pending requests per module</p>
            <div class="min-h-[260px]">
              <Bar v-if="requestOverview.length" :data="requestOverviewData" :options="hBarOptions" />
              <p v-else class="text-sm text-gray-400 text-center pt-10">No request data available</p>
            </div>
          </div>

          <div class="bg-white p-6 rounded-xl shadow">
            <h3 class="text-lg font-semibold mb-1">IPCR Status</h3>
            <p class="text-xs text-gray-400 mb-4">Distribution by status</p>
            <div class="min-h-[260px]">
              <Doughnut v-if="ipcrByStatus.length" :data="ipcrStatusData" :options="pieOptions" />
              <p v-else class="text-sm text-gray-400 text-center pt-10">No IPCR data</p>
            </div>
          </div>
        </div>

        <!-- Monthly Trends -->
        <div class="bg-white p-6 rounded-xl shadow">
          <h3 class="text-lg font-semibold mb-1">Monthly Request Trends</h3>
          <p class="text-xs text-gray-400 mb-4">Requests submitted over the last 6 months</p>
          <div class="min-h-[280px]">
            <Bar
              v-if="monthlyTrends.labels && monthlyTrends.labels.length"
              :data="monthlyTrendsData"
              :options="stackedBarOptions"
            />
            <p v-else class="text-sm text-gray-400 text-center pt-10">No trend data available</p>
          </div>
        </div>

        <!-- IT Job by Category + Employee Gender -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <div class="bg-white p-6 rounded-xl shadow">
            <h3 class="text-lg font-semibold mb-1">IT Job Requests by Category</h3>
            <p class="text-xs text-gray-400 mb-4">All-time breakdown</p>
            <div class="min-h-[220px]">
              <Pie v-if="itjrByCategory.length" :data="itjrCategoryData" :options="pieOptions" />
              <p v-else class="text-sm text-gray-400 text-center pt-10">No IT Job Request data</p>
            </div>
          </div>

          <div class="bg-white p-6 rounded-xl shadow">
            <h3 class="text-lg font-semibold mb-1">Employee Gender</h3>
            <p class="text-xs text-gray-400 mb-4">{{ totalEmployees }} total employees</p>
            <div class="min-h-[220px]">
              <Pie
                v-if="employeeMaleCount + employeeFemaleCount > 0"
                :data="employeeGenderData"
                :options="pieOptions"
              />
              <p v-else class="text-sm text-gray-400 text-center pt-10">No employee gender data</p>
            </div>
          </div>
        </div>

        <!-- Employees by Division -->
        <div v-if="employeesByDivision.length" class="bg-white p-6 rounded-xl shadow">
          <h3 class="text-lg font-semibold mb-1">Employees by Division</h3>
          <p class="text-xs text-gray-400 mb-4">Top 10 divisions by headcount</p>
          <div class="min-h-[240px]">
            <Bar :data="employeesByDivisionData" :options="hBarOptions" />
          </div>
        </div>

        <!-- Student Analytics -->
        <div class="space-y-4">
          <h2 class="text-xl font-bold text-gray-700">Student Analytics</h2>
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white p-6 rounded-xl shadow">
              <h3 class="text-lg font-semibold mb-1">Student Gender</h3>
              <p class="text-xs text-gray-400 mb-4">{{ (maleCount + femaleCount).toLocaleString() }} enrolled</p>
              <div class="min-h-[220px]">
                <Pie
                  v-if="maleCount + femaleCount > 0"
                  :data="studentGenderData"
                  :options="pieOptions"
                />
                <p v-else class="text-sm text-gray-400 text-center pt-10">No student gender data</p>
              </div>
            </div>

            <div class="bg-white p-6 rounded-xl shadow">
              <h3 class="text-lg font-semibold mb-1">Library Traffic</h3>
              <p class="text-xs text-gray-400 mb-4">Current month by grade level</p>
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
        <div class="bg-white p-4 rounded-xl shadow overflow-hidden">
          <h3 class="text-base font-semibold mb-3">Calendar</h3>
          <div class="rounded-lg border border-gray-100 overflow-hidden">
            <div ref="calendarContainer">
              <FullCalendar :options="calendarOptions" />
            </div>
            <!-- Date bookings modal -->
            <div v-if="showDateModal" class="fixed inset-0 flex items-center justify-center z-50">
              <div class="absolute inset-0 bg-black bg-opacity-40" @click="showDateModal = false"></div>
              <div class="bg-white rounded-xl shadow-lg max-w-2xl w-full mx-4 p-4 z-10">
                <div class="flex items-center justify-between mb-3">
                  <h4 class="font-semibold">Facility Requests — {{ selectedDate }}</h4>
                  <button class="text-gray-600 hover:text-gray-800" @click="showDateModal = false">Close</button>
                </div>
                <div v-if="loadingBookings" class="py-6 text-center text-sm text-gray-600">Loading…</div>
                <div v-else>
                  <div v-if="bookingsError" class="text-red-600 mb-2">{{ bookingsError }}</div>
                  <div v-if="dateBookings.length === 0" class="text-sm text-gray-600">No facility requests for this date.</div>
                  <ul v-else class="space-y-3">
                    <li v-for="b in dateBookings" :key="b.id" class="p-3 border rounded-lg">
                      <div class="flex items-start justify-between">
                        <div>
                          <div class="text-sm text-gray-500">Requestor: <span class="font-medium">{{ b.requester_name || '—' }}</span> <small class="text-gray-400">{{ b.requester_unit || '' }}</small></div>
                          <div class="text-lg font-semibold">{{ b.activity || '—' }}</div>
                          <div class="text-sm text-gray-600">Venue: <span class="font-medium">{{ (b.venue && b.venue.length) ? b.venue.join(', ') : '—' }}</span></div>
                          <div class="text-sm text-gray-600">Time: <span class="font-medium">{{ b.time_start || '—' }} — {{ b.time_end || '—' }}</span></div>
                        </div>
                        <div class="text-right space-y-1">
                          <div :class="{'text-green-600': b.status && b.status.includes('Approved'), 'text-yellow-600': b.status && b.status.includes('Pending'), 'text-gray-600': !b.status}" class="text-sm font-medium">{{ b.status || '—' }}</div>
                          <div class="text-sm">
                            <span v-if="b.has_it_job" class="inline-flex items-center px-2 py-1 rounded bg-gray-100 text-xs">IT Job: <strong class="ml-2">{{ b.it_job_status || 'Unknown' }}</strong></span>
                            <span v-else class="inline-flex items-center px-2 py-1 rounded bg-gray-50 text-xs text-gray-500">No IT job</span>
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
        <div class="bg-white p-4 rounded-xl shadow">
          <h3 class="text-base font-semibold mb-3">Pending Summary</h3>
          <ul class="space-y-2">
            <li
              v-for="item in requestOverview"
              :key="item.label"
              class="flex items-center justify-between text-sm"
            >
              <span class="text-gray-600 truncate mr-2">{{ item.label }}</span>
              <span
                v-if="item.pending > 0"
                class="shrink-0 text-xs font-semibold px-2 py-0.5 rounded-full"
                :class="requestBadgeColor(item.label)"
              >
                {{ item.pending }}
              </span>
              <span v-else class="shrink-0 text-xs text-gray-300">0</span>
            </li>
          </ul>
        </div>

        <!-- Recent IPCR Activity -->
        <div class="bg-white p-4 rounded-xl shadow">
          <h3 class="text-base font-semibold mb-3">Recent IPCR Activity</h3>
          <ul v-if="recentIPCRs.length" class="space-y-3">
            <li
              v-for="ipcr in recentIPCRs"
              :key="ipcr.id"
              class="text-sm border-l-2 border-blue-200 pl-3"
            >
              <p class="font-medium text-gray-700 truncate">{{ ipcr.user }}</p>
              <p class="text-xs text-gray-400">{{ ipcr.rating_period }}</p>
              <p class="text-xs mt-0.5" :class="statusColor(ipcr.status)">
                {{ ipcr.status }}
                <span class="text-gray-300 ml-1">· {{ ipcr.updated_at }}</span>
              </p>
            </li>
          </ul>
          <p v-else class="text-sm text-gray-400">No recent IPCR submissions</p>
        </div>

        <!-- HR Summary -->
        <div class="bg-white p-4 rounded-xl shadow">
          <h3 class="text-base font-semibold mb-3">HR Summary</h3>
          <ul class="space-y-2 text-sm text-gray-600">
            <li class="flex justify-between">
              <span>Total Employees</span>
              <span class="font-semibold text-gray-800">{{ totalEmployees.toLocaleString() }}</span>
            </li>
            <li class="flex justify-between">
              <span>Faculty</span>
              <span class="font-semibold text-gray-800">{{ facultyCount.toLocaleString() }}</span>
            </li>
            <li class="flex justify-between">
              <span>Staff</span>
              <span class="font-semibold text-gray-800">{{ staffCount.toLocaleString() }}</span>
            </li>
            <li class="flex justify-between">
              <span>Active Divisions</span>
              <span class="font-semibold text-gray-800">{{ activeDivisions }}</span>
            </li>
            <li class="flex justify-between border-t pt-2 mt-1">
              <span>Male Employees</span>
              <span class="font-semibold text-blue-600">{{ employeeMaleCount.toLocaleString() }}</span>
            </li>
            <li class="flex justify-between">
              <span>Female Employees</span>
              <span class="font-semibold text-yellow-600">{{ employeeFemaleCount.toLocaleString() }}</span>
            </li>
          </ul>
        </div>

      </div>
    </div>
  </AdminLayout>
</template>
