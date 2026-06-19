<script setup>
import { ref, computed, watch } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import {
  AcademicCapIcon,
  UserPlusIcon,
  MagnifyingGlassIcon,
  ArrowUpTrayIcon,
  XMarkIcon,
  ChevronDownIcon,
  FunnelIcon,
  CheckCircleIcon,
  ExclamationTriangleIcon,
  UserGroupIcon,
} from '@heroicons/vue/24/outline'
import axios from 'axios'

const props = defineProps({
  sections: Array,
  schoolYears: Array,
  selectedSchoolYear: Number,
  gradeLevels: Array,
})

// ── School year filter ────────────────────────────────────────────────────────
const schoolYearId = ref(props.selectedSchoolYear)
watch(schoolYearId, (val) => {
  router.get(route('registrar.enrollment.index'), { school_year_id: val }, { preserveState: true })
})

// ── Active grade tab ──────────────────────────────────────────────────────────
const activeGrade = ref(props.gradeLevels?.[0] ?? 7)

const sectionsByGrade = computed(() => {
  return props.sections.filter(s => s.grade_level === activeGrade.value)
})

// ── Section detail modal ──────────────────────────────────────────────────────
const selectedSection = ref(null)
const sectionStudents = ref([])
const loadingStudents = ref(false)
const studentFilter   = ref('all')    // 'all' | 'enrolled' | 'dropped'
const studentSearch   = ref('')

async function openSection(section) {
  selectedSection.value = section
  sectionStudents.value = []
  loadingStudents.value = true
  try {
    const { data } = await axios.get(route('registrar.enrollment.section-students', section.id), {
      params: { school_year_id: schoolYearId.value },
    })
    sectionStudents.value = data
  } finally {
    loadingStudents.value = false
  }
}

function closeSection() {
  selectedSection.value = null
  sectionStudents.value = []
  showEnrollModal.value = false
  showBulkModal.value   = false
  showAssignModal.value = false
}

const filteredStudents = computed(() => {
  let list = sectionStudents.value
  if (studentFilter.value !== 'all') {
    list = list.filter(s => s.status === studentFilter.value)
  }
  if (studentSearch.value) {
    const q = studentSearch.value.toLowerCase()
    list = list.filter(s =>
      s.full_name.toLowerCase().includes(q) ||
      (s.pisays_id || '').toLowerCase().includes(q) ||
      (s.lrn || '').toLowerCase().includes(q)
    )
  }
  return list
})

// ── Enroll student modal ──────────────────────────────────────────────────────
const showEnrollModal   = ref(false)
const studentQuery      = ref('')
const studentResults    = ref([])
const searchingStudents = ref(false)
let searchTimeout = null

const enrollForm = useForm({
  student_id:       null,
  school_year_id:   props.selectedSchoolYear,
  section_id:       null,
  enrollment_type:  'returning',
  enrollment_date:  new Date().toISOString().slice(0, 10),
  notes:            '',
})

const selectedStudent = ref(null)

function openEnrollModal() {
  enrollForm.reset()
  enrollForm.school_year_id = schoolYearId.value
  enrollForm.section_id     = selectedSection.value?.id
  selectedStudent.value     = null
  studentQuery.value        = ''
  studentResults.value      = []
  showEnrollModal.value     = true
}

watch(studentQuery, (q) => {
  clearTimeout(searchTimeout)
  if (q.length < 2) { studentResults.value = []; return }
  searchTimeout = setTimeout(async () => {
    searchingStudents.value = true
    try {
      const { data } = await axios.get(route('registrar.enrollment.search'), {
        params: { q, school_year_id: schoolYearId.value },
      })
      studentResults.value = data
    } finally {
      searchingStudents.value = false
    }
  }, 300)
})

function selectStudent(student) {
  selectedStudent.value     = student
  enrollForm.student_id     = student.id
  studentQuery.value        = student.full_name
  studentResults.value      = []
}

function submitEnroll() {
  enrollForm.post(route('registrar.enrollment.store'), {
    onSuccess: () => {
      showEnrollModal.value = false
      openSection(selectedSection.value)
    },
  })
}

// ── Assign by grade modal (checkbox picker) ───────────────────────────────────
const showAssignModal     = ref(false)
const unassignedStudents  = ref([])
const loadingUnassigned   = ref(false)
const assignSearch        = ref('')
const selectedAssignIds   = ref(new Set())
const assigning           = ref(false)
const assignError         = ref('')

const filteredUnassigned = computed(() => {
  if (!assignSearch.value) return unassignedStudents.value
  const q = assignSearch.value.toLowerCase()
  return unassignedStudents.value.filter(s =>
    s.full_name.toLowerCase().includes(q) ||
    (s.pisays_id || '').toLowerCase().includes(q) ||
    (s.lrn || '').toLowerCase().includes(q)
  )
})

const remainingCapacity = computed(() => {
  if (!selectedSection.value) return 0
  return selectedSection.value.capacity - selectedSection.value.enrolled
})

async function openAssignModal() {
  showAssignModal.value    = true
  assignSearch.value       = ''
  assignError.value        = ''
  selectedAssignIds.value  = new Set()
  unassignedStudents.value = []
  loadingUnassigned.value  = true
  try {
    const { data } = await axios.get(route('registrar.enrollment.unassigned'), {
      params: {
        school_year_id: schoolYearId.value,
        grade_level: selectedSection.value.grade_level,
      },
    })
    unassignedStudents.value = data
  } finally {
    loadingUnassigned.value = false
  }
}

function toggleAssign(enrollmentId) {
  const next = new Set(selectedAssignIds.value)
  if (next.has(enrollmentId)) next.delete(enrollmentId)
  else next.add(enrollmentId)
  selectedAssignIds.value = next
}

function toggleSelectAllAssign() {
  if (selectedAssignIds.value.size === filteredUnassigned.value.length) {
    selectedAssignIds.value = new Set()
  } else {
    selectedAssignIds.value = new Set(filteredUnassigned.value.map(s => s.enrollment_id))
  }
}

function submitAssign() {
  assignError.value = ''
  assigning.value = true
  router.post(route('registrar.enrollment.bulk-assign'), {
    school_year_id: schoolYearId.value,
    section_id: selectedSection.value.id,
    enrollment_ids: Array.from(selectedAssignIds.value),
  }, {
    onSuccess: () => {
      showAssignModal.value = false
      openSection(selectedSection.value)
    },
    onError: (errors) => {
      assignError.value = Object.values(errors)[0] ?? 'An error occurred.'
    },
    onFinish: () => { assigning.value = false },
  })
}

// ── Bulk import modal ─────────────────────────────────────────────────────────
const showBulkModal  = ref(false)
const bulkCsvText    = ref('')
const bulkParsed     = ref([])
const bulkParseError = ref('')

const bulkForm = useForm({
  school_year_id:  props.selectedSchoolYear,
  section_id:      null,
  enrollment_type: 'returning',
  enrollment_date: new Date().toISOString().slice(0, 10),
  pisays_ids:      [],
})

function openBulkModal() {
  bulkForm.reset()
  bulkForm.school_year_id = schoolYearId.value
  bulkForm.section_id     = selectedSection.value?.id
  bulkCsvText.value       = ''
  bulkParsed.value        = []
  bulkParseError.value    = ''
  showBulkModal.value     = true
}

function parseBulkCsv() {
  bulkParseError.value = ''
  const ids = bulkCsvText.value
    .split(/[\n,;]+/)
    .map(s => s.trim())
    .filter(Boolean)

  if (ids.length === 0) {
    bulkParseError.value = 'No PISAY IDs found.'
    return
  }
  if (ids.length > 60) {
    bulkParseError.value = 'Maximum 60 students per bulk import.'
    return
  }
  bulkParsed.value    = ids
  bulkForm.pisays_ids = ids
}

function submitBulk() {
  bulkForm.post(route('registrar.enrollment.bulk-store'), {
    onSuccess: () => {
      showBulkModal.value = false
      openSection(selectedSection.value)
    },
  })
}

// ── Update enrollment status ──────────────────────────────────────────────────
const editingEnrollment = ref(null)
const statusForm = useForm({ status: '', notes: '', section_id: null })

function openEditStatus(enrollment) {
  editingEnrollment.value = enrollment
  statusForm.status       = enrollment.status
  statusForm.notes        = enrollment.notes || ''
  statusForm.section_id   = null
}

function submitStatus() {
  statusForm.put(route('registrar.enrollment.update', editingEnrollment.value.id), {
    onSuccess: () => {
      editingEnrollment.value = null
      openSection(selectedSection.value)
    },
  })
}

// ── Helpers ───────────────────────────────────────────────────────────────────
const GRADE_LABELS = { 7:'Grade 7',8:'Grade 8',9:'Grade 9',10:'Grade 10',11:'Grade 11',12:'Grade 12' }

function capacityColor(enrolled, capacity) {
  const pct = enrolled / capacity
  if (pct >= 1)    return 'text-red-600 font-semibold'
  if (pct >= 0.9)  return 'text-amber-600 font-semibold'
  return 'text-slate-600'
}

function statusBadge(status) {
  const map = {
    enrolled:        'bg-green-100 text-green-700',
    dropped:         'bg-red-100 text-red-700',
    transferred_out: 'bg-amber-100 text-amber-700',
    on_leave:        'bg-blue-100 text-blue-700',
    completed:       'bg-slate-100 text-slate-600',
  }
  return map[status] ?? 'bg-slate-100 text-slate-600'
}

function statusLabel(status) {
  const map = {
    enrolled:        'Enrolled',
    dropped:         'Dropped',
    transferred_out: 'Transferred Out',
    on_leave:        'On Leave',
    completed:       'Completed',
  }
  return map[status] ?? status
}
</script>

<template>
  <Head title="Enrollment Management" />
  <AdminLayout title="Enrollment Management">

    <!-- Header bar -->
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
      <div class="flex items-center gap-2">
        <AcademicCapIcon class="w-6 h-6 text-indigo-600" />
        <h1 class="text-lg font-semibold text-slate-800">Enrollment Management</h1>
      </div>

      <div class="flex items-center gap-3">
        <!-- School year selector -->
        <select
          v-model="schoolYearId"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
        >
          <option v-for="sy in schoolYears" :key="sy.id" :value="sy.id">
            {{ sy.name }}{{ sy.is_current ? ' (Current)' : '' }}
          </option>
        </select>

        <!-- Enrollment periods link -->
        <a
          :href="route('registrar.enrollment-periods.index')"
          class="text-sm text-indigo-600 hover:underline"
        >Enrollment Periods</a>
      </div>
    </div>

    <!-- Grade level tabs -->
    <div class="flex gap-1 mb-5 border-b border-slate-200">
      <button
        v-for="grade in gradeLevels"
        :key="grade"
        @click="activeGrade = grade"
        :class="[
          'px-4 py-2 text-sm font-medium rounded-t-lg transition-colors',
          activeGrade === grade
            ? 'bg-indigo-600 text-white'
            : 'text-slate-600 hover:bg-slate-100'
        ]"
      >
        Grade {{ grade }}
      </button>
    </div>

    <!-- Section cards grid -->
    <div v-if="sectionsByGrade.length === 0" class="text-slate-500 text-sm py-8 text-center">
      No sections found for Grade {{ activeGrade }} in this school year.
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 mb-8">
      <button
        v-for="section in sectionsByGrade"
        :key="section.id"
        @click="openSection(section)"
        class="text-left bg-white rounded-xl border border-slate-200 p-4 hover:border-indigo-400 hover:shadow-sm transition-all"
      >
        <div class="flex items-start justify-between">
          <div>
            <p class="font-semibold text-slate-800 text-sm">{{ section.name }}</p>
            <p class="text-xs text-slate-500 mt-0.5">{{ GRADE_LABELS[section.grade_level] }}</p>
          </div>
          <!-- Capacity indicator -->
          <span
            :class="[
              'text-xs font-medium px-2 py-0.5 rounded-full',
              section.enrolled >= section.capacity
                ? 'bg-red-100 text-red-700'
                : 'bg-indigo-50 text-indigo-700'
            ]"
          >
            {{ section.enrolled }}/{{ section.capacity }}
          </span>
        </div>

        <div class="mt-3 flex items-center gap-4 text-xs text-slate-500">
          <span>Adviser: {{ section.adviser_name ?? '—' }}</span>
        </div>

        <div class="mt-2 flex items-center gap-3 text-xs">
          <span class="text-green-700">{{ section.enrolled }} enrolled</span>
          <span v-if="section.dropped > 0" class="text-red-600">{{ section.dropped }} dropped</span>
          <span v-if="section.transferred > 0" class="text-amber-600">{{ section.transferred }} transferred</span>
        </div>
      </button>
    </div>

    <!-- ── Section detail side panel ─────────────────────────────────────────── -->
    <Teleport to="body">
      <div
        v-if="selectedSection"
        class="fixed inset-0 z-40 flex"
        @click.self="closeSection"
      >
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-black/30" @click="closeSection" />

        <!-- Panel -->
        <div class="relative ml-auto w-full max-w-3xl bg-white shadow-2xl flex flex-col h-full">

          <!-- Panel header -->
          <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200">
            <div>
              <h2 class="font-semibold text-slate-800">{{ selectedSection.name }}</h2>
              <p class="text-xs text-slate-500">{{ GRADE_LABELS[selectedSection.grade_level] }} · {{ selectedSection.enrolled }}/{{ selectedSection.capacity }} enrolled</p>
            </div>
            <div class="flex items-center gap-2">
              <button
                @click="openEnrollModal"
                class="flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg text-sm font-medium"
              >
                <UserPlusIcon class="w-4 h-4" />
                Enroll Student
              </button>
              <button
                @click="openAssignModal"
                class="flex items-center gap-1.5 border border-slate-200 text-slate-700 hover:bg-slate-50 px-3 py-1.5 rounded-lg text-sm font-medium"
              >
                <UserGroupIcon class="w-4 h-4" />
                Assign by Grade List
              </button>
              <button
                @click="openBulkModal"
                class="flex items-center gap-1.5 border border-slate-200 text-slate-700 hover:bg-slate-50 px-3 py-1.5 rounded-lg text-sm font-medium"
              >
                <ArrowUpTrayIcon class="w-4 h-4" />
                Bulk Import
              </button>
              <button @click="closeSection" class="p-1 rounded-lg hover:bg-slate-100">
                <XMarkIcon class="w-5 h-5 text-slate-500" />
              </button>
            </div>
          </div>

          <!-- Filters -->
          <div class="flex items-center gap-3 px-6 py-3 border-b border-slate-100">
            <div class="relative flex-1">
              <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
              <input
                v-model="studentSearch"
                type="text"
                placeholder="Search by name, PISAY ID, or LRN…"
                class="w-full pl-9 pr-3 py-2 text-sm rounded-lg border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
              />
            </div>
            <select
              v-model="studentFilter"
              class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
            >
              <option value="all">All statuses</option>
              <option value="enrolled">Enrolled</option>
              <option value="dropped">Dropped</option>
              <option value="transferred_out">Transferred Out</option>
              <option value="on_leave">On Leave</option>
            </select>
          </div>

          <!-- Students list -->
          <div class="flex-1 overflow-y-auto">
            <div v-if="loadingStudents" class="flex justify-center py-12 text-slate-400 text-sm">
              Loading students…
            </div>

            <div v-else-if="filteredStudents.length === 0" class="flex justify-center py-12 text-slate-400 text-sm">
              No students found.
            </div>

            <table v-else class="min-w-full text-sm">
              <thead class="sticky top-0 bg-slate-50 border-b border-slate-200">
                <tr>
                  <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">#</th>
                  <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Name</th>
                  <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">PISAY ID</th>
                  <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Type</th>
                  <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
                  <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr
                  v-for="(s, idx) in filteredStudents"
                  :key="s.id"
                  class="hover:bg-slate-50"
                >
                  <td class="px-4 py-2 text-slate-400 tabular-nums">{{ idx + 1 }}</td>
                  <td class="px-4 py-2 font-medium text-slate-800">{{ s.full_name }}</td>
                  <td class="px-4 py-2 text-slate-500 tabular-nums">{{ s.pisays_id ?? '—' }}</td>
                  <td class="px-4 py-2 text-slate-500 capitalize">{{ s.enrollment_type }}</td>
                  <td class="px-4 py-2">
                    <span :class="['text-xs px-2 py-0.5 rounded-full font-medium', statusBadge(s.status)]">
                      {{ statusLabel(s.status) }}
                    </span>
                  </td>
                  <td class="px-4 py-2">
                    <button
                      @click="openEditStatus(s)"
                      class="text-xs text-indigo-600 hover:underline"
                    >Edit</button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ── Enroll student modal ───────────────────────────────────────────────── -->
    <Teleport to="body">
      <div
        v-if="showEnrollModal"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
      >
        <div class="absolute inset-0 bg-black/40" @click="showEnrollModal = false" />
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-md p-6">
          <h3 class="font-semibold text-slate-800 mb-4">Enroll Student — {{ selectedSection?.name }}</h3>

          <form @submit.prevent="submitEnroll" class="space-y-4">

            <!-- Student search -->
            <div class="relative">
              <label class="block text-xs font-medium text-slate-600 mb-1">Search Student</label>
              <div class="relative">
                <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
                <input
                  v-model="studentQuery"
                  type="text"
                  placeholder="Name, PISAY ID, or LRN…"
                  class="w-full pl-9 pr-3 py-2 text-sm rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  autocomplete="off"
                />
              </div>
              <!-- Dropdown results -->
              <div
                v-if="studentResults.length > 0"
                class="absolute z-10 w-full bg-white border border-slate-200 rounded-lg shadow-lg mt-1 max-h-48 overflow-y-auto"
              >
                <button
                  v-for="s in studentResults"
                  :key="s.id"
                  type="button"
                  @click="selectStudent(s)"
                  class="w-full text-left px-4 py-2 text-sm hover:bg-indigo-50"
                >
                  <span class="font-medium text-slate-800">{{ s.full_name }}</span>
                  <span class="text-slate-400 ml-2 text-xs">{{ s.pisays_id }}</span>
                </button>
              </div>
              <p v-if="enrollForm.errors.student_id" class="text-xs text-red-600 mt-1">{{ enrollForm.errors.student_id }}</p>
            </div>

            <!-- Selected student card -->
            <div v-if="selectedStudent" class="bg-indigo-50 rounded-lg p-3 text-sm">
              <p class="font-medium text-indigo-800">{{ selectedStudent.full_name }}</p>
              <p class="text-indigo-600 text-xs mt-0.5">PISAY ID: {{ selectedStudent.pisays_id ?? '—' }} · LRN: {{ selectedStudent.lrn ?? '—' }}</p>
            </div>

            <!-- Enrollment type -->
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Enrollment Type</label>
              <select
                v-model="enrollForm.enrollment_type"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
              >
                <option value="new">New</option>
                <option value="returning">Returning</option>
                <option value="transferee">Transferee</option>
                <option value="returnee">Returnee</option>
              </select>
            </div>

            <!-- Enrollment date -->
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Enrollment Date</label>
              <input
                v-model="enrollForm.enrollment_date"
                type="date"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
              />
              <p v-if="enrollForm.errors.enrollment_date" class="text-xs text-red-600 mt-1">{{ enrollForm.errors.enrollment_date }}</p>
            </div>

            <!-- Notes -->
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Notes <span class="text-slate-400">(optional)</span></label>
              <textarea
                v-model="enrollForm.notes"
                rows="2"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                placeholder="Scholarship status, special conditions, etc."
              />
            </div>

            <div class="flex justify-end gap-2 pt-2">
              <button
                type="button"
                @click="showEnrollModal = false"
                class="px-4 py-2 rounded-lg text-sm text-slate-600 border border-slate-200 hover:bg-slate-50"
              >Cancel</button>
              <button
                type="submit"
                :disabled="!enrollForm.student_id || enrollForm.processing"
                class="px-4 py-2 rounded-lg text-sm font-medium bg-indigo-600 hover:bg-indigo-700 text-white disabled:opacity-50"
              >
                {{ enrollForm.processing ? 'Enrolling…' : 'Enroll' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- ── Assign by grade list modal (checkbox picker) ───────────────────────── -->
    <Teleport to="body">
      <div
        v-if="showAssignModal"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
      >
        <div class="absolute inset-0 bg-black/40" @click="showAssignModal = false" />
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-2xl flex flex-col max-h-[85vh]">

          <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
            <div>
              <h3 class="font-semibold text-slate-800">Assign by Grade List — {{ selectedSection?.name }}</h3>
              <p class="text-xs text-slate-500 mt-0.5">
                Students awaiting section placement for Grade {{ selectedSection?.grade_level }}.
              </p>
            </div>
            <button @click="showAssignModal = false" class="p-1 rounded-lg hover:bg-slate-100">
              <XMarkIcon class="w-5 h-5 text-slate-500" />
            </button>
          </div>

          <!-- Filter + select-all -->
          <div class="flex items-center gap-3 px-6 py-3 border-b border-slate-100">
            <div class="relative flex-1">
              <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
              <input
                v-model="assignSearch"
                type="text"
                placeholder="Filter by name, PISAY ID, or LRN…"
                class="w-full pl-9 pr-3 py-2 text-sm rounded-lg border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
              />
            </div>
            <button
              type="button"
              @click="toggleSelectAllAssign"
              class="text-xs font-medium text-indigo-600 hover:underline whitespace-nowrap"
            >
              {{ selectedAssignIds.size === filteredUnassigned.length && filteredUnassigned.length > 0 ? 'Clear all' : 'Select all' }}
            </button>
          </div>

          <!-- Error -->
          <div v-if="assignError" class="px-6 pt-3 text-xs text-red-600">{{ assignError }}</div>

          <!-- List -->
          <div class="flex-1 overflow-y-auto px-6 py-2">
            <div v-if="loadingUnassigned" class="flex justify-center py-10 text-slate-400 text-sm">
              Loading students…
            </div>
            <div v-else-if="filteredUnassigned.length === 0" class="flex flex-col items-center gap-1 py-10 text-center text-sm">
              <p class="text-slate-400">No students are currently pending placement for Grade {{ selectedSection?.grade_level }}.</p>
              <p class="text-xs text-slate-400 max-w-md">
                Students appear here once an enrollment application is approved. If you're migrating continuing
                students from last year, use
                <a :href="route('registrar.section-assignment.index')" class="text-indigo-600 hover:underline">Continuing Student Migration</a>
                in the sidebar first.
              </p>
            </div>
            <label
              v-for="s in filteredUnassigned"
              :key="s.enrollment_id"
              class="flex items-center gap-3 py-2 px-2 rounded-lg hover:bg-slate-50 cursor-pointer"
            >
              <input
                type="checkbox"
                :checked="selectedAssignIds.has(s.enrollment_id)"
                @change="toggleAssign(s.enrollment_id)"
                class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
              />
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-slate-800">{{ s.full_name }}</p>
                <p class="text-xs text-slate-500">
                  {{ s.pisays_id ?? '—' }}
                  <span v-if="s.sex" class="mx-1">·</span>
                  {{ s.sex }}
                </p>
              </div>
              <span class="text-xs text-slate-400 capitalize">{{ s.enrollment_type }}</span>
            </label>
          </div>

          <!-- Footer -->
          <div class="px-6 py-4 border-t border-slate-200 flex items-center justify-between">
            <span class="text-xs text-slate-500">
              {{ selectedAssignIds.size }} selected · {{ remainingCapacity }} slot(s) remaining
            </span>
            <div class="flex gap-2">
              <button
                type="button"
                @click="showAssignModal = false"
                class="px-4 py-2 rounded-lg text-sm text-slate-600 border border-slate-200 hover:bg-slate-50"
              >Cancel</button>
              <button
                type="button"
                @click="submitAssign"
                :disabled="selectedAssignIds.size === 0 || assigning"
                class="px-4 py-2 rounded-lg text-sm font-medium bg-indigo-600 hover:bg-indigo-700 text-white disabled:opacity-50"
              >
                {{ assigning ? 'Assigning…' : `Assign ${selectedAssignIds.size} Student(s)` }}
              </button>
            </div>
          </div>

        </div>
      </div>
    </Teleport>

    <!-- ── Bulk import modal ──────────────────────────────────────────────────── -->
    <Teleport to="body">
      <div
        v-if="showBulkModal"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
      >
        <div class="absolute inset-0 bg-black/40" @click="showBulkModal = false" />
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-lg p-6">
          <h3 class="font-semibold text-slate-800 mb-1">Bulk Enroll — {{ selectedSection?.name }}</h3>
          <p class="text-xs text-slate-500 mb-4">Paste PISAY IDs separated by commas, semicolons, or new lines (max 60).</p>

          <form @submit.prevent="submitBulk" class="space-y-4">
            <textarea
              v-model="bulkCsvText"
              rows="6"
              placeholder="20240001&#10;20240002&#10;20240003"
              class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />

            <div class="flex items-center gap-3">
              <button
                type="button"
                @click="parseBulkCsv"
                class="px-3 py-1.5 rounded-lg text-sm border border-slate-200 hover:bg-slate-50 text-slate-700"
              >Preview ({{ bulkParsed.length }})</button>
              <span v-if="bulkParseError" class="text-xs text-red-600">{{ bulkParseError }}</span>
              <span v-else-if="bulkParsed.length > 0" class="text-xs text-green-600 flex items-center gap-1">
                <CheckCircleIcon class="w-4 h-4" /> {{ bulkParsed.length }} IDs parsed
              </span>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Enrollment Type</label>
                <select
                  v-model="bulkForm.enrollment_type"
                  class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                >
                  <option value="new">New</option>
                  <option value="returning">Returning</option>
                  <option value="transferee">Transferee</option>
                  <option value="returnee">Returnee</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Enrollment Date</label>
                <input
                  v-model="bulkForm.enrollment_date"
                  type="date"
                  class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                />
              </div>
            </div>

            <div class="flex justify-end gap-2 pt-2">
              <button
                type="button"
                @click="showBulkModal = false"
                class="px-4 py-2 rounded-lg text-sm text-slate-600 border border-slate-200 hover:bg-slate-50"
              >Cancel</button>
              <button
                type="submit"
                :disabled="bulkParsed.length === 0 || bulkForm.processing"
                class="px-4 py-2 rounded-lg text-sm font-medium bg-indigo-600 hover:bg-indigo-700 text-white disabled:opacity-50"
              >
                {{ bulkForm.processing ? 'Enrolling…' : `Enroll ${bulkParsed.length} Students` }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- ── Edit enrollment status modal ──────────────────────────────────────── -->
    <Teleport to="body">
      <div
        v-if="editingEnrollment"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
      >
        <div class="absolute inset-0 bg-black/40" @click="editingEnrollment = null" />
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-sm p-6">
          <h3 class="font-semibold text-slate-800 mb-4">Update Enrollment</h3>
          <p class="text-sm text-slate-600 mb-4">{{ editingEnrollment.full_name }}</p>

          <form @submit.prevent="submitStatus" class="space-y-4">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Status</label>
              <select
                v-model="statusForm.status"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
              >
                <option value="enrolled">Enrolled</option>
                <option value="dropped">Dropped</option>
                <option value="transferred_out">Transferred Out</option>
                <option value="on_leave">On Leave</option>
              </select>
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Notes</label>
              <textarea
                v-model="statusForm.notes"
                rows="2"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
              />
            </div>

            <div class="flex justify-end gap-2 pt-2">
              <button
                type="button"
                @click="editingEnrollment = null"
                class="px-4 py-2 rounded-lg text-sm text-slate-600 border border-slate-200 hover:bg-slate-50"
              >Cancel</button>
              <button
                type="submit"
                :disabled="statusForm.processing"
                class="px-4 py-2 rounded-lg text-sm font-medium bg-indigo-600 hover:bg-indigo-700 text-white disabled:opacity-50"
              >Save</button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

  </AdminLayout>
</template>
