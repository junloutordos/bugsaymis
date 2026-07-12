<script setup>
import { ref, computed } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppCard from '@/Components/AppCard.vue'
import AppButton from '@/Components/AppButton.vue'
import EmptyState from '@/Components/EmptyState.vue'
import {
  TableCellsIcon,
  SparklesIcon,
  CheckCircleIcon,
  ExclamationTriangleIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  schoolYears: Array,
  grades: Array,
})

// ── State ─────────────────────────────────────────────────────────────────────
const selectedGrade = ref(null)
const generating    = ref(false)
const result        = ref(null)
const alert         = ref({ type: '', message: '' })
const activeSection = ref(null)

const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday']

const TYPE_LABELS = {
  core:         { label: 'Core',        color: 'bg-indigo-100 text-indigo-700' },
  elective:     { label: 'Elective',    color: 'bg-amber-100 text-amber-700' },
  science_core: { label: 'Science Core',color: 'bg-emerald-100 text-emerald-700' },
}

// ── Computed ──────────────────────────────────────────────────────────────────
const canGenerate = computed(() => selectedGrade.value !== null && !generating.value)

const sectionNames = computed(() => {
  if (!result.value) return []
  return Object.keys(result.value.plan?.sections ?? {})
})

const currentSection = computed(() => {
  if (!result.value || !activeSection.value) return null
  return result.value.plan?.sections?.[activeSection.value] ?? []
})

/** Build a time-indexed lookup: { 'day|start' → placement } for the active section */
const gridMap = computed(() => {
  if (!currentSection.value) return {}
  const map = {}
  for (const p of currentSection.value) {
    map[`${p.day_of_week}|${p.start_time}`] = p
  }
  return map
})

/** All unique start times across the whole plan (for grid rows) */
const allStartTimes = computed(() => {
  if (!result.value) return []
  const times = new Set()
  for (const placements of Object.values(result.value.plan?.sections ?? {})) {
    for (const p of placements) times.add(p.start_time)
  }
  return [...times].sort()
})

const metrics = computed(() => result.value?.plan?.metrics ?? {})
const unresolved = computed(() => result.value?.plan?.unresolved ?? [])
const validationPasses = computed(() => result.value?.validation?.passes ?? true)
const violations = computed(() => result.value?.validation?.violations ?? [])

// ── Methods ───────────────────────────────────────────────────────────────────
function showAlert(type, message) {
  alert.value = { type, message }
  setTimeout(() => { alert.value = { type: '', message: '' } }, 6000)
}

async function runGenerate() {
  if (!canGenerate.value) return

  generating.value = true
  result.value     = null
  alert.value      = { type: '', message: '' }

  try {
    const response = await fetch(route('faculty-loading.auto-schedule.slot-plan.preview'), {
      method:  'POST',
      headers: {
        'Content-Type':  'application/json',
        'Accept':        'application/json',
        'X-CSRF-TOKEN':  usePage().props.csrf_token ?? document.querySelector('meta[name=csrf-token]')?.content ?? '',
      },
      body: JSON.stringify({ grade: selectedGrade.value }),
    })

    if (!response.ok) {
      const err = await response.json().catch(() => ({}))
      throw new Error(err.message ?? `Server error ${response.status}`)
    }

    const data = await response.json()
    result.value = data

    // Activate first section by default
    const sections = Object.keys(data.plan?.sections ?? {})
    activeSection.value = sections[0] ?? null

    if (!data.validation?.passes) {
      showAlert('warning', `Plan generated with ${data.validation.violations.length} constraint violation(s). Review the Violations tab.`)
    } else {
      showAlert('success', `Slot plan generated — ${data.plan.metrics.placed_count} sessions placed, ${data.plan.metrics.unresolved_count} unresolved.`)
    }
  } catch (e) {
    showAlert('error', e.message ?? 'Generation failed.')
  } finally {
    generating.value = false
  }
}

function cellFor(day, time) {
  return gridMap.value[`${day}|${time}`] ?? null
}

function typeStyle(type) {
  return TYPE_LABELS[type]?.color ?? 'bg-slate-100 text-slate-600'
}

function typeLabel(type) {
  return TYPE_LABELS[type]?.label ?? type
}
</script>

<template>
  <Head title="Slot Plan Generator" />
  <AdminLayout title="Slot Plan Generator">
    <div class="space-y-5">

      <!-- Page header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold text-slate-800 flex items-center gap-2">
            <TableCellsIcon class="h-5 w-5 text-indigo-500" />
            Deterministic Slot Plan Generator
          </h1>
          <p class="text-sm text-slate-500 mt-0.5">
            Generates a constraint-first, conflict-free weekly slot plan for a grade using the rule-based placement pipeline.
          </p>
        </div>
        <AppButton variant="secondary" as="a" :href="route('faculty-loading.auto-schedule.index')">
          <SparklesIcon class="h-4 w-4" />
          Switch to GA Generator
        </AppButton>
      </div>

      <!-- Alert -->
      <div v-if="alert.message" :class="[
          'rounded-lg px-4 py-3 text-sm flex items-start gap-2',
          alert.type === 'success' ? 'bg-success-50 border border-success-100 text-success-700'
            : alert.type === 'warning' ? 'bg-warning-50 border border-warning-100 text-warning-700'
            : 'bg-danger-50 border border-danger-100 text-danger-700',
        ]">
        <CheckCircleIcon v-if="alert.type === 'success'" class="h-4 w-4 mt-0.5 shrink-0" />
        <ExclamationTriangleIcon v-else class="h-4 w-4 mt-0.5 shrink-0" />
        <span>{{ alert.message }}</span>
      </div>

      <!-- ── Configuration ──────────────────────────────────────────────── -->
      <AppCard>
        <div class="space-y-4">
          <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">
            Select Grade
          </h2>

          <div class="flex flex-wrap gap-2">
            <button
              v-for="g in grades"
              :key="g.value"
              @click="selectedGrade = g.value; result = null"
              :class="[
                'px-4 py-2 rounded-lg text-sm font-medium border transition-colors',
                selectedGrade === g.value
                  ? 'bg-indigo-600 border-indigo-600 text-white shadow-sm'
                  : 'bg-white border-slate-200 text-slate-600 hover:border-indigo-300 hover:text-indigo-700',
              ]">
              {{ g.label }}
            </button>
          </div>

          <div class="pt-2 border-t border-slate-100 flex items-center gap-3">
            <AppButton size="lg" :disabled="!canGenerate" :loading="generating" @click="runGenerate">
              <TableCellsIcon v-if="!generating" class="h-4 w-4" />
              {{ generating ? 'Generating…' : 'Generate Slot Plan' }}
            </AppButton>
            <p v-if="generating" class="text-xs text-slate-400">
              Running constraint-first placement pipeline…
            </p>
          </div>
        </div>
      </AppCard>

      <!-- ── Results ────────────────────────────────────────────────────── -->
      <template v-if="result">

        <!-- Metrics row -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
          <AppCard class="text-center">
            <p class="text-2xl font-bold text-indigo-600">{{ metrics.placed_count ?? 0 }}</p>
            <p class="text-xs text-slate-500 mt-1">Sessions Placed</p>
          </AppCard>
          <AppCard class="text-center">
            <p class="text-2xl font-bold"
              :class="(metrics.unresolved_count ?? 0) > 0 ? 'text-warning-600' : 'text-success-600'">
              {{ metrics.unresolved_count ?? 0 }}
            </p>
            <p class="text-xs text-slate-500 mt-1">Unresolved</p>
          </AppCard>
          <AppCard class="text-center">
            <p class="text-2xl font-bold"
              :class="validationPasses ? 'text-success-600' : 'text-danger-600'">
              {{ validationPasses ? '✓' : violations.length }}
            </p>
            <p class="text-xs text-slate-500 mt-1">Constraint Violations</p>
          </AppCard>
          <AppCard class="text-center">
            <p class="text-2xl font-bold text-slate-700">{{ result.subjects_loaded }}</p>
            <p class="text-xs text-slate-500 mt-1">Subjects Loaded</p>
          </AppCard>
        </div>

        <!-- Advisory / SCALE info -->
        <AppCard>
          <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-2">Fixed Blocks (not in grid)</p>
          <div class="flex flex-wrap gap-3 text-sm text-slate-600">
            <span class="inline-flex items-center gap-1.5 bg-slate-100 rounded-full px-3 py-1 text-xs">
              <span class="font-medium">{{ result.plan.advisory?.type }}</span>
              {{ result.plan.advisory?.day }} {{ result.plan.advisory?.start }}–{{ result.plan.advisory?.end }}
            </span>
            <span v-if="result.plan.scale" class="inline-flex items-center gap-1.5 bg-slate-100 rounded-full px-3 py-1 text-xs">
              <span class="font-medium">SCALE Advising</span>
              {{ result.plan.scale?.day }} {{ result.plan.scale?.start }}–{{ result.plan.scale?.end }}
            </span>
          </div>
        </AppCard>

        <!-- Violations panel -->
        <div v-if="!validationPasses" class="bg-danger-50 border border-danger-100 rounded-xl p-4">
          <p class="text-sm font-semibold text-danger-700 mb-2 flex items-center gap-2">
            <ExclamationTriangleIcon class="h-4 w-4" />
            Constraint Violations
          </p>
          <ul class="text-xs text-danger-600 space-y-1 list-disc list-inside">
            <li v-for="(v, i) in violations" :key="i">{{ v }}</li>
          </ul>
        </div>

        <!-- Unresolved sessions -->
        <div v-if="unresolved.length" class="bg-warning-50 border border-warning-100 rounded-xl p-4">
          <p class="text-sm font-semibold text-warning-700 mb-2 flex items-center gap-2">
            <ExclamationTriangleIcon class="h-4 w-4" />
            {{ unresolved.length }} Unresolved Session(s)
          </p>
          <div class="flex flex-wrap gap-2">
            <span v-for="(u, i) in unresolved" :key="i"
              class="text-xs bg-warning-100 text-warning-700 rounded px-2 py-0.5">
              {{ u.subject }} s{{ u.session }} ({{ u.type }})
            </span>
          </div>
        </div>

        <!-- ── Per-section weekly grid ──────────────────────────────── -->
        <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200/70">

          <!-- Section tabs -->
          <div class="border-b border-slate-100 flex overflow-x-auto">
            <button
              v-for="sec in sectionNames"
              :key="sec"
              @click="activeSection = sec"
              :class="[
                'px-4 py-3 text-sm font-medium whitespace-nowrap transition-colors',
                activeSection === sec
                  ? 'border-b-2 border-indigo-600 text-indigo-700 bg-indigo-50'
                  : 'text-slate-500 hover:text-slate-700 hover:bg-slate-50',
              ]">
              {{ sec }}
              <span class="ml-1.5 text-xs text-slate-400">
                ({{ result.plan.sections[sec]?.length ?? 0 }})
              </span>
            </button>
          </div>

          <!-- Grid -->
          <div class="overflow-x-auto p-4">
            <table v-if="activeSection && allStartTimes.length" class="w-full text-xs border-separate border-spacing-0.5">
              <thead>
                <tr>
                  <th class="text-left px-2 py-1.5 text-slate-400 font-medium w-16">Time</th>
                  <th v-for="day in DAYS" :key="day"
                    class="text-center px-2 py-1.5 text-slate-500 font-semibold uppercase tracking-wide min-w-28">
                    {{ day }}
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="time in allStartTimes" :key="time">
                  <td class="px-2 py-1 text-slate-400 font-mono text-right align-top">{{ time }}</td>
                  <td v-for="day in DAYS" :key="day" class="px-1 py-1 align-top">
                    <div v-if="cellFor(day, time)" :class="[
                        'rounded px-2 py-1.5 text-center leading-tight',
                        typeStyle(cellFor(day, time).type),
                      ]">
                      <p class="font-semibold truncate max-w-28">{{ cellFor(day, time).subject }}</p>
                      <p class="text-[10px] opacity-70 mt-0.5">
                        {{ typeLabel(cellFor(day, time).type) }} · s{{ cellFor(day, time).session }}
                      </p>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>

            <p v-else class="text-sm text-slate-400 text-center py-8">
              No placements for this section.
            </p>
          </div>

          <!-- Section placement list (compact) -->
          <div v-if="currentSection?.length" class="border-t border-slate-100 px-4 py-3">
            <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-2">
              All Placements — {{ activeSection }}
            </p>
            <div class="flex flex-wrap gap-1.5">
              <span v-for="(p, i) in currentSection" :key="i"
                :class="['text-xs rounded px-2 py-0.5 font-medium', typeStyle(p.type)]">
                {{ p.subject }} s{{ p.session }}
                <span class="opacity-60 font-normal ml-1">{{ p.day_of_week.slice(0,3) }} {{ p.start_time }}</span>
              </span>
            </div>
          </div>
        </div>

      </template>

      <!-- Empty state -->
      <div v-if="!result && !generating" class="bg-white rounded-xl border border-dashed border-slate-200">
        <EmptyState title="No slot plan generated yet" :icon="TableCellsIcon">
          <p class="text-xs text-slate-400 mt-1">
            Select a grade and click <strong>Generate Slot Plan</strong> to preview the weekly timetable.
          </p>
        </EmptyState>
      </div>

    </div>
  </AdminLayout>
</template>
