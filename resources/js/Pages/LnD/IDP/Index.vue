<script setup>
import { ref, watch, computed } from 'vue'
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
import { PlusIcon, ChevronRightIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  idps:      { type: Object, required: true },
  employees: { type: Array,  default: () => [] },
  programs:  { type: Array,  default: () => [] },
  filters:   { type: Object, default: () => ({}) },
})

const page = usePage()

// ── Filters ────────────────────────────────────────────────────────────────────
const employeeId    = ref(props.filters?.employee_id     ?? '')
const supervisorId  = ref(props.filters?.supervisor_id   ?? '')
const year          = ref(props.filters?.year            ?? '')
const approvalSt    = ref(props.filters?.approval_status ?? '')
const statusF       = ref(props.filters?.status          ?? '')
const intervention  = ref(props.filters?.intervention_type ?? '')
const isLoading     = ref(false)

const applyFilters = () => {
  isLoading.value = true
  router.get(route('lnd.idp.index'), {
    employee_id:       employeeId.value   || undefined,
    supervisor_id:     supervisorId.value || undefined,
    year:              year.value         || undefined,
    approval_status:   approvalSt.value   || undefined,
    status:            statusF.value      || undefined,
    intervention_type: intervention.value || undefined,
  }, {
    preserveState: true, replace: true,
    only: ['idps', 'filters'],
    onFinish: () => { isLoading.value = false },
  })
}

watch([employeeId, supervisorId, year, approvalSt, statusF, intervention], () => applyFilters())

const goToPage = (p) => {
  isLoading.value = true
  router.get(route('lnd.idp.index'), {
    employee_id: employeeId.value || undefined, supervisor_id: supervisorId.value || undefined,
    year: year.value || undefined, approval_status: approvalSt.value || undefined,
    status: statusF.value || undefined, intervention_type: intervention.value || undefined,
    page: p,
  }, { preserveState: true, replace: true, only: ['idps', 'filters'], onFinish: () => { isLoading.value = false } })
}

// ── Helpers ────────────────────────────────────────────────────────────────────
const approvalLabel = {
  draft:     'Draft',
  submitted: 'Pending',
  approved:  'Approved',
  returned:  'Returned',
}
const interventionLabel = {
  training:     'Training',
  coaching:     'Coaching',
  assignment:   'Assignment',
  self_study:   'Self-Study',
  e_learning:   'E-Learning',
  other:        'Other',
}

function approvalBadgeColor(status) {
  const map = { draft: 'slate', submitted: 'amber', approved: 'green', returned: 'red' }
  return map[status] ?? 'slate'
}
function statusBadgeColor(status) {
  const map = { planned: 'blue', ongoing: 'amber', completed: 'green', deferred: 'orange', cancelled: 'red' }
  return map[status] ?? 'slate'
}
function levelBadgeColor(level) {
  const map = { none: 'slate', basic: 'blue', intermediate: 'indigo', advanced: 'purple' }
  return map[level] ?? 'slate'
}

const fmt = (d) => d ? new Date(d).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }) : '—'
const currentYear = new Date().getFullYear()
const yearOptions = Array.from({ length: 6 }, (_, i) => currentYear - 2 + i)

// ── Modal state ────────────────────────────────────────────────────────────────
const showModal       = ref(false)
const showApproveModal = ref(false)
const showStatusModal  = ref(false)
const editingItem     = ref(null)
const approveItem     = ref(null)
const statusItem      = ref(null)
const isSubmitting    = ref(false)

const emptyForm = () => ({
  employee_id:          '',
  supervisor_id:        '',
  training_need_id:     '',
  learning_program_id:  '',
  year:                 currentYear,
  competency:           '',
  current_level:        'basic',
  target_level:         'intermediate',
  development_activity: '',
  intervention_type:    'training',
  timeline_start:       '',
  timeline_end:         '',
  status:               'planned',
  employee_remarks:     '',
})
const form = ref(emptyForm())

const approveForm = ref({ action: 'approved', supervisor_remarks: '' })
const statusForm  = ref({ status: 'ongoing', employee_remarks: '' })

const openCreate = () => {
  editingItem.value = null
  form.value = emptyForm()
  showModal.value = true
}

const openEdit = (idp) => {
  editingItem.value = idp
  form.value = {
    employee_id:          idp.employee_id          ?? '',
    supervisor_id:        idp.supervisor_id         ?? '',
    training_need_id:     idp.training_need_id      ?? '',
    learning_program_id:  idp.learning_program_id   ?? '',
    year:                 idp.year                  ?? currentYear,
    competency:           idp.competency            ?? '',
    current_level:        idp.current_level         ?? 'basic',
    target_level:         idp.target_level          ?? 'intermediate',
    development_activity: idp.development_activity  ?? '',
    intervention_type:    idp.intervention_type     ?? 'training',
    timeline_start:       idp.timeline_start        ? idp.timeline_start.substring(0, 10) : '',
    timeline_end:         idp.timeline_end          ? idp.timeline_end.substring(0, 10)   : '',
    status:               idp.status                ?? 'planned',
    employee_remarks:     idp.employee_remarks      ?? '',
  }
  showModal.value = true
}

const openApprove = (idp) => {
  approveItem.value = idp
  approveForm.value = { action: 'approved', supervisor_remarks: '' }
  showApproveModal.value = true
}

const openStatus = (idp) => {
  statusItem.value = idp
  statusForm.value = { status: idp.status, employee_remarks: idp.employee_remarks ?? '' }
  showStatusModal.value = true
}

const closeModal = () => { showModal.value = false; editingItem.value = null }

const submit = () => {
  isSubmitting.value = true
  const url    = editingItem.value ? route('lnd.idp.update', editingItem.value.id) : route('lnd.idp.store')
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
  router.patch(route('lnd.idp.approve', approveItem.value.id), approveForm.value, {
    preserveState: true,
    onSuccess: () => {
      showApproveModal.value = false
      const label = approveForm.value.action === 'approved' ? 'Approved' : 'Returned for Revision'
      Swal.fire({ icon: 'success', title: label, timer: 1400, showConfirmButton: false })
    },
    onError: () => {},
    onFinish: () => { isSubmitting.value = false },
  })
}

const submitStatus = () => {
  isSubmitting.value = true
  router.patch(route('lnd.idp.status', statusItem.value.id), statusForm.value, {
    preserveState: true,
    onSuccess: () => {
      showStatusModal.value = false
      Swal.fire({ icon: 'success', title: 'Status Updated', timer: 1400, showConfirmButton: false })
    },
    onError: () => {},
    onFinish: () => { isSubmitting.value = false },
  })
}

const submitForApproval = (idp) => {
  Swal.fire({
    title: 'Submit for Approval?',
    text: 'This IDP will be sent to your supervisor for review.',
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#2563eb',
    confirmButtonText: 'Submit',
  }).then(res => {
    if (!res.isConfirmed) return
    router.patch(route('lnd.idp.submit', idp.id), {}, {
      preserveState: true,
      onSuccess: () => Swal.fire({ icon: 'success', title: 'Submitted', timer: 1400, showConfirmButton: false }),
      onError: (errs) => Swal.fire({ icon: 'error', title: 'Error', text: Object.values(errs)[0] }),
    })
  })
}

const deleteIdp = (idp) => {
  Swal.fire({
    title: 'Delete IDP?',
    text: `"${idp.competency}" will be removed.`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    confirmButtonText: 'Delete',
  }).then(res => {
    if (!res.isConfirmed) return
    router.delete(route('lnd.idp.destroy', idp.id), {
      preserveState: true,
      onSuccess: () => Swal.fire({ icon: 'success', title: 'Deleted', timer: 1200, showConfirmButton: false }),
      onError: (errs) => Swal.fire({ icon: 'error', title: 'Error', text: Object.values(errs)[0] }),
    })
  })
}

const hasFilters = computed(() =>
  employeeId.value || supervisorId.value || year.value || approvalSt.value || statusF.value || intervention.value
)
</script>

<template>
  <AdminLayout title="Individual Development Plans">
    <Head title="IDP" />

    <div class="space-y-5">

      <AppPageHeader title="Individual Development Plans (IDP)" subtitle="Track employee development activities and competency growth">
        <template #actions>
          <AppButton as="a" variant="secondary" :href="route('lnd.team-idp')">My Team's IDP</AppButton>
          <AppButton @click="openCreate">
            <PlusIcon class="h-4 w-4" />
            New IDP
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Filters -->
      <AppFilterBar>
        <select v-model="employeeId"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 min-w-[160px]">
          <option value="">All Employees</option>
          <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option>
        </select>
        <select v-model="supervisorId"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 min-w-[160px]">
          <option value="">All Supervisors</option>
          <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option>
        </select>
        <select v-model="year"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
          <option value="">All Years</option>
          <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
        </select>
        <select v-model="approvalSt"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
          <option value="">All Approval</option>
          <option value="draft">Draft</option>
          <option value="submitted">Pending</option>
          <option value="approved">Approved</option>
          <option value="returned">Returned</option>
        </select>
        <select v-model="statusF"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
          <option value="">All Progress</option>
          <option value="planned">Planned</option>
          <option value="ongoing">Ongoing</option>
          <option value="completed">Completed</option>
          <option value="deferred">Deferred</option>
          <option value="cancelled">Cancelled</option>
        </select>
        <select v-model="intervention"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
          <option value="">All Interventions</option>
          <option value="training">Training</option>
          <option value="coaching">Coaching</option>
          <option value="assignment">Assignment</option>
          <option value="self_study">Self-Study</option>
          <option value="e_learning">E-Learning</option>
          <option value="other">Other</option>
        </select>
        <template #actions>
          <AppButton v-if="hasFilters" size="sm" variant="secondary"
            @click="employeeId=''; supervisorId=''; year=''; approvalSt=''; statusF=''; intervention=''">Clear</AppButton>
        </template>
      </AppFilterBar>

      <!-- Table -->
      <AppTable :loading="isLoading" :is-empty="!idps.data?.length" :skeleton-cols="10">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Employee</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Competency</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Gap</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Intervention</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Program</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Timeline</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Year</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Approval</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Progress</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Actions</th>
          </tr>
        </template>

        <tr v-for="idp in idps.data" :key="idp.id" class="hover:bg-slate-50/60">
          <td class="px-4 py-3">
            <div class="font-medium text-sm text-slate-800">{{ idp.employee?.name ?? '—' }}</div>
            <div class="text-xs text-slate-500">Supervisor: {{ idp.supervisor?.name ?? '—' }}</div>
          </td>
          <td class="px-4 py-3">
            <div class="font-medium text-sm text-slate-800 max-w-[160px] truncate">{{ idp.competency }}</div>
            <div v-if="idp.training_need" class="text-xs text-slate-400">TNA: {{ idp.training_need.competency_area }}</div>
          </td>
          <td class="px-4 py-3 text-center">
            <div class="flex items-center justify-center gap-1 text-xs">
              <AppBadge :color="levelBadgeColor(idp.current_level)">{{ idp.current_level }}</AppBadge>
              <ChevronRightIcon class="w-3 h-3 text-slate-400" />
              <AppBadge :color="levelBadgeColor(idp.target_level)">{{ idp.target_level }}</AppBadge>
            </div>
          </td>
          <td class="px-4 py-3">
            <span class="text-xs text-slate-700">{{ interventionLabel[idp.intervention_type] ?? idp.intervention_type }}</span>
          </td>
          <td class="px-4 py-3 text-slate-600 text-xs max-w-[120px] truncate">
            {{ idp.learning_program?.title ?? '—' }}
          </td>
          <td class="px-4 py-3 text-center text-xs text-slate-600 whitespace-nowrap">
            <template v-if="idp.timeline_start">
              {{ fmt(idp.timeline_start) }}<br>
              <span class="text-slate-400">to</span><br>
              {{ fmt(idp.timeline_end) }}
            </template>
            <span v-else class="text-slate-400">—</span>
          </td>
          <td class="px-4 py-3 text-center text-sm text-slate-600">{{ idp.year }}</td>
          <td class="px-4 py-3 text-center">
            <AppBadge :color="approvalBadgeColor(idp.approval_status)">{{ approvalLabel[idp.approval_status] ?? idp.approval_status }}</AppBadge>
          </td>
          <td class="px-4 py-3 text-center">
            <AppBadge :color="statusBadgeColor(idp.status)"><span class="capitalize">{{ idp.status }}</span></AppBadge>
          </td>
          <td class="px-4 py-3">
            <div class="flex items-center justify-center gap-1 flex-wrap">
              <AppButton as="a" size="sm" variant="ghost" :href="route('lnd.idp.show', idp.id)">View</AppButton>
              <AppButton v-if="idp.approval_status !== 'approved'" size="sm" variant="ghost" @click="openEdit(idp)">Edit</AppButton>
              <AppButton v-if="idp.approval_status === 'draft' || idp.approval_status === 'returned'"
                size="sm" variant="primary" @click="submitForApproval(idp)">Submit</AppButton>
              <AppButton v-if="idp.approval_status === 'submitted'"
                size="sm" variant="success" @click="openApprove(idp)">Approve</AppButton>
              <AppButton v-if="idp.approval_status === 'approved'"
                size="sm" variant="warning" @click="openStatus(idp)">Progress</AppButton>
              <AppButton v-if="idp.approval_status !== 'approved'"
                size="sm" variant="danger" @click="deleteIdp(idp)">Delete</AppButton>
            </div>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="idp in idps.data" :key="idp.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="font-medium text-sm text-slate-800">{{ idp.employee?.name ?? '—' }}</p>
                <p class="text-xs text-slate-500">{{ idp.competency }}</p>
              </div>
              <div class="flex flex-col items-end gap-1 shrink-0">
                <AppBadge :color="approvalBadgeColor(idp.approval_status)">{{ approvalLabel[idp.approval_status] ?? idp.approval_status }}</AppBadge>
                <AppBadge :color="statusBadgeColor(idp.status)"><span class="capitalize">{{ idp.status }}</span></AppBadge>
              </div>
            </div>
            <div class="flex items-center gap-1 text-xs">
              <AppBadge :color="levelBadgeColor(idp.current_level)">{{ idp.current_level }}</AppBadge>
              <ChevronRightIcon class="w-3 h-3 text-slate-400" />
              <AppBadge :color="levelBadgeColor(idp.target_level)">{{ idp.target_level }}</AppBadge>
              <span class="ml-2 text-slate-500">{{ interventionLabel[idp.intervention_type] ?? idp.intervention_type }}</span>
            </div>
            <p class="text-xs text-slate-500">
              <template v-if="idp.timeline_start">{{ fmt(idp.timeline_start) }} – {{ fmt(idp.timeline_end) }}</template>
              <template v-else>—</template>
              &middot; {{ idp.year }}
            </p>
            <div class="flex items-center gap-1 flex-wrap pt-1">
              <AppButton as="a" size="sm" variant="ghost" :href="route('lnd.idp.show', idp.id)">View</AppButton>
              <AppButton v-if="idp.approval_status !== 'approved'" size="sm" variant="ghost" @click="openEdit(idp)">Edit</AppButton>
              <AppButton v-if="idp.approval_status === 'draft' || idp.approval_status === 'returned'"
                size="sm" variant="primary" @click="submitForApproval(idp)">Submit</AppButton>
              <AppButton v-if="idp.approval_status === 'submitted'"
                size="sm" variant="success" @click="openApprove(idp)">Approve</AppButton>
              <AppButton v-if="idp.approval_status === 'approved'"
                size="sm" variant="warning" @click="openStatus(idp)">Progress</AppButton>
              <AppButton v-if="idp.approval_status !== 'approved'"
                size="sm" variant="danger" @click="deleteIdp(idp)">Delete</AppButton>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No IDPs found." />
        </template>

        <template #footer>
          <PaginationControl :links="idps.links" :current-page="idps.current_page" :total-pages="idps.last_page" :total="idps.total" />
        </template>
      </AppTable>
    </div>

    <!-- Create / Edit Modal -->
    <AppModal :show="showModal" :title="editingItem ? 'Edit IDP' : 'New IDP'" size="2xl" @close="closeModal">
      <div class="space-y-4">

        <!-- Employee + Supervisor -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Employee <span class="text-danger-600">*</span></label>
            <select v-model="form.employee_id" required :disabled="!!editingItem"
              class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 disabled:bg-slate-50">
              <option value="">— Select —</option>
              <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Supervisor <span class="text-danger-600">*</span></label>
            <select v-model="form.supervisor_id" required
              class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
              <option value="">— Select —</option>
              <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option>
            </select>
          </div>
        </div>

        <!-- Year + Learning Program -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Year <span class="text-danger-600">*</span></label>
            <input v-model="form.year" type="number" min="2000" max="2099" required
              class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Learning Program</label>
            <select v-model="form.learning_program_id"
              class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
              <option value="">— None —</option>
              <option v-for="p in programs" :key="p.id" :value="p.id">{{ p.title }}</option>
            </select>
          </div>
        </div>

        <!-- Competency -->
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Competency <span class="text-danger-600">*</span></label>
          <input v-model="form.competency" type="text" required
            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
        </div>

        <!-- Current / Target Level -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Current Level <span class="text-danger-600">*</span></label>
            <select v-model="form.current_level" required
              class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
              <option value="none">None</option>
              <option value="basic">Basic</option>
              <option value="intermediate">Intermediate</option>
              <option value="advanced">Advanced</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Target Level <span class="text-danger-600">*</span></label>
            <select v-model="form.target_level" required
              class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
              <option value="none">None</option>
              <option value="basic">Basic</option>
              <option value="intermediate">Intermediate</option>
              <option value="advanced">Advanced</option>
            </select>
          </div>
        </div>

        <!-- Development Activity -->
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Development Activity <span class="text-danger-600">*</span></label>
          <textarea v-model="form.development_activity" rows="2" required
            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 resize-none" />
        </div>

        <!-- Intervention + Status -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Intervention Type <span class="text-danger-600">*</span></label>
            <select v-model="form.intervention_type" required
              class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
              <option value="training">Training</option>
              <option value="coaching">Coaching</option>
              <option value="assignment">Assignment</option>
              <option value="self_study">Self-Study</option>
              <option value="e_learning">E-Learning</option>
              <option value="other">Other</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Progress Status <span class="text-danger-600">*</span></label>
            <select v-model="form.status" required
              class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
              <option value="planned">Planned</option>
              <option value="ongoing">Ongoing</option>
              <option value="completed">Completed</option>
              <option value="deferred">Deferred</option>
              <option value="cancelled">Cancelled</option>
            </select>
          </div>
        </div>

        <!-- Timeline -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Start Date</label>
            <input v-model="form.timeline_start" type="date"
              class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">End Date</label>
            <input v-model="form.timeline_end" type="date" :min="form.timeline_start"
              class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
          </div>
        </div>

        <!-- Employee Remarks -->
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Employee Remarks</label>
          <textarea v-model="form.employee_remarks" rows="2"
            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 resize-none" />
        </div>
      </div>

      <template #footer>
        <AppButton variant="secondary" @click="closeModal">Cancel</AppButton>
        <AppButton :loading="isSubmitting" @click="submit">{{ editingItem ? 'Update' : 'Create' }}</AppButton>
      </template>
    </AppModal>

    <!-- Approve / Return Modal -->
    <AppModal :show="showApproveModal" title="Review IDP" @close="showApproveModal = false">
      <div class="space-y-4">
        <div v-if="approveItem" class="rounded-lg bg-slate-50 p-3 text-sm text-slate-700">
          <span class="font-medium">{{ approveItem.employee?.name }}</span> — {{ approveItem.competency }}
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-2">Decision <span class="text-danger-600">*</span></label>
          <div class="flex gap-4">
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="radio" v-model="approveForm.action" value="approved" />
              <span class="text-sm text-slate-700">Approve</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="radio" v-model="approveForm.action" value="returned" />
              <span class="text-sm text-slate-700">Return for Revision</span>
            </label>
          </div>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Supervisor Remarks</label>
          <textarea v-model="approveForm.supervisor_remarks" rows="3" placeholder="Optional remarks…"
            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 resize-none" />
        </div>
      </div>
      <template #footer>
        <AppButton variant="secondary" @click="showApproveModal = false">Cancel</AppButton>
        <AppButton :variant="approveForm.action === 'approved' ? 'primary' : 'danger'" :loading="isSubmitting" @click="submitApprove">
          {{ approveForm.action === 'approved' ? 'Approve' : 'Return' }}
        </AppButton>
      </template>
    </AppModal>

    <!-- Update Progress Modal -->
    <AppModal :show="showStatusModal" title="Update Progress" @close="showStatusModal = false">
      <div class="space-y-4">
        <div v-if="statusItem" class="rounded-lg bg-slate-50 p-3 text-sm text-slate-700">
          <span class="font-medium">{{ statusItem.employee?.name }}</span> — {{ statusItem.competency }}
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Progress Status <span class="text-danger-600">*</span></label>
          <select v-model="statusForm.status" required
            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
            <option value="planned">Planned</option>
            <option value="ongoing">Ongoing</option>
            <option value="completed">Completed</option>
            <option value="deferred">Deferred</option>
            <option value="cancelled">Cancelled</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Employee Remarks</label>
          <textarea v-model="statusForm.employee_remarks" rows="3"
            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 resize-none" />
        </div>
      </div>
      <template #footer>
        <AppButton variant="secondary" @click="showStatusModal = false">Cancel</AppButton>
        <AppButton :loading="isSubmitting" @click="submitStatus">Update</AppButton>
      </template>
    </AppModal>
  </AdminLayout>
</template>
