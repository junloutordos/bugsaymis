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
import {
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
    Promoted:    'green',
    Retained:    'amber',
    Excluded:    'red',
    Transferred: 'blue',
    Dropped:     'slate',
  }
  return map[s] ?? 'slate'
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
    <div class="space-y-5">

      <AppPageHeader title="Year-End Promotion" subtitle="Review standings and advance students to the next school year." />

      <!-- Flash -->
      <div v-if="$page.props.flash?.success" class="bg-success-50 border border-success-100 text-success-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <CheckCircleIcon class="h-4 w-4 shrink-0" />{{ $page.props.flash.success }}
      </div>
      <div v-if="$page.props.flash?.error" class="bg-danger-50 border border-danger-100 text-danger-600 rounded-lg px-4 py-3 text-sm">
        {{ $page.props.flash.error }}
      </div>

      <!-- Configuration strip -->
      <AppFilterBar>
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

        <template #actions>
          <AppButton variant="secondary" :loading="loadingPreview" @click="loadPreview">
            <ArrowPathIcon v-if="!loadingPreview" class="h-4 w-4" />
            {{ loadingPreview ? 'Loading…' : 'Load Preview' }}
          </AppButton>
          <AppButton v-if="previewRows.length > 0" @click="openConfirm">
            <CheckCircleIcon class="h-4 w-4" />
            Confirm Promotion
          </AppButton>
        </template>
      </AppFilterBar>

      <!-- Stats strip -->
      <div v-if="previewRows.length > 0" class="grid grid-cols-2 sm:grid-cols-5 gap-3">
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
        class="bg-amber-50 border border-amber-200 rounded-xl p-3 flex items-center gap-2 text-amber-700 text-sm"
      >
        <ExclamationTriangleIcon class="w-4 h-4 shrink-0" />
        {{ stats.noGrades }} student(s) have no computed grades. Go to Academic Transcripts and run "Compute All Grades" first.
      </div>

      <!-- Preview table -->
      <div v-if="previewRows.length > 0" class="space-y-3">
        <AppFilterBar>
          <div class="relative w-full sm:w-80">
            <MagnifyingGlassIcon class="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
            <input
              v-model="searchQ"
              type="text"
              placeholder="Filter by section or student ID…"
              class="w-full rounded-lg border border-slate-200 bg-white py-2 pl-9 pr-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
          </div>
          <template #actions>
            <span class="text-xs text-slate-400">Override individual standing by clicking the badge</span>
          </template>
        </AppFilterBar>

        <AppTable :is-empty="!filteredRows.length" :skeleton-cols="6">
          <template #head>
            <tr>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Student ID</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Grade / Section</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Subjects</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Failed</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">GWA</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Standing</th>
            </tr>
          </template>

          <tr v-for="row in filteredRows" :key="row.student_id" class="hover:bg-slate-50/60">
            <td class="px-4 py-3 tabular-nums text-slate-600">{{ row.student_id }}</td>
            <td class="px-4 py-3">
              {{ GRADE_LABELS[row.grade_level] ?? '—' }}
              <span class="text-slate-400"> · {{ row.section_name }}</span>
            </td>
            <td class="px-4 py-3 text-center text-slate-600">{{ row.subject_count }}</td>
            <td class="px-4 py-3 text-center" :class="row.failed_subject_count > 0 ? 'text-red-600 font-semibold' : 'text-slate-600'">
              {{ row.failed_subject_count }}
            </td>
            <td class="px-4 py-3 text-center" :class="geColor(row.gwa)">{{ row.gwa_display }}</td>
            <td class="px-4 py-3 text-center">
              <!-- Clickable standing badge cycles through Promoted → Retained → Excluded → Promoted -->
              <button
                :title="overrides[row.student_id] ? 'Overridden — click to cycle' : 'Click to override'"
                @click="setOverride(row.student_id, ({
                  Promoted:    'Retained',
                  Retained:    'Excluded',
                  Excluded:    'Promoted',
                  Transferred: 'Promoted',
                  Dropped:     'Promoted',
                })[effectiveStanding(row)] ?? 'Promoted')"
              >
                <AppBadge :color="standingColor(effectiveStanding(row))">
                  {{ effectiveStanding(row) }}
                  <span v-if="overrides[row.student_id]" class="ml-1 opacity-60">✱</span>
                </AppBadge>
              </button>
            </td>
          </tr>

          <template #mobileCard>
            <div v-for="row in filteredRows" :key="row.student_id" class="p-4 space-y-2">
              <div class="flex items-start justify-between gap-2">
                <div>
                  <p class="text-xs text-slate-500 tabular-nums">{{ row.student_id }}</p>
                  <p class="font-medium text-slate-800">
                    {{ GRADE_LABELS[row.grade_level] ?? '—' }}
                    <span class="text-slate-400 font-normal"> · {{ row.section_name }}</span>
                  </p>
                </div>
                <button
                  :title="overrides[row.student_id] ? 'Overridden — click to cycle' : 'Click to override'"
                  @click="setOverride(row.student_id, ({
                    Promoted:    'Retained',
                    Retained:    'Excluded',
                    Excluded:    'Promoted',
                    Transferred: 'Promoted',
                    Dropped:     'Promoted',
                  })[effectiveStanding(row)] ?? 'Promoted')"
                >
                  <AppBadge :color="standingColor(effectiveStanding(row))">
                    {{ effectiveStanding(row) }}
                    <span v-if="overrides[row.student_id]" class="ml-1 opacity-60">✱</span>
                  </AppBadge>
                </button>
              </div>
              <div class="flex justify-between text-xs text-slate-500">
                <span>{{ row.subject_count }} subjects</span>
                <span :class="row.failed_subject_count > 0 ? 'text-red-600 font-semibold' : ''">{{ row.failed_subject_count }} failed</span>
                <span :class="geColor(row.gwa)">GWA {{ row.gwa_display }}</span>
              </div>
            </div>
          </template>

          <template #empty>
            <EmptyState title="No students match your filter" />
          </template>
        </AppTable>
      </div>

      <!-- Empty state (no preview loaded yet) -->
      <div v-else-if="!loadingPreview" class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <EmptyState title="No preview loaded" subtitle="Click &quot;Load Preview&quot; to see the promotion results for the selected school year." />
      </div>

    </div>

    <!-- ── Confirm modal ───────────────────────────────────────────────────────── -->
    <AppModal :show="showConfirmModal" title="Confirm Year-End Promotion" size="md" @close="showConfirmModal = false">
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

      <p class="text-xs text-slate-500">
        This will finalise academic standings for S.Y. {{ schoolYears.find(s => s.id === schoolYearId)?.name }}
        and create enrollment records for the following school year. <b>This action cannot be undone.</b>
      </p>

      <template #footer>
        <AppButton variant="secondary" @click="showConfirmModal = false">Cancel</AppButton>
        <AppButton :loading="confirmForm.processing" @click="runConfirm">
          {{ confirmForm.processing ? 'Processing…' : 'Yes, Confirm Promotion' }}
        </AppButton>
      </template>
    </AppModal>

  </AdminLayout>
</template>
