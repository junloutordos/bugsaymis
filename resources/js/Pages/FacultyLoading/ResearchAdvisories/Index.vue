<template>
  <Head title="Research Advisories" />
  <AdminLayout title="Research Advisories">
    <div class="space-y-5">

      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Research Advisories</h1>
          <p class="text-sm text-slate-500 mt-0.5">Assign student research advisory loads (max {{ MAX_RESEARCH }} units per term)</p>
        </div>
        <button @click="openForm()"
          class="inline-flex items-center gap-2 px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-medium transition-colors shadow-sm shrink-0">
          <PlusIcon class="h-4 w-4" /> Add Advisory
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
      <div v-if="advisories.length === 0" class="bg-white rounded-xl border border-slate-100 shadow-sm py-16 text-center">
        <BeakerIcon class="mx-auto h-12 w-12 text-slate-200 mb-3" />
        <p class="text-sm font-medium text-slate-500">No research advisories for this term</p>
      </div>

      <!-- Table -->
      <div v-else class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Faculty</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Student</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Research Title</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Grade</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Type</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Units</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50">
            <tr v-for="r in advisories" :key="r.id" class="hover:bg-slate-50/50">
              <td class="px-4 py-3 font-medium text-slate-800">{{ r.faculty?.name ?? '—' }}</td>
              <td class="px-4 py-3 text-slate-700">{{ r.student_name }}</td>
              <td class="px-4 py-3 text-slate-600 max-w-xs truncate">{{ r.research_title }}</td>
              <td class="px-4 py-3 text-center text-slate-600">{{ r.grade_level }}</td>
              <td class="px-4 py-3 text-center">
                <span :class="typeBadge(r.advisory_type)"
                  class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium">
                  {{ typeLabel(r.advisory_type) }}
                </span>
              </td>
              <td class="px-4 py-3 text-center font-semibold text-slate-700">{{ r.load_units }}</td>
              <td class="px-4 py-3 text-center">
                <span :class="statusBadge(r.status)"
                  class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium">
                  {{ r.status }}
                </span>
              </td>
              <td class="px-4 py-3 text-right">
                <div class="flex items-center justify-end gap-1">
                  <button @click="openForm(r)" class="p-1.5 text-slate-400 hover:text-indigo-600 rounded">
                    <PencilIcon class="h-4 w-4" />
                  </button>
                  <button @click="remove(r)" class="p-1.5 text-slate-400 hover:text-red-600 rounded">
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
        <h2 class="text-lg font-semibold text-slate-800">{{ form.id ? 'Edit' : 'Add' }} Research Advisory</h2>

        <div class="grid grid-cols-2 gap-3">
          <!-- Faculty + term (create only) -->
          <template v-if="!form.id">
            <div class="col-span-2">
              <label class="block text-xs font-medium text-slate-600 mb-1">Faculty *</label>
              <select v-model="form.user_id" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                <option :value="null">Select faculty...</option>
                <option v-for="f in faculty" :key="f.id" :value="f.id">{{ f.name }}</option>
              </select>
            </div>
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
                <option v-for="t in terms" :key="'sy-' + t.id" :value="t.id">{{ t.label }}</option>
              </select>
            </div>
          </template>

          <div class="col-span-2">
            <label class="block text-xs font-medium text-slate-600 mb-1">Student Name *</label>
            <input v-model="form.student_name" type="text"
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
          </div>

          <div class="col-span-2">
            <label class="block text-xs font-medium text-slate-600 mb-1">Research Title *</label>
            <input v-model="form.research_title" type="text"
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Grade Level *</label>
            <select v-model.number="form.grade_level" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
              <option v-for="g in [7,8,9,10,11,12]" :key="g" :value="g">Grade {{ g }}</option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Advisory Type *</label>
            <select v-model="form.advisory_type" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
              <option v-for="t in advisoryTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Load Units *</label>
            <input v-model.number="form.load_units" type="number" step="0.5" min="0.5" max="5"
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
          </div>

          <div v-if="form.id">
            <label class="block text-xs font-medium text-slate-600 mb-1">Status</label>
            <select v-model="form.status" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
              <option value="completed">Completed</option>
            </select>
          </div>

          <div class="col-span-2">
            <label class="block text-xs font-medium text-slate-600 mb-1">Remarks</label>
            <textarea v-model="form.remarks" rows="2"
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent resize-none" />
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
import { BeakerIcon, CheckCircleIcon, PencilIcon, PlusIcon, TrashIcon } from '@heroicons/vue/24/outline'

const MAX_RESEARCH = 5

const props = defineProps({
  advisories:  { type: Array,  default: () => [] },
  terms:       { type: Array,  default: () => [] },
  faculty:     { type: Array,  default: () => [] },
  currentTerm: { type: Object, default: null },
  filters:     { type: Object, default: () => ({}) },
})

const advisoryTypes = [
  { value: 'thesis',           label: 'Thesis' },
  { value: 'investigatory',    label: 'Investigatory Project' },
  { value: 'science_research', label: 'Science Research' },
  { value: 'feasibility',      label: 'Feasibility Study' },
]

const filters = reactive({
  term_id:    props.filters.term_id    ?? props.currentTerm?.id ?? null,
  faculty_id: props.filters.faculty_id ?? null,
})

function applyFilters() {
  router.get(route('faculty-loading.research-advisories.index'), filters, { preserveState: true })
}

const modal = ref(false)
const form = useForm({
  id: null,
  user_id: null,
  school_year_id: null,
  academic_term_id: null,
  student_name: '',
  research_title: '',
  grade_level: 7,
  advisory_type: 'science_research',
  load_units: 0.5,
  status: 'active',
  remarks: '',
})

function openForm(r = null) {
  if (r) {
    Object.assign(form, {
      id: r.id,
      user_id: null, school_year_id: null, academic_term_id: null,
      student_name: r.student_name,
      research_title: r.research_title,
      grade_level: r.grade_level,
      advisory_type: r.advisory_type,
      load_units: r.load_units,
      status: r.status,
      remarks: r.remarks ?? '',
    })
  } else {
    form.reset()
    form.id = null
    form.advisory_type = 'science_research'
    form.grade_level = 7
    form.load_units = 0.5
    form.status = 'active'
    form.academic_term_id = filters.term_id ?? null
  }
  modal.value = true
}

function save() {
  if (form.id) {
    form.put(route('faculty-loading.research-advisories.update', form.id), {
      onSuccess: () => { modal.value = false },
    })
  } else {
    form.post(route('faculty-loading.research-advisories.store'), {
      onSuccess: () => { modal.value = false },
    })
  }
}

function remove(r) {
  if (!confirm(`Remove advisory for "${r.student_name}"?`)) return
  useForm({}).delete(route('faculty-loading.research-advisories.destroy', r.id))
}

function typeBadge(type) {
  return {
    thesis:           'bg-violet-50 text-violet-700',
    investigatory:    'bg-blue-50 text-blue-700',
    science_research: 'bg-indigo-50 text-indigo-700',
    feasibility:      'bg-teal-50 text-teal-700',
  }[type] ?? 'bg-slate-50 text-slate-600'
}

function typeLabel(type) {
  return advisoryTypes.find(t => t.value === type)?.label ?? type
}

function statusBadge(status) {
  return {
    active:    'bg-emerald-50 text-emerald-700',
    inactive:  'bg-slate-100 text-slate-500',
    completed: 'bg-blue-50 text-blue-700',
  }[status] ?? 'bg-slate-50 text-slate-600'
}
</script>
