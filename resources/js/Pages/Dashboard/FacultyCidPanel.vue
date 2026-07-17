<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { Doughnut, Bar } from 'vue-chartjs'
// Importing the theme registers Chart.js controllers/elements and applies the
// brand-wide defaults (idempotent) — no local ChartJS.register needed.
import { BRAND, seriesColor, withAlpha } from '@/Utils/chartTheme'
import { useCountUp } from '@/Composables/useCountUp.js'
import {
  AcademicCapIcon,
  CalendarDaysIcon,
  ChartPieIcon,
  CheckBadgeIcon,
  ChevronRightIcon,
  ClipboardDocumentListIcon,
  ClockIcon,
  RectangleStackIcon,
  ScaleIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  data: { type: Object, required: true },
})

function fmtUnits(v) {
  return Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

const cards = computed(() => props.data.cards || {})

const completionPct = computed(() => {
  const total = cards.value.records_total || 0
  if (!total) return 0
  return Math.round((cards.value.records_checked / total) * 100)
})

const todayLabel = computed(() =>
  new Date().toLocaleDateString('en-PH', { weekday: 'long' })
)

const kpis = computed(() => [
  {
    label: 'Teaching Load',
    value: Number(cards.value.total_units ?? 0),
    display: fmtUnits(cards.value.total_units),
    sub: cards.value.full_load_threshold
      ? `of ${fmtUnits(cards.value.full_load_threshold)} full load`
      : 'total units',
    badge: cards.value.is_overload ? `+${fmtUnits(cards.value.overload_units)} overload` : null,
    icon: ScaleIcon,
  },
  {
    label: 'Sections',
    value: Number(cards.value.sections_count ?? 0),
    display: String(cards.value.sections_count ?? 0),
    sub: 'Sections handled',
    icon: RectangleStackIcon,
  },
  {
    label: 'Class Records',
    value: Number(cards.value.records_checked ?? 0),
    display: `${cards.value.records_checked ?? 0}/${cards.value.records_total ?? 0}`,
    sub: `${completionPct.value}% checked`,
    icon: CheckBadgeIcon,
  },
  {
    label: 'Assessments',
    value: Number(cards.value.assessments_week ?? 0),
    display: String(cards.value.assessments_week ?? 0),
    sub: 'Logged this week',
    icon: ClipboardDocumentListIcon,
  },
  {
    label: 'Classes Today',
    value: Number(cards.value.classes_today ?? 0),
    display: String(cards.value.classes_today ?? 0),
    sub: todayLabel.value,
    icon: AcademicCapIcon,
  },
])

const { values: animatedValues } = useCountUp(() => kpis.value.map(k => k.value))

// ── Load distribution donut ────────────────────────────────────────────────
const loadRows = computed(() => props.data.loadDistribution || [])
const totalUnits = computed(() => loadRows.value.reduce((s, r) => s + Number(r.units), 0))

const loadChartData = computed(() => ({
  labels: loadRows.value.map(r => r.label),
  datasets: [{
    data: loadRows.value.map(r => Number(r.units)),
    backgroundColor: loadRows.value.map((_, i) => seriesColor(i)),
  }],
}))

const loadChartOptions = {
  cutout: '68%',
  plugins: {
    legend: { position: 'bottom' },
    tooltip: {
      callbacks: {
        label: (ctx) => ` ${ctx.label}: ${Number(ctx.parsed).toFixed(2)} units`,
      },
    },
  },
}

// ── Assessments by quarter bar ─────────────────────────────────────────────
const quarterRows = computed(() => props.data.assessmentByQuarter || [])

const quarterChartData = computed(() => ({
  labels: quarterRows.value.map(r => r.label),
  datasets: [{
    label: 'Assessments',
    data: quarterRows.value.map(r => r.count),
    backgroundColor: withAlpha(BRAND, 0.85),
    hoverBackgroundColor: BRAND,
  }],
}))

const quarterChartOptions = {
  plugins: { legend: { display: false } },
  scales: {
    y: { beginAtZero: true, ticks: { precision: 0 } },
  },
}

const hasAssessments = computed(() => quarterRows.value.some(r => r.count > 0))

// ── Supporting panels ──────────────────────────────────────────────────────
const attendance = computed(() => props.data.weeklyAttendance || [])
const schedule = computed(() => props.data.todaySchedule || [])
const records = computed(() => props.data.classRecords || [])
</script>

<template>
  <div class="space-y-5">
    <!-- CID KPI tiles — replace the generic dashboard stat cards for faculty -->
    <section class="dash-section grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5" style="--stagger: 1">
      <div
        v-for="(kpi, index) in kpis"
        :key="kpi.label"
        class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70 transition duration-200 hover:-translate-y-0.5 hover:shadow-md"
      >
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <p class="text-xs font-semibold uppercase text-slate-500">{{ kpi.label }}</p>
            <p class="mt-2 text-3xl font-bold tracking-normal text-slate-900 tabular-nums">
              {{ index === 0 ? fmtUnits(animatedValues[index] ?? kpi.value) : (kpi.display) }}
            </p>
            <p class="mt-1 truncate text-xs text-slate-500">{{ kpi.sub }}</p>
            <span
              v-if="kpi.badge"
              class="mt-2 inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-700 ring-1 ring-amber-100"
            >
              {{ kpi.badge }}
            </span>
          </div>
          <div class="rounded-lg bg-indigo-100 p-2 text-indigo-700">
            <component :is="kpi.icon" class="h-5 w-5" />
          </div>
        </div>
      </div>
    </section>

    <!-- Analytics row: load distribution · assessment activity · attendance -->
    <section class="dash-section grid grid-cols-1 gap-5 lg:grid-cols-3" style="--stagger: 2">
      <div class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200/70">
        <div class="flex items-center gap-2.5 border-b border-slate-100 px-4 py-3">
          <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-indigo-50">
            <ChartPieIcon class="h-4 w-4 text-indigo-600" />
          </span>
          <div class="min-w-0">
            <h2 class="font-heading text-sm font-semibold text-slate-900">Load Distribution</h2>
            <p class="truncate text-xs text-slate-500">Units by assignment type</p>
          </div>
        </div>
        <div class="p-4">
          <div v-if="loadRows.length" class="relative h-56">
            <Doughnut :data="loadChartData" :options="loadChartOptions" />
            <div class="pointer-events-none absolute inset-x-0 top-[42%] -translate-y-1/2 text-center">
              <p class="text-2xl font-bold text-slate-900 tabular-nums">{{ fmtUnits(totalUnits) }}</p>
              <p class="text-xs text-slate-500">total units</p>
            </div>
          </div>
          <p v-else class="flex h-56 items-center justify-center text-center text-sm text-slate-400">
            No load assignments recorded yet.
          </p>
        </div>
      </div>

      <div class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200/70">
        <div class="flex items-center gap-2.5 border-b border-slate-100 px-4 py-3">
          <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-indigo-50">
            <ClipboardDocumentListIcon class="h-4 w-4 text-indigo-600" />
          </span>
          <div class="min-w-0">
            <h2 class="font-heading text-sm font-semibold text-slate-900">Assessment Activity</h2>
            <p class="truncate text-xs text-slate-500">Assessments logged per quarter</p>
          </div>
        </div>
        <div class="p-4">
          <div v-if="hasAssessments" class="h-56">
            <Bar :data="quarterChartData" :options="quarterChartOptions" />
          </div>
          <p v-else class="flex h-56 items-center justify-center text-center text-sm text-slate-400">
            No assessments logged yet this school year.
          </p>
        </div>
      </div>

      <div class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200/70">
        <div class="flex items-center gap-2.5 border-b border-slate-100 px-4 py-3">
          <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-indigo-50">
            <CalendarDaysIcon class="h-4 w-4 text-indigo-600" />
          </span>
          <div class="min-w-0">
            <h2 class="font-heading text-sm font-semibold text-slate-900">My Attendance</h2>
            <p class="truncate text-xs text-slate-500">Classroom tap-ins this week</p>
          </div>
        </div>
        <div class="flex h-56 flex-col justify-center gap-2.5 p-4">
          <div
            v-for="day in attendance"
            :key="day.date"
            class="flex items-center gap-3"
          >
            <span class="w-9 shrink-0 text-xs font-semibold uppercase text-slate-500">{{ day.label }}</span>
            <div class="h-2.5 flex-1 overflow-hidden rounded-full bg-slate-100">
              <div
                class="h-full rounded-full transition-all"
                :class="day.present ? 'bg-indigo-500' : 'bg-transparent'"
                :style="{ width: day.present ? '100%' : '0%' }"
              />
            </div>
            <span
              class="w-14 shrink-0 text-right text-xs font-medium"
              :class="day.present ? 'text-indigo-600' : (day.is_past ? 'text-slate-400' : 'text-slate-300')"
            >
              {{ day.present ? 'Present' : (day.is_past ? 'No tap' : '—') }}
            </span>
          </div>
        </div>
      </div>
    </section>

    <!-- Today's schedule + class-record completion -->
    <section class="dash-section grid grid-cols-1 gap-5 lg:grid-cols-2" style="--stagger: 3">
      <div class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200/70">
        <div class="flex items-center gap-2.5 border-b border-slate-100 px-4 py-3">
          <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-indigo-50">
            <ClockIcon class="h-4 w-4 text-indigo-600" />
          </span>
          <div class="min-w-0">
            <h2 class="font-heading text-sm font-semibold text-slate-900">Today's Classes</h2>
            <p class="truncate text-xs text-slate-500">{{ todayLabel }} teaching schedule</p>
          </div>
        </div>
        <div v-if="schedule.length" class="divide-y divide-slate-100">
          <div v-for="cls in schedule" :key="cls.id" class="flex items-center gap-3 px-4 py-3">
            <div class="w-20 shrink-0 text-right">
              <p class="text-sm font-semibold text-slate-900 tabular-nums">{{ cls.start || '—' }}</p>
              <p class="text-xs text-slate-400 tabular-nums">{{ cls.end || '' }}</p>
            </div>
            <span class="h-8 w-px shrink-0 bg-slate-100" />
            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-semibold text-slate-900">{{ cls.title }}</p>
              <p class="truncate text-xs text-slate-500">
                {{ [cls.section, cls.room].filter(Boolean).join(' · ') || '—' }}
              </p>
            </div>
          </div>
        </div>
        <p v-else class="px-4 py-14 text-center text-sm text-slate-400">
          No classes scheduled today.
        </p>
      </div>

      <div class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200/70">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <div class="flex min-w-0 items-center gap-2.5">
            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-indigo-50">
              <CheckBadgeIcon class="h-4 w-4 text-indigo-600" />
            </span>
            <div class="min-w-0">
              <h2 class="font-heading text-sm font-semibold text-slate-900">My Class Records</h2>
              <p class="truncate text-xs text-slate-500">Checking status this school year</p>
            </div>
          </div>
          <span class="shrink-0 text-xs font-semibold text-slate-500 tabular-nums">{{ completionPct }}%</span>
        </div>

        <div v-if="records.length">
          <div class="px-4 pt-3">
            <div class="h-1.5 overflow-hidden rounded-full bg-slate-100">
              <div class="h-full rounded-full bg-indigo-500 transition-all" :style="{ width: completionPct + '%' }" />
            </div>
          </div>
          <div class="mt-1 max-h-64 divide-y divide-slate-100 overflow-y-auto">
            <Link
              v-for="rec in records"
              :key="rec.id"
              :href="rec.url"
              class="group flex items-center gap-3 px-4 py-2.5 transition hover:bg-slate-50"
            >
              <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-medium text-slate-900">{{ rec.subject }}</p>
                <p class="truncate text-xs text-slate-500">{{ rec.section }}</p>
              </div>
              <span
                class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium ring-1"
                :class="rec.checked
                  ? 'bg-emerald-50 text-emerald-700 ring-emerald-100'
                  : 'bg-amber-50 text-amber-700 ring-amber-100'"
              >
                {{ rec.checked ? 'Checked' : 'Pending' }}
              </span>
              <ChevronRightIcon class="h-4 w-4 shrink-0 text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-indigo-400" />
            </Link>
          </div>
        </div>
        <p v-else class="px-4 py-14 text-center text-sm text-slate-400">
          No class records for the current school year.
        </p>
      </div>
    </section>
  </div>
</template>
