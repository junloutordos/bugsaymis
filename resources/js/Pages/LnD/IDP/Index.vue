<script setup>
import { ref, watch, computed } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Swal from 'sweetalert2'

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
const approvalColors = {
  draft:     'bg-gray-100 text-gray-600',
  submitted: 'bg-yellow-100 text-yellow-700',
  approved:  'bg-green-100 text-green-700',
  returned:  'bg-red-100 text-red-600',
}
const approvalLabel = {
  draft:     'Draft',
  submitted: 'Pending',
  approved:  'Approved',
  returned:  'Returned',
}
const statusColors = {
  planned:   'bg-blue-100 text-blue-700',
  ongoing:   'bg-yellow-100 text-yellow-700',
  completed: 'bg-green-100 text-green-700',
  deferred:  'bg-orange-100 text-orange-700',
  cancelled: 'bg-red-100 text-red-600',
}
const interventionLabel = {
  training:     'Training',
  coaching:     'Coaching',
  assignment:   'Assignment',
  self_study:   'Self-Study',
  e_learning:   'E-Learning',
  other:        'Other',
}
const levelColors = {
  none:         'bg-gray-100 text-gray-500',
  basic:        'bg-blue-100 text-blue-600',
  intermediate: 'bg-indigo-100 text-indigo-700',
  advanced:     'bg-purple-100 text-purple-700',
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

    <div class="p-6 space-y-5">

      <!-- Header -->
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 class="text-xl font-bold text-gray-800">Individual Development Plans (IDP)</h1>
          <p class="text-sm text-gray-500">Track employee development activities and competency growth</p>
        </div>
        <div class="flex gap-2">
          <a :href="route('lnd.team-idp')"
            class="inline-flex items-center gap-1 rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
            My Team's IDP
          </a>
          <button @click="openCreate"
            class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            New IDP
          </button>
        </div>
      </div>

      <!-- Filters -->
      <div class="flex flex-wrap gap-2">
        <select v-model="employeeId" class="rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-400 focus:outline-none min-w-[160px]">
          <option value="">All Employees</option>
          <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option>
        </select>
        <select v-model="supervisorId" class="rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-400 focus:outline-none min-w-[160px]">
          <option value="">All Supervisors</option>
          <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option>
        </select>
        <select v-model="year" class="rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-400 focus:outline-none">
          <option value="">All Years</option>
          <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
        </select>
        <select v-model="approvalSt" class="rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-400 focus:outline-none">
          <option value="">All Approval</option>
          <option value="draft">Draft</option>
          <option value="submitted">Pending</option>
          <option value="approved">Approved</option>
          <option value="returned">Returned</option>
        </select>
        <select v-model="statusF" class="rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-400 focus:outline-none">
          <option value="">All Progress</option>
          <option value="planned">Planned</option>
          <option value="ongoing">Ongoing</option>
          <option value="completed">Completed</option>
          <option value="deferred">Deferred</option>
          <option value="cancelled">Cancelled</option>
        </select>
        <select v-model="intervention" class="rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-400 focus:outline-none">
          <option value="">All Interventions</option>
          <option value="training">Training</option>
          <option value="coaching">Coaching</option>
          <option value="assignment">Assignment</option>
          <option value="self_study">Self-Study</option>
          <option value="e_learning">E-Learning</option>
          <option value="other">Other</option>
        </select>
        <button v-if="hasFilters"
          @click="employeeId=''; supervisorId=''; year=''; approvalSt=''; statusF=''; intervention=''"
          class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-600 hover:bg-gray-100">Clear</button>
      </div>

      <!-- Table -->
      <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
        <div v-if="isLoading" class="flex items-center justify-center py-12 text-gray-400 text-sm">Loading…</div>
        <table v-else class="min-w-full divide-y divide-gray-200 text-sm">
          <thead class="bg-gray-50 text-xs font-semibold text-gray-500 uppercase tracking-wide">
            <tr>
              <th class="px-4 py-3 text-left">Employee</th>
              <th class="px-4 py-3 text-left">Competency</th>
              <th class="px-4 py-3 text-center">Gap</th>
              <th class="px-4 py-3 text-left">Intervention</th>
              <th class="px-4 py-3 text-left">Program</th>
              <th class="px-4 py-3 text-center">Timeline</th>
              <th class="px-4 py-3 text-center">Year</th>
              <th class="px-4 py-3 text-center">Approval</th>
              <th class="px-4 py-3 text-center">Progress</th>
              <th class="px-4 py-3 text-center">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-if="idps.data.length === 0">
              <td colspan="10" class="py-10 text-center text-gray-400">No IDPs found.</td>
            </tr>
            <tr v-for="idp in idps.data" :key="idp.id" class="hover:bg-gray-50">
              <td class="px-4 py-3">
                <div class="font-medium text-gray-800">{{ idp.employee?.name ?? '—' }}</div>
                <div class="text-xs text-gray-500">Supervisor: {{ idp.supervisor?.name ?? '—' }}</div>
              </td>
              <td class="px-4 py-3">
                <div class="font-medium text-gray-800 max-w-[160px] truncate">{{ idp.competency }}</div>
                <div v-if="idp.training_need" class="text-xs text-gray-400">TNA: {{ idp.training_need.competency_area }}</div>
              </td>
              <td class="px-4 py-3 text-center">
                <div class="flex items-center justify-center gap-1 text-xs">
                  <span :class="['rounded px-1.5 py-0.5 font-medium', levelColors[idp.current_level]]">{{ idp.current_level }}</span>
                  <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                  <span :class="['rounded px-1.5 py-0.5 font-medium', levelColors[idp.target_level]]">{{ idp.target_level }}</span>
                </div>
              </td>
              <td class="px-4 py-3">
                <span class="text-xs text-gray-700">{{ interventionLabel[idp.intervention_type] ?? idp.intervention_type }}</span>
              </td>
              <td class="px-4 py-3 text-gray-600 text-xs max-w-[120px] truncate">
                {{ idp.learning_program?.title ?? '—' }}
              </td>
              <td class="px-4 py-3 text-center text-xs text-gray-600 whitespace-nowrap">
                <template v-if="idp.timeline_start">
                  {{ fmt(idp.timeline_start) }}<br>
                  <span class="text-gray-400">to</span><br>
                  {{ fmt(idp.timeline_end) }}
                </template>
                <span v-else class="text-gray-400">—</span>
              </td>
              <td class="px-4 py-3 text-center text-gray-600">{{ idp.year }}</td>
              <td class="px-4 py-3 text-center">
                <span :class="['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium', approvalColors[idp.approval_status] ?? 'bg-gray-100 text-gray-600']">
                  {{ approvalLabel[idp.approval_status] ?? idp.approval_status }}
                </span>
              </td>
              <td class="px-4 py-3 text-center">
                <span :class="['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium capitalize', statusColors[idp.status] ?? 'bg-gray-100 text-gray-600']">
                  {{ idp.status }}
                </span>
              </td>
              <td class="px-4 py-3 text-center">
                <div class="flex items-center justify-center gap-1 flex-wrap">
                  <a :href="route('lnd.idp.show', idp.id)"
                    class="rounded px-2 py-1 text-xs font-medium text-blue-600 hover:bg-blue-50">View</a>
                  <button v-if="idp.approval_status !== 'approved'"
                    @click="openEdit(idp)"
                    class="rounded px-2 py-1 text-xs font-medium text-gray-600 hover:bg-gray-100">Edit</button>
                  <button v-if="idp.approval_status === 'draft' || idp.approval_status === 'returned'"
                    @click="submitForApproval(idp)"
                    class="rounded px-2 py-1 text-xs font-medium text-indigo-600 hover:bg-indigo-50">Submit</button>
                  <button v-if="idp.approval_status === 'submitted'"
                    @click="openApprove(idp)"
                    class="rounded px-2 py-1 text-xs font-medium text-green-600 hover:bg-green-50">Approve</button>
                  <button v-if="idp.approval_status === 'approved'"
                    @click="openStatus(idp)"
                    class="rounded px-2 py-1 text-xs font-medium text-yellow-600 hover:bg-yellow-50">Progress</button>
                  <button v-if="idp.approval_status !== 'approved'"
                    @click="deleteIdp(idp)"
                    class="rounded px-2 py-1 text-xs font-medium text-red-500 hover:bg-red-50">Delete</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="idps.last_page > 1" class="flex items-center justify-between text-sm text-gray-600">
        <span>Showing {{ idps.from }}–{{ idps.to }} of {{ idps.total }}</span>
        <div class="flex gap-1">
          <button v-for="p in idps.links" :key="p.label"
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
        <div class="w-full max-w-2xl max-h-[92vh] overflow-y-auto rounded-2xl bg-white shadow-2xl">
          <div class="flex items-center justify-between border-b px-6 py-4">
            <h2 class="text-lg font-bold text-gray-800">{{ editingItem ? 'Edit IDP' : 'New IDP' }}</h2>
            <button @click="closeModal" class="text-gray-400 hover:text-gray-600">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>
          <form @submit.prevent="submit" class="p-6 space-y-4">

            <!-- Employee + Supervisor -->
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Employee <span class="text-red-500">*</span></label>
                <select v-model="form.employee_id" required :disabled="!!editingItem"
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none disabled:bg-gray-50">
                  <option value="">— Select —</option>
                  <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Supervisor <span class="text-red-500">*</span></label>
                <select v-model="form.supervisor_id" required
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none">
                  <option value="">— Select —</option>
                  <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option>
                </select>
              </div>
            </div>

            <!-- Year + Learning Program -->
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Year <span class="text-red-500">*</span></label>
                <input v-model="form.year" type="number" min="2000" max="2099" required
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Learning Program</label>
                <select v-model="form.learning_program_id"
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none">
                  <option value="">— None —</option>
                  <option v-for="p in programs" :key="p.id" :value="p.id">{{ p.title }}</option>
                </select>
              </div>
            </div>

            <!-- Competency -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Competency <span class="text-red-500">*</span></label>
              <input v-model="form.competency" type="text" required
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none" />
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

            <!-- Development Activity -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Development Activity <span class="text-red-500">*</span></label>
              <textarea v-model="form.development_activity" rows="2" required
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none resize-none" />
            </div>

            <!-- Intervention + Status -->
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Intervention Type <span class="text-red-500">*</span></label>
                <select v-model="form.intervention_type" required
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none">
                  <option value="training">Training</option>
                  <option value="coaching">Coaching</option>
                  <option value="assignment">Assignment</option>
                  <option value="self_study">Self-Study</option>
                  <option value="e_learning">E-Learning</option>
                  <option value="other">Other</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Progress Status <span class="text-red-500">*</span></label>
                <select v-model="form.status" required
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none">
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
                <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                <input v-model="form.timeline_start" type="date"
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                <input v-model="form.timeline_end" type="date" :min="form.timeline_start"
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none" />
              </div>
            </div>

            <!-- Employee Remarks -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Employee Remarks</label>
              <textarea v-model="form.employee_remarks" rows="2"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none resize-none" />
            </div>

            <div class="flex justify-end gap-3 pt-2">
              <button type="button" @click="closeModal"
                class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
              <button type="submit" :disabled="isSubmitting"
                class="rounded-lg bg-blue-600 px-5 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                {{ isSubmitting ? 'Saving…' : (editingItem ? 'Update' : 'Create') }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- Approve / Return Modal -->
    <Teleport to="body">
      <div v-if="showApproveModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div class="w-full max-w-md rounded-2xl bg-white shadow-2xl">
          <div class="flex items-center justify-between border-b px-6 py-4">
            <h2 class="text-lg font-bold text-gray-800">Review IDP</h2>
            <button @click="showApproveModal = false" class="text-gray-400 hover:text-gray-600">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>
          <div class="p-6 space-y-4">
            <div v-if="approveItem" class="rounded-lg bg-gray-50 p-3 text-sm text-gray-700">
              <span class="font-medium">{{ approveItem.employee?.name }}</span> — {{ approveItem.competency }}
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Decision <span class="text-red-500">*</span></label>
              <div class="flex gap-4">
                <label class="flex items-center gap-2 cursor-pointer">
                  <input type="radio" v-model="approveForm.action" value="approved" class="text-green-600" />
                  <span class="text-sm text-gray-700">Approve</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                  <input type="radio" v-model="approveForm.action" value="returned" class="text-red-500" />
                  <span class="text-sm text-gray-700">Return for Revision</span>
                </label>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Supervisor Remarks</label>
              <textarea v-model="approveForm.supervisor_remarks" rows="3" placeholder="Optional remarks…"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none resize-none" />
            </div>
            <div class="flex justify-end gap-3 pt-2">
              <button @click="showApproveModal = false"
                class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
              <button @click="submitApprove" :disabled="isSubmitting"
                :class="['rounded-lg px-5 py-2 text-sm font-medium text-white disabled:opacity-50', approveForm.action === 'approved' ? 'bg-green-600 hover:bg-green-700' : 'bg-red-500 hover:bg-red-600']">
                {{ isSubmitting ? 'Saving…' : (approveForm.action === 'approved' ? 'Approve' : 'Return') }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Update Progress Modal -->
    <Teleport to="body">
      <div v-if="showStatusModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div class="w-full max-w-md rounded-2xl bg-white shadow-2xl">
          <div class="flex items-center justify-between border-b px-6 py-4">
            <h2 class="text-lg font-bold text-gray-800">Update Progress</h2>
            <button @click="showStatusModal = false" class="text-gray-400 hover:text-gray-600">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>
          <div class="p-6 space-y-4">
            <div v-if="statusItem" class="rounded-lg bg-gray-50 p-3 text-sm text-gray-700">
              <span class="font-medium">{{ statusItem.employee?.name }}</span> — {{ statusItem.competency }}
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Progress Status <span class="text-red-500">*</span></label>
              <select v-model="statusForm.status" required
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none">
                <option value="planned">Planned</option>
                <option value="ongoing">Ongoing</option>
                <option value="completed">Completed</option>
                <option value="deferred">Deferred</option>
                <option value="cancelled">Cancelled</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Employee Remarks</label>
              <textarea v-model="statusForm.employee_remarks" rows="3"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none resize-none" />
            </div>
            <div class="flex justify-end gap-3 pt-2">
              <button @click="showStatusModal = false"
                class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
              <button @click="submitStatus" :disabled="isSubmitting"
                class="rounded-lg bg-blue-600 px-5 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                {{ isSubmitting ? 'Saving…' : 'Update' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </AdminLayout>
</template>
