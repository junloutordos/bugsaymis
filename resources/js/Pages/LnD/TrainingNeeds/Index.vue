<script setup>
import { ref, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Swal from 'sweetalert2'

const props = defineProps({
  needs:     { type: Object, required: true },
  employees: { type: Array,  default: () => [] },
  filters:   { type: Object, default: () => ({}) },
})

const page = usePage()

// ── Filters ────────────────────────────────────────────────────────────────────
const search     = ref(props.filters?.search      ?? '')
const employeeId = ref(props.filters?.employee_id ?? '')
const year       = ref(props.filters?.year        ?? '')
const priority   = ref(props.filters?.priority    ?? '')
const status     = ref(props.filters?.status      ?? '')
const source     = ref(props.filters?.source      ?? '')
const isLoading  = ref(false)
let debounceTimer = null

const applyFilters = (immediate = true) => {
  clearTimeout(debounceTimer)
  const go = () => {
    isLoading.value = true
    router.get(route('lnd.tna.index'), {
      search:      search.value      || undefined,
      employee_id: employeeId.value  || undefined,
      year:        year.value        || undefined,
      priority:    priority.value    || undefined,
      status:      status.value      || undefined,
      source:      source.value      || undefined,
    }, {
      preserveState: true, replace: true,
      only: ['needs', 'filters'],
      onFinish: () => { isLoading.value = false },
    })
  }
  if (immediate) go()
  else debounceTimer = setTimeout(go, 400)
}

watch(search, () => applyFilters(false))
watch([employeeId, year, priority, status, source], () => applyFilters(true))

const goToPage = (p) => {
  isLoading.value = true
  router.get(route('lnd.tna.index'), {
    search: search.value || undefined,
    employee_id: employeeId.value || undefined,
    year: year.value || undefined,
    priority: priority.value || undefined,
    status: status.value || undefined,
    source: source.value || undefined,
    page: p,
  }, { preserveState: true, replace: true, only: ['needs', 'filters'], onFinish: () => { isLoading.value = false } })
}

// ── Helpers ────────────────────────────────────────────────────────────────────
const priorityColors = {
  high:   'bg-red-100 text-red-700',
  medium: 'bg-yellow-100 text-yellow-700',
  low:    'bg-green-100 text-green-700',
}
const statusColors = {
  pending:   'bg-gray-100 text-gray-600',
  approved:  'bg-blue-100 text-blue-700',
  addressed: 'bg-green-100 text-green-700',
  deferred:  'bg-orange-100 text-orange-700',
}
const levelColors = {
  none:         'bg-gray-100 text-gray-500',
  basic:        'bg-blue-100 text-blue-600',
  intermediate: 'bg-indigo-100 text-indigo-700',
  advanced:     'bg-purple-100 text-purple-700',
}
const sourceLabel = {
  self:       'Self',
  supervisor: 'Supervisor',
  hr:         'HR',
  ipcr:       'IPCR',
  spms:       'SPMS',
}

const currentYear = new Date().getFullYear()
const yearOptions = Array.from({ length: 6 }, (_, i) => currentYear - 2 + i)

// ── Modal ──────────────────────────────────────────────────────────────────────
const showModal    = ref(false)
const showApprove  = ref(false)
const editingItem  = ref(null)
const approveItem  = ref(null)
const isSubmitting = ref(false)

const emptyForm = () => ({
  employee_id:          '',
  competency_area:      '',
  competency_gap:       '',
  current_level:        'basic',
  target_level:         'intermediate',
  priority_level:       'medium',
  recommended_training: '',
  source:               'self',
  year:                 currentYear,
  remarks:              '',
})
const form = ref(emptyForm())

const approveForm = ref({ status: 'approved', remarks: '' })

const openCreate = () => {
  editingItem.value = null
  form.value = emptyForm()
  showModal.value = true
}

const openEdit = (n) => {
  editingItem.value = n
  form.value = {
    employee_id:          n.employee_id          ?? '',
    competency_area:      n.competency_area       ?? '',
    competency_gap:       n.competency_gap        ?? '',
    current_level:        n.current_level         ?? 'basic',
    target_level:         n.target_level          ?? 'intermediate',
    priority_level:       n.priority_level        ?? 'medium',
    recommended_training: n.recommended_training  ?? '',
    source:               n.source                ?? 'self',
    year:                 n.year                  ?? currentYear,
    remarks:              n.remarks               ?? '',
  }
  showModal.value = true
}

const openApprove = (n) => {
  approveItem.value = n
  approveForm.value = { status: 'approved', remarks: '' }
  showApprove.value = true
}

const closeModal = () => { showModal.value = false; editingItem.value = null }

const submit = () => {
  isSubmitting.value = true
  const url    = editingItem.value ? route('lnd.tna.update', editingItem.value.id) : route('lnd.tna.store')
  const method = editingItem.value ? 'put' : 'post'
  router[method](url, form.value, {
    preserveState: true,
    onSuccess: () => { closeModal(); Swal.fire({ icon: 'success', title: 'Saved', timer: 1400, showConfirmButton: false }) },
    onError: () => {},
    onFinish: () => { isSubmitting.value = false },
  })
}

const submitApprove = () => {
  isSubmitting.value = true
  router.patch(route('lnd.tna.approve', approveItem.value.id), approveForm.value, {
    preserveState: true,
    onSuccess: () => {
      showApprove.value = false
      Swal.fire({ icon: 'success', title: approveForm.value.status === 'approved' ? 'Approved' : 'Deferred', timer: 1400, showConfirmButton: false })
    },
    onError: () => {},
    onFinish: () => { isSubmitting.value = false },
  })
}

const deleteNeed = (n) => {
  Swal.fire({
    title: 'Delete training need?',
    text: `"${n.competency_area}" will be removed.`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    confirmButtonText: 'Delete',
  }).then(res => {
    if (!res.isConfirmed) return
    router.delete(route('lnd.tna.destroy', n.id), {
      preserveState: true,
      onSuccess: () => Swal.fire({ icon: 'success', title: 'Deleted', timer: 1200, showConfirmButton: false }),
      onError: (errs) => Swal.fire({ icon: 'error', title: 'Error', text: Object.values(errs)[0] }),
    })
  })
}
</script>

<template>
  <AdminLayout title="Training Needs Analysis">
    <Head title="Training Needs (TNA)" />

    <div class="p-6 space-y-5">

      <!-- Header -->
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 class="text-xl font-bold text-gray-800">Training Needs Analysis (TNA)</h1>
          <p class="text-sm text-gray-500">Identify and manage employee training needs</p>
        </div>
        <div class="flex gap-2">
          <a :href="route('lnd.tna.consolidation')"
            class="inline-flex items-center gap-1 rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
            Consolidation View
          </a>
          <button @click="openCreate"
            class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add Need
          </button>
        </div>
      </div>

      <!-- Filters -->
      <div class="flex flex-wrap gap-2">
        <input v-model="search" type="text" placeholder="Search competency / training…"
          class="rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-400 focus:outline-none w-52" />
        <select v-model="employeeId" class="rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-400 focus:outline-none min-w-[160px]">
          <option value="">All Employees</option>
          <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option>
        </select>
        <select v-model="year" class="rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-400 focus:outline-none">
          <option value="">All Years</option>
          <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
        </select>
        <select v-model="priority" class="rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-400 focus:outline-none">
          <option value="">All Priority</option>
          <option value="high">High</option>
          <option value="medium">Medium</option>
          <option value="low">Low</option>
        </select>
        <select v-model="status" class="rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-400 focus:outline-none">
          <option value="">All Status</option>
          <option value="pending">Pending</option>
          <option value="approved">Approved</option>
          <option value="addressed">Addressed</option>
          <option value="deferred">Deferred</option>
        </select>
        <select v-model="source" class="rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-400 focus:outline-none">
          <option value="">All Sources</option>
          <option value="self">Self</option>
          <option value="supervisor">Supervisor</option>
          <option value="hr">HR</option>
          <option value="ipcr">IPCR</option>
          <option value="spms">SPMS</option>
        </select>
        <button v-if="search || employeeId || year || priority || status || source"
          @click="search=''; employeeId=''; year=''; priority=''; status=''; source=''"
          class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-600 hover:bg-gray-100">Clear</button>
      </div>

      <!-- Table -->
      <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
        <div v-if="isLoading" class="flex items-center justify-center py-12 text-gray-400 text-sm">Loading…</div>
        <table v-else class="min-w-full divide-y divide-gray-200 text-sm">
          <thead class="bg-gray-50 text-xs font-semibold text-gray-500 uppercase tracking-wide">
            <tr>
              <th class="px-4 py-3 text-left">Employee</th>
              <th class="px-4 py-3 text-left">Competency Area</th>
              <th class="px-4 py-3 text-center">Gap</th>
              <th class="px-4 py-3 text-left">Recommended Training</th>
              <th class="px-4 py-3 text-center">Priority</th>
              <th class="px-4 py-3 text-center">Source</th>
              <th class="px-4 py-3 text-center">Year</th>
              <th class="px-4 py-3 text-center">IDP</th>
              <th class="px-4 py-3 text-center">Status</th>
              <th class="px-4 py-3 text-center">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-if="needs.data.length === 0">
              <td colspan="10" class="py-10 text-center text-gray-400">No training needs found.</td>
            </tr>
            <tr v-for="n in needs.data" :key="n.id" class="hover:bg-gray-50">
              <td class="px-4 py-3 font-medium text-gray-800">{{ n.employee?.name ?? '—' }}</td>
              <td class="px-4 py-3">
                <div class="font-medium text-gray-800">{{ n.competency_area }}</div>
                <div v-if="n.competency_gap" class="text-xs text-gray-500 line-clamp-1">{{ n.competency_gap }}</div>
              </td>
              <td class="px-4 py-3 text-center">
                <div class="flex items-center justify-center gap-1 text-xs">
                  <span :class="['rounded px-1.5 py-0.5 font-medium', levelColors[n.current_level]]">{{ n.current_level }}</span>
                  <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                  <span :class="['rounded px-1.5 py-0.5 font-medium', levelColors[n.target_level]]">{{ n.target_level }}</span>
                </div>
              </td>
              <td class="px-4 py-3 text-gray-600 max-w-[160px] truncate">{{ n.recommended_training ?? '—' }}</td>
              <td class="px-4 py-3 text-center">
                <span :class="['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium capitalize', priorityColors[n.priority_level]]">
                  {{ n.priority_level }}
                </span>
              </td>
              <td class="px-4 py-3 text-center text-xs text-gray-600">{{ sourceLabel[n.source] ?? n.source }}</td>
              <td class="px-4 py-3 text-center text-gray-600">{{ n.year }}</td>
              <td class="px-4 py-3 text-center">
                <span v-if="n.individual_development_plan" class="inline-flex items-center gap-1 text-xs text-green-600 font-medium">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                  Yes
                </span>
                <span v-else class="text-xs text-gray-400">—</span>
              </td>
              <td class="px-4 py-3 text-center">
                <span :class="['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium capitalize', statusColors[n.status] ?? 'bg-gray-100 text-gray-600']">
                  {{ n.status }}
                </span>
              </td>
              <td class="px-4 py-3 text-center">
                <div class="flex items-center justify-center gap-1 flex-wrap">
                  <button @click="openEdit(n)"
                    class="rounded px-2 py-1 text-xs font-medium text-gray-600 hover:bg-gray-100">Edit</button>
                  <button v-if="n.status === 'pending'"
                    @click="openApprove(n)"
                    class="rounded px-2 py-1 text-xs font-medium text-blue-600 hover:bg-blue-50">Approve</button>
                  <button @click="deleteNeed(n)"
                    class="rounded px-2 py-1 text-xs font-medium text-red-500 hover:bg-red-50">Delete</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="needs.last_page > 1" class="flex items-center justify-between text-sm text-gray-600">
        <span>Showing {{ needs.from }}–{{ needs.to }} of {{ needs.total }}</span>
        <div class="flex gap-1">
          <button v-for="p in needs.links" :key="p.label"
            @click="p.url && goToPage(new URL(p.url).searchParams.get('page'))"
            :disabled="!p.url"
            :class="['px-3 py-1 rounded border text-xs', p.active ? 'bg-blue-600 text-white border-blue-600' : 'border-gray-300 hover:bg-gray-50 disabled:opacity-40']"
            v-html="p.label" />
        </div>
      </div>
    </div>

    <!-- Create / Edit Modal -->
    <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div class="w-full max-w-xl max-h-[90vh] overflow-y-auto rounded-2xl bg-white shadow-2xl">
          <div class="flex items-center justify-between border-b px-6 py-4">
            <h2 class="text-lg font-bold text-gray-800">{{ editingItem ? 'Edit Training Need' : 'Add Training Need' }}</h2>
            <button @click="closeModal" class="text-gray-400 hover:text-gray-600">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>
          <form @submit.prevent="submit" class="p-6 space-y-4">

            <!-- Employee -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Employee <span class="text-red-500">*</span></label>
              <select v-model="form.employee_id" required :disabled="!!editingItem"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none disabled:bg-gray-50">
                <option value="">— Select employee —</option>
                <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option>
              </select>
            </div>

            <!-- Competency Area -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Competency Area <span class="text-red-500">*</span></label>
              <input v-model="form.competency_area" type="text" required
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none" />
            </div>

            <!-- Competency Gap -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Competency Gap Description</label>
              <textarea v-model="form.competency_gap" rows="2"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none resize-none" />
            </div>

            <!-- Current / Target Level -->
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Current Level <span class="text-red-500">*</span></label>
                <select v-model="form.current_level" required
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none">
                  <option value="none">None</option>
                  <option value="basic">Basic</option>
                  <option value="intermediate">Intermediate</option>
                  <option value="advanced">Advanced</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Target Level <span class="text-red-500">*</span></label>
                <select v-model="form.target_level" required
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none">
                  <option value="none">None</option>
                  <option value="basic">Basic</option>
                  <option value="intermediate">Intermediate</option>
                  <option value="advanced">Advanced</option>
                </select>
              </div>
            </div>

            <!-- Priority + Source + Year -->
            <div class="grid grid-cols-3 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Priority <span class="text-red-500">*</span></label>
                <select v-model="form.priority_level" required
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none">
                  <option value="high">High</option>
                  <option value="medium">Medium</option>
                  <option value="low">Low</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Source <span class="text-red-500">*</span></label>
                <select v-model="form.source" required
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none">
                  <option value="self">Self</option>
                  <option value="supervisor">Supervisor</option>
                  <option value="hr">HR</option>
                  <option value="ipcr">IPCR</option>
                  <option value="spms">SPMS</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Year <span class="text-red-500">*</span></label>
                <input v-model="form.year" type="number" required min="2000" max="2099"
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none" />
              </div>
            </div>

            <!-- Recommended Training -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Recommended Training</label>
              <input v-model="form.recommended_training" type="text"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none" />
            </div>

            <!-- Remarks -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Remarks</label>
              <textarea v-model="form.remarks" rows="2"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none resize-none" />
            </div>

            <div class="flex justify-end gap-3 pt-2">
              <button type="button" @click="closeModal"
                class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
              <button type="submit" :disabled="isSubmitting"
                class="rounded-lg bg-blue-600 px-5 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                {{ isSubmitting ? 'Saving…' : (editingItem ? 'Update' : 'Save') }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- Approve / Defer Modal -->
    <Teleport to="body">
      <div v-if="showApprove" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div class="w-full max-w-md rounded-2xl bg-white shadow-2xl">
          <div class="flex items-center justify-between border-b px-6 py-4">
            <h2 class="text-lg font-bold text-gray-800">Approve / Defer Training Need</h2>
            <button @click="showApprove = false" class="text-gray-400 hover:text-gray-600">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>
          <div class="p-6 space-y-4">
            <div v-if="approveItem" class="rounded-lg bg-gray-50 p-3 text-sm text-gray-700">
              <span class="font-medium">{{ approveItem.employee?.name }}</span> — {{ approveItem.competency_area }}
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Action <span class="text-red-500">*</span></label>
              <div class="flex gap-3">
                <label class="flex items-center gap-2 cursor-pointer">
                  <input type="radio" v-model="approveForm.status" value="approved" class="text-blue-600" />
                  <span class="text-sm text-gray-700">Approve</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                  <input type="radio" v-model="approveForm.status" value="deferred" class="text-orange-500" />
                  <span class="text-sm text-gray-700">Defer</span>
                </label>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Remarks</label>
              <textarea v-model="approveForm.remarks" rows="3" placeholder="Optional remarks…"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none resize-none" />
            </div>
            <div class="flex justify-end gap-3 pt-2">
              <button @click="showApprove = false"
                class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
              <button @click="submitApprove" :disabled="isSubmitting"
                :class="['rounded-lg px-5 py-2 text-sm font-medium text-white disabled:opacity-50', approveForm.status === 'approved' ? 'bg-blue-600 hover:bg-blue-700' : 'bg-orange-500 hover:bg-orange-600']">
                {{ isSubmitting ? 'Saving…' : (approveForm.status === 'approved' ? 'Approve' : 'Defer') }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </AdminLayout>
</template>
