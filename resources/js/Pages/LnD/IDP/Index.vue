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
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Individual Development Plans (IDP)</h1>
          <p class="text-sm text-slate-500">Track employee development activities and competency growth</p>
        </div>
        <div class="flex gap-2">
          <a :href="route('lnd.team-idp')"
            class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
            My Team's IDP
          </a>
          <button @click="openCreate"
            class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            New IDP
          </button>
        </div>
      </div>

      <!-- Filters -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 mb-4 flex flex-wrap items-center gap-3">
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
        <button v-if="hasFilters"
          @click="employeeId=''; supervisorId=''; year=''; approvalSt=''; statusF=''; intervention=''"
          class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-3 py-2 rounded-lg text-sm font-medium transition-colors">Clear</button>
      </div>

      <!-- Table -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
          <div v-if="isLoading" class="flex items-center justify-center py-12 text-slate-400 text-sm">Loading…</div>
          <table v-else class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
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
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-if="idps.data.length === 0">
                <td colspan="10" class="py-16 text-center text-slate-400 text-sm">No IDPs found.</td>
              </tr>
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
                    <span :class="['rounded px-1.5 py-0.5 font-medium', levelColors[idp.current_level]]">{{ idp.current_level }}</span>
                    <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    <span :class="['rounded px-1.5 py-0.5 font-medium', levelColors[idp.target_level]]">{{ idp.target_level }}</span>
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
                  <span :class="['inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium', approvalColors[idp.approval_status] ?? 'bg-slate-100 text-slate-600']">
                    {{ approvalLabel[idp.approval_status] ?? idp.approval_status }}
                  </span>
                </td>
                <td class="px-4 py-3 text-center">
                  <span :class="['inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium capitalize', statusColors[idp.status] ?? 'bg-slate-100 text-slate-600']">
                    {{ idp.status }}
                  </span>
                </td>
                <td class="px-4 py-3 text-center">
                  <div class="flex items-center justify-center gap-1 flex-wrap">
                    <a :href="route('lnd.idp.show', idp.id)"
                      class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors text-xs font-medium">View</a>
                    <button v-if="idp.approval_status !== 'approved'"
                      @click="openEdit(idp)"
                      class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors text-xs font-medium">Edit</button>
                    <button v-if="idp.approval_status === 'draft' || idp.approval_status === 'returned'"
                      @click="submitForApproval(idp)"
                      class="p-1.5 rounded-lg hover:bg-slate-100 text-indigo-600 hover:text-indigo-700 transition-colors text-xs font-medium">Submit</button>
                    <button v-if="idp.approval_status === 'submitted'"
                      @click="openApprove(idp)"
                      class="p-1.5 rounded-lg hover:bg-slate-100 text-emerald-600 hover:text-emerald-700 transition-colors text-xs font-medium">Approve</button>
                    <button v-if="idp.approval_status === 'approved'"
                      @click="openStatus(idp)"
                      class="p-1.5 rounded-lg hover:bg-slate-100 text-amber-600 hover:text-amber-700 transition-colors text-xs font-medium">Progress</button>
                    <button v-if="idp.approval_status !== 'approved'"
                      @click="deleteIdp(idp)"
                      class="p-1.5 rounded-lg hover:bg-slate-100 text-red-500 hover:text-red-600 transition-colors text-xs font-medium">Delete</button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="idps.last_page > 1"
        class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-sm text-slate-600">
        <span>Showing {{ idps.from }}–{{ idps.to }} of {{ idps.total }}</span>
        <div class="flex gap-1">
          <button v-for="p in idps.links" :key="p.label"
            @click="p.url && goToPage(new URL(p.url).searchParams.get('page'))"
            :disabled="!p.url"
            :class="['px-3 py-1 rounded border text-xs', p.active ? 'bg-indigo-600 text-white border-indigo-600' : 'border-slate-200 hover:bg-slate-50 disabled:opacity-40 text-slate-600']"
            v-html="p.label" />
        </div>
      </div>
    </div>

    <!-- Create / Edit Modal -->
    <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl max-h-[92vh] overflow-y-auto">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-800">{{ editingItem ? 'Edit IDP' : 'New IDP' }}</h2>
            <button @click="closeModal" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>
          <form @submit.prevent="submit" class="px-6 py-5 space-y-4">

            <!-- Employee + Supervisor -->
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Employee <span class="text-red-500">*</span></label>
                <select v-model="form.employee_id" required :disabled="!!editingItem"
                  class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 disabled:bg-slate-50">
                  <option value="">— Select —</option>
                  <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Supervisor <span class="text-red-500">*</span></label>
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
                <label class="block text-xs font-medium text-slate-600 mb-1">Year <span class="text-red-500">*</span></label>
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
              <label class="block text-xs font-medium text-slate-600 mb-1">Competency <span class="text-red-500">*</span></label>
              <input v-model="form.competency" type="text" required
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
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

            <!-- Development Activity -->
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Development Activity <span class="text-red-500">*</span></label>
              <textarea v-model="form.development_activity" rows="2" required
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 resize-none" />
            </div>

            <!-- Intervention + Status -->
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Intervention Type <span class="text-red-500">*</span></label>
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
                <label class="block text-xs font-medium text-slate-600 mb-1">Progress Status <span class="text-red-500">*</span></label>
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

            <div class="flex justify-end gap-2 pt-2">
              <button type="button" @click="closeModal"
                class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">Cancel</button>
              <button type="submit" :disabled="isSubmitting"
                class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50">
                {{ isSubmitting ? 'Saving…' : (editingItem ? 'Update' : 'Create') }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- Approve / Return Modal -->
    <Teleport to="body">
      <div v-if="showApproveModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-800">Review IDP</h2>
            <button @click="showApproveModal = false" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>
          <div class="px-6 py-5 space-y-4">
            <div v-if="approveItem" class="rounded-lg bg-slate-50 p-3 text-sm text-slate-700">
              <span class="font-medium">{{ approveItem.employee?.name }}</span> — {{ approveItem.competency }}
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-2">Decision <span class="text-red-500">*</span></label>
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
          <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
            <button @click="showApproveModal = false"
              class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">Cancel</button>
            <button @click="submitApprove" :disabled="isSubmitting"
              :class="['inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-white transition-colors shadow-sm disabled:opacity-50', approveForm.action === 'approved' ? 'bg-indigo-600 hover:bg-indigo-700' : 'bg-red-600 hover:bg-red-700']">
              {{ isSubmitting ? 'Saving…' : (approveForm.action === 'approved' ? 'Approve' : 'Return') }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Update Progress Modal -->
    <Teleport to="body">
      <div v-if="showStatusModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-800">Update Progress</h2>
            <button @click="showStatusModal = false" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>
          <div class="px-6 py-5 space-y-4">
            <div v-if="statusItem" class="rounded-lg bg-slate-50 p-3 text-sm text-slate-700">
              <span class="font-medium">{{ statusItem.employee?.name }}</span> — {{ statusItem.competency }}
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Progress Status <span class="text-red-500">*</span></label>
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
          <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
            <button @click="showStatusModal = false"
              class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">Cancel</button>
            <button @click="submitStatus" :disabled="isSubmitting"
              class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50">
              {{ isSubmitting ? 'Saving…' : 'Update' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </AdminLayout>
</template>
