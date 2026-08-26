<script setup>
import { reactive, ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppInput from '@/Components/AppInput.vue'
import AppSelect from '@/Components/AppSelect.vue'
import AppTextarea from '@/Components/AppTextarea.vue'
import AppTable from '@/Components/AppTable.vue'
import AppModal from '@/Components/AppModal.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { confirmDelete } from '@/Composables/useConfirm.js'
import { BookOpenIcon, CheckCircleIcon, DocumentDuplicateIcon, PencilIcon, PlusIcon, TrashIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  subjects:            { type: Array,  default: () => [] },
  terms:               { type: Array,  default: () => [] },
  currentTermId:       { type: Number, default: null },
  schoolYears:         { type: Array,  default: () => [] },
  currentSchoolYearId: { type: Number, default: null },
  filters:             { type: Object, default: () => ({}) },
  can:                 { type: Object, default: () => ({ manage: false }) },
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

let debounceTimer = null
function debouncedApplyFilters() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(applyFilters, 400)
}

function typeBadge(type) {
  return {
    lecture:     'blue',
    laboratory:  'purple',
    lecture_lab: 'indigo',
    research:    'green',
    elective:    'orange',
    science_core: 'purple',
  }[type] ?? 'slate'
}

const modal = ref(false)
const form  = useForm({
  id: null, school_year_id: props.currentSchoolYearId,
  code: '', name: '', description: '', specialization_tags: '',
  credit_units: 3, load_units: 3,
  lecture_hours: 3, lab_hours: 0, subject_type: 'lecture', grade_level: 7, subject_group: '',
  semester: 'both', sessions_per_week: 5, minutes_per_session: 60, is_active: true, has_ilp: false,
  requires_computer_lab: false,
})

function openForm(s = null) {
  if (s) {
    Object.assign(form, { id: s.id, school_year_id: props.currentSchoolYearId,
      code: s.code, name: s.name, description: s.description ?? '',
      specialization_tags: s.specialization_tags ?? '',
      credit_units: s.credit_units, load_units: s.load_units, lecture_hours: s.lecture_hours,
      lab_hours: s.lab_hours ?? 0, subject_type: s.subject_type, grade_level: s.grade_level,
      subject_group: s.subject_group ?? '',
      semester: s.semester, sessions_per_week: s.sessions_per_week,
      minutes_per_session: s.minutes_per_session, is_active: s.is_active, has_ilp: s.has_ilp,
      requires_computer_lab: s.requires_computer_lab })
  } else {
    form.reset()
    form.id = null
    form.school_year_id = props.currentSchoolYearId
    form.grade_level = 7; form.subject_type = 'lecture'
    form.subject_group = ''
    form.credit_units = 3; form.load_units = 3; form.lecture_hours = 3
    form.sessions_per_week = 5; form.minutes_per_session = 60; form.is_active = true
    form.has_ilp = false
    form.requires_computer_lab = false
    form.semester = 'both'
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

async function deleteSubject(s) {
  const confirmed = await confirmDelete(`Delete subject "${s.name}"? This cannot be undone.`)
  if (!confirmed) return
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

<template>
  <Head title="Subject Catalog" />
  <AdminLayout title="Subject Catalog">
    <div class="space-y-5">

      <AppPageHeader title="Subject Catalog" subtitle="Manage subjects and their load unit assignments">
        <template v-if="can.manage" #actions>
          <AppButton variant="secondary" @click="copyModal = true">
            <DocumentDuplicateIcon class="h-4 w-4" /> Copy from Year
          </AppButton>
          <AppButton @click="openForm()">
            <PlusIcon class="h-4 w-4" /> New Subject
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success" class="bg-success-50 border border-success-100 text-success-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <CheckCircleIcon class="h-4 w-4 shrink-0" />{{ $page.props.flash.success }}
      </div>
      <div v-if="$page.props.errors?.error" class="bg-danger-50 border border-danger-100 text-danger-600 rounded-lg px-4 py-3 text-sm">{{ $page.props.errors.error }}</div>

      <!-- Filters -->
      <AppFilterBar>
        <div class="w-56">
          <AppSelect :model-value="filters.school_year_id" :show-blank="false"
            @update:model-value="v => { filters.school_year_id = Number(v); applyFilters() }">
            <option v-for="sy in schoolYears" :key="sy.id" :value="sy.id">
              {{ sy.name }}{{ sy.is_current ? ' (current)' : '' }}
            </option>
          </AppSelect>
        </div>
        <div class="w-56">
          <AppInput :model-value="filters.search" type="search" placeholder="Search code or name..."
            @update:model-value="v => { filters.search = v; debouncedApplyFilters() }" />
        </div>
        <div class="w-40">
          <AppSelect :model-value="filters.grade_level" :show-blank="false"
            @update:model-value="v => { filters.grade_level = v; applyFilters() }">
            <option value="all">All Grades</option>
            <option v-for="g in [7,8,9,10,11,12]" :key="g" :value="g">Grade {{ g }}</option>
          </AppSelect>
        </div>
        <div class="w-44">
          <AppSelect :model-value="filters.subject_type" :show-blank="false"
            @update:model-value="v => { filters.subject_type = v; applyFilters() }">
            <option value="all">All Types</option>
            <option value="lecture">Lecture</option>
            <option value="laboratory">Laboratory</option>
            <option value="lecture_lab">Lecture + Lab</option>
            <option value="research">Research</option>
            <option value="elective">Elective</option>
            <option value="science_core">Science Core</option>
          </AppSelect>
        </div>
        <div class="w-56">
          <AppSelect :model-value="filters.term_id" :show-blank="false"
            @update:model-value="v => { filters.term_id = Number(v); applyFilters() }">
            <option v-for="t in terms" :key="t.id" :value="t.id">
              {{ t.label }}{{ t.is_current ? ' (current)' : '' }}
            </option>
          </AppSelect>
        </div>
      </AppFilterBar>

      <!-- Table -->
      <AppTable :is-empty="subjects.length === 0" :skeleton-cols="can.manage ? 9 : 8">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Code</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Name</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Grade</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Type</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Units</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Lec/Lab hrs</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Assigned Faculty</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Status</th>
            <th v-if="can.manage" class="px-4 py-3"></th>
          </tr>
        </template>

        <tr v-for="s in subjects" :key="s.id" class="hover:bg-indigo-50/40">
          <td class="px-4 py-3 font-mono text-xs font-semibold text-indigo-700">{{ s.code }}</td>
          <td class="px-4 py-3 text-slate-800">{{ s.name }}</td>
          <td class="px-4 py-3 text-center text-slate-600">{{ s.grade_level === 0 ? '—' : s.grade_level }}</td>
          <td class="px-4 py-3 text-center">
            <div class="flex flex-col items-center gap-1">
              <AppBadge :color="typeBadge(s.subject_type)">{{ s.subject_type }}</AppBadge>
              <AppBadge v-if="s.subject_group" color="purple">{{ s.subject_group }}</AppBadge>
            </div>
          </td>
          <td class="px-4 py-3 text-center font-semibold text-slate-700">{{ s.load_units }}</td>
          <td class="px-4 py-3 text-center text-slate-500">{{ s.lecture_hours }} / {{ s.lab_hours ?? 0 }}</td>
          <td class="px-4 py-3">
            <div v-if="s.faculty && s.faculty.length" class="flex flex-col gap-2">
              <div v-for="f in s.faculty" :key="f.id">
                <AppBadge color="indigo">{{ f.name }}</AppBadge>
                <p class="mt-0.5 text-xs text-slate-400">{{ f.email ?? '—' }} · {{ f.mobile_no ?? '—' }}</p>
              </div>
            </div>
            <span v-else class="text-xs text-slate-400 italic">Unassigned</span>
          </td>
          <td class="px-4 py-3 text-center">
            <div class="flex flex-col items-center gap-1">
              <AppBadge :color="s.is_active ? 'green' : 'slate'">{{ s.is_active ? 'Active' : 'Inactive' }}</AppBadge>
              <AppBadge v-if="s.requires_computer_lab" color="indigo">ComLab priority</AppBadge>
            </div>
          </td>
          <td v-if="can.manage" class="px-4 py-3 text-right">
            <div class="flex items-center justify-end gap-1">
              <AppIconButton label="Edit" @click="openForm(s)"><PencilIcon class="h-4 w-4" /></AppIconButton>
              <AppIconButton label="Delete" variant="danger" @click="deleteSubject(s)"><TrashIcon class="h-4 w-4" /></AppIconButton>
            </div>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="s in subjects" :key="s.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="font-mono text-xs font-semibold text-indigo-700">{{ s.code }}</p>
                <p class="font-medium text-slate-800">{{ s.name }}</p>
              </div>
              <AppBadge :color="s.is_active ? 'green' : 'slate'">{{ s.is_active ? 'Active' : 'Inactive' }}</AppBadge>
            </div>
            <div class="flex items-center gap-2">
              <AppBadge :color="typeBadge(s.subject_type)">{{ s.subject_type }}</AppBadge>
              <AppBadge v-if="s.subject_group" color="purple">{{ s.subject_group }}</AppBadge>
              <span class="text-xs text-slate-500">Grade {{ s.grade_level === 0 ? '—' : s.grade_level }}</span>
              <span class="text-xs text-slate-500">{{ s.load_units }} units</span>
            </div>
            <p class="text-xs text-slate-500">Lec/Lab: {{ s.lecture_hours }} / {{ s.lab_hours ?? 0 }}</p>
            <div v-if="s.faculty && s.faculty.length" class="flex flex-col gap-2">
              <div v-for="f in s.faculty" :key="f.id">
                <AppBadge color="indigo">{{ f.name }}</AppBadge>
                <p class="mt-0.5 text-xs text-slate-400">{{ f.email ?? '—' }} · {{ f.mobile_no ?? '—' }}</p>
              </div>
            </div>
            <span v-else class="text-xs text-slate-400 italic">Unassigned</span>
            <div v-if="can.manage" class="flex items-center gap-1 pt-1">
              <AppIconButton label="Edit" @click="openForm(s)"><PencilIcon class="h-4 w-4" /></AppIconButton>
              <AppIconButton label="Delete" variant="danger" @click="deleteSubject(s)"><TrashIcon class="h-4 w-4" /></AppIconButton>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No subjects found for this school year"
            subtitle='Use "Copy from Year" to import subjects from a previous year.' :icon="BookOpenIcon" />
        </template>
      </AppTable>

    </div>

    <!-- Subject Modal -->
    <AppModal :show="modal" :title="`${form.id ? 'Edit' : 'New'} Subject`" size="2xl" @close="modal = false">
      <form @submit.prevent="save" class="space-y-4">
        <div class="grid grid-cols-2 gap-3">
          <AppInput v-model="form.code" label="Subject Code" required :error="form.errors.code" />
          <AppInput v-model="form.name" label="Subject Name" required :error="form.errors.name" />
          <div class="col-span-2">
            <AppTextarea v-model="form.description" label="Description" :rows="2" />
          </div>
          <div class="col-span-2 rounded-lg border border-indigo-100 bg-indigo-50/60 p-3">
            <div class="flex items-center gap-2">
              <input v-model="form.requires_computer_lab" type="checkbox" id="sub-comlab"
                class="rounded text-indigo-600" />
              <label for="sub-comlab" class="text-sm font-medium text-slate-700">Priority use of a Computer Laboratory</label>
            </div>
            <p class="text-xs text-slate-500 mt-1 ml-6">The separate Computer Laboratory calendar will reserve an available lab at this subject's official class time. This does not constrain class placement.</p>
          </div>
          <div class="col-span-2">
            <AppInput v-model="form.specialization_tags" label="Specialization Tags" placeholder="e.g. mathematics, algebra, calculus" />
            <p class="text-xs text-slate-400 mt-0.5">Comma-separated keywords used for auto-assignment matching.</p>
          </div>
          <div class="col-span-2">
            <AppInput v-model="form.subject_group" label="Subject Group (optional)" placeholder="e.g. PEHM" />
            <p class="text-xs text-slate-400 mt-0.5">Subjects sharing the same group + grade level can share one class record (e.g. PEHM for PE/Health/Music).</p>
          </div>
          <AppSelect v-model="form.subject_type" label="Subject Type" required :show-blank="false">
            <option value="lecture">Lecture</option>
            <option value="laboratory">Laboratory</option>
            <option value="lecture_lab">Lecture + Lab</option>
            <option value="research">Research</option>
            <option value="elective">Elective</option>
            <option value="science_core">Science Core</option>
          </AppSelect>
          <AppSelect v-model.number="form.grade_level" label="Grade Level" :show-blank="false">
            <option :value="0">Cross-grade</option>
            <option v-for="g in [7,8,9,10,11,12]" :key="g" :value="g">Grade {{ g }}</option>
          </AppSelect>
          <AppInput v-model.number="form.credit_units" type="number" min="0" label="Credit Units" required />
          <AppInput v-model.number="form.load_units" type="number" step="0.5" min="0" label="Load Units" required />
          <AppInput v-model.number="form.lecture_hours" type="number" step="0.5" min="0" label="Lecture Hours/week" required />
          <AppInput v-model.number="form.lab_hours" type="number" step="0.5" min="0" label="Lab Hours/week" />
          <AppInput v-model.number="form.sessions_per_week" type="number" min="1" label="Sessions/week" required />
          <AppInput v-model.number="form.minutes_per_session" type="number" min="1" label="Minutes/session" required />
          <AppSelect v-model="form.semester" label="Semester" :show-blank="false">
            <option value="first">1st</option>
            <option value="second">2nd</option>
            <option value="both">Both</option>
          </AppSelect>
          <div class="flex items-center gap-2 pt-6">
            <input v-model="form.is_active" type="checkbox" id="sub-active" class="rounded text-indigo-600" />
            <label for="sub-active" class="text-sm text-slate-600">Active</label>
          </div>
          <div class="col-span-2">
            <div class="flex items-center gap-2">
              <input v-model="form.has_ilp" type="checkbox" id="sub-ilp"
                :disabled="Math.round(form.load_units) < 2" class="rounded text-indigo-600" />
              <label for="sub-ilp" class="text-sm text-slate-600">Has Independent Learning Period (30 min/week)</label>
            </div>
            <p class="text-xs text-slate-400 mt-0.5">
              One of the subject's weekly sessions is reserved as an ILP session instead of a regular class.
              <span v-if="Math.round(form.load_units) < 2">Requires at least 2 load units/sessions.</span>
            </p>
          </div>
        </div>

        <div class="flex justify-end gap-3 pt-2 border-t border-slate-100">
          <AppButton type="button" variant="secondary" @click="modal = false">Cancel</AppButton>
          <AppButton type="submit" :loading="form.processing">{{ form.id ? 'Update' : 'Create' }}</AppButton>
        </div>
      </form>
    </AppModal>

    <!-- Copy from Year Modal -->
    <AppModal :show="copyModal" title="Copy Subjects from Another Year"
      subtitle="Copies all subjects from the source year into the target year. Subjects with duplicate codes are skipped."
      @close="copyModal = false">
      <form @submit.prevent="doCopy" class="space-y-4">
        <AppSelect v-model.number="copyForm.source_school_year_id" label="Source Year (copy from)" :show-blank="false">
          <option v-for="sy in schoolYears" :key="sy.id" :value="sy.id">
            {{ sy.name }}{{ sy.is_current ? ' (current)' : '' }}
          </option>
        </AppSelect>
        <AppSelect v-model.number="copyForm.target_school_year_id" label="Target Year (copy into)" :show-blank="false"
          :error="copyForm.errors.target_school_year_id">
          <option v-for="sy in schoolYears" :key="sy.id" :value="sy.id">
            {{ sy.name }}{{ sy.is_current ? ' (current)' : '' }}
          </option>
        </AppSelect>

        <div class="flex justify-end gap-3 pt-2 border-t border-slate-100">
          <AppButton type="button" variant="secondary" @click="copyModal = false">Cancel</AppButton>
          <AppButton type="submit" :loading="copyForm.processing">Copy Subjects</AppButton>
        </div>
      </form>
    </AppModal>

  </AdminLayout>
</template>
