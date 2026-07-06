<script setup>
import { ref, computed, watch } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppModal from '@/Components/AppModal.vue'
import AppInput from '@/Components/AppInput.vue'
import AppSelect from '@/Components/AppSelect.vue'
import AppTextarea from '@/Components/AppTextarea.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import EmptyState from '@/Components/EmptyState.vue'
import {
  UserPlusIcon,
  MagnifyingGlassIcon,
  ArrowUpTrayIcon,
  XMarkIcon,
  CheckCircleIcon,
  UserGroupIcon,
} from '@heroicons/vue/24/outline'
import axios from 'axios'

const props = defineProps({
  sections: Array,
  schoolYears: Array,
  selectedSchoolYear: Number,
  gradeLevels: Array,
  pendingByGrade: Object,
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
  grade_level:      props.gradeLevels?.[0] ?? 7,
  enrollment_type:  'returning',
  enrollment_date:  new Date().toISOString().slice(0, 10),
  notes:            '',
})

const selectedStudent = ref(null)

function openEnrollModal() {
  enrollForm.reset()
  enrollForm.school_year_id = schoolYearId.value
  enrollForm.grade_level    = activeGrade.value
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
  grade_level:     props.gradeLevels?.[0] ?? 7,
  enrollment_type: 'returning',
  enrollment_date: new Date().toISOString().slice(0, 10),
  pisays_ids:      [],
})

const loadingContinuing = ref(false)

function openBulkModal() {
  bulkForm.reset()
  bulkForm.school_year_id = schoolYearId.value
  bulkForm.grade_level    = activeGrade.value
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
  if (ids.length > 200) {
    bulkParseError.value = 'Maximum 200 students per bulk import.'
    return
  }
  bulkParsed.value    = ids
  bulkForm.pisays_ids = ids
}

async function loadContinuingStudents() {
  bulkParseError.value    = ''
  loadingContinuing.value = true
  try {
    const { data } = await axios.get(route('registrar.enrollment.continuing-students'), {
      params: {
        school_year_id: bulkForm.school_year_id,
        grade_level: bulkForm.grade_level,
      },
    })
    if (data.length === 0) {
      bulkParseError.value = `No continuing students found for Grade ${bulkForm.grade_level}.`
      bulkCsvText.value = ''
      bulkParsed.value  = []
      return
    }
    bulkCsvText.value = data.map(s => s.pisays_id).filter(Boolean).join('\n')
    parseBulkCsv()
  } finally {
    loadingContinuing.value = false
  }
}

function submitBulk() {
  bulkForm.post(route('registrar.enrollment.bulk-store'), {
    onSuccess: () => {
      showBulkModal.value = false
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

function sectionCapacityBadge(section) {
  return section.enrolled >= section.capacity ? 'red' : 'indigo'
}

function statusBadge(status) {
  const map = {
    enrolled:        'green',
    dropped:         'red',
    transferred_out: 'amber',
    on_leave:        'blue',
    completed:       'slate',
  }
  return map[status] ?? 'slate'
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

function clearanceBadge(status) {
  const map = {
    cleared:             'green',
    ready_for_adviser:   'indigo',
    pending_registrar:   'blue',
    with_accountability: 'amber',
    in_progress:         'slate',
    open:                'slate',
  }
  return map[status] ?? 'slate'
}

function clearanceLabel(status) {
  return status ? String(status).replaceAll('_', ' ') : 'Not generated'
}
</script>

<template>
  <Head title="Enrollment Management" />
  <AdminLayout title="Enrollment Management">
    <div class="space-y-5">

      <AppPageHeader title="Enrollment Management">
        <template #actions>
          <!-- School year selector (FK-bound — native select) -->
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
        </template>
      </AppPageHeader>

      <!-- Grade level tabs + enroll actions -->
      <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200">
        <div class="flex gap-1">
          <button
            v-for="grade in gradeLevels"
            :key="grade"
            @click="activeGrade = grade"
            :class="[
              'flex items-center gap-1.5 px-4 py-2 text-sm font-medium rounded-t-lg transition-colors',
              activeGrade === grade
                ? 'bg-indigo-600 text-white'
                : 'text-slate-600 hover:bg-slate-100'
            ]"
          >
            Grade {{ grade }}
            <AppBadge v-if="pendingByGrade?.[grade]" color="amber">{{ pendingByGrade[grade] }} pending</AppBadge>
          </button>
        </div>

        <div class="flex items-center gap-2 pb-2">
          <AppButton size="sm" @click="openEnrollModal">
            <UserPlusIcon class="w-4 h-4" />
            Enroll Student
          </AppButton>
          <AppButton size="sm" variant="secondary" @click="openBulkModal">
            <ArrowUpTrayIcon class="w-4 h-4" />
            Bulk Import
          </AppButton>
        </div>
      </div>

      <p class="text-xs text-slate-500 -mt-2">
        Enroll Student / Bulk Import add students to a grade level only. Once enrolled, place them into a
        section using "Assign by Grade List" inside a section below.
      </p>

      <!-- Section cards grid -->
      <EmptyState
        v-if="sectionsByGrade.length === 0"
        title="No sections found"
        :subtitle="`No sections found for Grade ${activeGrade} in this school year.`"
      />

      <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
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
            <AppBadge :color="sectionCapacityBadge(section)">{{ section.enrolled }}/{{ section.capacity }}</AppBadge>
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
              <AppButton size="sm" @click="openAssignModal">
                <UserGroupIcon class="w-4 h-4" />
                Assign by Grade List
              </AppButton>
              <AppIconButton label="Close" @click="closeSection">
                <XMarkIcon class="h-5 w-5" />
              </AppIconButton>
            </div>
          </div>

          <!-- Filters -->
          <div class="px-6 pt-3">
            <AppFilterBar>
              <div class="relative flex-1 min-w-[220px]">
                <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 z-10" />
                <AppInput
                  v-model="studentSearch"
                  placeholder="Search by name, PISAY ID, or LRN…"
                  class="[&_input]:pl-9"
                />
              </div>
              <AppSelect v-model="studentFilter" :show-blank="false" class="min-w-[170px]">
                <option value="all">All statuses</option>
                <option value="enrolled">Enrolled</option>
                <option value="dropped">Dropped</option>
                <option value="transferred_out">Transferred Out</option>
                <option value="on_leave">On Leave</option>
              </AppSelect>
            </AppFilterBar>
          </div>

          <!-- Students list -->
          <div class="flex-1 overflow-y-auto px-6 pb-6">
            <AppTable :loading="loadingStudents" :is-empty="filteredStudents.length === 0" :skeleton-cols="7" :card="false">
              <template #head>
                <tr>
                  <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">#</th>
                  <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Name</th>
                  <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">PISAY ID</th>
                  <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Type</th>
                  <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
                  <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Clearance</th>
                  <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Actions</th>
                </tr>
              </template>

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
                  <AppBadge :color="statusBadge(s.status)">{{ statusLabel(s.status) }}</AppBadge>
                </td>
                <td class="px-4 py-2">
                  <AppBadge :color="clearanceBadge(s.clearance_status)" class="capitalize">{{ clearanceLabel(s.clearance_status) }}</AppBadge>
                  <p v-if="s.clearance_progress" class="mt-1 text-[11px] text-slate-400">
                    {{ s.clearance_progress.done }}/{{ s.clearance_progress.total }} signed
                  </p>
                </td>
                <td class="px-4 py-2">
                  <button
                    @click="openEditStatus(s)"
                    class="text-xs text-indigo-600 hover:underline"
                  >Edit</button>
                </td>
              </tr>

              <template #empty>
                <EmptyState title="No students found" />
              </template>
            </AppTable>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ── Enroll student modal ───────────────────────────────────────────────── -->
    <AppModal
      :show="showEnrollModal"
      title="Enroll Student"
      subtitle="Adds the student to this school year. Section is assigned separately afterward."
      size="md"
      @close="showEnrollModal = false"
    >
      <div class="space-y-4">

        <!-- Grade level (native — plain integer enum, not FK) -->
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Grade Level</label>
          <select
            v-model.number="enrollForm.grade_level"
            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
          >
            <option v-for="grade in gradeLevels" :key="grade" :value="grade">Grade {{ grade }}</option>
          </select>
        </div>

        <!-- Student search -->
        <div class="relative">
          <label class="block text-xs font-medium text-slate-600 mb-1">Search Student</label>
          <div class="relative">
            <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 z-10" />
            <AppInput
              v-model="studentQuery"
              placeholder="Name, PISAY ID, or LRN…"
              autocomplete="off"
              class="[&_input]:pl-9"
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
          <p v-if="enrollForm.errors.student_id" class="text-xs text-danger-600 mt-1">{{ enrollForm.errors.student_id }}</p>
        </div>

        <!-- Selected student card -->
        <div v-if="selectedStudent" class="bg-indigo-50 rounded-lg p-3 text-sm">
          <p class="font-medium text-indigo-800">{{ selectedStudent.full_name }}</p>
          <p class="text-indigo-600 text-xs mt-0.5">PISAY ID: {{ selectedStudent.pisays_id ?? '—' }} · LRN: {{ selectedStudent.lrn ?? '—' }}</p>
        </div>

        <!-- Enrollment type -->
        <AppSelect v-model="enrollForm.enrollment_type" label="Enrollment Type" :show-blank="false">
          <option value="new">New</option>
          <option value="returning">Returning</option>
          <option value="transferee">Transferee</option>
          <option value="returnee">Returnee</option>
        </AppSelect>

        <!-- Enrollment date -->
        <AppInput
          v-model="enrollForm.enrollment_date"
          type="date"
          label="Enrollment Date"
          :error="enrollForm.errors.enrollment_date"
        />

        <!-- Notes -->
        <AppTextarea
          v-model="enrollForm.notes"
          label="Notes (optional)"
          :rows="2"
          placeholder="Scholarship status, special conditions, etc."
        />
      </div>

      <template #footer>
        <AppButton variant="secondary" @click="showEnrollModal = false">Cancel</AppButton>
        <AppButton
          :disabled="!enrollForm.student_id"
          :loading="enrollForm.processing"
          @click="submitEnroll"
        >{{ enrollForm.processing ? 'Enrolling…' : 'Enroll' }}</AppButton>
      </template>
    </AppModal>

    <!-- ── Assign by grade list modal (checkbox picker) ───────────────────────── -->
    <AppModal
      :show="showAssignModal"
      :title="`Assign by Grade List — ${selectedSection?.name ?? ''}`"
      :subtitle="`Students awaiting section placement for Grade ${selectedSection?.grade_level ?? ''}.`"
      size="2xl"
      body-class="px-6 py-4"
      @close="showAssignModal = false"
    >
      <AppFilterBar>
        <div class="relative flex-1 min-w-[220px]">
          <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 z-10" />
          <AppInput
            v-model="assignSearch"
            placeholder="Filter by name, PISAY ID, or LRN…"
            class="[&_input]:pl-9"
          />
        </div>
        <template #actions>
          <button
            type="button"
            @click="toggleSelectAllAssign"
            class="text-xs font-medium text-indigo-600 hover:underline whitespace-nowrap"
          >
            {{ selectedAssignIds.size === filteredUnassigned.length && filteredUnassigned.length > 0 ? 'Clear all' : 'Select all' }}
          </button>
        </template>
      </AppFilterBar>

      <!-- Error -->
      <p v-if="assignError" class="text-xs text-danger-600 mb-2">{{ assignError }}</p>

      <!-- List -->
      <div class="max-h-96 overflow-y-auto">
        <div v-if="loadingUnassigned" class="flex justify-center py-10 text-slate-400 text-sm">
          Loading students…
        </div>
        <EmptyState
          v-else-if="filteredUnassigned.length === 0"
          :title="`No students pending placement for Grade ${selectedSection?.grade_level}`"
          subtitle="Students appear here once they're enrolled — either via an approved enrollment application, or manually using &quot;Enroll Student&quot; / &quot;Bulk Import&quot; above."
        />
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

      <template #footer>
        <div class="flex items-center justify-between w-full">
          <span class="text-xs text-slate-500">
            {{ selectedAssignIds.size }} selected · {{ remainingCapacity }} slot(s) remaining
          </span>
          <div class="flex gap-2">
            <AppButton variant="secondary" @click="showAssignModal = false">Cancel</AppButton>
            <AppButton
              :disabled="selectedAssignIds.size === 0"
              :loading="assigning"
              @click="submitAssign"
            >{{ assigning ? 'Assigning…' : `Assign ${selectedAssignIds.size} Student(s)` }}</AppButton>
          </div>
        </div>
      </template>
    </AppModal>

    <!-- ── Bulk import modal ──────────────────────────────────────────────────── -->
    <AppModal
      :show="showBulkModal"
      title="Bulk Enroll"
      subtitle="Paste PISAY IDs separated by commas, semicolons, or new lines (max 200). Adds students to this school year — section is assigned separately afterward."
      size="lg"
      @close="showBulkModal = false"
    >
      <div class="space-y-4">
        <div class="flex items-center justify-between">
          <label class="block text-xs font-medium text-slate-600">PISAY IDs</label>
          <button
            type="button"
            @click="loadContinuingStudents"
            :disabled="loadingContinuing"
            class="flex items-center gap-1.5 text-xs font-medium text-indigo-600 hover:underline disabled:opacity-50"
          >
            <UserGroupIcon class="w-3.5 h-3.5" />
            {{ loadingContinuing ? 'Loading…' : `Load Continuing Students (Grade ${bulkForm.grade_level})` }}
          </button>
        </div>
        <!-- Native textarea kept for the monospace CSV-paste styling (AppTextarea can't forward `font-mono` to the inner element) -->
        <textarea
          v-model="bulkCsvText"
          rows="6"
          placeholder="20240001&#10;20240002&#10;20240003"
          class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500"
        />

        <div class="flex items-center gap-3">
          <AppButton size="sm" variant="secondary" @click="parseBulkCsv">Preview ({{ bulkParsed.length }})</AppButton>
          <span v-if="bulkParseError" class="text-xs text-danger-600">{{ bulkParseError }}</span>
          <span v-else-if="bulkParsed.length > 0" class="text-xs text-success-600 flex items-center gap-1">
            <CheckCircleIcon class="w-4 h-4" /> {{ bulkParsed.length }} IDs parsed
          </span>
        </div>

        <div class="grid grid-cols-3 gap-3">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Grade Level</label>
            <select
              v-model.number="bulkForm.grade_level"
              class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
            >
              <option v-for="grade in gradeLevels" :key="grade" :value="grade">Grade {{ grade }}</option>
            </select>
          </div>
          <AppSelect v-model="bulkForm.enrollment_type" label="Enrollment Type" :show-blank="false">
            <option value="new">New</option>
            <option value="returning">Returning</option>
            <option value="transferee">Transferee</option>
            <option value="returnee">Returnee</option>
          </AppSelect>
          <AppInput v-model="bulkForm.enrollment_date" type="date" label="Enrollment Date" />
        </div>
      </div>

      <template #footer>
        <AppButton variant="secondary" @click="showBulkModal = false">Cancel</AppButton>
        <AppButton
          :disabled="bulkParsed.length === 0"
          :loading="bulkForm.processing"
          @click="submitBulk"
        >{{ bulkForm.processing ? 'Enrolling…' : `Enroll ${bulkParsed.length} Students` }}</AppButton>
      </template>
    </AppModal>

    <!-- ── Edit enrollment status modal ──────────────────────────────────────── -->
    <AppModal
      :show="!!editingEnrollment"
      title="Update Enrollment"
      :subtitle="editingEnrollment?.full_name"
      size="sm"
      @close="editingEnrollment = null"
    >
      <div class="space-y-4">
        <AppSelect v-model="statusForm.status" label="Status" :show-blank="false">
          <option value="enrolled">Enrolled</option>
          <option value="dropped">Dropped</option>
          <option value="transferred_out">Transferred Out</option>
          <option value="on_leave">On Leave</option>
        </AppSelect>

        <AppTextarea v-model="statusForm.notes" label="Notes" :rows="2" />
      </div>

      <template #footer>
        <AppButton variant="secondary" @click="editingEnrollment = null">Cancel</AppButton>
        <AppButton :loading="statusForm.processing" @click="submitStatus">Save</AppButton>
      </template>
    </AppModal>

  </AdminLayout>
</template>
