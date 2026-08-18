<script setup>
import { ref, computed, reactive, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import EvaluationSummaryBlock from '@/Components/AMS/EvaluationSummaryBlock.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppModal from '@/Components/AppModal.vue'
import EmptyState from '@/Components/EmptyState.vue'
import PaginationControl from '@/Components/PaginationControl.vue'
import { confirmAction, confirmDelete as confirmDeleteDialog } from '@/Composables/useConfirm.js'
import Swal from 'sweetalert2'
import {
  ArrowLeftIcon,
  PencilSquareIcon,
  TrashIcon,
  CalendarDaysIcon,
  ClockIcon,
  MapPinIcon,
  UserIcon,
  PaperClipIcon,
  UsersIcon,
  PlusIcon,
  XMarkIcon,
  CheckCircleIcon,
  ChevronDownIcon,
  ChevronUpIcon,
  DocumentArrowDownIcon,
  MagnifyingGlassIcon,
  PhotoIcon,
  LinkIcon,
  ClipboardDocumentCheckIcon,
  ChartBarIcon,
  EnvelopeIcon,
  AcademicCapIcon,
  BuildingOffice2Icon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  activity:                  Object,
  participants:               Array,
  employees:                  Array,
  sections:                   Array,
  canEdit:                    Boolean,
  canManage:                  Boolean,
  canToggleEvaluationPeriod:  Boolean,
  evaluations:                Object,
  walkinEvalQr:                Object,
  quizzes:                    { type: Array, default: () => [] },
})

const page       = usePage()
const csrfToken  = computed(() => document.querySelector('meta[name="csrf-token"]')?.content ?? '')
const activeTab  = ref('details')

const isTws = computed(() => props.activity.activity_type === 'training_workshop_seminar')

function hostQuiz(quiz) {
  if (quiz.question_count === 0) {
    alert('Add at least one question before hosting a session.')
    return
  }
  router.post(route('quiz.sessions.store', quiz.id))
}

function formatMealDay(dateStr) {
  return new Date(dateStr + 'T00:00:00').toLocaleDateString('en-PH', {
    weekday: 'short', month: 'short', day: 'numeric',
  })
}

const generatingCerts  = ref(false)
const sendingEvalLinks = ref(false)

async function generateCertificates() {
  const confirmed = await confirmAction({
    title: 'Generate Certificates?',
    text: 'Certificates will be generated for all present and evaluated participants.',
    confirmText: 'Yes, generate',
  })
  if (!confirmed) return

  generatingCerts.value = true
  Swal.fire({
    title: 'Generating Certificates…',
    text: 'Please wait while certificates are being generated.',
    allowOutsideClick: false,
    allowEscapeKey: false,
    didOpen: () => Swal.showLoading(),
  })

  router.post(
    route('ams.activities.certificates.generate', props.activity.id),
    {},
    {
      preserveScroll: true,
      onFinish: () => {
        generatingCerts.value = false
        Swal.close()
      },
    }
  )
}

async function sendEvaluationLinks() {
  const confirmed = await confirmAction({
    title: 'Send Evaluation Links?',
    text: 'An email with a unique evaluation link will be sent to all present participants.',
    confirmText: 'Yes, send',
  })
  if (!confirmed) return

  sendingEvalLinks.value = true
  Swal.fire({
    title: 'Sending Evaluation Links…',
    text: 'Please wait while emails are being sent.',
    allowOutsideClick: false,
    allowEscapeKey: false,
    didOpen: () => Swal.showLoading(),
  })

  router.post(
    route('ams.activities.participants.send-evaluation-links', props.activity.id),
    {},
    {
      preserveScroll: true,
      onFinish: () => {
        sendingEvalLinks.value = false
        Swal.close()
      },
    }
  )
}

const togglingPeriod = ref(false)

async function toggleEvaluationPeriod() {
  const closing = props.activity.evaluation_open
  const confirmed = await confirmAction({
    icon: 'warning',
    title: closing ? 'Close Evaluation Period?' : 'Reopen Evaluation Period?',
    text: closing
      ? 'Participants and walk-ins who have not yet evaluated will no longer be able to evaluate or receive a certificate. This does not affect anyone already evaluated/certified.'
      : 'Participants and walk-ins will be able to submit evaluations again.',
    confirmText: closing ? 'Yes, close it' : 'Yes, reopen it',
  })
  if (!confirmed) return

  togglingPeriod.value = true
  router.post(
    route('ams.activities.evaluation-period.toggle', props.activity.id),
    { open: !closing },
    { preserveScroll: true, onFinish: () => { togglingPeriod.value = false } }
  )
}

function formatDateTime(dt) {
  if (!dt) return '—'
  return new Date(dt.replace(' ', 'T')).toLocaleString('en-PH', {
    year: 'numeric', month: 'long', day: 'numeric', hour: 'numeric', minute: '2-digit',
  })
}

// ── Helpers ────────────────────────────────────────────────────────────────

function formatDate(d) {
  if (!d) return '—'
  return new Date(d + 'T00:00:00').toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })
}
function formatTime(t) {
  if (!t) return null
  const [h, m] = t.split(':')
  const date = new Date(); date.setHours(+h, +m)
  return date.toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit', hour12: true })
}
async function confirmDelete() {
  if (await confirmDeleteDialog(`"${props.activity.title}" will be permanently deleted. This cannot be undone.`)) {
    router.delete(route('ams.activities.destroy', props.activity.id))
  }
}

// ── Badge color mapping ───────────────────────────────────────────────────
function typeColor(isTwsType) { return isTwsType ? 'indigo' : 'amber' }
function attendanceColor(attended) { return attended === 'yes' ? 'green' : 'red' }
function evaluatedColor(evaluated) { return evaluated ? 'green' : 'slate' }

// ── Add Participants (multi-select) ────────────────────────────────────────

const showAddPanel      = ref(false)
const addTab            = ref('employees')  // 'employees' | 'sections'
const empSearch         = ref('')
const secSearch         = ref('')
const selectedEmployees = ref(new Set())
const selectedSections  = ref(new Set())
const addSubmitting     = ref(false)

const enrolledEmployeeIds = computed(() => props.participants.filter(p => p.type === 'employee').map(p => p.participant_id))
const enrolledSectionIds  = computed(() => props.participants.filter(p => p.type === 'section').map(p => p.participant_id))

const filteredEmployees = computed(() => {
  const q = empSearch.value.toLowerCase()
  return props.employees.filter(e =>
    !enrolledEmployeeIds.value.includes(e.id) &&
    e.name.toLowerCase().includes(q)
  )
})
const filteredSections = computed(() => {
  const q = secSearch.value.toLowerCase()
  return props.sections.filter(s =>
    !enrolledSectionIds.value.includes(s.id) &&
    s.label.toLowerCase().includes(q)
  )
})

const selectedCount = computed(() => selectedEmployees.value.size + selectedSections.value.size)

function toggleEmployee(id) {
  selectedEmployees.value.has(id) ? selectedEmployees.value.delete(id) : selectedEmployees.value.add(id)
  selectedEmployees.value = new Set(selectedEmployees.value)
}
function toggleSection(id) {
  selectedSections.value.has(id) ? selectedSections.value.delete(id) : selectedSections.value.add(id)
  selectedSections.value = new Set(selectedSections.value)
}
function selectAllEmployees() { filteredEmployees.value.forEach(e => selectedEmployees.value.add(e.id)); selectedEmployees.value = new Set(selectedEmployees.value) }
function clearEmployees()     { selectedEmployees.value = new Set() }
function selectAllSections()  { filteredSections.value.forEach(s => selectedSections.value.add(s.id)); selectedSections.value = new Set(selectedSections.value) }
function clearSections()      { selectedSections.value = new Set() }

function saveParticipants() {
  if (selectedCount.value === 0) return
  addSubmitting.value = true
  router.post(
    route('ams.activities.participants.add', props.activity.id),
    {
      employee_ids: [...selectedEmployees.value],
      section_ids:  [...selectedSections.value],
    },
    {
      preserveScroll: true,
      onSuccess: () => {
        showAddPanel.value    = false
        selectedEmployees.value = new Set()
        selectedSections.value  = new Set()
        addSubmitting.value   = false
      },
      onError: () => { addSubmitting.value = false },
    }
  )
}

// ── Participants datatable ─────────────────────────────────────────────────

const PT_PER_PAGE    = 15
const ptSearch       = ref('')
const ptStatusFilter = ref('all')   // 'all' | 'yes' | 'no'
const ptEvalFilter   = ref('all')   // 'all' | 'yes' | 'no'
const ptPage         = ref(1)
const copiedId       = ref(null)

const empSection = computed(() => props.participants.filter(p => p.type === 'employee'))
const secSection = computed(() => props.participants.filter(p => p.type === 'section'))

const filteredParticipants = computed(() => {
  const q   = ptSearch.value.toLowerCase()
  const st  = ptStatusFilter.value
  const ev  = ptEvalFilter.value
  return props.participants.filter(p => {
    if (q && !p.label.toLowerCase().includes(q)) return false
    // status filter only applies to employees; sections are excluded when filtering
    if (st !== 'all') {
      if (p.type === 'section') return false
      if (p.attended !== st) return false
    }
    // eval filter: sections are excluded when filtering by eval status
    if (ev !== 'all') {
      if (p.type === 'section') return false
      const evalBool = ev === 'yes'
      if (p.evaluated !== evalBool) return false
    }
    return true
  })
})

const ptTotalPages = computed(() => Math.max(1, Math.ceil(filteredParticipants.value.length / PT_PER_PAGE)))

const paginatedParticipants = computed(() => {
  const start = (ptPage.value - 1) * PT_PER_PAGE
  return filteredParticipants.value.slice(start, start + PT_PER_PAGE)
})

function ptSetPage(n) { ptPage.value = Math.max(1, Math.min(n, ptTotalPages.value)) }

// Reset page when filters change
watch([ptSearch, ptStatusFilter, ptEvalFilter], () => { ptPage.value = 1 })

// Copy evaluation link to clipboard
function copyEvalLink(p) {
  navigator.clipboard.writeText(p.evaluate_url).then(() => {
    copiedId.value = p.id
    setTimeout(() => { copiedId.value = null }, 2000)
  })
}

// Local editable state for employee attendance rows
const empAttendance = reactive({})
props.participants.filter(p => p.type === 'employee').forEach(p => {
  empAttendance[p.id] = {
    attended: p.attended,
    hours: p.hours_attended ?? '0.00',
    daily: { ...(p.daily ?? {}) },
  }
})

function initEmpRow(p) {
  if (!empAttendance[p.id]) {
    empAttendance[p.id] = { attended: p.attended, hours: p.hours_attended ?? '0.00', daily: { ...(p.daily ?? {}) } }
  }
}

function getEmpDay(p, date) {
  const row = empAttendance[p.id]
  if (!row.daily[date]) row.daily[date] = { attended: 'no', hours: 0 }
  return row.daily[date]
}

const expandedEmpDaily = ref(null)
function toggleEmpDailyExpand(p) {
  expandedEmpDaily.value = expandedEmpDaily.value === p.id ? null : p.id
}

async function toggleAttendance(p) {
  const row = empAttendance[p.id]
  if (!row) return
  if (row.attended === 'yes') {
    const confirmed = await confirmAction({
      icon: 'warning',
      title: 'Mark as Absent?',
      text: `This will mark "${p.label}" as Absent. Their certificate will not be generated.`,
      confirmText: 'Yes, mark absent',
    })
    if (!confirmed) return
  }
  row.attended = row.attended === 'yes' ? 'no' : 'yes'
}

function saveEmpAttendance(p) {
  const row = empAttendance[p.id]
  const payload = { attended: row.attended, hours_attended: row.hours }
  if (props.activity.is_multi_day) {
    payload.daily = props.activity.attendance_days.map(date => ({
      date,
      attended: row.daily[date]?.attended ?? 'no',
      hours_attended: row.daily[date]?.hours ?? 0,
    }))
  }
  router.post(
    route('ams.activities.participants.save-attendance', [props.activity.id, p.id]),
    payload,
    { preserveScroll: true }
  )
}

// ── Section Student Attendance ─────────────────────────────────────────────

const expandedSection   = ref(null)
const sectionStudents   = ref({})
const loadingStudents   = ref({})
const secAttendance     = reactive({})  // keyed by participantRowId → { students: [] }

async function toggleSectionExpand(p) {
  if (expandedSection.value === p.id) { expandedSection.value = null; return }
  expandedSection.value = p.id
  if (sectionStudents.value[p.id]) return

  loadingStudents.value[p.id] = true
  try {
    const res  = await fetch(
      route('ams.activities.participants.students', [props.activity.id, p.id]),
      { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }
    )
    const list = await res.json()
    sectionStudents.value[p.id] = list
    secAttendance[p.id] = list.map(s => ({ ...s, daily: { ...(s.daily ?? {}) } }))
  } finally {
    loadingStudents.value[p.id] = false
  }
}

function markAllSectionStudents(pid, attended) {
  if (!secAttendance[pid]) return
  secAttendance[pid].forEach(s => { s.attended = attended })
}

function getStudentDay(student, date) {
  if (!student.daily[date]) student.daily[date] = { attended: 'no', hours: 0 }
  return student.daily[date]
}

const expandedStudentDaily = ref(null)
function toggleStudentDailyExpand(studentAttendanceId) {
  expandedStudentDaily.value = expandedStudentDaily.value === studentAttendanceId ? null : studentAttendanceId
}

function saveSectionAttendance(p) {
  const rows = secAttendance[p.id]
  if (!rows) return
  router.post(
    route('ams.activities.participants.save-section-attendance', [props.activity.id, p.id]),
    {
      students: rows.map(s => ({
        attendance_id:  s.attendance_id,
        attended:       s.attended,
        hours_attended: s.hours_attended,
        student_id:     s.id,
        ...(props.activity.is_multi_day ? {
          daily: props.activity.attendance_days.map(date => ({
            date,
            attended: s.daily[date]?.attended ?? 'no',
            hours_attended: s.daily[date]?.hours ?? 0,
          })),
        } : {}),
      }))
    },
    { preserveScroll: true }
  )
}

// ── Remove participant ─────────────────────────────────────────────────────

async function removeParticipant(p) {
  const confirmed = await confirmAction({
    icon: 'warning',
    title: 'Remove Participant?',
    text: `"${p.label}" will be removed from this activity.`,
    confirmText: 'Remove',
  })
  if (!confirmed) return
  router.delete(
    route('ams.activities.participants.remove', [props.activity.id, p.id]),
    { preserveScroll: true }
  )
}

// ── Co-Proponents ──────────────────────────────────────────────────────────

const showAddCoPro       = ref(false)
const coproEmployeeId    = ref('')
const alreadyCoproIds    = computed(() => props.activity.co_proponents.map(c => c.employee_id))
const availableForCoPro  = computed(() => props.employees.filter(e => !alreadyCoproIds.value.includes(e.id)))

function addCoPro() {
  if (!coproEmployeeId.value) return
  router.post(
    route('ams.activities.co-proponents.add', props.activity.id),
    { employee_id: coproEmployeeId.value },
    { preserveScroll: true, onSuccess: () => { showAddCoPro.value = false; coproEmployeeId.value = '' } }
  )
}
async function removeCoPro(cp) {
  const confirmed = await confirmAction({
    icon: 'warning',
    title: 'Remove Co-Proponent?',
    text: `${cp.name} will be removed as co-proponent.`,
    confirmText: 'Remove',
  })
  if (!confirmed) return
  router.delete(route('ams.activities.co-proponents.remove', [props.activity.id, cp.id]), { preserveScroll: true })
}
</script>

<template>
  <Head :title="activity.title" />
  <AdminLayout :title="activity.title">

    <!-- Back + Actions -->
    <div class="flex items-center justify-between mb-6">
      <a :href="route('ams.activities.index')"
         class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-700">
        <ArrowLeftIcon class="w-4 h-4" /> All Activities
      </a>
      <div class="flex items-center gap-2">
        <template v-if="canEdit">
          <AppButton as="a" :href="route('ams.activities.edit', activity.id)" variant="warning" size="sm">
            <PencilSquareIcon class="w-4 h-4" /> Edit
          </AppButton>
          <AppButton variant="danger" size="sm" @click="confirmDelete">
            <TrashIcon class="w-4 h-4" /> Delete
          </AppButton>
        </template>
        <span v-else class="text-xs text-slate-400 italic">View only</span>
      </div>
    </div>

    <!-- Tabs -->
    <div class="flex gap-1 mb-5 border-b border-slate-200">
      <button
        v-for="tab in [
          { key: 'details',      label: 'Details' },
          { key: 'participants', label: 'Participants', badge: participants.length },
          { key: 'coproponents', label: 'Co-Proponents', badge: activity.co_proponents.length },
          { key: 'evaluations',  label: 'Evaluations', badge: evaluations?.count ?? 0 },
          { key: 'quizzes',      label: 'Quizzes', badge: quizzes.length },
        ]"
        :key="tab.key"
        @click="activeTab = tab.key"
        class="px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors"
        :class="activeTab === tab.key ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-700'"
      >
        {{ tab.label }}
        <span v-if="tab.badge" class="ml-1.5 text-xs bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded-full">{{ tab.badge }}</span>
      </button>
    </div>

    <!-- ── DETAILS TAB ─────────────────────────────────────────────────────── -->
    <div v-if="activeTab === 'details'">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="lg:col-span-2">
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">

          <!-- Banner -->
          <div class="bg-slate-100 border-b border-slate-200" style="height: 200px;">
            <img v-if="activity.banner"
                 :src="activity.banner"
                 :alt="activity.title"
                 class="w-full h-full object-contain" />
            <div v-else class="w-full h-full flex flex-col items-center justify-center gap-2 text-slate-400">
              <PhotoIcon class="w-12 h-12" />
              <span class="text-xs">No banner uploaded</span>
            </div>
          </div>

          <div class="p-5">
          <div class="flex items-center gap-2 mb-2">
            <AppBadge :color="typeColor(isTws)">
              <component :is="isTws ? AcademicCapIcon : BuildingOffice2Icon" class="w-3.5 h-3.5 mr-1" />
              {{ isTws ? 'Training / Workshop / Seminar' : 'In-house Activity' }}
            </AppBadge>
          </div>
          <h1 class="text-xl font-bold text-slate-800 mb-4">{{ activity.title }}</h1>
          <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
            <div class="flex items-start gap-2">
              <CalendarDaysIcon class="w-4 h-4 text-slate-400 mt-0.5 shrink-0" />
              <div>
                <dt class="text-xs text-slate-400 uppercase tracking-wide">Dates</dt>
                <dd class="font-medium text-slate-700">
                  {{ formatDate(activity.start_date) }}
                  <span v-if="activity.end_date && activity.end_date !== activity.start_date"> – {{ formatDate(activity.end_date) }}</span>
                </dd>
              </div>
            </div>
            <div v-if="activity.start_time || activity.end_time" class="flex items-start gap-2">
              <ClockIcon class="w-4 h-4 text-slate-400 mt-0.5 shrink-0" />
              <div>
                <dt class="text-xs text-slate-400 uppercase tracking-wide">Time</dt>
                <dd class="font-medium text-slate-700">
                  <span v-if="activity.start_time">{{ formatTime(activity.start_time) }}</span>
                  <span v-if="activity.start_time && activity.end_time"> – </span>
                  <span v-if="activity.end_time">{{ formatTime(activity.end_time) }}</span>
                </dd>
              </div>
            </div>
            <div v-if="activity.total_hours" class="flex items-start gap-2">
              <ClockIcon class="w-4 h-4 text-slate-400 mt-0.5 shrink-0" />
              <div>
                <dt class="text-xs text-slate-400 uppercase tracking-wide">Total Hours</dt>
                <dd class="font-medium text-slate-700">{{ activity.total_hours }}</dd>
              </div>
            </div>
            <div v-if="activity.venue" class="flex items-start gap-2">
              <MapPinIcon class="w-4 h-4 text-slate-400 mt-0.5 shrink-0" />
              <div>
                <dt class="text-xs text-slate-400 uppercase tracking-wide">Venue</dt>
                <dd class="font-medium text-slate-700">{{ activity.venue }}</dd>
              </div>
            </div>
            <div v-if="activity.resource_person" class="flex items-start gap-2">
              <UserIcon class="w-4 h-4 text-slate-400 mt-0.5 shrink-0" />
              <div>
                <dt class="text-xs text-slate-400 uppercase tracking-wide">Resource Person</dt>
                <dd class="font-medium text-slate-700">{{ activity.resource_person }}</dd>
              </div>
            </div>
            <div class="flex items-start gap-2">
              <UserIcon class="w-4 h-4 text-slate-400 mt-0.5 shrink-0" />
              <div>
                <dt class="text-xs text-slate-400 uppercase tracking-wide">Proponent</dt>
                <dd class="font-medium text-slate-700">{{ activity.creator?.name ?? '—' }}</dd>
              </div>
            </div>
          </dl>

          <!-- What to Bring -->
          <div v-if="activity.what_to_bring" class="mt-4 pt-4 border-t border-slate-100">
            <p class="text-xs text-slate-400 uppercase tracking-wide mb-1">What to Bring</p>
            <p class="text-sm text-slate-700 whitespace-pre-line">{{ activity.what_to_bring }}</p>
          </div>

          <!-- Speakers (Training/Workshop/Seminar only) -->
          <div v-if="isTws && activity.speakers.length" class="mt-4 pt-4 border-t border-slate-100">
            <p class="text-xs text-slate-400 uppercase tracking-wide mb-2">Speakers</p>
            <div class="space-y-2">
              <div v-for="s in activity.speakers" :key="s.id" class="flex items-start gap-2 text-sm">
                <UserIcon class="w-4 h-4 text-slate-400 mt-0.5 shrink-0" />
                <div>
                  <span class="font-medium text-slate-700">{{ s.name }}</span>
                  <span v-if="s.designation" class="text-slate-400"> — {{ s.designation }}</span>
                  <p v-if="s.topic" class="text-xs text-slate-500">{{ s.topic }}</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Meal Plan -->
          <div v-if="activity.meal_plans.length" class="mt-4 pt-4 border-t border-slate-100">
            <p class="text-xs text-slate-400 uppercase tracking-wide mb-2">Free Meals &amp; Snacks</p>
            <div class="overflow-x-auto">
              <table class="min-w-full text-xs">
                <thead>
                  <tr class="text-left text-slate-400 uppercase tracking-wide">
                    <th class="py-1 pr-4">Day</th>
                    <th class="py-1 px-2 text-center">AM Snacks</th>
                    <th class="py-1 px-2 text-center">Lunch</th>
                    <th class="py-1 px-2 text-center">PM Snacks</th>
                    <th class="py-1 px-2 text-center">Dinner</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                  <tr v-for="mp in activity.meal_plans" :key="mp.id">
                    <td class="py-1.5 pr-4 text-slate-700">{{ formatMealDay(mp.date) }}</td>
                    <td class="py-1.5 px-2 text-center">
                      <CheckCircleIcon v-if="mp.am_snacks" class="w-4 h-4 text-green-500 inline" />
                      <span v-else class="text-slate-300">—</span>
                    </td>
                    <td class="py-1.5 px-2 text-center">
                      <CheckCircleIcon v-if="mp.lunch" class="w-4 h-4 text-green-500 inline" />
                      <span v-else class="text-slate-300">—</span>
                    </td>
                    <td class="py-1.5 px-2 text-center">
                      <CheckCircleIcon v-if="mp.pm_snacks" class="w-4 h-4 text-green-500 inline" />
                      <span v-else class="text-slate-300">—</span>
                    </td>
                    <td class="py-1.5 px-2 text-center">
                      <CheckCircleIcon v-if="mp.dinner" class="w-4 h-4 text-green-500 inline" />
                      <span v-else class="text-slate-300">—</span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          </div><!-- end p-5 -->
        </div>
      </div>

      <div>
        <div class="bg-white rounded-xl border border-slate-200 p-5">
          <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-3 flex items-center gap-2">
            <PaperClipIcon class="w-4 h-4" /> Attachments
          </h2>
          <div class="space-y-3 text-sm">
            <template v-for="[key, label] in [['banner','Banner / Poster'],['special_order','Special Order'],['activity_report','Activity Report'],['official_documentation','Official Documentation']]" :key="key">
              <div v-if="activity[key]">
                <p class="text-xs text-slate-400 uppercase tracking-wide mb-1">{{ label }}</p>
                <a :href="activity[key]" target="_blank" class="text-indigo-600 hover:underline flex items-center gap-1">
                  <PaperClipIcon class="w-3.5 h-3.5" /> View file
                </a>
              </div>
            </template>
            <p v-if="!activity.banner && !activity.special_order && !activity.activity_report && !activity.official_documentation"
               class="text-slate-400 italic">No attachments uploaded.</p>
          </div>
        </div>
      </div>
    </div><!-- end details grid -->
    </div><!-- end details tab -->

    <!-- ── PARTICIPANTS TAB ────────────────────────────────────────────────── -->
    <div v-if="activeTab === 'participants'" class="space-y-4">

      <!-- Toolbar -->
      <div class="flex items-center gap-3 flex-wrap">
        <AppButton v-if="canManage" variant="success" :loading="generatingCerts" :disabled="generatingCerts" @click="generateCertificates">
          <DocumentArrowDownIcon class="w-4 h-4" />
          {{ generatingCerts ? 'Generating…' : 'Generate All Certificates' }}
        </AppButton>
        <AppButton v-if="canManage" variant="secondary" :loading="sendingEvalLinks" :disabled="sendingEvalLinks" @click="sendEvaluationLinks">
          <EnvelopeIcon class="w-4 h-4" />
          {{ sendingEvalLinks ? 'Sending…' : 'Send Evaluation Links' }}
        </AppButton>
        <AppButton v-if="canManage" @click="showAddPanel = !showAddPanel">
          <PlusIcon class="w-4 h-4" />
          Add Participants
        </AppButton>
      </div>

      <!-- ── Multi-select add panel ─────────────────────────────────────── -->
      <AppModal :show="showAddPanel && canManage" title="Add Participants" size="lg" body-class="p-0" @close="showAddPanel = false">
        <div class="flex border-b border-slate-100">
          <button @click="addTab = 'employees'"
                  class="flex-1 py-2.5 text-sm font-medium transition-colors"
                  :class="addTab === 'employees' ? 'bg-indigo-50 text-indigo-700 border-b-2 border-indigo-600' : 'text-slate-500 hover:text-slate-700'">
            Employees
            <span v-if="selectedEmployees.size" class="ml-1 text-xs bg-indigo-100 text-indigo-700 px-1.5 py-0.5 rounded-full">{{ selectedEmployees.size }}</span>
          </button>
          <button @click="addTab = 'sections'"
                  class="flex-1 py-2.5 text-sm font-medium transition-colors"
                  :class="addTab === 'sections' ? 'bg-indigo-50 text-indigo-700 border-b-2 border-indigo-600' : 'text-slate-500 hover:text-slate-700'">
            Sections
            <span v-if="selectedSections.size" class="ml-1 text-xs bg-indigo-100 text-indigo-700 px-1.5 py-0.5 rounded-full">{{ selectedSections.size }}</span>
          </button>
        </div>

        <!-- Employees -->
        <div v-if="addTab === 'employees'" class="p-4">
          <div class="flex items-center gap-2 mb-3">
            <div class="relative flex-1">
              <MagnifyingGlassIcon class="absolute left-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
              <input v-model="empSearch" placeholder="Search employees…" class="pl-8 rounded-lg border border-slate-200 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
            </div>
            <button @click="selectAllEmployees" class="text-xs text-indigo-600 hover:underline whitespace-nowrap">Select all</button>
            <button @click="clearEmployees" class="text-xs text-slate-400 hover:underline">Clear</button>
          </div>
          <div class="max-h-60 overflow-y-auto divide-y divide-slate-50 border border-slate-100 rounded-lg">
            <label v-for="emp in filteredEmployees" :key="emp.id"
                   class="flex items-center gap-3 px-3 py-2 hover:bg-indigo-50 cursor-pointer">
              <input type="checkbox" :checked="selectedEmployees.has(emp.id)" @change="toggleEmployee(emp.id)"
                     class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
              <span class="text-sm text-slate-700">{{ emp.name }}</span>
            </label>
            <div v-if="filteredEmployees.length === 0" class="px-3 py-4 text-sm text-slate-400 text-center">No employees available.</div>
          </div>
        </div>

        <!-- Sections -->
        <div v-if="addTab === 'sections'" class="p-4">
          <div class="flex items-center gap-2 mb-3">
            <div class="relative flex-1">
              <MagnifyingGlassIcon class="absolute left-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
              <input v-model="secSearch" placeholder="Search sections…" class="pl-8 rounded-lg border border-slate-200 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
            </div>
            <button @click="selectAllSections" class="text-xs text-indigo-600 hover:underline whitespace-nowrap">Select all</button>
            <button @click="clearSections" class="text-xs text-slate-400 hover:underline">Clear</button>
          </div>
          <div class="max-h-60 overflow-y-auto divide-y divide-slate-50 border border-slate-100 rounded-lg">
            <label v-for="sec in filteredSections" :key="sec.id"
                   class="flex items-center gap-3 px-3 py-2 hover:bg-indigo-50 cursor-pointer">
              <input type="checkbox" :checked="selectedSections.has(sec.id)" @change="toggleSection(sec.id)"
                     class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
              <span class="text-sm text-slate-700">{{ sec.label }}</span>
            </label>
            <div v-if="filteredSections.length === 0" class="px-3 py-4 text-sm text-slate-400 text-center">No sections available.</div>
          </div>
        </div>

        <template #footer>
          <span class="text-sm text-slate-500 mr-auto">{{ selectedCount }} selected</span>
          <AppButton variant="secondary" @click="showAddPanel = false">Cancel</AppButton>
          <AppButton :loading="addSubmitting" :disabled="selectedCount === 0 || addSubmitting" @click="saveParticipants">
            {{ addSubmitting ? 'Saving…' : 'Save & Send Invitations' }}
          </AppButton>
        </template>
      </AppModal>

      <!-- ── Participants datatable ─────────────────────────────────────── -->
      <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">

        <!-- Search + Filter toolbar -->
        <div class="px-4 py-3 border-b border-slate-100 flex flex-wrap items-center gap-3">
          <div class="relative flex-1 min-w-48">
            <MagnifyingGlassIcon class="absolute left-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
            <input v-model="ptSearch" placeholder="Search participants…"
                   class="pl-8 rounded-lg border border-slate-200 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
          </div>
          <select v-model="ptStatusFilter"
                  class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="all">All Status</option>
            <option value="yes">Present</option>
            <option value="no">Absent</option>
          </select>
          <select v-model="ptEvalFilter"
                  class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="all">All Evaluation</option>
            <option value="yes">Evaluated</option>
            <option value="no">Not Yet Evaluated</option>
          </select>
          <span class="text-xs text-slate-400 ml-auto">{{ filteredParticipants.length }} result(s)</span>
        </div>

        <!-- Table -->
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-slate-100 text-[11px] font-semibold text-slate-400 uppercase tracking-wider bg-slate-50">
              <th class="text-left px-4 py-2">Name</th>
              <th class="text-center px-3 py-2 w-24">Type</th>
              <th class="text-center px-3 py-2 w-28">Hours</th>
              <th class="text-center px-3 py-2 w-32">Status</th>
              <th class="text-center px-3 py-2 w-32">Evaluated</th>
              <th class="text-right px-4 py-2 w-44" v-if="canManage">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50">
            <template v-for="p in paginatedParticipants" :key="p.id">
              <!-- Employee row -->
              <tr v-if="p.type === 'employee'" :ref="() => initEmpRow(p)" class="hover:bg-slate-50">
                <td class="px-4 py-3 font-medium text-slate-700">
                  <span class="inline-flex items-center gap-1.5">
                    <button v-if="activity.is_multi_day" @click="toggleEmpDailyExpand(p)" class="shrink-0">
                      <component :is="expandedEmpDaily === p.id ? ChevronUpIcon : ChevronDownIcon" class="w-4 h-4 text-slate-400" />
                    </button>
                    {{ p.label }}
                  </span>
                </td>
                <td class="px-3 py-3 text-center">
                  <AppBadge color="indigo">Employee</AppBadge>
                </td>
                <td class="px-3 py-3 text-center">
                  <input v-if="canManage && empAttendance[p.id] && !activity.is_multi_day"
                         v-model="empAttendance[p.id].hours"
                         type="number" min="0" max="99" step="0.5"
                         class="w-20 text-center rounded-lg border border-slate-200 px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                  <span v-else class="text-slate-600">{{ empAttendance[p.id]?.hours ?? p.hours_attended ?? '—' }}</span>
                </td>
                <td class="px-3 py-3 text-center">
                  <button v-if="canManage && empAttendance[p.id] && !activity.is_multi_day" @click="toggleAttendance(p)">
                    <AppBadge :color="attendanceColor(empAttendance[p.id].attended)">
                      <CheckCircleIcon class="w-3.5 h-3.5 mr-1" />
                      {{ empAttendance[p.id].attended === 'yes' ? 'Present' : 'Absent' }}
                    </AppBadge>
                  </button>
                  <AppBadge v-else :color="attendanceColor(empAttendance[p.id]?.attended ?? p.attended)">
                    <CheckCircleIcon class="w-3.5 h-3.5 mr-1" />
                    {{ (empAttendance[p.id]?.attended ?? p.attended) === 'yes' ? 'Present' : 'Absent' }}
                  </AppBadge>
                </td>
                <td class="px-3 py-3 text-center">
                  <AppBadge :color="evaluatedColor(p.evaluated)">
                    <CheckCircleIcon class="w-3.5 h-3.5 mr-1" />
                    {{ p.evaluated ? 'Evaluated' : 'Pending' }}
                  </AppBadge>
                </td>
                <td v-if="canManage" class="px-4 py-3 text-right">
                  <div class="inline-flex items-center gap-1">
                    <AppButton size="sm" @click="saveEmpAttendance(p)">Save</AppButton>
                    <AppIconButton :label="copiedId === p.id ? 'Copied!' : 'Copy evaluation link'"
                                    :variant="copiedId === p.id ? 'success' : 'ghost'" size="sm"
                                    @click="copyEvalLink(p)">
                      <ClipboardDocumentCheckIcon v-if="copiedId === p.id" class="w-4 h-4" />
                      <LinkIcon v-else class="w-4 h-4" />
                    </AppIconButton>
                    <a v-if="empAttendance[p.id]?.attended === 'yes' && p.evaluated && p.has_certificate"
                       :href="route('ams.activities.certificates.download.participant', [activity.id, p.id])"
                       target="_blank" class="text-indigo-500 hover:text-indigo-700 p-1 rounded hover:bg-indigo-50" title="Download certificate">
                      <DocumentArrowDownIcon class="w-4 h-4" />
                    </a>
                    <AppIconButton label="Remove participant" variant="danger" size="sm" @click="removeParticipant(p)">
                      <XMarkIcon class="w-4 h-4" />
                    </AppIconButton>
                  </div>
                </td>
              </tr>

              <!-- Employee per-day attendance grid (multi-day activities only) -->
              <tr v-if="p.type === 'employee' && activity.is_multi_day && expandedEmpDaily === p.id">
                <td colspan="6" class="bg-slate-50 px-6 py-4 border-t border-slate-100">
                  <div class="flex items-center justify-between mb-3">
                    <span class="text-xs text-slate-500">Per-day attendance</span>
                    <AppButton v-if="canManage" size="sm" @click="saveEmpAttendance(p)">Save Daily Attendance</AppButton>
                  </div>
                  <div class="border border-slate-200 rounded-lg overflow-hidden overflow-x-auto">
                    <table class="w-full text-sm">
                      <thead>
                        <tr class="bg-white border-b border-slate-100 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
                          <th class="text-left px-3 py-2">Day</th>
                          <th class="text-center px-3 py-2 w-28">Attendance</th>
                          <th class="text-center px-3 py-2 w-24">Hours</th>
                        </tr>
                      </thead>
                      <tbody class="divide-y divide-slate-50">
                        <tr v-for="date in activity.attendance_days" :key="date" class="bg-white">
                          <td class="px-3 py-2 text-slate-700">{{ formatMealDay(date) }}</td>
                          <td class="px-3 py-2 text-center">
                            <button v-if="canManage" @click="getEmpDay(p, date).attended = getEmpDay(p, date).attended === 'yes' ? 'no' : 'yes'">
                              <AppBadge :color="attendanceColor(getEmpDay(p, date).attended)">
                                {{ getEmpDay(p, date).attended === 'yes' ? 'Present' : 'Absent' }}
                              </AppBadge>
                            </button>
                            <AppBadge v-else :color="attendanceColor(getEmpDay(p, date).attended)">
                              {{ getEmpDay(p, date).attended === 'yes' ? 'Present' : 'Absent' }}
                            </AppBadge>
                          </td>
                          <td class="px-3 py-2 text-center">
                            <input v-if="canManage" v-model="getEmpDay(p, date).hours"
                                   type="number" min="0" max="99" step="0.5"
                                   class="w-20 text-center rounded-lg border border-slate-200 px-2 py-1 text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                            <span v-else class="text-slate-600 text-xs">{{ getEmpDay(p, date).hours }}</span>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </td>
              </tr>

              <!-- Section row + inline student roster -->
              <template v-if="p.type === 'section'">
                <tr class="hover:bg-slate-50 cursor-pointer" @click="toggleSectionExpand(p)">
                  <td class="px-4 py-3 font-medium text-slate-700">
                    <span class="inline-flex items-center gap-1.5">
                      <component :is="expandedSection === p.id ? ChevronUpIcon : ChevronDownIcon" class="w-4 h-4 text-slate-400 shrink-0" />
                      {{ p.label }}
                    </span>
                  </td>
                  <td class="px-3 py-3 text-center">
                    <AppBadge color="amber">Section</AppBadge>
                  </td>
                  <td class="px-3 py-3 text-center text-slate-400 text-xs">—</td>
                  <td class="px-3 py-3 text-center text-slate-400 text-xs">—</td>
                  <td class="px-3 py-3 text-center text-slate-400 text-xs">—</td>
                  <td v-if="canManage" class="px-4 py-3 text-right">
                    <AppIconButton label="Remove participant" variant="danger" size="sm" @click.stop="removeParticipant(p)">
                      <XMarkIcon class="w-4 h-4" />
                    </AppIconButton>
                  </td>
                </tr>
                <!-- Inline student roster -->
                <tr v-if="expandedSection === p.id">
                  <td :colspan="canManage ? 6 : 5" class="bg-slate-50 px-6 py-4 border-t border-slate-100">
                    <div v-if="loadingStudents[p.id]" class="py-4 text-center text-sm text-slate-400">Loading students…</div>
                    <div v-else-if="secAttendance[p.id]">
                      <div class="flex items-center justify-between mb-3">
                        <span class="text-xs text-slate-500">{{ secAttendance[p.id].length }} students</span>
                        <div v-if="canManage" class="flex items-center gap-2">
                          <AppButton variant="success" size="sm" @click="markAllSectionStudents(p.id, 'yes')">All Present</AppButton>
                          <AppButton variant="secondary" size="sm" @click="markAllSectionStudents(p.id, 'no')">All Absent</AppButton>
                          <AppButton size="sm" @click="saveSectionAttendance(p)">Save Attendance</AppButton>
                        </div>
                      </div>
                      <div class="border border-slate-200 rounded-lg overflow-hidden">
                        <table class="w-full text-sm">
                          <thead>
                            <tr class="bg-white border-b border-slate-100 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
                              <th class="text-left px-3 py-2">Student</th>
                              <th class="text-center px-3 py-2 w-28">Hours</th>
                              <th class="text-center px-3 py-2 w-28">Attendance</th>
                              <th class="text-center px-3 py-2 w-28">Evaluated</th>
                              <th class="text-center px-3 py-2 w-24">Certificate</th>
                            </tr>
                          </thead>
                          <tbody class="divide-y divide-slate-50">
                            <template v-for="student in secAttendance[p.id]" :key="student.id">
                            <tr class="bg-white hover:bg-slate-50">
                              <td class="px-3 py-2 text-slate-700">
                                <span class="inline-flex items-center gap-1.5">
                                  <button v-if="activity.is_multi_day" @click="toggleStudentDailyExpand(student.attendance_id)" class="shrink-0">
                                    <component :is="expandedStudentDaily === student.attendance_id ? ChevronUpIcon : ChevronDownIcon" class="w-3.5 h-3.5 text-slate-400" />
                                  </button>
                                  {{ student.name }}
                                </span>
                              </td>
                              <td class="px-3 py-2 text-center">
                                <input v-if="canManage && !activity.is_multi_day" v-model="student.hours_attended"
                                       type="number" min="0" max="999" step="0.5"
                                       class="w-20 text-center rounded-lg border border-slate-200 px-2 py-1 text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                                <span v-else class="text-slate-600 text-xs">{{ student.hours_attended }}</span>
                              </td>
                              <td class="px-3 py-2 text-center">
                                <button v-if="canManage && !activity.is_multi_day" @click="student.attended = student.attended === 'yes' ? 'no' : 'yes'">
                                  <AppBadge :color="attendanceColor(student.attended)">
                                    <CheckCircleIcon class="w-3 h-3 mr-1" />
                                    {{ student.attended === 'yes' ? 'Present' : 'Absent' }}
                                  </AppBadge>
                                </button>
                                <AppBadge v-else :color="attendanceColor(student.attended)">
                                  <CheckCircleIcon class="w-3 h-3 mr-1" />
                                  {{ student.attended === 'yes' ? 'Present' : 'Absent' }}
                                </AppBadge>
                              </td>
                              <td class="px-3 py-2 text-center">
                                <AppBadge :color="evaluatedColor(student.evaluated)">
                                  {{ student.evaluated ? 'Evaluated' : 'Pending' }}
                                </AppBadge>
                              </td>
                              <td class="px-3 py-2 text-center">
                                <a
                                  v-if="student.attended === 'yes' && student.evaluated && student.has_certificate"
                                  :href="route('ams.activities.certificates.download.student', [activity.id, student.attendance_id])"
                                  target="_blank"
                                  class="inline-flex rounded p-1 text-indigo-500 hover:bg-indigo-50 hover:text-indigo-700"
                                  title="Download certificate"
                                >
                                  <DocumentArrowDownIcon class="h-4 w-4" />
                                </a>
                                <span v-else class="text-xs text-slate-400">—</span>
                              </td>
                            </tr>
                            <tr v-if="activity.is_multi_day && expandedStudentDaily === student.attendance_id" class="bg-slate-50">
                              <td colspan="5" class="px-6 py-3 border-t border-slate-100">
                                <div class="flex flex-wrap gap-3">
                                  <div v-for="date in activity.attendance_days" :key="date" class="border border-slate-200 rounded-lg px-3 py-2 bg-white min-w-[140px]">
                                    <p class="text-[10px] text-slate-400 uppercase tracking-wide mb-1">{{ formatMealDay(date) }}</p>
                                    <button v-if="canManage" @click="getStudentDay(student, date).attended = getStudentDay(student, date).attended === 'yes' ? 'no' : 'yes'">
                                      <AppBadge :color="attendanceColor(getStudentDay(student, date).attended)">
                                        {{ getStudentDay(student, date).attended === 'yes' ? 'Present' : 'Absent' }}
                                      </AppBadge>
                                    </button>
                                    <input v-if="canManage" v-model="getStudentDay(student, date).hours"
                                           type="number" min="0" max="999" step="0.5"
                                           class="mt-1 w-full text-center rounded border border-slate-200 px-1 py-0.5 text-xs" />
                                  </div>
                                </div>
                              </td>
                            </tr>
                            </template>
                          </tbody>
                        </table>
                      </div>
                    </div>
                  </td>
                </tr>
              </template>
            </template>

            <tr v-if="!filteredParticipants.length">
              <td :colspan="canManage ? 6 : 5" class="px-4 py-10 text-center text-sm text-slate-400 italic">
                {{ participants.length ? 'No participants match the current filters.' : 'No participants yet. Click "Add Participants" to enroll sections or employees.' }}
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Pagination -->
        <PaginationControl
          :current-page="ptPage"
          :total-pages="ptTotalPages"
          :total="filteredParticipants.length"
          @prev="ptSetPage(ptPage - 1)"
          @next="ptSetPage(ptPage + 1)"
          @page="ptSetPage($event)"
        />
      </div>
    </div>

    <!-- ── CO-PROPONENTS TAB ────────────────────────────────────────────────── -->
    <div v-if="activeTab === 'coproponents'" class="space-y-5 max-w-xl">
      <div v-if="canManage" class="flex justify-end">
        <AppButton @click="showAddCoPro = true">
          <PlusIcon class="w-4 h-4" /> Add Co-Proponent
        </AppButton>
      </div>

      <AppModal :show="showAddCoPro && canManage" title="Add Co-Proponent" size="sm" @close="showAddCoPro = false">
        <select v-model="coproEmployeeId"
                class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full">
          <option value="">— Select employee —</option>
          <option v-for="e in availableForCoPro" :key="e.id" :value="e.id">{{ e.name }}</option>
        </select>
        <template #footer>
          <AppButton variant="secondary" @click="showAddCoPro = false">Cancel</AppButton>
          <AppButton :disabled="!coproEmployeeId" @click="addCoPro">Add</AppButton>
        </template>
      </AppModal>

      <div v-if="activity.co_proponents.length" class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="divide-y divide-slate-100">
          <div v-for="cp in activity.co_proponents" :key="cp.id" class="flex items-center gap-3 px-4 py-3">
            <UserIcon class="w-4 h-4 text-slate-400 shrink-0" />
            <span class="flex-1 text-sm text-slate-700">{{ cp.name }}</span>
            <AppIconButton v-if="canManage" label="Remove co-proponent" variant="danger" size="sm" @click="removeCoPro(cp)">
              <XMarkIcon class="w-4 h-4" />
            </AppIconButton>
          </div>
        </div>
      </div>
      <EmptyState v-else-if="!showAddCoPro" title="No co-proponents added yet." />
    </div>

    <!-- ── EVALUATIONS TAB ──────────────────────────────────────────────────── -->
    <div v-if="activeTab === 'evaluations'" class="space-y-6">

      <!-- Evaluation Period -->
      <div class="bg-white rounded-xl border border-slate-200 p-4 flex items-center justify-between gap-4 flex-wrap">
        <div>
          <div class="flex items-center gap-2">
            <AppBadge :color="activity.evaluation_open ? 'green' : 'red'">
              {{ activity.evaluation_open ? 'Evaluation Period Open' : 'Evaluation Period Closed' }}
            </AppBadge>
          </div>
          <p v-if="activity.evaluation_status_changed_at" class="text-xs text-slate-400 mt-1">
            Last changed by {{ activity.evaluation_status_changed_by ?? 'unknown' }} on {{ formatDateTime(activity.evaluation_status_changed_at) }}
          </p>
          <p v-if="!activity.evaluation_open" class="text-xs text-slate-500 mt-1">
            New evaluations (and certificates for anyone who hasn't evaluated) are blocked while closed.
          </p>
        </div>
        <AppButton
          v-if="canToggleEvaluationPeriod"
          :variant="activity.evaluation_open ? 'danger' : 'success'"
          size="sm"
          :loading="togglingPeriod"
          :disabled="togglingPeriod"
          @click="toggleEvaluationPeriod"
        >
          {{ activity.evaluation_open ? 'Close Evaluation Period' : 'Reopen Evaluation Period' }}
        </AppButton>
      </div>

      <!-- Walk-in (QR) evaluation -->
      <div v-if="walkinEvalQr" class="bg-white rounded-xl border border-slate-200 p-4 flex items-center gap-4">
        <img :src="walkinEvalQr.qrData" alt="Walk-in evaluation QR code" class="w-28 h-28 shrink-0" />
        <div class="min-w-0">
          <p class="text-sm font-semibold text-slate-700">Scan to evaluate (walk-in / guest)</p>
          <p class="text-xs text-slate-500 mt-0.5">
            Attendees who aren't tagged as participants can scan this to submit their own evaluation — no account or invite needed.
          </p>
          <p class="text-xs font-mono text-indigo-600 mt-1 break-all">{{ walkinEvalQr.url }}</p>
        </div>
      </div>

      <!-- No responses yet -->
      <div v-if="!evaluations || evaluations.count === 0" class="bg-white rounded-xl border border-slate-200">
        <EmptyState title="No evaluations submitted yet." :icon="ChartBarIcon" />
      </div>

      <template v-else>

        <div class="flex justify-end">
          <AppButton as="a" size="sm" variant="secondary"
                     :href="route('ams.activities.evaluations.export', activity.id)">
            <DocumentArrowDownIcon class="w-4 h-4" />
            Download Excel
          </AppButton>
        </div>

        <!-- In-house: single 13-question survey -->
        <EvaluationSummaryBlock
          v-if="evaluations.type === 'in_house'"
          :count="evaluations.count"
          :sections="evaluations.sections"
          :responses="evaluations.responses"
        />

        <!-- Training/Workshop/Seminar: overall survey + one block per speaker -->
        <template v-else>
          <EvaluationSummaryBlock
            title="Training/Workshop/Activity Evaluation"
            :count="evaluations.count"
            :sections="evaluations.sections"
            :responses="evaluations.responses"
            :extra-text-fields="[
              { key: 'position_function', label: 'Function/Position' },
              { key: 'suggestions', label: 'Suggestions' },
              { key: 'other_comments', label: 'Comments' },
            ]"
          />

          <div v-for="s in evaluations.speakers" :key="s.speaker.id" class="pt-4 border-t border-slate-200">
            <EvaluationSummaryBlock
              :title="`Speaker/Topic Evaluation — ${s.speaker.name}${s.speaker.topic ? ' (' + s.speaker.topic + ')' : ''}`"
              :count="s.count"
              :sections="s.sections"
              :responses="s.responses"
              :extra-text-fields="[
                { key: 'effectiveness_reason', label: 'Effectiveness' },
                { key: 'improvement_suggestions', label: 'Suggestions' },
                { key: 'other_topics_wanted', label: 'Other Topics Wanted' },
              ]"
            />
          </div>
        </template>

      </template>
    </div>

    <!-- ── QUIZZES TAB ─────────────────────────────────────────────────────── -->
    <div v-if="activeTab === 'quizzes'" class="space-y-4">
      <div v-if="canManage" class="flex justify-end">
        <AppButton as="a" :href="route('quiz.create', { source_type: 'activity', source_id: activity.id })">
          <PlusIcon class="w-4 h-4" /> New Quiz
        </AppButton>
      </div>

      <div v-if="quizzes.length === 0" class="bg-white rounded-xl border border-slate-200">
        <EmptyState title="No quizzes attached to this activity yet." subtitle="Run a live poll or review game during the session." :icon="ChartBarIcon" />
      </div>

      <div v-else class="bg-white rounded-xl border border-slate-200 divide-y divide-slate-100">
        <div v-for="q in quizzes" :key="q.id" class="flex items-center justify-between px-4 py-3">
          <div>
            <p class="text-sm font-medium text-slate-700">{{ q.title }}</p>
            <span class="text-xs text-slate-400 capitalize">{{ q.status }} · {{ q.question_count }} question{{ q.question_count === 1 ? '' : 's' }}</span>
          </div>
          <div class="flex items-center gap-3">
            <AppButton v-if="canManage" variant="success" size="sm" @click="hostQuiz(q)">Host</AppButton>
            <AppButton as="a" variant="ghost" size="sm" :href="route('quiz.edit', q.id)">Manage</AppButton>
          </div>
        </div>
      </div>
    </div>

  </AdminLayout>
</template>
