<script setup>
import { ref, computed } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
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

function dayName(dateStr) {
  return new Date(dateStr + 'T00:00:00').toLocaleDateString('en-PH', { weekday: 'long', month: 'short', day: 'numeric' })
}

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

function complianceColor(pct) {
  if (pct === null || pct === undefined) return 'bg-slate-200'
  if (pct >= 90) return 'bg-emerald-500'
  if (pct >= 60) return 'bg-amber-500'
  return 'bg-danger-500'
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

        <!-- Day-by-day grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-3">
          <div v-for="day in wat.days" :key="day.date"
            :class="['bg-white rounded-xl border p-3 flex flex-col', day.over_daily && !day.is_exam_window ? 'border-danger-200' : 'border-slate-100']">
            <div class="flex items-center justify-between mb-2">
              <h3 class="text-xs font-semibold text-slate-700 flex items-center gap-1">
                {{ dayName(day.date) }}
                <AcademicCapIcon v-if="day.is_exam_window" class="w-3.5 h-3.5 text-indigo-500" title="Quarter Final Exam window — caps exempt for Long Test/Quarterly Exam entries" />
              </h3>
              <span :class="['text-[10px] font-bold', day.over_daily && !day.is_exam_window ? 'text-danger-600' : 'text-slate-400']"
                :title="`${day.graded_count}/${wat.limits.daily_graded} graded · ${day.major_count}/${wat.limits.daily_major} major`">
                {{ day.graded_count }}/{{ wat.limits.daily_graded }} · {{ day.major_count }}M/{{ wat.limits.daily_major }}
              </span>
            </div>

            <div v-if="!day.items.length" class="flex-1 flex items-center justify-center text-[11px] text-slate-300 py-6">
              No assessments
            </div>

            <div v-else class="space-y-2">
              <div v-for="item in day.items" :key="item.id"
                class="rounded-lg border border-slate-100 bg-slate-50/60 p-2">
                <p class="text-xs font-semibold text-slate-800 leading-tight">{{ item.subject_name }}</p>
                <p class="text-[11px] text-slate-600 leading-tight mt-0.5">{{ item.title }}</p>
                <div class="flex flex-wrap gap-1 mt-1">
                  <span class="px-1.5 py-0.5 rounded bg-indigo-100 text-indigo-700 text-[10px] font-medium">{{ item.type_label }}</span>
                  <span v-if="item.is_major" class="px-1.5 py-0.5 rounded bg-amber-100 text-amber-700 text-[10px] font-semibold uppercase tracking-wide">Major</span>
                  <span v-if="!item.is_graded" class="px-1.5 py-0.5 rounded bg-slate-100 text-slate-500 text-[10px] font-semibold uppercase tracking-wide">Non-graded</span>
                </div>
                <p class="text-[10px] text-slate-400 italic mt-1">{{ item.teacher_name }}</p>

                <div v-if="item.compliance !== null" class="mt-1.5">
                  <div class="flex items-center justify-between text-[10px] text-slate-500 mb-0.5">
                    <span>Compliance</span>
                    <span class="font-semibold">{{ item.compliance }}% ({{ item.submitted_count }}/{{ item.roster_count }})</span>
                  </div>
                  <div class="h-1.5 rounded-full bg-slate-100 overflow-hidden">
                    <div :class="['h-full rounded-full', complianceColor(item.compliance)]"
                      :style="{ width: Math.min(100, item.compliance) + '%' }"></div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </template>

      <div v-else class="bg-white rounded-xl border border-slate-100 p-10 text-center text-sm text-slate-400">
        Select a section to view its Weekly Assessment Tracker.
      </div>
    </div>
  </AdminLayout>
</template>
