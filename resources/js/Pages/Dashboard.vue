<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import {
  Chart as ChartJS,
  Title, Tooltip, Legend,
  ArcElement,
  CategoryScale, LinearScale,
  PointElement, LineElement, BarElement,
  Filler,
} from 'chart.js'
import { Line, Bar, Doughnut } from 'vue-chartjs'
import { computed, ref, onMounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import axios from 'axios'
import FullCalendar from '@fullcalendar/vue3'
import dayGridPlugin from '@fullcalendar/daygrid'
import {
  UsersIcon,
  AcademicCapIcon,
  ClockIcon,
  DocumentCheckIcon,
  BookOpenIcon,
  StarIcon,
  ComputerDesktopIcon,
  TruckIcon,
  BuildingOfficeIcon,
  WrenchScrewdriverIcon,
  ArrowTrendingUpIcon,
  CheckBadgeIcon,
} from '@heroicons/vue/24/outline'

ChartJS.register(
  Title, Tooltip, Legend,
  ArcElement,
  CategoryScale, LinearScale,
  PointElement, LineElement, BarElement,
  Filler,
)

const page = usePage()
const authUser = computed(() => page.props.auth?.user)
const greeting = computed(() => {
  const h = new Date().getHours()
  if (h < 12) return 'Good morning'
  if (h < 18) return 'Good afternoon'
  return 'Good evening'
})
const today = computed(() =>
  new Date().toLocaleDateString('en-PH', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })
)

const props = defineProps({
  totalEmployees:      { type: Number, default: 0 },
  facultyCount:        { type: Number, default: 0 },
  staffCount:          { type: Number, default: 0 },
  employeeMaleCount:   { type: Number, default: 0 },
  employeeFemaleCount: { type: Number, default: 0 },
  activeDivisions:     { type: Number, default: 0 },
  employeesByDivision: { type: Array,  default: () => [] },
  scholarsCount:       { type: Number, default: 0 },
  maleCount:           { type: Number, default: 0 },
  femaleCount:         { type: Number, default: 0 },
  libraryAttendanceByGrade:       { type: Array, default: () => [0,0,0,0,0,0] },
  libraryAttendanceMaleByGrade:   { type: Array, default: () => [0,0,0,0,0,0] },
  libraryAttendanceFemaleByGrade: { type: Array, default: () => [0,0,0,0,0,0] },
  ipcrByStatus:  { type: Array,  default: () => [] },
  ipcrForReview: { type: Number, default: 0 },
  recentIPCRs:   { type: Array,  default: () => [] },
  itjrByCategory:       { type: Array, default: () => [] },
  requestOverview:      { type: Array,  default: () => [] },
  totalPendingRequests: { type: Number, default: 0 },
  monthlyTrends: { type: Object, default: () => ({ labels: [], datasets: [] }) },
  lndStats:     { type: Object, default: () => ({ programs: 0, sessions: 0, tna_pending: 0, idp_pending: 0 }) },
  rewardsStats: { type: Object, default: () => ({ total: 0, pending: 0, approved: 0, awarded_this_year: 0 }) },
  // GAD / sex-disaggregated
  ipcrBySex:    { type: Object, default: () => ({ male: 0, female: 0, unspecified: 0 }) },
  tnaBySex:     { type: Object, default: () => ({ male: 0, female: 0, unspecified: 0 }) },
  idpBySex:     { type: Object, default: () => ({ male: 0, female: 0, unspecified: 0 }) },
  rewardsBySex: { type: Object, default: () => ({ male: 0, female: 0, unspecified: 0 }) },
  employeesByDivisionWithSex: { type: Array, default: () => [] },
})

// ── Palette ──────────────────────────────────────────────────────────────────
const p = {
  blue:   '#3b82f6',
  indigo: '#6366f1',
  violet: '#8b5cf6',
  emerald:'#10b981',
  amber:  '#f59e0b',
  rose:   '#f43f5e',
  cyan:   '#06b6d4',
  slate:  '#64748b',
  teal:   '#14b8a6',
  orange: '#f97316',
}
const palette = Object.values(p)

// ── Chart base options ────────────────────────────────────────────────────────
const base = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      position: 'bottom',
      labels: { usePointStyle: true, pointStyleWidth: 8, padding: 12, font: { size: 10 } },
    },
    tooltip: { mode: 'index', intersect: false },
  },
}

const lineOptions = {
  ...base,
  interaction: { mode: 'index', intersect: false },
  scales: {
    x: { grid: { display: false }, ticks: { font: { size: 10 } } },
    y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#f1f5f9' } },
  },
}

const hBarOptions = {
  ...base,
  indexAxis: 'y',
  plugins: { ...base.plugins, legend: { display: false } },
  scales: {
    x: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#f1f5f9' } },
    y: { grid: { display: false }, ticks: { font: { size: 10 } } },
  },
}

const barOptions = {
  ...base,
  scales: {
    x: { grid: { display: false }, ticks: { font: { size: 10 } } },
    y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#f1f5f9' } },
  },
}

const stackedHBarOptions = {
  ...base,
  indexAxis: 'y',
  scales: {
    x: { beginAtZero: true, stacked: true, ticks: { precision: 0 }, grid: { color: '#f1f5f9' } },
    y: { stacked: true, grid: { display: false }, ticks: { font: { size: 10 } } },
  },
}

const doughnutOptions = {
  ...base,
  cutout: '68%',
  plugins: {
    ...base.plugins,
    legend: { position: 'bottom', labels: { usePointStyle: true, pointStyleWidth: 8, padding: 10, font: { size: 10 } } },
  },
}

// ── Chart data ────────────────────────────────────────────────────────────────
const monthlyTrendsData = computed(() => ({
  labels: props.monthlyTrends.labels ?? [],
  datasets: (props.monthlyTrends.datasets ?? []).map((d, i) => ({
    label: d.label,
    data: d.data,
    borderColor: palette[i % palette.length],
    backgroundColor: palette[i % palette.length] + '18',
    tension: 0.4,
    fill: false,
    pointRadius: 3,
    pointHoverRadius: 5,
    borderWidth: 2,
  })),
}))

const requestOverviewData = computed(() => ({
  labels: props.requestOverview.map(r => r.label),
  datasets: [
    {
      label: 'Pending',
      data: props.requestOverview.map(r => r.pending),
      backgroundColor: p.amber + 'cc',
      borderRadius: 4,
    },
    {
      label: 'Completed',
      data: props.requestOverview.map(r => r.completed ?? 0),
      backgroundColor: p.emerald + 'cc',
      borderRadius: 4,
    },
  ],
}))

const ipcrStatusData = computed(() => ({
  labels: props.ipcrByStatus.map(r => r.status),
  datasets: [{ data: props.ipcrByStatus.map(r => r.total), backgroundColor: palette, borderWidth: 0 }],
}))

const itjrCategoryData = computed(() => ({
  labels: props.itjrByCategory.map(r => r.category),
  datasets: [{ data: props.itjrByCategory.map(r => r.total), backgroundColor: palette, borderWidth: 0 }],
}))

const gsModules = ['Vehicle Requests', 'Facility Requests', 'Service Requests', 'Work Requests']
const generalServicesData = computed(() => {
  const rows = props.requestOverview.filter(r => gsModules.includes(r.label))
  return {
    labels: rows.map(r => r.label),
    datasets: [{ data: rows.map(r => r.total), backgroundColor: [p.blue, p.indigo, p.violet, p.cyan], borderWidth: 0 }],
  }
})

const divisionSexData = computed(() => ({
  labels: props.employeesByDivisionWithSex.map(d => d.name),
  datasets: [
    { label: 'Male',   data: props.employeesByDivisionWithSex.map(d => d.male),   backgroundColor: p.blue + 'cc', borderRadius: 4 },
    { label: 'Female', data: props.employeesByDivisionWithSex.map(d => d.female), backgroundColor: p.rose + 'cc', borderRadius: 4 },
  ],
}))

const pct = (n, t) => t > 0 ? Math.round(n / t * 100) : 0

const gadSummaryRows = computed(() => {
  const libM = props.libraryAttendanceMaleByGrade.reduce((a, b) => a + b, 0)
  const libF = props.libraryAttendanceFemaleByGrade.reduce((a, b) => a + b, 0)
  const libT = libM + libF
  const iT = (props.ipcrBySex.male + props.ipcrBySex.female + props.ipcrBySex.unspecified) || 0
  const tT = (props.tnaBySex.male  + props.tnaBySex.female  + props.tnaBySex.unspecified)  || 0
  const dT = (props.idpBySex.male  + props.idpBySex.female  + props.idpBySex.unspecified)  || 0
  const rT = props.rewardsStats.total || 0
  return [
    { label: 'Employees',          total: props.totalEmployees,  male: props.employeeMaleCount,  female: props.employeeFemaleCount,  pctM: pct(props.employeeMaleCount,  props.totalEmployees), pctF: pct(props.employeeFemaleCount,  props.totalEmployees) },
    { label: 'Students (Enrolled)', total: props.maleCount + props.femaleCount, male: props.maleCount, female: props.femaleCount, pctM: pct(props.maleCount, props.maleCount + props.femaleCount), pctF: pct(props.femaleCount, props.maleCount + props.femaleCount) },
    { label: 'Library Attendance',  total: libT, male: libM, female: libF, pctM: pct(libM, libT), pctF: pct(libF, libT) },
    { label: 'IPCR Submissions',    total: iT, male: props.ipcrBySex.male, female: props.ipcrBySex.female, pctM: pct(props.ipcrBySex.male, iT), pctF: pct(props.ipcrBySex.female, iT) },
    { label: 'TNA Submissions',     total: tT, male: props.tnaBySex.male,  female: props.tnaBySex.female,  pctM: pct(props.tnaBySex.male,  tT), pctF: pct(props.tnaBySex.female,  tT) },
    { label: 'IDP Submissions',     total: dT, male: props.idpBySex.male,  female: props.idpBySex.female,  pctM: pct(props.idpBySex.male,  dT), pctF: pct(props.idpBySex.female,  dT) },
    { label: 'Reward Nominations',  total: rT, male: props.rewardsBySex.male, female: props.rewardsBySex.female, pctM: pct(props.rewardsBySex.male, rT), pctF: pct(props.rewardsBySex.female, rT) },
  ]
})

const employeeGenderData = computed(() => ({
  labels: ['Male', 'Female'],
  datasets: [{ data: [props.employeeMaleCount, props.employeeFemaleCount], backgroundColor: [p.blue, p.rose], borderWidth: 0 }],
}))

const studentGenderData = computed(() => ({
  labels: ['Male', 'Female'],
  datasets: [{ data: [props.maleCount, props.femaleCount], backgroundColor: [p.cyan, p.violet], borderWidth: 0 }],
}))

const libraryAttendanceData = computed(() => ({
  labels: ['G7', 'G8', 'G9', 'G10', 'G11', 'G12'],
  datasets: [
    { label: 'Male',   data: props.libraryAttendanceMaleByGrade,   backgroundColor: p.blue + 'cc',  borderRadius: 4 },
    { label: 'Female', data: props.libraryAttendanceFemaleByGrade, backgroundColor: p.rose + 'cc',  borderRadius: 4 },
  ],
}))

// ── KPI Cards ─────────────────────────────────────────────────────────────────
const kpiCards = computed(() => [
  { label: 'Total Employees', value: props.totalEmployees, sub: `${props.activeDivisions} divisions`, icon: UsersIcon,          color: 'bg-blue-500',    text: 'text-blue-600',   bg: 'bg-blue-50'   },
  { label: 'Faculty',         value: props.facultyCount,   sub: 'Teaching staff',            icon: AcademicCapIcon,     color: 'bg-indigo-500',  text: 'text-indigo-600', bg: 'bg-indigo-50' },
  { label: 'Staff',           value: props.staffCount,     sub: 'Non-teaching',              icon: UsersIcon,           color: 'bg-violet-500',  text: 'text-violet-600', bg: 'bg-violet-50' },
  { label: 'Scholars',        value: props.scholarsCount,  sub: `${props.maleCount + props.femaleCount} enrolled`, icon: AcademicCapIcon, color: 'bg-emerald-500', text: 'text-emerald-600', bg: 'bg-emerald-50' },
  { label: 'Pending Requests',value: props.totalPendingRequests, sub: 'All modules',         icon: ClockIcon,           color: 'bg-amber-500',   text: 'text-amber-600',  bg: 'bg-amber-50'  },
  { label: 'IPCR For Review', value: props.ipcrForReview,  sub: 'Awaiting review',           icon: DocumentCheckIcon,   color: 'bg-rose-500',    text: 'text-rose-600',   bg: 'bg-rose-50'   },
  { label: 'L&D Programs',    value: props.lndStats.programs, sub: `${props.lndStats.sessions} sessions`, icon: BookOpenIcon, color: 'bg-teal-500', text: 'text-teal-600',   bg: 'bg-teal-50'   },
  { label: 'Recognitions',    value: props.rewardsStats.total, sub: `${props.rewardsStats.awarded_this_year} awarded ${new Date().getFullYear()}`, icon: StarIcon, color: 'bg-orange-500', text: 'text-orange-600', bg: 'bg-orange-50' },
])

// ── Module Quick Stats ────────────────────────────────────────────────────────
const moduleStats = computed(() => [
  { label: 'IT Job Requests', pending: props.requestOverview.find(r => r.label === 'IT Job Requests')?.pending ?? 0, total: props.requestOverview.find(r => r.label === 'IT Job Requests')?.total ?? 0, icon: ComputerDesktopIcon, color: 'text-blue-600', dot: 'bg-blue-500' },
  { label: 'Vehicle Requests', pending: props.requestOverview.find(r => r.label === 'Vehicle Requests')?.pending ?? 0, total: props.requestOverview.find(r => r.label === 'Vehicle Requests')?.total ?? 0, icon: TruckIcon, color: 'text-indigo-600', dot: 'bg-indigo-500' },
  { label: 'Facility Requests', pending: props.requestOverview.find(r => r.label === 'Facility Requests')?.pending ?? 0, total: props.requestOverview.find(r => r.label === 'Facility Requests')?.total ?? 0, icon: BuildingOfficeIcon, color: 'text-violet-600', dot: 'bg-violet-500' },
  { label: 'Work Requests', pending: props.requestOverview.find(r => r.label === 'Work Requests')?.pending ?? 0, total: props.requestOverview.find(r => r.label === 'Work Requests')?.total ?? 0, icon: WrenchScrewdriverIcon, color: 'text-amber-600', dot: 'bg-amber-500' },
])

// ── L&D + Rewards Quick Stats ─────────────────────────────────────────────────
const lndCards = computed(() => [
  { label: 'Programs',     value: props.lndStats.programs,    sub: 'Active',      color: 'text-teal-600',  dot: 'bg-teal-400' },
  { label: 'Sessions',     value: props.lndStats.sessions,    sub: 'Total',       color: 'text-teal-600',  dot: 'bg-teal-300' },
  { label: 'TNA Pending',  value: props.lndStats.tna_pending, sub: 'Needs review',color: 'text-amber-600', dot: 'bg-amber-400' },
  { label: 'IDP Submitted',value: props.lndStats.idp_pending, sub: 'For approval',color: 'text-rose-600',  dot: 'bg-rose-400' },
])

const rewardsCards = computed(() => [
  { label: 'Total Nominations',  value: props.rewardsStats.total,             sub: 'All time',            color: 'text-orange-600', dot: 'bg-orange-400' },
  { label: 'Pending',            value: props.rewardsStats.pending,           sub: 'Awaiting screening',  color: 'text-amber-600',  dot: 'bg-amber-400'  },
  { label: 'Approved',           value: props.rewardsStats.approved,          sub: 'This cycle',          color: 'text-emerald-600',dot: 'bg-emerald-400'},
  { label: `Awarded ${new Date().getFullYear()}`, value: props.rewardsStats.awarded_this_year, sub: 'Incentives given', color: 'text-blue-600', dot: 'bg-blue-400' },
])

// ── Calendar ──────────────────────────────────────────────────────────────────
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
    const res = await axios.get(route('facility-requests.byDate'), { params: { date: dateStr } })
    dateBookings.value = res.data || []
  } catch (e) {
    bookingsError.value = 'Failed to load bookings'
    dateBookings.value = []
  } finally {
    loadingBookings.value = false
  }
}

onMounted(() => {
  if (calendarContainer.value) {
    calendarContainer.value.addEventListener('click', (ev) => {
      let el = ev.target
      while (el && el !== calendarContainer.value) {
        if (el.dataset?.date) { openDateModal(el.dataset.date); return }
        el = el.parentElement
      }
    })
  }
})
</script>

<template>
  <AdminLayout title="Dashboard">
    <div class="space-y-6">

      <!-- ── Greeting Header ──────────────────────────────────────────────── -->
      <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-gray-100 bg-white px-6 py-4 shadow-sm">
        <div>
          <p class="text-xs font-medium uppercase tracking-widest text-gray-400">{{ today }}</p>
          <h1 class="mt-0.5 text-xl font-bold text-gray-800">
            {{ greeting }}, {{ authUser?.name?.split(' ')[0] ?? 'Admin' }} 👋
          </h1>
          <p class="mt-0.5 text-sm text-gray-500">Here's what's happening across all modules today.</p>
        </div>
        <div class="flex items-center gap-2 rounded-xl bg-indigo-50 px-4 py-2 text-sm font-medium text-indigo-700">
          <ArrowTrendingUpIcon class="h-4 w-4" />
          {{ totalPendingRequests }} pending items across all modules
        </div>
      </div>

      <!-- ── GAD Banner ───────────────────────────────────────────────────── -->
      <div class="flex items-center gap-3 rounded-2xl border border-pink-100 bg-gradient-to-r from-blue-50 via-white to-pink-50 px-5 py-3 shadow-sm">
        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-pink-100 text-pink-600 font-bold text-xs">GAD</div>
        <p class="text-xs text-gray-600">
          <span class="font-semibold text-gray-800">Gender and Development (RA 9710)</span>
          — This dashboard presents sex-disaggregated data in compliance with the Magna Carta of Women and DOST/PSHSS GAD requirements.
        </p>
        <div class="ml-auto flex shrink-0 items-center gap-3 text-[11px] font-semibold">
          <span class="flex items-center gap-1.5 text-blue-600"><span class="inline-block h-2.5 w-2.5 rounded-full bg-blue-500"></span>Male</span>
          <span class="flex items-center gap-1.5 text-pink-600"><span class="inline-block h-2.5 w-2.5 rounded-full bg-pink-500"></span>Female</span>
        </div>
      </div>

      <!-- ── KPI Strip ────────────────────────────────────────────────────── -->
      <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 xl:grid-cols-8">
        <div v-for="card in kpiCards" :key="card.label"
          class="group relative overflow-hidden rounded-2xl border border-gray-100 bg-white p-4 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md">
          <div :class="[card.bg, 'mb-3 inline-flex h-9 w-9 items-center justify-center rounded-xl']">
            <component :is="card.icon" :class="[card.text, 'h-4 w-4']" />
          </div>
          <p class="text-2xl font-bold tracking-tight text-gray-900">{{ card.value.toLocaleString() }}</p>
          <p class="mt-0.5 text-xs font-semibold text-gray-600 leading-tight">{{ card.label }}</p>
          <p class="text-[10px] text-gray-400 mt-0.5">{{ card.sub }}</p>
          <div :class="[card.color, 'absolute bottom-0 left-0 h-0.5 w-full opacity-60']"></div>
        </div>
      </div>

      <!-- ── Main 3-col Grid ──────────────────────────────────────────────── -->
      <div class="grid grid-cols-1 gap-6 lg:grid-cols-4">

        <!-- LEFT: 3 columns -->
        <div class="space-y-6 lg:col-span-3">

          <!-- Monthly Trends (Line) -->
          <div class="card">
            <div class="card-header">
              <div>
                <p class="card-title">Monthly Request Trends</p>
                <p class="card-sub">Submissions over the last 6 months</p>
              </div>
            </div>
            <div class="min-h-[260px]">
              <Line v-if="monthlyTrends.labels?.length" :data="monthlyTrendsData" :options="lineOptions" />
              <EmptyState v-else text="No trend data" />
            </div>
          </div>

          <!-- Request Overview + IPCR -->
          <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
            <div class="card md:col-span-2">
              <div class="card-header">
                <div>
                  <p class="card-title">Request Overview</p>
                  <p class="card-sub">Pending vs completed per module</p>
                </div>
              </div>
              <div class="min-h-[240px]">
                <Bar v-if="requestOverview.length" :data="requestOverviewData" :options="hBarOptions" />
                <EmptyState v-else text="No request data" />
              </div>
            </div>

            <div class="card">
              <div class="card-header">
                <div>
                  <p class="card-title">IPCR Status</p>
                  <p class="card-sub">By review stage</p>
                </div>
              </div>
              <div class="min-h-[240px]">
                <Doughnut v-if="ipcrByStatus.length" :data="ipcrStatusData" :options="doughnutOptions" />
                <EmptyState v-else text="No IPCR data" />
              </div>
            </div>
          </div>

          <!-- IT + General Services -->
          <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
            <div class="card">
              <div class="card-header">
                <div>
                  <p class="card-title">IT Job Requests</p>
                  <p class="card-sub">Breakdown by category</p>
                </div>
              </div>
              <div class="min-h-[220px]">
                <Doughnut v-if="itjrByCategory.length" :data="itjrCategoryData" :options="doughnutOptions" />
                <EmptyState v-else text="No data" />
              </div>
            </div>

            <div class="card">
              <div class="card-header">
                <div>
                  <p class="card-title">General Services</p>
                  <p class="card-sub">Vehicle · Facility · Service · Work</p>
                </div>
              </div>
              <div class="min-h-[220px]">
                <Doughnut
                  v-if="generalServicesData.datasets[0].data.some(v => v > 0)"
                  :data="generalServicesData"
                  :options="doughnutOptions"
                />
                <EmptyState v-else text="No data" />
              </div>
            </div>
          </div>

          <!-- HR Analytics -->
          <div class="section-divider">HR Analytics</div>
          <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
            <!-- Stacked sex-disaggregated division chart -->
            <div class="card" v-if="employeesByDivisionWithSex.length">
              <div class="card-header">
                <div>
                  <p class="card-title">Employees by Division</p>
                  <p class="card-sub">Top 10 — sex-disaggregated (M / F)</p>
                </div>
              </div>
              <div class="min-h-[260px]">
                <Bar :data="divisionSexData" :options="stackedHBarOptions" />
              </div>
            </div>

            <div class="card">
              <div class="card-header">
                <div>
                  <p class="card-title">Employee Sex Breakdown</p>
                  <p class="card-sub">{{ totalEmployees.toLocaleString() }} total employees</p>
                </div>
              </div>
              <div class="min-h-[200px]">
                <Doughnut
                  v-if="employeeMaleCount + employeeFemaleCount > 0"
                  :data="employeeGenderData"
                  :options="doughnutOptions"
                />
                <EmptyState v-else text="No gender data" />
              </div>
              <!-- Sex breakdown pills -->
              <div class="mt-4 grid grid-cols-2 gap-3">
                <div class="rounded-xl bg-blue-50 px-3 py-2 text-center">
                  <p class="text-lg font-bold text-blue-700">{{ employeeMaleCount.toLocaleString() }}</p>
                  <p class="text-xs text-blue-500">Male · {{ pct(employeeMaleCount, totalEmployees) }}%</p>
                </div>
                <div class="rounded-xl bg-rose-50 px-3 py-2 text-center">
                  <p class="text-lg font-bold text-rose-700">{{ employeeFemaleCount.toLocaleString() }}</p>
                  <p class="text-xs text-rose-500">Female · {{ pct(employeeFemaleCount, totalEmployees) }}%</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Student Analytics -->
          <div class="section-divider">Student Analytics</div>
          <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
            <div class="card">
              <div class="card-header">
                <div>
                  <p class="card-title">Student Sex Breakdown</p>
                  <p class="card-sub">{{ (maleCount + femaleCount).toLocaleString() }} enrolled</p>
                </div>
              </div>
              <div class="min-h-[200px]">
                <Doughnut v-if="maleCount + femaleCount > 0" :data="studentGenderData" :options="doughnutOptions" />
                <EmptyState v-else text="No student data" />
              </div>
              <div class="mt-4 grid grid-cols-2 gap-3">
                <div class="rounded-xl bg-blue-50 px-3 py-2 text-center">
                  <p class="text-lg font-bold text-blue-700">{{ maleCount.toLocaleString() }}</p>
                  <p class="text-xs text-blue-500">Male · {{ pct(maleCount, maleCount + femaleCount) }}%</p>
                </div>
                <div class="rounded-xl bg-rose-50 px-3 py-2 text-center">
                  <p class="text-lg font-bold text-rose-700">{{ femaleCount.toLocaleString() }}</p>
                  <p class="text-xs text-rose-500">Female · {{ pct(femaleCount, maleCount + femaleCount) }}%</p>
                </div>
              </div>
            </div>

            <div class="card">
              <div class="card-header">
                <div>
                  <p class="card-title">Library Traffic</p>
                  <p class="card-sub">Current month by grade level</p>
                </div>
              </div>
              <div class="min-h-[200px]">
                <Bar :data="libraryAttendanceData" :options="barOptions" />
              </div>
            </div>
          </div>

          <!-- GAD Summary Table -->
          <div class="section-divider">GAD Summary — Sex-Disaggregated Data</div>
          <div class="card overflow-hidden p-0">
            <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100">
              <div>
                <p class="card-title">Institutional GAD Summary</p>
                <p class="card-sub">RA 9710 — Magna Carta of Women compliance</p>
              </div>
              <span class="rounded-full bg-pink-50 px-3 py-1 text-xs font-semibold text-pink-600">Sex-Disaggregated</span>
            </div>
            <div class="overflow-x-auto">
              <table class="w-full text-xs">
                <thead>
                  <tr class="border-b border-gray-100 bg-gray-50">
                    <th class="px-4 py-2.5 text-left font-semibold text-gray-600">Indicator</th>
                    <th class="px-3 py-2.5 text-right font-semibold text-gray-600">Total</th>
                    <th class="px-3 py-2.5 text-right font-semibold text-blue-600">Male</th>
                    <th class="px-3 py-2.5 text-right font-semibold text-blue-600">% M</th>
                    <th class="px-3 py-2.5 text-right font-semibold text-pink-600">Female</th>
                    <th class="px-3 py-2.5 text-right font-semibold text-pink-600">% F</th>
                    <th class="px-4 py-2.5 text-left font-semibold text-gray-500">Distribution</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="row in gadSummaryRows" :key="row.label"
                    class="border-b border-gray-50 hover:bg-gray-50 transition-colors">
                    <td class="px-4 py-2.5 font-medium text-gray-800">{{ row.label }}</td>
                    <td class="px-3 py-2.5 text-right font-bold text-gray-900">{{ row.total.toLocaleString() }}</td>
                    <td class="px-3 py-2.5 text-right text-blue-700">{{ row.male.toLocaleString() }}</td>
                    <td class="px-3 py-2.5 text-right text-blue-500 font-medium">{{ row.pctM }}%</td>
                    <td class="px-3 py-2.5 text-right text-pink-700">{{ row.female.toLocaleString() }}</td>
                    <td class="px-3 py-2.5 text-right text-pink-500 font-medium">{{ row.pctF }}%</td>
                    <td class="px-4 py-2.5">
                      <div v-if="row.total > 0" class="flex h-2 w-full overflow-hidden rounded-full bg-gray-100">
                        <div class="h-full bg-blue-400 transition-all" :style="{ width: row.pctM + '%' }"></div>
                        <div class="h-full bg-pink-400 transition-all" :style="{ width: row.pctF + '%' }"></div>
                      </div>
                      <span v-else class="text-gray-300">—</span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- L&D Section -->
          <div class="section-divider">Learning & Development</div>
          <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
            <div v-for="c in lndCards" :key="c.label"
              class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
              <div class="flex items-center gap-2 mb-2">
                <span :class="[c.dot, 'h-2 w-2 rounded-full']"></span>
                <span class="text-xs text-gray-500">{{ c.sub }}</span>
              </div>
              <p class="text-3xl font-bold text-gray-900">{{ c.value.toLocaleString() }}</p>
              <p :class="[c.color, 'mt-1 text-xs font-semibold']">{{ c.label }}</p>
            </div>
          </div>

          <!-- Rewards & Recognition Section -->
          <div class="section-divider">Rewards & Recognition (PRAISE)</div>
          <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
            <div v-for="c in rewardsCards" :key="c.label"
              class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
              <div class="flex items-center gap-2 mb-2">
                <span :class="[c.dot, 'h-2 w-2 rounded-full']"></span>
                <span class="text-xs text-gray-500">{{ c.sub }}</span>
              </div>
              <p class="text-3xl font-bold text-gray-900">{{ c.value.toLocaleString() }}</p>
              <p :class="[c.color, 'mt-1 text-xs font-semibold']">{{ c.label }}</p>
            </div>
          </div>

        </div>

        <!-- RIGHT SIDEBAR -->
        <div class="space-y-5">

          <!-- Calendar -->
          <div class="card overflow-hidden">
            <p class="card-title mb-3">Facility Calendar</p>
            <div class="rounded-xl overflow-hidden border border-gray-100 text-xs" ref="calendarContainer">
              <FullCalendar :options="calendarOptions" />
            </div>
          </div>

          <!-- Module Quick Stats -->
          <div class="card">
            <p class="card-title mb-3">Module Status</p>
            <div class="space-y-2">
              <div v-for="m in moduleStats" :key="m.label"
                class="flex items-center justify-between rounded-lg px-3 py-2 hover:bg-gray-50">
                <div class="flex items-center gap-2">
                  <span :class="[m.dot, 'h-2 w-2 shrink-0 rounded-full']"></span>
                  <span class="text-xs text-gray-700 truncate">{{ m.label }}</span>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                  <span v-if="m.pending > 0"
                    class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700">
                    {{ m.pending }}
                  </span>
                  <span class="text-[10px] text-gray-400">/ {{ m.total }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Pending Summary -->
          <div class="card">
            <p class="card-title mb-3">All Pending</p>
            <div class="space-y-1.5">
              <div v-for="item in requestOverview" :key="item.label"
                class="flex items-center justify-between rounded-lg px-3 py-1.5 hover:bg-gray-50">
                <span class="text-xs text-gray-600 truncate mr-2">{{ item.label }}</span>
                <span v-if="item.pending > 0"
                  class="shrink-0 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700">
                  {{ item.pending }}
                </span>
                <span v-else class="shrink-0 text-xs text-gray-300">—</span>
              </div>
            </div>
            <div class="mt-3 border-t pt-3 flex items-center justify-between">
              <span class="text-xs font-semibold text-gray-500">Total</span>
              <span class="rounded-full bg-indigo-100 px-3 py-0.5 text-xs font-bold text-indigo-700">
                {{ totalPendingRequests }}
              </span>
            </div>
          </div>

          <!-- Recent IPCR -->
          <div class="card">
            <p class="card-title mb-3">Recent IPCR</p>
            <div v-if="recentIPCRs.length" class="space-y-3">
              <div v-for="ipcr in recentIPCRs" :key="ipcr.id"
                class="border-l-2 border-indigo-200 pl-3">
                <p class="text-xs font-semibold text-gray-800 truncate">{{ ipcr.user }}</p>
                <p class="text-[10px] text-gray-500">{{ ipcr.rating_period }}</p>
                <div class="mt-0.5 flex items-center gap-2">
                  <span class="rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-medium text-indigo-600">
                    {{ ipcr.status }}
                  </span>
                  <span class="text-[10px] text-gray-400">{{ ipcr.updated_at }}</span>
                </div>
              </div>
            </div>
            <p v-else class="text-xs text-gray-400">No recent submissions.</p>
          </div>

          <!-- HR Summary -->
          <div class="card">
            <p class="card-title mb-3">HR Summary</p>
            <div class="space-y-2">
              <div v-for="row in [
                { label: 'Total Employees', value: totalEmployees, bold: true },
                { label: 'Faculty',         value: facultyCount },
                { label: 'Staff',           value: staffCount },
                { label: 'Active Divisions',value: activeDivisions },
                { label: 'Male',            value: employeeMaleCount },
                { label: 'Female',          value: employeeFemaleCount },
              ]" :key="row.label"
                class="flex items-center justify-between text-xs">
                <span class="text-gray-500">{{ row.label }}</span>
                <span :class="row.bold ? 'font-bold text-gray-900' : 'font-semibold text-gray-700'">
                  {{ row.value.toLocaleString() }}
                </span>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>

    <!-- Facility Date Modal -->
    <Teleport to="body">
      <div v-if="showDateModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showDateModal = false" />
        <div class="relative z-10 w-full max-w-2xl rounded-2xl bg-white p-6 shadow-2xl">
          <div class="mb-4 flex items-center justify-between">
            <h4 class="font-semibold text-gray-800">Facility Requests — {{ selectedDate }}</h4>
            <button class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600" @click="showDateModal = false">✕</button>
          </div>
          <div v-if="loadingBookings" class="py-8 text-center text-sm text-gray-400">Loading…</div>
          <div v-else>
            <p v-if="bookingsError" class="mb-3 text-sm text-red-500">{{ bookingsError }}</p>
            <p v-if="!dateBookings.length" class="text-sm text-gray-400">No facility requests for this date.</p>
            <ul v-else class="space-y-3 max-h-80 overflow-y-auto">
              <li v-for="b in dateBookings" :key="b.id" class="rounded-xl border border-gray-100 p-4">
                <div class="flex items-start justify-between gap-4">
                  <div>
                    <p class="text-sm font-semibold text-gray-800">{{ b.activity || '—' }}</p>
                    <p class="text-xs text-gray-500 mt-0.5">{{ b.requester_name || '—' }} · {{ b.requester_unit || '' }}</p>
                    <p class="text-xs text-gray-500">Venue: {{ (b.venue?.length ? b.venue.join(', ') : '—') }}</p>
                    <p class="text-xs text-gray-500">{{ b.time_start || '—' }} — {{ b.time_end || '—' }}</p>
                  </div>
                  <div class="shrink-0 text-right space-y-1">
                    <span :class="b.status?.includes('Approved') ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600'"
                      class="rounded-full px-2 py-0.5 text-xs font-medium">{{ b.status || '—' }}</span>
                    <div>
                      <span v-if="b.has_it_job" class="rounded bg-blue-50 px-2 py-0.5 text-xs text-blue-600">
                        IT: {{ b.it_job_status }}
                      </span>
                    </div>
                  </div>
                </div>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </Teleport>
  </AdminLayout>
</template>

<!-- Reusable local component via script -->
<script>
const EmptyState = {
  props: ['text'],
  template: `<div class="flex h-full min-h-[120px] items-center justify-center text-sm text-gray-300">{{ text }}</div>`,
}
export default { components: { EmptyState } }
</script>

<style>
/* Dashboard utility classes — plain CSS, no @apply needed */
.card {
  border-radius: 1rem;
  border: 1px solid #f3f4f6;
  background: #ffffff;
  padding: 1.25rem;
  box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
  transition: box-shadow 0.2s;
}
.card:hover { box-shadow: 0 4px 12px 0 rgb(0 0 0 / 0.08); }

.card-header { margin-bottom: 1rem; display: flex; align-items: flex-start; justify-content: space-between; }

.card-title { font-size: 0.875rem; font-weight: 600; color: #1f2937; letter-spacing: -0.01em; }

.card-sub { font-size: 0.75rem; color: #9ca3af; margin-top: 0.125rem; }

.section-divider {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  color: #9ca3af;
}
.section-divider::before,
.section-divider::after {
  content: '';
  flex: 1;
  height: 1px;
  background: #f3f4f6;
}
</style>

