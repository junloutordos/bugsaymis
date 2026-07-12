<script setup>
import { ref, computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppCard from '@/Components/AppCard.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppButton from '@/Components/AppButton.vue'
import AppModal from '@/Components/AppModal.vue'
import {
  ArrowPathIcon,
  CheckCircleIcon,
  ExclamationTriangleIcon,
  XCircleIcon,
  InformationCircleIcon,
  ClockIcon,
  ArrowTopRightOnSquareIcon,
  CpuChipIcon,
  LinkIcon,
  ChevronDownIcon,
  ChevronUpIcon,
  ArrowRightIcon,
} from '@heroicons/vue/24/outline'
import { StarIcon as StarSolid } from '@heroicons/vue/24/solid'
import { StarIcon as StarOutline } from '@heroicons/vue/24/outline'
import {
  Chart as ChartJS,
  RadialLinearScale,
  PointElement,
  LineElement,
  Filler,
  CategoryScale,
  LinearScale,
  BarElement,
  Tooltip,
  Legend,
} from 'chart.js'
import { Radar, Bar } from 'vue-chartjs'
import { BRAND, withAlpha } from '@/Utils/chartTheme'
import { useCountUp } from '@/Composables/useCountUp.js'

ChartJS.register(
  RadialLinearScale, PointElement, LineElement, Filler,
  CategoryScale, LinearScale, BarElement,
  Tooltip, Legend
)

const props = defineProps({
  modules:            { type: Array,  default: () => [] },
  summary:            { type: Object, default: () => ({}) },
  integrations:       { type: Array,  default: () => [] },
  categoryLabels:     { type: Object, default: () => ({}) },
  maturityLabels:     { type: Object, default: () => ({}) },
  maturityDimensions: { type: Object, default: () => ({}) },
  cachedAt:           { type: String, default: null },
})

const modules      = ref(props.modules)
const summary      = ref(props.summary)
const integrations = ref(props.integrations)
const cachedAt     = ref(props.cachedAt)

const { values: totalValues } = useCountUp(() => [summary.value.total ?? 0])
const totalValue = computed(() => totalValues.value[0] ?? summary.value.total)

const refreshing      = ref(false)
const selectedModule  = ref(null)
const showDetailModal = ref(false)
const activeTab       = ref('health')

const metricsLoading = ref(false)
const metricData     = ref(null)

const filterCategory = ref('all')
const filterHealth   = ref('all')
const filterMaturity = ref('all')

const scoringDimension = ref(null)
const dimensionForm    = ref({ criteria_checks: [], notes: '' })
const savingDimension  = ref(false)
const settingsForm     = ref({ version: '1.0.0', sla_hours: null, notes: '', owner_id: null })
const savingSettings   = ref(false)

const healthConfig = {
  healthy:  { label: 'Healthy',  badgeColor: 'green', icon: CheckCircleIcon,        iconClass: 'text-emerald-500' },
  idle:     { label: 'Idle',     badgeColor: 'slate', icon: ClockIcon,              iconClass: 'text-slate-400'   },
  degraded: { label: 'Degraded', badgeColor: 'amber', icon: ExclamationTriangleIcon, iconClass: 'text-amber-500'  },
  critical: { label: 'Critical', badgeColor: 'red',   icon: XCircleIcon,            iconClass: 'text-red-500'     },
  info:     { label: 'Info',     badgeColor: 'slate', icon: InformationCircleIcon,  iconClass: 'text-slate-400'   },
}

const tabs = [
  { key: 'health',     label: 'Technical Health' },
  { key: 'usage',      label: 'Usage' },
  { key: 'operations', label: 'Operations' },
  { key: 'maturity',   label: 'Maturity' },
]

const categories = computed(() => {
  const seen = new Set()
  return modules.value.reduce((acc, m) => {
    if (!seen.has(m.category)) {
      seen.add(m.category)
      acc.push({ key: m.category, label: props.categoryLabels[m.category] ?? m.category })
    }
    return acc
  }, [])
})

const filteredModules = computed(() =>
  modules.value.filter(m => {
    if (filterCategory.value !== 'all' && m.category !== filterCategory.value) return false
    if (filterHealth.value   !== 'all' && m.health_status !== filterHealth.value) return false
    if (filterMaturity.value !== 'all' && String(m.maturity) !== filterMaturity.value) return false
    return true
  })
)

const cachedAtFormatted = computed(() =>
  cachedAt.value
    ? new Intl.DateTimeFormat('en-PH', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Asia/Manila' }).format(new Date(cachedAt.value))
    : null
)

const radarChartData = computed(() => {
  const radar = metricData.value?.maturity?.radar
  if (!radar) return null
  return {
    labels: radar.labels,
    datasets: [{
      label: selectedModule.value?.name ?? 'Module',
      data: radar.data,
      backgroundColor: withAlpha(BRAND, 0.15),
      borderColor: BRAND,
      pointBackgroundColor: BRAND,
      pointBorderColor: '#fff',
      pointHoverBackgroundColor: '#fff',
      pointHoverBorderColor: BRAND,
    }],
  }
})

const radarChartOptions = {
  scales: {
    r: {
      min: 0,
      max: 100,
      ticks: { display: false, stepSize: 20 },
      pointLabels: { font: { size: 10 } },
    },
  },
  plugins: { legend: { display: false } },
}

const peakChartData = computed(() => {
  const peak = metricData.value?.metrics?.usage?.peak_hours?.value
  if (!Array.isArray(peak)) return null

  const labels = peak.map((_, i) => {
    if (i === 0) return '12am'
    if (i < 12) return `${i}am`
    if (i === 12) return '12pm'
    return `${i - 12}pm`
  })

  return {
    labels,
    datasets: [{ data: peak, backgroundColor: BRAND, borderRadius: 2, barThickness: 'flex' }],
  }
})

const peakChartOptions = {
  plugins: {
    legend: { display: false },
    tooltip: { callbacks: { label: ctx => `${ctx.raw} records at ${ctx.label}` } },
  },
  scales: {
    x: { display: false },
    y: { display: false, beginAtZero: true },
  },
}

function formatDate(val) {
  if (!val) return '—'
  return new Intl.DateTimeFormat('en-PH', { dateStyle: 'medium', timeZone: 'Asia/Manila' }).format(new Date(val))
}

function formatNumber(val) {
  if (val === null || val === undefined) return '—'
  return Number(val).toLocaleString('en-PH')
}

function integrationLabel(key) {
  return integrations.value.find(i => i.key === key)?.label ?? key
}

function safeRoute(routeName) {
  if (!routeName) return null
  try { return route(routeName) } catch { return null }
}

function statusDotClass(status) {
  return {
    ok:    'bg-emerald-500',
    warn:  'bg-amber-500',
    error: 'bg-red-500',
    stub:  'bg-slate-300',
  }[status] ?? 'bg-slate-300'
}

function pingDotClass(status) {
  return {
    healthy:  'bg-emerald-500',
    degraded: 'bg-amber-400',
    critical: 'bg-red-500',
    idle:     'bg-slate-300',
    info:     'bg-sky-400',
  }[status] ?? 'bg-slate-200'
}

function dimensionScore(dimKey) {
  return metricData.value?.maturity?.scores?.[dimKey]?.score ?? 0
}

function dimensionScoredAt(dimKey) {
  const scored = metricData.value?.maturity?.scores?.[dimKey]?.scored_at
  return scored ? formatDate(scored) : null
}

function dimensionScoredBy(dimKey) {
  return metricData.value?.maturity?.scores?.[dimKey]?.scorer?.name ?? null
}

async function refreshHealth() {
  refreshing.value = true
  try {
    const res = await window.axios.post(route('atlas.modules.refresh'))
    modules.value      = res.data.modules
    summary.value      = res.data.summary
    integrations.value = res.data.integrations
    cachedAt.value     = res.data.cachedAt
  } finally {
    refreshing.value = false
  }
}

async function openDetail(mod) {
  selectedModule.value   = mod
  showDetailModal.value  = true
  activeTab.value        = 'health'
  metricData.value       = null
  scoringDimension.value = null
  metricsLoading.value   = true

  try {
    const res = await window.axios.get(route('atlas.modules.metrics', { key: mod.key }))
    metricData.value = res.data
    const s = res.data.settings ?? {}
    settingsForm.value = {
      version:   s.version   ?? '1.0.0',
      sla_hours: s.sla_hours ?? null,
      notes:     s.notes     ?? '',
      owner_id:  s.owner_id  ?? null,
    }
  } catch {
    // silently leave metricData null — tabs will show an error state
  } finally {
    metricsLoading.value = false
  }
}

function closeDetail() {
  showDetailModal.value  = false
  selectedModule.value   = null
  metricData.value       = null
  scoringDimension.value = null
}

function expandDimension(dimKey) {
  if (scoringDimension.value === dimKey) {
    scoringDimension.value = null
    return
  }
  scoringDimension.value = dimKey
  const existing       = metricData.value?.maturity?.scores?.[dimKey]
  const criteriaCount  = metricData.value?.dimensions?.[dimKey]?.criteria?.length ?? 5
  dimensionForm.value  = {
    criteria_checks: existing?.criteria_checks ? [...existing.criteria_checks] : Array(criteriaCount).fill(false),
    notes: existing?.notes ?? '',
  }
}

async function saveDimension(dimKey) {
  if (!selectedModule.value || savingDimension.value) return
  savingDimension.value = true
  try {
    const res = await window.axios.post(
      route('atlas.modules.maturity.save', { key: selectedModule.value.key }),
      { dimension: dimKey, criteria_checks: dimensionForm.value.criteria_checks, notes: dimensionForm.value.notes }
    )
    if (metricData.value) {
      metricData.value.maturity.radar   = res.data.radar
      metricData.value.maturity.overall = res.data.overall
      if (!metricData.value.maturity.scores) metricData.value.maturity.scores = {}
      metricData.value.maturity.scores[dimKey] = res.data.score
    }
    scoringDimension.value = null
  } finally {
    savingDimension.value = false
  }
}

async function saveSettings() {
  if (!selectedModule.value || savingSettings.value) return
  savingSettings.value = true
  try {
    const res = await window.axios.post(
      route('atlas.modules.settings.save', { key: selectedModule.value.key }),
      settingsForm.value
    )
    if (metricData.value) metricData.value.settings = res.data.settings
  } finally {
    savingSettings.value = false
  }
}
</script>

<template>
  <AdminLayout title="Module Monitor">
    <Head title="Atlas Module Monitor" />

    <!-- ── Page Header ───────────────────────────────────────────────────── -->
    <AppPageHeader hero title="Atlas Module Monitor" subtitle="Atlas health, maturity, and ecosystem status of all software modules" class="dash-section mb-6" style="--stagger: 0">
      <template #actions>
        <span v-if="cachedAtFormatted" class="text-xs text-slate-400">
          Updated: {{ cachedAtFormatted }}
        </span>
        <AppButton :loading="refreshing" :disabled="refreshing" @click="refreshHealth">
          <ArrowPathIcon :class="['h-4 w-4', refreshing && 'animate-spin']" />
          {{ refreshing ? 'Refreshing…' : 'Refresh Health' }}
        </AppButton>
      </template>
    </AppPageHeader>

    <!-- ── System Summary Bar ────────────────────────────────────────────── -->
    <AppCard :padded="true" class="dash-section mb-6" style="--stagger: 1">
      <div class="flex flex-wrap items-center gap-x-6 gap-y-3">
        <div class="flex items-center gap-2">
          <CpuChipIcon class="h-5 w-5 text-slate-400" />
          <span class="text-2xl font-bold text-slate-800 tabular-nums">{{ totalValue }}</span>
          <span class="text-sm text-slate-500">modules</span>
        </div>

        <div class="hidden h-6 w-px bg-slate-200 sm:block" />

        <div class="flex flex-wrap items-center gap-4">
          <div v-for="(cfg, key) in healthConfig" :key="key" class="flex items-center gap-1.5">
            <component :is="cfg.icon" :class="['h-4 w-4', cfg.iconClass]" />
            <span class="text-sm font-bold" :class="cfg.iconClass">{{ summary[key] ?? 0 }}</span>
            <span class="text-xs text-slate-500">{{ cfg.label }}</span>
          </div>
        </div>

        <div class="hidden h-6 w-px bg-slate-200 sm:block" />

        <div class="flex items-center gap-3">
          <span class="text-xs text-slate-400">Maturity:</span>
          <div v-for="level in [1, 2, 3, 4, 5]" :key="level" class="flex flex-col items-center">
            <span class="text-sm font-semibold text-slate-700">{{ summary.maturity?.[level] ?? 0 }}</span>
            <span class="text-[10px] text-slate-400">L{{ level }}</span>
          </div>
        </div>
      </div>
    </AppCard>


    <!-- ── External Integrations Panel ──────────────────────────────────── -->
    <AppCard :padded="true" class="dash-section mb-6" style="--stagger: 2">
      <template #header>
        <div class="flex items-center gap-2">
          <LinkIcon class="h-4 w-4 text-slate-500" />
          <h2 class="text-sm font-semibold text-slate-700">External Integrations</h2>
        </div>
      </template>

      <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
        <div
          v-for="integration in integrations"
          :key="integration.key"
          class="rounded-lg border p-3 transition-colors"
          :class="integration.configured ? 'border-emerald-100 bg-emerald-50' : 'border-slate-100 bg-slate-50'"
        >
          <div class="mb-1 flex items-center justify-between gap-1">
            <span class="truncate text-sm font-medium text-slate-700">{{ integration.label }}</span>
            <AppBadge :color="integration.configured ? 'green' : 'slate'">
              {{ integration.configured ? 'OK' : 'Not set' }}
            </AppBadge>
          </div>
          <p class="text-xs text-slate-500 leading-relaxed">{{ integration.description }}</p>
        </div>
      </div>
    </AppCard>

    <!-- ── Filter Bar ────────────────────────────────────────────────────── -->
    <div class="dash-section mb-4 flex flex-wrap items-center gap-3" style="--stagger: 3">
      <div class="flex flex-wrap gap-1.5">
        <button
          @click="filterCategory = 'all'"
          :class="['rounded-full px-3 py-1 text-xs font-medium transition-colors', filterCategory === 'all' ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200']"
        >All</button>
        <button
          v-for="cat in categories"
          :key="cat.key"
          @click="filterCategory = cat.key"
          :class="['rounded-full px-3 py-1 text-xs font-medium transition-colors', filterCategory === cat.key ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200']"
        >{{ cat.label }}</button>
      </div>

      <div class="ml-auto flex items-center gap-2">
        <select
          v-model="filterHealth"
          class="rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs text-slate-600 focus:outline-none focus:ring-1 focus:ring-indigo-400"
        >
          <option value="all">All Health</option>
          <option v-for="(cfg, key) in healthConfig" :key="key" :value="key">{{ cfg.label }}</option>
        </select>

        <select
          v-model="filterMaturity"
          class="rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs text-slate-600 focus:outline-none focus:ring-1 focus:ring-indigo-400"
        >
          <option value="all">All Maturity</option>
          <option v-for="(ml, level) in maturityLabels" :key="level" :value="String(level)">
            L{{ level }} — {{ ml.label }}
          </option>
        </select>
      </div>
    </div>

    <!-- ── Module Cards Grid ─────────────────────────────────────────────── -->
    <div v-if="filteredModules.length === 0" class="dash-section py-20 text-center" style="--stagger: 4">
      <p class="text-sm text-slate-500">No modules match the selected filters.</p>
    </div>

    <div v-else class="dash-section grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3" style="--stagger: 4">
      <div
        v-for="mod in filteredModules"
        :key="mod.key"
        @click="openDetail(mod)"
        class="cursor-pointer rounded-xl border border-slate-100 bg-white p-5 shadow-sm transition-shadow hover:shadow-md"
      >
        <!-- Card header -->
        <div class="mb-3 flex items-start justify-between gap-2">
          <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-semibold text-slate-800">{{ mod.name }}</p>
            <AppBadge color="indigo" class="mt-1">
              {{ categoryLabels[mod.category] ?? mod.category }}
            </AppBadge>
          </div>
          <AppBadge :color="healthConfig[mod.health_status]?.badgeColor ?? 'slate'" class="shrink-0">
            <component :is="healthConfig[mod.health_status]?.icon" class="mr-1 inline h-3 w-3" />
            {{ healthConfig[mod.health_status]?.label ?? mod.health_status }}
          </AppBadge>
        </div>

        <!-- Maturity stars + overall % from DB scoring -->
        <div class="mb-2 flex items-center gap-0.5">
          <StarSolid v-for="i in mod.maturity" :key="'f' + i" class="h-4 w-4 text-amber-400" />
          <StarOutline v-for="i in (5 - mod.maturity)" :key="'e' + i" class="h-4 w-4 text-slate-200" />
          <span class="ml-1.5 text-xs text-slate-500">
            {{ maturityLabels[mod.maturity]?.label ?? ('L' + mod.maturity) }}
          </span>
          <span v-if="mod.maturity_overall !== null" class="ml-auto rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-semibold text-indigo-600">
            {{ mod.maturity_overall }}% scored
          </span>
        </div>

        <!-- Ping sparkline (last 7 health checks) -->
        <div v-if="mod.recent_pings?.length" class="mb-3 flex items-center gap-1">
          <span class="text-[10px] text-slate-400 mr-0.5">7d:</span>
          <span
            v-for="(ping, i) in mod.recent_pings"
            :key="i"
            :class="['h-2 w-2 rounded-full flex-shrink-0', pingDotClass(ping)]"
            :title="ping"
          />
        </div>

        <!-- Activity stats -->
        <div class="mb-3 grid grid-cols-2 gap-2 text-xs">
          <div>
            <p class="text-slate-400">7-day records</p>
            <p class="font-semibold text-slate-700">{{ formatNumber(mod.activity_count_7d) }}</p>
          </div>
          <div>
            <p class="text-slate-400">Last activity</p>
            <p class="truncate font-semibold text-slate-700">{{ formatDate(mod.last_activity_at) }}</p>
          </div>
        </div>

        <!-- Failed jobs warning -->
        <div
          v-if="mod.failed_jobs_count > 0"
          class="mb-3 flex items-center gap-1.5 rounded-md bg-danger-50 px-2.5 py-1.5 text-xs text-danger-600"
        >
          <ExclamationTriangleIcon class="h-3.5 w-3.5 shrink-0" />
          {{ mod.failed_jobs_count }} failed job{{ mod.failed_jobs_count !== 1 ? 's' : '' }}
        </div>

        <!-- Dependency chips -->
        <div v-if="mod.dependencies?.length" class="mb-3 flex flex-wrap gap-1">
          <span
            v-for="dep in mod.dependencies"
            :key="dep"
            class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] text-slate-500"
          >{{ dep }}</span>
        </div>

        <!-- Card footer -->
        <div class="flex items-center justify-between border-t border-slate-50 pt-3">
          <span class="text-xs text-slate-400">Click for details</span>
          <a
            v-if="safeRoute(mod.route_name)"
            :href="safeRoute(mod.route_name)"
            @click.stop
            class="inline-flex items-center gap-1 text-xs font-medium text-indigo-600 hover:text-indigo-800"
          >
            Open <ArrowTopRightOnSquareIcon class="h-3.5 w-3.5" />
          </a>
        </div>
      </div>
    </div>

    <!-- ── Module Detail Modal (4 tabs) ─────────────────────────────────── -->
    <AppModal
      :show="showDetailModal"
      :title="selectedModule?.name ?? 'Module Detail'"
      size="4xl"
      @close="closeDetail"
    >
      <template v-if="selectedModule">
        <!-- Module meta row -->
        <div class="mb-4 flex flex-wrap items-center gap-3">
          <AppBadge :color="healthConfig[selectedModule.health_status]?.badgeColor ?? 'slate'">
            <component :is="healthConfig[selectedModule.health_status]?.icon" class="mr-1 inline h-4 w-4" />
            {{ healthConfig[selectedModule.health_status]?.label }}
          </AppBadge>
          <AppBadge color="indigo">{{ categoryLabels[selectedModule.category] ?? selectedModule.category }}</AppBadge>
          <div class="flex items-center gap-0.5 ml-auto">
            <StarSolid v-for="i in selectedModule.maturity" :key="'f' + i" class="h-4 w-4 text-amber-400" />
            <StarOutline v-for="i in (5 - selectedModule.maturity)" :key="'e' + i" class="h-4 w-4 text-slate-200" />
            <span class="ml-1.5 text-xs text-slate-500">{{ maturityLabels[selectedModule.maturity]?.label ?? ('L' + selectedModule.maturity) }}</span>
          </div>
        </div>

        <p class="mb-5 text-sm leading-relaxed text-slate-600">{{ selectedModule.description }}</p>

        <!-- Tab nav -->
        <div class="mb-5 border-b border-slate-200">
          <nav class="-mb-px flex">
            <button
              v-for="tab in tabs"
              :key="tab.key"
              @click="activeTab = tab.key"
              :class="[
                'px-4 py-2.5 text-sm font-medium border-b-2 transition-colors whitespace-nowrap',
                activeTab === tab.key
                  ? 'border-indigo-600 text-indigo-600'
                  : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'
              ]"
            >{{ tab.label }}</button>
          </nav>
        </div>

        <!-- Loading skeleton -->
        <div v-if="metricsLoading" class="space-y-3 py-4">
          <div v-for="i in 5" :key="i" class="h-14 animate-pulse rounded-lg bg-slate-100" />
        </div>

        <!-- Metrics load error -->
        <div v-else-if="!metricData" class="rounded-lg bg-danger-50 p-4 text-sm text-danger-600">
          Failed to load module metrics. Try closing and reopening the module detail.
        </div>

        <template v-else>

          <!-- ── Tab 1: Technical Health ──────────────────────────────────── -->
          <div v-show="activeTab === 'health'">
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
              <template v-for="(metric, mKey) in metricData.metrics.technical" :key="mKey">
                <div class="rounded-lg border border-slate-100 bg-slate-50 p-3">
                  <div class="mb-1.5 flex items-center justify-between gap-1">
                    <p class="text-xs font-medium text-slate-500">{{ metric.label }}</p>
                    <span
                      v-if="metric.real"
                      class="h-2 w-2 flex-shrink-0 rounded-full"
                      :class="statusDotClass(metric.status)"
                    />
                    <span v-else class="text-[10px] text-slate-300 font-medium">STUB</span>
                  </div>
                  <template v-if="metric.real">
                    <p class="text-sm font-bold text-slate-800">
                      {{ metric.value !== null && metric.value !== undefined ? metric.value : '—' }}
                      <span v-if="metric.unit" class="text-xs font-normal text-slate-400"> {{ metric.unit }}</span>
                    </p>
                    <p v-if="metric.note" class="mt-0.5 text-[10px] text-slate-400">{{ metric.note }}</p>
                  </template>
                  <p v-else class="text-xs text-slate-400 italic leading-snug">{{ metric.stub_message }}</p>
                </div>
              </template>
            </div>

            <!-- Dependencies + integrations -->
            <div class="mt-5 grid grid-cols-2 gap-4">
              <div v-if="selectedModule.dependencies?.length">
                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Dependencies</p>
                <div class="flex flex-wrap gap-1.5">
                  <span
                    v-for="dep in selectedModule.dependencies"
                    :key="dep"
                    class="rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-600"
                  >{{ dep }}</span>
                </div>
              </div>
              <div v-if="selectedModule.integrations?.length">
                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Integrations</p>
                <div class="flex flex-wrap gap-1.5">
                  <AppBadge v-for="intg in selectedModule.integrations" :key="intg" color="blue">
                    {{ integrationLabel(intg) }}
                  </AppBadge>
                </div>
              </div>
            </div>

            <!-- Module notes -->
            <div v-if="selectedModule.notes" class="mt-4 rounded-lg bg-warning-50 p-3">
              <p class="text-xs leading-relaxed text-warning-700">
                <span class="font-semibold">Note: </span>{{ selectedModule.notes }}
              </p>
            </div>
          </div>

          <!-- ── Tab 2: Usage ─────────────────────────────────────────────── -->
          <div v-show="activeTab === 'usage'">
            <!-- KPI row: DAU / WAU / MAU / transactions -->
            <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
              <div class="rounded-lg bg-indigo-50 p-3 text-center">
                <p class="text-xs text-indigo-500">DAU</p>
                <p class="text-xl font-bold text-indigo-700">{{ formatNumber(metricData.metrics.usage.dau.value) }}</p>
                <p class="text-[10px] text-indigo-400">today</p>
              </div>
              <div class="rounded-lg bg-indigo-50 p-3 text-center">
                <p class="text-xs text-indigo-500">WAU</p>
                <p class="text-xl font-bold text-indigo-700">{{ formatNumber(metricData.metrics.usage.wau.value) }}</p>
                <p class="text-[10px] text-indigo-400">7 days</p>
              </div>
              <div class="rounded-lg bg-indigo-50 p-3 text-center">
                <p class="text-xs text-indigo-500">MAU</p>
                <p class="text-xl font-bold text-indigo-700">{{ formatNumber(metricData.metrics.usage.mau.value) }}</p>
                <p class="text-[10px] text-indigo-400">30 days</p>
              </div>
              <div class="rounded-lg bg-slate-50 p-3 text-center">
                <p class="text-xs text-slate-500">Records (7d)</p>
                <p class="text-xl font-bold text-slate-700">{{ formatNumber(metricData.metrics.usage.transactions_7d.value) }}</p>
                <p class="text-[10px] text-slate-400">module table</p>
              </div>
            </div>

            <p class="mb-3 text-[10px] text-slate-400">
              Active Users (DAU/WAU/MAU) are system-wide across all modules — module-specific attribution requires activity middleware.
            </p>

            <!-- Peak hours chart -->
            <div v-if="peakChartData" class="mb-4">
              <p class="mb-2 text-xs font-semibold text-slate-600">Peak Usage Hours (last 30 days — module transactions by hour)</p>
              <div class="h-20 w-full rounded-lg bg-slate-50 p-2">
                <Bar :data="peakChartData" :options="peakChartOptions" />
              </div>
              <div class="mt-1 flex justify-between text-[10px] text-slate-400">
                <span>12am</span><span>6am</span><span>12pm</span><span>6pm</span><span>11pm</span>
              </div>
            </div>

            <!-- Stub metrics -->
            <div class="grid grid-cols-3 gap-3">
              <div
                v-for="(metric, mKey) in metricData.metrics.usage"
                :key="mKey"
                v-show="!metric.real"
                class="rounded-lg border border-dashed border-slate-200 bg-slate-50 p-3"
              >
                <p class="text-xs font-medium text-slate-400">{{ metric.label }}</p>
                <p class="mt-1 text-xs text-slate-300 italic">{{ metric.stub_message }}</p>
              </div>
            </div>
          </div>

          <!-- ── Tab 3: Operations ─────────────────────────────────────────── -->
          <div v-show="activeTab === 'operations'">
            <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
              <!-- Pending approvals -->
              <div class="rounded-lg bg-slate-50 p-3">
                <p class="mb-1 text-xs font-medium text-slate-500">Pending Approvals</p>
                <p
                  class="text-2xl font-bold"
                  :class="metricData.metrics.operational.pending_approvals.value > 0 ? 'text-warning-600' : 'text-slate-800'"
                >{{ formatNumber(metricData.metrics.operational.pending_approvals.value) }}</p>
                <!-- Per-type drill-through links -->
                <div v-if="metricData.metrics.operational.pending_approvals.details?.length" class="mt-1 space-y-1">
                  <div
                    v-for="cfg in metricData.metrics.operational.pending_approvals.details"
                    :key="cfg.label"
                    class="flex items-center justify-between gap-1"
                  >
                    <p class="text-[10px] text-slate-400 truncate">{{ cfg.label }}</p>
                    <a
                      v-if="cfg.route_name && safeRoute(cfg.route_name)"
                      :href="safeRoute(cfg.route_name)"
                      target="_blank"
                      @click.stop
                      class="inline-flex shrink-0 items-center gap-0.5 text-[10px] font-medium text-indigo-500 hover:text-indigo-700"
                    >
                      View <ArrowRightIcon class="h-2.5 w-2.5" />
                    </a>
                  </div>
                </div>
              </div>

              <div class="rounded-lg bg-slate-50 p-3">
                <p class="mb-1 text-xs font-medium text-slate-500">Queue Backlog</p>
                <p
                  class="text-2xl font-bold"
                  :class="metricData.metrics.operational.queue_backlog.value > 0 ? 'text-warning-600' : 'text-slate-800'"
                >{{ formatNumber(metricData.metrics.operational.queue_backlog.value) }}</p>
                <p class="text-[10px] text-slate-400">jobs pending</p>
              </div>

              <div class="rounded-lg bg-slate-50 p-3">
                <p class="mb-1 text-xs font-medium text-slate-500">Notifs Sent (7d)</p>
                <p class="text-2xl font-bold text-slate-800">{{ formatNumber(metricData.metrics.operational.notifications_sent_7d.value) }}</p>
                <p class="text-[10px] text-slate-400">system-wide</p>
              </div>

              <div class="rounded-lg bg-slate-50 p-3">
                <p class="mb-1 text-xs font-medium text-slate-500">Unread Notifications</p>
                <p
                  class="text-2xl font-bold"
                  :class="metricData.metrics.operational.notifications_unread.value > 0 ? 'text-warning-600' : 'text-slate-800'"
                >{{ formatNumber(metricData.metrics.operational.notifications_unread.value) }}</p>
                <p class="text-[10px] text-slate-400">system-wide</p>
              </div>
            </div>

            <!-- SLA Compliance (real when sla_hours configured) -->
            <div
              v-if="metricData.metrics.operational.sla_compliance.real"
              class="mb-4 rounded-lg border border-slate-200 bg-white p-3"
            >
              <div class="flex items-center justify-between gap-2">
                <p class="text-xs font-medium text-slate-600">SLA Compliance</p>
                <div class="flex items-center gap-1.5">
                  <span
                    class="h-2 w-2 rounded-full"
                    :class="statusDotClass(metricData.metrics.operational.sla_compliance.status)"
                  />
                  <span
                    class="text-lg font-bold"
                    :class="{
                      'text-success-600': metricData.metrics.operational.sla_compliance.status === 'ok',
                      'text-warning-600': metricData.metrics.operational.sla_compliance.status === 'warn',
                      'text-danger-600':  metricData.metrics.operational.sla_compliance.status === 'error',
                      'text-slate-700':   !['ok','warn','error'].includes(metricData.metrics.operational.sla_compliance.status),
                    }"
                  >{{ metricData.metrics.operational.sla_compliance.value !== null ? metricData.metrics.operational.sla_compliance.value + '%' : '—' }}</span>
                </div>
              </div>
              <p class="text-[10px] text-slate-400">{{ metricData.metrics.operational.sla_compliance.unit }}</p>
              <p v-if="metricData.metrics.operational.sla_compliance.note" class="mt-0.5 text-[10px] text-slate-500">
                {{ metricData.metrics.operational.sla_compliance.note }}
              </p>
            </div>

            <!-- Stub metrics (excludes real ones) -->
            <div class="mb-4 grid grid-cols-2 gap-3">
              <div
                v-for="(metric, mKey) in metricData.metrics.operational"
                :key="mKey"
                v-show="!metric.real"
                class="rounded-lg border border-dashed border-slate-200 bg-slate-50 p-3"
              >
                <p class="text-xs font-medium text-slate-400">{{ metric.label }}</p>
                <p class="mt-1 text-xs text-slate-300 italic">{{ metric.stub_message }}</p>
              </div>
            </div>

            <!-- Module Settings panel -->
            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
              <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Module Settings</p>
              <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div>
                  <label class="mb-1 block text-xs font-medium text-slate-600">Version</label>
                  <input
                    v-model="settingsForm.version"
                    type="text"
                    placeholder="1.0.0"
                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  />
                </div>
                <div>
                  <label class="mb-1 block text-xs font-medium text-slate-600">SLA Hours</label>
                  <input
                    v-model.number="settingsForm.sla_hours"
                    type="number"
                    min="1"
                    max="8760"
                    placeholder="e.g. 24"
                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  />
                </div>
                <div>
                  <label class="mb-1 block text-xs font-medium text-slate-600">Owner</label>
                  <select
                    v-model="settingsForm.owner_id"
                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  >
                    <option :value="null">Not assigned</option>
                    <option
                      v-for="u in (metricData.users ?? [])"
                      :key="u.id"
                      :value="u.id"
                    >{{ u.name }}</option>
                  </select>
                </div>
              </div>
              <div class="mt-3">
                <label class="mb-1 block text-xs font-medium text-slate-600">Notes</label>
                <textarea
                  v-model="settingsForm.notes"
                  rows="2"
                  placeholder="Admin notes about this module…"
                  class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"
                />
              </div>
              <div class="mt-3 flex justify-end">
                <AppButton :loading="savingSettings" :disabled="savingSettings" @click="saveSettings">
                  {{ savingSettings ? 'Saving…' : 'Save Settings' }}
                </AppButton>
              </div>
            </div>
          </div>

          <!-- ── Tab 4: Maturity ───────────────────────────────────────────── -->
          <div v-show="activeTab === 'maturity'">
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

              <!-- Radar chart + overall score -->
              <div>
                <div class="mb-2 flex items-center justify-between">
                  <p class="text-xs font-semibold text-slate-600">Maturity Radar</p>
                  <div class="flex items-center gap-2">
                    <span class="text-xs text-slate-400">Overall score:</span>
                    <span class="text-lg font-bold text-indigo-600">{{ metricData.maturity.overall }}%</span>
                  </div>
                </div>
                <div v-if="radarChartData" class="h-56 w-full">
                  <Radar :data="radarChartData" :options="radarChartOptions" />
                </div>
                <div v-else class="flex h-56 items-center justify-center rounded-lg bg-slate-50">
                  <p class="text-sm text-slate-400">Score dimensions to see the radar chart</p>
                </div>
                <div class="mt-3 rounded-lg bg-slate-50 p-3">
                  <p class="text-xs text-slate-500 leading-relaxed">
                    Each dimension is scored by admin check-off (5 criteria per dimension).
                    Weight: Functional 20% · Technical 15% · Security 20% · Governance 15% · AI/DQ/UX 10% each.
                  </p>
                </div>
              </div>

              <!-- Dimension list + scorer -->
              <div class="space-y-2">
                <div
                  v-for="(dim, dimKey) in metricData.dimensions"
                  :key="dimKey"
                  class="rounded-lg border border-slate-200 overflow-hidden"
                >
                  <!-- Dimension header -->
                  <button
                    @click="expandDimension(dimKey)"
                    class="flex w-full items-center justify-between px-3 py-2.5 text-left hover:bg-slate-50 transition-colors"
                  >
                    <div class="flex items-center gap-2 min-w-0">
                      <span class="h-2.5 w-2.5 flex-shrink-0 rounded-full" :style="`background-color: ${dim.color}`" />
                      <span class="text-sm font-medium text-slate-700 truncate">{{ dim.label }}</span>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0 ml-2">
                      <div class="w-16 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                        <div
                          class="h-full rounded-full bg-indigo-500 transition-all"
                          :style="`width: ${dimensionScore(dimKey)}%`"
                        />
                      </div>
                      <span class="text-xs font-semibold text-slate-600 w-8 text-right">{{ dimensionScore(dimKey) }}%</span>
                      <component
                        :is="scoringDimension === dimKey ? ChevronUpIcon : ChevronDownIcon"
                        class="h-3.5 w-3.5 text-slate-400"
                      />
                    </div>
                  </button>

                  <!-- Scoring form (expanded) -->
                  <div v-show="scoringDimension === dimKey" class="border-t border-slate-100 bg-white px-3 pb-3 pt-2">
                    <p class="mb-2 text-xs text-slate-500">{{ dim.description }}</p>
                    <div class="space-y-1.5">
                      <label
                        v-for="(criterion, idx) in dim.criteria"
                        :key="idx"
                        class="flex cursor-pointer items-start gap-2.5"
                      >
                        <input
                          type="checkbox"
                          :checked="!!dimensionForm.criteria_checks[idx]"
                          @change="dimensionForm.criteria_checks[idx] = $event.target.checked"
                          class="mt-0.5 h-4 w-4 flex-shrink-0 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                        />
                        <span class="text-xs text-slate-600 leading-snug">{{ criterion }}</span>
                      </label>
                    </div>
                    <textarea
                      v-model="dimensionForm.notes"
                      rows="2"
                      placeholder="Optional notes…"
                      class="mt-2.5 w-full rounded border border-slate-200 px-2.5 py-1.5 text-xs resize-none focus:outline-none focus:ring-1 focus:ring-indigo-400"
                    />
                    <div class="mt-2 flex items-center justify-between">
                      <p v-if="dimensionScoredAt(dimKey)" class="text-[10px] text-slate-400">
                        Last scored {{ dimensionScoredAt(dimKey) }}
                        <template v-if="dimensionScoredBy(dimKey)">by {{ dimensionScoredBy(dimKey) }}</template>
                      </p>
                      <AppButton size="sm" class="ml-auto" :loading="savingDimension" :disabled="savingDimension" @click="saveDimension(dimKey)">
                        {{ savingDimension ? 'Saving…' : 'Save Score' }}
                      </AppButton>
                    </div>
                  </div>
                </div>
              </div>

            </div>
          </div>

        </template>
      </template>

      <template #footer>
        <AppButton variant="secondary" @click="closeDetail">Close</AppButton>
        <AppButton v-if="safeRoute(selectedModule?.route_name)" as="a" :href="safeRoute(selectedModule.route_name)">
          Open Module <ArrowTopRightOnSquareIcon class="h-4 w-4" />
        </AppButton>
      </template>
    </AppModal>
  </AdminLayout>
</template>
