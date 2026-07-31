<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppModal from '@/Components/AppModal.vue'
import AppTabs from '@/Components/AppTabs.vue'
import { confirmAction } from '@/Composables/useConfirm.js'
import { isWithinScheduledWeek } from '@/Utils/ClassRecord/watUtils.js'
import Swal from 'sweetalert2'
import {
  LockClosedIcon,
  LockOpenIcon,
  CheckCircleIcon,
  ArrowDownTrayIcon,
  PlusIcon,
  XMarkIcon,
  DocumentDuplicateIcon,
  Cog6ToothIcon,
  ChartBarIcon,
  ClipboardDocumentListIcon,
  PlayCircleIcon,
  CalendarDaysIcon,
  BoltIcon,
  ClockIcon,
  ExclamationTriangleIcon,
} from '@heroicons/vue/24/outline'
import AppDatePicker from '@/Components/AppDatePicker.vue'
import ScoreGrid from './components/ScoreGrid.vue'
import AttendanceGrid from './components/AttendanceGrid.vue'
import IlaGrid from './components/IlaGrid.vue'
import SectionAssessmentCalendar from './components/SectionAssessmentCalendar.vue'
import GradingOptionDetails from './components/GradingOptionDetails.vue'
import { adjectivalColor } from '@/Utils/ClassRecord/gradeUtils.js'

const props = defineProps({
  classRecord:        Object,
  isAdmin:            { type: Boolean, default: false },
  isMonitorView:      { type: Boolean, default: false },
  gradingOptions:     { type: Array, default: () => [] },
  stanineLookup:      { type: Array, default: () => [] },
  isCurrentSY:        { type: Boolean, default: true },
  currentSYName:      { type: String, default: null },
  sameSubjectRecords: { type: Array, default: () => [] },
  siblings:           { type: Array, default: () => [] },
  quizzes:            { type: Array, default: () => [] },
  scheduledDays:      { type: Array, default: () => [] },
})

const page = usePage()

// ── Status badge ──────────────────────────────────────────────────────────────
function statusBadge(status) {
  return {
    draft:     'slate',
    submitted: 'blue',
    checked:   'green',
  }[status] ?? 'slate'
}

function hostQuiz(quiz) {
  if (quiz.question_count === 0) {
    alert('Add at least one question before hosting a session.')
    return
  }
  router.post(route('quiz.sessions.store', quiz.id))
}

// ── Quarter tabs ──────────────────────────────────────────────────────────────
const activeQuarter = ref(1)
const activeSubTab  = ref('setup')  // 'setup' | 'scores' | 'attendance'

const currentQuarterData = computed(() =>
  props.classRecord.quarters?.find(q => q.quarter === activeQuarter.value) ?? null
)

// ── Per-quarter grading option ─────────────────────────────────────────────────
// The option in force for the active quarter: the quarter's override if set,
// else the record default. Leaves carry the weight + assessments.
function leavesOf(option) {
  const cats = option?.categories ?? []
  const parentIds = new Set(cats.map(c => c.parent_id).filter(Boolean))
  const byId = Object.fromEntries(cats.map(c => [c.id, c]))
  return cats
    .filter(c => !parentIds.has(c.id))
    .map(c => ({
      ...c,
      display_name: c.parent_id && byId[c.parent_id] ? `${byId[c.parent_id].name} · ${c.name}` : c.name,
    }))
    .sort((a, b) => a.sort_order - b.sort_order)
}

const currentGradingOption = computed(() =>
  currentQuarterData.value?.grading_option ?? props.classRecord.grading_option
)

// ── Shared (PEHM) record co-teacher scoping ───────────────────────────────────
// Which subject_id(s), if any, the CURRENT user owns on this record — empty
// for a normal (non-shared) record, or for an admin/monitor who isn't
// personally one of the co-teachers. Used to scope which grading-category
// columns and attendance dates this user may edit vs. view read-only.
const ownedSubjectIds = computed(() => {
  const userId = page.props.auth?.user?.id
  const coTeachers = props.classRecord.co_teachers ?? []
  return coTeachers
    .filter(ct => ct.user_id === userId)
    .map(ct => ct.subject_id)
})

// Every distinct subject on a shared record, for the attendance subject
// switcher — empty for a normal (non-shared) record.
const sharedSubjects = computed(() => {
  const coTeachers = props.classRecord.co_teachers ?? []
  const seen = new Map()
  for (const ct of coTeachers) {
    if (ct.subject_id && !seen.has(ct.subject_id)) {
      seen.set(ct.subject_id, { id: ct.subject_id, name: ct.subject?.name ?? `Subject #${ct.subject_id}` })
    }
  }
  return [...seen.values()]
})

const currentLeafCategories = computed(() => leavesOf(currentGradingOption.value))

/**
 * Can the current user edit THIS leaf category's assessments in the Setup
 * tab? Same rule as ScoreGrid's canEditCategory() — a category with no
 * subject_id (normal case) is always editable subject to isLocked/isReadOnly;
 * a subject-tagged leaf on a shared PEHM record is scoped to whichever
 * teacher owns that subject. Admins always pass.
 */
function canEditCategory(cat) {
  if (props.isAdmin) return true
  if (!cat.subject_id) return true
  return ownedSubjectIds.value.includes(cat.subject_id)
}

const isLocked   = computed(() => currentQuarterData.value?.is_locked ?? false)
// Past school year, or viewing as a CID Chief / Academic Unit Head monitor —
// both render every tab fully read-only (no new write surface, no separate
// component needed — every edit affordance in this page already checks this).
const isReadOnly = computed(() => !props.isCurrentSY || props.isMonitorView)

// Sub-tab bar (Setup / Scores / Attendance / ILA / Live Quiz)
// ILA only applies to subjects that reserve an Independent Learning Period.
const subTabs = computed(() => [
  { key: 'setup',       label: 'Setup',              icon: Cog6ToothIcon },
  { key: 'scores',      label: 'Scores & Grades',    icon: ChartBarIcon },
  { key: 'attendance',  label: 'Attendance',          icon: ClipboardDocumentListIcon },
  ...(props.classRecord.subject?.has_ilp ? [{ key: 'ila', label: 'ILA', icon: BoltIcon }] : []),
  { key: 'quiz',        label: 'Live Quiz',           icon: PlayCircleIcon },
])

watch(subTabs, (tabs) => {
  if (!tabs.some(t => t.key === activeSubTab.value)) activeSubTab.value = 'setup'
})

// ── Assessment setup ──────────────────────────────────────────────────────────
// Build editable assessment rows from the grading option categories
const assessmentDraft = ref({})

function buildDraft(quarter) {
  const draft = {}
  const option = quarter?.grading_option ?? props.classRecord.grading_option
  for (const cat of leavesOf(option)) {
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
        is_graded:           found ? !!found.is_graded : true,
        activity_date:       found?.activity_date ?? '',
        max_score:           found?.max_score ?? '',
        _saved:              !!found,
        _db_id:              found?.id ?? null,  // track DB id for delete validation
        _prevDate:           found?.activity_date ?? '',
        _dateWarning:        null,
        _pendingDeletion:    !!found?.pending_deletion_request,
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
    is_graded:           true,
    activity_date:       '',
    max_score:           '',
    _saved:              false,
    _db_id:              null,
    _prevDate:           '',
    _dateWarning:        null,
  })
}

async function removeAssessmentRow(catId, idx) {
  const row = assessmentDraft.value[catId][idx]

  if (row._pendingDeletion) {
    await Swal.fire('Deletion Already Requested', 'A deletion request for this assessment is awaiting Assistant CID Chief for Academic Affairs approval.', 'info')
    return
  }

  // Plotted (dated) + already-saved assessments are considered announced to
  // students during their scheduled week — while that week is current, they
  // can no longer be dropped from the grid directly. File a deletion request
  // instead; the row stays until ACIDAA acts on it. Outside that window
  // (week hasn't arrived yet, or has already passed) fall through to a
  // direct delete, same as an unplotted row.
  if (row._db_id && row.activity_date && isWithinScheduledWeek(row.activity_date)) {
    requestDeletionRow.value = row
    requestDeletionReason.value = ''
    showRequestDeletionModal.value = true
    return
  }

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

// ── Request deletion of a plotted assessment (ACIDAA approval required) ───────

const showRequestDeletionModal = ref(false)
const requestDeletionRow = ref(null)
const requestDeletionReason = ref('')
const submittingDeletionRequest = ref(false)

async function submitDeletionRequest() {
  const row = requestDeletionRow.value
  if (! row || ! requestDeletionReason.value.trim()) return

  submittingDeletionRequest.value = true
  try {
    await axios.post(
      route('class-records.assessments.request-deletion', {
        classRecord: props.classRecord.id, q: activeQuarter.value, assessment: row._db_id,
      }),
      { reason: requestDeletionReason.value }
    )
    row._pendingDeletion = true
    showRequestDeletionModal.value = false
    await Swal.fire({
      icon: 'success', title: 'Deletion requested',
      text: 'Awaiting Assistant CID Chief for Academic Affairs approval.',
      timer: 2000, showConfirmButton: false,
    })
  } catch (err) {
    Swal.fire('Error', err.response?.data?.message ?? 'Failed to submit deletion request.', 'error')
  } finally {
    submittingDeletionRequest.value = false
  }
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

// Title, Activity Date, and Max Score are all required per row. A row left
// completely untouched is just an unused placeholder and is skipped quietly,
// but a row with only SOME of those fields filled in used to be silently
// dropped from the save — the teacher would think it saved when it hadn't.
// Now it blocks the save with a specific error instead.
async function saveSetup() {
  savingSetup.value = true
  setupErrors.value = []

  const allRows = Object.values(assessmentDraft.value).flat()
  const incomplete = []
  const assessments = []

  for (const a of allRows) {
    const hasTitle = !!a.title
    const hasDate  = !!a.activity_date
    const hasScore = a.max_score !== '' && a.max_score !== null && a.max_score !== undefined

    if (!hasTitle && !hasDate && !hasScore) continue // untouched placeholder row

    if (!hasTitle || !hasDate || !hasScore) {
      const cat = currentLeafCategories.value.find(c => c.id === a.grading_category_id)
      const label = cat ? `${cat.code}${a.assessment_number}` : `Row ${a.assessment_number}`
      const missing = []
      if (!hasTitle) missing.push('Title')
      if (!hasDate)  missing.push('Activity Date')
      if (!hasScore) missing.push('Max Score')
      incomplete.push(`${label}: missing ${missing.join(', ')}`)
      continue
    }

    assessments.push({
      id:                   a._db_id,
      grading_category_id:  a.grading_category_id,
      assessment_number:    a.assessment_number,
      title:                a.title,
      is_graded:            a.is_graded,
      activity_date:        a.activity_date,
      max_score:            a.max_score,
    })
  }

  if (incomplete.length) {
    setupErrors.value = incomplete.map(m => `Incomplete row — ${m}.`)
    savingSetup.value = false
    return
  }

  if (!assessments.length) {
    setupErrors.value = ['Add at least one assessment with a title, activity date, and max score.']
    savingSetup.value = false
    return
  }

  try {
    const { data } = await axios.post(
      route('class-records.assessments.upsert', { classRecord: props.classRecord.id, q: activeQuarter.value }),
      { assessments }
    )
    if (data.warnings?.length) {
      await Swal.fire({ icon: 'warning', title: 'Saved — please double-check', html: data.warnings.join('<br>') })
    } else {
      await Swal.fire({ icon: 'success', title: 'Setup saved!', timer: 1000, showConfirmButton: false })
    }
    router.reload({ only: ['classRecord'] })
    loadSectionCalendar()
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

// ── Section assessment calendar (visibility + 3/day enforcement) ──────────────

const sectionCalendarDays = ref([])
const showSectionCalendar = ref(false)

async function loadSectionCalendar() {
  if (!props.classRecord.section_id) return
  try {
    const { data } = await axios.get(
      route('class-records.assessments.section-calendar', props.classRecord.id)
    )
    sectionCalendarDays.value = data
  } catch { /* calendar is a reference view only — fail silently */ }
}

onMounted(loadSectionCalendar)

// IDs of assessments already saved under the quarter currently being edited —
// excluded from the server baseline below since the live draft rows replace them.
const currentQuarterAssessmentIds = computed(() =>
  new Set((currentQuarterData.value?.assessments ?? []).map(a => a.id))
)

// ── WAT rules (mirrors WatRuleService — server re-validates authoritatively) ──
const WAT = { dailyGraded: 3, dailyMajor: 2, weeklyGraded: 15, weeklyMajor: 6 }

const categoriesById = computed(() =>
  Object.fromEntries(currentLeafCategories.value.map(c => [c.id, c]))
)

// Major = worth ≥10% of the quarterly grade (type is server-derived from the
// category, so the weight rule is the whole client-side check)
function isMajorRow(row) {
  const cat = categoriesById.value[row.grading_category_id]
  if (!cat) return false
  // rounding guards the exact-10% boundary against float division (0.30 / 3)
  return Math.round((cat.weight / Math.max(1, cat.max_assessments)) * 1e6) / 1e6 >= 0.10
}

// Overlays this quarter's own unsaved-but-dated draft rows onto the
// server-side section calendar so a stale/leftover row (dated via a prior
// calendar click or table edit, never saved) is visible and removable
// instead of silently consuming a day's WAT budget with no on-screen trace.
// Server-saved rows for this quarter are already present via
// sectionCalendarDays (they're real, persisted rows) — only rows with no
// _db_id are added here, tagged is_pending so the calendar can render them
// distinctly (e.g. a dashed/amber treatment) from confirmed entries.
const calendarDaysWithPending = computed(() => {
  const byDate = new Map(sectionCalendarDays.value.map(d => [d.date, { ...d, items: [...d.items] }]))

  for (const cat of currentLeafCategories.value) {
    for (const row of assessmentDraft.value[cat.id] ?? []) {
      if (!row.activity_date || row._db_id) continue
      const date = row.activity_date
      const entry = byDate.get(date) ?? { date, count: 0, graded_count: 0, major_count: 0, items: [] }
      entry.items.push({
        id:            `pending-${cat.id}-${row.assessment_number}`,
        title:         row.title || '(untitled)',
        subject_name:  props.classRecord.subject_name,
        teacher_name:  null,
        category_code: cat.code,
        is_graded:     row.is_graded,
        is_major:      isMajorRow(row),
        is_own_record: true,
        is_pending:    true,
      })
      entry.count += 1
      if (row.is_graded) {
        entry.graded_count = (entry.graded_count ?? 0) + 1
        if (isMajorRow(row)) entry.major_count = (entry.major_count ?? 0) + 1
      }
      byDate.set(date, entry)
    }
  }

  return [...byDate.values()]
})

// Monday of the date's week, minus 3 days = the preceding Friday, cutoff at
// 12:00 NN (not end of day) so coordinators/CID Chief have the Friday
// afternoon to review the week's plotted assessments.
function plottingDeadline(dateStr) {
  const d = new Date(dateStr + 'T00:00:00')
  const monday = new Date(d)
  monday.setDate(d.getDate() - ((d.getDay() + 6) % 7))
  const friday = new Date(monday)
  friday.setDate(monday.getDate() - 3)
  friday.setHours(12, 0, 0, 0)
  return friday
}

// Per-date graded/major counts for this section across the whole school year:
// server baseline (other quarters/subjects) + this quarter's live, unsaved draft.
const dateCounts = computed(() => {
  const map = new Map()
  const bump = (date, graded, major) => {
    const entry = map.get(date) ?? { graded: 0, major: 0 }
    entry.graded += graded
    entry.major  += major
    map.set(date, entry)
  }
  for (const day of sectionCalendarDays.value) {
    const others = day.items.filter(i => !currentQuarterAssessmentIds.value.has(i.id) && i.is_graded !== false)
    bump(day.date, others.length, others.filter(i => i.is_major).length)
  }
  for (const rows of Object.values(assessmentDraft.value)) {
    for (const row of rows) {
      if (row.activity_date && row.is_graded) {
        bump(row.activity_date, 1, isMajorRow(row) ? 1 : 0)
      }
    }
  }
  return map
})

// ── Schedule-day restriction ──────────────────────────────────────────────────
// Faculty may only date assessments on days the class meets per the schedule.
// Fail-open when no schedule is plotted yet; admins keep every day pickable
// so they can plot make-up/special dates (server warns instead of blocking).
const WEEKDAY_ORDER = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']

const disabledWeekdays = computed(() => {
  if (props.isAdmin || !props.scheduledDays.length) return []
  return WEEKDAY_ORDER.filter(d => !props.scheduledDays.includes(d))
})

const meetingDaysLabel = computed(() =>
  WEEKDAY_ORDER.filter(d => props.scheduledDays.includes(d)).map(d => d.slice(0, 3)).join(' · ')
)

function onDateChange(row) {
  if (!row.activity_date) {
    row._dateWarning = null
    row._prevDate     = ''
    return
  }
  const counts = dateCounts.value.get(row.activity_date) ?? { graded: 0, major: 0 }
  if (row.is_graded && counts.graded > WAT.dailyGraded) {
    row._dateWarning  = `Section already has ${WAT.dailyGraded} graded assessments on ${row.activity_date} — pick another date.`
    row.activity_date = row._prevDate
  } else if (row.is_graded && isMajorRow(row) && counts.major > WAT.dailyMajor) {
    row._dateWarning  = `Section already has ${WAT.dailyMajor} major assessments on ${row.activity_date} — pick another date.`
    row.activity_date = row._prevDate
  } else if (!props.isAdmin && new Date() > plottingDeadline(row.activity_date)) {
    const deadline = plottingDeadline(row.activity_date).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' })
    row._dateWarning  = `Plotting deadline passed — assessments must be plotted by 12:00 NN of the Friday before their week (${deadline}).`
    row.activity_date = row._prevDate
  } else {
    row._dateWarning = null
    row._prevDate    = row.activity_date
  }
}

// ── Click-to-schedule calendar wiring ──────────────────────────────────────
// Exposes the Setup tab's assessment rows to SectionAssessmentCalendar so a
// teacher can click a day and create/assign an assessment on it, instead of
// only using the per-row date picker in the table. Every category that still
// has editable room (either an existing untitled/undated placeholder row, or
// simply "add another") is offered — the picker lets the teacher type the
// title right there, so the calendar can be used as the primary entry point
// without visiting the table first. The calendar defers to the exact same
// dateCounts/deadline/schedule-day checks used elsewhere on this page — the
// server still re-validates on Save regardless.
const pendingAssessmentCategories = computed(() => {
  const cats = []
  for (const cat of currentLeafCategories.value) {
    if (!canEditCategory(cat)) continue
    if (isLocked.value || isReadOnly.value) continue
    const rows = assessmentDraft.value[cat.id] ?? []
    // An undated row is the natural target for a new date. A row that
    // already has a date but was never saved (_db_id is null) is a leftover
    // from an earlier, uncommitted attempt — e.g. dated via the calendar,
    // then the page was reused before Save was clicked. Surfacing it here
    // (instead of only ever creating a fresh row) prevents the picker from
    // silently stacking a second draft entry on top of the first, which
    // used to double-count toward the daily/weekly cap client-side while
    // the teacher could see only one (the earlier, still-unsaved) entry.
    const openRow = rows.find(r => !r.activity_date)
    const unsavedDatedRows = rows.filter(r => r.activity_date && !r._db_id)
    cats.push({
      key:      String(cat.id),
      label:    `${cat.code} — ${cat.display_name}`,
      catId:    cat.id,
      openRow,
      unsavedDatedRows,
    })
  }
  return cats
})

// Schedule-day + plotting-deadline feasibility for a candidate date, mirrors
// the same two checks onDateChange() applies per-row (fail-open on schedule
// when no scheduledDays are known yet; admins bypass both like elsewhere).
function calendarDateFeasibility(dateStr) {
  if (!props.isAdmin && props.scheduledDays.length) {
    const weekday = WEEKDAY_ORDER[(new Date(`${dateStr}T00:00:00`).getDay() + 6) % 7]
    if (!props.scheduledDays.includes(weekday)) {
      return { ok: false, reason: `${props.classRecord.subject_name ?? 'This subject'} has no scheduled class with this section on ${weekday}s.` }
    }
  }
  if (!props.isAdmin && new Date() > plottingDeadline(dateStr)) {
    const deadline = plottingDeadline(dateStr).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' })
    return { ok: false, reason: `Plotting deadline passed — must be plotted by 12:00 NN of the Friday before (${deadline}).` }
  }
  return { ok: true, reason: null }
}

async function onCalendarSchedule({ catId, title, maxScore, date, targetRecordIds }) {
  try {
    const { data } = await axios.post(
      route('class-records.assessments.plot', {
        classRecord: props.classRecord.id,
        q: activeQuarter.value,
      }),
      {
        grading_category_id: catId,
        title,
        max_score: maxScore,
        is_graded: true,
        activity_date: date,
        target_class_record_ids: targetRecordIds,
      },
    )

    await loadSectionCalendar()
    router.reload({ only: ['classRecord'] })

    if (data.skipped?.length) {
      await Swal.fire({
        icon: 'warning',
        title: 'Assessment saved',
        html: `Saved directly to ${data.created.length} Class Record Setup(s).<br><br>` +
          data.skipped.map(row => `<div class="text-left text-xs"><strong>${row.label}</strong>: ${row.reason}</div>`).join(''),
      })
    } else {
      await Swal.fire({
        icon: 'success',
        title: 'Assessment saved to Setup',
        text: `Saved directly to ${data.created.length} section(s).`,
        timer: 1600,
        showConfirmButton: false,
      })
    }
  } catch (err) {
    Swal.fire(
      'Could not save assessment',
      Object.values(err.response?.data?.errors ?? {}).flat()[0]
        ?? err.response?.data?.message
        ?? 'That assessment could not be saved.',
      'error',
    )
  }
}

// Removes a stale/leftover unsaved-but-dated row directly from the calendar
// (the "Unsaved" badge + X button in the day-detail panel) — the synthetic
// id `pending-{catId}-{assessmentNumber}` set in calendarDaysWithPending()
// is parsed back to find and clear that exact row. Clears the date rather
// than splicing the row out entirely, so its title/max-score (if any were
// typed) aren't lost — same soft-touch as un-dating a row in the table.
function onCalendarClearPending(item) {
  const match = /^pending-(\d+)-(\d+)$/.exec(item.id)
  if (!match) return
  const [, catId, assessmentNumber] = match.map(Number)
  const row = (assessmentDraft.value[catId] ?? []).find(r => r.assessment_number === assessmentNumber)
  if (!row) return
  row.activity_date = ''
  row._prevDate      = ''
  row._dateWarning   = null
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

// ── Apply this setup to other sections (push direction) ───────────────────────

const showApplyToSectionsModal = ref(false)
const applyToSectionIds         = ref([])
const applyingToSections        = ref(false)

const applyEligibleRecords = computed(() =>
  sameSubjectRecords.value.filter(r => r.school_year === props.currentSYName)
)
const applyPastYearRecords = computed(() =>
  sameSubjectRecords.value.filter(r => r.school_year !== props.currentSYName)
)

function openApplyToSectionsModal() {
  applyToSectionIds.value = []
  showApplyToSectionsModal.value = true
}

async function applyToSections() {
  if (!applyToSectionIds.value.length) return
  applyingToSections.value = true
  try {
    const { data } = await axios.post(
      route('class-records.assessments.apply-to-sections', { classRecord: props.classRecord.id, q: activeQuarter.value }),
      { target_class_record_ids: applyToSectionIds.value }
    )
    showApplyToSectionsModal.value = false

    const appliedLines = (data.applied ?? []).map(a => {
      const warn = a.warnings?.length ? ` (${a.warnings.join(' ')})` : ''
      return `✓ ${a.label}: ${a.count} assessment(s) applied${warn}`
    })
    const skippedLines = (data.skipped ?? []).map(s => `✗ ${s.label}: ${s.reason}`)

    await Swal.fire({
      icon: skippedLines.length && !appliedLines.length ? 'warning' : 'success',
      title: 'Apply to Other Sections',
      html: [...appliedLines, ...skippedLines].map(l => `<div class="text-left text-xs">${l}</div>`).join(''),
    })
  } catch (err) {
    Swal.fire('Error', err.response?.data?.message ?? 'Failed to apply setup.', 'error')
  } finally {
    applyingToSections.value = false
  }
}

// ── Final annual grades ───────────────────────────────────────────────────────
const showFinalGrades    = ref(false)
const finalGrades        = ref([])
const finalGradesLoading = ref(false)
const finalGradesError   = ref(null)
const finalGradesMode    = ref('numeric')  // 'numeric' | 'compliance' — set from the API response

const allQuartersExist = computed(() =>
  [1, 2, 3, 4].every(q => (props.classRecord.quarters ?? []).some(qt => qt.quarter === q))
)

// The record's OWN pass label (e.g. "Completed") — used to color-code the
// remark column green/red without re-deriving the rule client-side.
const complianceSyPassLabel = computed(() => props.classRecord.grading_option?.compliance_pass_label ?? 'Completed')

const finalGradesSyRuleLabel = computed(() => {
  const rule = props.classRecord.grading_option?.compliance_sy_rule
  return rule === 'average_threshold' ? 'average of all 4 quarters vs. threshold' : 'every quarter must pass'
})

function fmtCompliancePct(val) {
  return val === null || val === undefined ? '—' : `${Number(val).toFixed(2)}%`
}

async function loadFinalGrades() {
  finalGradesLoading.value = true
  finalGradesError.value   = null
  try {
    const { data } = await axios.get(route('class-records.final-grades', props.classRecord.id))
    finalGrades.value = data.students ?? []
    finalGradesMode.value = data.gradingMode ?? 'numeric'
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
  const confirmed = await confirmAction({
    title: `Lock Quarter ${activeQuarter.value}?`,
    text: 'Score entry will be disabled after locking. Admins can unlock it.',
    confirmText: 'Lock',
  })
  if (!confirmed) return

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
  const confirmed = await confirmAction({
    title: 'Submit for Review?',
    text: 'The class record will be locked for editing and sent to the Academic Unit Head for review.',
    confirmText: 'Yes, Submit',
  })
  if (!confirmed) return

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

// ── Change grading option ────────────────────────────────────────────────────
const showChangeGradingOptionModal = ref(false)
const changeGradingOptionId        = ref(null)
const changingGradingOption        = ref(false)
const changeGradingOptionError     = ref(null)

const changeGradingOptionSelected = computed(() =>
  props.gradingOptions.find(o => o.id === changeGradingOptionId.value) ?? null
)

function openChangeGradingOptionModal() {
  changeGradingOptionId.value    = props.classRecord.grading_option_id
  changeGradingOptionError.value = null
  showChangeGradingOptionModal.value = true
}

async function saveGradingOptionChange() {
  changingGradingOption.value    = true
  changeGradingOptionError.value = null
  try {
    await axios.put(route('class-records.update', props.classRecord.id), {
      grading_option_id: changeGradingOptionId.value,
    })
    showChangeGradingOptionModal.value = false
    router.reload()
  } catch (err) {
    changeGradingOptionError.value = err.response?.data?.message ?? 'Failed to change grading option.'
  } finally {
    changingGradingOption.value = false
  }
}

// ── Per-quarter grading option change ─────────────────────────────────────────
const showQuarterOptionModal   = ref(false)
const quarterOptionId          = ref(null)
const savingQuarterOption      = ref(false)
const quarterOptionError       = ref(null)
const applyToRemainingQuarters = ref(false)

// Options applicable to the active quarter (null applicable_quarters = all).
function optionAppliesToQuarter(opt, q) {
  const aq = opt?.applicable_quarters
  return !aq || aq.length === 0 || aq.map(Number).includes(q)
}
const applicableGradingOptions = computed(() =>
  props.gradingOptions.filter(o => optionAppliesToQuarter(o, activeQuarter.value))
)

const quarterOptionSelected = computed(() =>
  props.gradingOptions.find(o => o.id === quarterOptionId.value) ?? null
)

function openQuarterOptionModal() {
  quarterOptionId.value = currentGradingOption.value?.id ?? props.classRecord.grading_option_id
  quarterOptionError.value = null
  applyToRemainingQuarters.value = false
  showQuarterOptionModal.value = true
}

/** Put the grading option for one quarter; returns null on success or a skip/failure reason. */
async function applyGradingOptionToQuarter(q) {
  try {
    await axios.put(
      route('class-records.quarters.grading-option', { classRecord: props.classRecord.id, q }),
      { grading_option_id: quarterOptionId.value },
    )
    return null
  } catch (err) {
    return err.response?.data?.message ?? 'Failed to change grading option.'
  }
}

async function saveQuarterOption() {
  savingQuarterOption.value = true
  quarterOptionError.value  = null

  const failure = await applyGradingOptionToQuarter(activeQuarter.value)
  if (failure) {
    quarterOptionError.value = failure
    savingQuarterOption.value = false
    return
  }

  const results = [{ quarter: activeQuarter.value, status: 'applied' }]

  if (applyToRemainingQuarters.value) {
    const option = quarterOptionSelected.value
    for (let q = activeQuarter.value + 1; q <= 4; q++) {
      const qt = props.classRecord.quarters?.find(x => x.quarter === q)
      if (qt?.is_locked) {
        results.push({ quarter: q, status: 'skipped', reason: 'locked' })
        continue
      }
      if ((qt?.assessments?.length ?? 0) > 0) {
        results.push({ quarter: q, status: 'skipped', reason: 'already has assessments' })
        continue
      }
      if (!optionAppliesToQuarter(option, q)) {
        results.push({ quarter: q, status: 'skipped', reason: 'option does not apply to this quarter' })
        continue
      }
      const err = await applyGradingOptionToQuarter(q)
      results.push(err ? { quarter: q, status: 'failed', reason: err } : { quarter: q, status: 'applied' })
    }
  }

  showQuarterOptionModal.value = false
  savingQuarterOption.value = false
  await router.reload({ only: ['classRecord'] })

  if (results.length > 1) {
    const applied = results.filter(r => r.status === 'applied').map(r => `Q${r.quarter}`)
    const rest = results.filter(r => r.status !== 'applied').map(r => `Q${r.quarter} (${r.reason})`)
    const lines = [`Applied: ${applied.join(', ')}.`]
    if (rest.length) lines.push(`Not changed: ${rest.join(', ')}.`)
    Swal.fire('Grading Option Updated', lines.join(' '), 'success')
  }
}

</script>

<template>
  <Head :title="`Class Record — ${classRecord.display_name}`" />
  <AdminLayout :title="classRecord.display_name">
    <div class="space-y-5">

      <AppPageHeader
        :title="classRecord.display_name"
        :subtitle="`${classRecord.year_level_section} · SY ${classRecord.school_year} · ${classRecord.grading_option?.name ?? ''}`"
        :breadcrumb="[
          { label: 'Class Records', href: route('class-records.page.index') },
          { label: classRecord.display_name },
        ]">
        <template #actions>
          <AppBadge :color="statusBadge(classRecord.status)">
            {{ classRecord.status === 'checked' ? 'Checked ✓' : classRecord.status.charAt(0).toUpperCase() + classRecord.status.slice(1) }}
          </AppBadge>
          <AppButton v-if="classRecord.can_change_grading_option" variant="secondary" @click="openChangeGradingOptionModal">
            <Cog6ToothIcon class="h-4 w-4" /> Change Grading Option
          </AppButton>
          <AppButton variant="secondary" as="a" :href="route('class-records.export', classRecord.id)">
            <ArrowDownTrayIcon class="h-4 w-4" /> Export All
          </AppButton>
          <AppButton v-if="classRecord.status === 'draft' && isCurrentSY" @click="submitRecord">
            Submit for Review
          </AppButton>
          <AppButton v-if="classRecord.status === 'submitted' && isAdmin" variant="success" @click="checkRecord">
            <CheckCircleIcon class="h-4 w-4" /> Mark as Checked
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Co-teacher roster: only shown for a shared (e.g. PEHM) record -->
      <div v-if="classRecord.co_teachers?.length" class="flex flex-wrap items-center gap-2 text-sm">
        <span class="text-slate-500 font-medium">Shared with:</span>
        <span v-for="ct in classRecord.co_teachers" :key="ct.id"
          class="inline-flex items-center gap-1.5 rounded-full bg-indigo-50 text-indigo-700 px-3 py-1 text-xs font-medium">
          {{ ct.subject?.name ?? 'Subject' }} — {{ ct.user?.name ?? '—' }}
          <span v-if="ct.is_primary" class="text-indigo-400">(primary)</span>
        </span>
      </div>

      <!-- Read-only banner: past school year, or a CID Chief / AUH monitor view -->
      <div v-if="isReadOnly"
        class="flex items-start gap-3 rounded-xl border border-warning-100 bg-warning-50 px-4 py-3 text-sm text-warning-700">
        <LockClosedIcon class="h-4 w-4 mt-0.5 shrink-0 text-warning-500" />
        <span v-if="isMonitorView">
          You're viewing this class record in <strong>read-only monitoring mode</strong>. You can review activity and
          status here, but only the teacher or an administrator can make changes.
        </span>
        <span v-else>
          This class record is from <strong>SY {{ classRecord.school_year }}</strong> and is
          <strong>read-only</strong>. The school year is no longer active.
        </span>
      </div>

      <!-- Quarter tabs -->
      <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200/70">
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

        <div class="px-4 pt-1">
        <AppTabs :tabs="subTabs" v-model="activeSubTab">

        <!-- ── Live Quiz sub-tab ─────────────────────────────────────────── -->
        <div v-if="activeSubTab === 'quiz'" class="p-5">
          <div v-if="!isCurrentSY" class="bg-warning-50 border border-warning-100 text-warning-700 text-sm rounded-lg px-4 py-3 mb-4">
            This class record is from a past school year — quizzes can't be hosted for locked records.
          </div>
          <template v-else>
            <div class="flex justify-end mb-3">
              <AppButton as="a" size="sm"
                :href="route('quiz.create', { source_type: 'class_record', source_id: classRecord.id })">
                <PlusIcon class="w-4 h-4" /> New Quiz
              </AppButton>
            </div>
            <div v-if="quizzes.length === 0" class="text-center py-10 text-sm text-slate-400">
              No quizzes yet — create one to run a live review game with this class.
            </div>
            <div v-else class="bg-white rounded-xl border border-slate-200 divide-y divide-slate-100">
              <div v-for="q in quizzes" :key="q.id" class="flex items-center justify-between px-4 py-3">
                <div>
                  <p class="text-sm font-medium text-slate-700">{{ q.title }}</p>
                  <span class="text-xs text-slate-400 capitalize">{{ q.status }} · {{ q.question_count }} question{{ q.question_count === 1 ? '' : 's' }}</span>
                </div>
                <div class="flex items-center gap-3">
                  <button type="button" class="text-sm text-success-600 hover:underline" @click="hostQuiz(q)">Host</button>
                  <a :href="route('quiz.edit', q.id)" class="text-sm text-indigo-600 hover:underline">Manage</a>
                </div>
              </div>
            </div>
          </template>
        </div>

        <!-- ── Setup sub-tab ─────────────────────────────────────────────── -->
        <div v-if="activeSubTab === 'setup'" class="p-5">

          <!-- Lock / unlock controls -->
          <div class="flex items-center justify-between mb-4">
            <p class="text-xs text-slate-500">
              Configure assessment titles, dates, and max scores for each category.
            </p>
            <div class="flex items-center gap-2">
              <AppButton variant="secondary" size="sm"
                :disabled="!classRecord.section_id"
                :title="!classRecord.section_id ? 'No section linked to this class record' : ''"
                @click="showSectionCalendar = true">
                <CalendarDaysIcon class="h-3.5 w-3.5" /> {{ pendingAssessmentCategories.length ? 'Schedule via Calendar' : 'View Section Calendar' }}
              </AppButton>
              <template v-if="!isReadOnly">
                <AppButton v-if="!isLocked" variant="warning" size="sm" @click="lockQuarter">
                  <LockClosedIcon class="h-3.5 w-3.5" /> Lock Quarter
                </AppButton>
                <AppButton v-if="isLocked && isAdmin" variant="secondary" size="sm" @click="unlockQuarter">
                  <LockOpenIcon class="h-3.5 w-3.5" /> Unlock
                </AppButton>
                <AppBadge v-if="isLocked && !isAdmin" color="amber">
                  <span class="inline-flex items-center gap-1"><LockClosedIcon class="h-3 w-3" /> Locked</span>
                </AppBadge>
              </template>
              <AppBadge v-else color="amber">
                <span class="inline-flex items-center gap-1"><LockClosedIcon class="h-3 w-3" /> Past School Year</span>
              </AppBadge>
            </div>
          </div>

          <!-- Per-quarter grading option -->
          <div class="mb-4 flex flex-wrap items-center justify-between gap-2 rounded-lg border border-slate-200 bg-slate-50/70 px-3 py-2">
            <div class="text-xs text-slate-600">
              <span class="font-semibold text-slate-700">Q{{ activeQuarter }} grading option:</span>
              {{ currentGradingOption?.name ?? '—' }}
              <span class="text-slate-400">· weights total {{ Math.round(currentLeafCategories.reduce((s, c) => s + Number(c.weight || 0), 0) * 100) }}%</span>
            </div>
            <AppButton v-if="!isLocked && !isReadOnly" variant="secondary" size="sm"
              :disabled="currentHasAssessments"
              :title="currentHasAssessments ? 'Clear this quarter\'s assessments before changing its grading option' : ''"
              @click="openQuarterOptionModal">
              <Cog6ToothIcon class="h-3.5 w-3.5" /> Change Q{{ activeQuarter }} Grading Option
            </AppButton>
          </div>

          <!-- Copy-from banner (shown only when quarter has no assessments yet) -->
          <div v-if="!isLocked && !isReadOnly && !currentHasAssessments && (quartersWithAssessments.length || sameSubjectRecords.length)"
            class="mb-4 flex flex-wrap items-center gap-2 p-3 bg-indigo-50 border border-indigo-100 rounded-lg text-xs">
            <DocumentDuplicateIcon class="h-4 w-4 text-indigo-400 shrink-0" />
            <span class="text-slate-600">No assessments yet. Copy structure from:</span>
            <AppButton v-for="q in quartersWithAssessments" :key="q.quarter"
              size="sm" :disabled="copyingFrom" @click="copyFromQuarter(q.quarter)">
              {{ copyingFrom ? 'Copying…' : `Q${q.quarter}` }}
            </AppButton>
            <AppButton v-if="sameSubjectRecords.length"
              variant="secondary" size="sm" :disabled="copyingFrom" @click="showCopyFromRecordModal = true">
              Another section…
            </AppButton>
          </div>

          <!-- Apply-to-sections banner (shown once this quarter has assessments) -->
          <div v-if="!isLocked && !isReadOnly && currentHasAssessments && sameSubjectRecords.length"
            class="mb-4 flex flex-wrap items-center gap-2 p-3 bg-indigo-50 border border-indigo-100 rounded-lg text-xs">
            <DocumentDuplicateIcon class="h-4 w-4 text-indigo-400 shrink-0" />
            <span class="text-slate-600">Teach other sections of {{ classRecord.subject_name }}?</span>
            <AppButton variant="secondary" size="sm" @click="openApplyToSectionsModal">
              Apply This Setup to Other Sections…
            </AppButton>
          </div>

          <!-- WAT plotting rules reminder -->
          <div v-if="!isLocked && !isReadOnly"
            class="mb-4 bg-slate-50 border border-slate-100 text-slate-500 rounded-lg px-3 py-2 text-[11px]">
            <span class="font-semibold text-slate-600">WAT rules:</span>
            plot assessments no later than the Friday before their week (same-week plotting is not allowed)
            · max {{ WAT.dailyGraded }} graded ({{ WAT.dailyMajor }} major) per section per day
            · max {{ WAT.weeklyGraded }} graded ({{ WAT.weeklyMajor }} major) per section per week.
            <template v-if="scheduledDays.length">
              <br><span class="font-semibold text-slate-600">Class meets:</span>
              {{ meetingDaysLabel }}<span v-if="!isAdmin"> — other days are disabled in the date picker.</span>
            </template>
          </div>

          <!-- Errors -->
          <div v-if="setupErrors.length"
            class="mb-4 bg-danger-50 border border-danger-100 text-danger-700 rounded-lg px-4 py-3 text-xs space-y-1">
            <p v-for="e in setupErrors" :key="e">{{ e }}</p>
          </div>

          <!-- Category groups -->
          <div class="space-y-6">
            <div v-for="cat in currentLeafCategories" :key="cat.id">
              <div class="flex items-center gap-2 mb-2">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700 text-xs font-bold">
                  {{ cat.code }}
                </span>
                <span class="text-sm font-semibold text-slate-700">{{ cat.display_name }}</span>
                <span class="text-xs text-slate-400">({{ Math.round(cat.weight * 100) }}%)</span>
              </div>

              <div class="overflow-x-auto rounded-lg border border-slate-100">
                <table class="min-w-full text-sm">
                  <thead class="bg-slate-50/80">
                    <tr>
                      <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500 w-16">#</th>
                      <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500">Title / Description <span class="text-danger-500">*</span></th>
                      <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500 w-32">Category</th>
                      <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500 w-36">Activity Date <span class="text-danger-500">*</span></th>
                      <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500 w-28">Max Score <span class="text-danger-500">*</span></th>
                      <th v-if="!isLocked && !isReadOnly && canEditCategory(cat)" class="px-2 py-2 w-8"></th>
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
                          :disabled="isLocked || isReadOnly || !canEditCategory(cat)"
                          :placeholder="`${cat.code}${row.assessment_number} title…`"
                          class="w-full rounded border border-slate-200 px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400 disabled:bg-slate-50 disabled:text-slate-400" />
                      </td>
                      <td class="px-4 py-2">
                        <select v-model="row.is_graded"
                          :disabled="isLocked || isReadOnly || !canEditCategory(cat)"
                          class="w-full rounded border border-slate-200 px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400 disabled:bg-slate-50 disabled:text-slate-400">
                          <option :value="true">Graded</option>
                          <option :value="false">Non-graded</option>
                        </select>
                        <span v-if="isMajorRow(row)" class="inline-block mt-1 px-1.5 py-0.5 rounded bg-amber-100 text-amber-700 text-[10px] font-semibold uppercase tracking-wide">Major</span>
                      </td>
                      <td class="px-4 py-2">
                        <AppDatePicker v-model="row.activity_date"
                          :disabled="isLocked || isReadOnly || !canEditCategory(cat)"
                          :disabled-weekdays="disabledWeekdays"
                          @change="onDateChange(row)" />
                        <p v-if="row._dateWarning" class="text-red-500 text-[11px] mt-1">{{ row._dateWarning }}</p>
                      </td>
                      <td class="px-4 py-2">
                        <input v-model.number="row.max_score" type="number" min="0.01" step="0.5"
                          :disabled="isLocked || isReadOnly || !canEditCategory(cat)"
                          placeholder="e.g. 30"
                          class="w-full rounded border border-slate-200 px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400 disabled:bg-slate-50 disabled:text-slate-400" />
                      </td>
                      <td v-if="!isLocked && !isReadOnly && canEditCategory(cat)" class="px-2 py-2 text-center">
                        <span v-if="row._pendingDeletion"
                          class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-amber-100 text-amber-700 text-[10px] font-semibold uppercase tracking-wide whitespace-nowrap cursor-pointer"
                          title="Awaiting Assistant CID Chief for Academic Affairs approval"
                          @click="removeAssessmentRow(cat.id, rIdx)">
                          <ClockIcon class="h-3 w-3" /> Deletion Requested
                        </span>
                        <AppIconButton v-else-if="row._db_id && row.activity_date"
                          label="Request deletion (already plotted / announced to students)" variant="danger" size="sm"
                          @click="removeAssessmentRow(cat.id, rIdx)">
                          <ExclamationTriangleIcon class="h-4 w-4" />
                        </AppIconButton>
                        <AppIconButton v-else label="Remove this assessment row" variant="danger" size="sm"
                          @click="removeAssessmentRow(cat.id, rIdx)">
                          <XMarkIcon class="h-4 w-4" />
                        </AppIconButton>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <!-- Add row button per category -->
              <div v-if="!isLocked && !isReadOnly && canEditCategory(cat)" class="mt-1.5">
                <button @click="addAssessmentRow(cat.id)"
                  class="inline-flex items-center gap-1.5 text-indigo-600 hover:text-indigo-700 text-xs font-medium">
                  <PlusIcon class="h-3.5 w-3.5" /> Add {{ cat.code }} Row
                </button>
              </div>
              <p v-else-if="!canEditCategory(cat)" class="text-[11px] text-slate-400 mt-1.5 italic">
                Read-only — this subject is taught by a different teacher on this shared record.
              </p>
            </div>
          </div>

          <!-- Save setup button -->
          <div class="mt-5 flex justify-end">
            <AppButton v-if="!isLocked && !isReadOnly" :loading="savingSetup" @click="saveSetup">
              {{ savingSetup ? 'Saving…' : 'Save Setup' }}
            </AppButton>
          </div>
        </div>

        <!-- ── Scores & Grades sub-tab ───────────────────────────────────── -->
        <div v-if="activeSubTab === 'scores'" class="p-5">
          <ScoreGrid
            :class-record-id="classRecord.id"
            :quarter-number="activeQuarter"
            :quarter-data="currentQuarterData"
            :grading-option="currentGradingOption"
            :stanine-lookup="stanineLookup"
            :previous-grades="{}"
            :is-locked="isLocked || isReadOnly"
            :subject-type="classRecord.subject?.subject_type ?? null"
            :section-id="classRecord.section_id ?? null"
            :siblings="props.siblings"
            :owned-subject-ids="ownedSubjectIds"
            :is-admin="isAdmin"
            :is-compliance-mode="currentGradingOption?.grading_mode === 'compliance'"
            :compliance-pass-threshold="Number(currentGradingOption?.compliance_pass_threshold ?? 0.75)"
            :compliance-pass-label="currentGradingOption?.compliance_pass_label ?? 'Completed'"
            :compliance-fail-label="currentGradingOption?.compliance_fail_label ?? 'Not Completed'"
            @reload="router.reload({ only: ['classRecord'] })"
          />
        </div>

        <!-- ── Attendance sub-tab ────────────────────────────────────────── -->
        <div v-if="activeSubTab === 'attendance'" class="p-5">
          <AttendanceGrid
            :class-record-id="classRecord.id"
            :quarter-number="activeQuarter"
            :quarter-data="currentQuarterData"
            :is-locked="isLocked || isReadOnly"
            :shared-subjects="sharedSubjects"
            :owned-subject-ids="ownedSubjectIds"
            :is-admin="isAdmin"
          />
        </div>

        <!-- ── ILA sub-tab ───────────────────────────────────────────────── -->
        <div v-if="activeSubTab === 'ila'" class="p-5">
          <IlaGrid
            :class-record-id="classRecord.id"
            :quarter-number="activeQuarter"
            :quarter-data="currentQuarterData"
            :is-locked="isLocked || isReadOnly"
            :leaf-categories="currentLeafCategories"
            @reload="router.reload({ only: ['classRecord'] })"
          />
        </div>

        </AppTabs>
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
            <div class="rounded-xl border border-warning-100 bg-warning-50 px-4 py-3 text-sm text-warning-700 w-full max-w-lg text-center">
              {{ finalGradesError }}
            </div>
            <AppButton variant="secondary" size="sm" @click="loadFinalGrades">Retry</AppButton>
          </div>

          <!-- Table -->
          <template v-else-if="finalGrades.length">
            <p class="text-xs text-slate-500 mb-4">
              <template v-if="finalGradesMode === 'compliance'">
                School-year remark is computed from Q1–Q4 compliance percentages per the grading option's
                configured rule ({{ finalGradesSyRuleLabel }}).
              </template>
              <template v-else>
                Final annual grade = simple average of Q1–Q4 grade equivalents, rounded to 3 decimal places.
              </template>
            </p>
            <div class="overflow-x-auto rounded-xl border border-slate-100">
              <table class="min-w-full text-sm">
                <thead class="bg-slate-50/80">
                  <tr>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 w-10">#</th>
                    <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-500">Student</th>
                    <template v-if="finalGradesMode === 'compliance'">
                      <th class="px-3 py-2.5 text-center text-xs font-semibold text-slate-500 w-20">Q1 %</th>
                      <th class="px-3 py-2.5 text-center text-xs font-semibold text-slate-500 w-20">Q2 %</th>
                      <th class="px-3 py-2.5 text-center text-xs font-semibold text-slate-500 w-20">Q3 %</th>
                      <th class="px-3 py-2.5 text-center text-xs font-semibold text-slate-500 w-20">Q4 %</th>
                      <th class="px-3 py-2.5 text-center text-xs font-semibold text-slate-500 w-24">Average %</th>
                      <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500">Remark</th>
                    </template>
                    <template v-else>
                      <th class="px-3 py-2.5 text-center text-xs font-semibold text-slate-500 w-16">Q1 GE</th>
                      <th class="px-3 py-2.5 text-center text-xs font-semibold text-slate-500 w-16">Q2 GE</th>
                      <th class="px-3 py-2.5 text-center text-xs font-semibold text-slate-500 w-16">Q3 GE</th>
                      <th class="px-3 py-2.5 text-center text-xs font-semibold text-slate-500 w-16">Q4 GE</th>
                      <th class="px-3 py-2.5 text-center text-xs font-semibold text-slate-500 w-20">Final GE</th>
                      <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500">Rating</th>
                    </template>
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
                    <template v-if="finalGradesMode === 'compliance'">
                      <td class="px-3 py-2.5 text-center font-mono text-slate-600">{{ fmtCompliancePct(student.q1Compliance) }}</td>
                      <td class="px-3 py-2.5 text-center font-mono text-slate-600">{{ fmtCompliancePct(student.q2Compliance) }}</td>
                      <td class="px-3 py-2.5 text-center font-mono text-slate-600">{{ fmtCompliancePct(student.q3Compliance) }}</td>
                      <td class="px-3 py-2.5 text-center font-mono text-slate-600">{{ fmtCompliancePct(student.q4Compliance) }}</td>
                      <td class="px-3 py-2.5 text-center font-mono font-bold text-slate-800">{{ fmtCompliancePct(student.averageCompliance) }}</td>
                      <td class="px-3 py-2.5 text-sm font-medium" :class="student.remark === complianceSyPassLabel ? 'text-emerald-600' : 'text-red-600'">
                        {{ student.remark }}
                      </td>
                    </template>
                    <template v-else>
                      <td class="px-3 py-2.5 text-center font-mono text-slate-600">{{ Number(student.q1GE).toFixed(3) }}</td>
                      <td class="px-3 py-2.5 text-center font-mono text-slate-600">{{ Number(student.q2GE).toFixed(3) }}</td>
                      <td class="px-3 py-2.5 text-center font-mono text-slate-600">{{ Number(student.q3GE).toFixed(3) }}</td>
                      <td class="px-3 py-2.5 text-center font-mono text-slate-600">{{ Number(student.q4GE).toFixed(3) }}</td>
                      <td class="px-3 py-2.5 text-center font-mono font-bold text-slate-800">{{ Number(student.finalGE).toFixed(3) }}</td>
                      <td class="px-3 py-2.5 text-sm" :class="adjectivalColor(student.adjectival)">{{ student.adjectival }}</td>
                    </template>
                  </tr>
                </tbody>
              </table>
            </div>
          </template>

          <!-- Empty fallback -->
          <div v-else class="flex flex-col items-center justify-center py-12 text-slate-400 text-sm gap-2">
            <p>No student data found. Ensure all 4 quarters have scores entered.</p>
            <AppButton variant="secondary" size="sm" @click="loadFinalGrades">Retry</AppButton>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>

  <!-- Copy-from-record modal -->
  <AppModal :show="showCopyFromRecordModal" title="Copy from Another Section"
    subtitle="Select a class record with the same subject to copy its assessment structure."
    size="md" @close="showCopyFromRecordModal = false">
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
    <template #footer>
      <AppButton variant="secondary" @click="showCopyFromRecordModal = false">Cancel</AppButton>
      <AppButton :loading="copyingFromRecord" :disabled="!copyFromRecordId || copyingFromRecord" @click="copyFromRecord">
        {{ copyingFromRecord ? 'Copying…' : 'Copy Assessments' }}
      </AppButton>
    </template>
  </AppModal>

  <!-- Request deletion modal -->
  <AppModal :show="showRequestDeletionModal" title="Request Assessment Deletion"
    subtitle="This assessment is already plotted and announced to students — it can only be removed once the Assistant CID Chief for Academic Affairs approves. State your reason below."
    size="md" @close="showRequestDeletionModal = false">
    <div class="space-y-3">
      <p v-if="requestDeletionRow" class="text-sm text-slate-600">
        <strong>{{ requestDeletionRow.title || 'Untitled assessment' }}</strong>
        — {{ requestDeletionRow.activity_date }}
      </p>
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Reason for Deletion <span class="text-danger-500">*</span></label>
        <textarea v-model="requestDeletionReason" rows="3" maxlength="1000"
          placeholder="Why does this assessment need to be removed?"
          class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
      </div>
    </div>
    <template #footer>
      <AppButton variant="secondary" @click="showRequestDeletionModal = false">Cancel</AppButton>
      <AppButton :loading="submittingDeletionRequest" :disabled="!requestDeletionReason.trim() || submittingDeletionRequest" @click="submitDeletionRequest">
        {{ submittingDeletionRequest ? 'Submitting…' : 'Submit Request' }}
      </AppButton>
    </template>
  </AppModal>

  <!-- Apply-to-sections modal -->
  <AppModal :show="showApplyToSectionsModal" title="Apply This Setup to Other Sections"
    subtitle="Pushes this quarter's assessments (titles, dates, weights) to the sections you pick — each one is checked against its own grading option and WAT limits; anything ineligible is skipped and reported below."
    size="md" @close="showApplyToSectionsModal = false">
    <div class="space-y-3">
      <div v-if="applyEligibleRecords.length" class="space-y-1.5 max-h-64 overflow-y-auto">
        <label v-for="r in applyEligibleRecords" :key="r.id"
          class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm hover:bg-slate-50 cursor-pointer">
          <input type="checkbox" :value="r.id" v-model="applyToSectionIds"
            class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
          <span>{{ r.year_level_section }} <span class="text-slate-400">({{ r.school_year }})</span></span>
        </label>
      </div>
      <p v-else class="text-sm text-slate-400">No other current-school-year sections found for this subject.</p>
      <p v-if="applyPastYearRecords.length" class="text-xs text-slate-400">
        {{ applyPastYearRecords.length }} section(s) from past school years aren't shown — they're read-only.
      </p>
    </div>
    <template #footer>
      <AppButton variant="secondary" @click="showApplyToSectionsModal = false">Cancel</AppButton>
      <AppButton :loading="applyingToSections" :disabled="!applyToSectionIds.length || applyingToSections" @click="applyToSections">
        {{ applyingToSections ? 'Applying…' : `Apply to ${applyToSectionIds.length || ''} Section(s)` }}
      </AppButton>
    </template>
  </AppModal>

  <!-- Change grading option modal -->
  <AppModal :show="showChangeGradingOptionModal" title="Change Grading Option"
    subtitle="Only available before any assessments or scores are entered for this record."
    size="md" @close="showChangeGradingOptionModal = false">
    <div class="space-y-3">
      <select v-model="changeGradingOptionId"
        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
        <option v-for="opt in gradingOptions" :key="opt.id" :value="opt.id">
          {{ opt.name }}{{ opt.owner_designation ? ` (${opt.owner_designation.name.replace(/^Academic Unit Head\s*-\s*/i, '')})` : '' }}
        </option>
      </select>
      <GradingOptionDetails :option="changeGradingOptionSelected" />
      <p v-if="changeGradingOptionError" class="text-xs text-red-500">{{ changeGradingOptionError }}</p>
    </div>
    <template #footer>
      <AppButton variant="secondary" @click="showChangeGradingOptionModal = false">Cancel</AppButton>
      <AppButton :loading="changingGradingOption"
        :disabled="!changeGradingOptionId || changeGradingOptionId === classRecord.grading_option_id"
        @click="saveGradingOptionChange">
        {{ changingGradingOption ? 'Saving…' : 'Save Change' }}
      </AppButton>
    </template>
  </AppModal>

  <!-- Per-quarter grading option modal -->
  <AppModal :show="showQuarterOptionModal" :title="`Change Q${activeQuarter} Grading Option`"
    subtitle="Only options applicable to this quarter are shown. Available before this quarter has any assessments."
    size="md" @close="showQuarterOptionModal = false">
    <div class="space-y-3">
      <select v-model="quarterOptionId"
        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
        <option v-for="opt in applicableGradingOptions" :key="opt.id" :value="opt.id">
          {{ opt.name }}{{ opt.owner_designation ? ` (${opt.owner_designation.name.replace(/^Academic Unit Head\s*-\s*/i, '')})` : '' }}
        </option>
      </select>
      <GradingOptionDetails :option="quarterOptionSelected" />
      <label v-if="activeQuarter < 4" class="flex items-start gap-2 text-xs text-slate-600">
        <input type="checkbox" v-model="applyToRemainingQuarters" class="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
        <span>Also apply to Q{{ activeQuarter + 1 }}–Q4, skipping any that already have assessments, are locked, or don't accept this option.</span>
      </label>
      <p v-if="quarterOptionError" class="text-xs text-red-500">{{ quarterOptionError }}</p>
    </div>
    <template #footer>
      <AppButton variant="secondary" @click="showQuarterOptionModal = false">Cancel</AppButton>
      <AppButton :loading="savingQuarterOption" :disabled="!quarterOptionId || savingQuarterOption" @click="saveQuarterOption">
        {{ savingQuarterOption ? 'Saving…' : 'Save Change' }}
      </AppButton>
    </template>
  </AppModal>

  <!-- Section assessment calendar -->
  <SectionAssessmentCalendar
    :show="showSectionCalendar"
    :section-label="classRecord.year_level_section"
    :days="calendarDaysWithPending"
    :editable="pendingAssessmentCategories.length > 0"
    :pending-rows="pendingAssessmentCategories"
    :same-subject-records="applyEligibleRecords"
    :disabled-dates="calendarDateFeasibility"
    @close="showSectionCalendar = false"
    @schedule="onCalendarSchedule"
    @clear-pending="onCalendarClearPending"
  />
</template>
