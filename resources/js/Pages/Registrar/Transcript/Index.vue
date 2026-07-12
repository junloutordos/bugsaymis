<script setup>
import { ref, computed, watch } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppModal from '@/Components/AppModal.vue'
import EmptyState from '@/Components/EmptyState.vue'
import PaginationControl from '@/Components/PaginationControl.vue'
import { confirmAction } from '@/Composables/useConfirm.js'
import {
  MagnifyingGlassIcon,
  ArrowPathIcon,
  LockClosedIcon,
  DocumentArrowDownIcon,
  ChevronRightIcon,
  CheckCircleIcon,
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

async function lockGrades(studentId) {
  if (! await confirmAction({ title: 'Lock grades?', text: 'Lock all grades for this student? This cannot be undone.', confirmText: 'Lock' })) return
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
    'Promoted':  'green',
    'Graduated': 'indigo',
    'Retained':  'amber',
    'Excluded':  'red',
    'Dropped':   'slate',
  }
  return map[standing] ?? 'slate'
}

const GRADE_LABELS = { 7:'Grade 7',8:'Grade 8',9:'Grade 9',10:'Grade 10',11:'Grade 11',12:'Grade 12' }
</script>

<template>
  <Head title="Academic Transcripts" />
  <AdminLayout title="Academic Transcripts">
    <div class="space-y-5">

      <AppPageHeader title="Academic Transcripts">
        <template #actions>
          <AppButton variant="secondary" @click="showBulkConfirm = true">
            <ArrowPathIcon class="h-4 w-4" />
            Compute All Grades
          </AppButton>

          <select
            v-model="schoolYearId"
            class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
          >
            <option v-for="sy in schoolYears" :key="sy.id" :value="sy.id">
              {{ sy.name }}{{ sy.is_current ? ' (Current)' : '' }}
            </option>
          </select>
        </template>
      </AppPageHeader>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success" class="bg-success-50 border border-success-100 text-success-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <CheckCircleIcon class="h-4 w-4 shrink-0" />{{ $page.props.flash.success }}
      </div>
      <div v-if="$page.props.flash?.error" class="bg-danger-50 border border-danger-100 text-danger-600 rounded-lg px-4 py-3 text-sm">
        {{ $page.props.flash.error }}
      </div>

      <!-- Filters -->
      <AppFilterBar>
        <div class="relative w-full sm:w-72">
          <MagnifyingGlassIcon class="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
          <input
            v-model="searchQ"
            @keyup.enter="applyFilters"
            type="text"
            placeholder="Search by name or PISAY ID…"
            class="w-full rounded-lg border border-slate-200 bg-white py-2 pl-9 pr-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
          />
        </div>

        <select
          v-model="gradeFilter"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
        >
          <option value="">All Grades</option>
          <option v-for="g in gradeLevels" :key="g" :value="g">Grade {{ g }}</option>
        </select>
      </AppFilterBar>

      <!-- Table -->
      <AppTable :is-empty="!displayedRows.length" :skeleton-cols="6">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Student</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Grade / Section</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Subjects</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider">GWA</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Standing</th>
            <th class="px-4 py-3"></th>
          </tr>
        </template>

        <tr v-for="row in displayedRows" :key="row.id" class="hover:bg-indigo-50/40">
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
            <div v-if="row.honors && row.honors !== 'None' && row.honors !== '—'" class="mt-1">
              <AppBadge color="amber">{{ row.honors }}</AppBadge>
            </div>
          </td>
          <td class="px-4 py-3">
            <AppBadge :color="standingColor(row.standing)">{{ row.standing }}</AppBadge>
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

        <template #mobileCard>
          <div v-for="row in displayedRows" :key="row.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="font-medium text-slate-800">{{ row.full_name }}</p>
                <p class="text-xs text-slate-400">{{ row.pisays_id ?? '—' }}</p>
                <p class="text-xs text-slate-500 mt-0.5">
                  {{ GRADE_LABELS[row.grade_level] ?? '—' }}
                  <span v-if="row.section_name"> · {{ row.section_name }}</span>
                </p>
              </div>
              <AppBadge :color="standingColor(row.standing)">{{ row.standing }}</AppBadge>
            </div>
            <div class="flex items-center justify-between text-xs text-slate-500">
              <span :class="row.computed_count < row.subject_count ? 'text-amber-600' : ''">
                {{ row.computed_count }} / {{ row.subject_count }} computed
              </span>
              <span v-if="row.gwa" :class="geColor(row.gwa)">GWA {{ parseFloat(row.gwa).toFixed(3) }}</span>
            </div>
            <div v-if="row.honors && row.honors !== 'None' && row.honors !== '—'">
              <AppBadge color="amber">{{ row.honors }}</AppBadge>
            </div>
            <div class="flex justify-end pt-1">
              <button @click="openTranscript(row)" class="text-indigo-600 hover:underline text-xs flex items-center gap-1">
                View <ChevronRightIcon class="w-3 h-3" />
              </button>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No students found" />
        </template>

        <template #footer>
          <PaginationControl
            :current-page="currentPage"
            :total-pages="totalPages"
            @prev="currentPage--"
            @next="currentPage++"
            @page="currentPage = $event"
          />
        </template>
      </AppTable>

    </div>

    <!-- ── Student transcript panel ───────────────────────────────────────────── -->
    <AppModal
      :show="!!selectedStudent"
      :title="selectedStudent?.full_name"
      :subtitle="selectedStudent ? `PISAY ID: ${selectedStudent.pisays_id ?? '—'} · ${GRADE_LABELS[selectedStudent.grade_level] ?? ''} ${selectedStudent.section_name ?? ''}` : ''"
      size="3xl"
      @close="closePanel"
    >
      <template #header>
        <div class="flex items-center gap-2 shrink-0">
          <AppButton size="sm" variant="secondary" :loading="computeForm.processing" @click="computeGrades(selectedStudent.id)">
            <ArrowPathIcon v-if="!computeForm.processing" class="w-3.5 h-3.5" />
            {{ computeForm.processing ? 'Computing…' : 'Recompute' }}
          </AppButton>
          <AppButton
            size="sm" variant="secondary" as="a" target="_blank"
            :href="route('registrar.report-card', { student: selectedStudent.id, schoolYear: schoolYearId })"
          >
            <DocumentArrowDownIcon class="w-3.5 h-3.5" />
            Report Card PDF
          </AppButton>
          <AppButton size="sm" variant="secondary" @click="lockGrades(selectedStudent.id)">
            <LockClosedIcon class="w-3.5 h-3.5" />
            Lock
          </AppButton>
        </div>
      </template>

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
            <AppBadge v-if="transcriptData.standing.standing" :color="standingColor(transcriptData.standing.standing)">
              {{ transcriptData.standing.standing }}
            </AppBadge>
            <p v-else class="font-semibold text-slate-500">—</p>
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
                <AppBadge :color="g.passed ? 'green' : 'red'">{{ g.remarks }}</AppBadge>
                <span v-if="g.is_locked" title="Locked" class="ml-1 text-slate-400">🔒</span>
              </td>
            </tr>
          </tbody>
        </table>

        <p v-else class="text-slate-400 text-sm text-center py-8">
          No grade data available. Ensure class records are submitted for this section.
        </p>

      </div>
    </AppModal>

    <!-- ── Bulk compute confirm modal ─────────────────────────────────────────── -->
    <AppModal :show="showBulkConfirm" title="Compute All Grades" size="sm" @close="showBulkConfirm = false">
      <p class="text-sm text-slate-500">
        This will recompute grades for all enrolled students in the selected school year.
        Locked grades will not be overwritten. This may take a few moments.
      </p>
      <template #footer>
        <AppButton variant="secondary" @click="showBulkConfirm = false">Cancel</AppButton>
        <AppButton :loading="bulkForm.processing" @click="runBulkCompute">
          {{ bulkForm.processing ? 'Computing…' : 'Proceed' }}
        </AppButton>
      </template>
    </AppModal>

  </AdminLayout>
</template>
