<template>
  <Head title="Designations" />
  <AdminLayout title="Designations">
    <div class="space-y-5">

      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Designations</h1>
          <p class="text-sm text-slate-500 mt-0.5">Manage designations and assign faculty holders per term</p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
          <!-- Term filter -->
          <select v-model="selectedTermId" @change="applyTermFilter"
            class="text-sm border border-slate-200 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            <option v-for="t in terms" :key="t.id" :value="t.id">
              {{ t.label }}{{ t.is_current ? ' (current)' : '' }}
            </option>
          </select>
          <button @click="openCatForm()"
            class="inline-flex items-center gap-2 px-3 py-2 text-sm border border-indigo-300 text-indigo-700 hover:bg-indigo-50 rounded-lg font-medium transition-colors">
            <TagIcon class="h-4 w-4" /> New Category
          </button>
          <button @click="openDesigForm()"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-medium transition-colors shadow-sm">
            <PlusIcon class="h-4 w-4" /> New Designation
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

      <!-- Categories + designations -->
      <div v-if="categories.length === 0" class="bg-white rounded-xl border border-slate-100 shadow-sm py-14 text-center text-sm text-slate-400">
        No categories yet. Add one to get started.
      </div>

      <div v-for="cat in categories" :key="cat.id" class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
        <!-- Category header -->
        <div class="flex items-center justify-between px-5 py-3 bg-slate-50 border-b border-slate-100">
          <div class="flex items-center gap-3">
            <span class="font-mono text-xs text-indigo-700 font-bold bg-indigo-50 px-2 py-0.5 rounded">{{ cat.code }}</span>
            <span class="font-semibold text-slate-800 text-sm">{{ cat.name }}</span>
            <span :class="cat.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'"
              class="text-xs px-2 py-0.5 rounded-full font-medium">
              {{ cat.is_active ? 'Active' : 'Inactive' }}
            </span>
          </div>
          <div class="flex items-center gap-1">
            <button @click="openDesigForm(null, cat)" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium flex items-center gap-1 px-2 py-1">
              <PlusIcon class="h-3.5 w-3.5" /> Add
            </button>
            <button @click="openCatForm(cat)" class="p-1.5 text-slate-400 hover:text-indigo-600 rounded">
              <PencilIcon class="h-4 w-4" />
            </button>
            <button @click="deleteCat(cat)" class="p-1.5 text-slate-400 hover:text-red-600 rounded">
              <TrashIcon class="h-4 w-4" />
            </button>
          </div>
        </div>

        <!-- Designations table -->
        <table class="w-full text-sm">
          <thead class="border-b border-slate-50">
            <tr class="text-xs text-slate-400 font-medium uppercase tracking-wide">
              <th class="px-5 py-2 text-left">Code</th>
              <th class="px-5 py-2 text-left">Name</th>
              <th class="px-5 py-2 text-center">Units</th>
              <th class="px-5 py-2 text-center">Max</th>
              <th class="px-5 py-2 text-left">Current Holders</th>
              <th class="px-5 py-2 text-center">Status</th>
              <th class="px-5 py-2"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50">
            <tr v-if="cat.designations.length === 0">
              <td colspan="7" class="px-5 py-4 text-xs text-slate-400 italic">No designations in this category yet.</td>
            </tr>
            <tr v-for="d in cat.designations" :key="d.id" class="hover:bg-slate-50/50">
              <td class="px-5 py-3 font-mono text-xs text-slate-600">{{ d.code }}</td>
              <td class="px-5 py-3">
                <div class="font-medium text-slate-800">{{ d.name }}</div>
                <div v-if="d.requires_unit" class="text-[10px] text-amber-600 font-medium mt-0.5">Unit-scoped</div>
              </td>
              <td class="px-5 py-3 text-center text-slate-700 font-semibold">{{ d.load_units }}</td>
              <td class="px-5 py-3 text-center text-slate-500">{{ d.max_holders ?? '∞' }}</td>

              <!-- Current Holders -->
              <td class="px-5 py-3">
                <div v-if="d.holders.length" class="flex flex-wrap gap-1.5">
                  <span v-for="h in d.holders" :key="h.assignment_id"
                    class="inline-flex items-center gap-1 pl-2 pr-1 py-0.5 rounded-full text-xs font-medium bg-indigo-50 text-indigo-700 border border-indigo-100">
                    {{ h.user_name }}
                    <button v-if="!isAuhDesignation(d)" @click="revokeHolder(h)" class="hover:text-red-600 transition-colors ml-0.5 rounded-full p-0.5 hover:bg-red-50" title="Remove">
                      <XMarkIcon class="h-3 w-3" />
                    </button>
                  </span>
                </div>
                <span v-else class="text-xs text-slate-400 italic">Unassigned</span>
                <p v-if="isAuhDesignation(d)" class="text-[10px] text-amber-600 mt-1">Managed via Academic Units</p>
              </td>

              <!-- Status -->
              <td class="px-5 py-3 text-center">
                <span :class="d.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-400'"
                  class="text-xs px-2 py-0.5 rounded-full font-medium">
                  {{ d.is_active ? 'Active' : 'Inactive' }}
                </span>
              </td>

              <!-- Actions -->
              <td class="px-5 py-3 text-right">
                <div class="flex items-center justify-end gap-1">
                  <!-- AUH-* designations: link to Academic Units instead -->
                  <a v-if="isAuhDesignation(d)"
                    :href="route('faculty-loading.academic-units.index')"
                    class="p-1.5 rounded text-slate-400 hover:text-amber-600 transition-colors"
                    title="Manage in Academic Units">
                    <ArrowTopRightOnSquareIcon class="h-4 w-4" />
                  </a>
                  <!-- Assign button — disabled if at capacity -->
                  <button v-else @click="openAssignModal(d)"
                    :disabled="d.max_holders !== null && d.holders.length >= d.max_holders"
                    class="p-1.5 rounded transition-colors"
                    :class="d.max_holders !== null && d.holders.length >= d.max_holders
                      ? 'text-slate-200 cursor-not-allowed'
                      : 'text-slate-400 hover:text-emerald-600'"
                    :title="d.max_holders !== null && d.holders.length >= d.max_holders ? 'At capacity' : 'Assign faculty'">
                    <UserPlusIcon class="h-4 w-4" />
                  </button>
                  <button @click="openDesigForm(d, cat)" class="p-1.5 text-slate-400 hover:text-indigo-600 rounded">
                    <PencilIcon class="h-4 w-4" />
                  </button>
                  <button @click="deleteDesig(d)" class="p-1.5 text-slate-400 hover:text-red-600 rounded">
                    <TrashIcon class="h-4 w-4" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

    </div>

    <!-- ── Category Modal ───────────────────────────────────────────────────── -->
    <div v-if="catModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6 space-y-4">
        <h2 class="text-lg font-semibold text-slate-800">{{ catForm.id ? 'Edit' : 'New' }} Category</h2>
        <div class="space-y-3">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Code <span class="text-red-500">*</span></label>
            <input v-model="catForm.code" type="text" placeholder="e.g. ACADEMIC"
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none" />
            <p v-if="catForm.errors.code" class="text-red-500 text-xs mt-1">{{ catForm.errors.code }}</p>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Name <span class="text-red-500">*</span></label>
            <input v-model="catForm.name" type="text" placeholder="e.g. Academic"
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none" />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Description</label>
            <textarea v-model="catForm.description" rows="2"
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none" />
          </div>
          <div class="flex items-center gap-2">
            <input v-model="catForm.is_active" type="checkbox" id="cat-active" class="rounded text-indigo-600" />
            <label for="cat-active" class="text-sm text-slate-600">Active</label>
          </div>
        </div>
        <div class="flex justify-end gap-3 pt-1">
          <button @click="catModal = false" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Cancel</button>
          <button @click="saveCat" :disabled="catForm.processing"
            class="px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white rounded-lg font-medium">
            {{ catForm.id ? 'Update' : 'Create' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ── Designation Modal ────────────────────────────────────────────────── -->
    <div v-if="desigModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 space-y-4">
        <h2 class="text-lg font-semibold text-slate-800">{{ desigForm.id ? 'Edit' : 'New' }} Designation</h2>
        <div class="space-y-3">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Category <span class="text-red-500">*</span></label>
            <select v-model="desigForm.designation_category_id"
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
              <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Code <span class="text-red-500">*</span></label>
              <input v-model="desigForm.code" type="text" placeholder="e.g. DEPT_HEAD_SCI"
                class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none" />
              <p v-if="desigForm.errors.code" class="text-red-500 text-xs mt-1">{{ desigForm.errors.code }}</p>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Load Units <span class="text-red-500">*</span></label>
              <input v-model="desigForm.load_units" type="number" min="0" step="0.5"
                class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none" />
            </div>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Name <span class="text-red-500">*</span></label>
            <input v-model="desigForm.name" type="text" placeholder="e.g. Department Head – Science"
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none" />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Description</label>
            <textarea v-model="desigForm.description" rows="2"
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none" />
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Max Holders</label>
              <input v-model="desigForm.max_holders" type="number" min="1" placeholder="Unlimited"
                class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none" />
            </div>
            <div class="space-y-1 pt-1">
              <div class="flex items-center gap-2 pt-4">
                <input v-model="desigForm.requires_unit" type="checkbox" id="desig-unit" class="rounded text-indigo-600" />
                <label for="desig-unit" class="text-sm text-slate-600">Requires unit scope</label>
              </div>
              <div class="flex items-center gap-2">
                <input v-model="desigForm.is_active" type="checkbox" id="desig-active" class="rounded text-indigo-600" />
                <label for="desig-active" class="text-sm text-slate-600">Active</label>
              </div>
            </div>
          </div>
        </div>
        <div class="flex justify-end gap-3 pt-1">
          <button @click="desigModal = false" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Cancel</button>
          <button @click="saveDesig" :disabled="desigForm.processing"
            class="px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white rounded-lg font-medium">
            {{ desigForm.id ? 'Update' : 'Create' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ── Assign Faculty Modal ─────────────────────────────────────────────── -->
    <div v-if="assignModal" class="fixed inset-0 z-50 flex items-start justify-center bg-black/40 backdrop-blur-sm p-4 overflow-y-auto">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 space-y-4 my-8">
        <div class="flex items-start justify-between">
          <div>
            <h2 class="text-lg font-semibold text-slate-800">Assign Faculty</h2>
            <p class="text-xs text-slate-500 mt-0.5">
              {{ selectedDesig?.name }}
              <span class="ml-1 font-medium text-indigo-600">{{ selectedDesig?.load_units }} units</span>
            </p>
          </div>
          <button @click="assignModal = false" class="p-1.5 text-slate-400 hover:text-slate-700 rounded-lg hover:bg-slate-100 mt-0.5"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4 shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg></button>
        </div>

        <!-- Current holders from any module -->
        <div v-if="selectedDesig?.holders.length" class="rounded-lg bg-slate-50 border border-slate-100 px-3 py-2.5 space-y-1.5">
          <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Currently Assigned</p>
          <div class="flex flex-wrap gap-1.5">
            <span v-for="h in selectedDesig.holders" :key="h.assignment_id"
              class="inline-flex items-center gap-1 pl-2 pr-1 py-0.5 rounded-full text-xs font-medium bg-indigo-50 text-indigo-700 border border-indigo-100">
              {{ h.user_name }}
              <button v-if="!isAuhDesignation(selectedDesig)" @click="revokeHolder(h); assignModal = false"
                class="hover:text-red-600 transition-colors ml-0.5 rounded-full p-0.5 hover:bg-red-50" title="Remove">
                <XMarkIcon class="h-3 w-3" />
              </button>
            </span>
          </div>
        </div>

        <!-- At capacity warning -->
        <div v-if="selectedDesig?.max_holders !== null && selectedDesig?.holders.length >= selectedDesig?.max_holders"
          class="rounded-lg bg-amber-50 border border-amber-200 px-3 py-2 text-xs text-amber-700">
          This designation has reached its maximum of {{ selectedDesig.max_holders }} holder(s).
          Remove an existing holder to assign someone new.
        </div>

        <template v-else>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Academic Term <span class="text-red-500">*</span></label>
            <select v-model="assignForm.academic_term_id"
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
              <option :value="null">Select term...</option>
              <option v-for="t in terms" :key="t.id" :value="t.id">
                {{ t.label }}{{ t.is_current ? ' (current)' : '' }}
              </option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Faculty <span class="text-red-500">*</span></label>
            <select v-model="assignForm.user_id"
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
              <option :value="null">Select faculty...</option>
              <option v-for="f in faculty" :key="f.id" :value="f.id">{{ f.name }}</option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">
              Load Units
              <span class="text-slate-400 font-normal ml-1">(default: {{ selectedDesig?.load_units }})</span>
            </label>
            <input v-model.number="assignForm.override_units" type="number" step="0.5" min="0"
              :placeholder="`${selectedDesig?.load_units}`"
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
            <p class="text-xs text-slate-400 mt-1">Leave blank to use the designation's default units.</p>
          </div>

          <div class="flex justify-end gap-2 pt-1">
            <button @click="assignModal = false" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Cancel</button>
            <button @click="saveAssign" :disabled="assignForm.processing || !assignForm.user_id || !assignForm.academic_term_id"
              class="px-4 py-2 text-sm bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white rounded-lg font-medium">
              Assign
            </button>
          </div>
        </template>
      </div>
    </div>

  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import {
  ArrowTopRightOnSquareIcon, CheckCircleIcon, ExclamationCircleIcon, PencilIcon, PlusIcon,
  TagIcon, TrashIcon, UserPlusIcon, XMarkIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  categories:  { type: Array,  default: () => [] },
  terms:       { type: Array,  default: () => [] },
  faculty:     { type: Array,  default: () => [] },
  currentTerm: { type: Object, default: null },
  filters:     { type: Object, default: () => ({}) },
})

// ── Helpers ───────────────────────────────────────────────────────────────────

function isAuhDesignation(d) {
  return d.code.startsWith('AUH-')
}

// ── Term filter ───────────────────────────────────────────────────────────────

const selectedTermId = ref(props.filters.term_id ? Number(props.filters.term_id) : (props.currentTerm?.id ?? null))

function applyTermFilter() {
  router.get(route('faculty-loading.designations.index'), { term_id: selectedTermId.value }, { preserveState: true })
}

// ── Category CRUD ─────────────────────────────────────────────────────────────

const catModal = ref(false)
const catForm  = useForm({ id: null, code: '', name: '', description: '', sort_order: 0, is_active: true })

function openCatForm(cat = null) {
  if (cat) Object.assign(catForm, { id: cat.id, code: cat.code, name: cat.name, description: cat.description ?? '', sort_order: cat.sort_order, is_active: cat.is_active })
  else { catForm.reset(); catForm.id = null; catForm.is_active = true }
  catModal.value = true
}

function saveCat() {
  if (catForm.id) {
    catForm.put(route('faculty-loading.designations.categories.update', catForm.id), { onSuccess: () => { catModal.value = false } })
  } else {
    catForm.post(route('faculty-loading.designations.categories.store'), { onSuccess: () => { catModal.value = false } })
  }
}

function deleteCat(cat) {
  if (!confirm(`Delete category "${cat.name}"?`)) return
  useForm({}).delete(route('faculty-loading.designations.categories.destroy', cat.id))
}

// ── Designation CRUD ──────────────────────────────────────────────────────────

const desigModal = ref(false)
const desigForm  = useForm({ id: null, designation_category_id: null, code: '', name: '', description: '', load_units: 0, requires_unit: false, max_holders: null, sort_order: 0, is_active: true })

function openDesigForm(d = null, cat = null) {
  if (d) {
    Object.assign(desigForm, { id: d.id, designation_category_id: d.designation_category_id ?? cat?.id, code: d.code, name: d.name, description: d.description ?? '', load_units: d.load_units, requires_unit: d.requires_unit, max_holders: d.max_holders, sort_order: d.sort_order, is_active: d.is_active })
  } else {
    desigForm.reset(); desigForm.id = null; desigForm.is_active = true; desigForm.load_units = 0
    desigForm.designation_category_id = cat?.id ?? null
  }
  desigModal.value = true
}

function saveDesig() {
  if (desigForm.id) {
    desigForm.put(route('faculty-loading.designations.update', desigForm.id), { onSuccess: () => { desigModal.value = false } })
  } else {
    desigForm.post(route('faculty-loading.designations.store'), { onSuccess: () => { desigModal.value = false } })
  }
}

function deleteDesig(d) {
  if (!confirm(`Delete designation "${d.name}"?`)) return
  useForm({}).delete(route('faculty-loading.designations.destroy', d.id))
}

// ── Assign faculty ────────────────────────────────────────────────────────────

const assignModal   = ref(false)
const selectedDesig = ref(null)
const assignForm    = useForm({ user_id: null, academic_term_id: null, override_units: null })

function openAssignModal(d) {
  selectedDesig.value = d
  assignForm.reset()
  assignForm.user_id          = null
  assignForm.academic_term_id = props.currentTerm?.id ?? null
  assignForm.override_units   = null
  assignModal.value = true
}

function saveAssign() {
  assignForm.post(route('faculty-loading.designations.assign-direct', selectedDesig.value.id), {
    onSuccess: () => { assignModal.value = false },
  })
}

// ── Revoke a holder ───────────────────────────────────────────────────────────

function revokeHolder(holder) {
  if (!confirm(`Remove "${holder.user_name}" from this designation?`)) return
  useForm({}).delete(route('faculty-loading.designations.revoke-direct', holder.assignment_id))
}
</script>
