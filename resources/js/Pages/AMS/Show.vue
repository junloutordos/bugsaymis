<script setup>
import { ref, computed, reactive, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import EvaluationSummaryBlock from '@/Components/AMS/EvaluationSummaryBlock.vue'
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
  activity:     Object,
  participants: Array,
  employees:    Array,
  sections:     Array,
  canEdit:      Boolean,
  canManage:    Boolean,
  evaluations:  Object,
  quizzes:      { type: Array, default: () => [] },
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
  const result = await Swal.fire({
    icon: 'question',
    title: 'Generate Certificates?',
    text: 'Certificates will be generated for all present and evaluated participants.',
    showCancelButton: true,
    confirmButtonColor: '#059669',
    confirmButtonText: 'Yes, generate',
    cancelButtonText: 'Cancel',
  })
  if (!result.isConfirmed) return

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
  const result = await Swal.fire({
    icon: 'question',
    title: 'Send Evaluation Links?',
    text: 'An email with a unique evaluation link will be sent to all present participants.',
    showCancelButton: true,
    confirmButtonColor: '#7c3aed',
    confirmButtonText: 'Yes, send',
    cancelButtonText: 'Cancel',
  })
  if (!result.isConfirmed) return

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
  const result = await Swal.fire({
    icon: 'warning',
    title: 'Delete Activity?',
    text: `"${props.activity.title}" will be permanently deleted. This cannot be undone.`,
    showCancelButton: true,
    confirmButtonColor: '#dc2626',
    confirmButtonText: 'Yes, delete it',
    cancelButtonText: 'Cancel',
  })
  if (result.isConfirmed) router.delete(route('ams.activities.destroy', props.activity.id))
}

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
  empAttendance[p.id] = { attended: p.attended, hours: p.hours_attended ?? '0.00' }
})

function initEmpRow(p) {
  if (!empAttendance[p.id]) empAttendance[p.id] = { attended: p.attended, hours: p.hours_attended ?? '0.00' }
}

async function toggleAttendance(p) {
  const row = empAttendance[p.id]
  if (!row) return
  if (row.attended === 'yes') {
    const result = await Swal.fire({
      icon: 'warning',
      title: 'Mark as Absent?',
      text: `This will mark "${p.label}" as Absent. Their certificate will not be generated.`,
      showCancelButton: true,
      confirmButtonColor: '#dc2626',
      confirmButtonText: 'Yes, mark absent',
      cancelButtonText: 'Cancel',
    })
    if (!result.isConfirmed) return
  }
  row.attended = row.attended === 'yes' ? 'no' : 'yes'
}

function saveEmpAttendance(p) {
  const row = empAttendance[p.id]
  router.post(
    route('ams.activities.participants.save-attendance', [props.activity.id, p.id]),
    { attended: row.attended, hours_attended: row.hours },
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
    secAttendance[p.id] = list.map(s => ({ ...s }))
  } finally {
    loadingStudents.value[p.id] = false
  }
}

function markAllSectionStudents(pid, attended) {
  if (!secAttendance[pid]) return
  secAttendance[pid].forEach(s => { s.attended = attended })
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
      }))
    },
    { preserveScroll: true }
  )
}

// ── Remove participant ─────────────────────────────────────────────────────

async function removeParticipant(p) {
  const result = await Swal.fire({
    icon: 'warning',
    title: 'Remove Participant?',
    text: `"${p.label}" will be removed from this activity.`,
    showCancelButton: true,
    confirmButtonColor: '#dc2626',
    confirmButtonText: 'Remove',
    cancelButtonText: 'Cancel',
  })
  if (!result.isConfirmed) return
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
  const result = await Swal.fire({
    icon: 'warning',
    title: 'Remove Co-Proponent?',
    text: `${cp.name} will be removed as co-proponent.`,
    showCancelButton: true,
    confirmButtonColor: '#dc2626',
    confirmButtonText: 'Remove',
    cancelButtonText: 'Cancel',
  })
  if (!result.isConfirmed) return
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
          <a :href="route('ams.activities.edit', activity.id)"
             class="inline-flex items-center gap-1.5 bg-amber-500 hover:bg-amber-600 text-white px-3 py-1.5 rounded-lg text-sm font-medium">
            <PencilSquareIcon class="w-4 h-4" /> Edit
          </a>
          <button @click="confirmDelete"
                  class="inline-flex items-center gap-1.5 bg-red-500 hover:bg-red-600 text-white px-3 py-1.5 rounded-lg text-sm font-medium">
            <TrashIcon class="w-4 h-4" /> Delete
          </button>
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
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium"
                  :class="isTws ? 'bg-indigo-50 text-indigo-600' : 'bg-amber-50 text-amber-600'">
              <component :is="isTws ? AcademicCapIcon : BuildingOffice2Icon" class="w-3.5 h-3.5" />
              {{ isTws ? 'Training / Workshop / Seminar' : 'In-house Activity' }}
            </span>
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
        <button v-if="canManage"
                @click="generateCertificates"
                :disabled="generatingCerts"
                class="inline-flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60 text-white px-4 py-2 rounded-lg text-sm font-medium">
          <DocumentArrowDownIcon class="w-4 h-4" />
          {{ generatingCerts ? 'Generating…' : 'Generate All Certificates' }}
        </button>
        <button v-if="canManage"
                @click="sendEvaluationLinks"
                :disabled="sendingEvalLinks"
                class="inline-flex items-center gap-1.5 bg-violet-600 hover:bg-violet-700 disabled:opacity-60 text-white px-4 py-2 rounded-lg text-sm font-medium">
          <EnvelopeIcon class="w-4 h-4" />
          {{ sendingEvalLinks ? 'Sending…' : 'Send Evaluation Links' }}
        </button>
        <button v-if="canManage"
                @click="showAddPanel = !showAddPanel"
                class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
          <PlusIcon class="w-4 h-4" />
          {{ showAddPanel ? 'Cancel' : 'Add Participants' }}
        </button>
      </div>

      <!-- ── Multi-select add panel ─────────────────────────────────────── -->
      <div v-if="showAddPanel && canManage" class="bg-white rounded-xl border border-slate-200 overflow-hidden">
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

        <div class="border-t border-slate-100 px-4 py-3 flex items-center justify-between bg-slate-50">
          <span class="text-sm text-slate-500">{{ selectedCount }} selected</span>
          <button @click="saveParticipants" :disabled="selectedCount === 0 || addSubmitting"
                  class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-40 text-white px-4 py-2 rounded-lg text-sm font-medium">
            {{ addSubmitting ? 'Saving…' : 'Save & Send Invitations' }}
          </button>
        </div>
      </div>

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
            <tr class="border-b border-slate-100 text-xs font-semibold text-slate-500 uppercase tracking-wide bg-slate-50">
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
                <td class="px-4 py-3 font-medium text-slate-700">{{ p.label }}</td>
                <td class="px-3 py-3 text-center">
                  <span class="inline-block px-2 py-0.5 rounded-full text-xs bg-indigo-50 text-indigo-600 font-medium">Employee</span>
                </td>
                <td class="px-3 py-3 text-center">
                  <input v-if="canManage && empAttendance[p.id]"
                         v-model="empAttendance[p.id].hours"
                         type="number" min="0" max="99" step="0.5"
                         class="w-20 text-center rounded-lg border border-slate-200 px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                  <span v-else class="text-slate-600">{{ p.hours_attended ?? '—' }}</span>
                </td>
                <td class="px-3 py-3 text-center">
                  <button v-if="canManage && empAttendance[p.id]"
                          @click="toggleAttendance(p)"
                          class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium transition-colors"
                          :class="empAttendance[p.id].attended === 'yes' ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500'">
                    <CheckCircleIcon class="w-3.5 h-3.5" />
                    {{ empAttendance[p.id].attended === 'yes' ? 'Present' : 'Absent' }}
                  </button>
                  <span v-else class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium"
                        :class="p.attended === 'yes' ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500'">
                    <CheckCircleIcon class="w-3.5 h-3.5" />
                    {{ p.attended === 'yes' ? 'Present' : 'Absent' }}
                  </span>
                </td>
                <td class="px-3 py-3 text-center">
                  <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium"
                        :class="p.evaluated ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500'">
                    <CheckCircleIcon class="w-3.5 h-3.5" />
                    {{ p.evaluated ? 'Evaluated' : 'Pending' }}
                  </span>
                </td>
                <td v-if="canManage" class="px-4 py-3 text-right">
                  <div class="inline-flex items-center gap-1">
                    <button @click="saveEmpAttendance(p)"
                            class="text-xs bg-indigo-600 hover:bg-indigo-700 text-white px-2.5 py-1 rounded-lg font-medium">Save</button>
                    <button @click="copyEvalLink(p)"
                            class="p-1 rounded hover:bg-slate-100 transition-colors"
                            :class="copiedId === p.id ? 'text-green-600' : 'text-slate-400 hover:text-slate-600'"
                            :title="copiedId === p.id ? 'Copied!' : 'Copy evaluation link'">
                      <ClipboardDocumentCheckIcon v-if="copiedId === p.id" class="w-4 h-4" />
                      <LinkIcon v-else class="w-4 h-4" />
                    </button>
                    <a v-if="empAttendance[p.id]?.attended === 'yes'"
                       :href="route('ams.activities.certificates.download.participant', [activity.id, p.id])"
                       target="_blank" class="text-indigo-500 hover:text-indigo-700 p-1 rounded hover:bg-indigo-50" title="Download certificate">
                      <DocumentArrowDownIcon class="w-4 h-4" />
                    </a>
                    <button @click="removeParticipant(p)" class="text-red-400 hover:text-red-600 p-1 rounded hover:bg-red-50">
                      <XMarkIcon class="w-4 h-4" />
                    </button>
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
                    <span class="inline-block px-2 py-0.5 rounded-full text-xs bg-amber-50 text-amber-600 font-medium">Section</span>
                  </td>
                  <td class="px-3 py-3 text-center text-slate-400 text-xs">—</td>
                  <td class="px-3 py-3 text-center text-slate-400 text-xs">—</td>
                  <td class="px-3 py-3 text-center text-slate-400 text-xs">—</td>
                  <td v-if="canManage" class="px-4 py-3 text-right">
                    <button @click.stop="removeParticipant(p)" class="text-red-400 hover:text-red-600 p-1 rounded hover:bg-red-50">
                      <XMarkIcon class="w-4 h-4" />
                    </button>
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
                          <button @click="markAllSectionStudents(p.id, 'yes')"
                                  class="text-xs px-2 py-1 bg-green-100 text-green-700 rounded hover:bg-green-200">All Present</button>
                          <button @click="markAllSectionStudents(p.id, 'no')"
                                  class="text-xs px-2 py-1 bg-slate-100 text-slate-600 rounded hover:bg-slate-200">All Absent</button>
                          <button @click="saveSectionAttendance(p)"
                                  class="text-xs bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1 rounded-lg font-medium">
                            Save Attendance &amp; Send Certificates
                          </button>
                        </div>
                      </div>
                      <div class="border border-slate-200 rounded-lg overflow-hidden">
                        <table class="w-full text-sm">
                          <thead>
                            <tr class="bg-white border-b border-slate-100 text-xs font-semibold text-slate-500 uppercase tracking-wide">
                              <th class="text-left px-3 py-2">Student</th>
                              <th class="text-center px-3 py-2 w-28">Hours</th>
                              <th class="text-center px-3 py-2 w-28">Attendance</th>
                            </tr>
                          </thead>
                          <tbody class="divide-y divide-slate-50">
                            <tr v-for="student in secAttendance[p.id]" :key="student.id" class="bg-white hover:bg-slate-50">
                              <td class="px-3 py-2 text-slate-700">{{ student.name }}</td>
                              <td class="px-3 py-2 text-center">
                                <input v-if="canManage" v-model="student.hours_attended"
                                       type="number" min="0" max="999" step="0.5"
                                       class="w-20 text-center rounded-lg border border-slate-200 px-2 py-1 text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                                <span v-else class="text-slate-600 text-xs">{{ student.hours_attended }}</span>
                              </td>
                              <td class="px-3 py-2 text-center">
                                <button v-if="canManage"
                                        @click="student.attended = student.attended === 'yes' ? 'no' : 'yes'"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium transition-colors"
                                        :class="student.attended === 'yes' ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500'">
                                  <CheckCircleIcon class="w-3 h-3" />
                                  {{ student.attended === 'yes' ? 'Present' : 'Absent' }}
                                </button>
                                <span v-else class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium"
                                      :class="student.attended === 'yes' ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500'">
                                  <CheckCircleIcon class="w-3 h-3" />
                                  {{ student.attended === 'yes' ? 'Present' : 'Absent' }}
                                </span>
                              </td>
                            </tr>
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
        <div v-if="ptTotalPages > 1" class="px-4 py-3 border-t border-slate-100 flex items-center justify-between text-sm">
          <span class="text-slate-500 text-xs">
            Page {{ ptPage }} of {{ ptTotalPages }}
          </span>
          <div class="flex items-center gap-1">
            <button @click="ptSetPage(1)" :disabled="ptPage === 1"
                    class="px-2 py-1 rounded text-xs border border-slate-200 disabled:opacity-40 hover:bg-slate-50">«</button>
            <button @click="ptSetPage(ptPage - 1)" :disabled="ptPage === 1"
                    class="px-2 py-1 rounded text-xs border border-slate-200 disabled:opacity-40 hover:bg-slate-50">‹</button>
            <template v-for="n in ptTotalPages" :key="n">
              <button v-if="Math.abs(n - ptPage) <= 2"
                      @click="ptSetPage(n)"
                      class="px-2.5 py-1 rounded text-xs border font-medium"
                      :class="n === ptPage ? 'bg-indigo-600 text-white border-indigo-600' : 'border-slate-200 hover:bg-slate-50'">
                {{ n }}
              </button>
            </template>
            <button @click="ptSetPage(ptPage + 1)" :disabled="ptPage === ptTotalPages"
                    class="px-2 py-1 rounded text-xs border border-slate-200 disabled:opacity-40 hover:bg-slate-50">›</button>
            <button @click="ptSetPage(ptTotalPages)" :disabled="ptPage === ptTotalPages"
                    class="px-2 py-1 rounded text-xs border border-slate-200 disabled:opacity-40 hover:bg-slate-50">»</button>
          </div>
        </div>
      </div>
    </div>

    <!-- ── CO-PROPONENTS TAB ────────────────────────────────────────────────── -->
    <div v-if="activeTab === 'coproponents'" class="space-y-5 max-w-xl">
      <div v-if="canManage" class="flex justify-end">
        <button @click="showAddCoPro = !showAddCoPro"
                class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
          <PlusIcon class="w-4 h-4" /> Add Co-Proponent
        </button>
      </div>

      <div v-if="showAddCoPro && canManage" class="bg-white rounded-xl border border-slate-200 p-4">
        <h3 class="text-sm font-semibold text-slate-700 mb-3">Add Co-Proponent</h3>
        <div class="flex gap-3 items-end">
          <div class="flex-1">
            <select v-model="coproEmployeeId"
                    class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full">
              <option value="">— Select employee —</option>
              <option v-for="e in availableForCoPro" :key="e.id" :value="e.id">{{ e.name }}</option>
            </select>
          </div>
          <button @click="addCoPro" :disabled="!coproEmployeeId"
                  class="bg-indigo-600 hover:bg-indigo-700 disabled:opacity-40 text-white px-4 py-2 rounded-lg text-sm font-medium">Add</button>
          <button @click="showAddCoPro = false"
                  class="px-3 py-2 rounded-lg border border-slate-200 text-sm text-slate-600 hover:bg-slate-50">Cancel</button>
        </div>
      </div>

      <div v-if="activity.co_proponents.length" class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="divide-y divide-slate-100">
          <div v-for="cp in activity.co_proponents" :key="cp.id" class="flex items-center gap-3 px-4 py-3">
            <UserIcon class="w-4 h-4 text-slate-400 shrink-0" />
            <span class="flex-1 text-sm text-slate-700">{{ cp.name }}</span>
            <button v-if="canManage" @click="removeCoPro(cp)" class="text-red-400 hover:text-red-600 p-1 rounded hover:bg-red-50">
              <XMarkIcon class="w-4 h-4" />
            </button>
          </div>
        </div>
      </div>
      <p v-else-if="!showAddCoPro" class="text-sm text-slate-400 italic text-center py-8">No co-proponents added yet.</p>
    </div>

    <!-- ── EVALUATIONS TAB ──────────────────────────────────────────────────── -->
    <div v-if="activeTab === 'evaluations'" class="space-y-6">

      <!-- No responses yet -->
      <div v-if="!evaluations || evaluations.count === 0"
           class="bg-white rounded-xl border border-slate-200 py-16 text-center">
        <ChartBarIcon class="w-10 h-10 text-slate-300 mx-auto mb-3" />
        <p class="text-sm text-slate-400">No evaluations submitted yet.</p>
      </div>

      <template v-else>

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
        <a
          :href="route('quiz.create', { source_type: 'activity', source_id: activity.id })"
          class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg text-sm font-medium"
        >
          <PlusIcon class="w-4 h-4" /> New Quiz
        </a>
      </div>

      <div v-if="quizzes.length === 0" class="bg-white rounded-xl border border-slate-200 py-16 text-center">
        <ChartBarIcon class="w-10 h-10 text-slate-300 mx-auto mb-3" />
        <p class="text-sm text-slate-500">No quizzes attached to this activity yet.</p>
        <p class="text-xs text-slate-400 mt-1">Run a live poll or review game during the session.</p>
      </div>

      <div v-else class="bg-white rounded-xl border border-slate-200 divide-y divide-slate-100">
        <div v-for="q in quizzes" :key="q.id" class="flex items-center justify-between px-4 py-3">
          <div>
            <p class="text-sm font-medium text-slate-700">{{ q.title }}</p>
            <span class="text-xs text-slate-400 capitalize">{{ q.status }} · {{ q.question_count }} question{{ q.question_count === 1 ? '' : 's' }}</span>
          </div>
          <div class="flex items-center gap-3">
            <button v-if="canManage" type="button" class="text-sm text-emerald-600 hover:underline" @click="hostQuiz(q)">Host</button>
            <a :href="route('quiz.edit', q.id)" class="text-sm text-indigo-600 hover:underline">Manage</a>
          </div>
        </div>
      </div>
    </div>

  </AdminLayout>
</template>
