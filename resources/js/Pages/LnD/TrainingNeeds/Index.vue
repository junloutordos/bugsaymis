<script setup>
import { ref, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Swal from 'sweetalert2'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppModal from '@/Components/AppModal.vue'
import EmptyState from '@/Components/EmptyState.vue'
import PaginationControl from '@/Components/PaginationControl.vue'
import { confirmDelete } from '@/Composables/useConfirm.js'
import { PlusIcon, MagnifyingGlassIcon, CheckIcon } from '@heroicons/vue/24/outline'

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

const clearFilters = () => {
  search.value = ''
  employeeId.value = ''
  year.value = ''
  priority.value = ''
  status.value = ''
  source.value = ''
}

// ── Helpers ────────────────────────────────────────────────────────────────────
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

function priorityBadgeColor (p) {
  const map = { high: 'red', medium: 'amber', low: 'green' }
  return map[p] ?? 'slate'
}
function statusBadgeColor (s) {
  const map = { pending: 'slate', approved: 'blue', addressed: 'green', deferred: 'orange' }
  return map[s] ?? 'slate'
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

const deleteNeed = async (n) => {
  if (!await confirmDelete(`"${n.competency_area}" will be removed.`)) return
  router.delete(route('lnd.tna.destroy', n.id), {
    preserveState: true,
    onSuccess: () => Swal.fire({ icon: 'success', title: 'Deleted', timer: 1200, showConfirmButton: false }),
    onError: (errs) => Swal.fire({ icon: 'error', title: 'Error', text: Object.values(errs)[0] }),
  })
}
</script>

<template>
  <Head title="Training Needs (TNA)" />
  <AdminLayout title="Training Needs Analysis">
    <div class="space-y-5">

      <AppPageHeader title="Training Needs Analysis (TNA)" subtitle="Identify and manage employee training needs">
        <template #actions>
          <AppButton as="link" variant="secondary" :href="route('lnd.tna.consolidation')">Consolidation View</AppButton>
          <AppButton @click="openCreate">
            <PlusIcon class="h-4 w-4" />
            Add Need
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Filters -->
      <AppFilterBar>
        <div class="relative w-52">
          <MagnifyingGlassIcon class="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
          <input v-model="search" type="text" placeholder="Search competency / training…"
            class="w-full rounded-lg border border-slate-200 bg-white py-2 pl-9 pr-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>
        <select v-model="employeeId"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 min-w-[160px]">
          <option value="">All Employees</option>
          <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option>
        </select>
        <select v-model="year"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
          <option value="">All Years</option>
          <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
        </select>
        <select v-model="priority"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
          <option value="">All Priority</option>
          <option value="high">High</option>
          <option value="medium">Medium</option>
          <option value="low">Low</option>
        </select>
        <select v-model="status"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
          <option value="">All Status</option>
          <option value="pending">Pending</option>
          <option value="approved">Approved</option>
          <option value="addressed">Addressed</option>
          <option value="deferred">Deferred</option>
        </select>
        <select v-model="source"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
          <option value="">All Sources</option>
          <option value="self">Self</option>
          <option value="supervisor">Supervisor</option>
          <option value="hr">HR</option>
          <option value="ipcr">IPCR</option>
          <option value="spms">SPMS</option>
        </select>
        <template #actions>
          <AppButton v-if="search || employeeId || year || priority || status || source"
            size="sm" variant="secondary" @click="clearFilters">Clear</AppButton>
        </template>
      </AppFilterBar>

      <!-- Table -->
      <AppTable :loading="isLoading" :is-empty="!needs.data.length" :skeleton-cols="10">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Employee</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Competency Area</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Gap</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Recommended Training</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Priority</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Source</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Year</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">IDP</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Status</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Actions</th>
          </tr>
        </template>

        <tr v-for="n in needs.data" :key="n.id" class="hover:bg-slate-50/60">
          <td class="px-4 py-3 font-medium text-sm text-slate-800">{{ n.employee?.name ?? '—' }}</td>
          <td class="px-4 py-3">
            <div class="font-medium text-slate-800 text-sm">{{ n.competency_area }}</div>
            <div v-if="n.competency_gap" class="text-xs text-slate-500 line-clamp-1">{{ n.competency_gap }}</div>
          </td>
          <td class="px-4 py-3 text-center">
            <div class="flex items-center justify-center gap-1 text-xs">
              <span :class="['rounded px-1.5 py-0.5 font-medium', levelColors[n.current_level]]">{{ n.current_level }}</span>
              <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
              <span :class="['rounded px-1.5 py-0.5 font-medium', levelColors[n.target_level]]">{{ n.target_level }}</span>
            </div>
          </td>
          <td class="px-4 py-3 text-sm text-slate-700 max-w-[160px] truncate">{{ n.recommended_training ?? '—' }}</td>
          <td class="px-4 py-3 text-center">
            <AppBadge :color="priorityBadgeColor(n.priority_level)">{{ n.priority_level }}</AppBadge>
          </td>
          <td class="px-4 py-3 text-center text-xs text-slate-600">{{ sourceLabel[n.source] ?? n.source }}</td>
          <td class="px-4 py-3 text-center text-sm text-slate-700">{{ n.year }}</td>
          <td class="px-4 py-3 text-center">
            <span v-if="n.individual_development_plan" class="inline-flex items-center gap-1 text-xs text-emerald-600 font-medium">
              <CheckIcon class="w-3.5 h-3.5" />
              Yes
            </span>
            <span v-else class="text-xs text-slate-400">—</span>
          </td>
          <td class="px-4 py-3 text-center">
            <AppBadge :color="statusBadgeColor(n.status)">{{ n.status }}</AppBadge>
          </td>
          <td class="px-4 py-3 text-center">
            <div class="flex items-center justify-center gap-1 flex-wrap">
              <AppButton size="sm" variant="secondary" @click="openEdit(n)">Edit</AppButton>
              <AppButton v-if="n.status === 'pending'" size="sm" @click="openApprove(n)">Approve</AppButton>
              <AppButton size="sm" variant="danger" @click="deleteNeed(n)">Delete</AppButton>
            </div>
          </td>
        </tr>

        <template #empty>
          <EmptyState title="No training needs found." />
        </template>

        <template #footer>
          <PaginationControl :links="needs.links" :total="needs.total" />
        </template>
      </AppTable>

    </div>

    <!-- Create / Edit Modal -->
    <AppModal :show="showModal" :title="editingItem ? 'Edit Training Need' : 'Add Training Need'" size="xl" @close="closeModal">
      <div class="space-y-4">

        <!-- Employee -->
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Employee <span class="text-red-500">*</span></label>
          <select v-model="form.employee_id" required :disabled="!!editingItem"
            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 disabled:bg-slate-50 disabled:text-slate-400">
            <option value="">— Select employee —</option>
            <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option>
          </select>
        </div>

        <!-- Competency Area -->
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Competency Area <span class="text-red-500">*</span></label>
          <input v-model="form.competency_area" type="text" required
            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
        </div>

        <!-- Competency Gap -->
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Competency Gap Description</label>
          <textarea v-model="form.competency_gap" rows="2"
            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 resize-none" />
        </div>

        <!-- Current / Target Level -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Current Level <span class="text-red-500">*</span></label>
            <select v-model="form.current_level" required
              class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
              <option value="none">None</option>
              <option value="basic">Basic</option>
              <option value="intermediate">Intermediate</option>
              <option value="advanced">Advanced</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Target Level <span class="text-red-500">*</span></label>
            <select v-model="form.target_level" required
              class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
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
            <label class="block text-xs font-medium text-slate-600 mb-1">Priority <span class="text-red-500">*</span></label>
            <select v-model="form.priority_level" required
              class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
              <option value="high">High</option>
              <option value="medium">Medium</option>
              <option value="low">Low</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Source <span class="text-red-500">*</span></label>
            <select v-model="form.source" required
              class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
              <option value="self">Self</option>
              <option value="supervisor">Supervisor</option>
              <option value="hr">HR</option>
              <option value="ipcr">IPCR</option>
              <option value="spms">SPMS</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Year <span class="text-red-500">*</span></label>
            <input v-model="form.year" type="number" required min="2000" max="2099"
              class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
          </div>
        </div>

        <!-- Recommended Training -->
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Recommended Training</label>
          <input v-model="form.recommended_training" type="text"
            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
        </div>

        <!-- Remarks -->
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Remarks</label>
          <textarea v-model="form.remarks" rows="2"
            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 resize-none" />
        </div>
      </div>

      <template #footer>
        <AppButton variant="secondary" @click="closeModal">Cancel</AppButton>
        <AppButton :loading="isSubmitting" @click="submit">{{ editingItem ? 'Update' : 'Save' }}</AppButton>
      </template>
    </AppModal>

    <!-- Approve / Defer Modal -->
    <AppModal :show="showApprove" title="Approve / Defer Training Need" size="md" @close="showApprove = false">
      <div class="space-y-4">
        <div v-if="approveItem" class="rounded-lg bg-slate-50 border border-slate-100 p-3 text-sm text-slate-700">
          <span class="font-medium">{{ approveItem.employee?.name }}</span> — {{ approveItem.competency_area }}
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-2">Action <span class="text-red-500">*</span></label>
          <div class="flex gap-3">
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="radio" v-model="approveForm.status" value="approved" />
              <span class="text-sm text-slate-700">Approve</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="radio" v-model="approveForm.status" value="deferred" />
              <span class="text-sm text-slate-700">Defer</span>
            </label>
          </div>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Remarks</label>
          <textarea v-model="approveForm.remarks" rows="3" placeholder="Optional remarks…"
            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 resize-none" />
        </div>
      </div>

      <template #footer>
        <AppButton variant="secondary" @click="showApprove = false">Cancel</AppButton>
        <AppButton :variant="approveForm.status === 'approved' ? 'primary' : 'warning'" :loading="isSubmitting" @click="submitApprove">
          {{ approveForm.status === 'approved' ? 'Approve' : 'Defer' }}
        </AppButton>
      </template>
    </AppModal>
  </AdminLayout>
</template>
