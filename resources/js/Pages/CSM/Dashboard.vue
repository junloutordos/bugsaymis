<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppCard from '@/Components/AppCard.vue'
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
import { BRAND, STATUS, OTHER, seriesColor, gradientFill } from '@/Utils/chartTheme'
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
    'Excellent': STATUS.success, 'Very Good': BRAND,
    'Satisfactory': '#0284C7', 'Fair': STATUS.warning, 'Poor': STATUS.danger,
  }
  return map[adj] ?? OTHER
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

const moduleChart = computed(() => ({
  labels: props.byModule.map(m => m.label),
  datasets: [{ data: props.byModule.map(m => m.count), backgroundColor: props.byModule.map((_, i) => seriesColor(i)) }],
}))

const sqdChart = computed(() => ({
  labels: Object.keys(props.sqdAvgs).map(k => sqdLabel(k)),
  datasets: [{
    label: 'Avg Score (1–5)',
    data: Object.values(props.sqdAvgs),
    backgroundColor: Object.values(props.sqdAvgs).map(v =>
      v >= 4.5 ? STATUS.success : v >= 3.5 ? BRAND : v >= 2.5 ? STATUS.warning : STATUS.danger
    ),
  }],
}))

const trendChart = computed(() => ({
  labels: props.monthlyTrend.map(m => m.month),
  datasets: [{
    label: 'Responses',
    data: props.monthlyTrend.map(m => m.count),
    borderColor: BRAND,
    backgroundColor: gradientFill(BRAND),
    fill: true,
  }],
}))

const clientTypeChart = computed(() => {
  const entries = Object.entries(props.byClientType)
  return {
    labels: entries.map(([k]) => k.charAt(0).toUpperCase() + k.slice(1)),
    datasets: [{ data: entries.map(([,v]) => v), backgroundColor: entries.map((_, i) => seriesColor(i)) }],
  }
})

const adjectivalChart = computed(() => {
  const entries = Object.entries(props.adjectivalDist)
  return {
    labels: entries.map(([k]) => k),
    datasets: [{
      data: entries.map(([,v]) => v),
      backgroundColor: entries.map(([k]) => adjectivalColor(k)),
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
    datasets: [{ data: entries.map(([,v]) => v), backgroundColor: [STATUS.success, BRAND, STATUS.warning, STATUS.danger] }],
  }
})

const donutOpts = { plugins: { legend: { position: 'right' } } }
const barOpts   = { indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { min: 0, max: 5, ticks: { precision: 1 } }, y: { ticks: { font: { size: 10 } } } } }
const lineOpts  = { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }

const starRating = computed(() => Math.round((props.overallAvg / 5) * 5 * 10) / 10)
</script>

<template>
  <Head title="CSM Feedback Dashboard" />
  <AdminLayout title="CSM Feedback Dashboard">
    <div class="space-y-6">

      <AppPageHeader title="Client Satisfaction Dashboard" subtitle="Aggregated feedback from all General Services modules">
        <template #actions>
          <AppButton as="link" variant="secondary" :href="route('csm.list')">View All Feedback →</AppButton>
        </template>
      </AppPageHeader>

      <!-- KPI Cards -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="bg-indigo-50 border border-indigo-100 rounded-xl p-4 text-center">
          <ClipboardDocumentCheckIcon class="h-5 w-5 text-indigo-500 mx-auto mb-1" />
          <div class="text-2xl font-bold text-indigo-700">{{ total }}</div>
          <div class="text-xs text-indigo-500 font-semibold">Total Responses</div>
        </div>
        <div class="bg-success-50 border border-success-100 rounded-xl p-4 text-center">
          <ChatBubbleLeftRightIcon class="h-5 w-5 text-success-600 mx-auto mb-1" />
          <div class="text-2xl font-bold text-success-700">{{ thisMonth }}</div>
          <div class="text-xs text-success-600 font-semibold">This Month</div>
        </div>
        <div class="bg-warning-50 border border-warning-100 rounded-xl p-4 text-center">
          <StarIcon class="h-5 w-5 text-warning-600 mx-auto mb-1" />
          <div class="text-2xl font-bold text-warning-700">{{ overallAvg }} <span class="text-base">/ 5</span></div>
          <div class="text-xs text-warning-600 font-semibold">Avg SQD Score</div>
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
        <AppCard title="Responses by Module">
          <div style="height:200px"><Doughnut :data="moduleChart" :options="donutOpts" /></div>
        </AppCard>

        <!-- Monthly trend -->
        <AppCard title="Monthly Trend — Last 12 Months" class="lg:col-span-2">
          <div style="height:200px"><Line :data="trendChart" :options="lineOpts" /></div>
        </AppCard>
      </div>

      <!-- Charts row 2 -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <!-- SQD scores -->
        <AppCard title="SQD Avg Scores per Dimension" subtitle="Scale 1–5 (N/A excluded)">
          <div style="height:260px"><Bar :data="sqdChart" :options="barOpts" /></div>
        </AppCard>

        <!-- Adjectival + client type -->
        <div class="space-y-4">
          <AppCard title="Adjectival Distribution">
            <div style="height:140px"><Doughnut :data="adjectivalChart" :options="donutOpts" /></div>
          </AppCard>
          <AppCard title="Client Type">
            <div style="height:120px"><Doughnut :data="clientTypeChart" :options="donutOpts" /></div>
          </AppCard>
        </div>
      </div>

      <!-- CC1 awareness -->
      <AppCard title="Citizen's Charter Awareness (CC1)">
        <div style="height:180px"><Doughnut :data="cc1Chart" :options="donutOpts" /></div>
      </AppCard>

    </div>
  </AdminLayout>
</template>
