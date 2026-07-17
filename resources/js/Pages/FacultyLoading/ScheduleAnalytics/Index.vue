<script setup>
import { computed, ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import axios from 'axios'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppButton from '@/Components/AppButton.vue'
import AppCard from '@/Components/AppCard.vue'
import AppModal from '@/Components/AppModal.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { Bar } from 'vue-chartjs'
import { Chart as ChartJS, CategoryScale, LinearScale, BarElement, Tooltip, Legend } from 'chart.js'
import { BRAND, withAlpha } from '@/Utils/chartTheme'
import {
  ArrowTopRightOnSquareIcon, ChartBarIcon, ExclamationTriangleIcon,
  InformationCircleIcon, PresentationChartLineIcon, ScaleIcon, UserGroupIcon,
} from '@heroicons/vue/24/outline'

ChartJS.register(CategoryScale, LinearScale, BarElement, Tooltip, Legend)

const props = defineProps({
  terms:  { type: Array, default: () => [] },
  termId: { type: Number, default: null },
  report: { type: Object, default: null },
})

const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday']
const DAY_SHORT = { Monday: 'Mon', Tuesday: 'Tue', Wednesday: 'Wed', Thursday: 'Thu', Friday: 'Fri' }

const selectedTerm = ref(props.termId)
const report = ref(props.report)
const loading = ref(false)
const methodologyOpen = ref(false)

async function switchTerm() {
  loading.value = true
  try {
    const { data } = await axios.get(route('faculty-loading.schedule-analytics.data'), {
      params: { term_id: selectedTerm.value },
    })
    report.value = data
  } finally {
    loading.value = false
  }
}

const hasData = computed(() =>
  (report.value?.faculty?.length ?? 0) > 0 || (report.value?.sections?.length ?? 0) > 0)

// ── Score presentation ───────────────────────────────────────────────────────

const GRADE_META = {
  excellent: { label: 'Excellent',       chip: 'bg-emerald-50 text-emerald-700 border-emerald-200' },
  good:      { label: 'Good',            chip: 'bg-indigo-50 text-indigo-700 border-indigo-200' },
  fair:      { label: 'Fair',            chip: 'bg-amber-50 text-amber-700 border-amber-200' },
  attention: { label: 'Needs attention', chip: 'bg-red-50 text-red-700 border-red-200' },
}
const gradeMeta = (g) => GRADE_META[g] ?? GRADE_META.fair

// SVG score ring geometry (r=26 → circumference ≈ 163.36)
const RING_C = 2 * Math.PI * 26
const ringOffset = (score) => RING_C * (1 - (score ?? 0) / 100)

// ── Utilization heatmap ──────────────────────────────────────────────────────

const heatMax = computed(() => {
  let max = 0
  for (const day of DAYS) {
    for (const v of Object.values(report.value?.campus?.utilization?.[day] ?? {})) max = Math.max(max, v)
  }
  return max
})

function heatCellStyle(day, hour) {
  const v = report.value?.campus?.utilization?.[day]?.[hour] ?? 0
  if (!v || !heatMax.value) return { backgroundColor: '#f8fafc' }
  return { backgroundColor: withAlpha(BRAND, 0.08 + 0.82 * (v / heatMax.value)) }
}

function heatCellText(day, hour) {
  const v = report.value?.campus?.utilization?.[day]?.[hour] ?? 0
  return v > heatMax.value * 0.55 ? 'text-white' : 'text-slate-600'
}

const fmtHour = (h) => `${h % 12 || 12}${h >= 12 ? 'PM' : 'AM'}`

// ── Faculty weekly-hours chart (single series — legend intentionally off) ────

const hoursChartData = computed(() => {
  const rows = [...(report.value?.faculty ?? [])].sort((a, b) => b.weekly_hours - a.weekly_hours)
  return {
    labels: rows.map((f) => f.name),
    datasets: [{
      data: rows.map((f) => f.weekly_hours),
      backgroundColor: withAlpha(BRAND, 0.85),
      hoverBackgroundColor: BRAND,
      borderRadius: 4,
      maxBarThickness: 14,
    }],
  }
})

const hoursChartOptions = {
  indexAxis: 'y',
  plugins: {
    legend: { display: false },
    tooltip: { callbacks: { label: (ctx) => ` ${ctx.parsed.x} h / week` } },
  },
  scales: {
    x: { beginAtZero: true, ticks: { callback: (v) => `${v}h` } },
    y: { ticks: { autoSkip: false } },
  },
}

const hoursChartHeight = computed(() =>
  Math.max(180, (report.value?.faculty?.length ?? 0) * 26 + 40))

// ── Tables / drill-down ──────────────────────────────────────────────────────

const expandedFaculty = ref(null)
const expandedSection = ref(null)

const dailyBarHeight = (minutes, maxMin) => `${maxMin ? Math.max(6, (minutes / maxMin) * 100) : 6}%`
const facultyDayMax = (f) => Math.max(...DAYS.map((d) => f.daily_minutes?.[d] ?? 0), 60)
const sectionDayMax = (s) => Math.max(...DAYS.map((d) => s.periods_per_day?.[d] ?? 0), 1)

const fmtHours = (min) => (min / 60).toFixed(1).replace(/\.0$/, '')
const pct = (v) => (v == null ? '—' : `${Math.round(v * 100)}%`)

const SEVERITY_META = {
  3: { label: 'High',   chip: 'bg-red-50 text-red-700 border-red-200' },
  2: { label: 'Medium', chip: 'bg-amber-50 text-amber-700 border-amber-200' },
  1: { label: 'Info',   chip: 'bg-slate-50 text-slate-600 border-slate-200' },
}

function issueLink(issue) {
  if (issue.code === 'load_above_mean' || issue.code === 'load_below_mean') {
    return route('faculty-loading.load-balance.index')
  }
  return issue.scope === 'faculty'
    ? route('faculty-loading.schedules.index', { faculty_id: issue.id, term_id: selectedTerm.value })
    : route('faculty-loading.schedules.index', { section_id: issue.id, term_id: selectedTerm.value })
}
</script>

<template>
  <Head title="Schedule Analytics" />
  <AdminLayout title="Schedule Analytics">
    <div class="space-y-5">

      <AppPageHeader title="Schedule Analytics"
        subtitle="How the current timetable is balanced across faculty and students — compactness, fatigue, fairness and daily load.">
        <template #actions>
          <AppButton variant="secondary" @click="methodologyOpen = true">
            <InformationCircleIcon class="h-4 w-4" /> Methodology
          </AppButton>
        </template>
      </AppPageHeader>

      <AppFilterBar>
        <select v-model="selectedTerm" :disabled="loading" @change="switchTerm"
          class="text-sm border border-slate-200 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
          <option v-for="t in terms" :key="t.id" :value="t.id">
            {{ t.label }}{{ t.is_current ? ' (current)' : '' }}
          </option>
        </select>
        <span v-if="loading" class="text-xs text-slate-400">Recomputing…</span>
      </AppFilterBar>

      <AppCard v-if="!hasData">
        <EmptyState title="Nothing to analyze yet"
          subtitle="No occupying class schedules were found for this term. Generate or plot schedules first, then come back."
          :icon="PresentationChartLineIcon" />
      </AppCard>

      <template v-else>

        <!-- ── Hero score tiles ─────────────────────────────────────────── -->
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">
          <AppCard v-for="tile in [
              { key: 'campus',  label: 'Campus Balance',  score: report.campus.score,         icon: ScaleIcon,       hint: 'Mean of both axes' },
              { key: 'faculty', label: 'Faculty Axis',    score: report.campus.faculty_score, icon: UserGroupIcon,   hint: report.campus.totals.faculty_count + ' faculty analyzed' },
              { key: 'student', label: 'Student Axis',    score: report.campus.section_score, icon: ChartBarIcon,    hint: report.campus.totals.section_count + ' sections analyzed' },
            ]" :key="tile.key">
            <div class="flex items-center gap-4">
              <div class="relative h-16 w-16 shrink-0">
                <svg viewBox="0 0 60 60" class="h-16 w-16 -rotate-90">
                  <circle cx="30" cy="30" r="26" fill="none" stroke="#eef2ff" stroke-width="6" />
                  <circle cx="30" cy="30" r="26" fill="none" :stroke="BRAND" stroke-width="6"
                    stroke-linecap="round" :stroke-dasharray="RING_C" :stroke-dashoffset="ringOffset(tile.score)" />
                </svg>
                <span class="absolute inset-0 flex items-center justify-center text-sm font-bold text-slate-800">
                  {{ tile.score ?? '—' }}
                </span>
              </div>
              <div class="min-w-0">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide flex items-center gap-1.5">
                  <component :is="tile.icon" class="h-3.5 w-3.5 text-indigo-400" /> {{ tile.label }}
                </p>
                <p class="text-[11px] text-slate-400 mt-1">{{ tile.hint }}</p>
              </div>
            </div>
          </AppCard>

          <AppCard>
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Load Fairness</p>
            <div class="mt-2 flex items-baseline gap-2">
              <span class="text-2xl font-bold text-slate-800">{{ report.campus.fairness.jain ?? '—' }}</span>
              <span class="text-[11px] text-slate-400">Jain index (1.00 = perfectly even)</span>
            </div>
            <p class="text-[11px] text-slate-400 mt-1.5">
              Roster mean {{ report.campus.fairness.mean_hours ?? '—' }} h/week ·
              variation {{ report.campus.fairness.cv == null ? '—' : Math.round(report.campus.fairness.cv * 100) + '%' }}
            </p>
          </AppCard>
        </div>

        <!-- ── Utilization heatmap ──────────────────────────────────────── -->
        <AppCard :padded="false" class="overflow-hidden">
          <div class="px-5 py-3 border-b border-slate-100">
            <h2 class="text-sm font-semibold text-slate-700">Campus Utilization</h2>
            <p class="text-xs text-slate-500 mt-0.5">
              Concurrent class sessions per hour — {{ report.campus.totals.class_sessions }} sessions,
              {{ report.campus.totals.scheduled_hours }} scheduled hours this week.
            </p>
          </div>
          <div class="p-5 overflow-x-auto">
            <div class="min-w-[560px]">
              <div class="grid gap-1" :style="{ gridTemplateColumns: `56px repeat(${report.campus.heat_hours.length}, 1fr)` }">
                <div></div>
                <div v-for="h in report.campus.heat_hours" :key="'h' + h"
                  class="text-center text-[10px] text-slate-400 font-medium">{{ fmtHour(h) }}</div>
                <template v-for="day in DAYS" :key="day">
                  <div class="flex items-center text-[11px] font-medium text-slate-500">{{ DAY_SHORT[day] }}</div>
                  <div v-for="h in report.campus.heat_hours" :key="day + h"
                    class="h-9 rounded-md flex items-center justify-center text-[10px] font-semibold transition-colors"
                    :class="heatCellText(day, h)" :style="heatCellStyle(day, h)"
                    :title="`${day} ${fmtHour(h)}–${fmtHour(h + 1)}: ${report.campus.utilization?.[day]?.[h] ?? 0} concurrent session(s)`">
                    {{ (report.campus.utilization?.[day]?.[h] ?? 0) || '' }}
                  </div>
                </template>
              </div>
              <div class="mt-3 flex items-center justify-end gap-1.5 text-[10px] text-slate-400">
                Fewer
                <span v-for="i in 5" :key="i" class="h-2.5 w-5 rounded-sm"
                  :style="{ backgroundColor: withAlpha(BRAND, 0.08 + 0.82 * (i / 5)) }"></span>
                More sessions
              </div>
            </div>
          </div>
        </AppCard>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
          <!-- ── Weekly teaching hours (ranked) ─────────────────────────── -->
          <AppCard :padded="false" class="overflow-hidden">
            <div class="px-5 py-3 border-b border-slate-100">
              <h2 class="text-sm font-semibold text-slate-700">Weekly Teaching Hours by Faculty</h2>
              <p class="text-xs text-slate-500 mt-0.5">
                Roster mean {{ report.campus.fairness.mean_hours ?? '—' }} h — bars far above or below it are fairness outliers.
              </p>
            </div>
            <div class="p-4" :style="{ height: hoursChartHeight + 'px' }">
              <Bar :data="hoursChartData" :options="hoursChartOptions" />
            </div>
          </AppCard>

          <!-- ── Needs attention feed ───────────────────────────────────── -->
          <AppCard :padded="false" class="overflow-hidden">
            <div class="px-5 py-3 border-b border-slate-100 flex items-center gap-2">
              <ExclamationTriangleIcon class="h-4 w-4 text-amber-500" />
              <h2 class="text-sm font-semibold text-slate-700">Needs Attention</h2>
            </div>
            <div v-if="!report.issues.length" class="px-5 py-10 text-center text-sm text-slate-400">
              No significant balance issues found — this timetable looks healthy.
            </div>
            <ul v-else class="divide-y divide-slate-50 max-h-[420px] overflow-y-auto">
              <li v-for="(issue, i) in report.issues" :key="i" class="px-5 py-3 flex items-start gap-3">
                <span :class="['mt-0.5 shrink-0 inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-semibold',
                  SEVERITY_META[issue.severity].chip]">
                  {{ SEVERITY_META[issue.severity].label }}
                </span>
                <div class="min-w-0 flex-1">
                  <p class="text-sm font-medium text-slate-700 truncate">
                    {{ issue.name }}
                    <span class="ml-1 text-[10px] font-semibold uppercase tracking-wide text-slate-400">{{ issue.scope }}</span>
                  </p>
                  <p class="text-xs text-slate-500 mt-0.5">{{ issue.detail }}</p>
                </div>
                <a :href="issueLink(issue)"
                  class="shrink-0 inline-flex items-center gap-1 text-xs font-medium text-indigo-600 hover:text-indigo-700">
                  Open <ArrowTopRightOnSquareIcon class="h-3 w-3" />
                </a>
              </li>
            </ul>
          </AppCard>
        </div>

        <!-- ── Faculty balance table ────────────────────────────────────── -->
        <AppCard :padded="false" class="overflow-hidden">
          <div class="px-5 py-3 border-b border-slate-100">
            <h2 class="text-sm font-semibold text-slate-700">Faculty Balance</h2>
            <p class="text-xs text-slate-500 mt-0.5">Sorted worst-first. Click a row for the daily breakdown and findings.</p>
          </div>
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-50 text-sm">
              <thead>
                <tr class="text-xs text-slate-400 uppercase tracking-wide bg-slate-50">
                  <th class="px-4 py-2.5 text-left">Faculty</th>
                  <th class="px-4 py-2.5 text-center">Score</th>
                  <th class="px-4 py-2.5 text-center">Hrs/Week</th>
                  <th class="px-4 py-2.5 text-center">Days</th>
                  <th class="px-4 py-2.5 text-center">Preps</th>
                  <th class="px-4 py-2.5 text-center">Compactness</th>
                  <th class="px-4 py-2.5 text-center">Longest Streak</th>
                  <th class="px-4 py-2.5 text-center">Units</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-50">
                <template v-for="f in report.faculty" :key="f.id">
                  <tr class="hover:bg-slate-50/60 cursor-pointer"
                    @click="expandedFaculty = expandedFaculty === f.id ? null : f.id">
                    <td class="px-4 py-2.5 font-medium text-slate-700">{{ f.name }}</td>
                    <td class="px-4 py-2.5 text-center">
                      <span :class="['inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-semibold', gradeMeta(f.grade).chip]">
                        {{ f.score }} · {{ gradeMeta(f.grade).label }}
                      </span>
                    </td>
                    <td class="px-4 py-2.5 text-center tabular-nums text-slate-600">{{ f.weekly_hours }}</td>
                    <td class="px-4 py-2.5 text-center text-slate-600">{{ f.teaching_days }}</td>
                    <td class="px-4 py-2.5 text-center text-slate-600">{{ f.preps }}</td>
                    <td class="px-4 py-2.5 text-center tabular-nums text-slate-600">{{ pct(f.compactness) }}</td>
                    <td class="px-4 py-2.5 text-center tabular-nums text-slate-600">{{ fmtHours(f.streak_max_min) }}h</td>
                    <td class="px-4 py-2.5 text-center tabular-nums text-slate-600">{{ f.units || '—' }}</td>
                  </tr>
                  <tr v-if="expandedFaculty === f.id" class="bg-slate-50/40">
                    <td colspan="8" class="px-6 py-4">
                      <div class="flex flex-col md:flex-row gap-6">
                        <div>
                          <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-2">Teaching hours per day</p>
                          <div class="flex items-end gap-2 h-20">
                            <div v-for="day in DAYS" :key="day" class="flex flex-col items-center gap-1 w-10">
                              <div class="w-full h-14 bg-slate-100 rounded flex items-end overflow-hidden">
                                <div class="w-full rounded-t" :style="{
                                  height: dailyBarHeight(f.daily_minutes?.[day] ?? 0, facultyDayMax(f)),
                                  backgroundColor: withAlpha(BRAND, 0.8),
                                }"></div>
                              </div>
                              <span class="text-[10px] text-slate-400">{{ DAY_SHORT[day] }}</span>
                              <span class="text-[10px] font-medium text-slate-500 tabular-nums">{{ fmtHours(f.daily_minutes?.[day] ?? 0) }}h</span>
                            </div>
                          </div>
                        </div>
                        <div class="flex-1 min-w-0">
                          <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-2">Findings</p>
                          <p v-if="!f.findings.length" class="text-xs text-slate-400">No issues — well balanced.</p>
                          <ul v-else class="space-y-1.5">
                            <li v-for="(fd, i) in f.findings" :key="i" class="flex items-start gap-2 text-xs text-slate-600">
                              <span :class="['mt-px shrink-0 inline-flex rounded-full border px-1.5 py-0 text-[10px] font-semibold', SEVERITY_META[fd.severity].chip]">
                                {{ SEVERITY_META[fd.severity].label }}
                              </span>
                              {{ fd.detail }}
                            </li>
                          </ul>
                          <p class="text-[11px] text-slate-400 mt-2.5">
                            {{ f.non_teaching_hours }}h non-teaching blocks ·
                            {{ fmtHours(f.idle_min) }}h idle gaps/week
                          </p>
                        </div>
                      </div>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
        </AppCard>

        <!-- ── Section balance table ────────────────────────────────────── -->
        <AppCard :padded="false" class="overflow-hidden">
          <div class="px-5 py-3 border-b border-slate-100">
            <h2 class="text-sm font-semibold text-slate-700">Section (Student) Balance</h2>
            <p class="text-xs text-slate-500 mt-0.5">Daily load shape per section — sorted worst-first.</p>
          </div>
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-50 text-sm">
              <thead>
                <tr class="text-xs text-slate-400 uppercase tracking-wide bg-slate-50">
                  <th class="px-4 py-2.5 text-left">Section</th>
                  <th class="px-4 py-2.5 text-center">Score</th>
                  <th class="px-4 py-2.5 text-center">Week Shape</th>
                  <th class="px-4 py-2.5 text-center">Periods</th>
                  <th class="px-4 py-2.5 text-center">σ / day</th>
                  <th class="px-4 py-2.5 text-center">Majors after 1 PM</th>
                  <th class="px-4 py-2.5 text-center">Free Periods</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-50">
                <template v-for="s in report.sections" :key="s.id">
                  <tr class="hover:bg-slate-50/60 cursor-pointer"
                    @click="expandedSection = expandedSection === s.id ? null : s.id">
                    <td class="px-4 py-2.5 font-medium text-slate-700">
                      <span class="text-slate-400 text-xs mr-1">G{{ s.grade_level }}</span>{{ s.name }}
                    </td>
                    <td class="px-4 py-2.5 text-center">
                      <span :class="['inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-semibold', gradeMeta(s.grade).chip]">
                        {{ s.score }} · {{ gradeMeta(s.grade).label }}
                      </span>
                    </td>
                    <td class="px-4 py-2.5">
                      <div class="flex items-end justify-center gap-0.5 h-6">
                        <div v-for="day in DAYS" :key="day" class="w-2 rounded-sm"
                          :style="{
                            height: dailyBarHeight((s.periods_per_day?.[day] ?? 0) * 60, sectionDayMax(s) * 60),
                            backgroundColor: withAlpha(BRAND, 0.7),
                          }"
                          :title="`${day}: ${s.periods_per_day?.[day] ?? 0} period(s)`"></div>
                      </div>
                    </td>
                    <td class="px-4 py-2.5 text-center tabular-nums text-slate-600">{{ s.periods_used }}/{{ s.capacity }}</td>
                    <td class="px-4 py-2.5 text-center tabular-nums text-slate-600">{{ s.std_dev }}</td>
                    <td class="px-4 py-2.5 text-center tabular-nums text-slate-600">{{ pct(s.heavy_pm_share) }}</td>
                    <td class="px-4 py-2.5 text-center tabular-nums"
                      :class="s.free_periods > 4 ? 'text-amber-600 font-semibold' : 'text-slate-600'">
                      {{ s.free_periods }}
                    </td>
                  </tr>
                  <tr v-if="expandedSection === s.id" class="bg-slate-50/40">
                    <td colspan="7" class="px-6 py-4">
                      <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-2">Findings</p>
                      <p v-if="!s.findings.length" class="text-xs text-slate-400">No issues — well balanced.</p>
                      <ul v-else class="space-y-1.5">
                        <li v-for="(fd, i) in s.findings" :key="i" class="flex items-start gap-2 text-xs text-slate-600">
                          <span :class="['mt-px shrink-0 inline-flex rounded-full border px-1.5 py-0 text-[10px] font-semibold', SEVERITY_META[fd.severity].chip]">
                            {{ SEVERITY_META[fd.severity].label }}
                          </span>
                          {{ fd.detail }}
                        </li>
                      </ul>
                      <p class="text-[11px] text-slate-400 mt-2.5">
                        {{ s.repeat_extra }} same-day subject repeat(s) · {{ s.spacing_violations }} spacing violation(s)
                      </p>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
        </AppCard>
      </template>
    </div>

    <!-- ── Methodology ──────────────────────────────────────────────────── -->
    <AppModal :show="methodologyOpen" title="How Balance Scores are computed" size="lg" @close="methodologyOpen = false">
      <div class="space-y-4 text-sm text-slate-600">
        <p>
          Every faculty member and section starts at <strong>100</strong> and loses points per issue below.
          90+ is Excellent, 75+ Good, 60+ Fair, below 60 Needs Attention. The campus score is the mean of
          the faculty and student axis averages.
        </p>
        <div>
          <p class="font-semibold text-slate-700 mb-1.5">Faculty axis</p>
          <ul class="list-disc pl-5 space-y-1 text-xs">
            <li><strong>−15</strong> teaching streak beyond 3 contiguous hours (gaps under 15 min don't break a streak)</li>
            <li><strong>−10 per day (max −20)</strong> any day with more than 5 teaching hours</li>
            <li><strong>−10</strong> daily hours vary by more than ±1.5h across teaching days</li>
            <li><strong>−10 / −20</strong> compactness below 70% / 50% — idle gaps between classes beyond one 60-min midday lunch</li>
            <li><strong>−5 / −10</strong> 4–5 / 6+ distinct subject preparations</li>
            <li><strong>−10</strong> 12+ weekly hours compressed into 3 or fewer days</li>
            <li><strong>−10 / −5</strong> weekly hours more than 25% above / below the roster mean (5+ teaching faculty required)</li>
          </ul>
        </div>
        <div>
          <p class="font-semibold text-slate-700 mb-1.5">Student (section) axis</p>
          <ul class="list-disc pl-5 space-y-1 text-xs">
            <li><strong>−10 / −20</strong> daily period counts spread unevenly (σ above 1.5 / 2.5)</li>
            <li><strong>−10 / −20</strong> over 40% / 60% of Math–Science minutes after 1 PM (heavy subjects matched by code: MATH, BIO, CHEM, PHY, SCI, CS, STEM)</li>
            <li><strong>−5 each (max −15)</strong> same subject doubled up on one day</li>
            <li><strong>−5 each (max −15)</strong> multi-session subjects sharing days instead of spreading</li>
            <li><strong>−10</strong> more than 4 weekly periods still unscheduled</li>
          </ul>
        </div>
        <div>
          <p class="font-semibold text-slate-700 mb-1.5">Fairness</p>
          <p class="text-xs">
            <strong>Jain's index</strong> — (Σx)² / (n·Σx²) over weekly teaching hours: 1.00 means a perfectly even
            roster; lower values mean load concentrates on fewer people. TBA/vacant placeholders are excluded.
          </p>
        </div>
        <p class="text-xs text-slate-400">
          Analytics cover active and tentative schedules for the selected term. Cross-section electives count toward
          faculty metrics exactly; their student-side impact can't be attributed to one section and is not counted there.
        </p>
      </div>
    </AppModal>
  </AdminLayout>
</template>
