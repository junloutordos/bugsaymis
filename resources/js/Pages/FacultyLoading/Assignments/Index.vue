<template>
  <Head title="Load Assignments" />
  <AdminLayout title="Load Assignments">
    <div class="space-y-5">

      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Load Assignments</h1>
          <p class="text-sm text-slate-500 mt-0.5">Assign teaching, research, admin and co-curricular loads to faculty</p>
        </div>
        <div class="flex gap-2">
          <button @click="openAutoAssign()"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm bg-purple-50 hover:bg-purple-100 text-purple-700 border border-purple-200 rounded-lg font-medium transition-colors shrink-0">
            <SparklesIcon class="h-4 w-4" /> Auto-Assign
          </button>
          <button @click="openForm()"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-medium transition-colors shadow-sm shrink-0">
            <PlusIcon class="h-4 w-4" /> Add Assignment
          </button>
        </div>
      </div>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success" class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <CheckCircleIcon class="h-4 w-4 shrink-0" />{{ $page.props.flash.success }}
      </div>
      <div v-if="Object.keys($page.props.errors ?? {}).length" class="bg-red-50 border border-red-200 text-red-600 rounded-lg px-4 py-3 text-sm space-y-1">
        <p v-for="(msg, key) in $page.props.errors" :key="key">{{ msg }}</p>
      </div>

      <!-- Filters -->
      <div class="flex flex-wrap gap-2">
        <select v-model="filters.term_id" @change="applyFilters"
          class="text-sm border border-slate-200 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
          <option v-for="t in terms" :key="t.id" :value="t.id">
            {{ t.label }}{{ t.is_current ? ' (current)' : '' }}
          </option>
        </select>
        <select v-model="filters.faculty_id" @change="applyFilters"
          class="text-sm border border-slate-200 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
          <option :value="null">All Faculty</option>
          <option v-for="f in faculty" :key="f.id" :value="f.id">{{ f.name }}</option>
        </select>
      </div>

      <!-- Empty -->
      <div v-if="assignments.length === 0" class="bg-white rounded-xl border border-slate-100 shadow-sm py-16 text-center">
        <ClipboardDocumentListIcon class="mx-auto h-12 w-12 text-slate-200 mb-3" />
        <p class="text-sm font-medium text-slate-500">No assignments found for this term</p>
        <p class="text-xs text-slate-400 mt-1">Add a load assignment to get started.</p>
      </div>

      <!-- Table -->
      <div v-else class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Faculty</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Assignment</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Type</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Units</th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50">
            <tr v-for="a in assignments" :key="a.id" class="hover:bg-slate-50/50">
              <td class="px-4 py-3 font-medium text-slate-800">{{ a.faculty?.name ?? '—' }}</td>
              <td class="px-4 py-3 text-slate-700">{{ a.display_label }}</td>
              <td class="px-4 py-3 text-center">
                <span :class="typeBadge(a.assignment_type)"
                  class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium">
                  {{ typeLabel(a.assignment_type) }}
                </span>
              </td>
              <td class="px-4 py-3 text-center font-semibold text-slate-700">{{ a.load_units }}</td>
              <td class="px-4 py-3 text-right">
                <div class="flex items-center justify-end gap-1">
                  <button @click="openForm(a)" class="p-1.5 text-slate-400 hover:text-indigo-600 rounded">
                    <PencilIcon class="h-4 w-4" />
                  </button>
                  <button @click="remove(a)" class="p-1.5 text-slate-400 hover:text-red-600 rounded">
                    <TrashIcon class="h-4 w-4" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

    </div>

    <!-- Modal -->
    <div v-if="modal" class="fixed inset-0 z-50 flex items-start justify-center bg-black/40 backdrop-blur-sm p-4 overflow-y-auto">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg p-6 space-y-4 my-8">
        <h2 class="text-lg font-semibold text-slate-800">{{ form.id ? 'Edit' : 'Add' }} Load Assignment</h2>

        <div class="space-y-3">
          <!-- Faculty (create only) -->
          <div v-if="!form.id">
            <label class="block text-xs font-medium text-slate-600 mb-1">Faculty *</label>
            <select v-model="form.user_id" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
              <option :value="null">Select faculty...</option>
              <option v-for="f in faculty" :key="f.id" :value="f.id">{{ f.name }}</option>
            </select>
          </div>

          <!-- Term (create only) -->
          <div v-if="!form.id">
            <label class="block text-xs font-medium text-slate-600 mb-1">Academic Term *</label>
            <select v-model="form.academic_term_id" @change="onTermChange"
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
              <option :value="null">Select term...</option>
              <option v-for="t in terms" :key="t.id" :value="t.id">{{ t.label }}</option>
            </select>
          </div>

          <!-- Type -->
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Assignment Type *</label>
            <select v-model="form.assignment_type" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
              <option value="">Select type...</option>
              <option v-for="t in assignmentTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
            </select>
          </div>

          <!-- Subject (teaching only) -->
          <div v-if="form.assignment_type === 'teaching'">
            <label class="block text-xs font-medium text-slate-600 mb-1">Subject *</label>
            <select v-model="form.subject_id" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
              <option :value="null">Select subject...</option>
              <option v-for="s in subjects" :key="s.id" :value="s.id">{{ s.code }} — {{ s.name }}</option>
            </select>
          </div>

          <!-- Section (teaching only) -->
          <div v-if="form.assignment_type === 'teaching'">
            <label class="block text-xs font-medium text-slate-600 mb-1">Section</label>
            <select v-model="form.section_id" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
              <option :value="null">Select section...</option>
              <option v-for="s in sections" :key="s.id" :value="s.id">Gr {{ s.levelid }} — {{ s.sectionname }}</option>
            </select>
          </div>

          <!-- Description (non-teaching) -->
          <div v-if="form.assignment_type && form.assignment_type !== 'teaching'">
            <label class="block text-xs font-medium text-slate-600 mb-1">Description</label>
            <input v-model="form.description" type="text" placeholder="Brief description..."
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
          </div>

          <!-- Load units -->
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Load Units *</label>
            <input v-model.number="form.load_units" type="number" step="0.5" min="0.5" max="30"
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
          </div>
        </div>

        <div class="flex justify-end gap-2 pt-1">
          <button @click="modal = false" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Cancel</button>
          <button @click="save" :disabled="form.processing"
            class="px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-medium disabled:opacity-50">
            {{ form.id ? 'Update' : 'Save' }}
          </button>
        </div>
      </div>
    </div>

  <!-- ── Auto-Assign Modal ──────────────────────────────────────── -->
  <div v-if="autoAssign.open" class="fixed inset-0 z-50 flex items-start justify-center bg-black/40 backdrop-blur-sm p-4 overflow-y-auto">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl my-8 overflow-hidden">

      <!-- Header -->
      <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="h-9 w-9 rounded-full bg-purple-50 flex items-center justify-center">
            <SparklesIcon class="h-5 w-5 text-purple-500" />
          </div>
          <div>
            <h2 class="text-base font-semibold text-slate-800">Smart Auto-Assign Loads</h2>
            <p class="text-xs text-slate-500">Matches faculty to subjects by specialization · auto-assigns sections by grade level · balances section load.</p>
          </div>
        </div>
        <button @click="autoAssign.open = false" class="p-1.5 text-slate-400 hover:text-slate-700 rounded-lg hover:bg-slate-100">✕</button>
      </div>

      <!-- Controls -->
      <div class="px-6 py-4 border-b border-slate-100 flex flex-wrap gap-3 items-end">
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Academic Term</label>
          <select v-model="autoAssign.termId" @change="autoAssign.proposals = null; autoAssign.selected = []"
            class="text-sm border border-slate-200 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-purple-500 focus:border-transparent">
            <option v-for="t in terms" :key="t.id" :value="t.id">{{ t.label }}{{ t.is_current ? ' (current)' : '' }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Faculty (optional)</label>
          <select v-model="autoAssign.facultyId"
            class="text-sm border border-slate-200 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-purple-500 focus:border-transparent">
            <option :value="null">All Faculty</option>
            <option v-for="f in faculty" :key="f.id" :value="f.id">{{ f.name }}</option>
          </select>
        </div>
        <button @click="runPreview" :disabled="autoAssign.loading"
          class="inline-flex items-center gap-2 px-4 py-2 text-sm bg-purple-600 hover:bg-purple-700 disabled:opacity-50 text-white rounded-lg font-medium transition-colors">
          <ArrowPathIcon v-if="autoAssign.loading" class="h-4 w-4 animate-spin" />
          <MagnifyingGlassIcon v-else class="h-4 w-4" />
          {{ autoAssign.loading ? 'Generating…' : 'Generate Preview' }}
        </button>
      </div>

      <!-- Alert -->
      <div v-if="autoAssign.error" class="mx-6 mt-4 bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
        {{ autoAssign.error }}
      </div>

      <!-- Summary bar -->
      <div v-if="autoAssign.proposals" class="px-6 py-3 bg-purple-50 border-b border-purple-100 flex flex-wrap gap-4 text-xs text-purple-700">
        <span><strong>{{ autoAssign.proposals.filter(p => p.assignments.length).length }}</strong> faculty matched</span>
        <span><strong>{{ allProposedItems.length }}</strong> assignments proposed</span>
        <span><strong>{{ allProposedItems.filter(a => a.section_id).length }}</strong> with auto-assigned section</span>
        <span><strong>{{ allProposedItems.filter(a => !a.section_id).length }}</strong> without section</span>
      </div>

      <!-- Preview results -->
      <div v-if="autoAssign.proposals" class="px-6 py-4 space-y-3 max-h-[52vh] overflow-y-auto">
        <div v-if="autoAssign.proposals.length === 0" class="text-center py-8 text-slate-400 text-sm">
          No faculty found for the selected term.
        </div>

        <div v-for="p in autoAssign.proposals" :key="p.faculty_id"
          class="border rounded-xl overflow-hidden"
          :class="p.assignments.length ? 'border-purple-100' : 'border-slate-100'">

          <!-- Faculty header -->
          <div class="px-4 py-2.5 flex items-center justify-between"
            :class="p.assignments.length ? 'bg-purple-50' : 'bg-slate-50'">
            <div class="flex items-center gap-2 flex-wrap">
              <span class="text-sm font-semibold text-slate-800">{{ p.faculty_name }}</span>
              <span v-if="p.position" class="text-xs text-slate-500">{{ p.position }}</span>
              <span v-if="p.specialization"
                class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-purple-100 text-purple-700 text-xs">
                <AcademicCapIcon class="h-3 w-3" /> {{ p.specialization }}
              </span>
            </div>
            <div class="shrink-0 text-xs text-slate-500 space-x-2">
              <span>Cap: <strong>{{ p.teaching_cap }}u</strong></span>
              <span>Already: <strong>{{ p.already_assigned }}u</strong></span>
              <span v-if="p.assignments.length" class="text-purple-700">
                +<strong>{{ p.assignments.reduce((s, a) => s + a.load_units, 0).toFixed(1) }}u proposed</strong>
              </span>
            </div>
          </div>

          <!-- No matches -->
          <div v-if="!p.assignments.length" class="px-4 py-2 text-xs text-slate-400 italic">
            {{ p.skipped_reason ?? 'No matching subjects' }}
          </div>

          <!-- Proposed assignments table -->
          <table v-else class="w-full text-sm divide-y divide-slate-50">
            <thead class="bg-white">
              <tr class="text-left text-xs text-slate-400 font-medium">
                <th class="px-4 py-1.5 w-6"></th>
                <th class="px-2 py-1.5">Subject</th>
                <th class="px-2 py-1.5 text-center">Grade</th>
                <th class="px-2 py-1.5">Section</th>
                <th class="px-2 py-1.5 text-center">Match</th>
                <th class="px-2 py-1.5 text-right">Units</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
              <tr v-for="a in p.assignments" :key="a.subject_id" class="hover:bg-slate-50">
                <!-- Checkbox -->
                <td class="px-4 py-2">
                  <input type="checkbox" :value="buildSelected(p.faculty_id, a)"
                    v-model="autoAssign.selected"
                    class="rounded text-purple-600 focus:ring-purple-500" />
                </td>
                <!-- Subject -->
                <td class="px-2 py-2">
                  <span class="font-medium text-slate-700">{{ a.subject_code }}</span>
                  <span class="text-slate-500 ml-1">{{ a.subject_name }}</span>
                </td>
                <!-- Grade -->
                <td class="px-2 py-2 text-center text-slate-500">
                  {{ a.grade_level || '—' }}
                </td>
                <!-- Section: auto-assigned + override -->
                <td class="px-2 py-2">
                  <div class="flex items-center gap-1.5">
                    <span v-if="a.section_auto"
                      class="inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold bg-emerald-100 text-emerald-700 shrink-0">AUTO</span>
                    <span v-else
                      class="inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-100 text-amber-700 shrink-0">NONE</span>
                    <select v-model="a.section_id"
                      class="text-xs border border-slate-200 rounded px-1.5 py-0.5 focus:ring-1 focus:ring-purple-400 max-w-[160px]">
                      <option :value="null">— No section —</option>
                      <optgroup v-for="grade in sectionGrades(autoAssign.sections)" :key="grade" :label="`Grade ${grade}`">
                        <option
                          v-for="s in autoAssign.sections.filter(s => s.grade === grade)"
                          :key="s.id" :value="s.id">
                          {{ s.label }}
                        </option>
                      </optgroup>
                    </select>
                  </div>
                </td>
                <!-- Match score -->
                <td class="px-2 py-2 text-center">
                  <span :class="scoreClass(a.match_score)"
                    class="inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold">
                    {{ scoreLabel(a.match_score) }}
                  </span>
                </td>
                <!-- Units -->
                <td class="px-2 py-2 text-right font-semibold text-purple-700">{{ a.load_units }}u</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Select/deselect all -->
        <div v-if="allProposedItems.length" class="flex items-center gap-3 pt-1 text-xs text-slate-500">
          <button @click="selectAllProposed" class="underline hover:text-slate-700">Select all</button>
          <button @click="autoAssign.selected = []" class="underline hover:text-slate-700">Deselect all</button>
          <span>{{ autoAssign.selected.length }} of {{ allProposedItems.length }} selected</span>
        </div>
      </div>

      <!-- Footer -->
      <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-between">
        <p class="text-xs text-slate-400">
          Sections are auto-assigned by grade level. You can override any section above before applying.
        </p>
        <div class="flex gap-3">
          <button @click="autoAssign.open = false" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800 font-medium">Cancel</button>
          <button @click="applyAutoAssign" :disabled="!autoAssign.selected.length || autoAssign.applying"
            class="inline-flex items-center gap-2 px-5 py-2 text-sm bg-purple-600 hover:bg-purple-700 disabled:opacity-50 text-white rounded-lg font-medium transition-colors">
            <ArrowPathIcon v-if="autoAssign.applying" class="h-4 w-4 animate-spin" />
            <CheckIcon v-else class="h-4 w-4" />
            Apply {{ autoAssign.selected.length ? `(${autoAssign.selected.length})` : '' }}
          </button>
        </div>
      </div>

    </div>
  </div>

  </AdminLayout>
</template>

<script setup>
import { reactive, ref, computed } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import axios from 'axios'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import {
  AcademicCapIcon, ArrowPathIcon, CheckCircleIcon, CheckIcon, ClipboardDocumentListIcon,
  MagnifyingGlassIcon, PencilIcon, PlusIcon, SparklesIcon, TrashIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  assignments: { type: Array,  default: () => [] },
  terms:       { type: Array,  default: () => [] },
  faculty:     { type: Array,  default: () => [] },
  subjects:    { type: Array,  default: () => [] },
  sections:    { type: Array,  default: () => [] },
  currentTerm: { type: Object, default: null },
  filters:     { type: Object, default: () => ({}) },
})

const assignmentTypes = [
  { value: 'teaching',     label: 'Teaching' },
  { value: 'research',     label: 'Research' },
  { value: 'admin',        label: 'Administrative' },
  { value: 'cocurricular', label: 'Co-curricular' },
  { value: 'committee',    label: 'Committee' },
]

const filters = reactive({
  term_id:    props.filters.term_id    ?? props.currentTerm?.id ?? null,
  faculty_id: props.filters.faculty_id ?? null,
})

function applyFilters() {
  router.get(route('faculty-loading.assignments.index'), filters, { preserveState: true })
}

function onTermChange() {
  const t = props.terms.find(t => t.id === Number(form.academic_term_id))
  form.school_year_id = t?.school_year_id ?? null
}

const modal = ref(false)
const form = useForm({
  id: null,
  user_id: null,
  school_year_id: null,
  academic_term_id: null,
  assignment_type: '',
  subject_id: null,
  section_id: null,
  load_units: 3,
  description: '',
})

function openForm(a = null) {
  if (a) {
    Object.assign(form, {
      id: a.id,
      user_id: null,
      school_year_id: null,
      academic_term_id: null,
      assignment_type: a.assignment_type,
      subject_id: a.subject?.id ?? null,
      section_id: a.section_id,
      load_units: a.load_units,
      description: a.description ?? '',
    })
  } else {
    form.reset()
    form.id = null
    form.academic_term_id = filters.term_id ? Number(filters.term_id) : null
    form.load_units = 3
    // Pre-derive school_year_id from the pre-selected term
    const t = props.terms.find(t => t.id === form.academic_term_id)
    form.school_year_id = t?.school_year_id ?? null
  }
  modal.value = true
}

function save() {
  if (form.id) {
    form.put(route('faculty-loading.assignments.update', form.id), {
      onSuccess: () => { modal.value = false },
    })
  } else {
    // Always re-derive school_year_id from the selected term before submitting
    const t = props.terms.find(t => t.id === Number(form.academic_term_id))
    if (t) form.school_year_id = t.school_year_id
    form.post(route('faculty-loading.assignments.store'), {
      onSuccess: () => { modal.value = false },
    })
  }
}

function remove(a) {
  if (!confirm(`Remove "${a.display_label}" assignment?`)) return
  useForm({}).delete(route('faculty-loading.assignments.destroy', a.id))
}

function typeBadge(type) {
  return {
    teaching:     'bg-indigo-50 text-indigo-700',
    research:     'bg-violet-50 text-violet-700',
    admin:        'bg-blue-50 text-blue-700',
    cocurricular: 'bg-teal-50 text-teal-700',
    committee:    'bg-orange-50 text-orange-700',
  }[type] ?? 'bg-slate-50 text-slate-600'
}

function typeLabel(type) {
  return assignmentTypes.find(t => t.value === type)?.label ?? type
}

// ── Auto-Assign ───────────────────────────────────────────────────────────────

const autoAssign = reactive({
  open:      false,
  termId:    props.filters.term_id ? Number(props.filters.term_id) : (props.currentTerm?.id ?? null),
  facultyId: null,
  loading:   false,
  applying:  false,
  error:     null,
  proposals: null,
  sections:  [],   // available sections returned from preview (for override dropdowns)
  selected:  [],
})

// Flat list of all proposed items across all faculty (used for select-all and count)
const allProposedItems = computed(() => {
  if (!autoAssign.proposals) return []
  return autoAssign.proposals.flatMap(p =>
    p.assignments.map(a => ({ faculty_id: p.faculty_id, ...a }))
  )
})

function openAutoAssign() {
  autoAssign.proposals = null
  autoAssign.sections  = []
  autoAssign.selected  = []
  autoAssign.error     = null
  autoAssign.open      = true
}

function selectAllProposed() {
  autoAssign.selected = allProposedItems.value.map(item => buildSelected(item.faculty_id, item))
}

/**
 * Build a selected-item snapshot that captures the current section_id from the
 * reactive assignment object (so overrides are included when applying).
 */
function buildSelected(facultyId, a) {
  return {
    faculty_id:   facultyId,
    subject_id:   a.subject_id,
    subject_code: a.subject_code,
    subject_name: a.subject_name,
    load_units:   a.load_units,
    grade_level:  a.grade_level,
    match_score:  a.match_score,
    section_id:   a.section_id,
  }
}

async function runPreview() {
  if (!autoAssign.termId || autoAssign.loading) return
  autoAssign.loading   = true
  autoAssign.error     = null
  autoAssign.proposals = null
  autoAssign.sections  = []
  autoAssign.selected  = []

  try {
    const { data } = await axios.get('/faculty-loading/auto-assign/preview', {
      params: { academic_term_id: autoAssign.termId, faculty_id: autoAssign.facultyId || undefined },
    })
    autoAssign.proposals = data.proposals
    autoAssign.sections  = data.sections ?? []
  } catch (err) {
    autoAssign.error = err.response?.data?.message ?? 'Failed to generate preview.'
  } finally {
    autoAssign.loading = false
  }
}

async function applyAutoAssign() {
  if (!autoAssign.selected.length || autoAssign.applying) return
  autoAssign.applying = true
  autoAssign.error    = null

  // Re-snapshot section_id from the live assignment objects before submitting
  // (user may have overridden sections after selecting)
  const payload = autoAssign.selected.map(sel => {
    const proposal = autoAssign.proposals?.find(p => p.faculty_id === sel.faculty_id)
    const liveAssignment = proposal?.assignments?.find(a => a.subject_id === sel.subject_id)
    return { ...sel, section_id: liveAssignment?.section_id ?? sel.section_id }
  })

  try {
    const { data } = await axios.post('/faculty-loading/auto-assign/apply', {
      academic_term_id: autoAssign.termId,
      selected:         payload,
    })
    autoAssign.open = false
    router.reload({ only: ['assignments'] })
    alert(data.message)
  } catch (err) {
    autoAssign.error = err.response?.data?.message ?? 'Failed to apply assignments.'
  } finally {
    autoAssign.applying = false
  }
}

// ── Auto-assign UI helpers ────────────────────────────────────────────────────

/** Unique sorted grade levels from the sections list (for optgroup labels). */
function sectionGrades(sectionList) {
  return [...new Set((sectionList ?? []).map(s => s.grade))].sort((a, b) => a - b)
}

/** CSS class for the match-score badge. */
function scoreClass(score) {
  if (score >= 6) return 'bg-emerald-100 text-emerald-700'
  if (score >= 3) return 'bg-blue-100 text-blue-700'
  return 'bg-amber-100 text-amber-700'
}

/** Human-readable label for match score. */
function scoreLabel(score) {
  if (score >= 6) return '★ High'
  if (score >= 3) return '◆ Med'
  return '◇ Low'
}
</script>
