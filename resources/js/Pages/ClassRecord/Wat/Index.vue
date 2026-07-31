<script setup>
import { ref, computed, watch } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppModal from '@/Components/AppModal.vue'
import WatWeeklyCalendar from './components/WatWeeklyCalendar.vue'
import axios from 'axios'
import Swal from 'sweetalert2'
import {
  ChevronLeftIcon, ChevronRightIcon, PrinterIcon, CheckBadgeIcon,
  ClipboardDocumentCheckIcon, ExclamationTriangleIcon, AcademicCapIcon, Cog6ToothIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  sections:             { type: Array, default: () => [] },
  sectionId:            { type: Number, default: null },
  weekStart:            { type: String, required: true },
  wat:                  { type: Object, default: null },
  canReview:            { type: Boolean, default: false },
  isCoordinator:        { type: Boolean, default: false },
  schoolYear:           { type: Object, default: null },
  canManageExamWindows: { type: Boolean, default: false },
  examWindows:          { type: Array, default: () => [] },
  plottingRecords:      { type: Array, default: () => [] },
})

const selectedSection = ref(props.sectionId)
const selectedWeek    = ref(props.weekStart)

function reload() {
  router.get(route('class-records.wat.index'),
    { section: selectedSection.value, week: selectedWeek.value },
    { preserveScroll: true, preserveState: true })
}

function shiftWeek(deltaDays) {
  const d = new Date(selectedWeek.value + 'T00:00:00')
  d.setDate(d.getDate() + deltaDays)
  selectedWeek.value = d.toISOString().slice(0, 10)
  reload()
}

const sectionLabel = computed(() => {
  const s = props.sections.find(x => x.id === selectedSection.value)
  return s ? `Grade ${s.level} — ${s.name}` : ''
})

const weekLabel = computed(() => {
  if (!props.wat) return ''
  const opts = { month: 'long', day: 'numeric', year: 'numeric' }
  return `${new Date(props.wat.week_start + 'T00:00:00').toLocaleDateString('en-PH', opts)} – ${new Date(props.wat.week_end + 'T00:00:00').toLocaleDateString('en-PH', opts)}`
})

function openPrint() {
  window.open(
    route('class-records.wat.print', { section: selectedSection.value, week: selectedWeek.value }),
    '_blank'
  )
}

// ── ACIDAA review ─────────────────────────────────────────────────────────────
const showReviewBox = ref(false)
const reviewForm = useForm({
  section_id: null,
  week_start: null,
  remarks: '',
})

function submitReview() {
  reviewForm.section_id = selectedSection.value
  reviewForm.week_start = props.wat?.week_start ?? selectedWeek.value
  reviewForm.post(route('class-records.wat.review.store'), {
    preserveScroll: true,
    onSuccess: () => { showReviewBox.value = false; reviewForm.reset('remarks') },
  })
}

// ── Quarter final exam windows (admin-only) ───────────────────────────────────
const showExamWindows = ref(false)
const examWindowForm = ref(
  [1, 2, 3, 4].map(q => {
    const existing = props.examWindows.find(w => w.quarter === q)
    return { quarter: q, start_date: existing?.start_date ?? '', end_date: existing?.end_date ?? '', id: existing?.id ?? null }
  })
)
const savingExamWindow = ref(null)

async function saveExamWindow(row) {
  if (!row.start_date || !row.end_date) return
  savingExamWindow.value = row.quarter
  try {
    await axios.put(route('quarter-exam-windows.upsert'), {
      school_year_id: props.schoolYear.id,
      quarter: row.quarter,
      start_date: row.start_date,
      end_date: row.end_date,
    })
    router.reload({ only: ['examWindows', 'wat'] })
  } catch (err) {
    Swal.fire('Error', err.response?.data?.message ?? 'Failed to save exam window.', 'error')
  } finally {
    savingExamWindow.value = null
  }
}

async function clearExamWindow(row) {
  if (!row.id) {
    row.start_date = ''
    row.end_date = ''
    return
  }
  const confirmed = await Swal.fire({
    title: `Clear the Quarter ${row.quarter} exam window?`,
    icon: 'warning', showCancelButton: true, confirmButtonText: 'Clear',
  })
  if (!confirmed.isConfirmed) return
  savingExamWindow.value = row.quarter
  try {
    await axios.delete(route('quarter-exam-windows.destroy', row.id))
    row.id = null
    row.start_date = ''
    row.end_date = ''
    router.reload({ only: ['examWindows', 'wat'] })
  } catch (err) {
    Swal.fire('Error', err.response?.data?.message ?? 'Failed to clear exam window.', 'error')
  } finally {
    savingExamWindow.value = null
  }
}

// ── Remaining WAT slots — self-check before plotting ──────────────────────────
// Reframes wat.totals/wat.limits (already computed server-side) as "how much
// room is left this week", so teachers can coordinate with co-teachers on
// the same section before hitting the shared cap — visibility only, does
// not change any WAT rule.
const remainingGraded = computed(() => props.wat ? Math.max(0, props.wat.limits.weekly_graded - props.wat.totals.graded) : null)
const remainingMajor  = computed(() => props.wat ? Math.max(0, props.wat.limits.weekly_major - props.wat.totals.major) : null)

function remainingColor(remaining) {
  if (remaining === null) return 'slate'
  if (remaining <= 0) return 'red'
  if (remaining <= 2) return 'amber'
  return 'green'
}

// ── Direct calendar plotting ─────────────────────────────────────────────────
const showPlotModal = ref(false)
const plottingRecord = ref(null)
const savingPlot = ref(false)
const plotError = ref(null)
const plotForm = ref({
  quarter: 1,
  grading_category_id: null,
  title: '',
  max_score: '',
  is_graded: true,
  activity_date: '',
  target_class_record_ids: [],
})

const eligibleQuarters = computed(() =>
  (plottingRecord.value?.quarters ?? []).filter(q => !q.is_locked && q.categories.length)
)

const selectedPlotQuarter = computed(() =>
  eligibleQuarters.value.find(q => q.number === Number(plotForm.value.quarter)) ?? null
)

const selectedPlotCategory = computed(() =>
  selectedPlotQuarter.value?.categories.find(c => c.id === Number(plotForm.value.grading_category_id)) ?? null
)

watch(() => plotForm.value.quarter, () => {
  plotForm.value.grading_category_id = selectedPlotQuarter.value?.categories[0]?.id ?? null
})

function openPlot({ date, record }) {
  plottingRecord.value = record
  const quarters = (record.quarters ?? []).filter(q => !q.is_locked && q.categories.length)
  const first = quarters[0]
  plotForm.value = {
    quarter: first?.number ?? 1,
    grading_category_id: first?.categories[0]?.id ?? null,
    title: '',
    max_score: '',
    is_graded: true,
    activity_date: date,
    target_class_record_ids: [],
  }
  plotError.value = first ? null : 'This class record has no editable quarter with available grading categories.'
  showPlotModal.value = true
}

async function savePlot() {
  if (!plottingRecord.value || !selectedPlotQuarter.value || !selectedPlotCategory.value) return
  savingPlot.value = true
  plotError.value = null

  try {
    const { data } = await axios.post(
      route('class-records.assessments.plot', {
        classRecord: plottingRecord.value.id,
        q: plotForm.value.quarter,
      }),
      {
        grading_category_id: plotForm.value.grading_category_id,
        title: plotForm.value.title,
        max_score: plotForm.value.max_score,
        is_graded: plotForm.value.is_graded,
        activity_date: plotForm.value.activity_date,
        target_class_record_ids: plotForm.value.target_class_record_ids,
      },
    )

    showPlotModal.value = false
    await router.reload({ only: ['wat', 'plottingRecords'] })

    const skipped = data.skipped ?? []
    if (skipped.length) {
      await Swal.fire({
        icon: 'warning',
        title: 'Assessment plotted',
        html: `Saved to ${data.created.length} section(s).<br><br>` +
          skipped.map(row => `<div class="text-left text-xs"><strong>${row.label}</strong>: ${row.reason}</div>`).join(''),
      })
    } else {
      await Swal.fire({
        icon: 'success',
        title: 'Assessment plotted',
        text: `Saved directly to Class Record Setup for ${data.created.length} section(s).`,
        timer: 1800,
        showConfirmButton: false,
      })
    }
  } catch (err) {
    plotError.value = Object.values(err.response?.data?.errors ?? {}).flat()[0]
      ?? err.response?.data?.message
      ?? 'Failed to plot the assessment.'
  } finally {
    savingPlot.value = false
  }
}
</script>

<template>
  <Head title="Weekly Assessment Tracker" />
  <AdminLayout title="Weekly Assessment Tracker">
    <div class="max-w-6xl mx-auto space-y-4">

      <AppPageHeader title="Weekly Assessment Tracker"
        subtitle="Section-wide view of plotted assessments against the daily and weekly WAT limits.">
        <template #actions>
          <AppButton v-if="canManageExamWindows" variant="secondary" @click="showExamWindows = !showExamWindows">
            <Cog6ToothIcon class="w-4 h-4" /> Quarter Exam Windows
          </AppButton>
          <AppButton v-if="canReview" variant="secondary" @click="router.get(route('class-records.wat.review', { week: selectedWeek }))">
            <ClipboardDocumentCheckIcon class="w-4 h-4" /> Review All Sections
          </AppButton>
          <AppButton v-if="sectionId && (isCoordinator || canReview)" variant="secondary" @click="openPrint">
            <PrinterIcon class="w-4 h-4" /> Print WAT Form
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Quarter final exam windows (admin-only) -->
      <div v-if="canManageExamWindows && showExamWindows" class="bg-white rounded-xl border border-slate-100 p-4 space-y-3">
        <div>
          <h3 class="text-sm font-semibold text-slate-800">Quarter Final Exam Windows — SY {{ schoolYear?.name }}</h3>
          <p class="text-xs text-slate-500 mt-0.5">
            Dates set here exempt Long Test/Quarterly Exam entries from the daily/weekly WAT caps and the schedule-day rule for that quarter. The Friday-before plotting deadline still applies.
          </p>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">
          <div v-for="row in examWindowForm" :key="row.quarter" class="rounded-lg border border-slate-200 p-3 space-y-2">
            <p class="text-xs font-semibold text-slate-700">Quarter {{ row.quarter }}</p>
            <div class="flex items-center gap-1">
              <input v-model="row.start_date" type="date"
                class="w-full rounded border border-slate-200 px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-indigo-400" />
              <span class="text-slate-300 text-xs">–</span>
              <input v-model="row.end_date" type="date"
                class="w-full rounded border border-slate-200 px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-indigo-400" />
            </div>
            <div class="flex gap-1.5">
              <AppButton size="sm" :loading="savingExamWindow === row.quarter"
                :disabled="!row.start_date || !row.end_date" @click="saveExamWindow(row)">
                Save
              </AppButton>
              <AppButton v-if="row.id" size="sm" variant="secondary" @click="clearExamWindow(row)">Clear</AppButton>
            </div>
          </div>
        </div>
      </div>

      <!-- Controls -->
      <div class="bg-white rounded-xl border border-slate-100 p-4 flex flex-wrap items-end gap-3">
        <div>
          <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Section</label>
          <select v-model.number="selectedSection" @change="reload"
            class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option v-for="s in sections" :key="s.id" :value="s.id">
              Grade {{ s.level }} — {{ s.name }}
            </option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Week of</label>
          <div class="flex items-center gap-1">
            <button @click="shiftWeek(-7)" class="p-2 rounded-lg hover:bg-slate-100" title="Previous week">
              <ChevronLeftIcon class="w-4 h-4 text-slate-500" />
            </button>
            <input v-model="selectedWeek" type="date" @change="reload"
              class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            <button @click="shiftWeek(7)" class="p-2 rounded-lg hover:bg-slate-100" title="Next week">
              <ChevronRightIcon class="w-4 h-4 text-slate-500" />
            </button>
          </div>
        </div>
      </div>

      <template v-if="wat">
        <!-- Week summary -->
        <div class="bg-white rounded-xl border border-slate-100 p-4">
          <div class="flex flex-wrap items-center gap-3">
            <div>
              <h2 class="text-sm font-semibold text-slate-800">{{ sectionLabel }}</h2>
              <p class="text-xs text-slate-500">{{ weekLabel }} · SY {{ schoolYear?.name }}</p>
            </div>
            <div class="flex-1"></div>
            <AppBadge :color="wat.totals.graded > wat.limits.weekly_graded && !wat.totals.has_exam_window ? 'red' : 'slate'">
              {{ wat.totals.graded }} / {{ wat.limits.weekly_graded }} graded this week
            </AppBadge>
            <AppBadge :color="wat.totals.major > wat.limits.weekly_major && !wat.totals.has_exam_window ? 'red' : 'slate'">
              {{ wat.totals.major }} / {{ wat.limits.weekly_major }} major this week
            </AppBadge>
            <AppBadge :color="remainingColor(remainingGraded)" title="Graded slots left this week before the section hits its WAT cap">
              {{ remainingGraded }} graded slot(s) left
            </AppBadge>
            <AppBadge :color="remainingColor(remainingMajor)" title="Major slots left this week before the section hits its WAT cap">
              {{ remainingMajor }} major slot(s) left
            </AppBadge>
          </div>

          <div v-if="remainingGraded <= 0 || remainingMajor <= 0" class="mt-3 flex items-center gap-2 bg-amber-50 border border-amber-100 text-amber-700 rounded-lg px-3 py-2 text-xs">
            <ExclamationTriangleIcon class="w-4 h-4 shrink-0" />
            This section has no {{ remainingGraded <= 0 ? 'graded' : 'major' }} slots left this week — coordinate with co-teachers on this section before plotting, or wait until next week.
          </div>

          <div v-if="wat.totals.over_weekly && wat.totals.has_exam_window" class="mt-3 flex items-center gap-2 bg-indigo-50 border border-indigo-100 text-indigo-700 rounded-lg px-3 py-2 text-xs">
            <AcademicCapIcon class="w-4 h-4 shrink-0" />
            This week includes a Quarter Final Exam window — the daily/weekly caps don't apply to Long Test/Quarterly Exam entries in that window.
          </div>
          <div v-else-if="wat.totals.over_weekly" class="mt-3 flex items-center gap-2 bg-danger-50 border border-danger-100 text-danger-700 rounded-lg px-3 py-2 text-xs">
            <ExclamationTriangleIcon class="w-4 h-4 shrink-0" />
            This section exceeds the weekly WAT limits ({{ wat.limits.weekly_graded }} graded / {{ wat.limits.weekly_major }} major).
          </div>

          <!-- Review status -->
          <div v-if="wat.review" class="mt-3 flex items-start gap-2 bg-emerald-50 border border-emerald-100 text-emerald-700 rounded-lg px-3 py-2 text-xs">
            <CheckBadgeIcon class="w-4 h-4 shrink-0 mt-0.5" />
            <div>
              Reviewed by <span class="font-semibold">{{ wat.review.reviewed_by?.name }}</span>
              on {{ new Date(wat.review.reviewed_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }) }}.
              <p v-if="wat.review.remarks" class="mt-0.5 italic">“{{ wat.review.remarks }}”</p>
            </div>
          </div>

          <div v-if="canReview" class="mt-3">
            <AppButton v-if="!showReviewBox" size="sm" variant="secondary" @click="showReviewBox = true">
              <CheckBadgeIcon class="w-4 h-4 mr-1.5" /> {{ wat.review ? 'Update review' : 'Mark as reviewed' }}
            </AppButton>
            <div v-else class="space-y-2">
              <textarea v-model="reviewForm.remarks" rows="2" placeholder="Remarks (optional)…"
                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full"></textarea>
              <div class="flex gap-2">
                <AppButton size="sm" :loading="reviewForm.processing" @click="submitReview">Save review</AppButton>
                <AppButton size="sm" variant="secondary" @click="showReviewBox = false">Cancel</AppButton>
              </div>
            </div>
          </div>
        </div>

        <WatWeeklyCalendar
          :wat="wat"
          :plotting-records="plottingRecords"
          @plot="openPlot"
        />
      </template>

      <div v-else class="bg-white rounded-xl border border-slate-100 p-10 text-center text-sm text-slate-400">
        Select a section to view its Weekly Assessment Tracker.
      </div>
    </div>
  </AdminLayout>

  <AppModal :show="showPlotModal" size="lg" title="Plot Assessment"
    :subtitle="plottingRecord ? `${plottingRecord.subject_name} · ${plottingRecord.section_label} · ${plotForm.activity_date}` : ''"
    @close="showPlotModal = false">
    <div class="space-y-4">
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Quarter</label>
          <select v-model.number="plotForm.quarter"
            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option v-for="quarter in eligibleQuarters" :key="quarter.number" :value="quarter.number">
              Quarter {{ quarter.number }}
            </option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Setup Assessment</label>
          <select v-model.number="plotForm.grading_category_id"
            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option v-for="category in selectedPlotQuarter?.categories ?? []" :key="category.id" :value="category.id">
              {{ category.code }}{{ category.next_number }} — {{ category.name }}
            </option>
          </select>
          <p v-if="selectedPlotCategory" class="mt-1 text-[11px] text-slate-400">
            {{ selectedPlotCategory.type_label }}
            <span v-if="selectedPlotCategory.is_major" class="font-semibold text-amber-600"> · Major assessment</span>
          </p>
        </div>
      </div>

      <div>
        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Title / Description</label>
        <input v-model.trim="plotForm.title" type="text" placeholder="e.g. Quiz 1 — Fractions"
          class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Maximum Score</label>
          <input v-model="plotForm.max_score" type="number" min="0.01" step="0.01"
            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>
        <label class="flex items-center gap-2 self-end rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700">
          <input v-model="plotForm.is_graded" type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
          Include in grading
        </label>
      </div>

      <div v-if="plottingRecord?.same_subject_targets?.length" class="rounded-xl border border-slate-200 p-3">
        <p class="text-xs font-semibold text-slate-700">Also apply to other sections</p>
        <p class="mb-2 text-[11px] text-slate-400">Only sections you handle for the same subject are shown. Each section is validated separately.</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
          <label v-for="target in plottingRecord.same_subject_targets" :key="target.id"
            class="flex items-center gap-2 text-xs text-slate-600">
            <input v-model="plotForm.target_class_record_ids" type="checkbox" :value="target.id"
              class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
            {{ target.label }}
          </label>
        </div>
      </div>

      <p v-if="plotError" class="rounded-lg border border-red-100 bg-red-50 px-3 py-2 text-xs text-red-600">{{ plotError }}</p>
    </div>
    <template #footer>
      <AppButton variant="secondary" @click="showPlotModal = false">Cancel</AppButton>
      <AppButton :loading="savingPlot"
        :disabled="savingPlot || !selectedPlotCategory || !plotForm.title || !plotForm.max_score"
        @click="savePlot">
        Save to Class Record Setup
      </AppButton>
    </template>
  </AppModal>
</template>
