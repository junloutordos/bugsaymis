<script setup>
import { ref, computed } from 'vue'
import AppInput from '@/Components/AppInput.vue'
import AppSelect from '@/Components/AppSelect.vue'
import AppTextarea from '@/Components/AppTextarea.vue'
import {
  UserGroupIcon, BuildingOfficeIcon, BuildingLibraryIcon, UserIcon,
  CheckCircleIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  form:             Object,
  categories:       Array,
  supersedeOptions: { type: Array, default: () => [] },
  offices:          Array,
  divisions:        Array,
  users:            Array,
  isEdit:           { type: Boolean, default: false },
  currentFileName:  { type: String, default: null },
})

// ── File upload ───────────────────────────────────────────────────────────
const newFileName = ref('')

function handleFile(e) {
  const file = e.target.files[0]
  if (!file) return
  newFileName.value = file.name
  props.form.file_name = file.name
  props.form.file_mime = file.type
  const reader = new FileReader()
  reader.onload = ev => { props.form.file_base64 = ev.target.result }
  reader.readAsDataURL(file)
  e.target.value = ''
}

// ── Recipient selection ───────────────────────────────────────────────────
const officeSearch   = ref('')
const userSearch     = ref('')
const divisionSearch = ref('')

const filteredOffices = computed(() => {
  const q = officeSearch.value.toLowerCase()
  return (props.offices ?? []).filter(o => !q || o.name.toLowerCase().includes(q))
})

const filteredUsers = computed(() => {
  const q = userSearch.value.toLowerCase()
  return (props.users ?? []).filter(u => !q || u.name.toLowerCase().includes(q))
})

const filteredDivisions = computed(() => {
  const q = divisionSearch.value.toLowerCase()
  return (props.divisions ?? []).filter(d => !q || d.division_name.toLowerCase().includes(q) || d.acronym?.toLowerCase().includes(q))
})

function toggleId(id) {
  const ids = props.form.recipient_ids
  const idx = ids.indexOf(id)
  if (idx === -1) ids.push(id)
  else ids.splice(idx, 1)
}
</script>

<template>
  <div class="space-y-4">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
      <AppSelect v-model="form.category_code" label="Category" required :error="form.errors.category_code">
        <option v-for="c in categories" :key="c.code" :value="c.code">{{ c.label }}</option>
      </AppSelect>
      <AppInput v-model="form.reference_no" label="Reference No." :error="form.errors.reference_no" placeholder="e.g. OED-MC-2026-015" />
    </div>

    <AppInput v-model="form.title" label="Title" required :error="form.errors.title" maxlength="500" />
    <AppTextarea v-model="form.description" label="Description" :error="form.errors.description" :rows="3" />

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
      <AppInput v-model="form.issued_date" type="date" label="Issued Date" required :error="form.errors.issued_date" />
      <AppInput v-model="form.effective_date" type="date" label="Effective Date" :error="form.errors.effective_date" />
    </div>

    <AppSelect v-if="supersedeOptions.length" v-model="form.supersedes_id" label="This document supersedes" :error="form.errors.supersedes_id">
      <option v-for="o in supersedeOptions" :key="o.id" :value="o.id">
        {{ o.reference_no ? o.reference_no + ' — ' : '' }}{{ o.title }}
      </option>
    </AppSelect>

    <div v-if="isEdit">
      <AppSelect v-model="form.status" label="Status" required :error="form.errors.status">
        <option value="active">Active</option>
        <option value="superseded">Superseded</option>
        <option value="archived">Archived</option>
      </AppSelect>
    </div>

    <!-- File -->
    <div>
      <label class="block text-xs font-medium text-slate-600 mb-1">
        {{ isEdit ? 'Replace File (optional)' : 'Document File' }} <span v-if="!isEdit" class="text-red-500">*</span>
      </label>
      <p v-if="isEdit && currentFileName" class="text-xs text-slate-500 mb-1.5">Current file: {{ currentFileName }}</p>
      <input type="file" accept=".pdf,.jpg,.jpeg,.png" @change="handleFile"
        class="w-full text-sm text-slate-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" />
      <p class="text-xs text-slate-400 mt-1">PDF, JPG, PNG</p>
      <div v-if="newFileName" class="flex items-center gap-2 text-xs text-emerald-600 mt-1.5">
        <CheckCircleIcon class="h-4 w-4" /> {{ newFileName }} selected
      </div>
      <p v-if="form.errors.file_base64" class="mt-1 text-xs text-red-500">{{ form.errors.file_base64 }}</p>
    </div>

    <!-- Visibility / Recipients -->
    <div>
      <label class="block text-xs font-medium text-slate-600 mb-2">Who can view this document?</label>
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <button v-for="opt in [
          { key:'all', icon: UserGroupIcon, label:'All Employees', sub:'Everyone' },
          { key:'office', icon: BuildingOfficeIcon, label:'By Office', sub:'Select offices' },
          { key:'division', icon: BuildingLibraryIcon, label:'By Division', sub:'Select divisions' },
          { key:'individual', icon: UserIcon, label:'Individual(s)', sub:'Pick specific users' },
        ]" :key="opt.key" type="button"
          @click="form.recipient_type = opt.key"
          class="flex flex-col items-center gap-1 p-3 rounded-xl border text-center transition-colors"
          :class="form.recipient_type === opt.key ? 'border-indigo-500 bg-indigo-50' : 'border-slate-200 hover:border-slate-300'">
          <component :is="opt.icon" class="h-5 w-5" :class="form.recipient_type === opt.key ? 'text-indigo-600' : 'text-slate-400'" />
          <p class="text-xs font-semibold" :class="form.recipient_type === opt.key ? 'text-indigo-700' : 'text-slate-700'">{{ opt.label }}</p>
          <p class="text-[10px] text-slate-400">{{ opt.sub }}</p>
        </button>
      </div>

      <!-- Office selection -->
      <div v-if="form.recipient_type === 'office'" class="space-y-2 mt-3">
        <input v-model="officeSearch" type="text" placeholder="Search offices…"
          class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        <div class="max-h-48 overflow-y-auto border border-slate-200 rounded-lg divide-y divide-slate-100">
          <label v-for="o in filteredOffices" :key="o.id" class="flex items-center gap-3 px-3 py-2 hover:bg-slate-50 cursor-pointer">
            <input type="checkbox" :checked="form.recipient_ids.includes(o.id)" @change="toggleId(o.id)" class="rounded border-slate-300 text-indigo-600" />
            <span class="text-sm text-slate-700">{{ o.name }}</span>
          </label>
        </div>
        <p v-if="form.recipient_ids.length" class="text-xs text-indigo-600 font-medium">{{ form.recipient_ids.length }} office(s) selected</p>
      </div>

      <!-- Division selection -->
      <div v-if="form.recipient_type === 'division'" class="space-y-2 mt-3">
        <input v-model="divisionSearch" type="text" placeholder="Search divisions…"
          class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        <div class="max-h-48 overflow-y-auto border border-slate-200 rounded-lg divide-y divide-slate-100">
          <label v-for="d in filteredDivisions" :key="d.id" class="flex items-center gap-3 px-3 py-2 hover:bg-slate-50 cursor-pointer">
            <input type="checkbox" :checked="form.recipient_ids.includes(d.id)" @change="toggleId(d.id)" class="rounded border-slate-300 text-indigo-600" />
            <div>
              <p class="text-sm text-slate-700">{{ d.division_name }}</p>
              <p v-if="d.acronym" class="text-xs text-slate-400">{{ d.acronym }}</p>
            </div>
          </label>
        </div>
        <p v-if="form.recipient_ids.length" class="text-xs text-indigo-600 font-medium">{{ form.recipient_ids.length }} division(s) selected</p>
      </div>

      <!-- Individual selection -->
      <div v-if="form.recipient_type === 'individual'" class="space-y-2 mt-3">
        <input v-model="userSearch" type="text" placeholder="Search by name…"
          class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        <div class="max-h-48 overflow-y-auto border border-slate-200 rounded-lg divide-y divide-slate-100">
          <label v-for="u in filteredUsers.slice(0, 50)" :key="u.id" class="flex items-center gap-3 px-3 py-2 hover:bg-slate-50 cursor-pointer">
            <input type="checkbox" :checked="form.recipient_ids.includes(u.id)" @change="toggleId(u.id)" class="rounded border-slate-300 text-indigo-600" />
            <span class="text-sm font-medium text-slate-700">{{ u.name }}</span>
          </label>
        </div>
        <p v-if="form.recipient_ids.length" class="text-xs text-indigo-600 font-medium">{{ form.recipient_ids.length }} person(s) selected</p>
      </div>

      <p v-if="form.errors.recipient_ids" class="mt-1 text-xs text-red-500">{{ form.errors.recipient_ids }}</p>
    </div>
  </div>
</template>
