<script setup>
import { ref, computed } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { badgeBase } from '@/Composables/useStatusBadge.js'
import Swal from 'sweetalert2'
import { PlusIcon, ArrowRightIcon, Cog6ToothIcon, TrashIcon, BoltIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  classRecords:      Array,
  gradingOptions:    Array,
  isAdmin:           { type: Boolean, default: false },
  currentSchoolYear: { type: String, default: null },
})

// ── Search ────────────────────────────────────────────────────────────────────
const searchQuery = ref('')
const filtered = computed(() => {
  const q = searchQuery.value.toLowerCase().trim()
  if (!q) return props.classRecords
  return props.classRecords.filter(r =>
    r.subject_name?.toLowerCase().includes(q) ||
    r.year_level_section?.toLowerCase().includes(q) ||
    r.school_year?.toLowerCase().includes(q) ||
    r.teacher?.name?.toLowerCase().includes(q)
  )
})

// ── Status badge ──────────────────────────────────────────────────────────────
function statusBadge(status) {
  return {
    draft:     'bg-slate-100 text-slate-600',
    submitted: 'bg-blue-100 text-blue-700',
    checked:   'bg-emerald-100 text-emerald-700',
  }[status] ?? 'bg-slate-100 text-slate-600'
}

// ── Quarter progress dots ──────────────────────────────────────────────────────
function quarterDot(record, q) {
  const quarter = record.quarters?.find(qt => qt.quarter === q)
  if (!quarter)          return 'bg-slate-200 text-slate-400'
  if (quarter.is_locked) return 'bg-emerald-500 text-white'
  return 'bg-amber-400 text-white'
}

// ── Create modal ──────────────────────────────────────────────────────────────
const showModal       = ref(false)
const creating        = ref(false)
const createErrors    = ref({})
const selectedLoad    = ref(null)   // the chosen load assignment object
const gradingOptionId = ref('')

// Teaching load list
const myLoad       = ref([])
const loadFetching = ref(false)
const loadSyName   = ref(null)

async function fetchMyLoad() {
  loadFetching.value = true
  try {
    const { data } = await axios.get(route('class-records.my-teaching-load'))
    myLoad.value    = data.assignments ?? []
    loadSyName.value = data.school_year ?? null
  } catch {
    myLoad.value = []
  } finally {
    loadFetching.value = false
  }
}

function openCreate() {
  selectedLoad.value    = null
  gradingOptionId.value = ''
  createErrors.value    = {}
  showModal.value       = true
  fetchMyLoad()
}

function pickLoad(assignment) {
  // Deselect if clicking the same one
  if (selectedLoad.value?.load_assignment_id === assignment.load_assignment_id) {
    selectedLoad.value = null
  } else {
    selectedLoad.value = assignment
  }
}

async function handleCreate() {
  createErrors.value = {}

  if (!selectedLoad.value) {
    createErrors.value = { assignment: ['Please select a teaching assignment.'] }
    return
  }
  if (!gradingOptionId.value) {
    createErrors.value = { grading_option_id: ['Please select a grading option.'] }
    return
  }

  creating.value = true
  try {
    const payload = {
      subject_id:        selectedLoad.value.subject_id,
      section_id:        selectedLoad.value.section_id,
      grading_option_id: gradingOptionId.value,
    }
    // Admins creating on behalf of another teacher
    if (props.isAdmin && selectedLoad.value.teacher_id) {
      payload.teacher_id = selectedLoad.value.teacher_id
    }

    const { data } = await axios.post(route('class-records.store'), payload)
    showModal.value = false
    router.visit(route('class-records.page.show', data.data.id))
  } catch (err) {
    if (err.response?.status === 422) {
      createErrors.value = err.response.data.errors ?? {}
    } else {
      Swal.fire('Error', err.response?.data?.message ?? 'Could not create class record.', 'error')
    }
  } finally {
    creating.value = false
  }
}

function navigateTo(record) {
  router.visit(route('class-records.page.show', record.id))
}

// ── Grading Option Management Modal ──────────────────────────────────────────
const showManageModal   = ref(false)
const managingOption    = ref(null)   // null = create mode, object = edit mode
const manageOptionForm  = ref({ name: '', description: '', is_active: true })
const manageCategories  = ref([])
const manageSaving      = ref(false)
const manageErrors      = ref({})

function openManageModal(option) {
  managingOption.value   = option
  manageOptionForm.value = { name: option.name, description: option.description ?? '', is_active: option.is_active }
  manageCategories.value = option.categories.map(c => ({ ...c }))
  manageErrors.value     = {}
  showManageModal.value  = true
}

function openCreateOptionModal() {
  managingOption.value   = null
  manageOptionForm.value = { name: '', description: '', is_active: true }
  manageCategories.value = [{ id: null, name: '', code: '', weight: 0, max_assessments: 1, sort_order: 1 }]
  manageErrors.value     = {}
  showManageModal.value  = true
}

async function deleteOption(opt, event) {
  event.stopPropagation()
  const result = await Swal.fire({
    title: `Delete "${opt.name}"?`,
    text: 'This cannot be undone. Will be blocked if any class record uses this option.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#ef4444',
    confirmButtonText: 'Delete',
  })
  if (!result.isConfirmed) return
  try {
    await axios.delete(route('grading-options.destroy', opt.id))
    await Swal.fire({ icon: 'success', title: 'Deleted', timer: 1000, showConfirmButton: false })
    router.reload({ only: ['gradingOptions'] })
  } catch (err) {
    Swal.fire('Cannot Delete', err.response?.data?.message ?? 'Failed to delete.', 'error')
  }
}

const weightTotal = computed(() =>
  Math.round(manageCategories.value.reduce((s, c) => s + Number(c.weight || 0), 0) * 100)
)

function addCategory() {
  const next = manageCategories.value.length + 1
  manageCategories.value.push({
    id: null, name: '', code: '', weight: 0, max_assessments: 1, sort_order: next,
  })
}

function removeCategory(idx) {
  manageCategories.value.splice(idx, 1)
  manageCategories.value.forEach((c, i) => { c.sort_order = i + 1 })
}

async function saveManageOption() {
  manageSaving.value = true
  manageErrors.value = {}

  const categories = manageCategories.value.map((c, i) => ({
    id:              c.id ?? null,
    name:            c.name,
    code:            c.code,
    weight:          Number(c.weight),
    max_assessments: Number(c.max_assessments),
    sort_order:      i + 1,
  }))

  try {
    if (managingOption.value === null) {
      // Create mode — single POST with categories included
      await axios.post(route('grading-options.store'), { ...manageOptionForm.value, categories })
      await Swal.fire({ icon: 'success', title: 'Grading option created!', timer: 1200, showConfirmButton: false })
    } else {
      // Edit mode — PUT meta then PUT categories
      await axios.put(route('grading-options.update', managingOption.value.id), manageOptionForm.value)
      await axios.put(route('grading-options.categories.update', managingOption.value.id), { categories })
      await Swal.fire({ icon: 'success', title: 'Grading option updated!', timer: 1200, showConfirmButton: false })
    }
    showManageModal.value = false
    router.reload({ only: ['gradingOptions'] })
  } catch (err) {
    if (err.response?.status === 422) {
      manageErrors.value = err.response.data.errors ?? {}
      const msg = err.response.data.message ?? 'Please fix the errors below.'
      Swal.fire('Validation Error', msg, 'warning')
    } else {
      Swal.fire('Error', err.response?.data?.message ?? 'Failed to save.', 'error')
    }
  } finally {
    manageSaving.value = false
  }
}

</script>

<template>
  <Head title="Class Records" />
  <AdminLayout title="Class Records">
    <div class="space-y-5">

      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Class Records</h1>
          <p class="text-sm text-slate-500 mt-0.5">Manage grade class records per subject and section</p>
        </div>
        <div class="flex items-center gap-2">
          <!-- Grading options editor — admin/CID Chief only -->
          <div v-if="isAdmin" class="relative group">
            <button class="inline-flex items-center gap-1.5 border border-slate-200 bg-white hover:bg-slate-50 text-slate-600 px-3 py-2 rounded-lg text-sm font-medium transition-colors">
              <Cog6ToothIcon class="h-4 w-4" /> Grading Options
            </button>
            <!-- Dropdown of options to edit -->
            <div class="absolute right-0 mt-1 w-72 bg-white rounded-xl shadow-lg border border-slate-100 z-10 hidden group-hover:block">
              <div class="py-1">
                <button @click="openCreateOptionModal"
                  class="w-full text-left px-4 py-2.5 text-sm text-indigo-600 hover:bg-indigo-50 flex items-center gap-2 font-medium border-b border-slate-100">
                  <PlusIcon class="h-4 w-4" /> Add New Grading Option
                </button>
                <p class="px-4 py-2 text-xs text-slate-400 font-semibold uppercase tracking-wide">Edit existing</p>
                <div v-for="opt in gradingOptions" :key="opt.id"
                  class="flex items-center justify-between px-4 py-2 hover:bg-slate-50">
                  <button @click="openManageModal(opt)"
                    class="text-left text-sm text-slate-700 flex items-center gap-2 flex-1 min-w-0">
                    <span class="truncate">{{ opt.name }}</span>
                    <span v-if="!opt.is_active" class="shrink-0 text-[10px] text-slate-400 bg-slate-100 px-1.5 py-0.5 rounded">inactive</span>
                  </button>
                  <button @click="deleteOption(opt, $event)"
                    class="ml-2 p-1 rounded hover:bg-red-50 text-slate-300 hover:text-red-500 shrink-0">
                    <TrashIcon class="h-3.5 w-3.5" />
                  </button>
                </div>
              </div>
            </div>
          </div>
          <button @click="openCreate"
            class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
            <PlusIcon class="h-4 w-4" /> New Class Record
          </button>
        </div>
      </div>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success"
        class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg px-4 py-3 text-sm">
        {{ $page.props.flash.success }}
      </div>

      <!-- Table card -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <div class="px-5 py-4 border-b border-slate-100">
          <input v-model="searchQuery" type="text" placeholder="Search by subject, section, or school year…"
            class="w-full sm:w-80 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
        </div>

        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Subject</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Year Level &amp; Section</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">School Year</th>
                <th v-if="isAdmin" class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Teacher</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Grading Option</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Quarters</th>
                <th class="px-4 py-3"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-if="!filtered.length">
                <td :colspan="isAdmin ? 8 : 7" class="py-16 text-center">
                  <p class="text-slate-400 text-sm">No class records yet.</p>
                  <button @click="openCreate"
                    class="mt-3 inline-flex items-center gap-1.5 text-indigo-600 hover:text-indigo-700 text-sm font-medium">
                    <PlusIcon class="h-4 w-4" /> Create your first class record
                  </button>
                </td>
              </tr>
              <tr v-for="r in filtered" :key="r.id"
                class="hover:bg-slate-50/60 cursor-pointer"
                @click="navigateTo(r)">
                <td class="px-4 py-3 font-medium text-slate-800">{{ r.subject_name }}</td>
                <td class="px-4 py-3 text-slate-600">{{ r.year_level_section }}</td>
                <td class="px-4 py-3 text-slate-600 whitespace-nowrap">{{ r.school_year }}</td>
                <td v-if="isAdmin" class="px-4 py-3 text-slate-600">{{ r.teacher?.name ?? '—' }}</td>
                <td class="px-4 py-3 text-slate-600 text-xs">{{ r.grading_option?.name ?? '—' }}</td>
                <td class="px-4 py-3">
                  <span :class="[badgeBase, statusBadge(r.status)]">
                    {{ r.status === 'checked' ? 'Checked ✓' : r.status.charAt(0).toUpperCase() + r.status.slice(1) }}
                  </span>
                </td>
                <td class="px-4 py-3">
                  <div class="flex items-center gap-1">
                    <span v-for="q in [1,2,3,4]" :key="q"
                      :class="['inline-flex items-center justify-center h-5 w-5 rounded-full text-[9px] font-bold', quarterDot(r, q)]">
                      Q{{ q }}
                    </span>
                  </div>
                </td>
                <td class="px-4 py-3" @click.stop>
                  <button @click="navigateTo(r)"
                    class="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-700 text-xs font-medium">
                    Open <ArrowRightIcon class="h-3.5 w-3.5" />
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Create modal -->
    <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg flex flex-col max-h-[90vh]">

          <!-- Header -->
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between shrink-0">
            <div>
              <h2 class="text-base font-semibold text-slate-800">New Class Record</h2>
              <p v-if="currentSchoolYear" class="text-xs text-slate-400 mt-0.5">SY {{ currentSchoolYear }}</p>
            </div>
            <button @click="showModal = false" class="text-slate-400 hover:text-slate-600 text-xl leading-none">&times;</button>
          </div>

          <!-- Step 1: Select assignment -->
          <div class="px-6 py-4 flex-1 overflow-y-auto">
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">
              {{ isAdmin ? 'Step 1 — Select a teaching assignment' : 'Select your teaching assignment' }}
            </p>

            <!-- Loading -->
            <div v-if="loadFetching" class="flex items-center gap-2 text-sm text-slate-400 py-4">
              <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
              </svg>
              Loading assignments…
            </div>

            <!-- No assignments -->
            <div v-else-if="!myLoad.length"
                 class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-800">
              <p class="font-medium mb-1">No teaching assignments found for SY {{ loadSyName ?? currentSchoolYear ?? '—' }}.</p>
              <p class="text-xs text-amber-700">Faculty Loading for the current school year must be completed first. Contact the Academic Affairs Office.</p>
            </div>

            <!-- Assignment list -->
            <div v-else class="space-y-1.5">
              <button v-for="la in myLoad" :key="la.load_assignment_id"
                      type="button"
                      @click="pickLoad(la)"
                      class="w-full text-left rounded-xl px-4 py-3 border transition"
                      :class="selectedLoad?.load_assignment_id === la.load_assignment_id
                        ? 'border-indigo-500 bg-indigo-50 ring-2 ring-indigo-200'
                        : 'border-slate-200 hover:border-indigo-200 hover:bg-slate-50'">
                <div class="flex items-center justify-between gap-2">
                  <div class="min-w-0">
                    <p class="font-medium text-sm text-slate-800 truncate">{{ la.subject_name }}</p>
                    <p class="text-xs text-slate-500 mt-0.5">
                      G-{{ la.grade_level }} {{ la.section_name }}
                      <span v-if="isAdmin && la.teacher_name" class="ml-1 text-slate-400">· {{ la.teacher_name }}</span>
                    </p>
                  </div>
                  <div class="flex items-center gap-1.5 shrink-0">
                    <span class="rounded-full px-2 py-0.5 text-xs font-medium"
                          :class="la.subject_type === 'elective'
                            ? 'bg-amber-100 text-amber-700'
                            : 'bg-slate-100 text-slate-500'">
                      {{ la.subject_type }}
                    </span>
                    <span v-if="la.already_created"
                          class="rounded-full px-2 py-0.5 text-xs bg-green-100 text-green-600 font-medium">
                      ✓ created
                    </span>
                    <span v-if="selectedLoad?.load_assignment_id === la.load_assignment_id"
                          class="text-indigo-600">
                      <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                      </svg>
                    </span>
                  </div>
                </div>
              </button>
            </div>

            <p v-if="createErrors.assignment" class="text-xs text-red-500 mt-2">{{ createErrors.assignment[0] }}</p>

            <!-- Step 2: Grading option (only shown when assignment selected) -->
            <div v-if="selectedLoad" class="mt-5 pt-4 border-t border-slate-100">
              <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">
                Step 2 — Grading Option
              </p>

              <!-- Confirmation card -->
              <div class="rounded-xl bg-indigo-50 border border-indigo-200 px-4 py-3 mb-4 text-sm">
                <p class="font-semibold text-indigo-800">{{ selectedLoad.subject_name }}</p>
                <p class="text-indigo-600 text-xs mt-0.5">
                  G-{{ selectedLoad.grade_level }} {{ selectedLoad.section_name }}
                  <span v-if="isAdmin && selectedLoad.teacher_name"> · {{ selectedLoad.teacher_name }}</span>
                  · <span :class="selectedLoad.subject_type === 'elective' ? 'text-amber-600 font-medium' : ''">
                    {{ selectedLoad.subject_type }}
                  </span>
                </p>
              </div>

              <select v-model="gradingOptionId"
                class="w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
                :class="createErrors.grading_option_id ? 'border-red-400' : 'border-slate-200'">
                <option value="">— Select a grading option —</option>
                <option v-for="opt in gradingOptions" :key="opt.id" :value="opt.id">{{ opt.name }}</option>
              </select>
              <p v-if="createErrors.grading_option_id" class="text-xs text-red-500 mt-1">{{ createErrors.grading_option_id[0] }}</p>
            </div>
          </div>

          <!-- Footer -->
          <div class="px-6 py-4 border-t border-slate-100 flex gap-3 justify-end shrink-0">
            <button type="button" @click="showModal = false"
              class="px-4 py-2 text-sm text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50">
              Cancel
            </button>
            <button @click="handleCreate"
              :disabled="creating || !selectedLoad || !gradingOptionId || !currentSchoolYear"
              class="inline-flex items-center gap-2 px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white rounded-lg font-medium transition-colors">
              {{ creating ? 'Creating…' : 'Create Class Record' }}
            </button>
          </div>

        </div>
      </div>
    </Teleport>
    <!-- Manage Grading Option Modal -->
    <Teleport to="body">
      <div v-if="showManageModal" class="fixed inset-0 z-50 flex items-start justify-center bg-black/40 backdrop-blur-sm py-6 px-4 overflow-y-auto">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl mx-auto">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <div>
              <h2 class="text-base font-semibold text-slate-800">{{ managingOption ? 'Edit Grading Option' : 'New Grading Option' }}</h2>
              <p class="text-xs text-slate-400 mt-0.5">{{ managingOption ? 'Changes affect all future class records using this option.' : 'Define the name and grading categories.' }}</p>
            </div>
            <button @click="showManageModal = false" class="text-slate-400 hover:text-slate-600 text-xl leading-none">&times;</button>
          </div>

          <div class="px-6 py-5 space-y-5">
            <!-- Option meta -->
            <div class="grid grid-cols-2 gap-4">
              <div class="col-span-2">
                <label class="block text-xs font-medium text-slate-600 mb-1">Option Name <span class="text-red-500">*</span></label>
                <input v-model="manageOptionForm.name" type="text"
                  class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
              </div>
              <div class="col-span-2">
                <label class="block text-xs font-medium text-slate-600 mb-1">Description</label>
                <input v-model="manageOptionForm.description" type="text"
                  class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
              </div>
              <div class="col-span-2 flex items-center gap-2">
                <input type="checkbox" v-model="manageOptionForm.is_active" id="opt-active" class="rounded text-indigo-600" />
                <label for="opt-active" class="text-sm text-slate-700 cursor-pointer">Active (visible in dropdown)</label>
              </div>
            </div>

            <!-- Categories table -->
            <div>
              <div class="flex items-center justify-between mb-2">
                <p class="text-sm font-semibold text-slate-700">Categories</p>
                <span :class="['text-xs font-medium px-2 py-0.5 rounded-full',
                  weightTotal === 100 ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-600']">
                  Total: {{ weightTotal }}% {{ weightTotal === 100 ? '✓' : '≠ 100%' }}
                </span>
              </div>
              <p v-if="manageErrors.categories" class="text-xs text-red-500 mb-2">{{ manageErrors.categories[0] }}</p>

              <table class="w-full text-sm">
                <thead class="bg-slate-50">
                  <tr>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500 w-1/3">Name</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500 w-16">Code</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500 w-24">Weight %</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500 w-24">Max Items</th>
                    <th class="px-3 py-2 w-8"></th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                  <tr v-for="(cat, idx) in manageCategories" :key="idx" class="hover:bg-slate-50/50">
                    <td class="px-3 py-1.5">
                      <input v-model="cat.name" type="text" placeholder="e.g. Alternative Assessments"
                        class="w-full rounded border border-slate-200 px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400" />
                    </td>
                    <td class="px-3 py-1.5">
                      <input v-model="cat.code" type="text" maxlength="5" placeholder="AA"
                        class="w-full rounded border border-slate-200 px-2 py-1 text-sm uppercase focus:outline-none focus:ring-1 focus:ring-indigo-400" />
                    </td>
                    <td class="px-3 py-1.5">
                      <div class="flex items-center gap-1">
                        <input v-model.number="cat.weight" type="number" min="0.01" max="1" step="0.05"
                          class="w-16 rounded border border-slate-200 px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400" />
                        <span class="text-xs text-slate-400">{{ Math.round(cat.weight * 100) }}%</span>
                      </div>
                    </td>
                    <td class="px-3 py-1.5">
                      <input v-model.number="cat.max_assessments" type="number" min="1" max="20"
                        class="w-16 rounded border border-slate-200 px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400" />
                    </td>
                    <td class="px-3 py-1.5 text-center">
                      <button @click="removeCategory(idx)" class="p-1 rounded hover:bg-red-50 text-slate-300 hover:text-red-500">
                        <TrashIcon class="h-4 w-4" />
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>

              <button @click="addCategory"
                class="mt-2 inline-flex items-center gap-1.5 text-indigo-600 hover:text-indigo-700 text-xs font-medium">
                <PlusIcon class="h-4 w-4" /> Add Category
              </button>
            </div>
          </div>

          <div class="px-6 py-4 border-t border-slate-100 flex gap-3 justify-end">
            <button @click="showManageModal = false"
              class="px-4 py-2 text-sm text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50">Cancel</button>
            <button @click="saveManageOption" :disabled="manageSaving || weightTotal !== 100"
              class="inline-flex items-center gap-2 px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white rounded-lg font-medium transition-colors">
              {{ manageSaving ? 'Saving…' : (managingOption ? 'Save Changes' : 'Create Option') }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

  </AdminLayout>
</template>
