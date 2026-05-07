<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { Head, router } from '@inertiajs/vue3'
import { computed } from 'vue'
import {
  Chart as ChartJS,
  Title, Tooltip, Legend,
  ArcElement,
  CategoryScale, LinearScale,
  PointElement, LineElement, BarElement,
  Filler,
} from 'chart.js'
import { Doughnut, Bar, Line } from 'vue-chartjs'
import { StarIcon, ChatBubbleLeftRightIcon, UserGroupIcon, ClipboardDocumentCheckIcon } from '@heroicons/vue/24/outline'

ChartJS.register(Title, Tooltip, Legend, ArcElement, CategoryScale, LinearScale, PointElement, LineElement, BarElement, Filler)

const props = defineProps({
  total:          Number,
  thisMonth:      Number,
  overallAvg:     Number,
  sqdAvgs:        Object,
  byModule:       Array,
  byClientType:   Object,
  bySex:          Object,
  monthlyTrend:   Array,
  cc1Breakdown:   Object,
  adjectivalDist: Object,
})

// ── Helpers ───────────────────────────────────────────────────────────────────
function adjectivalColor(adj) {
  const map = {
    'Excellent': '#10b981', 'Very Good': '#3b82f6',
    'Satisfactory': '#8b5cf6', 'Fair': '#f59e0b', 'Poor': '#ef4444',
  }
  return map[adj] ?? '#94a3b8'
}

function sqdLabel(key) {
  const labels = {
    sqd0: 'Overall Satisfaction',     sqd1: 'Reasonable Time',
    sqd2: 'Followed Requirements',    sqd3: 'Steps Were Simple',
    sqd4: 'Info from Website',        sqd5: 'Reasonable Fees',
    sqd6: 'Transaction Secure',       sqd7: 'Support Responsive',
    sqd8: 'Got What Was Needed',
  }
  return labels[key] ?? key
}

// ── Chart data ─────────────────────────────────────────────────────────────

const moduleColors = ['#6366f1','#10b981','#f59e0b','#3b82f6','#ec4899']
const moduleChart = computed(() => ({
  labels: props.byModule.map(m => m.label),
  datasets: [{ data: props.byModule.map(m => m.count), backgroundColor: moduleColors, borderWidth: 1 }],
}))

const sqdChart = computed(() => ({
  labels: Object.keys(props.sqdAvgs).map(k => sqdLabel(k)),
  datasets: [{
    label: 'Avg Score (1–5)',
    data: Object.values(props.sqdAvgs),
    backgroundColor: Object.values(props.sqdAvgs).map(v =>
      v >= 4.5 ? '#10b981' : v >= 3.5 ? '#6366f1' : v >= 2.5 ? '#f59e0b' : '#ef4444'
    ),
    borderRadius: 4,
  }],
}))

const trendChart = computed(() => ({
  labels: props.monthlyTrend.map(m => m.month),
  datasets: [{
    label: 'Responses',
    data: props.monthlyTrend.map(m => m.count),
    borderColor: '#6366f1',
    backgroundColor: 'rgba(99,102,241,0.1)',
    fill: true,
    tension: 0.3,
    pointRadius: 3,
  }],
}))

const clientTypeChart = computed(() => {
  const entries = Object.entries(props.byClientType)
  return {
    labels: entries.map(([k]) => k.charAt(0).toUpperCase() + k.slice(1)),
    datasets: [{ data: entries.map(([,v]) => v), backgroundColor: ['#6366f1','#10b981','#f59e0b'], borderWidth: 1 }],
  }
})

const adjectivalChart = computed(() => {
  const entries = Object.entries(props.adjectivalDist)
  return {
    labels: entries.map(([k]) => k),
    datasets: [{
      data: entries.map(([,v]) => v),
      backgroundColor: entries.map(([k]) => adjectivalColor(k)),
      borderWidth: 1,
    }],
  }
})

const cc1Labels = {
  1: 'Know CC & saw it',
  2: 'Know CC but did NOT see it',
  3: 'Learned CC only when saw it',
  4: 'Do not know CC',
}
const cc1Chart = computed(() => {
  const entries = Object.entries(props.cc1Breakdown)
  return {
    labels: entries.map(([k]) => cc1Labels[k] ?? `CC1-${k}`),
    datasets: [{ data: entries.map(([,v]) => v), backgroundColor: ['#10b981','#6366f1','#f59e0b','#ef4444'], borderWidth: 1 }],
  }
})

const donutOpts = { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right', labels: { boxWidth: 12, font: { size: 11 } } } } }
const barOpts   = { responsive: true, maintainAspectRatio: false, indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { min: 0, max: 5, ticks: { precision: 1 } }, y: { ticks: { font: { size: 10 } } } } }
const lineOpts  = { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }

const starRating = computed(() => Math.round((props.overallAvg / 5) * 5 * 10) / 10)
</script>

<template>
  <Head title="CSM Feedback Dashboard" />
  <AdminLayout title="CSM Feedback Dashboard">
    <div class="space-y-6">

      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-xl font-bold text-slate-800">Client Satisfaction Dashboard</h1>
          <p class="text-sm text-slate-500 mt-0.5">Aggregated feedback from all General Services modules</p>
        </div>
        <a :href="route('csm.list')"
          class="inline-flex items-center gap-2 border border-slate-200 bg-white hover:bg-slate-50 text-slate-600 px-4 py-2 rounded-lg text-sm font-medium transition-colors">
          View All Feedback →
        </a>
      </div>

      <!-- KPI Cards -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="bg-indigo-50 border border-indigo-100 rounded-xl p-4 text-center">
          <ClipboardDocumentCheckIcon class="h-5 w-5 text-indigo-500 mx-auto mb-1" />
          <div class="text-2xl font-bold text-indigo-700">{{ total }}</div>
          <div class="text-xs text-indigo-500 font-semibold">Total Responses</div>
        </div>
        <div class="bg-emerald-50 border border-emerald-100 rounded-xl p-4 text-center">
          <ChatBubbleLeftRightIcon class="h-5 w-5 text-emerald-500 mx-auto mb-1" />
          <div class="text-2xl font-bold text-emerald-700">{{ thisMonth }}</div>
          <div class="text-xs text-emerald-500 font-semibold">This Month</div>
        </div>
        <div class="bg-amber-50 border border-amber-100 rounded-xl p-4 text-center">
          <StarIcon class="h-5 w-5 text-amber-500 mx-auto mb-1" />
          <div class="text-2xl font-bold text-amber-700">{{ overallAvg }} <span class="text-base">/ 5</span></div>
          <div class="text-xs text-amber-500 font-semibold">Avg SQD Score</div>
        </div>
        <div class="bg-slate-50 border border-slate-100 rounded-xl p-4 text-center">
          <UserGroupIcon class="h-5 w-5 text-slate-400 mx-auto mb-1" />
          <div class="text-2xl font-bold text-slate-700">{{ byModule.length }}</div>
          <div class="text-xs text-slate-500 font-semibold">Modules Covered</div>
        </div>
      </div>

      <!-- Charts row 1 -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <!-- Module breakdown -->
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
          <h3 class="text-sm font-semibold text-slate-700 mb-3">Responses by Module</h3>
          <div style="height:200px"><Doughnut :data="moduleChart" :options="donutOpts" /></div>
        </div>

        <!-- Monthly trend -->
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5 lg:col-span-2">
          <h3 class="text-sm font-semibold text-slate-700 mb-3">Monthly Trend — Last 12 Months</h3>
          <div style="height:200px"><Line :data="trendChart" :options="lineOpts" /></div>
        </div>
      </div>

      <!-- Charts row 2 -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <!-- SQD scores -->
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
          <h3 class="text-sm font-semibold text-slate-700 mb-1">SQD Avg Scores per Dimension</h3>
          <p class="text-xs text-slate-400 mb-3">Scale 1–5 (N/A excluded)</p>
          <div style="height:260px"><Bar :data="sqdChart" :options="barOpts" /></div>
        </div>

        <!-- Adjectival + client type -->
        <div class="space-y-4">
          <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
            <h3 class="text-sm font-semibold text-slate-700 mb-3">Adjectival Distribution</h3>
            <div style="height:140px"><Doughnut :data="adjectivalChart" :options="donutOpts" /></div>
          </div>
          <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
            <h3 class="text-sm font-semibold text-slate-700 mb-3">Client Type</h3>
            <div style="height:120px"><Doughnut :data="clientTypeChart" :options="donutOpts" /></div>
          </div>
        </div>
      </div>

      <!-- CC1 awareness -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
        <h3 class="text-sm font-semibold text-slate-700 mb-3">Citizen's Charter Awareness (CC1)</h3>
        <div style="height:180px"><Doughnut :data="cc1Chart" :options="donutOpts" /></div>
      </div>

    </div>
  </AdminLayout>
</template>
