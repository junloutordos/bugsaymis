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
            Automatically generate a conflict-free class schedule using the constraint-based scheduler.
          </p>
        </div>
      </div>

      <!-- ── Configuration Panel ─────────────────────────────────────── -->
      <AppCard>
        <div class="space-y-5">
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

          <!-- Generate button -->
          <div class="flex items-center gap-3 pt-2 border-t border-slate-100">
            <AppButton size="lg" :disabled="!canGenerate || generating" :loading="generating" @click="runGenerate">
              <SparklesIcon v-if="!generating" class="h-4 w-4" />
              {{ generating ? 'Generating…' : 'Generate Schedule' }}
            </AppButton>
            <p v-if="generating" class="text-xs text-slate-400">
              Generating — this may take a few seconds…
            </p>
          </div>
        </div>
      </AppCard>

      <!-- ── Result Panel ────────────────────────────────────────────── -->
      <template v-if="result">

        <!-- Summary cards -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
          <AppCard class="text-center">
            <p class="text-2xl font-bold"
              :class="liveConflictCount > 0 ? 'text-danger-600' : 'text-success-600'">
              {{ liveConflictCount }}
            </p>
            <p class="text-xs text-slate-500 mt-1">Hard Conflicts</p>
          </AppCard>
          <AppCard class="text-center">
            <p class="text-2xl font-bold text-indigo-600">{{ result.schedules_generated }}</p>
            <p class="text-xs text-slate-500 mt-1">Slots Generated</p>
          </AppCard>
          <AppCard class="text-center">
            <p class="text-2xl font-bold" :class="unplaceable.length === 0 ? 'text-success-600' : 'text-warning-600'">
              {{ unplaceable.length }}
            </p>
            <p class="text-xs text-slate-500 mt-1">Unplaced</p>
          </AppCard>
          <AppCard class="text-center">
            <p class="text-2xl font-bold text-slate-700">{{ result.duration_seconds ?? '—' }}s</p>
            <p class="text-xs text-slate-500 mt-1">Run Time</p>
          </AppCard>
        </div>

        <!-- Conflict-free success -->
        <div v-if="liveConflictCount === 0" class="bg-success-50 border border-success-100 rounded-xl px-4 py-3 flex items-center gap-3">
          <CheckCircleIcon class="h-5 w-5 text-success-500 shrink-0" />
          <p class="text-sm font-semibold text-success-700">Conflict-free schedule generated!</p>
        </div>

        <!-- ── Per-section coverage report ─────────────────────────────── -->
        <AppCard v-if="sectionReport.length" :padded="false" class="overflow-hidden">
          <div class="px-5 py-3 border-b border-slate-100">
            <h2 class="text-sm font-semibold text-slate-700">Section Coverage</h2>
            <p class="text-xs text-slate-500 mt-0.5">Sessions placed vs. required per section.</p>
          </div>
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-50 text-sm">
              <thead>
                <tr class="text-xs text-slate-400 uppercase tracking-wide bg-slate-50">
                  <th class="px-4 py-2.5 text-left">Grade</th>
                  <th class="px-4 py-2.5 text-left">Section</th>
                  <th class="px-4 py-2.5 text-center">Needed</th>
                  <th class="px-4 py-2.5 text-center">Placed</th>
                  <th class="px-4 py-2.5 text-center">Unplaced</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-50">
                <tr v-for="r in sectionReport" :key="r.section_id"
                  :class="['hover:bg-slate-50/50', r.unplaced > 0 ? 'bg-warning-50/40' : '']">
                  <td class="px-4 py-2 text-slate-500">G{{ r.grade }}</td>
                  <td class="px-4 py-2 font-medium text-slate-700">{{ r.section_name }}</td>
                  <td class="px-4 py-2 text-center text-slate-600">{{ r.needed }}</td>
                  <td class="px-4 py-2 text-center text-success-600 font-semibold">{{ r.placed }}</td>
                  <td class="px-4 py-2 text-center font-semibold"
                    :class="r.unplaced > 0 ? 'text-warning-600' : 'text-slate-300'">{{ r.unplaced }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </AppCard>

        <!-- ── Unplaced sessions ──────────────────────────────────────── -->
        <AppCard v-if="unplaceable.length" :padded="false" class="overflow-hidden">
          <div class="px-5 py-3 bg-warning-50 border-b border-warning-100 flex items-start gap-2">
            <ExclamationTriangleIcon class="h-5 w-5 text-warning-500 mt-0.5 shrink-0" />
            <div>
              <p class="text-sm font-semibold text-warning-700">
                {{ unplaceable.length }} session(s) could not be placed
              </p>
              <p class="text-xs text-warning-600 mt-0.5">
                These exceed the available periods for the grade (over-subscribed) or hit a fully-packed
                section. Reduce the grade's load, adjust the bell schedule, or place these manually in the
                Schedules module.
              </p>
            </div>
          </div>
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-50 text-sm">
              <thead>
                <tr class="text-xs text-slate-400 uppercase tracking-wide bg-slate-50">
                  <th class="px-4 py-2.5 text-left">Grade</th>
                  <th class="px-4 py-2.5 text-left">Section</th>
                  <th class="px-4 py-2.5 text-left">Subject</th>
                  <th class="px-4 py-2.5 text-left">Faculty</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-50">
                <tr v-for="(u, ui) in unplaceable" :key="ui" class="hover:bg-slate-50/50">
                  <td class="px-4 py-2 text-slate-500">G{{ u.grade }}</td>
                  <td class="px-4 py-2 font-medium text-slate-700">{{ u.section_name }}</td>
                  <td class="px-4 py-2 text-slate-600">{{ u.subject_code }} — {{ u.subject_name }}</td>
                  <td class="px-4 py-2 text-slate-500">{{ u.faculty_name }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </AppCard>

        <!-- ── Conflict Resolution Panel ──────────────────────────────── -->
        <AppCard v-else-if="conflictSuggestions.length > 0" :padded="false" class="overflow-hidden">
          <div class="px-5 py-3 bg-danger-50 border-b border-danger-100 flex items-center gap-2">
            <ExclamationTriangleIcon class="h-5 w-5 text-danger-500 shrink-0" />
            <div>
              <p class="text-sm font-semibold text-danger-700">
                {{ liveConflictCount }} hard conflict(s) detected — Suggested Fixes
              </p>
              <p class="text-xs text-danger-600 mt-0.5">
                Click "Use This Fix" to apply an alternative. Fixes update the preview and will be saved when you click "Save as Tentative".
              </p>
            </div>
          </div>

          <div class="divide-y divide-slate-100">
            <div v-for="(c, ci) in conflictSuggestions" :key="ci"
              :class="['px-5 py-4', isConflictResolved(c) ? 'opacity-40' : '']">

              <!-- Conflict header -->
              <div class="flex items-center gap-2 mb-3">
                <AppBadge :color="conflictTypeBadge(c.type)">{{ c.type }}</AppBadge>
                <span class="text-sm font-medium text-slate-700">
                  {{ c.entity_label }} — {{ c.day }}
                </span>
                <span v-if="isConflictResolved(c)"
                  class="text-xs text-success-600 font-medium flex items-center gap-1">
                  <CheckCircleIcon class="h-3.5 w-3.5" /> Resolved
                </span>
              </div>

              <!-- The two conflicting slots -->
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3">
                <div class="rounded-lg bg-danger-50 border border-danger-100 px-3 py-2 text-xs space-y-0.5">
                  <p class="font-semibold text-slate-700">{{ c.subject_a }}</p>
                  <p class="text-slate-500">{{ c.faculty_a }}</p>
                  <p class="text-danger-600 font-medium">{{ c.day }} {{ c.time_a }}</p>
                </div>
                <div class="rounded-lg bg-danger-50 border border-danger-100 px-3 py-2 text-xs space-y-0.5">
                  <p class="font-semibold text-slate-700">{{ c.subject_b }}</p>
                  <p class="text-slate-500">{{ c.faculty_b }}</p>
                  <p class="text-danger-600 font-medium">{{ c.day }} {{ c.time_b }}</p>
                </div>
              </div>

              <!-- Alternatives -->
              <div v-if="c.alternatives_a.length || c.alternatives_b.length" class="grid grid-cols-1 sm:grid-cols-2 gap-3">

                <!-- Alternatives for slot A -->
                <div v-if="c.alternatives_a.length">
                  <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
                    Move "{{ c.subject_a }}" to:
                  </p>
                  <div class="space-y-1.5">
                    <div v-for="(alt, ai) in c.alternatives_a" :key="ai"
                      class="flex items-center justify-between rounded-lg bg-success-50 border border-success-100 px-3 py-1.5">
                      <div class="text-xs">
                        <span class="font-semibold text-slate-700">{{ alt.day }}</span>
                        <span class="text-slate-500 ml-1">{{ fmtTime(alt.start_time) }}–{{ fmtTime(alt.end_time) }}</span>
                        <span class="text-slate-400 ml-1">· {{ alt.classroom_name }}</span>
                      </div>
                      <button @click="applyFix(c.req_id_a, alt, ci)"
                        class="text-xs text-success-700 hover:opacity-75 font-semibold ml-2 shrink-0">
                        Use This Fix
                      </button>
                    </div>
                  </div>
                </div>

                <!-- Alternatives for slot B -->
                <div v-if="c.alternatives_b.length">
                  <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
                    Move "{{ c.subject_b }}" to:
                  </p>
                  <div class="space-y-1.5">
                    <div v-for="(alt, bi) in c.alternatives_b" :key="bi"
                      class="flex items-center justify-between rounded-lg bg-success-50 border border-success-100 px-3 py-1.5">
                      <div class="text-xs">
                        <span class="font-semibold text-slate-700">{{ alt.day }}</span>
                        <span class="text-slate-500 ml-1">{{ fmtTime(alt.start_time) }}–{{ fmtTime(alt.end_time) }}</span>
                        <span class="text-slate-400 ml-1">· {{ alt.classroom_name }}</span>
                      </div>
                      <button @click="applyFix(c.req_id_b, alt, ci)"
                        class="text-xs text-success-700 hover:opacity-75 font-semibold ml-2 shrink-0">
                        Use This Fix
                      </button>
                    </div>
                  </div>
                </div>

                <!-- No alternatives available -->
                <div v-if="!c.alternatives_a.length && !c.alternatives_b.length"
                  class="sm:col-span-2 text-xs text-slate-400 italic">
                  No automatic alternatives found. Fix this manually in the Schedules module after saving.
                </div>
              </div>

            </div>
          </div>
        </AppCard>

        <!-- Conflict warning (no suggestions) -->
        <div v-else-if="liveConflictCount > 0"
          class="bg-warning-50 border border-warning-100 rounded-xl px-4 py-3 flex items-start gap-3">
          <ExclamationTriangleIcon class="h-5 w-5 text-warning-500 mt-0.5 shrink-0" />
          <div>
            <p class="text-sm font-semibold text-warning-700">
              {{ liveConflictCount }} hard conflict(s) remain
            </p>
            <p class="text-xs text-warning-600 mt-0.5">
              The generated schedule is conflict-free by construction; this should not normally appear.
              You can still save and review in the Schedules module.
            </p>
          </div>
        </div>

        <!-- Schedule preview table -->
        <AppCard :padded="false" class="overflow-hidden">
          <div class="px-5 py-3 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <h2 class="text-sm font-semibold text-slate-700">Preview — Generated Schedule</h2>
            <div class="flex items-center gap-2">
              <!-- Filter by day -->
              <AppSelect v-model="previewFilter.day" placeholder="All Days" class="w-36">
                <option v-for="d in days" :key="d" :value="d">{{ d }}</option>
              </AppSelect>
              <!-- Filter by faculty -->
              <AppSelect v-model="previewFilter.faculty" placeholder="All Faculty" class="w-40">
                <option v-for="f in previewFaculty" :key="f" :value="f">{{ f }}</option>
              </AppSelect>
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
                  :class="['hover:bg-slate-50/50 transition-colors', hasConflict(s) ? 'bg-danger-50' : '']">
                  <td class="px-4 py-2.5 font-medium text-slate-700">{{ s.day_of_week }}</td>
                  <td class="px-4 py-2.5 text-slate-600 tabular-nums">
                    {{ fmtTime(s.start_time) }} – {{ fmtTime(s.end_time) }}
                  </td>
                  <td class="px-4 py-2.5 text-slate-700">{{ s._faculty_name }}</td>
                  <td class="px-4 py-2.5 text-slate-700">{{ s._subject_name }}</td>
                  <td class="px-4 py-2.5 text-slate-500">{{ s._section_name }}</td>
                  <td class="px-4 py-2.5 text-slate-500">{{ s._classroom_name }}</td>
                  <td class="px-4 py-2.5 text-center">
                    <AppBadge v-if="hasConflict(s)" color="red">
                      <ExclamationTriangleIcon class="h-3 w-3 mr-1" /> Conflict
                    </AppBadge>
                    <AppBadge v-else color="slate">Tentative</AppBadge>
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
        </AppCard>

        <!-- Action buttons -->
        <div class="flex flex-wrap items-center gap-3">
          <AppButton variant="success" size="lg" :loading="applying" @click="applySchedules">
            <ArrowDownTrayIcon v-if="!applying" class="h-4 w-4" />
            {{ applying ? 'Saving…' : 'Save as Tentative' }}
          </AppButton>
          <AppButton variant="secondary" size="lg" @click="discardResult">
            Discard
          </AppButton>
          <p class="text-xs text-slate-400">
            Saving adds these as <strong>tentative</strong> schedules in the Schedules module.
            Existing tentative schedules for this term will be replaced.
          </p>
        </div>
      </template>

      <!-- ── Recent Jobs ─────────────────────────────────────────────── -->
      <AppCard v-if="recentJobs.length" :padded="false" class="overflow-hidden">
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
              <th class="px-4 py-2.5 text-center">Unplaced</th>
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
                    job.hard_conflicts === 0 ? 'text-success-600' : 'text-danger-600'
                  ]">{{ job.hard_conflicts }}</span>
              </td>
              <td class="px-4 py-2.5 text-center text-slate-500 tabular-nums text-xs">
                {{ job.fitness_score != null ? Math.abs(job.fitness_score) : '—' }}
              </td>
              <td class="px-4 py-2.5 text-center">
                <AppBadge :color="statusClass(job.status)">{{ job.status }}</AppBadge>
              </td>
              <td class="px-4 py-2.5 text-center">
                <button v-if="job.status === 'completed'"
                  @click="loadJob(job)"
                  class="text-xs text-indigo-600 hover:text-indigo-700 font-medium">
                  Load
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </AppCard>

    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, reactive } from 'vue'
import { Head } from '@inertiajs/vue3'
import axios from 'axios'
import Swal from 'sweetalert2'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppCard from '@/Components/AppCard.vue'
import AppButton from '@/Components/AppButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppSelect from '@/Components/AppSelect.vue'
import {
  SparklesIcon,
  CheckCircleIcon,
  ExclamationTriangleIcon,
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
})

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

const generating         = ref(false)
const result             = ref(null)   // the job object returned from the API
const conflictSuggestions = ref([])    // suggestions returned alongside the job
const resolvedConflicts  = ref(new Set()) // indices of conflicts user has fixed
const unplaceable        = ref([])     // sessions that could not be placed
const sectionReport      = ref([])     // per-section placed vs needed coverage

async function runGenerate() {
  if (!canGenerate.value || generating.value) return
  generating.value    = true
  result.value        = null
  conflictSuggestions.value = []
  resolvedConflicts.value   = new Set()
  unplaceable.value   = []
  sectionReport.value = []

  try {
    const { data } = await axios.post('/faculty-loading/auto-schedule/generate', {
      school_year_id:   form.school_year_id,
      academic_term_id: form.academic_term_id,
    })

    result.value             = data.job
    conflictSuggestions.value = data.conflict_suggestions ?? []
    unplaceable.value        = data.unplaceable ?? []
    sectionReport.value      = data.section_report ?? []

    if (data.warning) {
      await Swal.fire('Warning', data.warning, 'warning')
    } else if (data.job.hard_conflicts === 0) {
      await Swal.fire('Success', 'Conflict-free schedule generated successfully!', 'success')
    }
  } catch (err) {
    await Swal.fire('Error', err.response?.data?.message ?? 'Generation failed. Please try again.', 'error')
  } finally {
    generating.value = false
  }
}

// ── Conflict resolution ──────────────────────────────────────────────────────

/** Live conflict count based on the current (possibly patched) schedule entries. */
const liveConflictCount = computed(() => {
  if (!result.value?.schedules) return 0
  return result.value.schedules.filter(s => hasConflict(s)).length > 0
    ? result.value.schedules.filter(s => hasConflict(s)).length
    : 0
})

function isConflictResolved(conflict) {
  return resolvedConflicts.value.has(conflict.req_id_a) || resolvedConflicts.value.has(conflict.req_id_b)
}

/** Maps a conflict type to an AppBadge color. */
function conflictTypeBadge(type) {
  const map = {
    faculty: 'purple',
    room:    'blue',
    section: 'orange',
  }
  return map[type] ?? 'slate'
}

/**
 * Apply a conflict-resolution suggestion: patch the matching schedule entry
 * in-place (local only; included in the apply POST payload automatically).
 */
function applyFix(reqId, alt, conflictIndex) {
  if (!result.value?.schedules) return

  const entry = result.value.schedules.find(s => s._req_id === reqId)
  if (!entry) return

  entry.day_of_week     = alt.day
  entry.start_time      = alt.start_time
  entry.end_time        = alt.end_time
  entry.classroom_id    = alt.classroom_id
  entry._classroom_name = alt.classroom_name
  entry._classroom_code = alt.classroom_code

  resolvedConflicts.value.add(reqId)
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
    // Electives intentionally share a reserved window with no dedicated
    // classroom (classroom_id is null) — only compare non-null values so
    // those parallel, unrelated sessions aren't flagged as room conflicts.
    return (
      (schedule.user_id      != null && o.user_id      === schedule.user_id)      ||
      (schedule.classroom_id != null && o.classroom_id === schedule.classroom_id) ||
      (schedule.section_id   != null && o.section_id   === schedule.section_id)
    )
  })
}

// ── Apply ───────────────────────────────────────────────────────────────────

const applying = ref(false)

async function applySchedules() {
  if (!result.value?.id || applying.value) return
  applying.value = true

  try {
    // Send local (possibly patched) schedules so conflict fixes are persisted
    const { data } = await axios.post(
      `/faculty-loading/auto-schedule/jobs/${result.value.id}/apply`,
      { schedules: result.value.schedules }
    )
    await Swal.fire('Success', data.message, 'success')
  } catch (err) {
    const conflicts = err.response?.data?.conflicts ?? []
    const message   = err.response?.data?.message ?? 'Failed to save schedules.'
    await Swal.fire({
      title: 'Error',
      icon:  'error',
      html:  conflicts.length
        ? `<p>${message}</p><ul class="text-left text-sm mt-2">${conflicts.map(c => `<li>• ${c}</li>`).join('')}</ul>`
        : message,
    })
  } finally {
    applying.value = false
  }
}

function discardResult() {
  result.value             = null
  conflictSuggestions.value = []
  resolvedConflicts.value   = new Set()
  unplaceable.value        = []
  sectionReport.value      = []
}

// ── History ─────────────────────────────────────────────────────────────────

const recentJobs = ref(props.recentJobs ?? [])

function loadJob(job) {
  // Load a past job result into the preview panel (no conflict suggestions for history)
  axios.get(`/faculty-loading/auto-schedule/jobs/${job.id}`)
    .then(({ data }) => {
      result.value              = data
      conflictSuggestions.value = []
      resolvedConflicts.value   = new Set()
      unplaceable.value         = []
      sectionReport.value       = []
      form.academic_term_id     = data.academic_term_id
    })
    .catch(() => {
      Swal.fire('Error', 'Could not load the selected job.', 'error')
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

/** Maps a generation-job status to an AppBadge color. */
function statusClass(status) {
  const map = {
    completed: 'green',
    running:   'blue',
    failed:    'red',
    pending:   'slate',
  }
  return map[status] ?? 'slate'
}
</script>
