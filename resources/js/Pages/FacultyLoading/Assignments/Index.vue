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
        <button @click="openForm()"
          class="inline-flex items-center gap-2 px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-medium transition-colors shadow-sm shrink-0">
          <PlusIcon class="h-4 w-4" /> Add Assignment
        </button>
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
          <div v-if="!form.id" class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Academic Term *</label>
              <select v-model="form.academic_term_id" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                <option :value="null">Select term...</option>
                <option v-for="t in terms" :key="t.id" :value="t.id">{{ t.label }}</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">School Year *</label>
              <select v-model="form.school_year_id" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                <option :value="null">Select SY...</option>
                <option v-for="t in terms" :key="'sy-' + t.id" :value="termSchoolYearId(t)">{{ t.label }}</option>
              </select>
            </div>
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

  </AdminLayout>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { CheckCircleIcon, ClipboardDocumentListIcon, PencilIcon, PlusIcon, TrashIcon } from '@heroicons/vue/24/outline'

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

// Derive school_year_id from selected term (terms carry school year info via label)
function termSchoolYearId(t) {
  return t.id  // server handles resolution; we pass term id as school_year_id placeholder
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
    form.academic_term_id = filters.term_id ?? null
    form.load_units = 3
  }
  modal.value = true
}

function save() {
  if (form.id) {
    form.put(route('faculty-loading.assignments.update', form.id), {
      onSuccess: () => { modal.value = false },
    })
  } else {
    // school_year_id: derive from selected term on server if missing; pass term id
    if (!form.school_year_id && form.academic_term_id) {
      const t = props.terms.find(t => t.id === form.academic_term_id)
      form.school_year_id = t?.id ?? form.academic_term_id
    }
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
</script>
