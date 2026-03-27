<template>
  <Head title="AI Auto Schedule Generator" />
  <AdminLayout title="AI Auto Schedule Generator">
    <div class="space-y-5">

      <!-- Page header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold text-slate-800 flex items-center gap-2">
            <SparklesIcon class="h-5 w-5 text-indigo-500" />
            AI Timetable Generator
          </h1>
          <p class="text-sm text-slate-500 mt-0.5">
            Automatically generate an optimized class schedule using a Genetic Algorithm.
          </p>
        </div>
      </div>

      <!-- Alert / notification -->
      <div v-if="alert.message" :class="[
          'rounded-lg px-4 py-3 text-sm flex items-start gap-2',
          alert.type === 'success' ? 'bg-emerald-50 border border-emerald-200 text-emerald-700'
            : alert.type === 'warning' ? 'bg-amber-50 border border-amber-200 text-amber-700'
            : 'bg-red-50 border border-red-200 text-red-700'
        ]">
        <CheckCircleIcon v-if="alert.type === 'success'" class="h-4 w-4 mt-0.5 shrink-0" />
        <ExclamationTriangleIcon v-else class="h-4 w-4 mt-0.5 shrink-0" />
        <span>{{ alert.message }}</span>
      </div>

      <!-- ── Configuration Panel ─────────────────────────────────────── -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5 space-y-5">
        <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">
          1. Select Term
        </h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <!-- School Year -->
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">School Year</label>
            <select v-model="form.school_year_id" @change="onSchoolYearChange"
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
              <option :value="null" disabled>Select school year…</option>
              <option v-for="sy in schoolYears" :key="sy.id" :value="sy.id">
                {{ sy.name }}{{ sy.is_current ? ' (current)' : '' }}
              </option>
            </select>
          </div>

          <!-- Academic Term -->
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Academic Term</label>
            <select v-model="form.academic_term_id" :disabled="!form.school_year_id"
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent disabled:opacity-50">
              <option :value="null" disabled>Select term…</option>
              <option v-for="t in selectedSchoolYear?.terms ?? []" :key="t.id" :value="t.id">
                {{ t.label }}{{ t.is_current ? ' (current)' : '' }}
              </option>
            </select>
          </div>
        </div>

        <!-- Advanced GA Parameters -->
        <div>
          <button @click="showAdvanced = !showAdvanced"
            class="flex items-center gap-1 text-xs font-medium text-indigo-600 hover:text-indigo-800 transition-colors">
            <ChevronDownIcon :class="['h-3.5 w-3.5 transition-transform', showAdvanced ? 'rotate-180' : '']" />
            Advanced GA Parameters
          </button>

          <div v-if="showAdvanced" class="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-4 pt-3 border-t border-slate-100">
            <!-- Population Size -->
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">
                Population Size
                <span class="text-slate-400 font-normal">(10–100)</span>
              </label>
              <input type="number" v-model.number="form.population_size"
                min="10" max="100" step="5"
                class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
              <p class="text-xs text-slate-400 mt-1">More = better results, slower run</p>
            </div>

            <!-- Mutation Rate -->
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">
                Mutation Rate
                <span class="text-slate-400 font-normal">(0.01–0.30)</span>
              </label>
              <input type="number" v-model.number="form.mutation_rate"
                min="0.01" max="0.30" step="0.01"
                class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
              <p class="text-xs text-slate-400 mt-1">Higher = more exploration</p>
            </div>

            <!-- Max Generations -->
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">
                Max Generations
                <span class="text-slate-400 font-normal">(20–500)</span>
              </label>
              <input type="number" v-model.number="form.max_generations"
                min="20" max="500" step="10"
                class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
              <p class="text-xs text-slate-400 mt-1">Stops early if conflict-free</p>
            </div>
          </div>
        </div>

        <!-- Generate button -->
        <div class="flex items-center gap-3 pt-2 border-t border-slate-100">
          <button @click="runGenerate"
            :disabled="!canGenerate || generating"
            class="inline-flex items-center gap-2 px-5 py-2.5 text-sm bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-lg font-medium transition-colors shadow-sm">
            <ArrowPathIcon v-if="generating" class="h-4 w-4 animate-spin" />
            <SparklesIcon v-else class="h-4 w-4" />
            {{ generating ? 'Generating…' : 'Generate Schedule' }}
          </button>
          <p v-if="generating" class="text-xs text-slate-400">
            Running GA — this may take a few seconds…
          </p>
        </div>
      </div>

      <!-- ── Result Panel ────────────────────────────────────────────── -->
      <template v-if="result">

        <!-- Summary cards -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
          <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 text-center">
            <p class="text-2xl font-bold"
              :class="result.hard_conflicts > 0 ? 'text-red-600' : 'text-emerald-600'">
              {{ result.hard_conflicts }}
            </p>
            <p class="text-xs text-slate-500 mt-1">Hard Conflicts</p>
          </div>
          <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 text-center">
            <p class="text-2xl font-bold text-indigo-600">{{ result.schedules_generated }}</p>
            <p class="text-xs text-slate-500 mt-1">Slots Generated</p>
          </div>
          <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 text-center">
            <p class="text-2xl font-bold" :class="result.fitness_score >= 0 ? 'text-emerald-600' : 'text-amber-600'">
              {{ result.fitness_score?.toLocaleString() ?? '—' }}
            </p>
            <p class="text-xs text-slate-500 mt-1">Fitness Score</p>
          </div>
          <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 text-center">
            <p class="text-2xl font-bold text-slate-700">{{ result.duration_seconds ?? '—' }}s</p>
            <p class="text-xs text-slate-500 mt-1">Run Time</p>
          </div>
        </div>

        <!-- Conflict warning -->
        <div v-if="result.hard_conflicts > 0"
          class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 flex items-start gap-3">
          <ExclamationTriangleIcon class="h-5 w-5 text-amber-500 mt-0.5 shrink-0" />
          <div>
            <p class="text-sm font-semibold text-amber-800">
              {{ result.hard_conflicts }} hard conflict(s) remain
            </p>
            <p class="text-xs text-amber-700 mt-0.5">
              Try increasing population size or max generations, then re-run.
              You can still save and fix conflicts manually in the Schedules module.
            </p>
          </div>
        </div>
        <div v-else class="bg-emerald-50 border border-emerald-200 rounded-xl px-4 py-3 flex items-center gap-3">
          <CheckCircleIcon class="h-5 w-5 text-emerald-500 shrink-0" />
          <p class="text-sm font-semibold text-emerald-800">Conflict-free schedule generated!</p>
        </div>

        <!-- Schedule preview table -->
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
          <div class="px-5 py-3 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <h2 class="text-sm font-semibold text-slate-700">Preview — Generated Schedule</h2>
            <div class="flex items-center gap-2">
              <!-- Filter by day -->
              <select v-model="previewFilter.day"
                class="text-xs border border-slate-200 rounded-md px-2 py-1.5 focus:ring-2 focus:ring-indigo-500">
                <option value="">All Days</option>
                <option v-for="d in days" :key="d" :value="d">{{ d }}</option>
              </select>
              <!-- Filter by faculty -->
              <select v-model="previewFilter.faculty"
                class="text-xs border border-slate-200 rounded-md px-2 py-1.5 focus:ring-2 focus:ring-indigo-500">
                <option value="">All Faculty</option>
                <option v-for="f in previewFaculty" :key="f" :value="f">{{ f }}</option>
              </select>
            </div>
          </div>

          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-50 text-sm">
              <thead>
                <tr class="text-xs text-slate-400 uppercase tracking-wide bg-slate-50">
                  <th class="px-4 py-2.5 text-left">Day</th>
                  <th class="px-4 py-2.5 text-left">Time</th>
                  <th class="px-4 py-2.5 text-left">Faculty</th>
                  <th class="px-4 py-2.5 text-left">Subject</th>
                  <th class="px-4 py-2.5 text-left">Section</th>
                  <th class="px-4 py-2.5 text-left">Room</th>
                  <th class="px-4 py-2.5 text-center">Status</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-50">
                <tr v-for="(s, idx) in filteredSchedules" :key="idx"
                  :class="['hover:bg-slate-50/50 transition-colors', hasConflict(s) ? 'bg-red-50' : '']">
                  <td class="px-4 py-2.5 font-medium text-slate-700">{{ s.day_of_week }}</td>
                  <td class="px-4 py-2.5 text-slate-600 tabular-nums">
                    {{ fmtTime(s.start_time) }} – {{ fmtTime(s.end_time) }}
                  </td>
                  <td class="px-4 py-2.5 text-slate-700">{{ s._faculty_name }}</td>
                  <td class="px-4 py-2.5 text-slate-700">{{ s._subject_name }}</td>
                  <td class="px-4 py-2.5 text-slate-500">{{ s._section_id }}</td>
                  <td class="px-4 py-2.5 text-slate-500">{{ s._classroom_name }}</td>
                  <td class="px-4 py-2.5 text-center">
                    <span v-if="hasConflict(s)"
                      class="inline-flex items-center gap-1 text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded-full font-medium">
                      <ExclamationTriangleIcon class="h-3 w-3" /> Conflict
                    </span>
                    <span v-else
                      class="inline-flex items-center gap-1 text-xs bg-slate-100 text-slate-600 px-2 py-0.5 rounded-full">
                      Tentative
                    </span>
                  </td>
                </tr>
                <tr v-if="filteredSchedules.length === 0">
                  <td colspan="7" class="px-4 py-10 text-center text-slate-400 text-xs">
                    No schedules match the current filter.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Action buttons -->
        <div class="flex flex-wrap items-center gap-3">
          <button @click="applySchedules"
            :disabled="applying"
            class="inline-flex items-center gap-2 px-5 py-2.5 text-sm bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white rounded-lg font-medium transition-colors shadow-sm">
            <ArrowDownTrayIcon v-if="!applying" class="h-4 w-4" />
            <ArrowPathIcon v-else class="h-4 w-4 animate-spin" />
            {{ applying ? 'Saving…' : 'Save as Tentative' }}
          </button>
          <button @click="discardResult"
            class="inline-flex items-center gap-2 px-5 py-2.5 text-sm bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 rounded-lg font-medium transition-colors">
            Discard
          </button>
          <p class="text-xs text-slate-400">
            Saving adds these as <strong>tentative</strong> schedules in the Schedules module.
            Existing tentative schedules for this term will be replaced.
          </p>
        </div>
      </template>

      <!-- ── Recent Jobs ─────────────────────────────────────────────── -->
      <div v-if="recentJobs.length" class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-100">
          <h2 class="text-sm font-semibold text-slate-700">Recent Generation History</h2>
        </div>
        <table class="min-w-full divide-y divide-slate-50 text-sm">
          <thead>
            <tr class="text-xs text-slate-400 uppercase tracking-wide bg-slate-50">
              <th class="px-4 py-2.5 text-left">Term</th>
              <th class="px-4 py-2.5 text-left">Generated</th>
              <th class="px-4 py-2.5 text-center">Slots</th>
              <th class="px-4 py-2.5 text-center">Conflicts</th>
              <th class="px-4 py-2.5 text-center">Fitness</th>
              <th class="px-4 py-2.5 text-center">Status</th>
              <th class="px-4 py-2.5 text-center">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50">
            <tr v-for="job in recentJobs" :key="job.id" class="hover:bg-slate-50/50">
              <td class="px-4 py-2.5 text-slate-700">{{ job.academic_term_label }}</td>
              <td class="px-4 py-2.5 text-slate-500 text-xs">
                {{ fmtDateTime(job.created_at) }}
                <span v-if="job.created_by_name !== '—'" class="text-slate-400"> by {{ job.created_by_name }}</span>
              </td>
              <td class="px-4 py-2.5 text-center text-slate-600">{{ job.schedules_generated }}</td>
              <td class="px-4 py-2.5 text-center">
                <span :class="[
                    'text-xs font-semibold',
                    job.hard_conflicts === 0 ? 'text-emerald-600' : 'text-red-600'
                  ]">{{ job.hard_conflicts }}</span>
              </td>
              <td class="px-4 py-2.5 text-center text-slate-500 tabular-nums text-xs">
                {{ job.fitness_score?.toLocaleString() ?? '—' }}
              </td>
              <td class="px-4 py-2.5 text-center">
                <span :class="statusClass(job.status)">{{ job.status }}</span>
              </td>
              <td class="px-4 py-2.5 text-center">
                <button v-if="job.status === 'completed'"
                  @click="loadJob(job)"
                  class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                  Load
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, reactive } from 'vue'
import { Head } from '@inertiajs/vue3'
import axios from 'axios'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import {
  SparklesIcon,
  ArrowPathIcon,
  CheckCircleIcon,
  ExclamationTriangleIcon,
  ChevronDownIcon,
  ArrowDownTrayIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  schoolYears: Array,
  recentJobs:  Array,
})

const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday']

// ── Form state ─────────────────────────────────────────────────────────────

const form = reactive({
  school_year_id:   null,
  academic_term_id: null,
  population_size:  30,
  mutation_rate:    0.05,
  max_generations:  100,
})

const showAdvanced = ref(false)

const selectedSchoolYear = computed(() =>
  props.schoolYears?.find(sy => sy.id === form.school_year_id) ?? null
)

const canGenerate = computed(() => form.school_year_id && form.academic_term_id)

function onSchoolYearChange() {
  form.academic_term_id = null
  // Auto-select current term if available
  const currentTerm = selectedSchoolYear.value?.terms?.find(t => t.is_current)
  if (currentTerm) {
    form.academic_term_id = currentTerm.id
  }
}

// Auto-select current school year on mount
const currentSY = props.schoolYears?.find(sy => sy.is_current)
if (currentSY) {
  form.school_year_id = currentSY.id
  const currentTerm = currentSY.terms?.find(t => t.is_current)
  if (currentTerm) form.academic_term_id = currentTerm.id
}

// ── Generation ──────────────────────────────────────────────────────────────

const generating = ref(false)
const result     = ref(null)   // the job object returned from the API
const alert      = reactive({ type: '', message: '' })

function clearAlert() {
  alert.type    = ''
  alert.message = ''
}

async function runGenerate() {
  if (!canGenerate.value || generating.value) return
  generating.value = true
  result.value     = null
  clearAlert()

  try {
    const { data } = await axios.post('/faculty-loading/auto-schedule/generate', {
      school_year_id:   form.school_year_id,
      academic_term_id: form.academic_term_id,
      population_size:  form.population_size,
      mutation_rate:    form.mutation_rate,
      max_generations:  form.max_generations,
    })

    result.value = data.job

    if (data.warning) {
      alert.type    = 'warning'
      alert.message = data.warning
    } else if (data.job.hard_conflicts === 0) {
      alert.type    = 'success'
      alert.message = 'Conflict-free schedule generated successfully!'
    }
  } catch (err) {
    alert.type    = 'error'
    alert.message = err.response?.data?.message ?? 'Generation failed. Please try again.'
  } finally {
    generating.value = false
  }
}

// ── Preview / Filters ───────────────────────────────────────────────────────

const previewFilter = reactive({ day: '', faculty: '' })

const filteredSchedules = computed(() => {
  if (!result.value?.schedules) return []
  return result.value.schedules.filter(s => {
    if (previewFilter.day && s.day_of_week !== previewFilter.day) return false
    if (previewFilter.faculty && s._faculty_name !== previewFilter.faculty) return false
    return true
  })
})

const previewFaculty = computed(() => {
  if (!result.value?.schedules) return []
  return [...new Set(result.value.schedules.map(s => s._faculty_name))].sort()
})

/** Detect hard conflicts in the preview list (same faculty/room/section overlapping). */
function hasConflict(schedule) {
  if (!result.value?.schedules) return false
  const others = result.value.schedules.filter(s => s !== schedule)
  const sStart = timeToMin(schedule.start_time)
  const sEnd   = timeToMin(schedule.end_time)

  return others.some(o => {
    if (o.day_of_week !== schedule.day_of_week) return false
    const oStart = timeToMin(o.start_time)
    const oEnd   = timeToMin(o.end_time)
    const overlap = sStart < oEnd && sEnd > oStart
    if (!overlap) return false
    return (
      o.user_id      === schedule.user_id      ||
      o.classroom_id === schedule.classroom_id ||
      o.section_id   === schedule.section_id
    )
  })
}

// ── Apply ───────────────────────────────────────────────────────────────────

const applying = ref(false)

async function applySchedules() {
  if (!result.value?.id || applying.value) return
  applying.value = true
  clearAlert()

  try {
    const { data } = await axios.post(
      `/faculty-loading/auto-schedule/jobs/${result.value.id}/apply`
    )
    alert.type    = 'success'
    alert.message = data.message
  } catch (err) {
    alert.type    = 'error'
    alert.message = err.response?.data?.message ?? 'Failed to save schedules.'
  } finally {
    applying.value = false
  }
}

function discardResult() {
  result.value = null
  clearAlert()
}

// ── History ─────────────────────────────────────────────────────────────────

const recentJobs = ref(props.recentJobs ?? [])

function loadJob(job) {
  // Load a past job result into the preview panel
  axios.get(`/faculty-loading/auto-schedule/jobs/${job.id}`)
    .then(({ data }) => {
      result.value = data
      form.academic_term_id = data.academic_term_id
    })
    .catch(() => {
      alert.type    = 'error'
      alert.message = 'Could not load the selected job.'
    })
}

// ── Utilities ────────────────────────────────────────────────────────────────

function fmtTime(t) {
  if (!t) return '—'
  const [h, m] = t.split(':').map(Number)
  const suffix = h >= 12 ? 'PM' : 'AM'
  const hh     = h % 12 || 12
  return `${hh}:${String(m).padStart(2, '0')} ${suffix}`
}

function fmtDateTime(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('en-PH', {
    month: 'short', day: 'numeric', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })
}

function timeToMin(t) {
  const [h, m] = (t ?? '').split(':').map(Number)
  return (h || 0) * 60 + (m || 0)
}

function statusClass(status) {
  const map = {
    completed: 'inline-flex text-xs bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded-full font-medium',
    running:   'inline-flex text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full font-medium',
    failed:    'inline-flex text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded-full font-medium',
    pending:   'inline-flex text-xs bg-slate-100 text-slate-600 px-2 py-0.5 rounded-full',
  }
  return map[status] ?? map.pending
}
</script>
