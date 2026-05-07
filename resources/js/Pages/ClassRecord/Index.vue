<script setup>
import { ref, computed } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { badgeBase } from '@/Composables/useStatusBadge.js'
import Swal from 'sweetalert2'
import { PlusIcon, ArrowRightIcon, Cog6ToothIcon, TrashIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  classRecords:   Array,
  gradingOptions: Array,
  isAdmin:        { type: Boolean, default: false },
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
const showModal    = ref(false)
const creating     = ref(false)
const createErrors = ref({})
const form = ref({
  subject_name:       '',
  year_level_section: '',
  school_year:        '',
  grading_option_id:  '',
})

function openCreate() {
  form.value = { subject_name: '', year_level_section: '', school_year: '', grading_option_id: '' }
  createErrors.value = {}
  showModal.value = true
}

async function handleCreate() {
  creating.value     = true
  createErrors.value = {}
  try {
    const { data } = await axios.post(route('class-records.store'), form.value)
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
const managingOption    = ref(null)   // the option currently being edited
const manageOptionForm  = ref({ name: '', description: '', is_active: true })
const manageCategories  = ref([])
const manageSaving      = ref(false)
const manageErrors      = ref({})

function openManageModal(option) {
  managingOption.value   = option
  manageOptionForm.value = { name: option.name, description: option.description ?? '', is_active: option.is_active }
  manageCategories.value = option.categories.map(c => ({ ...c }))  // shallow copy
  manageErrors.value     = {}
  showManageModal.value  = true
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

  try {
    // Save option meta
    await axios.put(route('grading-options.update', managingOption.value.id), manageOptionForm.value)

    // Save categories
    const payload = manageCategories.value.map((c, i) => ({
      id:              c.id ?? null,
      name:            c.name,
      code:            c.code,
      weight:          Number(c.weight),
      max_assessments: Number(c.max_assessments),
      sort_order:      i + 1,
    }))
    await axios.put(route('grading-options.categories.update', managingOption.value.id), { categories: payload })

    showManageModal.value = false
    await Swal.fire({ icon: 'success', title: 'Grading option updated!', timer: 1200, showConfirmButton: false })
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

const selectedOption = computed(() =>
  props.gradingOptions?.find(o => o.id == form.value.grading_option_id) ?? null
)
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
            <div class="absolute right-0 mt-1 w-64 bg-white rounded-xl shadow-lg border border-slate-100 z-10 hidden group-hover:block">
              <div class="py-1">
                <p class="px-4 py-2 text-xs text-slate-400 font-semibold uppercase tracking-wide">Edit an option</p>
                <button v-for="opt in gradingOptions" :key="opt.id"
                  @click="openManageModal(opt)"
                  class="w-full text-left px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 flex items-center justify-between">
                  <span>{{ opt.name }}</span>
                  <span v-if="!opt.is_active" class="text-[10px] text-slate-400 bg-slate-100 px-1.5 py-0.5 rounded">inactive</span>
                </button>
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
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-800">New Class Record</h2>
            <button @click="showModal = false" class="text-slate-400 hover:text-slate-600 text-xl leading-none">&times;</button>
          </div>

          <form @submit.prevent="handleCreate" class="px-6 py-5 space-y-4">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Subject Name <span class="text-red-500">*</span></label>
              <input v-model="form.subject_name" type="text" placeholder="e.g. Chemistry" required
                class="w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
                :class="createErrors.subject_name ? 'border-red-400' : 'border-slate-200'" />
              <p v-if="createErrors.subject_name" class="text-xs text-red-500 mt-1">{{ createErrors.subject_name[0] }}</p>
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Year Level &amp; Section <span class="text-red-500">*</span></label>
              <input v-model="form.year_level_section" type="text" placeholder="e.g. G-10 Graviton" required
                class="w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
                :class="createErrors.year_level_section ? 'border-red-400' : 'border-slate-200'" />
              <p v-if="createErrors.year_level_section" class="text-xs text-red-500 mt-1">{{ createErrors.year_level_section[0] }}</p>
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">School Year <span class="text-red-500">*</span></label>
              <input v-model="form.school_year" type="text" placeholder="e.g. 2025-2026" required
                class="w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
                :class="createErrors.school_year ? 'border-red-400' : 'border-slate-200'" />
              <p v-if="createErrors.school_year" class="text-xs text-red-500 mt-1">{{ createErrors.school_year[0] }}</p>
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Grading Option <span class="text-red-500">*</span></label>
              <select v-model="form.grading_option_id" required
                class="w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
                :class="createErrors.grading_option_id ? 'border-red-400' : 'border-slate-200'">
                <option value="">— Select a grading option —</option>
                <option v-for="opt in gradingOptions" :key="opt.id" :value="opt.id">{{ opt.name }}</option>
              </select>
              <p v-if="selectedOption?.description" class="text-xs text-slate-400 mt-1">{{ selectedOption.description }}</p>
              <p v-if="createErrors.grading_option_id" class="text-xs text-red-500 mt-1">{{ createErrors.grading_option_id[0] }}</p>
            </div>

            <div class="flex gap-3 justify-end pt-2">
              <button type="button" @click="showModal = false"
                class="px-4 py-2 text-sm text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50">Cancel</button>
              <button type="submit" :disabled="creating"
                class="inline-flex items-center gap-2 px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white rounded-lg font-medium transition-colors">
                {{ creating ? 'Creating…' : 'Create Class Record' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>
    <!-- Manage Grading Option Modal -->
    <Teleport to="body">
      <div v-if="showManageModal" class="fixed inset-0 z-50 flex items-start justify-center bg-black/40 backdrop-blur-sm py-6 px-4 overflow-y-auto">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl mx-auto">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <div>
              <h2 class="text-base font-semibold text-slate-800">Edit Grading Option</h2>
              <p class="text-xs text-slate-400 mt-0.5">Changes affect all future class records using this option.</p>
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
              {{ manageSaving ? 'Saving…' : 'Save Changes' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

  </AdminLayout>
</template>
