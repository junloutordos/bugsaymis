<script setup>
import { ref, computed, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { badgeBase } from '@/Composables/useStatusBadge.js'
import Swal from 'sweetalert2'
import {
  LockClosedIcon,
  LockOpenIcon,
  ArrowLeftIcon,
  CheckCircleIcon,
  ArrowDownTrayIcon,
  PlusIcon,
  XMarkIcon,
  DocumentDuplicateIcon,
} from '@heroicons/vue/24/outline'
import ScoreGrid from './components/ScoreGrid.vue'
import { adjectivalColor } from '@/Utils/ClassRecord/gradeUtils.js'

const props = defineProps({
  classRecord:        Object,
  isAdmin:            { type: Boolean, default: false },
  stanineLookup:      { type: Array, default: () => [] },
  isCurrentSY:        { type: Boolean, default: true },
  currentSYName:      { type: String, default: null },
  sameSubjectRecords: { type: Array, default: () => [] },
})

const page = usePage()

// ── Status badge ──────────────────────────────────────────────────────────────
function statusBadge(status) {
  return {
    draft:     'bg-slate-100 text-slate-600',
    submitted: 'bg-blue-100 text-blue-700',
    checked:   'bg-emerald-100 text-emerald-700',
  }[status] ?? 'bg-slate-100 text-slate-600'
}

// ── Quarter tabs ──────────────────────────────────────────────────────────────
const activeQuarter = ref(1)
const activeSubTab  = ref('setup')  // 'setup' | 'scores'

const currentQuarterData = computed(() =>
  props.classRecord.quarters?.find(q => q.quarter === activeQuarter.value) ?? null
)

const isLocked   = computed(() => currentQuarterData.value?.is_locked ?? false)
const isReadOnly = computed(() => !props.isCurrentSY)  // past school year → fully read-only

// ── Assessment setup ──────────────────────────────────────────────────────────
// Build editable assessment rows from the grading option categories
const assessmentDraft = ref({})

function buildDraft(quarter) {
  const draft = {}
  for (const cat of props.classRecord.grading_option?.categories ?? []) {
    draft[cat.id] = []
    const existing = (quarter?.assessments ?? [])
      .filter(a => a.grading_category_id === cat.id)
      .sort((a, b) => a.assessment_number - b.assessment_number)

    // Show at least max_assessments rows OR all saved rows, whichever is more
    const rowCount = Math.max(cat.max_assessments, existing.length)
    for (let n = 1; n <= rowCount; n++) {
      const found = existing.find(a => a.assessment_number === n)
      draft[cat.id].push({
        grading_category_id: cat.id,
        assessment_number:   n,
        title:               found?.title ?? '',
        activity_date:       found?.activity_date ?? '',
        max_score:           found?.max_score ?? '',
        _saved:              !!found,
        _db_id:              found?.id ?? null,  // track DB id for delete validation
      })
    }
  }
  return draft
}

function addAssessmentRow(catId) {
  const rows = assessmentDraft.value[catId]
  const nextNum = rows.length + 1
  rows.push({
    grading_category_id: catId,
    assessment_number:   nextNum,
    title:               '',
    activity_date:       '',
    max_score:           '',
    _saved:              false,
    _db_id:              null,
  })
}

async function removeAssessmentRow(catId, idx) {
  const row = assessmentDraft.value[catId][idx]

  // If it has a DB assessment ID, check if scores exist
  if (row._db_id) {
    try {
      const { data } = await axios.get(
        route('class-records.scores.index', { classRecord: props.classRecord.id, q: activeQuarter.value })
      )
      const hasScores = Object.keys(data).some(k => k.startsWith(`_${row._db_id}`) || k.endsWith(`_${row._db_id}`))
      if (hasScores) {
        await Swal.fire('Cannot Remove', 'This assessment already has scores entered. Clear all scores for it first.', 'warning')
        return
      }
    } catch { /* allow removal if check fails */ }
  }

  assessmentDraft.value[catId].splice(idx, 1)
  // Re-number remaining rows
  assessmentDraft.value[catId].forEach((r, i) => { r.assessment_number = i + 1 })
}

const savingSetup = ref(false)
const setupErrors = ref([])

watch(activeQuarter, (q) => {
  activeSubTab.value    = 'setup'
  assessmentDraft.value = buildDraft(currentQuarterData.value)
  savingSetup.value     = false
  setupErrors.value     = []
}, { immediate: true })

watch(() => props.classRecord.quarters, () => {
  assessmentDraft.value = buildDraft(currentQuarterData.value)
}, { deep: true })

async function saveSetup() {
  savingSetup.value = true
  setupErrors.value = []

  const assessments = Object.values(assessmentDraft.value).flat().filter(a => a.title && a.max_score)

  if (!assessments.length) {
    setupErrors.value = ['Add at least one assessment with a title and max score.']
    savingSetup.value = false
    return
  }

  try {
    await axios.post(
      route('class-records.assessments.upsert', { classRecord: props.classRecord.id, q: activeQuarter.value }),
      { assessments }
    )
    await Swal.fire({ icon: 'success', title: 'Setup saved!', timer: 1000, showConfirmButton: false })
    router.reload({ only: ['classRecord'] })
  } catch (err) {
    if (err.response?.status === 422) {
      setupErrors.value = Object.values(err.response.data.errors ?? {}).flat()
    } else {
      setupErrors.value = [err.response?.data?.message ?? 'Failed to save setup.']
    }
  } finally {
    savingSetup.value = false
  }
}

// ── Copy assessments ──────────────────────────────────────────────────────────

const copyingFrom = ref(false)
const showCopyFromRecordModal = ref(false)

const currentHasAssessments = computed(() =>
  (currentQuarterData.value?.assessments?.length ?? 0) > 0
)

const quartersWithAssessments = computed(() =>
  (props.classRecord.quarters ?? [])
    .filter(q => q.quarter !== activeQuarter.value && (q.assessments?.length ?? 0) > 0)
    .sort((a, b) => a.quarter - b.quarter)
)

const sameSubjectRecords = computed(() =>
  (props.sameSubjectRecords ?? []).filter(r => r.id !== props.classRecord.id)
)

async function copyFromQuarter(sourceQ) {
  copyingFrom.value = true
  try {
    await axios.post(
      route('class-records.assessments.copy-from', { classRecord: props.classRecord.id, q: activeQuarter.value }),
      { source_quarter: sourceQ }
    )
    router.reload({ only: ['classRecord'] })
  } catch (err) {
    Swal.fire('Error', err.response?.data?.message ?? 'Failed to copy assessments.', 'error')
  } finally {
    copyingFrom.value = false
  }
}

const copyFromRecordId   = ref(null)
const copyFromRecordQ    = ref(1)
const copyingFromRecord  = ref(false)

async function copyFromRecord() {
  if (!copyFromRecordId.value) return
  copyingFromRecord.value = true
  try {
    await axios.post(
      route('class-records.assessments.copy-from-record', { classRecord: props.classRecord.id, q: activeQuarter.value }),
      { source_class_record_id: copyFromRecordId.value, source_quarter: copyFromRecordQ.value }
    )
    showCopyFromRecordModal.value = false
    router.reload({ only: ['classRecord'] })
  } catch (err) {
    Swal.fire('Error', err.response?.data?.message ?? 'Failed to copy.', 'error')
  } finally {
    copyingFromRecord.value = false
  }
}

// ── Final annual grades ───────────────────────────────────────────────────────
const showFinalGrades    = ref(false)
const finalGrades        = ref([])
const finalGradesLoading = ref(false)
const finalGradesError   = ref(null)

const allQuartersExist = computed(() =>
  [1, 2, 3, 4].every(q => (props.classRecord.quarters ?? []).some(qt => qt.quarter === q))
)

async function loadFinalGrades() {
  finalGradesLoading.value = true
  finalGradesError.value   = null
  try {
    const { data } = await axios.get(route('class-records.final-grades', props.classRecord.id))
    finalGrades.value = data.students ?? []
    if (data.message && !data.students?.length) {
      finalGradesError.value = data.message
    }
  } catch (err) {
    finalGradesError.value = err.response?.data?.message ?? 'Failed to load final grades.'
  } finally {
    finalGradesLoading.value = false
  }
}

function openFinalGrades() {
  showFinalGrades.value = true
  if (!finalGrades.value.length && !finalGradesLoading.value) {
    loadFinalGrades()
  }
}

// ── Quarter lock / unlock ─────────────────────────────────────────────────────
async function lockQuarter() {
  const result = await Swal.fire({
    title: `Lock Quarter ${activeQuarter.value}?`,
    text: 'Score entry will be disabled after locking. Admins can unlock it.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Lock',
    confirmButtonColor: '#d97706',
  })
  if (!result.isConfirmed) return

  try {
    await axios.post(route('class-records.quarters.lock', { classRecord: props.classRecord.id, q: activeQuarter.value }))
    router.reload({ only: ['classRecord'] })
  } catch (err) {
    Swal.fire('Error', err.response?.data?.message ?? 'Failed to lock quarter.', 'error')
  }
}

async function unlockQuarter() {
  try {
    await axios.post(route('class-records.quarters.unlock', { classRecord: props.classRecord.id, q: activeQuarter.value }))
    router.reload({ only: ['classRecord'] })
  } catch (err) {
    Swal.fire('Error', err.response?.data?.message ?? 'Failed to unlock quarter.', 'error')
  }
}

// ── Workflow: submit / check ──────────────────────────────────────────────────
async function submitRecord() {
  const result = await Swal.fire({
    title:             'Submit for Review?',
    text:              'The class record will be locked for editing and sent to the Academic Unit Head for review.',
    icon:              'question',
    showCancelButton:  true,
    confirmButtonText: 'Yes, Submit',
  })
  if (!result.isConfirmed) return

  try {
    await axios.post(route('class-records.submit', props.classRecord.id))
    router.reload()
  } catch (err) {
    Swal.fire('Error', err.response?.data?.message ?? 'Failed to submit.', 'error')
  }
}

async function checkRecord() {
  try {
    await axios.post(route('class-records.check', props.classRecord.id))
    router.reload()
  } catch (err) {
    Swal.fire('Error', err.response?.data?.message ?? 'Failed to mark as checked.', 'error')
  }
}

</script>

<template>
  <Head :title="`Class Record — ${classRecord.subject_name}`" />
  <AdminLayout :title="classRecord.subject_name">
    <div class="space-y-5">

      <!-- Back + header -->
      <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div class="flex items-start gap-3">
          <button @click="router.visit(route('class-records.page.index'))"
            class="mt-0.5 p-1.5 rounded-lg border border-slate-200 hover:bg-slate-50 text-slate-500">
            <ArrowLeftIcon class="h-4 w-4" />
          </button>
          <div>
            <h1 class="text-xl font-bold text-slate-800">{{ classRecord.subject_name }}</h1>
            <p class="text-sm text-slate-500 mt-0.5">
              {{ classRecord.year_level_section }} &middot; {{ classRecord.school_year }}
              &middot; {{ classRecord.grading_option?.name }}
            </p>
            <div class="mt-1.5">
              <span :class="[badgeBase, statusBadge(classRecord.status)]">
                {{ classRecord.status === 'checked' ? 'Checked ✓' : classRecord.status.charAt(0).toUpperCase() + classRecord.status.slice(1) }}
              </span>
            </div>
          </div>
        </div>

        <!-- Workflow actions -->
        <div class="flex items-center gap-2 shrink-0">
          <!-- Export All button -->
          <a :href="route('class-records.export', classRecord.id)"
            class="inline-flex items-center gap-2 border border-slate-200 bg-white hover:bg-slate-50 text-slate-600 px-3 py-2 rounded-lg text-sm font-medium transition-colors">
            <ArrowDownTrayIcon class="h-4 w-4" /> Export All
          </a>
          <button v-if="classRecord.status === 'draft' && isCurrentSY"
            @click="submitRecord"
            class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
            Submit for Review
          </button>
          <button v-if="classRecord.status === 'submitted' && isAdmin"
            @click="checkRecord"
            class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
            <CheckCircleIcon class="h-4 w-4" /> Mark as Checked
          </button>
        </div>
      </div>

      <!-- Past SY read-only banner -->
      <div v-if="isReadOnly"
        class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        <LockClosedIcon class="h-4 w-4 mt-0.5 shrink-0 text-amber-500" />
        <span>
          This class record is from <strong>SY {{ classRecord.school_year }}</strong> and is
          <strong>read-only</strong>. The school year is no longer active.
        </span>
      </div>

      <!-- Quarter tabs -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <!-- Quarter tab bar -->
        <div class="flex border-b border-slate-100">
          <button v-for="q in [1,2,3,4]" :key="q"
            @click="showFinalGrades = false; activeQuarter = q"
            :class="[
              'px-6 py-3 text-sm font-medium transition-colors border-b-2 -mb-px',
              !showFinalGrades && activeQuarter === q
                ? 'border-indigo-500 text-indigo-600'
                : 'border-transparent text-slate-500 hover:text-slate-700',
            ]">
            Quarter {{ q }}
            <span v-if="classRecord.quarters?.find(qt => qt.quarter === q)?.is_locked"
              class="ml-1.5 inline-flex"><LockClosedIcon class="h-3 w-3 text-amber-500" /></span>
          </button>
          <button
            @click="openFinalGrades"
            :disabled="!allQuartersExist"
            :title="!allQuartersExist ? 'All 4 quarters must exist first' : ''"
            :class="[
              'px-6 py-3 text-sm font-medium transition-colors border-b-2 -mb-px',
              showFinalGrades
                ? 'border-indigo-500 text-indigo-600'
                : 'border-transparent text-slate-500 hover:text-slate-700',
              !allQuartersExist ? 'opacity-40 cursor-not-allowed' : '',
            ]">
            Final Grades
          </button>
        </div>

        <template v-if="!showFinalGrades">
        <!-- Quarter export buttons -->
        <div class="flex justify-end gap-1 px-4 pt-2">
          <a :href="route('class-records.quarters.export', { classRecord: classRecord.id, q: activeQuarter })"
            class="inline-flex items-center gap-1.5 text-xs text-slate-500 hover:text-indigo-600 px-2 py-1 rounded hover:bg-slate-50">
            <ArrowDownTrayIcon class="h-3.5 w-3.5" /> Excel Q{{ activeQuarter }}
          </a>
          <a :href="route('class-records.quarters.pdf', { classRecord: classRecord.id, q: activeQuarter })"
            target="_blank"
            class="inline-flex items-center gap-1.5 text-xs text-slate-500 hover:text-red-600 px-2 py-1 rounded hover:bg-slate-50">
            <ArrowDownTrayIcon class="h-3.5 w-3.5" /> PDF Q{{ activeQuarter }}
          </a>
        </div>

        <!-- Sub-tab bar -->
        <div class="flex gap-1 px-4 pt-1 border-b border-slate-50">
          <button
            v-for="tab in ['setup', 'scores']" :key="tab"
            @click="activeSubTab = tab"
            :class="[
              'px-4 py-1.5 rounded-lg text-xs font-medium transition-colors',
              activeSubTab === tab
                ? 'bg-indigo-50 text-indigo-700'
                : 'text-slate-500 hover:text-slate-700 hover:bg-slate-50',
            ]">
            {{ tab === 'setup' ? '⚙ Setup' : '📊 Scores & Grades' }}
          </button>
        </div>

        <!-- ── Setup sub-tab ─────────────────────────────────────────────── -->
        <div v-if="activeSubTab === 'setup'" class="p-5">

          <!-- Lock / unlock controls -->
          <div class="flex items-center justify-between mb-4">
            <p class="text-xs text-slate-500">
              Configure assessment titles, dates, and max scores for each category.
            </p>
            <div class="flex items-center gap-2">
              <template v-if="!isReadOnly">
                <button v-if="!isLocked"
                  @click="lockQuarter"
                  class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-amber-300 text-amber-700 bg-amber-50 hover:bg-amber-100 text-xs font-medium">
                  <LockClosedIcon class="h-3.5 w-3.5" /> Lock Quarter
                </button>
                <button v-if="isLocked && isAdmin"
                  @click="unlockQuarter"
                  class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 text-xs font-medium">
                  <LockOpenIcon class="h-3.5 w-3.5" /> Unlock
                </button>
                <span v-if="isLocked && !isAdmin"
                  class="inline-flex items-center gap-1 text-xs text-amber-600">
                  <LockClosedIcon class="h-3.5 w-3.5" /> Locked
                </span>
              </template>
              <span v-else class="inline-flex items-center gap-1 text-xs text-amber-600">
                <LockClosedIcon class="h-3.5 w-3.5" /> Past School Year
              </span>
            </div>
          </div>

          <!-- Copy-from banner (shown only when quarter has no assessments yet) -->
          <div v-if="!isLocked && !isReadOnly && !currentHasAssessments && (quartersWithAssessments.length || sameSubjectRecords.length)"
            class="mb-4 flex flex-wrap items-center gap-2 p-3 bg-indigo-50 border border-indigo-100 rounded-lg text-xs">
            <DocumentDuplicateIcon class="h-4 w-4 text-indigo-400 shrink-0" />
            <span class="text-slate-600">No assessments yet. Copy structure from:</span>
            <button v-for="q in quartersWithAssessments" :key="q.quarter"
              @click="copyFromQuarter(q.quarter)"
              :disabled="copyingFrom"
              class="px-2.5 py-1 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-medium disabled:opacity-50 transition-colors">
              {{ copyingFrom ? 'Copying…' : `Q${q.quarter}` }}
            </button>
            <button v-if="sameSubjectRecords.length"
              @click="showCopyFromRecordModal = true"
              :disabled="copyingFrom"
              class="px-2.5 py-1 rounded-lg border border-indigo-300 text-indigo-700 hover:bg-indigo-100 font-medium disabled:opacity-50 transition-colors">
              Another section…
            </button>
          </div>

          <!-- Errors -->
          <div v-if="setupErrors.length"
            class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-xs space-y-1">
            <p v-for="e in setupErrors" :key="e">{{ e }}</p>
          </div>

          <!-- Category groups -->
          <div class="space-y-6">
            <div v-for="cat in classRecord.grading_option?.categories" :key="cat.id">
              <div class="flex items-center gap-2 mb-2">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700 text-xs font-bold">
                  {{ cat.code }}
                </span>
                <span class="text-sm font-semibold text-slate-700">{{ cat.name }}</span>
                <span class="text-xs text-slate-400">({{ Math.round(cat.weight * 100) }}%)</span>
              </div>

              <div class="overflow-x-auto rounded-lg border border-slate-100">
                <table class="min-w-full text-sm">
                  <thead class="bg-slate-50">
                    <tr>
                      <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500 w-16">#</th>
                      <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500">Title / Description</th>
                      <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500 w-36">Activity Date</th>
                      <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500 w-28">Max Score</th>
                      <th v-if="!isLocked && !isReadOnly" class="px-2 py-2 w-8"></th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-100">
                    <tr v-for="(row, rIdx) in assessmentDraft[cat.id]" :key="rIdx"
                      class="hover:bg-slate-50/40">
                      <td class="px-4 py-2 text-xs font-bold text-slate-500 whitespace-nowrap">
                        {{ cat.code }}{{ row.assessment_number }}
                      </td>
                      <td class="px-4 py-2">
                        <input v-model="row.title" type="text"
                          :disabled="isLocked || isReadOnly"
                          :placeholder="`${cat.code}${row.assessment_number} title…`"
                          class="w-full rounded border border-slate-200 px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400 disabled:bg-slate-50 disabled:text-slate-400" />
                      </td>
                      <td class="px-4 py-2">
                        <input v-model="row.activity_date" type="date"
                          :disabled="isLocked || isReadOnly"
                          class="w-full rounded border border-slate-200 px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400 disabled:bg-slate-50 disabled:text-slate-400" />
                      </td>
                      <td class="px-4 py-2">
                        <input v-model.number="row.max_score" type="number" min="0.01" step="0.5"
                          :disabled="isLocked || isReadOnly"
                          placeholder="e.g. 30"
                          class="w-full rounded border border-slate-200 px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400 disabled:bg-slate-50 disabled:text-slate-400" />
                      </td>
                      <td v-if="!isLocked && !isReadOnly" class="px-2 py-2 text-center">
                        <button @click="removeAssessmentRow(cat.id, rIdx)"
                          class="p-1 rounded hover:bg-red-50 text-slate-300 hover:text-red-500 transition-colors"
                          title="Remove this assessment row">
                          <XMarkIcon class="h-4 w-4" />
                        </button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <!-- Add row button per category -->
              <div v-if="!isLocked && !isReadOnly" class="mt-1.5">
                <button @click="addAssessmentRow(cat.id)"
                  class="inline-flex items-center gap-1.5 text-indigo-600 hover:text-indigo-700 text-xs font-medium">
                  <PlusIcon class="h-3.5 w-3.5" /> Add {{ cat.code }} Row
                </button>
              </div>
            </div>
          </div>

          <!-- Save setup button -->
          <div class="mt-5 flex justify-end">
            <button v-if="!isLocked && !isReadOnly"
              @click="saveSetup"
              :disabled="savingSetup"
              class="inline-flex items-center gap-2 px-5 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white rounded-lg text-sm font-medium transition-colors shadow-sm">
              {{ savingSetup ? 'Saving…' : 'Save Setup' }}
            </button>
          </div>
        </div>

        <!-- ── Scores & Grades sub-tab ───────────────────────────────────── -->
        <div v-if="activeSubTab === 'scores'" class="p-5">
          <ScoreGrid
            :class-record-id="classRecord.id"
            :quarter-number="activeQuarter"
            :quarter-data="currentQuarterData"
            :grading-option="classRecord.grading_option"
            :stanine-lookup="stanineLookup"
            :previous-grades="{}"
            :is-locked="isLocked || isReadOnly"
            @reload="router.reload({ only: ['classRecord'] })"
          />
        </div>
        </template>

        <!-- ── Final Grades tab ──────────────────────────────────────────── -->
        <div v-if="showFinalGrades" class="p-5">

          <!-- Loading -->
          <div v-if="finalGradesLoading"
            class="flex items-center justify-center py-12 text-slate-400 text-sm">
            Loading final grades…
          </div>

          <!-- Error / not-ready message -->
          <div v-else-if="finalGradesError"
            class="flex flex-col items-center gap-3 py-10">
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 w-full max-w-lg text-center">
              {{ finalGradesError }}
            </div>
            <button @click="loadFinalGrades"
              class="px-3 py-1.5 rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50 text-xs">
              Retry
            </button>
          </div>

          <!-- Table -->
          <template v-else-if="finalGrades.length">
            <p class="text-xs text-slate-500 mb-4">
              Final annual grade = simple average of Q1–Q4 grade equivalents, rounded to 3 decimal places.
            </p>
            <div class="overflow-x-auto rounded-xl border border-slate-100">
              <table class="min-w-full text-sm">
                <thead class="bg-slate-50">
                  <tr>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 w-10">#</th>
                    <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-500">Student</th>
                    <th class="px-3 py-2.5 text-center text-xs font-semibold text-slate-500 w-16">Q1 GE</th>
                    <th class="px-3 py-2.5 text-center text-xs font-semibold text-slate-500 w-16">Q2 GE</th>
                    <th class="px-3 py-2.5 text-center text-xs font-semibold text-slate-500 w-16">Q3 GE</th>
                    <th class="px-3 py-2.5 text-center text-xs font-semibold text-slate-500 w-16">Q4 GE</th>
                    <th class="px-3 py-2.5 text-center text-xs font-semibold text-slate-500 w-20">Final GE</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500">Rating</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                  <tr v-for="(student, idx) in finalGrades" :key="student.studentId"
                    :class="idx % 2 === 0 ? 'bg-white' : 'bg-slate-50/40'">
                    <td class="px-3 py-2.5 text-xs text-slate-400 text-center">{{ student.sequenceNumber }}</td>
                    <td class="px-4 py-2.5 text-sm font-medium text-slate-800">
                      {{ student.familyName }}, {{ student.givenName }}
                      <span v-if="student.middleInitial"> {{ student.middleInitial }}.</span>
                    </td>
                    <td class="px-3 py-2.5 text-center font-mono text-slate-600">{{ Number(student.q1GE).toFixed(3) }}</td>
                    <td class="px-3 py-2.5 text-center font-mono text-slate-600">{{ Number(student.q2GE).toFixed(3) }}</td>
                    <td class="px-3 py-2.5 text-center font-mono text-slate-600">{{ Number(student.q3GE).toFixed(3) }}</td>
                    <td class="px-3 py-2.5 text-center font-mono text-slate-600">{{ Number(student.q4GE).toFixed(3) }}</td>
                    <td class="px-3 py-2.5 text-center font-mono font-bold text-slate-800">{{ Number(student.finalGE).toFixed(3) }}</td>
                    <td class="px-3 py-2.5 text-sm" :class="adjectivalColor(student.adjectival)">{{ student.adjectival }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </template>

          <!-- Empty fallback -->
          <div v-else class="flex flex-col items-center justify-center py-12 text-slate-400 text-sm gap-2">
            <p>No student data found. Ensure all 4 quarters have scores entered.</p>
            <button @click="loadFinalGrades"
              class="px-3 py-1.5 rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50 text-xs">
              Retry
            </button>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>

  <!-- Copy-from-record modal -->
  <div v-if="showCopyFromRecordModal"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
    @click.self="showCopyFromRecordModal = false">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 p-6 space-y-4">
      <div class="flex items-center justify-between">
        <h3 class="font-semibold text-slate-800">Copy from Another Section</h3>
        <button @click="showCopyFromRecordModal = false" class="p-1 rounded hover:bg-slate-100">
          <XMarkIcon class="h-5 w-5 text-slate-400" />
        </button>
      </div>
      <p class="text-xs text-slate-500">Select a class record with the same subject to copy its assessment structure.</p>
      <div class="space-y-3">
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Source Class Record</label>
          <select v-model="copyFromRecordId"
            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option :value="null" disabled>Select section…</option>
            <option v-for="r in sameSubjectRecords" :key="r.id" :value="r.id">
              {{ r.year_level_section }} ({{ r.school_year }})
            </option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Copy from Quarter</label>
          <select v-model="copyFromRecordQ"
            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option v-for="n in 4" :key="n" :value="n">Quarter {{ n }}</option>
          </select>
        </div>
      </div>
      <div class="flex justify-end gap-2 pt-2">
        <button @click="showCopyFromRecordModal = false"
          class="px-4 py-2 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 text-sm font-medium">
          Cancel
        </button>
        <button @click="copyFromRecord"
          :disabled="!copyFromRecordId || copyingFromRecord"
          class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium disabled:opacity-50 transition-colors">
          {{ copyingFromRecord ? 'Copying…' : 'Copy Assessments' }}
        </button>
      </div>
    </div>
  </div>
</template>
