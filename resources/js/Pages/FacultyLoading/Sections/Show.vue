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
              <span v-if="section.strand" class="mx-1 text-slate-300">·</span>
              <span v-if="section.strand">{{ section.strand }}</span>
              <span v-if="section.section_code" class="mx-1 text-slate-300">·</span>
              <span v-if="section.section_code" class="font-mono text-xs">{{ section.section_code }}</span>
            </p>
          </div>
        </div>

        <!-- Meta pills -->
        <div class="flex flex-wrap gap-2 text-xs">
          <span class="inline-flex items-center gap-1 bg-slate-100 text-slate-600 rounded-full px-3 py-1">
            <UsersIcon class="h-3.5 w-3.5" />
            {{ students.length }} student{{ students.length !== 1 ? 's' : '' }}
            <span v-if="section.capacity" class="text-slate-400">/ {{ section.capacity }}</span>
          </span>
          <span class="inline-flex items-center gap-1 bg-indigo-50 text-indigo-600 rounded-full px-3 py-1">
            <BookOpenIcon class="h-3.5 w-3.5" />
            {{ assignedCount }}/{{ subjects.length }} assigned
          </span>
          <span v-if="section.adviser" class="inline-flex items-center gap-1 bg-amber-50 text-amber-700 rounded-full px-3 py-1">
            <AcademicCapIcon class="h-3.5 w-3.5" />
            {{ section.adviser.name }}
          </span>
        </div>
      </div>

      <!-- Break Times Card -->
      <div v-if="section.recess_start || section.lunch_start"
        class="bg-white rounded-xl border border-slate-100 shadow-sm px-5 py-4">
        <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3 flex items-center gap-2">
          <ClockIcon class="h-4 w-4" /> Break Times
        </h2>
        <div class="flex flex-wrap gap-6">
          <div v-if="section.recess_start" class="flex items-center gap-2">
            <span class="inline-flex items-center rounded-full bg-sky-50 text-sky-700 px-3 py-1 text-xs font-medium">
              Recess: {{ formatTime(section.recess_start) }} – {{ formatTime(section.recess_end) }}
            </span>
          </div>
          <div v-if="section.lunch_start" class="flex items-center gap-2">
            <span class="inline-flex items-center rounded-full bg-orange-50 text-orange-700 px-3 py-1 text-xs font-medium">
              Lunch: {{ formatTime(section.lunch_start) }} – {{ formatTime(section.lunch_end) }}
            </span>
          </div>
        </div>
      </div>
      <div v-else
        class="bg-amber-50 border border-amber-100 rounded-xl px-5 py-3 text-xs text-amber-700 flex items-center gap-2">
        <ExclamationTriangleIcon class="h-4 w-4 shrink-0" />
        No break times set. Schedule generation will not block recess or lunch periods for this section.
        <Link :href="route('faculty-loading.sections.index')" class="underline ml-1">Set break times</Link>
      </div>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success" class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <CheckCircleIcon class="h-4 w-4 shrink-0" />{{ $page.props.flash.success }}
      </div>

      <!-- ── Subjects (full catalog for this grade) ──────────────────── -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
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

        <div v-if="filteredSubjects.length === 0" class="py-10 text-center">
          <BookOpenIcon class="mx-auto h-10 w-10 text-slate-200 mb-2" />
          <p class="text-sm text-slate-400">No subjects match the current filter.</p>
        </div>

        <table v-else class="min-w-full divide-y divide-slate-50 text-sm">
          <thead>
            <tr class="text-xs text-slate-400 uppercase tracking-wide bg-slate-50">
              <th class="px-4 py-2 text-left">Code</th>
              <th class="px-4 py-2 text-left">Subject</th>
              <th class="px-4 py-2 text-left">Type</th>
              <th class="px-4 py-2 text-center">Units</th>
              <th class="px-4 py-2 text-left">Faculty</th>
              <th class="px-4 py-2 text-center">Status</th>
              <th class="px-4 py-2"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50">
            <tr v-for="s in filteredSubjects" :key="s.id"
              :class="s.is_assigned ? 'hover:bg-slate-50/50' : 'bg-red-50/30 hover:bg-red-50/50'">
              <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ s.subject_code ?? '—' }}</td>
              <td class="px-4 py-3 font-medium text-slate-800">
                {{ s.subject_name }}
                <span v-if="s.is_elective"
                  class="ml-1.5 text-xs font-normal text-amber-600 bg-amber-50 rounded-full px-1.5 py-0.5">elective</span>
              </td>
              <td class="px-4 py-3">
                <span :class="typeClass(s.subject_type)" class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium capitalize">
                  {{ s.subject_type ?? '—' }}
                </span>
              </td>
              <td class="px-4 py-3 text-center text-slate-600">{{ s.load_units }}</td>
              <td class="px-4 py-3 text-slate-600">{{ s.faculty_name ?? '—' }}</td>
              <td class="px-4 py-3 text-center">
                <span v-if="s.is_assigned"
                  class="inline-flex items-center gap-1 bg-emerald-50 text-emerald-700 rounded-full px-2 py-0.5 text-xs font-medium">
                  <CheckCircleIcon class="h-3 w-3" /> Assigned
                </span>
                <span v-else
                  class="inline-flex items-center gap-1 bg-red-50 text-red-600 rounded-full px-2 py-0.5 text-xs font-medium">
                  <ExclamationCircleIcon class="h-3 w-3" /> Unassigned
                </span>
              </td>
              <td class="px-4 py-3 text-right">
                <button v-if="s.is_assigned && s.assignment_id"
                  @click="removeAssignment(s)"
                  class="p-1.5 text-slate-300 hover:text-red-600 rounded transition-colors"
                  title="Remove assignment">
                  <TrashIcon class="h-4 w-4" />
                </button>
              </td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="bg-slate-50 border-t border-slate-100">
              <td colspan="3" class="px-4 py-2 text-xs text-slate-500 font-medium text-right">Total Units (assigned)</td>
              <td class="px-4 py-2 text-center text-xs font-semibold text-slate-700">{{ assignedTotalUnits }}</td>
              <td colspan="3"></td>
            </tr>
          </tfoot>
        </table>
      </div>

      <!-- ── Students ──────────────────────────────────────────────── -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-5 py-3.5 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3">
          <h2 class="text-sm font-semibold text-slate-700 flex items-center gap-2">
            <UsersIcon class="h-4 w-4 text-emerald-500" /> Students
            <span class="text-xs font-normal text-slate-400">({{ filteredStudents.length }})</span>
          </h2>
          <input v-model="studentSearch" type="search" placeholder="Search name / LRN / ID..."
            class="text-xs border border-slate-200 rounded-lg px-3 py-1.5 w-48 focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
        </div>

        <div v-if="students.length === 0" class="py-10 text-center">
          <UsersIcon class="mx-auto h-10 w-10 text-slate-200 mb-2" />
          <p class="text-sm text-slate-400">No students enrolled in this section.</p>
        </div>

        <table v-else class="min-w-full divide-y divide-slate-50 text-sm">
          <thead>
            <tr class="text-xs text-slate-400 uppercase tracking-wide bg-slate-50">
              <th class="px-4 py-2 text-left w-8">#</th>
              <th class="px-4 py-2 text-left">Name</th>
              <th class="px-4 py-2 text-left">LRN</th>
              <th class="px-4 py-2 text-left">System ID</th>
              <th class="px-4 py-2 text-center">Sex</th>
              <th class="px-4 py-2 text-left">Email</th>
              <th class="px-4 py-2 text-center">Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50">
            <tr v-for="(st, i) in displayedStudents" :key="st.id" class="hover:bg-slate-50/50">
              <td class="px-4 py-2.5 text-slate-400 text-xs">{{ (currentPage - 1) * PER_PAGE + i + 1 }}</td>
              <td class="px-4 py-2.5 font-medium text-slate-800">{{ st.full_name }}</td>
              <td class="px-4 py-2.5 font-mono text-xs text-slate-500">{{ st.lrn ?? '—' }}</td>
              <td class="px-4 py-2.5 font-mono text-xs text-slate-500">{{ st.system_id ?? '—' }}</td>
              <td class="px-4 py-2.5 text-center text-slate-600 text-xs">{{ st.sex ?? '—' }}</td>
              <td class="px-4 py-2.5 text-slate-500 text-xs">{{ st.email ?? '—' }}</td>
              <td class="px-4 py-2.5 text-center">
                <span :class="!st.status || st.status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-400'"
                  class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium capitalize">
                  {{ st.status ?? 'active' }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Pagination -->
        <div v-if="totalPages > 1" class="px-4 py-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
          <span>Showing {{ (currentPage - 1) * PER_PAGE + 1 }}–{{ Math.min(currentPage * PER_PAGE, filteredStudents.length) }} of {{ filteredStudents.length }}</span>
          <div class="flex gap-1">
            <button @click="currentPage--" :disabled="currentPage === 1"
              class="px-2 py-1 rounded border border-slate-200 disabled:opacity-40 hover:bg-slate-50">‹</button>
            <button v-for="p in totalPages" :key="p" @click="currentPage = p"
              :class="p === currentPage ? 'bg-indigo-600 text-white border-indigo-600' : 'border-slate-200 hover:bg-slate-50'"
              class="px-2.5 py-1 rounded border text-xs">{{ p }}</button>
            <button @click="currentPage++" :disabled="currentPage === totalPages"
              class="px-2 py-1 rounded border border-slate-200 disabled:opacity-40 hover:bg-slate-50">›</button>
          </div>
        </div>
      </div>

    </div>
  </AdminLayout>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
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
  section:  { type: Object, default: () => ({}) },
  terms:    { type: Array,  default: () => [] },
  termId:   { type: Number, default: null },
  subjects: { type: Array,  default: () => [] },
  students: { type: Array,  default: () => [] },
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

function removeAssignment(s) {
  if (!confirm(`Remove "${s.subject_name}" assignment from ${s.faculty_name}?`)) return
  useForm({}).delete(route('faculty-loading.assignments.destroy', s.assignment_id), {
    preserveScroll: true,
  })
}

function typeClass(type) {
  const map = {
    lecture:     'bg-blue-50 text-blue-700',
    laboratory:  'bg-violet-50 text-violet-700',
    lecture_lab: 'bg-purple-50 text-purple-700',
    elective:    'bg-amber-50 text-amber-700',
    research:    'bg-teal-50 text-teal-700',
    special:     'bg-pink-50 text-pink-700',
  }
  return map[type] ?? 'bg-slate-100 text-slate-500'
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
