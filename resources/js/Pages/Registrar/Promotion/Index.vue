<script setup>
import { ref, computed, watch } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import {
  ArrowUpCircleIcon,
  ArrowPathIcon,
  CheckCircleIcon,
  ExclamationTriangleIcon,
  MagnifyingGlassIcon,
} from '@heroicons/vue/24/outline'
import axios from 'axios'

const props = defineProps({
  schoolYears: Array,
  selectedSchoolYear: Number,
})

const schoolYearId     = ref(props.selectedSchoolYear)
const nextSchoolYearId = ref(null)
const previewRows      = ref([])
const loadingPreview   = ref(false)
const overrides        = ref({})   // { studentId: standing }
const showConfirmModal = ref(false)
const searchQ          = ref('')

watch(schoolYearId, () => {
  previewRows.value = []
  nextSchoolYearId.value = null
  router.get(route('registrar.promotion.index'), { school_year_id: schoolYearId.value }, { preserveState: true })
})

async function loadPreview() {
  loadingPreview.value = true
  try {
    const { data } = await axios.get(route('registrar.promotion.preview'), {
      params: { school_year_id: schoolYearId.value },
    })
    previewRows.value = data.rows
    overrides.value   = {}
  } finally {
    loadingPreview.value = false
  }
}

const confirmForm = useForm({
  school_year_id:      props.selectedSchoolYear,
  next_school_year_id: null,
  overrides:           {},
})

function openConfirm() {
  if (! nextSchoolYearId.value) {
    alert('Please select the next school year before confirming promotion.')
    return
  }
  showConfirmModal.value = true
}

function runConfirm() {
  confirmForm.school_year_id      = schoolYearId.value
  confirmForm.next_school_year_id = nextSchoolYearId.value
  confirmForm.overrides           = overrides.value
  confirmForm.post(route('registrar.promotion.confirm'), {
    onSuccess: () => {
      showConfirmModal.value = false
      previewRows.value      = []
    },
  })
}

// ── Computed ──────────────────────────────────────────────────────────────────
const filteredRows = computed(() => {
  if (! searchQ.value) return previewRows.value
  const q = searchQ.value.toLowerCase()
  return previewRows.value.filter(r =>
    String(r.student_id).includes(q) ||
    (r.section_name ?? '').toLowerCase().includes(q)
  )
})

const stats = computed(() => {
  const rows = previewRows.value
  return {
    total:    rows.length,
    promoted: rows.filter(r => effectiveStanding(r) === 'Promoted').length,
    retained: rows.filter(r => effectiveStanding(r) === 'Retained').length,
    excluded: rows.filter(r => effectiveStanding(r) === 'Excluded').length,
    noGrades: rows.filter(r => ! r.grades_computed).length,
  }
})

const nextSchoolYears = computed(() => {
  return props.schoolYears.filter(sy => sy.id !== schoolYearId.value)
})

function effectiveStanding(row) {
  return overrides.value[row.student_id] ?? row.standing
}

function setOverride(studentId, standing) {
  overrides.value = { ...overrides.value, [studentId]: standing }
}

function standingColor(s) {
  const map = {
    Promoted:  'bg-green-100 text-green-700',
    Retained:  'bg-amber-100 text-amber-700',
    Excluded:  'bg-red-100 text-red-700',
    Transferred: 'bg-blue-100 text-blue-700',
    Dropped:   'bg-slate-100 text-slate-500',
  }
  return map[s] ?? 'bg-slate-100 text-slate-500'
}

function geColor(ge) {
  if (ge == null) return 'text-slate-400'
  const v = parseFloat(ge)
  if (v <= 1.5) return 'text-green-700'
  if (v <= 3.0) return 'text-slate-700'
  return 'text-red-600 font-semibold'
}

const GRADE_LABELS = { 7:'Gr.7',8:'Gr.8',9:'Gr.9',10:'Gr.10',11:'Gr.11',12:'Gr.12' }
</script>

<template>
  <Head title="Year-End Promotion" />
  <AdminLayout title="Year-End Promotion">

    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
      <div class="flex items-center gap-2">
        <ArrowUpCircleIcon class="w-6 h-6 text-indigo-600" />
        <div>
          <h1 class="text-lg font-semibold text-slate-800">Year-End Promotion</h1>
          <p class="text-xs text-slate-500">Review standings and advance students to the next school year.</p>
        </div>
      </div>
    </div>

    <!-- Configuration strip -->
    <div class="bg-white rounded-xl border border-slate-200 p-4 mb-5 flex flex-wrap items-end gap-4">
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">From School Year</label>
        <select
          v-model="schoolYearId"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
        >
          <option v-for="sy in schoolYears" :key="sy.id" :value="sy.id">
            {{ sy.name }}{{ sy.is_current ? ' (Current)' : '' }}
          </option>
        </select>
      </div>

      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">To School Year <span class="text-slate-400">(next)</span></label>
        <select
          v-model="nextSchoolYearId"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
        >
          <option :value="null">— Select —</option>
          <option v-for="sy in nextSchoolYears" :key="sy.id" :value="sy.id">{{ sy.name }}</option>
        </select>
      </div>

      <button
        @click="loadPreview"
        :disabled="loadingPreview"
        class="flex items-center gap-1.5 border border-indigo-200 text-indigo-600 hover:bg-indigo-50 px-4 py-2 rounded-lg text-sm font-medium"
      >
        <ArrowPathIcon class="w-4 h-4" :class="loadingPreview ? 'animate-spin' : ''" />
        {{ loadingPreview ? 'Loading…' : 'Load Preview' }}
      </button>

      <button
        v-if="previewRows.length > 0"
        @click="openConfirm"
        class="ml-auto flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium"
      >
        <CheckCircleIcon class="w-4 h-4" />
        Confirm Promotion
      </button>
    </div>

    <!-- Stats strip -->
    <div v-if="previewRows.length > 0" class="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-5">
      <div class="bg-white rounded-xl border border-slate-200 p-3 text-center">
        <p class="text-xs text-slate-500">Total</p>
        <p class="text-2xl font-bold text-slate-800">{{ stats.total }}</p>
      </div>
      <div class="bg-green-50 rounded-xl border border-green-200 p-3 text-center">
        <p class="text-xs text-slate-500">Promoted</p>
        <p class="text-2xl font-bold text-green-700">{{ stats.promoted }}</p>
      </div>
      <div class="bg-amber-50 rounded-xl border border-amber-200 p-3 text-center">
        <p class="text-xs text-slate-500">Retained</p>
        <p class="text-2xl font-bold text-amber-700">{{ stats.retained }}</p>
      </div>
      <div class="bg-red-50 rounded-xl border border-red-200 p-3 text-center">
        <p class="text-xs text-slate-500">Excluded</p>
        <p class="text-2xl font-bold text-red-600">{{ stats.excluded }}</p>
      </div>
      <div class="bg-slate-50 rounded-xl border border-slate-200 p-3 text-center">
        <p class="text-xs text-slate-500">No Grades</p>
        <p class="text-2xl font-bold" :class="stats.noGrades > 0 ? 'text-amber-600' : 'text-slate-600'">
          {{ stats.noGrades }}
        </p>
      </div>
    </div>

    <!-- Warning if uncomputed -->
    <div
      v-if="previewRows.length > 0 && stats.noGrades > 0"
      class="bg-amber-50 border border-amber-200 rounded-xl p-3 mb-4 flex items-center gap-2 text-amber-700 text-sm"
    >
      <ExclamationTriangleIcon class="w-4 h-4 shrink-0" />
      {{ stats.noGrades }} student(s) have no computed grades. Go to Academic Transcripts and run "Compute All Grades" first.
    </div>

    <!-- Preview table -->
    <div v-if="previewRows.length > 0" class="bg-white rounded-xl border border-slate-200 overflow-hidden">
      <div class="flex items-center gap-3 px-4 py-3 border-b border-slate-100">
        <div class="relative flex-1 max-w-xs">
          <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
          <input
            v-model="searchQ"
            type="text"
            placeholder="Filter by section or student ID…"
            class="w-full pl-9 pr-3 py-2 text-sm rounded-lg border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
          />
        </div>
        <p class="text-xs text-slate-400">Override individual standing by clicking the badge</p>
      </div>

      <table class="min-w-full text-sm">
        <thead class="bg-slate-50 border-b border-slate-200">
          <tr>
            <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500 uppercase">Student ID</th>
            <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500 uppercase">Grade / Section</th>
            <th class="px-4 py-2 text-center text-xs font-semibold text-slate-500 uppercase">Subjects</th>
            <th class="px-4 py-2 text-center text-xs font-semibold text-slate-500 uppercase">Failed</th>
            <th class="px-4 py-2 text-center text-xs font-semibold text-slate-500 uppercase">GWA</th>
            <th class="px-4 py-2 text-center text-xs font-semibold text-slate-500 uppercase">Standing</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="row in filteredRows" :key="row.student_id" class="hover:bg-slate-50">
            <td class="px-4 py-2 tabular-nums text-slate-600">{{ row.student_id }}</td>
            <td class="px-4 py-2">
              {{ GRADE_LABELS[row.grade_level] ?? '—' }}
              <span class="text-slate-400"> · {{ row.section_name }}</span>
            </td>
            <td class="px-4 py-2 text-center text-slate-600">{{ row.subject_count }}</td>
            <td class="px-4 py-2 text-center" :class="row.failed_subject_count > 0 ? 'text-red-600 font-semibold' : 'text-slate-600'">
              {{ row.failed_subject_count }}
            </td>
            <td class="px-4 py-2 text-center" :class="geColor(row.gwa)">{{ row.gwa_display }}</td>
            <td class="px-4 py-2 text-center">
              <!-- Clickable standing badge cycles through Promoted → Retained → Excluded → Promoted -->
              <button
                :class="['text-xs px-3 py-1 rounded-full font-medium', standingColor(effectiveStanding(row))]"
                :title="overrides[row.student_id] ? 'Overridden — click to cycle' : 'Click to override'"
                @click="setOverride(row.student_id, ({
                  Promoted:  'Retained',
                  Retained:  'Excluded',
                  Excluded:  'Promoted',
                  Transferred: 'Promoted',
                  Dropped:   'Promoted',
                })[effectiveStanding(row)] ?? 'Promoted')"
              >
                {{ effectiveStanding(row) }}
                <span v-if="overrides[row.student_id]" class="ml-1 opacity-60">✱</span>
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Empty state -->
    <div v-else-if="!loadingPreview" class="bg-white rounded-xl border border-slate-200 p-12 text-center text-slate-400">
      Click "Load Preview" to see the promotion results for the selected school year.
    </div>

    <!-- ── Confirm modal ───────────────────────────────────────────────────────── -->
    <Teleport to="body">
      <div v-if="showConfirmModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" @click="showConfirmModal = false" />
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-md p-6">
          <h3 class="font-semibold text-slate-800 mb-2">Confirm Year-End Promotion</h3>

          <div class="bg-slate-50 rounded-lg p-4 mb-4 text-sm space-y-1">
            <p><b>From:</b> {{ schoolYears.find(s => s.id === schoolYearId)?.name }}</p>
            <p><b>To:</b> {{ schoolYears.find(s => s.id === nextSchoolYearId)?.name }}</p>
            <div class="border-t border-slate-200 mt-2 pt-2 space-y-0.5">
              <p class="text-green-700"><b>Promoting:</b> {{ stats.promoted }} students</p>
              <p class="text-amber-600"><b>Retaining:</b> {{ stats.retained }} students</p>
              <p v-if="stats.excluded > 0" class="text-red-600"><b>Excluding:</b> {{ stats.excluded }} students</p>
              <p v-if="Object.keys(overrides).length > 0" class="text-indigo-600">
                <b>Manual overrides:</b> {{ Object.keys(overrides).length }} student(s)
              </p>
            </div>
          </div>

          <p class="text-xs text-slate-500 mb-4">
            This will finalise academic standings for S.Y. {{ schoolYears.find(s => s.id === schoolYearId)?.name }}
            and create enrollment records for the following school year. <b>This action cannot be undone.</b>
          </p>

          <div class="flex justify-end gap-2">
            <button @click="showConfirmModal = false" class="px-4 py-2 text-sm border border-slate-200 rounded-lg">Cancel</button>
            <button
              @click="runConfirm"
              :disabled="confirmForm.processing"
              class="px-4 py-2 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50"
            >
              {{ confirmForm.processing ? 'Processing…' : 'Yes, Confirm Promotion' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

  </AdminLayout>
</template>
