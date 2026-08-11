<script setup>
import { ref, computed } from 'vue'
import AppInput from '@/Components/AppInput.vue'
import {
  UserGroupIcon, BuildingOfficeIcon, BuildingLibraryIcon, UserIcon,
  UsersIcon, Squares2X2Icon, AcademicCapIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  offices:     { type: Array, default: () => [] },
  divisions:   { type: Array, default: () => [] },
  users:       { type: Array, default: () => [] },
  sections:    { type: Array, default: () => [] },
  gradeLevels: { type: Array, default: () => [] },
  students:    { type: Array, default: () => [] },
  modelValue:  { type: Object, required: true },
})
const emit = defineEmits(['update:modelValue'])

function update(patch) {
  emit('update:modelValue', { ...props.modelValue, ...patch })
}

const TOGGLES = [
  { key: 'all_staff',          label: 'All Staff',            sub: 'Every active employee',   icon: UserGroupIcon,      kind: 'flag' },
  { key: 'office',              label: 'By Office',            sub: 'Select offices',          icon: BuildingOfficeIcon, kind: 'list', field: 'office_ids' },
  { key: 'division',            label: 'By Division',          sub: 'Select divisions',        icon: BuildingLibraryIcon, kind: 'list', field: 'division_ids' },
  { key: 'individual_staff',    label: 'Individual Staff',     sub: 'Pick specific employees', icon: UserIcon,           kind: 'list', field: 'user_ids' },
  { key: 'all_students',        label: 'All Students',         sub: 'Every enrolled student',  icon: UsersIcon,          kind: 'flag' },
  { key: 'section',             label: 'By Section',           sub: 'Select sections',         icon: Squares2X2Icon,     kind: 'list', field: 'section_ids' },
  { key: 'grade_level',         label: 'By Grade Level',       sub: 'Select grade levels',     icon: AcademicCapIcon,    kind: 'list', field: 'grade_levels' },
  { key: 'individual_student',  label: 'Individual Students',  sub: 'Pick specific students',  icon: UserIcon,           kind: 'list', field: 'student_ids' },
]

const openPanels = ref(new Set(
  TOGGLES.filter(t => t.kind === 'flag' ? props.modelValue[t.key] : props.modelValue[t.field]?.length).map(t => t.key)
))

function isOpen(t) {
  return openPanels.value.has(t.key)
}

function toggleChip(t) {
  const next = new Set(openPanels.value)
  if (next.has(t.key)) {
    next.delete(t.key)
    if (t.kind === 'flag') update({ [t.key]: false })
    else update({ [t.field]: [] })
  } else {
    next.add(t.key)
    if (t.kind === 'flag') update({ [t.key]: true })
  }
  openPanels.value = next
}

function toggleInList(field, id) {
  const list = props.modelValue[field] ?? []
  const idx = list.indexOf(id)
  const next = idx === -1 ? [...list, id] : list.filter(x => x !== id)
  update({ [field]: next })
}

const search = ref({ office_ids: '', division_ids: '', user_ids: '', section_ids: '', student_ids: '' })

function filterList(list, q, keyFn) {
  const needle = (q || '').toLowerCase()
  return (list ?? []).filter(item => !needle || keyFn(item).toLowerCase().includes(needle))
}

const filteredOffices   = computed(() => filterList(props.offices, search.value.office_ids, o => o.name))
const filteredDivisions = computed(() => filterList(props.divisions, search.value.division_ids, d => `${d.division_name} ${d.acronym ?? ''}`))
const filteredUsers     = computed(() => filterList(props.users, search.value.user_ids, u => `${u.name} ${u.position ?? ''}`))
const filteredSections  = computed(() => filterList(props.sections, search.value.section_ids, s => s.sectionname))
const filteredStudents  = computed(() => filterList(props.students, search.value.student_ids, s => s.full_name))
</script>

<template>
  <div class="space-y-4">
    <div>
      <label class="block text-xs font-medium text-slate-600 mb-2">Who receives this issuance?</label>
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
        <button v-for="t in TOGGLES" :key="t.key" type="button"
          @click="toggleChip(t)"
          class="flex flex-col items-center gap-1 p-3 rounded-xl border text-center transition-colors"
          :class="isOpen(t) ? 'border-indigo-500 bg-indigo-50' : 'border-slate-200 hover:border-slate-300'">
          <component :is="t.icon" class="h-5 w-5" :class="isOpen(t) ? 'text-indigo-600' : 'text-slate-400'" />
          <p class="text-xs font-semibold" :class="isOpen(t) ? 'text-indigo-700' : 'text-slate-700'">{{ t.label }}</p>
          <p class="text-[10px] text-slate-400">{{ t.sub }}</p>
        </button>
      </div>
    </div>

    <div v-if="isOpen(TOGGLES[1])" class="space-y-2">
      <AppInput v-model="search.office_ids" type="text" placeholder="Search offices…" />
      <div class="max-h-48 overflow-y-auto border border-slate-200 rounded-lg divide-y divide-slate-100">
        <label v-for="o in filteredOffices" :key="o.id" class="flex items-center gap-3 px-3 py-2 hover:bg-slate-50 cursor-pointer">
          <input type="checkbox" :checked="(modelValue.office_ids ?? []).includes(o.id)"
            @change="toggleInList('office_ids', o.id)" class="rounded border-slate-300 text-indigo-600" />
          <span class="text-sm text-slate-700">{{ o.name }}</span>
        </label>
      </div>
      <p v-if="modelValue.office_ids?.length" class="text-xs text-indigo-600 font-medium">{{ modelValue.office_ids.length }} office(s) selected</p>
    </div>

    <div v-if="isOpen(TOGGLES[2])" class="space-y-2">
      <AppInput v-model="search.division_ids" type="text" placeholder="Search divisions…" />
      <div class="max-h-48 overflow-y-auto border border-slate-200 rounded-lg divide-y divide-slate-100">
        <label v-for="d in filteredDivisions" :key="d.id" class="flex items-center gap-3 px-3 py-2 hover:bg-slate-50 cursor-pointer">
          <input type="checkbox" :checked="(modelValue.division_ids ?? []).includes(d.id)"
            @change="toggleInList('division_ids', d.id)" class="rounded border-slate-300 text-indigo-600" />
          <div>
            <p class="text-sm text-slate-700">{{ d.division_name }}</p>
            <p v-if="d.acronym" class="text-xs text-slate-400">{{ d.acronym }}</p>
          </div>
        </label>
      </div>
      <p v-if="modelValue.division_ids?.length" class="text-xs text-indigo-600 font-medium">{{ modelValue.division_ids.length }} division(s) selected</p>
    </div>

    <div v-if="isOpen(TOGGLES[3])" class="space-y-2">
      <AppInput v-model="search.user_ids" type="text" placeholder="Search by name or position…" />
      <div class="max-h-48 overflow-y-auto border border-slate-200 rounded-lg divide-y divide-slate-100">
        <label v-for="u in filteredUsers.slice(0, 50)" :key="u.id" class="flex items-center gap-3 px-3 py-2 hover:bg-slate-50 cursor-pointer">
          <input type="checkbox" :checked="(modelValue.user_ids ?? []).includes(u.id)"
            @change="toggleInList('user_ids', u.id)" class="rounded border-slate-300 text-indigo-600" />
          <div>
            <p class="text-sm font-medium text-slate-700">{{ u.name }}</p>
            <p v-if="u.position" class="text-xs text-slate-400">{{ u.position }}</p>
          </div>
        </label>
      </div>
      <p v-if="modelValue.user_ids?.length" class="text-xs text-indigo-600 font-medium">{{ modelValue.user_ids.length }} person(s) selected</p>
    </div>

    <div v-if="isOpen(TOGGLES[5])" class="space-y-2">
      <AppInput v-model="search.section_ids" type="text" placeholder="Search sections…" />
      <div class="max-h-48 overflow-y-auto border border-slate-200 rounded-lg divide-y divide-slate-100">
        <label v-for="s in filteredSections" :key="s.id" class="flex items-center gap-3 px-3 py-2 hover:bg-slate-50 cursor-pointer">
          <input type="checkbox" :checked="(modelValue.section_ids ?? []).includes(s.id)"
            @change="toggleInList('section_ids', s.id)" class="rounded border-slate-300 text-indigo-600" />
          <span class="text-sm text-slate-700">{{ s.sectionname }} <span class="text-xs text-slate-400">(Grade {{ s.levelid }})</span></span>
        </label>
      </div>
      <p v-if="modelValue.section_ids?.length" class="text-xs text-indigo-600 font-medium">{{ modelValue.section_ids.length }} section(s) selected</p>
    </div>

    <div v-if="isOpen(TOGGLES[6])" class="space-y-2">
      <div class="max-h-48 overflow-y-auto border border-slate-200 rounded-lg divide-y divide-slate-100">
        <label v-for="g in gradeLevels" :key="g.grade" class="flex items-center gap-3 px-3 py-2 hover:bg-slate-50 cursor-pointer">
          <input type="checkbox" :checked="(modelValue.grade_levels ?? []).includes(g.grade)"
            @change="toggleInList('grade_levels', g.grade)" class="rounded border-slate-300 text-indigo-600" />
          <span class="text-sm text-slate-700">{{ g.label }}</span>
        </label>
      </div>
      <p v-if="modelValue.grade_levels?.length" class="text-xs text-indigo-600 font-medium">{{ modelValue.grade_levels.length }} grade level(s) selected</p>
    </div>

    <div v-if="isOpen(TOGGLES[7])" class="space-y-2">
      <AppInput v-model="search.student_ids" type="text" placeholder="Search students by name…" />
      <div class="max-h-48 overflow-y-auto border border-slate-200 rounded-lg divide-y divide-slate-100">
        <label v-for="s in filteredStudents.slice(0, 50)" :key="s.id" class="flex items-center gap-3 px-3 py-2 hover:bg-slate-50 cursor-pointer">
          <input type="checkbox" :checked="(modelValue.student_ids ?? []).includes(s.id)"
            @change="toggleInList('student_ids', s.id)" class="rounded border-slate-300 text-indigo-600" />
          <span class="text-sm text-slate-700">{{ s.full_name }} <span class="text-xs text-slate-400">(Grade {{ s.grade_level }})</span></span>
        </label>
      </div>
      <p v-if="modelValue.student_ids?.length" class="text-xs text-indigo-600 font-medium">{{ modelValue.student_ids.length }} student(s) selected</p>
    </div>
  </div>
</template>
