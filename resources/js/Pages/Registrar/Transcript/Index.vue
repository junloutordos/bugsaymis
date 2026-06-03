<script setup>
import { ref, computed, watch } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import {
  ClipboardDocumentListIcon,
  MagnifyingGlassIcon,
  ArrowPathIcon,
  LockClosedIcon,
  DocumentArrowDownIcon,
  FunnelIcon,
  ChevronRightIcon,
} from '@heroicons/vue/24/outline'
import axios from 'axios'

const props = defineProps({
  rows: Array,
  schoolYears: Array,
  selectedSchoolYear: Number,
  filters: Object,
  gradeLevels: Array,
})

// ── Filters ───────────────────────────────────────────────────────────────────
const schoolYearId = ref(props.selectedSchoolYear)
const gradeFilter  = ref(props.filters?.grade_level ?? '')
const searchQ      = ref(props.filters?.q ?? '')

function applyFilters() {
  router.get(route('registrar.transcript.index'), {
    school_year_id: schoolYearId.value,
    grade_level:    gradeFilter.value || undefined,
    q:              searchQ.value || undefined,
  }, { preserveState: true })
}

watch(schoolYearId, applyFilters)
watch(gradeFilter,  applyFilters)

// ── Transcript detail panel ───────────────────────────────────────────────────
const selectedStudent  = ref(null)
const transcriptData   = ref(null)
const loadingTranscript = ref(false)

async function openTranscript(row) {
  selectedStudent.value   = row
  transcriptData.value    = null
  loadingTranscript.value = true
  try {
    const { data } = await axios.get(route('registrar.transcript.show', row.id), {
      params: { school_year_id: schoolYearId.value },
    })
    transcriptData.value = data
  } finally {
    loadingTranscript.value = false
  }
}

function closePanel() {
  selectedStudent.value = null
  transcriptData.value  = null
}

// ── Compute grades ────────────────────────────────────────────────────────────
const computeForm = useForm({ student_id: null, school_year_id: props.selectedSchoolYear })

function computeGrades(studentId) {
  computeForm.student_id     = studentId
  computeForm.school_year_id = schoolYearId.value
  computeForm.post(route('registrar.transcript.compute'), {
    onSuccess: () => openTranscript(selectedStudent.value),
  })
}

// ── Bulk compute ──────────────────────────────────────────────────────────────
const bulkForm = useForm({ school_year_id: props.selectedSchoolYear })
const showBulkConfirm = ref(false)

function runBulkCompute() {
  bulkForm.school_year_id = schoolYearId.value
  bulkForm.post(route('registrar.transcript.bulk-compute'), {
    onSuccess: () => { showBulkConfirm.value = false },
  })
}

// ── Lock grades ───────────────────────────────────────────────────────────────
const lockForm = useForm({ student_id: null, school_year_id: props.selectedSchoolYear })

function lockGrades(studentId) {
  if (! confirm('Lock all grades for this student? This cannot be undone.')) return
  lockForm.student_id     = studentId
  lockForm.school_year_id = schoolYearId.value
  lockForm.post(route('registrar.transcript.lock'), {
    onSuccess: () => openTranscript(selectedStudent.value),
  })
}

// ── Pagination ────────────────────────────────────────────────────────────────
const PER_PAGE   = 15
const currentPage = ref(1)
const totalPages  = computed(() => Math.max(1, Math.ceil(filteredRows.value.length / PER_PAGE)))
const filteredRows = computed(() => {
  if (!searchQ.value) return props.rows
  const q = searchQ.value.toLowerCase()
  return props.rows.filter(r =>
    r.full_name.toLowerCase().includes(q) ||
    (r.pisays_id ?? '').toLowerCase().includes(q)
  )
})
const displayedRows = computed(() => {
  const start = (currentPage.value - 1) * PER_PAGE
  return filteredRows.value.slice(start, start + PER_PAGE)
})

// ── Helpers ───────────────────────────────────────────────────────────────────
function geLabel(ge) {
  if (ge == null) return '—'
  const v = parseFloat(ge)
  if (v <= 1.0) return `${v.toFixed(3)} (Outstanding)`
  if (v <= 1.5) return `${v.toFixed(3)} (Very Satisfactory)`
  if (v <= 2.0) return `${v.toFixed(3)} (Satisfactory)`
  if (v <= 2.5) return `${v.toFixed(3)} (Fairly Satisfactory)`
  if (v <= 3.0) return `${v.toFixed(3)} (Did Not Meet Expectations)`
  return `${v.toFixed(3)} (Failed)`
}

function geColor(ge) {
  if (ge == null) return 'text-slate-400'
  const v = parseFloat(ge)
  if (v <= 1.5) return 'text-green-700 font-semibold'
  if (v <= 2.5) return 'text-slate-700'
  if (v <= 3.0) return 'text-amber-600'
  return 'text-red-600 font-semibold'
}

function honorsBadge(honors) {
  if (!honors || honors === 'None' || honors === '—') return ''
  return 'bg-amber-100 text-amber-800 text-xs px-2 py-0.5 rounded-full font-medium ml-2'
}

function standingColor(standing) {
  const map = {
    'Promoted':  'text-green-700',
    'Graduated': 'text-indigo-700',
    'Retained':  'text-amber-600',
    'Excluded':  'text-red-600',
    'Dropped':   'text-slate-500',
  }
  return map[standing] ?? 'text-slate-500'
}

const GRADE_LABELS = { 7:'Grade 7',8:'Grade 8',9:'Grade 9',10:'Grade 10',11:'Grade 11',12:'Grade 12' }
</script>

<template>
  <Head title="Academic Transcripts" />
  <AdminLayout title="Academic Transcripts">

    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
      <div class="flex items-center gap-2">
        <ClipboardDocumentListIcon class="w-6 h-6 text-indigo-600" />
        <h1 class="text-lg font-semibold text-slate-800">Academic Transcripts</h1>
      </div>

      <div class="flex items-center gap-2">
        <button
          @click="showBulkConfirm = true"
          class="flex items-center gap-1.5 border border-slate-200 text-slate-700 hover:bg-slate-50 px-3 py-2 rounded-lg text-sm font-medium"
        >
          <ArrowPathIcon class="w-4 h-4" />
          Compute All Grades
        </button>

        <select
          v-model="schoolYearId"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
        >
          <option v-for="sy in schoolYears" :key="sy.id" :value="sy.id">
            {{ sy.name }}{{ sy.is_current ? ' (Current)' : '' }}
          </option>
        </select>
      </div>
    </div>

    <!-- Filters -->
    <div class="flex items-center gap-3 mb-5">
      <div class="relative flex-1 max-w-xs">
        <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
        <input
          v-model="searchQ"
          @keyup.enter="applyFilters"
          type="text"
          placeholder="Search by name or PISAY ID…"
          class="w-full pl-9 pr-3 py-2 text-sm rounded-lg border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
        />
      </div>

      <select
        v-model="gradeFilter"
        class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
      >
        <option value="">All Grades</option>
        <option v-for="g in gradeLevels" :key="g" :value="g">Grade {{ g }}</option>
      </select>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50 border-b border-slate-200">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Student</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Grade / Section</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Subjects</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">GWA</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Standing</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="row in displayedRows" :key="row.id" class="hover:bg-slate-50">
            <td class="px-4 py-3">
              <p class="font-medium text-slate-800">{{ row.full_name }}</p>
              <p class="text-xs text-slate-400">{{ row.pisays_id ?? '—' }}</p>
            </td>
            <td class="px-4 py-3 text-slate-600">
              {{ GRADE_LABELS[row.grade_level] ?? '—' }}
              <span v-if="row.section_name" class="text-slate-400"> · {{ row.section_name }}</span>
            </td>
            <td class="px-4 py-3 text-center text-slate-600">
              <span :class="row.computed_count < row.subject_count ? 'text-amber-600' : 'text-slate-600'">
                {{ row.computed_count }} / {{ row.subject_count }}
              </span>
            </td>
            <td class="px-4 py-3 text-center">
              <span v-if="row.gwa" :class="geColor(row.gwa)">
                {{ parseFloat(row.gwa).toFixed(3) }}
              </span>
              <span v-else class="text-slate-400">—</span>
              <span v-if="row.honors && row.honors !== 'None' && row.honors !== '—'" class="block text-xs text-amber-600 font-medium">
                {{ row.honors }}
              </span>
            </td>
            <td class="px-4 py-3">
              <span :class="standingColor(row.standing)">{{ row.standing }}</span>
            </td>
            <td class="px-4 py-3 text-right">
              <button
                @click="openTranscript(row)"
                class="text-indigo-600 hover:underline text-xs flex items-center gap-1 ml-auto"
              >
                View <ChevronRightIcon class="w-3 h-3" />
              </button>
            </td>
          </tr>
          <tr v-if="displayedRows.length === 0">
            <td colspan="6" class="px-4 py-12 text-center text-slate-400 text-sm">No students found.</td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <div v-if="totalPages > 1" class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-sm text-slate-500">
        <span>Page {{ currentPage }} of {{ totalPages }}</span>
        <div class="flex gap-1">
          <button @click="currentPage--" :disabled="currentPage <= 1" class="px-2 py-1 rounded border disabled:opacity-40">‹</button>
          <button @click="currentPage++" :disabled="currentPage >= totalPages" class="px-2 py-1 rounded border disabled:opacity-40">›</button>
        </div>
      </div>
    </div>

    <!-- ── Student transcript panel ───────────────────────────────────────────── -->
    <Teleport to="body">
      <div
        v-if="selectedStudent"
        class="fixed inset-0 z-40 flex"
        @click.self="closePanel"
      >
        <div class="absolute inset-0 bg-black/30" @click="closePanel" />

        <div class="relative ml-auto w-full max-w-2xl bg-white shadow-2xl flex flex-col h-full">

          <!-- Panel header -->
          <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200">
            <div>
              <h2 class="font-semibold text-slate-800">{{ selectedStudent.full_name }}</h2>
              <p class="text-xs text-slate-500">
                PISAY ID: {{ selectedStudent.pisays_id ?? '—' }} ·
                {{ GRADE_LABELS[selectedStudent.grade_level] }} {{ selectedStudent.section_name }}
              </p>
            </div>
            <div class="flex items-center gap-2">
              <button
                @click="computeGrades(selectedStudent.id)"
                :disabled="computeForm.processing"
                class="flex items-center gap-1.5 border border-indigo-200 text-indigo-600 hover:bg-indigo-50 px-3 py-1.5 rounded-lg text-xs font-medium disabled:opacity-50"
              >
                <ArrowPathIcon class="w-3.5 h-3.5" />
                {{ computeForm.processing ? 'Computing…' : 'Recompute' }}
              </button>
              <a
                :href="route('registrar.report-card', { student: selectedStudent.id, schoolYear: schoolYearId })"
                target="_blank"
                class="flex items-center gap-1.5 border border-slate-200 text-slate-700 hover:bg-slate-50 px-3 py-1.5 rounded-lg text-xs font-medium"
              >
                <DocumentArrowDownIcon class="w-3.5 h-3.5" />
                Report Card PDF
              </a>
              <button
                @click="lockGrades(selectedStudent.id)"
                class="flex items-center gap-1.5 border border-slate-200 text-slate-500 hover:bg-slate-50 px-3 py-1.5 rounded-lg text-xs font-medium"
              >
                <LockClosedIcon class="w-3.5 h-3.5" />
                Lock
              </button>
              <button @click="closePanel" class="p-1 rounded hover:bg-slate-100 text-slate-400">✕</button>
            </div>
          </div>

          <!-- Content -->
          <div class="flex-1 overflow-y-auto p-6">

            <div v-if="loadingTranscript" class="text-center text-slate-400 py-12">
              Loading transcript…
            </div>

            <div v-else-if="transcriptData">

              <!-- Standing summary -->
              <div v-if="transcriptData.standing" class="bg-slate-50 rounded-xl p-4 mb-5 grid grid-cols-3 gap-3 text-sm">
                <div>
                  <p class="text-xs text-slate-500 mb-0.5">GWA</p>
                  <p :class="['font-semibold', geColor(transcriptData.standing.gwa)]">
                    {{ transcriptData.standing.gwa ? parseFloat(transcriptData.standing.gwa).toFixed(3) : '—' }}
                  </p>
                </div>
                <div>
                  <p class="text-xs text-slate-500 mb-0.5">Honors</p>
                  <p class="font-semibold text-amber-700">{{ transcriptData.standing.honors ?? '—' }}</p>
                </div>
                <div>
                  <p class="text-xs text-slate-500 mb-0.5">Standing</p>
                  <p :class="['font-semibold', standingColor(transcriptData.standing.standing)]">
                    {{ transcriptData.standing.standing ?? '—' }}
                  </p>
                </div>
              </div>
              <div v-else class="bg-amber-50 border border-amber-200 rounded-xl p-3 text-amber-700 text-sm mb-5">
                Grades not yet computed. Click "Recompute" to generate this student's transcript.
              </div>

              <!-- Grade table -->
              <table v-if="transcriptData.grades.length > 0" class="w-full text-sm">
                <thead class="bg-slate-50 border-b border-slate-200">
                  <tr>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500 uppercase">Subject</th>
                    <th class="px-3 py-2 text-center text-xs font-semibold text-slate-500 uppercase">Q1</th>
                    <th class="px-3 py-2 text-center text-xs font-semibold text-slate-500 uppercase">Q2</th>
                    <th class="px-3 py-2 text-center text-xs font-semibold text-slate-500 uppercase">Q3</th>
                    <th class="px-3 py-2 text-center text-xs font-semibold text-slate-500 uppercase">Q4</th>
                    <th class="px-3 py-2 text-center text-xs font-semibold text-slate-500 uppercase">Final</th>
                    <th class="px-3 py-2 text-center text-xs font-semibold text-slate-500 uppercase">Remarks</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                  <tr v-for="g in transcriptData.grades" :key="g.id" class="hover:bg-slate-50">
                    <td class="px-3 py-2 font-medium text-slate-800">{{ g.subject_name }}</td>
                    <td class="px-3 py-2 text-center tabular-nums" :class="geColor(g.q1_ge)">{{ g.q1_ge ?? '—' }}</td>
                    <td class="px-3 py-2 text-center tabular-nums" :class="geColor(g.q2_ge)">{{ g.q2_ge ?? '—' }}</td>
                    <td class="px-3 py-2 text-center tabular-nums" :class="geColor(g.q3_ge)">{{ g.q3_ge ?? '—' }}</td>
                    <td class="px-3 py-2 text-center tabular-nums" :class="geColor(g.q4_ge)">{{ g.q4_ge ?? '—' }}</td>
                    <td class="px-3 py-2 text-center tabular-nums font-semibold" :class="geColor(g.final_ge)">
                      {{ g.final_ge ?? '—' }}
                    </td>
                    <td class="px-3 py-2 text-center">
                      <span :class="[
                        'text-xs px-2 py-0.5 rounded-full font-medium',
                        g.passed ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'
                      ]">{{ g.remarks }}</span>
                      <span v-if="g.is_locked" title="Locked" class="ml-1 text-slate-400">🔒</span>
                    </td>
                  </tr>
                </tbody>
              </table>

              <p v-else class="text-slate-400 text-sm text-center py-8">
                No grade data available. Ensure class records are submitted for this section.
              </p>

            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ── Bulk compute confirm modal ─────────────────────────────────────────── -->
    <Teleport to="body">
      <div v-if="showBulkConfirm" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" @click="showBulkConfirm = false" />
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-sm p-6">
          <h3 class="font-semibold text-slate-800 mb-2">Compute All Grades</h3>
          <p class="text-sm text-slate-500 mb-4">
            This will recompute grades for all enrolled students in the selected school year.
            Locked grades will not be overwritten. This may take a few moments.
          </p>
          <div class="flex justify-end gap-2">
            <button @click="showBulkConfirm = false" class="px-4 py-2 text-sm border border-slate-200 rounded-lg">Cancel</button>
            <button
              @click="runBulkCompute"
              :disabled="bulkForm.processing"
              class="px-4 py-2 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50"
            >
              {{ bulkForm.processing ? 'Computing…' : 'Proceed' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

  </AdminLayout>
</template>
