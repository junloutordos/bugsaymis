<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppCard from '@/Components/AppCard.vue'
import { Head, router, Link } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import {
  Chart as ChartJS,
  Title, Tooltip, Legend,
  ArcElement,
  CategoryScale, LinearScale,
  PointElement, LineElement, BarElement,
  Filler,
} from 'chart.js'
import { Doughnut, Bar, Line } from 'vue-chartjs'
import {
  UserGroupIcon, ClockIcon, CalendarDaysIcon, TicketIcon,
  BriefcaseIcon, UserPlusIcon, ClipboardDocumentCheckIcon,
  ChartBarIcon, ExclamationTriangleIcon,
  AcademicCapIcon, UsersIcon,
  DocumentTextIcon, ShieldExclamationIcon,
  TrophyIcon, BanknotesIcon, ArrowRightEndOnRectangleIcon,
} from '@heroicons/vue/24/outline'

ChartJS.register(Title, Tooltip, Legend, ArcElement, CategoryScale, LinearScale, PointElement, LineElement, BarElement, Filler)

const props = defineProps({
  hr:          Object,
  recruitment: Object,
  pms:         Object,
  lnd:         Object,
  saln:        Object,
  rewards:     Object,
})

const cardColors = {
  indigo:  'bg-indigo-50 text-indigo-600 border-indigo-100',
  emerald: 'bg-emerald-50 text-emerald-600 border-emerald-100',
  amber:   'bg-amber-50 text-amber-600 border-amber-100',
  violet:  'bg-violet-50 text-violet-600 border-violet-100',
  sky:     'bg-sky-50 text-sky-600 border-sky-100',
  rose:    'bg-rose-50 text-rose-600 border-rose-100',
  slate:   'bg-slate-50 text-slate-500 border-slate-100',
}

const doughnutOptions = {
  responsive: true, maintainAspectRatio: false,
  plugins: { legend: { position: 'right', labels: { boxWidth: 12, font: { size: 11 } } } },
}
const hBarOptions = {
  responsive: true, maintainAspectRatio: false,
  indexAxis: 'y',
  plugins: { legend: { display: false } },
  scales: { x: { beginAtZero: true, ticks: { precision: 0 } }, y: { ticks: { font: { size: 11 } } } },
}
const vBarOptions = {
  responsive: true, maintainAspectRatio: false,
  plugins: { legend: { display: false } },
  scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { ticks: { font: { size: 11 } } } },
}
const lineOptions = {
  responsive: true, maintainAspectRatio: false,
  plugins: { legend: { position: 'top', labels: { boxWidth: 12, font: { size: 11 } } } },
  scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { ticks: { font: { size: 11 } } } },
}

const reload = (params, only) => {
  router.get(route('hr.dashboard'), params, { preserveState: true, preserveScroll: true, only })
}

// ── 1. HR Core ─────────────────────────────────────────────────────────────────

const hrMonth = ref(props.hr?.month ?? new Date().toISOString().slice(0, 7))
const goHrMonth = () => reload({ hr_month: hrMonth.value }, ['hr'])

const hrKpiCards = computed(() => !props.hr ? [] : [
  { label: 'Active Employees', value: props.hr.kpi.total_active_employees, sub: 'Org-wide', icon: UserGroupIcon, color: 'indigo' },
  { label: 'Attendance Rate', value: props.hr.kpi.attendance_rate != null ? props.hr.kpi.attendance_rate + '%' : '—', sub: hrMonth.value, icon: ClockIcon, color: 'emerald' },
  { label: 'Pending Leave Applications', value: props.hr.kpi.pending_leave, sub: 'Awaiting any approval stage', icon: CalendarDaysIcon, color: props.hr.kpi.pending_leave > 0 ? 'amber' : 'slate' },
  { label: 'Pending Gate Passes', value: props.hr.kpi.pending_gate_pass, sub: 'Awaiting first approval', icon: TicketIcon, color: props.hr.kpi.pending_gate_pass > 0 ? 'amber' : 'slate' },
])

const hrCategoryData = computed(() => {
  const labels = Object.keys(props.hr?.categoryBreakdown ?? {})
  return {
    labels,
    datasets: [{ data: labels.map(l => props.hr.categoryBreakdown[l]), backgroundColor: ['#6366f1', '#10b981', '#f59e0b', '#0ea5e9', '#ef4444', '#8b5cf6'], borderWidth: 1 }],
  }
})

const hrAttendanceTrendData = computed(() => ({
  labels: (props.hr?.attendanceTrend ?? []).map(r => r.month),
  datasets: [
    { label: 'Present', data: (props.hr?.attendanceTrend ?? []).map(r => r.present), borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,0.08)', fill: true, tension: 0.3, pointRadius: 3 },
    { label: 'Absent', data: (props.hr?.attendanceTrend ?? []).map(r => r.absent), borderColor: '#ef4444', backgroundColor: 'rgba(239,68,68,0.08)', fill: true, tension: 0.3, pointRadius: 3 },
    { label: 'On Leave', data: (props.hr?.attendanceTrend ?? []).map(r => r.on_leave), borderColor: '#f59e0b', backgroundColor: 'rgba(245,158,11,0.08)', fill: true, tension: 0.3, pointRadius: 3 },
  ],
}))

// ── 2. Recruitment ───────────────────────────────────────────────────────────

const recruitmentYear = ref(props.recruitment?.year ?? new Date().getFullYear())
const goRecruitmentYear = () => reload({ recruitment_year: recruitmentYear.value }, ['recruitment'])

const recruitmentKpiCards = computed(() => !props.recruitment ? [] : [
  { label: 'Open Positions', value: props.recruitment.kpi.open_positions, sub: 'Approved + Published', icon: BriefcaseIcon, color: 'indigo' },
  { label: 'Active Vacancies', value: props.recruitment.kpi.active_vacancies, sub: 'Currently open postings', icon: UserPlusIcon, color: 'sky' },
  { label: 'Applications', value: props.recruitment.kpi.applications_year, sub: recruitmentYear.value, icon: ClipboardDocumentCheckIcon, color: 'violet' },
  { label: 'Placements', value: props.recruitment.kpi.placements_year, sub: recruitmentYear.value, icon: UserGroupIcon, color: 'emerald' },
  { label: 'Pending Onboarding', value: props.recruitment.kpi.pending_onboarding, sub: 'Incomplete onboarding tasks', icon: ExclamationTriangleIcon, color: props.recruitment.kpi.pending_onboarding > 0 ? 'amber' : 'slate' },
])

const recruitmentPipelineData = computed(() => ({
  labels: (props.recruitment?.pipeline ?? []).map(r => r.month),
  datasets: [
    { label: 'Submitted', data: (props.recruitment?.pipeline ?? []).map(r => r.submitted), borderColor: '#6366f1', backgroundColor: 'rgba(99,102,241,0.08)', fill: true, tension: 0.3, pointRadius: 3 },
    { label: 'Placed', data: (props.recruitment?.pipeline ?? []).map(r => r.placed), borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,0.08)', fill: true, tension: 0.3, pointRadius: 3 },
    { label: 'Rejected', data: (props.recruitment?.pipeline ?? []).map(r => r.rejected), borderColor: '#ef4444', backgroundColor: 'rgba(239,68,68,0.08)', fill: true, tension: 0.3, pointRadius: 3 },
  ],
}))

// ── 3. Performance Management ────────────────────────────────────────────────

const pmsPeriod = ref(props.pms?.period ?? '')
const goPmsPeriod = () => reload({ pms_period: pmsPeriod.value }, ['pms'])

const pmsKpiCards = computed(() => !props.pms ? [] : [
  { label: 'Total IPCRs', value: props.pms.kpi.total_ipcrs, sub: pmsPeriod.value, icon: ChartBarIcon, color: 'indigo' },
  { label: 'Pending Director Sign-off', value: props.pms.kpi.pending_director_signoff, sub: 'Approved by PMT', icon: ClockIcon, color: props.pms.kpi.pending_director_signoff > 0 ? 'amber' : 'slate' },
  { label: 'Average Rating', value: props.pms.kpi.average_rating ?? '—', sub: 'Weighted, this period', icon: ClipboardDocumentCheckIcon, color: 'violet' },
])

const ratingColors = { Outstanding: '#10b981', 'Very Satisfactory': '#6366f1', Satisfactory: '#0ea5e9', Unsatisfactory: '#f59e0b', Poor: '#ef4444' }
const pmsRatingData = computed(() => {
  const labels = Object.keys(props.pms?.ratingDistribution ?? {})
  return { labels, datasets: [{ data: labels.map(l => props.pms.ratingDistribution[l]), backgroundColor: labels.map(l => ratingColors[l] ?? '#94a3b8'), borderWidth: 1 }] }
})

const pmsFunnelData = computed(() => {
  const labels = Object.keys(props.pms?.statusFunnel ?? {})
  return { labels, datasets: [{ data: labels.map(l => props.pms.statusFunnel[l]), backgroundColor: '#6366f1', borderRadius: 4 }] }
})

// ── 4. Learning & Development ────────────────────────────────────────────────

const lndYear = ref(props.lnd?.year ?? new Date().getFullYear())
const goLndYear = () => reload({ lnd_year: lndYear.value }, ['lnd'])

const lndKpiCards = computed(() => !props.lnd ? [] : [
  { label: 'Active Programs', value: props.lnd.kpi.active_programs, sub: 'Planned + Ongoing', icon: AcademicCapIcon, color: 'indigo' },
  { label: 'Sessions', value: props.lnd.kpi.sessions_year, sub: lndYear.value, icon: ClipboardDocumentCheckIcon, color: 'sky' },
  { label: 'Upcoming Sessions', value: props.lnd.kpi.upcoming_sessions, sub: 'Scheduled, not yet held', icon: ClockIcon, color: 'violet' },
  { label: 'Participants Trained', value: props.lnd.kpi.participants_trained, sub: lndYear.value + ' YTD', icon: UsersIcon, color: 'emerald' },
  { label: 'Pending TNA', value: props.lnd.kpi.pending_tna, sub: 'Training needs awaiting review', icon: ExclamationTriangleIcon, color: props.lnd.kpi.pending_tna > 0 ? 'amber' : 'slate' },
])

const lndSessionsData = computed(() => ({
  labels: (props.lnd?.sessionsPerMonth ?? []).map(r => r.month),
  datasets: [{ label: 'Sessions', data: (props.lnd?.sessionsPerMonth ?? []).map(r => r.count), backgroundColor: '#8b5cf6', borderRadius: 4 }],
}))

// ── 5. SALN ───────────────────────────────────────────────────────────────────

const salnYear = ref(props.saln?.year ?? new Date().getFullYear())
const goSalnYear = () => reload({ saln_year: salnYear.value }, ['saln'])

const salnKpiCards = computed(() => !props.saln ? [] : [
  { label: 'Required Filers', value: props.saln.kpi.total_required, sub: 'All non-Student/Parent employees', icon: UserGroupIcon, color: 'indigo' },
  { label: 'Filed', value: props.saln.kpi.filed, sub: salnYear.value, icon: ClipboardDocumentCheckIcon, color: 'emerald' },
  { label: 'Approved', value: props.saln.kpi.approved, sub: 'Not yet filed', icon: ClockIcon, color: 'sky' },
  { label: 'Under Review', value: props.saln.kpi.review, sub: 'Submitted / under review', icon: DocumentTextIcon, color: 'violet' },
  { label: 'Non-Filers', value: props.saln.kpi.non_filers, sub: salnYear.value, icon: ShieldExclamationIcon, color: props.saln.kpi.non_filers > 0 ? 'rose' : 'slate' },
])

const salnStatusColors = { filed: '#10b981', approved: '#0ea5e9', submitted: '#6366f1', under_review: '#8b5cf6', draft: '#94a3b8', returned: '#f59e0b' }
const salnStatusData = computed(() => {
  const labels = Object.keys(props.saln?.statusCounts ?? {})
  return { labels, datasets: [{ data: labels.map(l => props.saln.statusCounts[l]), backgroundColor: labels.map(l => salnStatusColors[l] ?? '#cbd5e1'), borderWidth: 1 }] }
})

// ── 6. Rewards & Recognition ─────────────────────────────────────────────────

const rewardsYear = ref(props.rewards?.year ?? new Date().getFullYear())
const goRewardsYear = () => reload({ rewards_year: rewardsYear.value }, ['rewards'])

const rewardsKpiCards = computed(() => !props.rewards ? [] : [
  { label: 'Nominations', value: props.rewards.kpi.total_nominations, sub: rewardsYear.value, icon: TrophyIcon, color: 'indigo' },
  { label: 'Pending', value: props.rewards.kpi.pending, sub: 'Pending / screened / evaluated', icon: ClockIcon, color: props.rewards.kpi.pending > 0 ? 'amber' : 'slate' },
  { label: 'Awarded', value: props.rewards.kpi.awarded, sub: rewardsYear.value, icon: ClipboardDocumentCheckIcon, color: 'emerald' },
  { label: 'Total Incentive Value', value: '₱' + Number(props.rewards.kpi.total_value).toLocaleString('en-PH', { minimumFractionDigits: 2 }), sub: rewardsYear.value, icon: BanknotesIcon, color: 'violet' },
])

const rewardsByTypeData = computed(() => ({
  labels: (props.rewards?.byType ?? []).map(r => r.name),
  datasets: [{ data: (props.rewards?.byType ?? []).map(r => r.total), backgroundColor: '#6366f1', borderRadius: 4 }],
}))
</script>

<template>
  <Head title="HR Dashboard" />
  <AdminLayout title="HR Dashboard">
    <div class="space-y-8">
      <AppPageHeader title="HR Dashboard" subtitle="Comprehensive overview across HR, Recruitment, Performance Management, Learning & Development, SALN, and Rewards & Recognition" />

      <!-- ── 1. HR Core ─────────────────────────────────────────────────────── -->
      <section v-if="hr" class="space-y-4">
        <div class="flex items-center justify-between">
          <h2 class="text-base font-semibold text-slate-800">HR Core</h2>
          <input v-model="hrMonth" type="month" @change="goHrMonth"
                 class="border border-slate-200 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
          <div v-for="card in hrKpiCards" :key="card.label" :class="['rounded-xl border p-4 flex flex-col gap-2', cardColors[card.color]]">
            <component :is="card.icon" class="h-5 w-5 opacity-70" />
            <div class="text-2xl font-bold leading-none">{{ card.value ?? '—' }}</div>
            <div class="text-xs font-semibold leading-tight">{{ card.label }}</div>
            <div class="text-[10px] opacity-70 leading-tight">{{ card.sub }}</div>
          </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
          <AppCard title="Employee Category Breakdown">
            <div style="height:220px;"><Doughnut :data="hrCategoryData" :options="doughnutOptions" /></div>
          </AppCard>
          <AppCard title="Attendance Trend (last 6 months)" class="lg:col-span-2">
            <div style="height:220px;"><Line :data="hrAttendanceTrendData" :options="lineOptions" /></div>
          </AppCard>
        </div>

        <AppCard v-if="hr.leaveTypeBreakdown?.length" title="Leave Type Breakdown (this year)">
          <table class="w-full text-sm">
            <thead><tr class="text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider"><th class="py-1">Leave Type</th><th class="py-1 text-right">Applications</th><th class="py-1 text-right">Days</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="row in hr.leaveTypeBreakdown" :key="row.name">
                <td class="py-1.5 text-slate-700">{{ row.name }}</td>
                <td class="py-1.5 text-right text-slate-700">{{ row.total }}</td>
                <td class="py-1.5 text-right text-slate-700">{{ row.days }}</td>
              </tr>
            </tbody>
          </table>
        </AppCard>

        <!-- Today at a Glance -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
          <!-- On Leave Today -->
          <AppCard>
            <div class="flex items-center gap-2 mb-3">
              <CalendarDaysIcon class="h-4 w-4 text-amber-500" />
              <h3 class="text-sm font-semibold text-slate-700">On Leave Today</h3>
              <span class="ml-auto text-xs font-semibold text-amber-600 bg-amber-50 rounded-full px-2 py-0.5">{{ hr.onLeaveToday?.length ?? 0 }}</span>
            </div>
            <div v-if="hr.onLeaveToday?.length" class="divide-y divide-slate-100">
              <div v-for="emp in hr.onLeaveToday" :key="emp.name" class="py-2 flex items-start justify-between gap-2">
                <div>
                  <div class="text-sm font-medium text-slate-800">{{ emp.name }}</div>
                  <div class="text-xs text-slate-500">{{ emp.leave_type }}</div>
                </div>
                <div class="text-right text-xs text-slate-500 whitespace-nowrap">
                  {{ new Date(emp.date_from).toLocaleDateString('en-PH', { month: 'short', day: 'numeric' }) }}
                  <template v-if="emp.date_from !== emp.date_to"> – {{ new Date(emp.date_to).toLocaleDateString('en-PH', { month: 'short', day: 'numeric' }) }}</template>
                </div>
              </div>
            </div>
            <p v-else class="text-sm text-slate-400">No approved leaves for today.</p>
          </AppCard>

          <!-- On Gate Pass Today -->
          <AppCard>
            <div class="flex items-center gap-2 mb-3">
              <ArrowRightEndOnRectangleIcon class="h-4 w-4 text-sky-500" />
              <h3 class="text-sm font-semibold text-slate-700">On Gate Pass Today</h3>
              <span class="ml-auto text-xs font-semibold text-sky-600 bg-sky-50 rounded-full px-2 py-0.5">{{ hr.onGatepassToday?.length ?? 0 }}</span>
            </div>
            <div v-if="hr.onGatepassToday?.length" class="divide-y divide-slate-100">
              <div v-for="emp in hr.onGatepassToday" :key="emp.name" class="py-2 flex items-start justify-between gap-2">
                <div>
                  <div class="text-sm font-medium text-slate-800">{{ emp.name }}</div>
                  <div class="text-xs text-slate-500">{{ emp.type }}<template v-if="emp.purpose"> — {{ emp.purpose }}</template></div>
                </div>
                <div class="text-right text-xs text-slate-500 whitespace-nowrap">
                  {{ emp.timeout }}<template v-if="emp.timein"> – {{ emp.timein }}</template>
                </div>
              </div>
            </div>
            <p v-else class="text-sm text-slate-400">No approved gate passes for today.</p>
          </AppCard>
        </div>
      </section>

      <!-- ── 2. Recruitment ─────────────────────────────────────────────────── -->
      <section v-if="recruitment" class="space-y-4 border-t border-slate-100 pt-6">
        <div class="flex items-center justify-between">
          <h2 class="text-base font-semibold text-slate-800">Recruitment</h2>
          <div class="flex items-center gap-3">
            <input v-model="recruitmentYear" type="number" @change="goRecruitmentYear"
                   class="w-24 border border-slate-200 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
            <Link :href="route('recruitment.reports.index')" class="text-xs text-indigo-600 hover:underline whitespace-nowrap">View Full Reports →</Link>
          </div>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
          <div v-for="card in recruitmentKpiCards" :key="card.label" :class="['rounded-xl border p-4 flex flex-col gap-2', cardColors[card.color]]">
            <component :is="card.icon" class="h-5 w-5 opacity-70" />
            <div class="text-2xl font-bold leading-none">{{ card.value ?? '—' }}</div>
            <div class="text-xs font-semibold leading-tight">{{ card.label }}</div>
            <div class="text-[10px] opacity-70 leading-tight">{{ card.sub }}</div>
          </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
          <AppCard title="Monthly Pipeline" class="lg:col-span-2">
            <div style="height:220px;"><Line :data="recruitmentPipelineData" :options="lineOptions" /></div>
          </AppCard>
          <AppCard title="Applications Stuck >14 Days">
            <table v-if="recruitment.stuckApplications?.length" class="w-full text-sm">
              <tbody class="divide-y divide-slate-100">
                <tr v-for="row in recruitment.stuckApplications" :key="row.stage">
                  <td class="py-1.5 text-slate-700 capitalize">{{ row.stage }}</td>
                  <td class="py-1.5 text-right font-semibold text-amber-600">{{ row.total }}</td>
                </tr>
              </tbody>
            </table>
            <p v-else class="text-sm text-slate-400">No applications stuck in a stage right now.</p>
          </AppCard>
        </div>
      </section>

      <!-- ── 3. Performance Management ──────────────────────────────────────── -->
      <section v-if="pms" class="space-y-4 border-t border-slate-100 pt-6">
        <div class="flex items-center justify-between">
          <h2 class="text-base font-semibold text-slate-800">Performance Management (IPCR/PMS)</h2>
          <select v-model="pmsPeriod" @change="goPmsPeriod"
                  class="border border-slate-200 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
            <option v-for="p in pms.periods" :key="p" :value="p">{{ p }}</option>
          </select>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <div v-for="card in pmsKpiCards" :key="card.label" :class="['rounded-xl border p-4 flex flex-col gap-2', cardColors[card.color]]">
            <component :is="card.icon" class="h-5 w-5 opacity-70" />
            <div class="text-2xl font-bold leading-none">{{ card.value ?? '—' }}</div>
            <div class="text-xs font-semibold leading-tight">{{ card.label }}</div>
            <div class="text-[10px] opacity-70 leading-tight">{{ card.sub }}</div>
          </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
          <AppCard title="Status Funnel">
            <div style="height:240px;"><Bar :data="pmsFunnelData" :options="hBarOptions" /></div>
          </AppCard>
          <AppCard title="Rating Distribution">
            <div style="height:240px;"><Doughnut :data="pmsRatingData" :options="doughnutOptions" /></div>
          </AppCard>
          <AppCard title="Completion Rate by Division">
            <table class="w-full text-sm">
              <tbody class="divide-y divide-slate-100">
                <tr v-for="row in pms.completionByDivision" :key="row.division">
                  <td class="py-1.5 text-slate-700">{{ row.division }}</td>
                  <td class="py-1.5 text-right text-slate-500">{{ row.submitted }}/{{ row.total }}</td>
                  <td class="py-1.5 text-right font-semibold" :class="row.rate >= 80 ? 'text-emerald-600' : row.rate >= 50 ? 'text-amber-600' : 'text-rose-600'">{{ row.rate }}%</td>
                </tr>
              </tbody>
            </table>
          </AppCard>
        </div>
      </section>

      <!-- ── 4. Learning & Development ──────────────────────────────────────── -->
      <section v-if="lnd" class="space-y-4 border-t border-slate-100 pt-6">
        <div class="flex items-center justify-between">
          <h2 class="text-base font-semibold text-slate-800">Learning &amp; Development</h2>
          <input v-model="lndYear" type="number" @change="goLndYear"
                 class="w-24 border border-slate-200 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
          <div v-for="card in lndKpiCards" :key="card.label" :class="['rounded-xl border p-4 flex flex-col gap-2', cardColors[card.color]]">
            <component :is="card.icon" class="h-5 w-5 opacity-70" />
            <div class="text-2xl font-bold leading-none">{{ card.value ?? '—' }}</div>
            <div class="text-xs font-semibold leading-tight">{{ card.label }}</div>
            <div class="text-[10px] opacity-70 leading-tight">{{ card.sub }}</div>
          </div>
        </div>

        <AppCard title="Sessions per Month">
          <div style="height:220px;"><Bar :data="lndSessionsData" :options="vBarOptions" /></div>
        </AppCard>
      </section>

      <!-- ── 5. SALN ─────────────────────────────────────────────────────────── -->
      <section v-if="saln" class="space-y-4 border-t border-slate-100 pt-6">
        <div class="flex items-center justify-between">
          <h2 class="text-base font-semibold text-slate-800">SALN</h2>
          <div class="flex items-center gap-3">
            <input v-model="salnYear" type="number" @change="goSalnYear"
                   class="w-24 border border-slate-200 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
            <Link :href="route('saln.hr.reports.annual', { year: salnYear })" class="text-xs text-indigo-600 hover:underline whitespace-nowrap">View Annual Report →</Link>
          </div>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
          <div v-for="card in salnKpiCards" :key="card.label" :class="['rounded-xl border p-4 flex flex-col gap-2', cardColors[card.color]]">
            <component :is="card.icon" class="h-5 w-5 opacity-70" />
            <div class="text-2xl font-bold leading-none">{{ card.value ?? '—' }}</div>
            <div class="text-xs font-semibold leading-tight">{{ card.label }}</div>
            <div class="text-[10px] opacity-70 leading-tight">{{ card.sub }}</div>
          </div>
        </div>

        <AppCard title="Filing Status Breakdown" class="max-w-md">
          <div style="height:220px;"><Doughnut :data="salnStatusData" :options="doughnutOptions" /></div>
        </AppCard>
      </section>

      <!-- ── 6. Rewards & Recognition ───────────────────────────────────────── -->
      <section v-if="rewards" class="space-y-4 border-t border-slate-100 pt-6">
        <div class="flex items-center justify-between">
          <h2 class="text-base font-semibold text-slate-800">Rewards &amp; Recognition</h2>
          <div class="flex items-center gap-3">
            <input v-model="rewardsYear" type="number" @change="goRewardsYear"
                   class="w-24 border border-slate-200 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
            <Link :href="route('rewards.dashboard')" class="text-xs text-indigo-600 hover:underline whitespace-nowrap">View Full Dashboard →</Link>
          </div>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
          <div v-for="card in rewardsKpiCards" :key="card.label" :class="['rounded-xl border p-4 flex flex-col gap-2', cardColors[card.color]]">
            <component :is="card.icon" class="h-5 w-5 opacity-70" />
            <div class="text-2xl font-bold leading-none">{{ card.value ?? '—' }}</div>
            <div class="text-xs font-semibold leading-tight">{{ card.label }}</div>
            <div class="text-[10px] opacity-70 leading-tight">{{ card.sub }}</div>
          </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
          <AppCard title="Nominations by Award Type">
            <div style="height:220px;"><Bar :data="rewardsByTypeData" :options="vBarOptions" /></div>
          </AppCard>
          <AppCard title="Awards by Incentive Type">
            <table v-if="rewards.awardsByIncentive?.length" class="w-full text-sm">
              <thead><tr class="text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider"><th class="py-1">Type</th><th class="py-1 text-right">Count</th><th class="py-1 text-right">Total Value</th></tr></thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="row in rewards.awardsByIncentive" :key="row.incentive_type">
                  <td class="py-1.5 text-slate-700 capitalize">{{ row.incentive_type?.replace('_', ' ') }}</td>
                  <td class="py-1.5 text-right text-slate-700">{{ row.count }}</td>
                  <td class="py-1.5 text-right text-slate-700">₱{{ Number(row.total_value ?? 0).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}</td>
                </tr>
              </tbody>
            </table>
            <p v-else class="text-sm text-slate-400">No awards recorded for this year.</p>
          </AppCard>
        </div>
      </section>

      <div v-if="!hr && !recruitment && !pms && !lnd && !saln && !rewards" class="text-center text-slate-400 text-sm py-16">
        You don't have access to any dashboard section yet. Contact HR if you believe this is an error.
      </div>
    </div>
  </AdminLayout>
</template>
