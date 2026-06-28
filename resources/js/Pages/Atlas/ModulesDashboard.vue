<script setup>
import { ref, computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppCard from '@/Components/AppCard.vue'
import AppBadge from '@/Components/AppBadge.vue'
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
} from '@heroicons/vue/24/outline'
import { StarIcon as StarSolid } from '@heroicons/vue/24/solid'
import { StarIcon as StarOutline } from '@heroicons/vue/24/outline'

const props = defineProps({
  modules:        { type: Array,  default: () => [] },
  summary:        { type: Object, default: () => ({}) },
  integrations:   { type: Array,  default: () => [] },
  categoryLabels: { type: Object, default: () => ({}) },
  maturityLabels: { type: Object, default: () => ({}) },
  cachedAt:       { type: String, default: null },
})

// Reactive — updated in-place by refresh POST without a full page reload
const modules      = ref(props.modules)
const summary      = ref(props.summary)
const integrations = ref(props.integrations)
const cachedAt     = ref(props.cachedAt)

const refreshing      = ref(false)
const selectedModule  = ref(null)
const showDetailModal = ref(false)
const filterCategory  = ref('all')
const filterHealth    = ref('all')
const filterMaturity  = ref('all')

const healthConfig = {
  healthy:  { label: 'Healthy',  badgeColor: 'green',  icon: CheckCircleIcon,         iconClass: 'text-emerald-500' },
  idle:     { label: 'Idle',     badgeColor: 'slate',  icon: ClockIcon,               iconClass: 'text-slate-400'   },
  degraded: { label: 'Degraded', badgeColor: 'amber',  icon: ExclamationTriangleIcon,  iconClass: 'text-amber-500'   },
  critical: { label: 'Critical', badgeColor: 'red',    icon: XCircleIcon,             iconClass: 'text-red-500'     },
  info:     { label: 'Info',     badgeColor: 'slate',  icon: InformationCircleIcon,   iconClass: 'text-slate-400'   },
}

// Ordered category list derived from the module set (preserves registry order)
const categories = computed(() => {
  const seen = new Set()
  const result = []
  for (const m of modules.value) {
    if (!seen.has(m.category)) {
      seen.add(m.category)
      result.push({ key: m.category, label: props.categoryLabels[m.category] ?? m.category })
    }
  }
  return result
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
    ? new Intl.DateTimeFormat('en-PH', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: 'Asia/Manila',
      }).format(new Date(cachedAt.value))
    : null
)

function formatDate(val) {
  if (!val) return '—'
  return new Intl.DateTimeFormat('en-PH', {
    dateStyle: 'medium',
    timeZone: 'Asia/Manila',
  }).format(new Date(val))
}

function formatNumber(val) {
  if (val === null || val === undefined) return '—'
  return Number(val).toLocaleString('en-PH')
}

// Integration label lookup by key
function integrationLabel(key) {
  const found = integrations.value.find(i => i.key === key)
  return found ? found.label : key
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

function openDetail(mod) {
  selectedModule.value  = mod
  showDetailModal.value = true
}

function closeDetail() {
  showDetailModal.value = false
}
</script>

<template>
  <AdminLayout title="Module Monitor">
    <Head title="Atlas Module Monitor" />

    <!-- ── Page Header ───────────────────────────────────────────────────── -->
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
      <div>
        <h1 class="text-xl font-bold text-slate-800">Atlas Module Monitor</h1>
        <p class="mt-0.5 text-sm text-slate-500">
          Health, maturity, and ecosystem status of all BugSayMis software modules
        </p>
      </div>
      <div class="flex items-center gap-3">
        <span v-if="cachedAtFormatted" class="text-xs text-slate-400">
          Updated: {{ cachedAtFormatted }}
        </span>
        <button
          @click="refreshHealth"
          :disabled="refreshing"
          class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white transition-colors hover:bg-indigo-700 disabled:opacity-60"
        >
          <ArrowPathIcon :class="['h-4 w-4', refreshing && 'animate-spin']" />
          {{ refreshing ? 'Refreshing…' : 'Refresh Health' }}
        </button>
      </div>
    </div>

    <!-- ── System Summary Bar ────────────────────────────────────────────── -->
    <AppCard :padded="true" class="mb-6">
      <div class="flex flex-wrap items-center gap-x-6 gap-y-3">
        <!-- Total -->
        <div class="flex items-center gap-2">
          <CpuChipIcon class="h-5 w-5 text-slate-400" />
          <span class="text-2xl font-bold text-slate-800">{{ summary.total }}</span>
          <span class="text-sm text-slate-500">modules</span>
        </div>

        <div class="hidden h-6 w-px bg-slate-200 sm:block" />

        <!-- Health status pills -->
        <div class="flex flex-wrap items-center gap-4">
          <div
            v-for="(cfg, key) in healthConfig"
            :key="key"
            class="flex items-center gap-1.5"
          >
            <component :is="cfg.icon" :class="['h-4 w-4', cfg.iconClass]" />
            <span class="text-sm font-bold" :class="cfg.iconClass">{{ summary[key] ?? 0 }}</span>
            <span class="text-xs text-slate-500">{{ cfg.label }}</span>
          </div>
        </div>

        <div class="hidden h-6 w-px bg-slate-200 sm:block" />

        <!-- Maturity distribution -->
        <div class="flex items-center gap-3">
          <span class="text-xs text-slate-400">Maturity:</span>
          <div
            v-for="level in [1, 2, 3, 4, 5]"
            :key="level"
            class="flex flex-col items-center"
          >
            <span class="text-sm font-semibold text-slate-700">{{ summary.maturity?.[level] ?? 0 }}</span>
            <span class="text-[10px] text-slate-400">L{{ level }}</span>
          </div>
        </div>
      </div>
    </AppCard>

    <!-- ── External Integrations Panel ──────────────────────────────────── -->
    <AppCard :padded="true" class="mb-6">
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
          :class="integration.configured
            ? 'border-emerald-100 bg-emerald-50'
            : 'border-slate-100 bg-slate-50'"
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
    <div class="mb-4 flex flex-wrap items-center gap-3">
      <!-- Category pills -->
      <div class="flex flex-wrap gap-1.5">
        <button
          @click="filterCategory = 'all'"
          :class="[
            'rounded-full px-3 py-1 text-xs font-medium transition-colors',
            filterCategory === 'all'
              ? 'bg-indigo-600 text-white'
              : 'bg-slate-100 text-slate-600 hover:bg-slate-200',
          ]"
        >
          All
        </button>
        <button
          v-for="cat in categories"
          :key="cat.key"
          @click="filterCategory = cat.key"
          :class="[
            'rounded-full px-3 py-1 text-xs font-medium transition-colors',
            filterCategory === cat.key
              ? 'bg-indigo-600 text-white'
              : 'bg-slate-100 text-slate-600 hover:bg-slate-200',
          ]"
        >
          {{ cat.label }}
        </button>
      </div>

      <div class="ml-auto flex items-center gap-2">
        <select
          v-model="filterHealth"
          class="rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs text-slate-600 focus:outline-none focus:ring-1 focus:ring-indigo-400"
        >
          <option value="all">All Health</option>
          <option v-for="(cfg, key) in healthConfig" :key="key" :value="key">
            {{ cfg.label }}
          </option>
        </select>

        <select
          v-model="filterMaturity"
          class="rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs text-slate-600 focus:outline-none focus:ring-1 focus:ring-indigo-400"
        >
          <option value="all">All Maturity</option>
          <option
            v-for="(ml, level) in maturityLabels"
            :key="level"
            :value="String(level)"
          >
            L{{ level }} — {{ ml.label }}
          </option>
        </select>
      </div>
    </div>

    <!-- ── Module Cards Grid ─────────────────────────────────────────────── -->
    <div v-if="filteredModules.length === 0" class="py-20 text-center">
      <p class="text-sm text-slate-500">No modules match the selected filters.</p>
    </div>

    <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
      <div
        v-for="mod in filteredModules"
        :key="mod.key"
        @click="openDetail(mod)"
        class="cursor-pointer rounded-xl border border-slate-100 bg-white p-5 shadow-sm transition-shadow hover:shadow-md"
      >
        <!-- Card header: name + badges -->
        <div class="mb-3 flex items-start justify-between gap-2">
          <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-semibold text-slate-800">{{ mod.name }}</p>
            <AppBadge color="indigo" class="mt-1">
              {{ categoryLabels[mod.category] ?? mod.category }}
            </AppBadge>
          </div>
          <AppBadge :color="healthConfig[mod.health_status]?.badgeColor ?? 'slate'" class="shrink-0">
            <component
              :is="healthConfig[mod.health_status]?.icon"
              class="mr-1 inline h-3 w-3"
            />
            {{ healthConfig[mod.health_status]?.label ?? mod.health_status }}
          </AppBadge>
        </div>

        <!-- Maturity stars -->
        <div class="mb-3 flex items-center gap-0.5">
          <StarSolid
            v-for="i in mod.maturity"
            :key="'f' + i"
            class="h-4 w-4 text-amber-400"
          />
          <StarOutline
            v-for="i in (5 - mod.maturity)"
            :key="'e' + i"
            class="h-4 w-4 text-slate-200"
          />
          <span class="ml-1.5 text-xs text-slate-500">
            {{ maturityLabels[mod.maturity]?.label ?? ('L' + mod.maturity) }}
          </span>
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
          class="mb-3 flex items-center gap-1.5 rounded-md bg-red-50 px-2.5 py-1.5 text-xs text-red-600"
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
          >
            {{ dep }}
          </span>
        </div>

        <!-- Card footer -->
        <div class="flex items-center justify-between border-t border-slate-50 pt-3">
          <span class="text-xs text-slate-400">Click for details</span>
          <a
            v-if="mod.route_name"
            :href="route(mod.route_name)"
            @click.stop
            class="inline-flex items-center gap-1 text-xs font-medium text-indigo-600 hover:text-indigo-800"
          >
            Open
            <ArrowTopRightOnSquareIcon class="h-3.5 w-3.5" />
          </a>
        </div>
      </div>
    </div>

    <!-- ── Module Detail Modal ───────────────────────────────────────────── -->
    <AppModal
      :show="showDetailModal"
      :title="selectedModule?.name ?? 'Module Detail'"
      size="2xl"
      @close="closeDetail"
    >
      <template v-if="selectedModule">
        <!-- Health + stars row -->
        <div class="mb-4 flex items-center justify-between gap-3">
          <AppBadge :color="healthConfig[selectedModule.health_status]?.badgeColor ?? 'slate'">
            <component
              :is="healthConfig[selectedModule.health_status]?.icon"
              class="mr-1 inline h-4 w-4"
            />
            {{ healthConfig[selectedModule.health_status]?.label }}
          </AppBadge>

          <div class="flex items-center gap-0.5">
            <StarSolid
              v-for="i in selectedModule.maturity"
              :key="'f' + i"
              class="h-5 w-5 text-amber-400"
            />
            <StarOutline
              v-for="i in (5 - selectedModule.maturity)"
              :key="'e' + i"
              class="h-5 w-5 text-slate-200"
            />
          </div>
        </div>

        <!-- Category badge -->
        <div class="mb-3">
          <AppBadge color="indigo">
            {{ categoryLabels[selectedModule.category] ?? selectedModule.category }}
          </AppBadge>
        </div>

        <!-- Description -->
        <p class="mb-5 text-sm leading-relaxed text-slate-600">
          {{ selectedModule.description }}
        </p>

        <!-- Maturity level description -->
        <div class="mb-5 rounded-lg bg-indigo-50 p-3">
          <p class="mb-0.5 text-xs font-semibold text-indigo-700">
            Level {{ selectedModule.maturity }} — {{ maturityLabels[selectedModule.maturity]?.label }}
          </p>
          <p class="text-xs text-indigo-600">
            {{ maturityLabels[selectedModule.maturity]?.description }}
          </p>
        </div>

        <!-- Metrics grid -->
        <div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
          <div class="rounded-lg bg-slate-50 p-3">
            <p class="mb-1 text-xs text-slate-400">7-day records</p>
            <p class="text-lg font-bold text-slate-800">{{ formatNumber(selectedModule.activity_count_7d) }}</p>
          </div>
          <div class="rounded-lg bg-slate-50 p-3">
            <p class="mb-1 text-xs text-slate-400">30-day records</p>
            <p class="text-lg font-bold text-slate-800">{{ formatNumber(selectedModule.activity_count_30d) }}</p>
          </div>
          <div class="rounded-lg bg-slate-50 p-3">
            <p class="mb-1 text-xs text-slate-400">Failed jobs</p>
            <p
              class="text-lg font-bold"
              :class="selectedModule.failed_jobs_count > 0 ? 'text-red-600' : 'text-slate-800'"
            >
              {{ selectedModule.failed_jobs_count }}
            </p>
          </div>
          <div class="rounded-lg bg-slate-50 p-3">
            <p class="mb-1 text-xs text-slate-400">Last activity</p>
            <p class="text-sm font-semibold text-slate-800">{{ formatDate(selectedModule.last_activity_at) }}</p>
          </div>
        </div>

        <!-- Dependencies -->
        <div v-if="selectedModule.dependencies?.length" class="mb-4">
          <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
            Dependencies
          </p>
          <div class="flex flex-wrap gap-2">
            <span
              v-for="dep in selectedModule.dependencies"
              :key="dep"
              class="rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-600"
            >
              {{ dep }}
            </span>
          </div>
        </div>

        <!-- Integrations -->
        <div v-if="selectedModule.integrations?.length" class="mb-4">
          <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
            External Integrations
          </p>
          <div class="flex flex-wrap gap-2">
            <AppBadge
              v-for="intg in selectedModule.integrations"
              :key="intg"
              color="blue"
            >
              {{ integrationLabel(intg) }}
            </AppBadge>
          </div>
        </div>

        <!-- Notes -->
        <div v-if="selectedModule.notes" class="rounded-lg bg-amber-50 p-3">
          <p class="text-xs leading-relaxed text-amber-700">
            <span class="font-semibold">Note: </span>{{ selectedModule.notes }}
          </p>
        </div>
      </template>

      <template #footer>
        <div class="flex items-center justify-end gap-3">
          <button
            @click="closeDetail"
            class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50"
          >
            Close
          </button>
          <a
            v-if="selectedModule?.route_name"
            :href="route(selectedModule.route_name)"
            class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
          >
            Open Module
            <ArrowTopRightOnSquareIcon class="h-4 w-4" />
          </a>
        </div>
      </template>
    </AppModal>
  </AdminLayout>
</template>
