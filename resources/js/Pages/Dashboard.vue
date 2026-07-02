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
  DocumentCheckIcon,
  ClockIcon,
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
  facultyMaleCount:    { type: Number, default: 0 },
  facultyFemaleCount:  { type: Number, default: 0 },
  staffMaleCount:      { type: Number, default: 0 },
  staffFemaleCount:    { type: Number, default: 0 },
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
  ipcrBySex:    { type: Object, default: () => ({ male: 0, female: 0, unspecified: 0 }) },
  tnaBySex:     { type: Object, default: () => ({ male: 0, female: 0, unspecified: 0 }) },
  idpBySex:     { type: Object, default: () => ({ male: 0, female: 0, unspecified: 0 }) },
  rewardsBySex: { type: Object, default: () => ({ male: 0, female: 0, unspecified: 0 }) },
  employeesByDivisionWithSex: { type: Array, default: () => [] },
})

// ── Colour system ─────────────────────────────────────────────────────────────
// Semantic — always the same meaning across every chart
const sem = {
  male:      '#3B82F6', // blue-500
  female:    '#EC4899', // pink-500
  pending:   '#F59E0B', // amber-400
  completed: '#10B981', // emerald-500
}

// Brand-derived categorical palette — 6 harmonious, brand-anchored colours
// Used for multi-category charts (trends, IPCR status, IT categories, general services)
const brandPalette = [
  '#1447C0', // brand primary blue
  '#0891B2', // cyan-blue (brand family)
  '#7C3AED', // violet (contrast)
  '#059669', // emerald (positive)
  '#D97706', // amber (attention)
  '#64748B', // slate (neutral / other)
]




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
    borderColor: brandPalette[i % brandPalette.length],
    backgroundColor: brandPalette[i % brandPalette.length] + '18',
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
      backgroundColor: sem.pending + 'cc',
      borderRadius: 4,
    },
    {
      label: 'Completed',
      data: props.requestOverview.map(r => r.completed ?? 0),
      backgroundColor: sem.completed + 'cc',
      borderRadius: 4,
    },
  ],
}))

const ipcrStatusData = computed(() => ({
  labels: props.ipcrByStatus.map(r => r.status),
  datasets: [{ data: props.ipcrByStatus.map(r => r.total), backgroundColor: brandPalette, borderWidth: 0 }],
}))

const itjrCategoryData = computed(() => ({
  labels: props.itjrByCategory.map(r => r.category),
  datasets: [{ data: props.itjrByCategory.map(r => r.total), backgroundColor: brandPalette, borderWidth: 0 }],
}))

const gsModules = ['Vehicle Requests', 'Facility Requests', 'Service Requests', 'Work Requests']
const generalServicesData = computed(() => {
  const rows = props.requestOverview.filter(r => gsModules.includes(r.label))
  return {
    labels: rows.map(r => r.label),
    datasets: [{ data: rows.map(r => r.total), backgroundColor: brandPalette.slice(0, 4), borderWidth: 0 }],
  }
})

const divisionSexData = computed(() => ({
  labels: props.employeesByDivisionWithSex.map(d => d.name),
  datasets: [
    { label: 'Male',   data: props.employeesByDivisionWithSex.map(d => d.male),   backgroundColor: sem.male   + 'cc', borderRadius: 4 },
    { label: 'Female', data: props.employeesByDivisionWithSex.map(d => d.female), backgroundColor: sem.female + 'cc', borderRadius: 4 },
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
  datasets: [{ data: [props.employeeMaleCount, props.employeeFemaleCount], backgroundColor: [sem.male, sem.female], borderWidth: 0 }],
}))

const studentGenderData = computed(() => ({
  labels: ['Male', 'Female'],
  datasets: [{ data: [props.maleCount, props.femaleCount], backgroundColor: [sem.male, sem.female], borderWidth: 0 }],
}))

const libraryAttendanceData = computed(() => ({
  labels: ['G7', 'G8', 'G9', 'G10', 'G11', 'G12'],
  datasets: [
    { label: 'Male',   data: props.libraryAttendanceMaleByGrade,   backgroundColor: sem.male   + 'cc', borderRadius: 4 },
    { label: 'Female', data: props.libraryAttendanceFemaleByGrade, backgroundColor: sem.female + 'cc', borderRadius: 4 },
  ],
}))

// ── KPI Cards ─────────────────────────────────────────────────────────────────
const kpiCards = computed(() => [
  { label: 'Total Employees', value: props.totalEmployees, male: props.employeeMaleCount, female: props.employeeFemaleCount, sub: `${props.activeDivisions} divisions`, icon: UsersIcon,          color: 'bg-blue-500',    text: 'text-blue-600',   bg: 'bg-blue-50'   },
  { label: 'Faculty',         value: props.facultyCount,   male: props.facultyMaleCount,  female: props.facultyFemaleCount,  sub: 'Teaching staff', icon: AcademicCapIcon, color: 'bg-indigo-500',  text: 'text-indigo-600', bg: 'bg-indigo-50' },
  { label: 'Staff',           value: props.staffCount,     male: props.staffMaleCount,    female: props.staffFemaleCount,    sub: 'Non-teaching',   icon: UsersIcon,       color: 'bg-violet-500',  text: 'text-violet-600', bg: 'bg-violet-50' },
  { label: 'Students',        value: props.maleCount + props.femaleCount, male: props.maleCount, female: props.femaleCount, sub: `${props.scholarsCount} scholars`, icon: AcademicCapIcon, color: 'bg-emerald-500', text: 'text-emerald-600', bg: 'bg-emerald-50' },
  { label: 'IPCR For Review', value: props.ipcrForReview,  male: props.ipcrBySex.male, female: props.ipcrBySex.female, sub: 'Awaiting review', icon: DocumentCheckIcon,   color: 'bg-rose-500',    text: 'text-rose-600',   bg: 'bg-rose-50'   },
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
    <div class="dash-root">

      <!-- ══════════════════════════════════════════════════════════
           HERO BANNER
      ══════════════════════════════════════════════════════════ -->
      <div class="hero-banner">
        <div class="hero-inner">
          <div class="hero-left">
            <div class="hero-avatar">
              {{ (authUser?.name ?? '?').split(' ').filter(Boolean).slice(0, 2).map(w => w[0].toUpperCase()).join('') }}
            </div>
            <div>
              <h1 class="hero-greeting">
                {{ greeting }}, {{ authUser?.name?.split(' ')[0] ?? 'Admin' }}
              </h1>
              <p class="hero-date">{{ today }}</p>
            </div>
          </div>
          <div class="hero-right">
            <div class="hero-pending-badge">
              <ClockIcon class="h-3.5 w-3.5 shrink-0" />
              <span class="font-bold">{{ totalPendingRequests }}</span>
              <span class="opacity-80">pending items</span>
            </div>
            <p class="hero-pending-sub">across all modules</p>
          </div>
        </div>
      </div>

      <!-- ══════════════════════════════════════════════════════════
           GAD BANNER
      ══════════════════════════════════════════════════════════ -->
      <div class="gad-banner">
        <span class="gad-icon">⚧</span>
        <p class="gad-text">
          <span class="gad-bold">Gender and Development Data Ready</span>
          — Sex-disaggregated data in compliance with the Magna Carta of Women and DOST/PSHSS GAD requirements.
        </p>
        <div class="gad-legend">
          <span class="gad-dot gad-dot-m"></span><span class="gad-legend-label">Male</span>
          <span class="gad-dot gad-dot-f ml-3"></span><span class="gad-legend-label">Female</span>
        </div>
      </div>

      <!-- ══════════════════════════════════════════════════════════
           KPI STRIP
      ══════════════════════════════════════════════════════════ -->
      <div class="kpi-grid">
        <div
          v-for="card in kpiCards"
          :key="card.label"
          class="kpi-card"
          style="border-left-color: #2563eb"
        >
          <!-- Floating icon -->
          <component :is="card.icon" :class="[card.text, 'kpi-icon-float']" />

          <!-- Value -->
          <p class="kpi-value">{{ card.value.toLocaleString() }}</p>
          <p class="kpi-label">{{ card.label }}</p>

          <!-- Sex bar -->
          <template v-if="card.male !== undefined && card.female !== undefined">
            <div class="kpi-sex-bar mt-2">
              <div
                class="kpi-sex-male"
                :style="{ width: (card.male + card.female) > 0 ? `${Math.round(card.male / (card.male + card.female) * 100)}%` : '50%' }"
              ></div>
              <div class="kpi-sex-female flex-1"></div>
            </div>
            <div class="kpi-sex-labels">
              <span class="kpi-sex-m">{{ card.male.toLocaleString() }} M</span>
              <span class="kpi-sex-f">F {{ card.female.toLocaleString() }}</span>
            </div>
          </template>
          <p v-else class="kpi-sub">{{ card.sub }}</p>
        </div>
      </div>

      <!-- ══════════════════════════════════════════════════════════
           MAIN 3-COL GRID
      ══════════════════════════════════════════════════════════ -->
      <div class="main-grid">

        <!-- LEFT — 3 columns wide -->
        <div class="main-left">

          <!-- Monthly Trends -->
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
          <div class="grid grid-cols-1 gap-5 md:grid-cols-3">
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
          <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
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
          <div class="section-head">HR Analytics</div>
          <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
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
              <div class="min-h-[180px]">
                <Doughnut
                  v-if="employeeMaleCount + employeeFemaleCount > 0"
                  :data="employeeGenderData"
                  :options="doughnutOptions"
                />
                <EmptyState v-else text="No gender data" />
              </div>
              <div class="sex-pills mt-4">
                <div class="sex-pill sex-pill-m">
                  <p class="sex-pill-val">{{ employeeMaleCount.toLocaleString() }}</p>
                  <p class="sex-pill-lbl">Male · {{ pct(employeeMaleCount, totalEmployees) }}%</p>
                </div>
                <div class="sex-pill sex-pill-f">
                  <p class="sex-pill-val">{{ employeeFemaleCount.toLocaleString() }}</p>
                  <p class="sex-pill-lbl">Female · {{ pct(employeeFemaleCount, totalEmployees) }}%</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Student Analytics -->
          <div class="section-head">Student Analytics</div>
          <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
            <div class="card">
              <div class="card-header">
                <div>
                  <p class="card-title">Student Sex Breakdown</p>
                  <p class="card-sub">{{ (maleCount + femaleCount).toLocaleString() }} enrolled</p>
                </div>
              </div>
              <div class="min-h-[180px]">
                <Doughnut v-if="maleCount + femaleCount > 0" :data="studentGenderData" :options="doughnutOptions" />
                <EmptyState v-else text="No student data" />
              </div>
              <div class="sex-pills mt-4">
                <div class="sex-pill sex-pill-m">
                  <p class="sex-pill-val">{{ maleCount.toLocaleString() }}</p>
                  <p class="sex-pill-lbl">Male · {{ pct(maleCount, maleCount + femaleCount) }}%</p>
                </div>
                <div class="sex-pill sex-pill-f">
                  <p class="sex-pill-val">{{ femaleCount.toLocaleString() }}</p>
                  <p class="sex-pill-lbl">Female · {{ pct(femaleCount, maleCount + femaleCount) }}%</p>
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

          <!-- GAD Summary -->
          <div class="section-head">GAD Summary — Sex-Disaggregated Data</div>
          <div class="card overflow-hidden p-0">
            <div class="flex items-center justify-between px-5 py-3.5 border-b border-slate-100">
              <div>
                <p class="card-title">Institutional GAD Summary</p>
                <p class="card-sub">RA 9710 — Magna Carta of Women compliance</p>
              </div>
              <span class="gad-chip">Sex-Disaggregated</span>
            </div>
            <div class="overflow-x-auto">
              <table class="w-full min-w-[680px] text-xs">
                <thead>
                  <tr class="border-b border-slate-100 bg-slate-50/70">
                    <th class="px-4 py-2.5 text-left font-semibold text-slate-500">Indicator</th>
                    <th class="px-3 py-2.5 text-right font-semibold text-slate-500">Total</th>
                    <th class="px-3 py-2.5 text-right font-semibold text-blue-500">Male</th>
                    <th class="px-3 py-2.5 text-right font-semibold text-blue-400">% M</th>
                    <th class="px-3 py-2.5 text-right font-semibold text-pink-500">Female</th>
                    <th class="px-3 py-2.5 text-right font-semibold text-pink-400">% F</th>
                    <th class="px-4 py-2.5 text-left font-semibold text-slate-400">Distribution</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="row in gadSummaryRows" :key="row.label"
                    class="border-b border-slate-50 hover:bg-slate-50/60 transition-colors">
                    <td class="px-4 py-2.5 font-medium text-slate-700">{{ row.label }}</td>
                    <td class="px-3 py-2.5 text-right font-bold text-slate-800">{{ row.total.toLocaleString() }}</td>
                    <td class="px-3 py-2.5 text-right text-blue-600">{{ row.male.toLocaleString() }}</td>
                    <td class="px-3 py-2.5 text-right text-blue-400 font-medium">{{ row.pctM }}%</td>
                    <td class="px-3 py-2.5 text-right text-pink-600">{{ row.female.toLocaleString() }}</td>
                    <td class="px-3 py-2.5 text-right text-pink-400 font-medium">{{ row.pctF }}%</td>
                    <td class="px-4 py-2.5">
                      <div v-if="row.total > 0" class="flex h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full bg-blue-400 transition-all" :style="{ width: row.pctM + '%' }"></div>
                        <div class="h-full bg-pink-400 transition-all" :style="{ width: row.pctF + '%' }"></div>
                      </div>
                      <span v-else class="text-slate-200">—</span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

        </div><!-- /main-left -->

        <!-- RIGHT SIDEBAR -->
        <div class="main-right">

          <!-- Facility Calendar -->
          <div class="card overflow-hidden">
            <p class="card-title mb-3">Facility Calendar</p>
            <div class="rounded-xl overflow-hidden border border-slate-100 text-xs" ref="calendarContainer">
              <FullCalendar :options="calendarOptions" />
            </div>
          </div>

          <!-- Pending Overview (merged Module Status + All Pending) -->
          <div class="card">
            <div class="flex items-center justify-between mb-3">
              <p class="card-title">Pending Overview</p>
              <span class="pending-total-badge">{{ totalPendingRequests }}</span>
            </div>
            <div class="space-y-1">
              <div
                v-for="item in requestOverview"
                :key="item.label"
                class="pending-row"
              >
                <span class="pending-row-label">{{ item.label }}</span>
                <div class="flex items-center gap-2 shrink-0">
                  <span v-if="item.pending > 0" class="pending-count">{{ item.pending }}</span>
                  <span v-else class="text-xs text-slate-300">—</span>
                  <span class="pending-total-small">/ {{ item.total }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Recent IPCR -->
          <div class="card">
            <p class="card-title mb-3">Recent IPCR</p>
            <div v-if="recentIPCRs.length" class="space-y-3">
              <div v-for="ipcr in recentIPCRs" :key="ipcr.id" class="ipcr-row">
                <!-- Avatar initials -->
                <div class="ipcr-avatar">
                  {{ (ipcr.user ?? '?').split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase() }}
                </div>
                <div class="flex-1 min-w-0">
                  <p class="ipcr-name">{{ ipcr.user }}</p>
                  <p class="ipcr-period">{{ ipcr.rating_period }}</p>
                  <div class="flex items-center gap-2 mt-0.5">
                    <span class="ipcr-status-chip">{{ ipcr.status }}</span>
                    <span class="ipcr-date">{{ ipcr.updated_at }}</span>
                  </div>
                </div>
              </div>
            </div>
            <p v-else class="text-xs text-slate-400">No recent submissions.</p>
          </div>

          <!-- HR Summary (compact stat grid) -->
          <div class="card">
            <p class="card-title mb-3">HR Summary</p>
            <div class="hr-stat-grid">
              <div class="hr-stat">
                <p class="hr-stat-val">{{ totalEmployees.toLocaleString() }}</p>
                <p class="hr-stat-lbl">Total Employees</p>
              </div>
              <div class="hr-stat">
                <p class="hr-stat-val">{{ facultyCount.toLocaleString() }}</p>
                <p class="hr-stat-lbl">Faculty</p>
              </div>
              <div class="hr-stat">
                <p class="hr-stat-val">{{ staffCount.toLocaleString() }}</p>
                <p class="hr-stat-lbl">Staff</p>
              </div>
              <div class="hr-stat">
                <p class="hr-stat-val">{{ activeDivisions }}</p>
                <p class="hr-stat-lbl">Divisions</p>
              </div>
              <div class="hr-stat hr-stat-male">
                <p class="hr-stat-val text-blue-700">{{ employeeMaleCount.toLocaleString() }}</p>
                <p class="hr-stat-lbl">Male</p>
              </div>
              <div class="hr-stat hr-stat-female">
                <p class="hr-stat-val text-pink-700">{{ employeeFemaleCount.toLocaleString() }}</p>
                <p class="hr-stat-lbl">Female</p>
              </div>
            </div>
          </div>

        </div><!-- /main-right -->
      </div><!-- /main-grid -->
    </div><!-- /dash-root -->

    <!-- Facility Date Modal -->
    <Teleport to="body">
      <div v-if="showDateModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showDateModal = false" />
        <div class="relative z-10 w-full max-w-2xl rounded-2xl bg-white p-6 shadow-2xl">
          <div class="mb-4 flex items-center justify-between">
            <h4 class="font-semibold text-slate-800">Facility Requests — {{ selectedDate }}</h4>
            <button class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600" @click="showDateModal = false"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4 shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg></button>
          </div>
          <div v-if="loadingBookings" class="py-8 text-center text-sm text-slate-400">Loading…</div>
          <div v-else>
            <p v-if="bookingsError" class="mb-3 text-sm text-red-500">{{ bookingsError }}</p>
            <p v-if="!dateBookings.length" class="text-sm text-slate-400">No facility requests for this date.</p>
            <ul v-else class="space-y-3 max-h-80 overflow-y-auto">
              <li v-for="b in dateBookings" :key="b.id" class="rounded-xl border border-slate-100 p-4">
                <div class="flex items-start justify-between gap-4">
                  <div>
                    <p class="text-sm font-semibold text-slate-800">{{ b.activity || '—' }}</p>
                    <p class="text-xs text-slate-500 mt-0.5">{{ b.requester_name || '—' }} · {{ b.requester_unit || '' }}</p>
                    <p class="text-xs text-slate-500">Venue: {{ (b.venue?.length ? b.venue.join(', ') : '—') }}</p>
                    <p class="text-xs text-slate-500">{{ b.time_start || '—' }} — {{ b.time_end || '—' }}</p>
                  </div>
                  <div class="shrink-0 text-right space-y-1">
                    <span :class="b.status?.includes('Approved') ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'"
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

<!-- Reusable local component -->
<script>
const EmptyState = {
  props: ['text'],
  template: `<div class="flex h-full min-h-[120px] items-center justify-center text-sm text-slate-300 gap-2"><span>—</span><span>{{ text }}</span></div>`,
}
export default { components: { EmptyState } }
</script>

<style>
/* ════════════════════════════════════════════════════════════
   ROOT
════════════════════════════════════════════════════════════ */
.dash-root {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}
@media (min-width: 640px) {
  .dash-root { gap: 1.25rem; }
}

/* ════════════════════════════════════════════════════════════
   HERO BANNER
════════════════════════════════════════════════════════════ */
.hero-banner {
  position: relative;
  overflow: hidden;
  border-radius: .875rem;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  padding: 1rem;
  box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
}

.hero-inner {
  position: relative;
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 1rem;
}

.hero-left {
  display: flex;
  align-items: center;
  gap: .875rem;
  min-width: 0;
}

.hero-avatar {
  height: 3rem;
  width: 3rem;
  border-radius: 99px;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  font-size: .95rem;
  font-weight: 800;
  color: #1d4ed8;
  letter-spacing: .02em;
}

.hero-greeting {
  font-size: 1.05rem;
  font-weight: 700;
  color: #0f172a;
  letter-spacing: 0;
  line-height: 1.2;
}

.hero-date {
  font-size: .75rem;
  color: #64748b;
  margin-top: .2rem;
}

.hero-right {
  width: 100%;
  text-align: left;
}

.hero-pending-badge {
  display: inline-flex;
  align-items: center;
  gap: .4rem;
  background: #fffbeb;
  border: 1px solid #fde68a;
  border-radius: 99px;
  padding: .35rem .85rem;
  font-size: .8rem;
  color: #92400e;
}

.hero-pending-sub {
  font-size: .7rem;
  color: #94a3b8;
  margin-top: .3rem;
}

@media (min-width: 640px) {
  .hero-banner { padding: 1.25rem 1.5rem; }
  .hero-avatar {
    height: 3.25rem;
    width: 3.25rem;
    font-size: 1.05rem;
  }
  .hero-greeting { font-size: 1.25rem; }
  .hero-right {
    width: auto;
    text-align: right;
  }
}

/* ════════════════════════════════════════════════════════════
   GAD BANNER
════════════════════════════════════════════════════════════ */
.gad-banner {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: .5rem .75rem;
  border-radius: .875rem;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-left: 3px solid #2563eb;
  padding: .75rem 1rem;
  box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
}

.gad-icon { font-size: 1.1rem; color: #7c3aed; flex-shrink: 0; }

.gad-text { font-size: .75rem; color: #475569; flex: 1 1 16rem; }

.gad-bold { font-weight: 700; color: #4c1d95; }

.gad-legend {
  display: flex;
  align-items: center;
  gap: .35rem;
  flex: 1 1 100%;
  font-size: .7rem;
  font-weight: 600;
}

.gad-dot { display: inline-block; height: .55rem; width: .55rem; border-radius: 99px; flex-shrink: 0; }
.gad-dot-m { background: #3b82f6; }
.gad-dot-f { background: #ec4899; }
.gad-legend-label { color: #64748b; }
@media (min-width: 640px) {
  .gad-banner { flex-wrap: nowrap; }
  .gad-legend { flex: 0 0 auto; }
}

/* ════════════════════════════════════════════════════════════
   KPI GRID
════════════════════════════════════════════════════════════ */
.kpi-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(145px, 1fr));
  gap: .75rem;
}
@media (min-width: 640px)  { .kpi-grid { gap: .875rem; } }
@media (min-width: 1280px) { .kpi-grid { grid-template-columns: repeat(5, 1fr); } }

.kpi-card {
  position: relative;
  background: #ffffff;
  border-radius: .875rem;
  border: 1px solid #e2e8f0;
  border-left-width: 4px;
  padding: 1rem .875rem .875rem;
  box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
  transition: transform .18s, box-shadow .18s;
  overflow: hidden;
}
@media (hover: hover) {
  .kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(15, 23, 42, .08), 0 1px 3px rgba(15, 23, 42, .05);
  }
}
@media (min-width: 640px) {
  .kpi-card { padding: 1.25rem 1.125rem 1rem; }
}

.kpi-icon-float {
  position: absolute;
  top: 1rem;
  right: 1rem;
  width: 1.35rem;
  height: 1.35rem;
  opacity: .4;
}

.kpi-value {
  font-size: 1.65rem;
  font-weight: 900;
  color: #0f172a;
  letter-spacing: 0;
  line-height: 1;
  margin-top: .375rem;
}
@media (min-width: 640px) {
  .kpi-value { font-size: 2rem; }
}

.kpi-label {
  font-size: .75rem;
  font-weight: 600;
  color: #475569;
  margin-top: .375rem;
  line-height: 1.3;
}

.kpi-sub {
  font-size: .7rem;
  color: #94a3b8;
  margin-top: .3rem;
}

.kpi-sex-bar {
  display: flex;
  height: .25rem;
  border-radius: 99px;
  overflow: hidden;
  background: #f1f5f9;
}
.kpi-sex-male  { background: #60a5fa; }
.kpi-sex-female{ background: #f472b6; }

.kpi-sex-labels {
  display: flex;
  justify-content: space-between;
  margin-top: .25rem;
}
.kpi-sex-m { font-size: .6rem; font-weight: 600; color: #3b82f6; }
.kpi-sex-f { font-size: .6rem; font-weight: 600; color: #ec4899; }

/* ════════════════════════════════════════════════════════════
   MAIN LAYOUT
════════════════════════════════════════════════════════════ */
.main-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 1rem;
}
@media (min-width: 1024px) {
  .main-grid {
    grid-template-columns: minmax(0, 1fr) 280px;
    gap: 1.25rem;
  }
}

.main-left  { display: flex; flex-direction: column; gap: 1rem; min-width: 0; }
.main-right { display: flex; flex-direction: column; gap: 1rem; }
@media (min-width: 640px) {
  .main-left { gap: 1.25rem; }
}

/* ════════════════════════════════════════════════════════════
   CARDS
════════════════════════════════════════════════════════════ */
.card {
  background: #ffffff;
  border-radius: .875rem;
  border: 1px solid #e2e8f0;
  padding: .875rem;
  box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
  transition: box-shadow .2s;
}
@media (min-width: 640px) {
  .card { padding: 1.125rem; }
}
@media (hover: hover) {
  .card:hover {
    box-shadow: 0 8px 22px rgba(15, 23, 42, .07), 0 1px 3px rgba(15, 23, 42, .04);
  }
}

.card-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: .875rem;
}

.card-title {
  font-size: .8125rem;
  font-weight: 700;
  color: #1e293b;
  letter-spacing: 0;
}

.card-sub {
  font-size: .7rem;
  color: #94a3b8;
  margin-top: .125rem;
}

/* ════════════════════════════════════════════════════════════
   SECTION HEADS
════════════════════════════════════════════════════════════ */
.section-head {
  display: flex;
  align-items: center;
  gap: .625rem;
  font-size: .7rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: .08em;
  color: #475569;
  padding-left: .625rem;
  border-left: 3px solid #2563eb;
}

/* ════════════════════════════════════════════════════════════
   SEX PILLS (employee / student breakdown)
════════════════════════════════════════════════════════════ */
.sex-pills { display: grid; grid-template-columns: 1fr 1fr; gap: .5rem; }
.sex-pill {
  border-radius: .625rem;
  padding: .5rem .625rem;
  text-align: center;
}
.sex-pill-m { background: #eff6ff; }
.sex-pill-f { background: #fdf2f8; }
.sex-pill-val { font-size: 1rem; font-weight: 700; color: inherit; }
.sex-pill-m .sex-pill-val { color: #1d4ed8; }
.sex-pill-f .sex-pill-val { color: #be185d; }
.sex-pill-lbl { font-size: .65rem; margin-top: .1rem; }
.sex-pill-m .sex-pill-lbl { color: #60a5fa; }
.sex-pill-f .sex-pill-lbl { color: #f472b6; }

/* ════════════════════════════════════════════════════════════
   GAD CHIP
════════════════════════════════════════════════════════════ */
.gad-chip {
  border-radius: 99px;
  background: #fdf2f8;
  padding: .2rem .65rem;
  font-size: .65rem;
  font-weight: 700;
  color: #be185d;
  flex-shrink: 0;
}

/* ════════════════════════════════════════════════════════════
   SIDEBAR — PENDING OVERVIEW
════════════════════════════════════════════════════════════ */
.pending-total-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: #eef2ff;
  color: #3730a3;
  font-size: .7rem;
  font-weight: 800;
  padding: .1rem .55rem;
  border-radius: 99px;
  min-width: 1.75rem;
}

.pending-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: .35rem .5rem;
  border-radius: .5rem;
  transition: background .12s;
}
.pending-row:hover { background: #f8fafc; }
.pending-row-label { font-size: .7rem; color: #475569; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-right: .5rem; }
.pending-count {
  background: #fef3c7;
  color: #92400e;
  font-size: .65rem;
  font-weight: 700;
  padding: .1rem .4rem;
  border-radius: 99px;
}
.pending-total-small { font-size: .6rem; color: #cbd5e1; }

/* ════════════════════════════════════════════════════════════
   SIDEBAR — RECENT IPCR
════════════════════════════════════════════════════════════ */
.ipcr-row { display: flex; align-items: flex-start; gap: .625rem; }

.ipcr-avatar {
  height: 1.75rem;
  width: 1.75rem;
  border-radius: 99px;
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: .55rem;
  font-weight: 800;
  color: #1d4ed8;
  flex-shrink: 0;
  letter-spacing: .02em;
}

.ipcr-name   { font-size: .75rem; font-weight: 600; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ipcr-period { font-size: .65rem; color: #94a3b8; }
.ipcr-status-chip {
  background: #eef2ff;
  color: #4338ca;
  font-size: .6rem;
  font-weight: 600;
  padding: .1rem .45rem;
  border-radius: 99px;
}
.ipcr-date { font-size: .6rem; color: #cbd5e1; }

/* ════════════════════════════════════════════════════════════
   SIDEBAR — HR SUMMARY STAT GRID
════════════════════════════════════════════════════════════ */
.hr-stat-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: .5rem;
}
@media (min-width: 640px) {
  .hr-stat-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}

.hr-stat {
  background: #f8fafc;
  border-radius: .5rem;
  padding: .5rem .5rem .4rem;
  text-align: center;
}
.hr-stat-male   { background: #eff6ff; }
.hr-stat-female { background: #fdf2f8; }

.hr-stat-val {
  font-size: 1.05rem;
  font-weight: 800;
  color: #0f172a;
  letter-spacing: -.03em;
}
.hr-stat-lbl {
  font-size: .6rem;
  color: #94a3b8;
  margin-top: .1rem;
}

@media (max-width: 640px) {
  .dash-root .card-header {
    gap: .75rem;
    margin-bottom: .75rem;
  }

  .dash-root .min-h-\[260px\] { min-height: 220px; }
  .dash-root .min-h-\[240px\] { min-height: 215px; }
  .dash-root .min-h-\[220px\] { min-height: 200px; }
  .dash-root .min-h-\[200px\] { min-height: 190px; }
  .dash-root .min-h-\[180px\] { min-height: 175px; }

  .dash-root .fc .fc-toolbar {
    gap: .5rem;
    padding: .6rem .7rem;
  }

  .dash-root .fc .fc-toolbar-title {
    font-size: .85rem;
  }

  .dash-root .fc .fc-button {
    font-size: 1rem;
    padding: .15rem .25rem !important;
  }

  .dash-root .fc .fc-col-header-cell {
    padding: .35rem 0;
    font-size: .68rem;
  }

  .dash-root .fc .fc-daygrid-day {
    padding: .1rem;
  }

  .dash-root .fc .fc-daygrid-day-number {
    font-size: .72rem;
    padding: .15rem;
  }

  .dash-root .fc .fc-daygrid-day-frame {
    min-height: 2.25rem;
  }
}
</style>
