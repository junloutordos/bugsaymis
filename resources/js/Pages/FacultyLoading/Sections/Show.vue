<template>
  <Head :title="`${section.full_label} — Detail`" />
  <AdminLayout :title="section.full_label">
    <div class="space-y-5">

      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
        <div class="flex items-center gap-3">
          <Link :href="route('faculty-loading.sections.index')"
            class="p-1.5 text-slate-400 hover:text-slate-600 rounded transition-colors">
            <ArrowLeftIcon class="h-5 w-5" />
          </Link>
          <div>
            <h1 class="text-xl font-semibold text-slate-800">{{ section.full_label }}</h1>
            <p class="text-sm text-slate-500 mt-0.5">
              {{ section.school_year?.name }}
              <span v-if="section.section_code" class="mx-1 text-slate-300">·</span>
              <span v-if="section.section_code" class="font-mono text-xs">{{ section.section_code }}</span>
            </p>
          </div>
        </div>

        <!-- Meta pills -->
        <div class="flex flex-wrap gap-2">
          <AppBadge color="slate">
            <UsersIcon class="h-3.5 w-3.5 mr-1" />
            {{ students.length }} student{{ students.length !== 1 ? 's' : '' }}
            <span v-if="section.capacity" class="text-slate-400">/ {{ section.capacity }}</span>
          </AppBadge>
          <AppBadge color="indigo">
            <BookOpenIcon class="h-3.5 w-3.5 mr-1" />
            {{ assignedCount }}/{{ subjects.length }} assigned
          </AppBadge>
          <AppBadge v-if="section.adviser" color="amber">
            <AcademicCapIcon class="h-3.5 w-3.5 mr-1" />
            {{ section.adviser.name }}
          </AppBadge>
        </div>
      </div>

      <!-- Break Times Card -->
      <AppCard v-if="section.recess_start || section.lunch_start || section.afternoon_break_start">
        <h2 class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-3 flex items-center gap-2">
          <ClockIcon class="h-4 w-4" /> Break Times
        </h2>
        <div class="flex flex-wrap gap-6">
          <div v-if="section.recess_start" class="flex items-center gap-2">
            <AppBadge color="blue">Recess: {{ formatTime(section.recess_start) }} – {{ formatTime(section.recess_end) }}</AppBadge>
          </div>
          <div v-if="section.lunch_start" class="flex items-center gap-2">
            <AppBadge color="orange">Lunch: {{ formatTime(section.lunch_start) }} – {{ formatTime(section.lunch_end) }}</AppBadge>
          </div>
          <div v-if="section.afternoon_break_start" class="flex items-center gap-2">
            <AppBadge color="purple">Afternoon Break: {{ formatTime(section.afternoon_break_start) }} – {{ formatTime(section.afternoon_break_end) }}</AppBadge>
          </div>
        </div>
      </AppCard>
      <div v-else
        class="bg-warning-50 border border-warning-100 rounded-xl px-5 py-3 text-xs text-warning-700 flex items-center gap-2">
        <ExclamationTriangleIcon class="h-4 w-4 shrink-0" />
        No break times set. Schedule generation will not block recess, lunch, or afternoon break periods for this section.
        <Link :href="route('faculty-loading.sections.index')" class="underline ml-1">Set break times</Link>
      </div>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success" class="bg-success-50 border border-success-100 text-success-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <CheckCircleIcon class="h-4 w-4 shrink-0" />{{ $page.props.flash.success }}
      </div>

      <!-- ── Weekly Schedule (read-only) ──────────────────────────────── -->
      <ScheduleCalendarCard
        title="Weekly Schedule"
        :meta="'· ' + schedule.length + ' slot(s)'"
        :events-by-day="scheduleByDay"
        :day-configs="dayConfigs"
        :legend="scheduleLegend" />

      <!-- ── Subjects (full catalog for this grade) ──────────────────── -->
      <AppCard :padded="false">
        <div class="px-5 py-3.5 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3">
          <h2 class="text-sm font-semibold text-slate-700 flex items-center gap-2">
            <BookOpenIcon class="h-4 w-4 text-indigo-500" />
            Subjects
            <span class="text-xs font-normal text-slate-400">
              ({{ assignedCount }} assigned, {{ unassignedCount }} pending)
            </span>
          </h2>
          <div class="flex items-center gap-2">
            <select v-model="subjectFilter"
              class="text-xs border border-slate-200 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
              <option value="all">All subjects</option>
              <option value="assigned">Assigned only</option>
              <option value="unassigned">Unassigned only</option>
            </select>
            <!-- Term filter -->
            <select v-model.number="selectedTermId" @change="applyTermFilter"
              class="text-xs border border-slate-200 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
              <option v-for="t in terms" :key="t.id" :value="t.id">
                {{ t.label }}{{ t.is_current ? ' (current)' : '' }}
              </option>
            </select>
          </div>
        </div>

        <AppTable :card="false" :is-empty="filteredSubjects.length === 0">
          <template #head>
            <tr>
              <th class="px-4 py-2 text-left text-xs text-slate-400 uppercase tracking-wide">Code</th>
              <th class="px-4 py-2 text-left text-xs text-slate-400 uppercase tracking-wide">Subject</th>
              <th class="px-4 py-2 text-left text-xs text-slate-400 uppercase tracking-wide">Type</th>
              <th class="px-4 py-2 text-center text-xs text-slate-400 uppercase tracking-wide">Units</th>
              <th class="px-4 py-2 text-left text-xs text-slate-400 uppercase tracking-wide">Faculty</th>
              <th class="px-4 py-2 text-center text-xs text-slate-400 uppercase tracking-wide">Status</th>
              <th class="px-4 py-2"></th>
            </tr>
          </template>

          <tr v-for="s in filteredSubjects" :key="s.id"
            :class="s.is_assigned ? 'hover:bg-slate-50/50' : 'bg-danger-50/30 hover:bg-danger-50/50'">
            <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ s.subject_code ?? '—' }}</td>
            <td class="px-4 py-3 font-medium text-slate-800">
              {{ s.subject_name }}
              <AppBadge v-if="s.is_elective" color="amber">elective</AppBadge>
            </td>
            <td class="px-4 py-3">
              <AppBadge :color="typeClass(s.subject_type)" class="capitalize">{{ s.subject_type ?? '—' }}</AppBadge>
            </td>
            <td class="px-4 py-3 text-center text-slate-600">{{ s.load_units }}</td>
            <td class="px-4 py-3 text-slate-600">{{ s.faculty_name ?? '—' }}</td>
            <td class="px-4 py-3 text-center">
              <AppBadge v-if="s.is_assigned" color="green">
                <CheckCircleIcon class="h-3 w-3 mr-1" /> Assigned
              </AppBadge>
              <AppBadge v-else color="red">
                <ExclamationCircleIcon class="h-3 w-3 mr-1" /> Unassigned
              </AppBadge>
            </td>
            <td class="px-4 py-3 text-right">
              <AppIconButton v-if="s.is_assigned && s.assignment_id" label="Remove assignment" variant="danger" @click="removeAssignment(s)">
                <TrashIcon class="h-4 w-4" />
              </AppIconButton>
            </td>
          </tr>

          <template #empty>
            <EmptyState title="No subjects match the current filter." :icon="BookOpenIcon" />
          </template>

          <template #footer>
            <div class="flex items-center justify-between px-4 py-2 bg-slate-50 border-t border-slate-100">
              <span class="text-xs text-slate-500 font-medium">Total Units (assigned)</span>
              <span class="text-xs font-semibold text-slate-700">{{ assignedTotalUnits }}</span>
            </div>
          </template>
        </AppTable>
      </AppCard>

      <!-- ── Students ──────────────────────────────────────────────── -->
      <AppCard :padded="false">
        <div class="px-5 py-3.5 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3">
          <h2 class="text-sm font-semibold text-slate-700 flex items-center gap-2">
            <UsersIcon class="h-4 w-4 text-success-500" /> Students
            <span class="text-xs font-normal text-slate-400">({{ filteredStudents.length }})</span>
          </h2>
          <input v-model="studentSearch" type="search" placeholder="Search name / LRN / ID..."
            class="text-xs border border-slate-200 rounded-lg px-3 py-1.5 w-48 focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
        </div>

        <AppTable :card="false" :is-empty="students.length === 0">
          <template #head>
            <tr>
              <th class="px-4 py-2 text-left text-xs text-slate-400 uppercase tracking-wide w-8">#</th>
              <th class="px-4 py-2 text-left text-xs text-slate-400 uppercase tracking-wide">Name</th>
              <th class="px-4 py-2 text-left text-xs text-slate-400 uppercase tracking-wide">LRN</th>
              <th class="px-4 py-2 text-left text-xs text-slate-400 uppercase tracking-wide">System ID</th>
              <th class="px-4 py-2 text-center text-xs text-slate-400 uppercase tracking-wide">Sex</th>
              <th class="px-4 py-2 text-left text-xs text-slate-400 uppercase tracking-wide">Email</th>
              <th class="px-4 py-2 text-center text-xs text-slate-400 uppercase tracking-wide">Status</th>
            </tr>
          </template>

          <tr v-for="(st, i) in displayedStudents" :key="st.id" class="hover:bg-slate-50/50">
            <td class="px-4 py-2.5 text-slate-400 text-xs">{{ (currentPage - 1) * PER_PAGE + i + 1 }}</td>
            <td class="px-4 py-2.5 font-medium text-slate-800">{{ st.full_name }}</td>
            <td class="px-4 py-2.5 font-mono text-xs text-slate-500">{{ st.lrn ?? '—' }}</td>
            <td class="px-4 py-2.5 font-mono text-xs text-slate-500">{{ st.system_id ?? '—' }}</td>
            <td class="px-4 py-2.5 text-center text-slate-600 text-xs">{{ st.sex ?? '—' }}</td>
            <td class="px-4 py-2.5 text-slate-500 text-xs">{{ st.email ?? '—' }}</td>
            <td class="px-4 py-2.5 text-center">
              <AppBadge :color="!st.status || st.status === 'active' ? 'green' : 'slate'" class="capitalize">
                {{ st.status ?? 'active' }}
              </AppBadge>
            </td>
          </tr>

          <template #empty>
            <EmptyState title="No students enrolled in this section." :icon="UsersIcon" />
          </template>

          <template #footer>
            <PaginationControl
              v-if="totalPages > 1"
              :current-page="currentPage"
              :total-pages="totalPages"
              :total="filteredStudents.length"
              @prev="currentPage--"
              @next="currentPage++"
              @page="currentPage = $event"
            />
          </template>
        </AppTable>
      </AppCard>

    </div>
  </AdminLayout>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppCard from '@/Components/AppCard.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppTable from '@/Components/AppTable.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import EmptyState from '@/Components/EmptyState.vue'
import PaginationControl from '@/Components/PaginationControl.vue'
import ScheduleCalendarCard from '@/Components/FacultyLoading/ScheduleCalendarCard.vue'
import { confirmDelete } from '@/Composables/useConfirm.js'
import {
  AcademicCapIcon,
  ArrowLeftIcon,
  BookOpenIcon,
  CheckCircleIcon,
  ClockIcon,
  ExclamationCircleIcon,
  ExclamationTriangleIcon,
  TrashIcon,
  UsersIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  section:    { type: Object, default: () => ({}) },
  terms:      { type: Array,  default: () => [] },
  termId:     { type: Number, default: null },
  subjects:   { type: Array,  default: () => [] },
  students:   { type: Array,  default: () => [] },
  schedule:   { type: Array,  default: () => [] },
  dayConfigs: { type: Object, default: () => ({}) },
})

// ── Weekly Schedule (read-only) ─────────────────────────────────────────────

const WEEKDAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday']

/** { Monday: [schedule, ...], ... } — faculty name as the secondary label,
 *  since the section itself is already this whole card's context. */
const scheduleByDay = computed(() => {
  const map = {}
  for (const day of WEEKDAYS) map[day] = []
  for (const s of props.schedule) {
    if (!map[s.day_of_week]) map[s.day_of_week] = []
    map[s.day_of_week].push({
      ...s,
      secondary_label: s.entry_type === 'non_teaching' ? '' : (s.faculty?.name ?? 'TBA'),
    })
  }
  return map
})

const scheduleLegend = computed(() => {
  const seen = new Map()
  for (const s of props.schedule) {
    if (s.subject && !seen.has(s.subject.id)) seen.set(s.subject.id, s.subject)
  }
  return [...seen.values()]
})

// ── Term filter ───────────────────────────────────────────────────────────────
const selectedTermId = ref(props.termId)

function applyTermFilter() {
  router.get(
    route('faculty-loading.sections.show', props.section.id),
    { term_id: selectedTermId.value },
    { preserveState: true }
  )
}

// ── Subjects ──────────────────────────────────────────────────────────────────
const subjectFilter = ref('all')

const filteredSubjects = computed(() => {
  if (subjectFilter.value === 'assigned')   return props.subjects.filter(s => s.is_assigned)
  if (subjectFilter.value === 'unassigned') return props.subjects.filter(s => !s.is_assigned)
  return props.subjects
})

const assignedCount     = computed(() => props.subjects.filter(s => s.is_assigned).length)
const unassignedCount   = computed(() => props.subjects.filter(s => !s.is_assigned).length)
const assignedTotalUnits = computed(() =>
  props.subjects
    .filter(s => s.is_assigned)
    .reduce((sum, s) => sum + (s.load_units || 0), 0)
    .toFixed(1)
)

async function removeAssignment(s) {
  if (! await confirmDelete(`Remove "${s.subject_name}" assignment from ${s.faculty_name}?`)) return
  useForm({}).delete(route('faculty-loading.assignments.destroy', s.assignment_id), {
    preserveScroll: true,
  })
}

function typeClass(type) {
  const map = {
    lecture:     'blue',
    laboratory:  'purple',
    lecture_lab: 'purple',
    elective:    'amber',
    research:    'indigo',
    special:     'red',
  }
  return map[type] ?? 'slate'
}

function formatTime(t) {
  if (!t) return '—'
  const [h, m] = t.split(':')
  const hour = parseInt(h)
  const ampm = hour >= 12 ? 'PM' : 'AM'
  const display = hour % 12 || 12
  return `${display}:${m} ${ampm}`
}

// ── Students ──────────────────────────────────────────────────────────────────
const PER_PAGE = 15
const studentSearch = ref('')
const currentPage = ref(1)

watch(studentSearch, () => { currentPage.value = 1 })

const filteredStudents = computed(() => {
  const q = studentSearch.value.trim().toLowerCase()
  if (!q) return props.students
  return props.students.filter(s =>
    s.full_name.toLowerCase().includes(q) ||
    (s.lrn ?? '').toLowerCase().includes(q) ||
    (s.system_id ?? '').toLowerCase().includes(q) ||
    (s.email ?? '').toLowerCase().includes(q)
  )
})

const totalPages = computed(() => Math.max(1, Math.ceil(filteredStudents.value.length / PER_PAGE)))

const displayedStudents = computed(() => {
  const start = (currentPage.value - 1) * PER_PAGE
  return filteredStudents.value.slice(start, start + PER_PAGE)
})
</script>
