<script setup>
import { computed, ref } from "vue"
import { Head, Link, router } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import AppCard from "@/Components/AppCard.vue"
import AppBadge from "@/Components/AppBadge.vue"
import AppButton from "@/Components/AppButton.vue"
import AppSelect from "@/Components/AppSelect.vue"
import EmptyState from "@/Components/EmptyState.vue"
import { useSubmit } from "@/Composables/useSubmit"
import {
  UsersIcon, CheckCircleIcon, InboxIcon, ChartBarIcon, StarIcon, SignalIcon,
  ArrowPathIcon, PrinterIcon, ExclamationTriangleIcon, ShieldCheckIcon,
} from "@heroicons/vue/24/outline"
import {
  Chart as ChartJS, Title, Tooltip, Legend, ArcElement,
  CategoryScale, LinearScale, PointElement, LineElement, BarElement, Filler,
} from "chart.js"
import { Doughnut, Bar, Line } from "vue-chartjs"

ChartJS.register(Title, Tooltip, Legend, ArcElement, CategoryScale, LinearScale, PointElement, LineElement, BarElement, Filler)

const props = defineProps({
  workforce: Object,
  performance: Object,
  requests: Object,
  satisfaction: Object,
  academics: Object,
  recruitment: Object,
  finance: Object,
  operations: Object,
  attention: { type: Array, default: () => [] },
  scorecard: { type: Array, default: () => null },
  generatedAt: String,
  lens: Object,
  divisions: { type: Array, default: () => [] },
})

const { isSubmitting, submit } = useSubmit()

// ── Shared chart config (validated palette; fixed assignment order) ────────
const PALETTE = ["#6366f1", "#10b981", "#f59e0b", "#0ea5e9", "#ef4444", "#8b5cf6"]
const doughnutOptions = { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: "bottom", labels: { boxWidth: 10, font: { size: 11 } } } } }
const hBarOptions = { indexAxis: "y", responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { precision: 0 } } } }
const vBarOptions = { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
const lineOptions = { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }, elements: { line: { tension: 0.3 } } }

const objToChart = (obj, color = 0) => ({
  labels: Object.keys(obj ?? {}),
  datasets: [{ data: Object.values(obj ?? {}), backgroundColor: PALETTE.slice(color).concat(PALETTE).slice(0, Math.max(1, Object.keys(obj ?? {}).length)) }],
})

// ── Lens / header ───────────────────────────────────────────────────────────
const lensLabel = computed(() => props.lens?.division ? props.lens.division.division_name : "Campus-wide")
const filterDivision = ref(props.lens?.divisionId ? String(props.lens.divisionId) : "")
const applyDivision = (v) => {
  filterDivision.value = v
  router.get(route("executive.dashboard"), v ? { division_id: v } : {}, { preserveScroll: true })
}
const printPage = () => window.print()
const refresh = () => submit.post(route("executive.dashboard.refresh"),
  props.lens?.campus && filterDivision.value ? { division_id: filterDivision.value } : {},
  { onSuccess: () => router.reload() })

const generatedLabel = computed(() => props.generatedAt
  ? new Date(props.generatedAt).toLocaleString("en-PH", { month: "long", day: "numeric", year: "numeric", hour: "numeric", minute: "2-digit" })
  : "—")

// ── KPI tiles ───────────────────────────────────────────────────────────────
const ipcrCompliance = computed(() => {
  const rows = props.performance?.complianceByDivision ?? []
  if (props.lens?.division) {
    const own = rows.find(r => r.label === props.lens.division.division_name)
    return own?.rate ?? null
  }
  const emp = rows.reduce((s, r) => s + r.employees, 0)
  const sub = rows.reduce((s, r) => s + r.submitted, 0)
  return emp ? Math.round(sub / emp * 100) : null
})

const kpis = computed(() => [
  { label: "Employees", value: props.workforce?.headcount ?? "—", icon: UsersIcon, color: "text-indigo-600 bg-indigo-50" },
  { label: "Present Today", value: props.workforce?.dtrToday?.present ?? 0, icon: CheckCircleIcon, color: "text-emerald-600 bg-emerald-50" },
  { label: "Open Requests", value: props.requests?.totalOpen ?? 0, icon: InboxIcon, color: "text-sky-600 bg-sky-50" },
  { label: "IPCR Compliance", value: ipcrCompliance.value !== null ? `${ipcrCompliance.value}%` : "—", icon: ChartBarIcon, color: "text-violet-600 bg-violet-50" },
  { label: "Client Satisfaction", value: props.satisfaction?.overall ?? "—", sub: props.satisfaction?.adjectival, icon: StarIcon, color: "text-amber-600 bg-amber-50" },
  { label: "Active Users Now", value: props.operations?.activeUsersNow ?? 0, icon: SignalIcon, color: "text-emerald-600 bg-emerald-50" },
])

// ── Section chart data ──────────────────────────────────────────────────────
const wfDivisionChart = computed(() => ({
  labels: (props.workforce?.byDivision ?? []).map(d => d.label),
  datasets: [{ data: (props.workforce?.byDivision ?? []).map(d => d.total), backgroundColor: "#6366f1" }],
}))
const wfCategoryChart = computed(() => objToChart(Object.fromEntries((props.workforce?.byCategory ?? []).map(c => [c.label, c.total]))))
const leaveTrendChart = computed(() => ({
  labels: (props.workforce?.leaveTrend ?? []).map(m => m.label),
  datasets: [{ data: (props.workforce?.leaveTrend ?? []).map(m => m.total), borderColor: "#6366f1", backgroundColor: "rgba(99,102,241,.08)", fill: true }],
}))

const ipcrFunnelChart = computed(() => objToChart(props.performance?.funnel ?? {}))
const complianceChart = computed(() => ({
  labels: (props.performance?.complianceByDivision ?? []).map(r => r.label),
  datasets: [{ data: (props.performance?.complianceByDivision ?? []).map(r => r.rate), backgroundColor: "#8b5cf6" }],
}))

const requestBarChart = computed(() => ({
  labels: (props.requests?.modules ?? []).map(m => m.label),
  datasets: [
    { label: "Open", data: (props.requests?.modules ?? []).map(m => m.open), backgroundColor: "#f59e0b" },
    { label: "Completed", data: (props.requests?.modules ?? []).map(m => m.completed), backgroundColor: "#10b981" },
  ],
}))
const requestBarOptions = { ...vBarOptions, plugins: { legend: { position: "bottom", labels: { boxWidth: 10, font: { size: 11 } } } } }
const requestTrendChart = computed(() => ({
  labels: props.requests?.trendLabels ?? [],
  datasets: [{ data: props.requests?.trendTotals ?? [], borderColor: "#0ea5e9", backgroundColor: "rgba(14,165,233,.08)", fill: true }],
}))

const SQD_LABELS = {
  sqd0: "Overall satisfaction", sqd1: "Time spent", sqd2: "Requirements", sqd3: "Steps easy",
  sqd4: "Info accessible", sqd5: "Reasonable fees", sqd6: "Fair treatment", sqd7: "Courteous staff", sqd8: "Got what I needed",
}
const sqdChart = computed(() => ({
  labels: Object.keys(props.satisfaction?.dimensions ?? {}).map(k => SQD_LABELS[k] ?? k),
  datasets: [{ data: Object.values(props.satisfaction?.dimensions ?? {}), backgroundColor: "#f59e0b" }],
}))
const sqdOptions = { ...hBarOptions, scales: { x: { min: 0, max: 5 } } }

const enrollmentChart = computed(() => ({
  labels: (props.academics?.enrollment ?? []).map(e => `Grade ${e.label}`),
  datasets: [{ data: (props.academics?.enrollment ?? []).map(e => e.total), backgroundColor: "#0ea5e9" }],
}))
const loadChart = computed(() => objToChart(props.academics?.facultyLoad ?? {}, 1))
const pipelineChart = computed(() => objToChart(props.recruitment?.pipeline ?? {}))

const netTrendChart = computed(() => ({
  labels: (props.finance?.netTrend ?? []).map(r => `${r.month}/${r.year}`),
  datasets: [{ data: (props.finance?.netTrend ?? []).map(r => Number(r.total)), borderColor: "#10b981", backgroundColor: "rgba(16,185,129,.08)", fill: true }],
}))

const peso = (v) => v == null ? "—" : `₱${Number(v).toLocaleString("en-PH", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`

// ── Scorecard sorting ───────────────────────────────────────────────────────
const sortKey = ref("headcount")
const sortDesc = ref(true)
const sortedScorecard = computed(() => {
  const rows = [...(props.scorecard ?? [])]
  rows.sort((a, b) => {
    const av = a[sortKey.value] ?? -1, bv = b[sortKey.value] ?? -1
    return sortDesc.value ? bv - av : av - bv
  })
  return rows
})
const sortBy = (key) => {
  if (sortKey.value === key) sortDesc.value = !sortDesc.value
  else { sortKey.value = key; sortDesc.value = true }
}

const taskProgressPct = computed(() => {
  const t = props.operations?.committeeTasks
  return t?.total ? Math.round(t.done / t.total * 100) : 0
})
</script>

<template>
  <Head title="Executive Dashboard" />
  <AdminLayout title="Executive Dashboard">
    <div class="space-y-5 exec-dashboard">

      <!-- Header -->
      <div class="flex flex-wrap items-center gap-3">
        <div>
          <h1 class="font-heading text-xl font-semibold text-slate-800 flex items-center gap-2">
            Executive Dashboard
            <AppBadge :color="lens?.division ? 'blue' : 'purple'">{{ lensLabel }}</AppBadge>
          </h1>
          <p class="text-xs text-slate-400 mt-0.5">Data as of {{ generatedLabel }}</p>
        </div>
        <div class="flex-1" />
        <AppSelect v-if="lens?.campus && divisions.length" :model-value="filterDivision" class="w-64 no-print"
          @update:model-value="applyDivision">
          <option value="">All divisions (campus-wide)</option>
          <option v-for="d in divisions" :key="d.id" :value="String(d.id)">{{ d.division_name }}</option>
        </AppSelect>
        <AppButton variant="secondary" class="no-print" :loading="isSubmitting" :disabled="isSubmitting" @click="refresh">
          <ArrowPathIcon class="w-4 h-4" /> Refresh
        </AppButton>
        <AppButton variant="secondary" class="no-print" @click="printPage">
          <PrinterIcon class="w-4 h-4" /> Print
        </AppButton>
      </div>

      <!-- Needs Attention -->
      <div v-if="attention.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
        <Link v-for="(a, i) in attention" :key="i" :href="route(a.route)"
          :class="['flex items-center gap-3 rounded-xl border px-4 py-3 transition-colors',
                   a.severity === 'danger' ? 'border-red-200 bg-red-50/70 hover:bg-red-50' : 'border-amber-200 bg-amber-50/70 hover:bg-amber-50']">
          <ExclamationTriangleIcon :class="['w-6 h-6 shrink-0', a.severity === 'danger' ? 'text-red-500' : 'text-amber-500']" />
          <div class="min-w-0">
            <p :class="['text-lg font-bold leading-none', a.severity === 'danger' ? 'text-red-700' : 'text-amber-700']">{{ a.count }}</p>
            <p class="text-xs text-slate-600 mt-0.5">{{ a.label }}</p>
          </div>
        </Link>
      </div>
      <div v-else class="flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50/70 px-4 py-3 text-sm text-emerald-700">
        <ShieldCheckIcon class="w-5 h-5" /> All clear — nothing needs your attention right now.
      </div>

      <!-- KPI tiles -->
      <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3">
        <AppCard v-for="k in kpis" :key="k.label" class="!p-4">
          <div class="flex items-center gap-3">
            <span :class="['w-9 h-9 rounded-lg flex items-center justify-center shrink-0', k.color]">
              <component :is="k.icon" class="w-5 h-5" />
            </span>
            <div class="min-w-0">
              <p class="text-xl font-bold text-slate-800 leading-none">{{ k.value }}</p>
              <p class="text-[11px] text-slate-500 mt-1 truncate">{{ k.label }}<span v-if="k.sub" class="text-slate-400"> · {{ k.sub }}</span></p>
            </div>
          </div>
        </AppCard>
      </div>

      <!-- Division scorecard (campus lens) -->
      <AppCard v-if="scorecard" :padded="false" title="Division Scorecard" subtitle="Click a column to sort">
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-slate-100">
                <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Division</th>
                <th v-for="(col, key) in { headcount: 'Headcount', leaveDaysPerEmp: 'Leave d/emp (YTD)', ipcrRate: 'IPCR %', requestRate: 'Request completion %' }"
                    :key="key" class="px-4 py-2.5 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide cursor-pointer select-none hover:text-indigo-600"
                    @click="sortBy(key)">
                  {{ col }} <span v-if="sortKey === key">{{ sortDesc ? '▾' : '▴' }}</span>
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in sortedScorecard" :key="row.division" class="border-b border-slate-50 hover:bg-slate-50/60">
                <td class="px-4 py-2.5 font-medium text-slate-700">{{ row.division }}</td>
                <td class="px-4 py-2.5 text-right text-slate-600">{{ row.headcount }}</td>
                <td class="px-4 py-2.5 text-right text-slate-600">{{ row.leaveDaysPerEmp }}</td>
                <td class="px-4 py-2.5">
                  <div class="flex items-center justify-end gap-2">
                    <div class="w-16 h-1.5 rounded-full bg-slate-100 overflow-hidden"><div class="h-full bg-violet-500" :style="{ width: row.ipcrRate + '%' }" /></div>
                    <span class="text-slate-600 w-9 text-right">{{ row.ipcrRate }}%</span>
                  </div>
                </td>
                <td class="px-4 py-2.5">
                  <div class="flex items-center justify-end gap-2">
                    <div class="w-16 h-1.5 rounded-full bg-slate-100 overflow-hidden"><div class="h-full bg-emerald-500" :style="{ width: (row.requestRate ?? 0) + '%' }" /></div>
                    <span class="text-slate-600 w-9 text-right">{{ row.requestRate ?? '—' }}<template v-if="row.requestRate !== null">%</template></span>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </AppCard>

      <!-- Sections grid -->
      <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">

        <!-- 1. Workforce & Leave -->
        <AppCard title="Workforce & Leave" subtitle="Headcount, leave activity, today's attendance" class="exec-section">
          <div class="grid grid-cols-2 gap-4">
            <div v-if="!lens?.division">
              <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">By division</p>
              <div style="height: 200px"><Bar :data="wfDivisionChart" :options="hBarOptions" /></div>
            </div>
            <div v-else>
              <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">By category</p>
              <div style="height: 200px"><Doughnut :data="wfCategoryChart" :options="doughnutOptions" /></div>
            </div>
            <div>
              <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Leave filed — 12 months</p>
              <div style="height: 200px"><Line :data="leaveTrendChart" :options="lineOptions" /></div>
            </div>
          </div>
          <div class="flex flex-wrap gap-x-5 gap-y-1 mt-4 text-xs text-slate-500">
            <span>Leave days used YTD: <strong class="text-slate-700">{{ workforce?.leaveDaysYtd ?? 0 }}</strong></span>
            <span>Pending leave: <strong class="text-slate-700">{{ (workforce?.leaveByStatus?.pending ?? 0) + (workforce?.leaveByStatus?.forwarded ?? 0) + (workforce?.leaveByStatus?.hr_verified ?? 0) }}</strong></span>
            <span>On leave today: <strong class="text-slate-700">{{ workforce?.dtrToday?.on_leave ?? 0 }}</strong></span>
            <span>Absent today: <strong class="text-slate-700">{{ workforce?.dtrToday?.absent ?? 0 }}</strong></span>
          </div>
        </AppCard>

        <!-- 2. Performance (IPCR) -->
        <AppCard title="Performance Management" :subtitle="`IPCR — ${performance?.currentPeriod?.label ?? 'no open period'}`" class="exec-section">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Submission funnel</p>
              <div style="height: 200px">
                <Doughnut v-if="Object.keys(performance?.funnel ?? {}).length" :data="ipcrFunnelChart" :options="doughnutOptions" />
                <EmptyState v-else title="No IPCRs yet for this period" />
              </div>
            </div>
            <div v-if="!lens?.division">
              <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Compliance % by division</p>
              <div style="height: 200px"><Bar :data="complianceChart" :options="{ ...hBarOptions, scales: { x: { min: 0, max: 100 } } }" /></div>
            </div>
            <div v-else class="flex flex-col justify-center items-center text-center">
              <p class="text-3xl font-bold text-violet-600">{{ ipcrCompliance ?? '—' }}<span v-if="ipcrCompliance !== null" class="text-lg">%</span></p>
              <p class="text-xs text-slate-500 mt-1">of your division has an IPCR this period</p>
            </div>
          </div>
          <div v-if="performance?.avgRating" class="mt-4 text-xs text-slate-500">
            Last closed period ({{ performance.lastClosedPeriod?.label }}): average final rating
            <strong class="text-slate-700">{{ performance.avgRating }}</strong> — {{ performance.avgAdjectival }}
          </div>
        </AppCard>

        <!-- 3. Requests & Service Delivery -->
        <AppCard title="Requests & Service Delivery" subtitle="Across IT, Facility, Vehicle, Service, Work, and Travel" class="exec-section">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Open vs completed</p>
              <div style="height: 210px"><Bar :data="requestBarChart" :options="requestBarOptions" /></div>
            </div>
            <div>
              <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Volume — 6 months</p>
              <div style="height: 210px"><Line :data="requestTrendChart" :options="lineOptions" /></div>
            </div>
          </div>
          <div class="flex flex-wrap gap-x-5 gap-y-1 mt-4 text-xs text-slate-500">
            <span>Filed this month: <strong class="text-slate-700">{{ (requests?.modules ?? []).reduce((s, m) => s + m.thisMonth, 0) }}</strong></span>
            <span>Open > 7 days: <strong :class="requests?.openOver7d ? 'text-red-600' : 'text-slate-700'">{{ requests?.openOver7d ?? 0 }}</strong></span>
            <span v-if="requests?.avgCompletionDays !== null">IT avg completion: <strong class="text-slate-700">{{ requests.avgCompletionDays }} days</strong></span>
          </div>
        </AppCard>

        <!-- 4. Client Satisfaction -->
        <AppCard title="Client Satisfaction (CSM)" :subtitle="`${satisfaction?.responses ?? 0} responses — overall ${satisfaction?.overall ?? '—'} (${satisfaction?.adjectival ?? '—'})`" class="exec-section">
          <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Service quality dimensions (1–5)</p>
          <div style="height: 230px"><Bar :data="sqdChart" :options="sqdOptions" /></div>
          <div v-if="(satisfaction?.byOffice ?? []).length" class="mt-3 flex flex-wrap gap-2">
            <span v-for="o in satisfaction.byOffice" :key="o.label" class="text-[11px] bg-slate-50 border border-slate-100 rounded-full px-2.5 py-1 text-slate-600">
              {{ o.label }}: <strong>{{ o.avg_score ?? '—' }}</strong> ({{ o.responses }})
            </span>
          </div>
        </AppCard>

        <!-- 5. Academics -->
        <AppCard title="Academics" subtitle="Enrollment, faculty loading, class records" class="exec-section">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Enrolled by grade ({{ academics?.enrolledTotal ?? 0 }} total)</p>
              <div style="height: 200px"><Bar :data="enrollmentChart" :options="vBarOptions" /></div>
            </div>
            <div>
              <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Faculty load status</p>
              <div style="height: 200px">
                <Doughnut v-if="Object.keys(academics?.facultyLoad ?? {}).length" :data="loadChart" :options="doughnutOptions" />
                <EmptyState v-else title="No faculty loads this term" />
              </div>
            </div>
          </div>
          <div class="flex flex-wrap gap-x-5 gap-y-1 mt-4 text-xs text-slate-500">
            <span>Class records checked: <strong class="text-slate-700">{{ academics?.classRecords?.checked ?? 0 }}</strong> / {{ Object.values(academics?.classRecords ?? {}).reduce((s, v) => s + Number(v), 0) }}</span>
            <span>Student gate scans today: <strong class="text-slate-700">{{ Object.values(academics?.gateToday ?? {}).reduce((s, v) => s + Number(v), 0) }}</strong></span>
          </div>
        </AppCard>

        <!-- 6. Recruitment -->
        <AppCard title="Recruitment" :subtitle="`${recruitment?.openVacancies ?? 0} open vacancies`" class="exec-section">
          <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Application pipeline</p>
          <div style="height: 200px">
            <Doughnut v-if="Object.keys(recruitment?.pipeline ?? {}).length" :data="pipelineChart" :options="doughnutOptions" />
            <EmptyState v-else title="No applications in the pipeline" />
          </div>
          <div class="flex flex-wrap gap-x-5 gap-y-1 mt-4 text-xs text-slate-500">
            <span>Applications this month: <strong class="text-slate-700">{{ recruitment?.applicationsThisMonth ?? 0 }}</strong></span>
            <span>Placements pending: <strong class="text-slate-700">{{ recruitment?.placementsPending ?? 0 }}</strong></span>
          </div>
        </AppCard>

        <!-- 7. Finance snapshot -->
        <AppCard title="Finance Snapshot" subtitle="Payroll and procurement" class="exec-section">
          <div class="grid grid-cols-2 gap-4">
            <div class="space-y-2">
              <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Latest payroll run</p>
              <template v-if="finance?.latestRun">
                <p class="text-lg font-bold text-slate-800">{{ peso(finance.latestRun.total_net) }}</p>
                <p class="text-xs text-slate-500">{{ finance.latestRun.month }}/{{ finance.latestRun.year }} ({{ finance.latestRun.period }}) — {{ finance.latestRun.employee_count }} employees · <AppBadge color="blue">{{ finance.latestRun.status }}</AppBadge></p>
              </template>
              <EmptyState v-else title="No payroll runs yet" />
              <div class="flex flex-wrap gap-1.5 pt-2">
                <span v-for="(n, s) in finance?.prByStatus ?? {}" :key="'pr'+s" class="text-[11px] bg-slate-50 border border-slate-100 rounded-full px-2 py-0.5 text-slate-600">PR {{ s }}: <strong>{{ n }}</strong></span>
                <span v-for="(n, s) in finance?.dvByStatus ?? {}" :key="'dv'+s" class="text-[11px] bg-slate-50 border border-slate-100 rounded-full px-2 py-0.5 text-slate-600">DV {{ s }}: <strong>{{ n }}</strong></span>
              </div>
            </div>
            <div v-if="(finance?.netTrend ?? []).length">
              <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Net payroll — 6 months</p>
              <div style="height: 180px"><Line :data="netTrendChart" :options="lineOptions" /></div>
            </div>
          </div>
        </AppCard>

        <!-- 8. Operations pulse -->
        <AppCard title="Operations Pulse" subtitle="Documents, committees, system health" class="exec-section">
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
            <div class="rounded-lg bg-slate-50 p-3">
              <p class="text-lg font-bold" :class="operations?.routingsOverdue ? 'text-red-600' : 'text-slate-800'">{{ operations?.routingsOverdue ?? 0 }}</p>
              <p class="text-[11px] text-slate-500">Overdue document routings ({{ operations?.routingsOpen ?? 0 }} open)</p>
            </div>
            <div class="rounded-lg bg-slate-50 p-3">
              <p class="text-lg font-bold text-slate-800">{{ operations?.issuancesThisMonth ?? 0 }}</p>
              <p class="text-[11px] text-slate-500">Issuances this month</p>
            </div>
            <div class="rounded-lg bg-slate-50 p-3">
              <p class="text-lg font-bold" :class="operations?.openErrorReports ? 'text-amber-600' : 'text-slate-800'">{{ operations?.openErrorReports ?? 0 }}</p>
              <p class="text-[11px] text-slate-500">Open error reports</p>
            </div>
            <div class="rounded-lg bg-slate-50 p-3 col-span-2">
              <div class="flex items-center gap-2">
                <div class="flex-1 h-2 rounded-full bg-slate-200 overflow-hidden"><div class="h-full bg-emerald-500" :style="{ width: taskProgressPct + '%' }" /></div>
                <span class="text-xs font-semibold text-slate-600">{{ operations?.committeeTasks?.done ?? 0 }}/{{ operations?.committeeTasks?.total ?? 0 }}</span>
              </div>
              <p class="text-[11px] text-slate-500 mt-1.5">Committee tasks done this period<span v-if="operations?.committeeTasks?.stuck" class="text-red-600 font-medium"> · {{ operations.committeeTasks.stuck }} stuck</span></p>
            </div>
            <div class="rounded-lg bg-slate-50 p-3">
              <p class="text-lg font-bold text-emerald-600">{{ operations?.activeUsersNow ?? 0 }}</p>
              <p class="text-[11px] text-slate-500">Active users (30 min)</p>
            </div>
          </div>
        </AppCard>
      </div>
    </div>
  </AdminLayout>
</template>

<style scoped>
@media print {
  .no-print { display: none !important; }
  .exec-dashboard { gap: 12px; }
  .exec-section { break-inside: avoid; }
}
</style>
