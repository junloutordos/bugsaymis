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
} from '@heroicons/vue/24/outline'
import ScoreGrid from './components/ScoreGrid.vue'

const props = defineProps({
  classRecord:   Object,
  isAdmin:       { type: Boolean, default: false },
  stanineLookup: { type: Array, default: () => [] },
  isCurrentSY:   { type: Boolean, default: true },
  currentSYName: { type: String, default: null },
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

// ── CSV Import ────────────────────────────────────────────────────────────────
const csvInput       = ref(null)
const importRows     = ref([])   // parsed rows for preview
const importErrors   = ref([])
const showImport     = ref(false)
const importingCsv   = ref(false)

function triggerCsvPicker() {
  csvInput.value?.click()
}

function onCsvSelected(e) {
  const file = e.target.files?.[0]
  if (!file) return
  importErrors.value = []

  const reader = new FileReader()
  reader.onload = (ev) => {
    const text  = ev.target.result
    const lines = text.split(/\r?\n/).filter(l => l.trim())
    const rows  = []
    const errs  = []

    lines.forEach((line, idx) => {
      if (idx === 0) return  // skip header
      const cols = line.split(',').map(c => c.trim().replace(/^"|"$/g, ''))
      const [seqRaw, familyName, givenName, middleInitial, sex] = cols

      const seq = parseInt(seqRaw)
      const rowNum = idx + 1

      if (isNaN(seq) || seq < 1)        errs.push(`Row ${rowNum}: Invalid sequence number "${seqRaw}".`)
      if (!familyName)                   errs.push(`Row ${rowNum}: Family name is required.`)
      if (!givenName)                    errs.push(`Row ${rowNum}: Given name is required.`)
      if (!['M','F'].includes(sex?.toUpperCase())) errs.push(`Row ${rowNum}: Sex must be M or F (got "${sex}").`)

      rows.push({
        sequence_number: seq,
        family_name:     familyName ?? '',
        given_name:      givenName ?? '',
        middle_initial:  middleInitial ?? '',
        sex:             sex?.toUpperCase() ?? '',
        _valid: !errs.some(e => e.startsWith(`Row ${rowNum}:`)),
      })
    })

    importRows.value   = rows
    importErrors.value = errs
    showImport.value   = true
  }
  reader.readAsText(file)
  e.target.value = ''  // reset so same file can be re-selected
}

async function confirmImport() {
  if (importErrors.value.length) {
    Swal.fire('Fix Errors', 'Please correct the highlighted rows before importing.', 'warning')
    return
  }

  importingCsv.value = true
  try {
    const { data } = await axios.post(
      route('class-records.students.import', { classRecord: props.classRecord.id, q: activeQuarter.value }),
      { rows: importRows.value }
    )
    showImport.value = false
    importRows.value = []
    await Swal.fire({ icon: 'success', title: data.message, timer: 1400, showConfirmButton: false })
    router.reload({ only: ['classRecord'] })
  } catch (err) {
    Swal.fire('Import Failed', err.response?.data?.message ?? 'Could not import students.', 'error')
  } finally {
    importingCsv.value = false
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
            @click="activeQuarter = q"
            :class="[
              'px-6 py-3 text-sm font-medium transition-colors border-b-2 -mb-px',
              activeQuarter === q
                ? 'border-indigo-500 text-indigo-600'
                : 'border-transparent text-slate-500 hover:text-slate-700',
            ]">
            Quarter {{ q }}
            <span v-if="classRecord.quarters?.find(qt => qt.quarter === q)?.is_locked"
              class="ml-1.5 inline-flex"><LockClosedIcon class="h-3 w-3 text-amber-500" /></span>
          </button>
        </div>

        <!-- Quarter export button -->
        <div class="flex justify-end px-4 pt-2">
          <a :href="route('class-records.quarters.export', { classRecord: classRecord.id, q: activeQuarter })"
            class="inline-flex items-center gap-1.5 text-xs text-slate-500 hover:text-indigo-600 px-2 py-1 rounded hover:bg-slate-50">
            <ArrowDownTrayIcon class="h-3.5 w-3.5" /> Export Q{{ activeQuarter }}
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

          <!-- CSV import controls (hidden file input) -->
          <input ref="csvInput" type="file" accept=".csv" class="hidden" @change="onCsvSelected" />

          <!-- Student roster CSV import section -->
          <div v-if="!isReadOnly && !isLocked" class="mt-6 border-t border-slate-100 pt-5">
            <div class="flex items-center justify-between mb-3">
              <div>
                <p class="text-xs font-semibold text-slate-600 uppercase tracking-wide">Student Roster (Bulk Import)</p>
                <p class="text-xs text-slate-400 mt-0.5">Download the template, fill it in, then upload to add students in bulk.</p>
              </div>
              <div class="flex items-center gap-2">
                <a :href="route('class-records.students.template', { classRecord: classRecord.id, q: activeQuarter })"
                  class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-600 text-xs font-medium transition-colors">
                  <ArrowDownTrayIcon class="h-3.5 w-3.5" /> Download Template
                </a>
                <button @click="triggerCsvPicker"
                  class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-indigo-200 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-medium transition-colors">
                  <PlusIcon class="h-3.5 w-3.5" /> Upload CSV
                </button>
              </div>
            </div>
          </div>

          <!-- CSV Preview Modal -->
          <div v-if="showImport" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-3xl max-h-[80vh] flex flex-col">
              <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
                <h3 class="text-base font-semibold text-slate-800">Preview Import — {{ importRows.length }} Student(s)</h3>
                <button @click="showImport = false; importRows = []; importErrors = []"
                  class="p-1.5 rounded hover:bg-slate-100 text-slate-400">
                  <XMarkIcon class="h-4 w-4" />
                </button>
              </div>

              <div v-if="importErrors.length" class="mx-5 mt-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-xs text-red-700 space-y-1">
                <p v-for="e in importErrors" :key="e">{{ e }}</p>
              </div>

              <div class="overflow-auto flex-1 p-5">
                <table class="min-w-full text-xs border-separate border-spacing-0">
                  <thead>
                    <tr class="bg-slate-50">
                      <th class="px-3 py-2 text-left font-semibold text-slate-500 border border-slate-200 rounded-tl">#</th>
                      <th class="px-3 py-2 text-left font-semibold text-slate-500 border-y border-r border-slate-200">Seq</th>
                      <th class="px-3 py-2 text-left font-semibold text-slate-500 border-y border-r border-slate-200">Family Name</th>
                      <th class="px-3 py-2 text-left font-semibold text-slate-500 border-y border-r border-slate-200">Given Name</th>
                      <th class="px-3 py-2 text-left font-semibold text-slate-500 border-y border-r border-slate-200">MI</th>
                      <th class="px-3 py-2 text-left font-semibold text-slate-500 border-y border-r border-slate-200 rounded-tr">Sex</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(row, idx) in importRows" :key="idx"
                      :class="row._valid ? 'bg-white' : 'bg-red-50'">
                      <td class="px-3 py-1.5 border-b border-x border-slate-200 text-slate-400">{{ idx + 1 }}</td>
                      <td class="px-3 py-1.5 border-b border-r border-slate-200">{{ row.sequence_number }}</td>
                      <td class="px-3 py-1.5 border-b border-r border-slate-200 font-medium">{{ row.family_name }}</td>
                      <td class="px-3 py-1.5 border-b border-r border-slate-200">{{ row.given_name }}</td>
                      <td class="px-3 py-1.5 border-b border-r border-slate-200">{{ row.middle_initial }}</td>
                      <td class="px-3 py-1.5 border-b border-r border-slate-200">{{ row.sex }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <div class="flex justify-end gap-3 px-5 py-4 border-t border-slate-100">
                <button @click="showImport = false; importRows = []; importErrors = []"
                  class="px-4 py-2 text-sm border border-slate-200 rounded-lg text-slate-600 hover:bg-slate-50">Cancel</button>
                <button @click="confirmImport" :disabled="importingCsv || importErrors.length > 0"
                  class="inline-flex items-center gap-2 px-5 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white rounded-lg text-sm font-medium transition-colors">
                  {{ importingCsv ? 'Importing…' : `Import ${importRows.length} Student(s)` }}
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
      </div>
    </div>
  </AdminLayout>
</template>
