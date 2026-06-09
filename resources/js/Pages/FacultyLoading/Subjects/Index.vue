<template>
  <Head title="Subject Catalog" />
  <AdminLayout title="Subject Catalog">
    <div class="space-y-5">

      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Subject Catalog</h1>
          <p class="text-sm text-slate-500 mt-0.5">Manage subjects and their load unit assignments</p>
        </div>
        <div class="flex items-center gap-2">
          <button @click="copyModal = true"
            class="inline-flex items-center gap-2 px-3 py-2 text-sm border border-slate-200 bg-white hover:bg-slate-50 text-slate-600 rounded-lg font-medium transition-colors shadow-sm shrink-0">
            <DocumentDuplicateIcon class="h-4 w-4" /> Copy from Year
          </button>
          <button @click="openForm()"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-medium transition-colors shadow-sm shrink-0">
            <PlusIcon class="h-4 w-4" /> New Subject
          </button>
        </div>
      </div>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success" class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <CheckCircleIcon class="h-4 w-4 shrink-0" />{{ $page.props.flash.success }}
      </div>
      <div v-if="$page.props.errors?.error" class="bg-red-50 border border-red-200 text-red-600 rounded-lg px-4 py-3 text-sm">{{ $page.props.errors.error }}</div>

      <!-- Filters -->
      <div class="flex flex-wrap gap-2">
        <!-- School year picker -->
        <select v-model="filters.school_year_id" @change="applyFilters"
          class="text-sm border border-indigo-300 bg-indigo-50 text-indigo-700 font-medium rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
          <option v-for="sy in schoolYears" :key="sy.id" :value="sy.id">
            {{ sy.name }}{{ sy.is_current ? ' (current)' : '' }}
          </option>
        </select>
        <input v-model="filters.search" @input="applyFilters" type="search" placeholder="Search code or name..."
          class="text-sm border border-slate-200 rounded-lg px-3 py-1.5 w-56 focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
        <select v-model="filters.grade_level" @change="applyFilters"
          class="text-sm border border-slate-200 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
          <option value="all">All Grades</option>
          <option v-for="g in [7,8,9,10,11,12]" :key="g" :value="g">Grade {{ g }}</option>
        </select>
        <select v-model="filters.subject_type" @change="applyFilters"
          class="text-sm border border-slate-200 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
          <option value="all">All Types</option>
          <option value="lecture">Lecture</option>
          <option value="laboratory">Laboratory</option>
          <option value="lecture_lab">Lecture + Lab</option>
          <option value="research">Research</option>
          <option value="special">Special</option>
        </select>
        <select v-model="filters.term_id" @change="applyFilters"
          class="text-sm border border-slate-200 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
          <option v-for="t in terms" :key="t.id" :value="t.id">
            {{ t.label }}{{ t.is_current ? ' (current)' : '' }}
          </option>
        </select>
      </div>

      <!-- Empty -->
      <div v-if="subjects.length === 0" class="bg-white rounded-xl border border-slate-100 shadow-sm py-16 text-center">
        <BookOpenIcon class="mx-auto h-12 w-12 text-slate-200 mb-3" />
        <p class="text-sm font-medium text-slate-500">No subjects found for this school year</p>
        <p class="text-xs text-slate-400 mt-1">Use "Copy from Year" to import subjects from a previous year.</p>
      </div>

      <!-- Table -->
      <div v-else class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Code</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Name</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Grade</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Type</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Units</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Lec/Lab hrs</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Assigned Faculty</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50">
            <tr v-for="s in subjects" :key="s.id" class="hover:bg-slate-50/50">
              <td class="px-4 py-3 font-mono text-xs font-semibold text-indigo-700">{{ s.code }}</td>
              <td class="px-4 py-3 text-slate-800">{{ s.name }}</td>
              <td class="px-4 py-3 text-center text-slate-600">{{ s.grade_level === 0 ? '—' : s.grade_level }}</td>
              <td class="px-4 py-3 text-center">
                <span :class="typeBadge(s.subject_type)"
                  class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium">
                  {{ s.subject_type }}
                </span>
              </td>
              <td class="px-4 py-3 text-center font-semibold text-slate-700">{{ s.load_units }}</td>
              <td class="px-4 py-3 text-center text-slate-500">{{ s.lecture_hours }} / {{ s.lab_hours ?? 0 }}</td>
              <td class="px-4 py-3">
                <div v-if="s.faculty && s.faculty.length" class="flex flex-wrap gap-1">
                  <span v-for="f in s.faculty" :key="f.id"
                    class="inline-flex items-center rounded-full bg-indigo-50 text-indigo-700 px-2 py-0.5 text-xs font-medium">
                    {{ f.name }}
                  </span>
                </div>
                <span v-else class="text-xs text-slate-400 italic">Unassigned</span>
              </td>
              <td class="px-4 py-3 text-center">
                <span :class="s.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-400'"
                  class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium">
                  {{ s.is_active ? 'Active' : 'Inactive' }}
                </span>
              </td>
              <td class="px-4 py-3 text-right">
                <div class="flex items-center justify-end gap-1">
                  <button @click="openForm(s)" class="p-1.5 text-slate-400 hover:text-indigo-600 rounded">
                    <PencilIcon class="h-4 w-4" />
                  </button>
                  <button @click="deleteSubject(s)" class="p-1.5 text-slate-400 hover:text-red-600 rounded">
                    <TrashIcon class="h-4 w-4" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

    </div>

    <!-- Subject Modal -->
    <div v-if="modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4 overflow-y-auto">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg p-6 space-y-4 my-8">
        <h2 class="text-lg font-semibold text-slate-800">{{ form.id ? 'Edit' : 'New' }} Subject</h2>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Subject Code *</label>
            <input v-model="form.code" type="text" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
            <p v-if="form.errors.code" class="text-xs text-red-500 mt-0.5">{{ form.errors.code }}</p>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Subject Name *</label>
            <input v-model="form.name" type="text" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
          </div>
          <div class="col-span-2">
            <label class="block text-xs font-medium text-slate-600 mb-1">Description</label>
            <textarea v-model="form.description" rows="2" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent resize-none" />
          </div>
          <div class="col-span-2">
            <label class="block text-xs font-medium text-slate-600 mb-1">Specialization Tags</label>
            <input v-model="form.specialization_tags" type="text" placeholder="e.g. mathematics, algebra, calculus"
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
            <p class="text-xs text-slate-400 mt-0.5">Comma-separated keywords used for auto-assignment matching.</p>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Subject Type *</label>
            <select v-model="form.subject_type" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
              <option value="lecture">Lecture</option>
              <option value="laboratory">Laboratory</option>
              <option value="lecture_lab">Lecture + Lab</option>
              <option value="research">Research</option>
              <option value="special">Special</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Grade Level</label>
            <select v-model="form.grade_level" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
              <option :value="0">Cross-grade</option>
              <option v-for="g in [7,8,9,10,11,12]" :key="g" :value="g">Grade {{ g }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Credit Units *</label>
            <input v-model.number="form.credit_units" type="number" min="0" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Load Units *</label>
            <input v-model.number="form.load_units" type="number" step="0.5" min="0" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Lecture Hours/week *</label>
            <input v-model.number="form.lecture_hours" type="number" step="0.5" min="0" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Lab Hours/week</label>
            <input v-model.number="form.lab_hours" type="number" step="0.5" min="0" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Sessions/week *</label>
            <input v-model.number="form.sessions_per_week" type="number" min="1" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Minutes/session *</label>
            <input v-model.number="form.minutes_per_session" type="number" min="1" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Semester</label>
            <select v-model="form.semester" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
              <option :value="null">Any</option>
              <option value="1st">1st</option>
              <option value="2nd">2nd</option>
              <option value="both">Both</option>
            </select>
          </div>
          <div class="flex items-center gap-2 pt-4">
            <input v-model="form.is_active" type="checkbox" id="sub-active" class="rounded text-indigo-600" />
            <label for="sub-active" class="text-sm text-slate-600">Active</label>
          </div>
        </div>
        <div class="flex justify-end gap-3 pt-1">
          <button @click="modal = false" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Cancel</button>
          <button @click="save" :disabled="form.processing"
            class="px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-medium disabled:opacity-50">
            {{ form.id ? 'Update' : 'Create' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Copy from Year Modal -->
    <div v-if="copyModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 space-y-4">
        <h2 class="text-lg font-semibold text-slate-800">Copy Subjects from Another Year</h2>
        <p class="text-sm text-slate-500">Copies all subjects from the source year into the target year. Subjects with duplicate codes are skipped.</p>
        <div class="space-y-3">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Source Year (copy from)</label>
            <select v-model="copyForm.source_school_year_id" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
              <option v-for="sy in schoolYears" :key="sy.id" :value="sy.id">
                {{ sy.name }}{{ sy.is_current ? ' (current)' : '' }}
              </option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Target Year (copy into)</label>
            <select v-model="copyForm.target_school_year_id" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
              <option v-for="sy in schoolYears" :key="sy.id" :value="sy.id">
                {{ sy.name }}{{ sy.is_current ? ' (current)' : '' }}
              </option>
            </select>
          </div>
          <p v-if="copyForm.errors.target_school_year_id" class="text-xs text-red-500">{{ copyForm.errors.target_school_year_id }}</p>
        </div>
        <div class="flex justify-end gap-3 pt-1">
          <button @click="copyModal = false" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Cancel</button>
          <button @click="doCopy" :disabled="copyForm.processing"
            class="px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-medium disabled:opacity-50">
            Copy Subjects
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
import { BookOpenIcon, CheckCircleIcon, DocumentDuplicateIcon, PencilIcon, PlusIcon, TrashIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  subjects:            { type: Array,  default: () => [] },
  terms:               { type: Array,  default: () => [] },
  currentTermId:       { type: Number, default: null },
  schoolYears:         { type: Array,  default: () => [] },
  currentSchoolYearId: { type: Number, default: null },
  filters:             { type: Object, default: () => ({}) },
})

const filters = reactive({
  search:          props.filters.search          ?? '',
  grade_level:     props.filters.grade_level     ?? 'all',
  subject_type:    props.filters.subject_type    ?? 'all',
  term_id:         props.filters.term_id         ?? props.currentTermId,
  school_year_id:  props.filters.school_year_id  ?? props.currentSchoolYearId,
})

function applyFilters() {
  router.get(route('faculty-loading.subjects.index'), filters, { preserveState: true })
}

function typeBadge(type) {
  return {
    lecture:     'bg-blue-50 text-blue-700',
    laboratory:  'bg-violet-50 text-violet-700',
    lecture_lab: 'bg-indigo-50 text-indigo-700',
    research:    'bg-emerald-50 text-emerald-700',
    special:     'bg-orange-50 text-orange-700',
  }[type] ?? 'bg-slate-50 text-slate-600'
}

const modal = ref(false)
const form  = useForm({
  id: null, school_year_id: props.currentSchoolYearId,
  code: '', name: '', description: '', specialization_tags: '',
  credit_units: 3, load_units: 3,
  lecture_hours: 3, lab_hours: 0, subject_type: 'lecture', grade_level: 7,
  semester: null, sessions_per_week: 5, minutes_per_session: 60, is_active: true,
})

function openForm(s = null) {
  if (s) {
    Object.assign(form, { id: s.id, school_year_id: props.currentSchoolYearId,
      code: s.code, name: s.name, description: s.description ?? '',
      specialization_tags: s.specialization_tags ?? '',
      credit_units: s.credit_units, load_units: s.load_units, lecture_hours: s.lecture_hours,
      lab_hours: s.lab_hours ?? 0, subject_type: s.subject_type, grade_level: s.grade_level,
      semester: s.semester, sessions_per_week: s.sessions_per_week,
      minutes_per_session: s.minutes_per_session, is_active: s.is_active })
  } else {
    form.reset()
    form.id = null
    form.school_year_id = props.currentSchoolYearId
    form.grade_level = 7; form.subject_type = 'lecture'
    form.credit_units = 3; form.load_units = 3; form.lecture_hours = 3
    form.sessions_per_week = 5; form.minutes_per_session = 60; form.is_active = true
  }
  modal.value = true
}

function save() {
  if (form.id) {
    form.put(route('faculty-loading.subjects.update', form.id), { onSuccess: () => { modal.value = false } })
  } else {
    form.post(route('faculty-loading.subjects.store'), { onSuccess: () => { modal.value = false } })
  }
}

function deleteSubject(s) {
  if (! confirm(`Delete subject "${s.name}"?`)) return
  useForm({}).delete(route('faculty-loading.subjects.destroy', s.id))
}

const copyModal = ref(false)
const copyForm  = useForm({
  source_school_year_id: props.currentSchoolYearId,
  target_school_year_id: props.currentSchoolYearId,
})

function doCopy() {
  copyForm.post(route('faculty-loading.subjects.copy-from-year'), {
    onSuccess: () => { copyModal.value = false }
  })
}
</script>
