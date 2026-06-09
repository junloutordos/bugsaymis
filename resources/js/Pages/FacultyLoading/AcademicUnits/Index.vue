<template>
  <Head title="Academic Units" />
  <AdminLayout title="Academic Units">
    <div class="space-y-5">

      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Academic Units</h1>
          <p class="text-sm text-slate-500 mt-0.5">Manage JHS, SHS, SST and administrative units</p>
        </div>
        <div class="flex items-center gap-2">
          <button @click="copyModal = true"
            class="inline-flex items-center gap-2 px-3 py-2 text-sm border border-slate-200 bg-white hover:bg-slate-50 text-slate-600 rounded-lg font-medium transition-colors shadow-sm shrink-0">
            <DocumentDuplicateIcon class="h-4 w-4" /> Copy from Year
          </button>
          <button @click="openForm()"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-medium transition-colors shadow-sm shrink-0">
            <PlusIcon class="h-4 w-4" /> New Unit
          </button>
        </div>
      </div>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success" class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <CheckCircleIcon class="h-4 w-4 shrink-0" /> {{ $page.props.flash.success }}
      </div>
      <div v-if="$page.props.errors?.error" class="bg-red-50 border border-red-200 text-red-600 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <ExclamationCircleIcon class="h-4 w-4 shrink-0" /> {{ $page.props.errors.error }}
      </div>

      <!-- School Year picker -->
      <div class="flex items-center gap-2">
        <label class="text-xs font-medium text-slate-500 uppercase tracking-wide">School Year</label>
        <select v-model="selectedSy" @change="switchYear"
          class="text-sm border border-indigo-300 bg-indigo-50 text-indigo-700 font-medium rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
          <option v-for="sy in schoolYears" :key="sy.id" :value="sy.id">
            {{ sy.name }}{{ sy.is_current ? ' (current)' : '' }}
          </option>
        </select>
      </div>

      <!-- Table -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
        <table class="w-full text-sm">
          <thead class="bg-slate-50 border-b border-slate-100">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Code</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Name</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Type</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Head</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50">
            <tr v-if="units.length === 0">
              <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-400">
                No academic units for this school year. Use "Copy from Year" to get started.
              </td>
            </tr>
            <tr v-for="u in units" :key="u.id" class="hover:bg-slate-50/50">
              <td class="px-4 py-3 font-mono text-xs text-indigo-700 font-semibold">{{ u.code }}</td>
              <td class="px-4 py-3 font-medium text-slate-800">{{ u.name }}</td>
              <td class="px-4 py-3">
                <span class="text-xs px-2 py-0.5 rounded-full font-medium"
                  :class="typeClass(u.unit_type)">
                  {{ typeLabel(u.unit_type) }}
                </span>
              </td>
              <td class="px-4 py-3 text-slate-600">{{ u.head_name ?? '—' }}</td>
              <td class="px-4 py-3">
                <span :class="u.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'"
                  class="text-xs px-2 py-0.5 rounded-full font-medium">
                  {{ u.is_active ? 'Active' : 'Inactive' }}
                </span>
              </td>
              <td class="px-4 py-3 text-right">
                <div class="flex items-center justify-end gap-1">
                  <button @click="openForm(u)" class="p-1.5 text-slate-400 hover:text-indigo-600 rounded">
                    <PencilIcon class="h-4 w-4" />
                  </button>
                  <button @click="deleteUnit(u)" class="p-1.5 text-slate-400 hover:text-red-600 rounded">
                    <TrashIcon class="h-4 w-4" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

    </div>

    <!-- Edit/Create Modal -->
    <div v-if="modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 space-y-4">
        <h2 class="text-lg font-semibold text-slate-800">{{ form.id ? 'Edit' : 'New' }} Academic Unit</h2>
        <div class="space-y-3">
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Code <span class="text-red-500">*</span></label>
              <input v-model="form.code" type="text" placeholder="e.g. JHS"
                class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none" />
              <p v-if="form.errors.code" class="text-red-500 text-xs mt-1">{{ form.errors.code }}</p>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Type <span class="text-red-500">*</span></label>
              <select v-model="form.unit_type"
                class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                <option value="department">Department</option>
                <option value="junior_high">Junior High School</option>
                <option value="senior_high">Senior High School</option>
                <option value="sst">SST Program</option>
                <option value="admin">Administrative</option>
              </select>
            </div>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Name <span class="text-red-500">*</span></label>
            <input v-model="form.name" type="text" placeholder="e.g. Junior High School"
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none" />
            <p v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</p>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Sort Order</label>
            <input v-model="form.sort_order" type="number" min="0"
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none" />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Unit Head</label>
            <select v-model="form.head_user_id"
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
              <option :value="null">— None —</option>
              <option v-for="f in facultyOptions" :key="f.id" :value="f.id">{{ f.name }}</option>
            </select>
          </div>
          <div class="flex items-center gap-2">
            <input v-model="form.is_active" type="checkbox" id="unit-active" class="rounded text-indigo-600" />
            <label for="unit-active" class="text-sm text-slate-600">Active</label>
          </div>
        </div>
        <div class="flex justify-end gap-3 pt-1">
          <button @click="modal = false" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Cancel</button>
          <button @click="save" :disabled="form.processing"
            class="px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white rounded-lg font-medium">
            {{ form.id ? 'Update' : 'Create' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Copy from Year Modal -->
    <div v-if="copyModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 space-y-4">
        <h2 class="text-lg font-semibold text-slate-800">Copy Academic Units from Another Year</h2>
        <p class="text-sm text-slate-500">Copies all units from the source year into the target year. AUH designations are preserved. Units with duplicate codes are skipped.</p>
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
            Copy Units
          </button>
        </div>
      </div>
    </div>

  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import {
  CheckCircleIcon, DocumentDuplicateIcon, ExclamationCircleIcon,
  PencilIcon, PlusIcon, TrashIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  units:               { type: Array,  default: () => [] },
  facultyOptions:      { type: Array,  default: () => [] },
  schoolYears:         { type: Array,  default: () => [] },
  currentSchoolYearId: { type: Number, default: null },
})

const selectedSy = ref(props.currentSchoolYearId)

function switchYear() {
  router.get(route('faculty-loading.academic-units.index'), { school_year_id: selectedSy.value }, { preserveState: false })
}

const modal = ref(false)
const form  = useForm({
  id: null, school_year_id: props.currentSchoolYearId,
  code: '', name: '', unit_type: 'department', head_user_id: null, sort_order: 0, is_active: true,
})

function openForm(u = null) {
  if (u) {
    Object.assign(form, { id: u.id, school_year_id: props.currentSchoolYearId,
      code: u.code, name: u.name, unit_type: u.unit_type,
      head_user_id: u.head_user_id, sort_order: u.sort_order, is_active: u.is_active })
  } else {
    form.reset()
    form.id = null
    form.school_year_id = props.currentSchoolYearId
    form.unit_type = 'department'; form.is_active = true
  }
  modal.value = true
}

function save() {
  if (form.id) {
    form.put(route('faculty-loading.academic-units.update', form.id), { onSuccess: () => { modal.value = false } })
  } else {
    form.post(route('faculty-loading.academic-units.store'), { onSuccess: () => { modal.value = false } })
  }
}

function deleteUnit(u) {
  if (! confirm(`Delete academic unit "${u.name}"?`)) return
  useForm({}).delete(route('faculty-loading.academic-units.destroy', u.id))
}

const copyModal = ref(false)
const copyForm  = useForm({
  source_school_year_id: props.currentSchoolYearId,
  target_school_year_id: props.currentSchoolYearId,
})

function doCopy() {
  copyForm.post(route('faculty-loading.academic-units.copy-from-year'), {
    onSuccess: () => { copyModal.value = false }
  })
}

const TYPE_LABELS = {
  department:  'Dept',
  junior_high: 'Junior HS',
  senior_high: 'Senior HS',
  sst:         'SST',
  admin:       'Admin',
}
const TYPE_CLASSES = {
  department:  'bg-indigo-100 text-indigo-700',
  junior_high: 'bg-blue-100 text-blue-700',
  senior_high: 'bg-violet-100 text-violet-700',
  sst:         'bg-amber-100 text-amber-700',
  admin:       'bg-slate-100 text-slate-600',
}
const typeLabel = (t) => TYPE_LABELS[t] ?? t
const typeClass = (t) => TYPE_CLASSES[t] ?? 'bg-slate-100 text-slate-600'
</script>
