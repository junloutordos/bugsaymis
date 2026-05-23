<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import axios from 'axios'
import Swal from 'sweetalert2'
import {
  computeStudentGrade,
  adjectivalColor,
} from '@/Utils/ClassRecord/gradeUtils.js'
import ManageStudentsModal from './ManageStudentsModal.vue'
import { LockClosedIcon, UsersIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  classRecordId:   { type: Number,  required: true },
  quarterNumber:   { type: Number,  required: true },    // 1–4
  quarterData:     { type: Object,  default: null },     // { id, is_locked, assessments, students }
  gradingOption:   { type: Object,  required: true },    // { categories: [...] }
  stanineLookup:   { type: Array,   required: true },
  previousGrades:  { type: Object,  default: () => ({}) }, // { studentId: runningGrade }
  isLocked:        { type: Boolean, default: false },
})

const emit = defineEmits(['reload'])

// ── Derived data ──────────────────────────────────────────────────────────────

const categories = computed(() =>
  [...(props.gradingOption?.categories ?? [])].sort((a, b) => a.sort_order - b.sort_order)
)

const students = computed(() =>
  [...(props.quarterData?.students ?? [])].sort((a, b) => a.sequence_number - b.sequence_number)
)

// Assessments grouped by category
const assessmentsByCategory = computed(() => {
  const map = {}
  for (const cat of categories.value) {
    map[cat.id] = (props.quarterData?.assessments ?? [])
      .filter(a => a.grading_category_id === cat.id)
      .sort((a, b) => a.sort_order - b.sort_order)
  }
  return map
})

// Flat list of assessments in display order
const allAssessments = computed(() =>
  categories.value.flatMap(cat => assessmentsByCategory.value[cat.id] ?? [])
)

// ── Local score state ─────────────────────────────────────────────────────────

const scores  = ref({})   // { `${studentId}_${assessmentId}`: number|null }
const loading = ref(true)
const saveStatus = ref('idle')  // 'idle' | 'saving' | 'saved' | 'error'
let   saveTimer  = null
const dirtyKeys  = ref(new Set())

async function loadScores() {
  if (!props.quarterData) return
  loading.value = true
  try {
    const { data } = await axios.get(
      route('class-records.scores.index', { classRecord: props.classRecordId, q: props.quarterNumber })
    )
    // data is { "studentId_assessmentId": score }
    scores.value = {}
    for (const [key, val] of Object.entries(data)) {
      scores.value[key] = val !== null ? Number(val) : null
    }
  } catch (err) {
    console.error('Failed to load scores', err)
  } finally {
    loading.value = false
  }
}

onMounted(loadScores)
watch(() => props.quarterData?.id, loadScores)

function scoreKey(studentId, assessmentId) {
  return `${studentId}_${assessmentId}`
}

function getScore(studentId, assessmentId) {
  const key = scoreKey(studentId, assessmentId)
  return scores.value[key] ?? null
}

function setScore(studentId, assessmentId, val) {
  const key = scoreKey(studentId, assessmentId)
  const parsed = val === '' || val === null || val === undefined ? null : Number(val)
  scores.value[key] = parsed
  dirtyKeys.value.add(key)
  scheduleSave()
}

// ── Debounced auto-save ───────────────────────────────────────────────────────

function scheduleSave() {
  clearTimeout(saveTimer)
  saveStatus.value = 'idle'
  saveTimer = setTimeout(flushSave, 1500)
}

async function flushSave() {
  if (!dirtyKeys.value.size || props.isLocked) return

  saveStatus.value = 'saving'
  const keysToSave = [...dirtyKeys.value]
  dirtyKeys.value  = new Set()

  const payload = keysToSave.map(key => {
    const [studentId, assessmentId] = key.split('_').map(Number)
    return { student_id: studentId, assessment_id: assessmentId, score: scores.value[key] }
  })

  try {
    await axios.post(
      route('class-records.scores.upsert', { classRecord: props.classRecordId, q: props.quarterNumber }),
      { scores: payload }
    )
    saveStatus.value = 'saved'
    setTimeout(() => { if (saveStatus.value === 'saved') saveStatus.value = 'idle' }, 3000)
  } catch (err) {
    saveStatus.value = 'error'
    dirtyKeys.value  = new Set([...dirtyKeys.value, ...keysToSave])
    console.error('Auto-save failed', err)
  }
}

// ── Computed grades ───────────────────────────────────────────────────────────

const computedGrades = computed(() => {
  const result = {}
  const catsForComputation = categories.value.map(cat => ({
    id:          cat.id,
    code:        cat.code,
    weight:      Number(cat.weight),
    assessments: (assessmentsByCategory.value[cat.id] ?? []).map(a => ({
      id:       a.id,
      maxScore: Number(a.max_score),
    })),
  }))

  for (const student of students.value) {
    const sid      = student.id
    const scoreMap = {}
    for (const a of allAssessments.value) {
      scoreMap[a.id] = getScore(sid, a.id)
    }
    const prevGrade = props.previousGrades[sid] ?? null
    result[sid] = computeStudentGrade(
      catsForComputation,
      scoreMap,
      props.stanineLookup,
      prevGrade,
      props.quarterNumber,
    )
  }
  return result
})

// Class statistics (GE scale: lower = better, 1.000 = Excellent, 5.000 = Failed)
const classStats = computed(() => {
  const grades = students.value.map(s => computedGrades.value[s.id]).filter(Boolean)
  if (!grades.length) return null

  const twPcts = grades.map(g => g.twPct * 100)
  const ges    = grades.map(g => g.gradeEquivalent)
  const adjCounts = {}
  for (const g of grades) {
    adjCounts[g.adjectivalEquivalent] = (adjCounts[g.adjectivalEquivalent] ?? 0) + 1
  }
  const avgGE = ges.reduce((s, v) => s + v, 0) / ges.length
  return {
    count:   grades.length,
    avg:     twPcts.reduce((s, v) => s + v, 0) / twPcts.length,
    avgGE,
    bestGE:  Math.min(...ges),   // lowest GE = best performance
    worstGE: Math.max(...ges),   // highest GE = weakest performance
    adjCounts,
  }
})

// At-risk students (by quarter grade this quarter)
const atRiskCounts = computed(() => {
  const failed = [], conditional = [], fair = []
  for (const student of students.value) {
    const grade = computedGrades.value[student.id]
    if (!grade) continue
    const adj = (grade.adjectivalEquivalent ?? '').toLowerCase()
    if (adj === 'failed') failed.push(student)
    else if (adj.includes('condition')) conditional.push(student)
    else if (adj === 'fair') fair.push(student)
  }
  return { failed, conditional, fair }
})

function rowBg(studentId, isEven) {
  const grade = computedGrades.value[studentId]
  const adj = (grade?.adjectivalEquivalent ?? '').toLowerCase()
  if (adj === 'failed') return 'bg-red-50'
  if (adj.includes('condition')) return 'bg-orange-50'
  if (adj === 'fair') return 'bg-amber-50'
  return isEven ? 'bg-white' : 'bg-slate-50/40'
}

// ── Keyboard navigation ───────────────────────────────────────────────────────

const inputRefs = ref({})

function setRef(studentId, assessmentId, el) {
  if (el) inputRefs.value[scoreKey(studentId, assessmentId)] = el
}

function onKeydown(e, studentIdx, assessmentIdx) {
  if (e.key === 'Enter') {
    e.preventDefault()
    const nextStudent = students.value[studentIdx + 1]
    if (nextStudent) {
      const assessment = allAssessments.value[assessmentIdx]
      const el = inputRefs.value[scoreKey(nextStudent.id, assessment.id)]
      el?.focus()
    }
  }
  // Tab is handled by browser naturally
}

// ── Quarter lock ──────────────────────────────────────────────────────────────

const locking = ref(false)

async function lockQuarter() {
  const result = await Swal.fire({
    title:             `Lock Quarter ${props.quarterNumber}?`,
    text:              'Score entry will be disabled. Administrators can unlock it.',
    icon:              'warning',
    showCancelButton:  true,
    confirmButtonText: 'Yes, Lock',
    confirmButtonColor:'#d97706',
  })
  if (!result.isConfirmed) return

  locking.value = true
  try {
    await flushSave()
    await axios.post(
      route('class-records.quarters.lock', { classRecord: props.classRecordId, q: props.quarterNumber })
    )
    emit('reload')
  } catch (err) {
    Swal.fire('Error', err.response?.data?.message ?? 'Failed to lock.', 'error')
  } finally {
    locking.value = false
  }
}

// ── Student management modal ──────────────────────────────────────────────────

const showStudentsModal = ref(false)

function onStudentsSaved() {
  emit('reload')
  loadScores()
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function scoreOutOfRange(studentId, assessment) {
  const val = getScore(studentId, assessment.id)
  if (val === null) return false
  return val < 0 || val > Number(assessment.max_score)
}

const showRunning = computed(() => props.quarterNumber > 1)
</script>

<template>
  <div class="space-y-4">

    <!-- Toolbar -->
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-2">
        <!-- Save indicator -->
        <span v-if="saveStatus === 'saving'" class="text-xs text-slate-400">Saving…</span>
        <span v-if="saveStatus === 'saved'"  class="text-xs text-emerald-600 font-medium">Saved ✓</span>
        <span v-if="saveStatus === 'error'"  class="text-xs text-red-500">Save failed — will retry</span>
      </div>
      <div class="flex items-center gap-2">
        <button @click="showStudentsModal = true"
          class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 text-xs font-medium">
          <UsersIcon class="h-3.5 w-3.5" /> Manage Students
        </button>
      </div>
    </div>

    <!-- At-risk summary chips -->
    <div v-if="atRiskCounts.failed.length || atRiskCounts.conditional.length || atRiskCounts.fair.length"
      class="flex flex-wrap items-center gap-2 text-xs">
      <span class="text-slate-400 font-medium">At Risk (Q{{ quarterNumber }}):</span>
      <span v-if="atRiskCounts.failed.length"
        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-red-100 text-red-700 font-semibold">
        {{ atRiskCounts.failed.length }} Failed
      </span>
      <span v-if="atRiskCounts.conditional.length"
        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-orange-100 text-orange-700 font-semibold">
        {{ atRiskCounts.conditional.length }} Conditional
      </span>
      <span v-if="atRiskCounts.fair.length"
        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 font-semibold">
        {{ atRiskCounts.fair.length }} Fair
      </span>
    </div>

    <!-- Locked banner -->
    <div v-if="isLocked"
      class="flex items-center gap-2 bg-amber-50 border border-amber-200 text-amber-700 rounded-lg px-4 py-2.5 text-sm">
      <LockClosedIcon class="h-4 w-4 shrink-0" />
      This quarter is locked. Unlock it (admin only) to allow score changes.
    </div>

    <!-- Loading -->
    <div v-if="loading" class="py-12 text-center text-slate-400 text-sm">Loading scores…</div>

    <!-- No students -->
    <div v-else-if="!students.length" class="py-12 text-center text-slate-400 text-sm">
      No students in roster. Click "Manage Students" to add them.
    </div>

    <!-- No assessments -->
    <div v-else-if="!allAssessments.length" class="py-12 text-center text-slate-400 text-sm">
      No assessments configured. Go to the Setup tab to add assessments first.
    </div>

    <!-- Score grid -->
    <div v-else class="overflow-x-auto rounded-xl border border-slate-100 shadow-sm">
      <table class="text-xs border-collapse" style="min-width: max-content;">
        <!-- ── Two-row header ──────────────────────────────────────── -->
        <thead>
          <!-- Row 1: Group headers -->
          <tr class="bg-slate-100">
            <!-- Fixed left cols spanning 2 rows -->
            <th rowspan="2" class="sticky left-0 z-20 bg-slate-100 border border-slate-200 px-2 py-1.5 text-slate-500 font-semibold w-8">#</th>
            <th rowspan="2" class="sticky left-8 z-20 bg-slate-100 border border-slate-200 px-2 py-1.5 text-slate-500 font-semibold whitespace-nowrap">Family Name</th>
            <th rowspan="2" class="sticky left-32 z-20 bg-slate-100 border border-slate-200 px-2 py-1.5 text-slate-500 font-semibold whitespace-nowrap">Given Name</th>
            <th rowspan="2" class="sticky left-56 z-20 bg-slate-100 border border-slate-200 px-2 py-1.5 text-slate-500 font-semibold w-10">MI</th>
            <th rowspan="2" class="sticky left-64 z-20 bg-slate-100 border border-slate-200 px-2 py-1.5 text-slate-500 font-semibold w-10">Sex</th>

            <!-- Category group headers -->
            <th v-for="cat in categories" :key="cat.id"
              :colspan="(assessmentsByCategory[cat.id]?.length ?? 0) + 3"
              class="border border-slate-200 px-2 py-1.5 text-center font-bold text-indigo-700 bg-indigo-50 whitespace-nowrap">
              {{ cat.name }} ({{ Math.round(cat.weight * 100) }}%)
            </th>

            <!-- Summary group -->
            <th :colspan="showRunning ? 6 : 3"
              class="border border-slate-200 px-2 py-1.5 text-center font-bold text-slate-600 bg-slate-50">
              Summary
            </th>
          </tr>

          <!-- Row 2: Sub-headers -->
          <tr class="bg-slate-50">
            <!-- Per category: assessments + T, %, W% -->
            <template v-for="cat in categories" :key="cat.id">
              <!-- Individual assessment columns -->
              <th v-for="a in assessmentsByCategory[cat.id]" :key="a.id"
                class="border border-slate-200 px-2 py-1 text-center font-semibold text-slate-600 min-w-[56px]">
                <div>{{ cat.code }}{{ a.assessment_number }}</div>
                <div class="text-[10px] text-slate-400 font-normal">/{{ a.max_score }}</div>
              </th>
              <!-- T, %, W% -->
              <th class="border border-slate-200 px-2 py-1 text-center font-semibold text-slate-500 min-w-[44px]">T</th>
              <th class="border border-slate-200 px-2 py-1 text-center font-semibold text-slate-500 min-w-[48px]">%</th>
              <th class="border border-slate-200 px-2 py-1 text-center font-semibold text-slate-500 min-w-[48px]">W%</th>
            </template>

            <!-- Summary: TW%, Score%, GE, Adj, [Running GE, Running Adj] for Q2+ -->
            <th class="border border-slate-200 px-2 py-1 text-center font-semibold text-slate-600 min-w-[52px]">TW%</th>
            <th class="border border-slate-200 px-2 py-1 text-center font-semibold text-slate-600 min-w-[52px]">Score%</th>
            <th class="border border-slate-200 px-2 py-1 text-center font-semibold text-slate-600 min-w-[44px]">GE</th>
            <template v-if="showRunning">
              <th class="border border-slate-200 px-2 py-1 text-center font-semibold text-slate-600 min-w-[56px]">Run GE</th>
              <th class="border border-slate-200 px-2 py-1 text-center font-semibold text-slate-600 min-w-[80px]">Adjectival</th>
              <th class="border border-slate-200 px-2 py-1 text-center font-semibold text-slate-600 min-w-[80px]">Run Adj</th>
            </template>
            <template v-else>
              <th class="border border-slate-200 px-2 py-1 text-center font-semibold text-slate-600 min-w-[90px]">Adjectival</th>
            </template>
          </tr>
        </thead>

        <!-- ── Student rows ───────────────────────────────────────── -->
        <tbody>
          <tr v-for="(student, sIdx) in students" :key="student.id"
            :class="rowBg(student.id, sIdx % 2 === 0)">

            <!-- Fixed left columns -->
            <td :class="['sticky left-0 z-10 border border-slate-200 px-2 py-1 text-center text-slate-400', rowBg(student.id, sIdx % 2 === 0)]">
              {{ student.sequence_number }}
            </td>
            <td :class="['sticky left-8 z-10 border border-slate-200 px-2 py-1 font-medium text-slate-700 whitespace-nowrap', rowBg(student.id, sIdx % 2 === 0)]">
              {{ student.family_name }}
            </td>
            <td :class="['sticky left-32 z-10 border border-slate-200 px-2 py-1 text-slate-600 whitespace-nowrap', rowBg(student.id, sIdx % 2 === 0)]">
              {{ student.given_name }}
            </td>
            <td :class="['sticky left-56 z-10 border border-slate-200 px-2 py-1 text-slate-500', rowBg(student.id, sIdx % 2 === 0)]">
              {{ student.middle_initial ?? '' }}
            </td>
            <td :class="['sticky left-64 z-10 border border-slate-200 px-2 py-1 text-center text-slate-500', rowBg(student.id, sIdx % 2 === 0)]">
              {{ student.sex }}
            </td>

            <!-- Score inputs + computed per category -->
            <template v-for="cat in categories" :key="cat.id">
              <!-- Assessment inputs -->
              <td v-for="a in assessmentsByCategory[cat.id]" :key="a.id"
                class="border border-slate-200 p-0.5">
                <input
                  type="number"
                  :min="0" :max="a.max_score" step="0.5"
                  :value="getScore(student.id, a.id) ?? ''"
                  :disabled="isLocked"
                  :ref="el => setRef(student.id, a.id, el)"
                  @input="setScore(student.id, a.id, $event.target.value)"
                  @keydown="onKeydown($event, sIdx, allAssessments.indexOf(a))"
                  :class="[
                    'w-14 text-center rounded px-1 py-0.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400 disabled:bg-slate-50 disabled:text-slate-400',
                    scoreOutOfRange(student.id, a)
                      ? 'border border-red-400 bg-red-50 text-red-700'
                      : 'border-0 bg-transparent',
                  ]"
                />
              </td>

              <!-- T, %, W% computed -->
              <template v-if="computedGrades[student.id]">
                <td class="border border-slate-200 px-2 py-1 text-center text-slate-600 bg-slate-50/60 tabular-nums">
                  {{ computedGrades[student.id].categoryResults.find(r => r.catId === cat.id)?.total.toFixed(1) ?? '—' }}
                </td>
                <td class="border border-slate-200 px-2 py-1 text-center text-slate-600 bg-slate-50/60 tabular-nums">
                  {{ (computedGrades[student.id].categoryResults.find(r => r.catId === cat.id)?.percentage * 100).toFixed(1) ?? '—' }}%
                </td>
                <td class="border border-slate-200 px-2 py-1 text-center text-slate-600 bg-slate-50/60 tabular-nums">
                  {{ (computedGrades[student.id].categoryResults.find(r => r.catId === cat.id)?.weightedPercentage * 100).toFixed(1) ?? '—' }}%
                </td>
              </template>
              <template v-else>
                <td v-for="_ in 3" :key="_" class="border border-slate-200 px-2 py-1 text-center text-slate-300 bg-slate-50/60">—</td>
              </template>
            </template>

            <!-- Summary columns -->
            <template v-if="computedGrades[student.id]">
              <td class="border border-slate-200 px-2 py-1 text-center font-semibold text-slate-700 bg-indigo-50/50 tabular-nums">
                {{ (computedGrades[student.id].twPct * 100).toFixed(2) }}%
              </td>
              <td class="border border-slate-200 px-2 py-1 text-center text-slate-700 bg-indigo-50/50 tabular-nums">
                {{ computedGrades[student.id].percentScore.toFixed(2) }}%
              </td>
              <td class="border border-slate-200 px-2 py-1 text-center font-bold text-slate-800 bg-indigo-50/50 tabular-nums">
                {{ computedGrades[student.id].gradeEquivalent.toFixed(3) }}
              </td>
              <template v-if="showRunning">
                <td class="border border-slate-200 px-2 py-1 text-center font-bold text-slate-800 bg-emerald-50/50 tabular-nums">
                  {{ computedGrades[student.id].runningGrade.toFixed(3) }}
                </td>
                <td :class="['border border-slate-200 px-2 py-1 text-center text-[11px]', adjectivalColor(computedGrades[student.id].adjectivalEquivalent)]">
                  {{ computedGrades[student.id].adjectivalEquivalent }}
                </td>
                <td :class="['border border-slate-200 px-2 py-1 text-center text-[11px]', adjectivalColor(computedGrades[student.id].runningAdjectival)]">
                  {{ computedGrades[student.id].runningAdjectival }}
                </td>
              </template>
              <template v-else>
                <td :class="['border border-slate-200 px-2 py-1 text-center text-[11px]', adjectivalColor(computedGrades[student.id].adjectivalEquivalent)]">
                  {{ computedGrades[student.id].adjectivalEquivalent }}
                </td>
              </template>
            </template>
            <template v-else>
              <td v-for="_ in showRunning ? 6 : 3" :key="_" class="border border-slate-200 px-2 py-1 text-center text-slate-300">—</td>
            </template>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Class statistics summary -->
    <div v-if="classStats && students.length" class="bg-white rounded-xl border border-slate-100 shadow-sm p-4">
      <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">Class Summary — Q{{ quarterNumber }}</h3>
      <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 text-sm">
        <div class="bg-slate-50 rounded-lg p-3 text-center">
          <div class="text-xs text-slate-400">Students</div>
          <div class="text-lg font-bold text-slate-800 tabular-nums">{{ classStats.count }}</div>
        </div>
        <div class="bg-slate-50 rounded-lg p-3 text-center">
          <div class="text-xs text-slate-400">Avg Score</div>
          <div class="text-lg font-bold text-slate-800 tabular-nums">{{ classStats.avg.toFixed(2) }}%</div>
        </div>
        <div class="bg-indigo-50 rounded-lg p-3 text-center">
          <div class="text-xs text-slate-400">Avg GE</div>
          <div class="text-lg font-bold text-indigo-700 tabular-nums">{{ classStats.avgGE.toFixed(3) }}</div>
        </div>
        <div class="bg-emerald-50 rounded-lg p-3 text-center">
          <div class="text-xs text-slate-400">Best GE</div>
          <div class="text-lg font-bold text-emerald-700 tabular-nums">{{ classStats.bestGE.toFixed(3) }}</div>
        </div>
        <div class="bg-red-50 rounded-lg p-3 text-center">
          <div class="text-xs text-slate-400">Weakest GE</div>
          <div class="text-lg font-bold text-red-600 tabular-nums">{{ classStats.worstGE.toFixed(3) }}</div>
        </div>
      </div>
      <!-- Adjectival distribution -->
      <div class="mt-3 flex flex-wrap gap-2">
        <div v-for="(count, adj) in classStats.adjCounts" :key="adj"
          class="flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-slate-50 border border-slate-100 text-xs">
          <span :class="adjectivalColor(adj)">{{ adj }}</span>
          <span class="font-semibold text-slate-700">{{ count }}</span>
          <span class="text-slate-400">({{ ((count / classStats.count) * 100).toFixed(0) }}%)</span>
        </div>
      </div>
    </div>

    <!-- Lock button -->
    <div v-if="!isLocked && students.length && allAssessments.length" class="flex justify-end">
      <button @click="lockQuarter" :disabled="locking"
        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-amber-300 text-amber-700 bg-amber-50 hover:bg-amber-100 text-sm font-medium disabled:opacity-50">
        <LockClosedIcon class="h-4 w-4" />
        {{ locking ? 'Locking…' : `Lock Quarter ${quarterNumber}` }}
      </button>
    </div>

  </div>

  <!-- Manage students modal -->
  <ManageStudentsModal
    :show="showStudentsModal"
    :class-record-id="classRecordId"
    :quarter="quarterNumber"
    :initial-students="students"
    @close="showStudentsModal = false"
    @saved="onStudentsSaved"
  />
</template>
