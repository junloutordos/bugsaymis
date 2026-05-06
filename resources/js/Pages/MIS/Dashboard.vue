<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
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
import {
  ClipboardDocumentListIcon,
  CheckCircleIcon,
  ClockIcon,
  StarIcon,
  BoltIcon,
  ChatBubbleLeftRightIcon,
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
  'Pending Division Chief Approval': '#94a3b8',
  'Pending OCD Approval':            '#64748b',
  'In Progress':                     '#3b82f6',
  'MIS Assessed the Request':        '#8b5cf6',
  'Acted by MIS':                    '#f59e0b',
  'Request Completed':               '#10b981',
  'Rejected by Division Chief':      '#ef4444',
  'Rejected by OCD':                 '#dc2626',
}

const donutData = computed(() => {
  const labels = Object.keys(props.statusBreakdown)
  return {
    labels,
    datasets: [{
      data:            labels.map(l => props.statusBreakdown[l]),
      backgroundColor: labels.map(l => statusColors[l] ?? '#cbd5e1'),
      borderWidth:     1,
    }],
  }
})

const donutOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { position: 'right', labels: { boxWidth: 12, font: { size: 11 } } },
  },
}

// ── Category bar ──────────────────────────────────────────────────────────────

const categoryData = computed(() => ({
  labels: props.categoryBreakdown.map(r => r.category),
  datasets: [{
    label:           'Requests',
    data:            props.categoryBreakdown.map(r => r.count),
    backgroundColor: '#6366f1',
    borderRadius:    4,
  }],
}))

const barOptions = {
  responsive: true,
  maintainAspectRatio: false,
  indexAxis: 'y',
  plugins: { legend: { display: false } },
  scales: {
    x: { beginAtZero: true, ticks: { precision: 0 } },
    y: { ticks: { font: { size: 11 } } },
  },
}

// ── Monthly trend line ────────────────────────────────────────────────────────

const trendData = computed(() => ({
  labels: props.monthlyTrend.map(r => r.month),
  datasets: [
    {
      label:            'Filed',
      data:             props.monthlyTrend.map(r => r.filed),
      borderColor:      '#6366f1',
      backgroundColor:  'rgba(99,102,241,0.08)',
      fill:             true,
      tension:          0.3,
      pointRadius:      3,
    },
    {
      label:            'Completed',
      data:             props.monthlyTrend.map(r => r.completed),
      borderColor:      '#10b981',
      backgroundColor:  'rgba(16,185,129,0.08)',
      fill:             true,
      tension:          0.3,
      pointRadius:      3,
    },
  ],
}))

const lineOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { position: 'top', labels: { boxWidth: 12, font: { size: 11 } } } },
  scales: {
    y: { beginAtZero: true, ticks: { precision: 0 } },
    x: { ticks: { font: { size: 11 } } },
  },
}

// ── SQD bar ───────────────────────────────────────────────────────────────────

const sqdData = computed(() => ({
  labels: props.sqdBreakdown.map(r => r.label),
  datasets: [{
    label:           'Avg Score (1–5)',
    data:            props.sqdBreakdown.map(r => r.score),
    backgroundColor: props.sqdBreakdown.map(r =>
      r.score >= 4.5 ? '#10b981' :
      r.score >= 3.5 ? '#6366f1' :
      r.score >= 2.5 ? '#f59e0b' : '#ef4444'
    ),
    borderRadius: 4,
  }],
}))

const sqdOptions = {
  responsive: true,
  maintainAspectRatio: false,
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
    'Request Completed':               'bg-emerald-100 text-emerald-700',
    'Acted by MIS':                    'bg-amber-100 text-amber-700',
    'In Progress':                     'bg-blue-100 text-blue-700',
    'MIS Assessed the Request':        'bg-violet-100 text-violet-700',
    'Pending Division Chief Approval': 'bg-slate-100 text-slate-600',
    'Pending OCD Approval':            'bg-slate-100 text-slate-600',
    'Rejected by Division Chief':      'bg-red-100 text-red-600',
    'Rejected by OCD':                 'bg-red-100 text-red-600',
  }
  return map[status] ?? 'bg-slate-100 text-slate-600'
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
</script>

<template>
  <Head title="MIS Dashboard" />
  <AdminLayout title="MIS Dashboard">
    <div class="space-y-6">

      <!-- Header + month picker -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 class="text-xl font-bold text-slate-800">MIS Analytics Dashboard</h1>
          <p class="text-sm text-slate-500 mt-0.5">IT Job Request performance and client satisfaction metrics</p>
        </div>
        <div class="flex items-center gap-2">
          <label class="text-xs text-slate-500 font-medium">Month</label>
          <input
            v-model="currentMonth"
            type="month"
            @change="goMonth"
            class="border border-slate-200 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
          />
        </div>
      </div>

      <!-- Pending action banner -->
      <div v-if="kpi.pending_action > 0"
           class="bg-amber-50 border border-amber-300 rounded-xl px-5 py-3 flex items-center gap-3 text-sm text-amber-800">
        <ClockIcon class="h-5 w-5 shrink-0 text-amber-500" />
        <span>
          <strong>{{ kpi.pending_action }}</strong>
          request{{ kpi.pending_action > 1 ? 's' : '' }} with status <em>Acted by MIS</em> are waiting for client confirmation and CSM rating.
        </span>
      </div>

      <!-- KPI Cards -->
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        <div v-for="card in kpiCards" :key="card.label"
             :class="['rounded-xl border p-4 flex flex-col gap-2', cardColors[card.color]]">
          <component :is="card.icon" class="h-5 w-5 opacity-70" />
          <div class="text-2xl font-bold leading-none">{{ card.value ?? '—' }}</div>
          <div class="text-xs font-semibold leading-tight">{{ card.label }}</div>
          <div class="text-[10px] opacity-70 leading-tight">{{ card.sub }}</div>
        </div>
      </div>

      <!-- Charts row 1: Status donut + Monthly trend -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

        <!-- Status Breakdown -->
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
          <h2 class="text-sm font-semibold text-slate-700 mb-4">Request Status Breakdown</h2>
          <div style="height:240px;">
            <Doughnut :data="donutData" :options="donutOptions" />
          </div>
        </div>

        <!-- Monthly Trend -->
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
          <h2 class="text-sm font-semibold text-slate-700 mb-4">Monthly Trend — Last 12 Months</h2>
          <div style="height:240px;">
            <Line :data="trendData" :options="lineOptions" />
          </div>
        </div>

      </div>

      <!-- Charts row 2: Category + SQD -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

        <!-- Category Breakdown -->
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
          <h2 class="text-sm font-semibold text-slate-700 mb-4">Top Request Categories</h2>
          <div :style="{ height: Math.max(180, categoryBreakdown.length * 28) + 'px' }">
            <Bar :data="categoryData" :options="barOptions" />
          </div>
        </div>

        <!-- CSM SQD Breakdown -->
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
          <h2 class="text-sm font-semibold text-slate-700 mb-1">Client Satisfaction — SQD Scores</h2>
          <p class="text-xs text-slate-400 mb-4">Average score per dimension (1–5; N/A excluded)</p>
          <div style="height:240px;">
            <Bar :data="sqdData" :options="sqdOptions" />
          </div>
        </div>

      </div>

      <!-- Personnel Workload table -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <div class="px-5 py-4 border-b border-slate-100">
          <h2 class="text-sm font-semibold text-slate-700">MIS Personnel Workload</h2>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Staff</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Total Handled</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Pending Confirmation</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-amber-500 uppercase tracking-wide">Avg Rating</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-if="!personnelWorkload.length">
                <td colspan="4" class="px-4 py-8 text-center text-slate-400 text-sm">No attendance data recorded yet.</td>
              </tr>
              <tr v-for="p in personnelWorkload" :key="p.attendedby" class="hover:bg-slate-50/60">
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
            </tbody>
          </table>
        </div>
      </div>

      <!-- Recent Requests table -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
          <h2 class="text-sm font-semibold text-slate-700">Recent Requests</h2>
          <a :href="route('jobrequests.index')" class="text-xs text-indigo-600 hover:underline">View all →</a>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">ITJR #</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Title</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Category</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Submitted By</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Date Filed</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="r in recentRequests" :key="r.id" class="hover:bg-slate-50/60">
                <td class="px-4 py-2.5 text-xs font-mono text-slate-600">{{ r.itjr_no }}</td>
                <td class="px-4 py-2.5 text-slate-700 max-w-xs truncate">{{ r.title }}</td>
                <td class="px-4 py-2.5 text-slate-500 text-xs">{{ r.category }}</td>
                <td class="px-4 py-2.5 text-slate-500 text-xs">{{ r.user?.name ?? '—' }}</td>
                <td class="px-4 py-2.5 text-slate-500 text-xs whitespace-nowrap">{{ fmtDate(r.created_at) }}</td>
                <td class="px-4 py-2.5">
                  <span :class="[statusBadge(r.status), 'inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium whitespace-nowrap']">
                    {{ statusShort(r.status) }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </AdminLayout>
</template>
