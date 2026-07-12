<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppCard from '@/Components/AppCard.vue'
import AppTable from '@/Components/AppTable.vue'
import AppBadge from '@/Components/AppBadge.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { Head, router } from '@inertiajs/vue3'
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
import { BRAND, STATUS, OTHER, gradientFill } from '@/Utils/chartTheme'
import { useCountUp } from '@/Composables/useCountUp.js'
import {
  ClipboardDocumentListIcon,
  CheckCircleIcon,
  ClockIcon,
  StarIcon,
  BoltIcon,
  ChatBubbleLeftRightIcon,
  ServerStackIcon,
  ExclamationTriangleIcon,
  ShieldExclamationIcon,
  ArrowPathIcon,
  ComputerDesktopIcon,
} from '@heroicons/vue/24/outline'

ChartJS.register(
  Title, Tooltip, Legend,
  ArcElement,
  CategoryScale, LinearScale,
  PointElement, LineElement, BarElement,
  Filler,
)

const props = defineProps({
  month:             String,
  kpi:               Object,
  statusBreakdown:   Object,
  categoryBreakdown: Array,
  monthlyTrend:      Array,
  personnelWorkload: Array,
  sqdBreakdown:      Array,
  recentRequests:    Array,
  canViewFleet:      Boolean,
  fleet:             Object,
})

const currentMonth = ref(props.month)

function goMonth() {
  router.get(route('mis.dashboard'), { month: currentMonth.value }, { preserveState: false })
}

const monthLabel = computed(() => {
  const [y, m] = currentMonth.value.split('-')
  return new Date(+y, +m - 1, 1).toLocaleDateString('en-PH', { month: 'long', year: 'numeric' })
})

// ── KPI helpers ───────────────────────────────────────────────────────────────

const kpiCards = computed(() => [
  {
    label:   'Requests Filed',
    value:   props.kpi.total_this_month,
    sub:     monthLabel.value,
    icon:    ClipboardDocumentListIcon,
    color:   'indigo',
  },
  {
    label:   'Completed',
    value:   props.kpi.completed_this_month,
    sub:     monthLabel.value,
    icon:    CheckCircleIcon,
    color:   'emerald',
  },
  {
    label:   'Pending Confirmation',
    value:   props.kpi.pending_action,
    sub:     'Acted — awaiting CSM',
    icon:    ClockIcon,
    color:   props.kpi.pending_action > 0 ? 'amber' : 'slate',
  },
  {
    label:   'Avg CSM Rating',
    value:   props.kpi.avg_rating ? props.kpi.avg_rating + ' ★' : '—',
    sub:     `${props.kpi.total_csm_responses} response(s) · ${monthLabel.value}`,
    icon:    StarIcon,
    color:   'violet',
  },
  {
    label:   'Avg Response Time',
    value:   props.kpi.avg_response_days != null ? props.kpi.avg_response_days + ' days' : '—',
    sub:     monthLabel.value,
    icon:    BoltIcon,
    color:   'sky',
  },
  {
    label:   'CSM Responses',
    value:   props.kpi.total_csm_responses,
    sub:     'All time',
    icon:    ChatBubbleLeftRightIcon,
    color:   'rose',
  },
])

const cardColors = {
  indigo:  'bg-indigo-50 text-indigo-600 border-indigo-100',
  emerald: 'bg-emerald-50 text-emerald-600 border-emerald-100',
  amber:   'bg-amber-50 text-amber-600 border-amber-100',
  violet:  'bg-violet-50 text-violet-600 border-violet-100',
  sky:     'bg-sky-50 text-sky-600 border-sky-100',
  rose:    'bg-rose-50 text-rose-600 border-rose-100',
  slate:   'bg-slate-50 text-slate-500 border-slate-100',
}

// ── Status donut ──────────────────────────────────────────────────────────────

const statusColors = {
  'Pending Division Chief Approval': OTHER,
  'Pending OCD Approval':            '#64748b',
  'In Progress':                     '#0284C7',
  'MIS Assessed the Request':        '#7C3AED',
  'Acted by MIS':                    BRAND,
  'Request Completed':               STATUS.success,
  'Rejected by Division Chief':      STATUS.danger,
  'Rejected by OCD':                 '#dc2626',
}

const donutData = computed(() => {
  const labels = Object.keys(props.statusBreakdown)
  return {
    labels,
    datasets: [{
      data:            labels.map(l => props.statusBreakdown[l]),
      backgroundColor: labels.map(l => statusColors[l] ?? OTHER),
    }],
  }
})

const donutOptions = {
  plugins: {
    legend: { position: 'right' },
  },
}

// ── Category bar ──────────────────────────────────────────────────────────────

const categoryData = computed(() => ({
  labels: props.categoryBreakdown.map(r => r.category),
  datasets: [{
    label:           'Requests',
    data:            props.categoryBreakdown.map(r => r.count),
    backgroundColor: BRAND,
  }],
}))

const barOptions = {
  indexAxis: 'y',
  plugins: { legend: { display: false } },
  scales: {
    x: { beginAtZero: true, ticks: { precision: 0 } },
  },
}

// ── Monthly trend line ────────────────────────────────────────────────────────

const trendData = computed(() => ({
  labels: props.monthlyTrend.map(r => r.month),
  datasets: [
    {
      label:            'Filed',
      data:             props.monthlyTrend.map(r => r.filed),
      borderColor:      BRAND,
      backgroundColor:  gradientFill(BRAND, 0.1),
      fill:             true,
    },
    {
      label:            'Completed',
      data:             props.monthlyTrend.map(r => r.completed),
      borderColor:      STATUS.success,
      backgroundColor:  gradientFill(STATUS.success, 0.1),
      fill:             true,
    },
  ],
}))

const lineOptions = {
  plugins: { legend: { position: 'top' } },
  scales: {
    y: { beginAtZero: true, ticks: { precision: 0 } },
  },
}

// ── SQD bar ───────────────────────────────────────────────────────────────────

const sqdData = computed(() => ({
  labels: props.sqdBreakdown.map(r => r.label),
  datasets: [{
    label:           'Avg Score (1–5)',
    data:            props.sqdBreakdown.map(r => r.score),
    backgroundColor: props.sqdBreakdown.map(r =>
      r.score >= 4.5 ? STATUS.success :
      r.score >= 3.5 ? BRAND :
      r.score >= 2.5 ? STATUS.warning : STATUS.danger
    ),
  }],
}))

const sqdOptions = {
  indexAxis: 'y',
  plugins: { legend: { display: false } },
  scales: {
    x: { min: 0, max: 5, ticks: { precision: 1 } },
    y: { ticks: { font: { size: 10 } } },
  },
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function fmtDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' })
}

function statusBadge(status) {
  const map = {
    'Request Completed':               'green',
    'Acted by MIS':                    'amber',
    'In Progress':                     'orange',
    'MIS Assessed the Request':        'purple',
    'Pending Division Chief Approval': 'slate',
    'Pending OCD Approval':            'slate',
    'Rejected by Division Chief':      'red',
    'Rejected by OCD':                 'red',
  }
  return map[status] ?? 'slate'
}

function statusShort(status) {
  const map = {
    'Request Completed':               'Completed',
    'Acted by MIS':                    'Acted',
    'In Progress':                     'In Progress',
    'MIS Assessed the Request':        'MIS Assessed',
    'Pending Division Chief Approval': 'Pending DC',
    'Pending OCD Approval':            'Pending OCD',
    'Rejected by Division Chief':      'Rejected DC',
    'Rejected by OCD':                 'Rejected OCD',
  }
  return map[status] ?? status
}

// ── Fleet & Infrastructure Health ──────────────────────────────────────────────

const fleetKpiCards = computed(() => {
  if (!props.fleet) return []
  return [
    {
      label: 'Devices Enrolled',
      value: `${props.fleet.devices_enrolled} / ${props.fleet.total_equipment}`,
      sub:   'Atlas Sentinel rollout',
      icon:  ServerStackIcon,
      color: 'indigo',
    },
    {
      label: 'Open Alerts',
      value: props.fleet.open_alerts,
      sub:   'Across the fleet',
      icon:  ExclamationTriangleIcon,
      color: props.fleet.open_alerts > 0 ? 'amber' : 'slate',
    },
    {
      label: 'High/Critical Risk',
      value: props.fleet.high_critical_risk,
      sub:   'Devices needing attention',
      icon:  ShieldExclamationIcon,
      color: props.fleet.high_critical_risk > 0 ? 'rose' : 'slate',
    },
    {
      label: 'Reboot Required',
      value: props.fleet.reboot_required,
      sub:   `${props.fleet.antivirus_disabled} antivirus disabled`,
      icon:  ArrowPathIcon,
      color: props.fleet.reboot_required > 0 ? 'sky' : 'slate',
    },
  ]
})

const { values: kpiValues } = useCountUp(() => kpiCards.value.map(c => c.value))
const { values: fleetKpiValues } = useCountUp(() => fleetKpiCards.value.map(c => c.value))

const riskTierLabels = { critical: 'Critical', high: 'High', medium: 'Medium', low: 'Low' }
const riskTierColors = { critical: STATUS.danger, high: '#f97316', medium: STATUS.warning, low: STATUS.success }

const riskDonutData = computed(() => {
  const breakdown = props.fleet?.risk_tier_breakdown ?? {}
  const keys = Object.keys(breakdown)
  return {
    labels: keys.map(k => riskTierLabels[k] ?? 'Not Scored'),
    datasets: [{
      data: keys.map(k => breakdown[k]),
      backgroundColor: keys.map(k => riskTierColors[k] ?? OTHER),
    }],
  }
})

function riskTierBadge(tier) {
  const map = {
    critical: 'red',
    high:     'orange',
    medium:   'amber',
    low:      'green',
  }
  return map[tier] ?? 'slate'
}

function fmtDateTime(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' })
}
</script>

<template>
  <Head title="MIS Dashboard" />
  <AdminLayout title="MIS Dashboard">
    <div class="space-y-6">

      <!-- Header + month picker -->
      <AppPageHeader hero class="dash-section" style="--stagger: 0" title="MIS Analytics Dashboard" subtitle="IT Job Request performance and client satisfaction metrics">
        <template #actions>
          <label class="text-xs text-slate-500 font-medium">Month</label>
          <input
            v-model="currentMonth"
            type="month"
            @change="goMonth"
            class="border border-slate-200 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
          />
        </template>
      </AppPageHeader>

      <!-- Pending action banner -->
      <div v-if="kpi.pending_action > 0"
           class="bg-warning-50 border border-warning-100 rounded-xl px-5 py-3 flex items-center gap-3 text-sm text-warning-700">
        <ClockIcon class="h-5 w-5 shrink-0 text-warning-500" />
        <span>
          <strong>{{ kpi.pending_action }}</strong>
          request{{ kpi.pending_action > 1 ? 's' : '' }} with status <em>Acted by MIS</em> are waiting for client confirmation and CSM rating.
        </span>
      </div>

      <!-- KPI Cards -->
      <div class="dash-section grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3" style="--stagger: 1">
        <div v-for="(card, i) in kpiCards" :key="card.label"
             :class="['rounded-xl border p-4 flex flex-col gap-2', cardColors[card.color]]">
          <component :is="card.icon" class="h-5 w-5 opacity-70" />
          <div class="text-2xl font-bold leading-none tabular-nums">{{ kpiValues[i] ?? card.value ?? '—' }}</div>
          <div class="text-xs font-semibold leading-tight">{{ card.label }}</div>
          <div class="text-[10px] opacity-70 leading-tight">{{ card.sub }}</div>
        </div>
      </div>

      <!-- Charts row 1: Status donut + Monthly trend -->
      <div class="dash-section grid grid-cols-1 lg:grid-cols-2 gap-4" style="--stagger: 2">

        <!-- Status Breakdown -->
        <AppCard title="Request Status Breakdown">
          <div style="height:240px;">
            <Doughnut :data="donutData" :options="donutOptions" />
          </div>
        </AppCard>

        <!-- Monthly Trend -->
        <AppCard title="Monthly Trend — Last 12 Months">
          <div style="height:240px;">
            <Line :data="trendData" :options="lineOptions" />
          </div>
        </AppCard>

      </div>

      <!-- Charts row 2: Category + SQD -->
      <div class="dash-section grid grid-cols-1 lg:grid-cols-2 gap-4" style="--stagger: 3">

        <!-- Category Breakdown -->
        <AppCard title="Top Request Categories">
          <div :style="{ height: Math.max(180, categoryBreakdown.length * 28) + 'px' }">
            <Bar :data="categoryData" :options="barOptions" />
          </div>
        </AppCard>

        <!-- CSM SQD Breakdown -->
        <AppCard title="Client Satisfaction — SQD Scores" subtitle="Average score per dimension (1–5; N/A excluded)">
          <div style="height:240px;">
            <Bar :data="sqdData" :options="sqdOptions" />
          </div>
        </AppCard>

      </div>

      <!-- Personnel Workload table -->
      <AppCard title="MIS Personnel Workload" :padded="false" class="dash-section" style="--stagger: 4">
        <AppTable :is-empty="!personnelWorkload.length" :card="false" :skeleton-cols="4">
          <template #head>
            <tr>
              <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Staff</th>
              <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Total Handled</th>
              <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Pending Confirmation</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-amber-500 uppercase tracking-wide">Avg Rating</th>
            </tr>
          </template>
          <tr v-for="p in personnelWorkload" :key="p.attendedby" class="hover:bg-indigo-50/40">
            <td class="px-4 py-3 font-medium text-slate-700">{{ p.attendedby }}</td>
            <td class="px-4 py-3 text-center text-slate-700 tabular-nums">{{ p.total }}</td>
            <td class="px-4 py-3 text-center tabular-nums"
                :class="p.pending > 0 ? 'text-amber-600 font-semibold' : 'text-slate-300'">
              {{ p.pending > 0 ? p.pending : '—' }}
            </td>
            <td class="px-4 py-3 text-center tabular-nums"
                :class="p.avg_rating ? 'text-violet-600 font-semibold' : 'text-slate-300'">
              {{ p.avg_rating ? p.avg_rating + ' ★' : '—' }}
            </td>
          </tr>
          <template #empty>
            <EmptyState title="No attendance data recorded yet." />
          </template>
        </AppTable>
      </AppCard>

      <!-- Recent Requests table -->
      <AppCard title="Recent Requests" :padded="false" class="dash-section" style="--stagger: 5">
        <template #header>
          <a :href="route('jobrequests.index')" class="text-xs text-indigo-600 hover:underline">View all →</a>
        </template>
        <AppTable :is-empty="!recentRequests.length" :card="false" :skeleton-cols="6">
          <template #head>
            <tr>
              <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">ITJR #</th>
              <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Title</th>
              <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Category</th>
              <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Submitted By</th>
              <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Date Filed</th>
              <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Status</th>
            </tr>
          </template>
          <tr v-for="r in recentRequests" :key="r.id" class="hover:bg-indigo-50/40">
            <td class="px-4 py-2.5 text-xs font-mono text-slate-600">{{ r.itjr_no }}</td>
            <td class="px-4 py-2.5 text-slate-700 max-w-xs truncate">{{ r.title }}</td>
            <td class="px-4 py-2.5 text-slate-500 text-xs">{{ r.category }}</td>
            <td class="px-4 py-2.5 text-slate-500 text-xs">{{ r.user?.name ?? '—' }}</td>
            <td class="px-4 py-2.5 text-slate-500 text-xs whitespace-nowrap">{{ fmtDate(r.created_at) }}</td>
            <td class="px-4 py-2.5">
              <AppBadge :color="statusBadge(r.status)" class="whitespace-nowrap">{{ statusShort(r.status) }}</AppBadge>
            </td>
          </tr>
          <template #empty>
            <EmptyState title="No recent requests found." />
          </template>
        </AppTable>
      </AppCard>

      <!-- Fleet & Infrastructure Health -->
      <div v-if="canViewFleet && fleet" class="dash-section space-y-4 pt-2 border-t border-slate-100" style="--stagger: 6">
        <div>
          <h1 class="text-lg font-bold text-slate-800">Fleet &amp; Infrastructure Health</h1>
          <p class="text-sm text-slate-500 mt-0.5">Live Atlas Sentinel fleet snapshot — not scoped to the month above</p>
        </div>

        <!-- Fleet KPI Cards -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
          <div v-for="(card, i) in fleetKpiCards" :key="card.label"
               :class="['rounded-xl border p-4 flex flex-col gap-2', cardColors[card.color]]">
            <component :is="card.icon" class="h-5 w-5 opacity-70" />
            <div class="text-2xl font-bold leading-none tabular-nums">{{ fleetKpiValues[i] ?? card.value ?? '—' }}</div>
            <div class="text-xs font-semibold leading-tight">{{ card.label }}</div>
            <div class="text-[10px] opacity-70 leading-tight">{{ card.sub }}</div>
          </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

          <!-- Risk Tier Donut -->
          <AppCard title="Device Risk Tiers">
            <div v-if="!Object.keys(fleet.risk_tier_breakdown).length" class="py-16 text-center text-slate-400 text-sm">
              No enrolled devices yet.
            </div>
            <div v-else style="height:240px;">
              <Doughnut :data="riskDonutData" :options="donutOptions" />
            </div>
          </AppCard>

          <!-- Computer Laboratories -->
          <AppCard title="Computer Laboratories">
            <template #header>
              <a :href="route('computer-labs.index')" class="text-xs text-indigo-600 hover:underline">View all →</a>
            </template>
            <div v-if="!fleet.labs.length" class="py-12 text-center text-slate-400 text-sm">
              No rooms tagged as "Computer Laboratory" yet.
            </div>
            <div v-else class="space-y-2">
              <a
                v-for="lab in fleet.labs"
                :key="lab.room.id"
                :href="route('computer-labs.show', lab.room.id)"
                class="flex items-center justify-between gap-3 px-3 py-2.5 rounded-lg border border-slate-100 hover:border-indigo-200 hover:bg-slate-50 transition-colors"
              >
                <div class="flex items-center gap-2">
                  <ComputerDesktopIcon class="w-4 h-4 text-indigo-500" />
                  <span class="text-sm text-slate-700">{{ lab.room.name }}</span>
                </div>
                <div class="flex items-center gap-2 text-xs">
                  <span class="text-slate-400">{{ lab.enrolled }}/{{ lab.total }} enrolled</span>
                  <AppBadge v-if="lab.critical > 0" color="red">{{ lab.critical }} critical</AppBadge>
                </div>
              </a>
            </div>
          </AppCard>

        </div>

        <!-- Top At-Risk Devices -->
        <AppCard title="Top At-Risk Devices" :padded="false">
          <template #header>
            <a :href="route('atlas-sentinel.health-dashboard')" class="text-xs text-indigo-600 hover:underline">View Atlas Sentinel Health Dashboard →</a>
          </template>
          <AppTable :is-empty="!fleet.top_at_risk_devices.length" :card="false" :skeleton-cols="5">
            <template #head>
              <tr>
                <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Device</th>
                <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Room</th>
                <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Risk</th>
                <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Open Alerts</th>
                <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Last Check-in</th>
              </tr>
            </template>
            <tr v-for="d in fleet.top_at_risk_devices" :key="d.id" class="hover:bg-indigo-50/40">
              <td class="px-4 py-3 font-medium text-slate-700">{{ d.hostname || '—' }}</td>
              <td class="px-4 py-3 text-slate-500 text-xs">{{ d.equipment?.room?.name || '—' }}</td>
              <td class="px-4 py-3 text-center">
                <AppBadge :color="riskTierBadge(d.risk_tier)" class="capitalize">{{ d.risk_tier ?? '—' }}</AppBadge>
              </td>
              <td class="px-4 py-3 text-center tabular-nums" :class="d.open_alerts_count > 0 ? 'text-amber-600 font-semibold' : 'text-slate-300'">
                {{ d.open_alerts_count > 0 ? d.open_alerts_count : '—' }}
              </td>
              <td class="px-4 py-3 text-slate-500 text-xs whitespace-nowrap">{{ fmtDateTime(d.last_checkin_at) }}</td>
            </tr>
            <template #empty>
              <EmptyState title="No scored devices yet." />
            </template>
          </AppTable>
        </AppCard>

      </div>

    </div>
  </AdminLayout>
</template>
