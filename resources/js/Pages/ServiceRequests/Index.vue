<template>
  <Head title="Request for Services" />
  <AdminLayout title="Request for Services">
    <div class="space-y-5">

      <AppPageHeader title="Request for Services" subtitle="Manage reproduction, security, and janitorial service requests">
        <template #actions>
          <AppButton @click="handleNewRequest">+ New Request</AppButton>
        </template>
      </AppPageHeader>

      <!-- Filters -->
      <AppFilterBar>
        <input v-model="searchQuery" type="text" placeholder="Search requests…"
               @keydown.enter.prevent="applyFilters"
               class="w-full sm:w-72 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
        <template #actions>
          <span v-if="isLoading" class="text-xs text-slate-400">Searching...</span>
          <AppButton size="sm" :disabled="isLoading" @click="applyFilters">Search</AppButton>
          <AppButton v-if="searchQuery" size="sm" variant="secondary" :disabled="isLoading" @click="clearFilters">Clear</AppButton>
        </template>
      </AppFilterBar>

      <!-- Table -->
      <AppTable :is-empty="filteredRequests.length === 0" :skeleton-cols="hasAnyRole('Administrator', 'GSU Head', 'DivisionChief') ? 8 : 7">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">#</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Service</th>
            <th v-if="hasAnyRole('Administrator', 'GSU Head', 'DivisionChief')" class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Requestor</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Date Needed</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Time Needed</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Purpose(s)</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Status</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Actions</th>
          </tr>
        </template>

        <tr v-for="r in filteredRequests" :key="r.id" class="hover:bg-indigo-50/40">
          <td class="px-4 py-3 text-sm text-slate-700">{{ r.id }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">
            {{ r.service_type }}
            <div v-if="r.service_type==='Reproduction'" class="text-xs text-slate-500">{{ r.copies }} copies × {{ r.sheets_per_set }} sheets</div>
          </td>
          <td v-if="hasAnyRole('Administrator', 'GSU Head', 'DivisionChief')" class="px-4 py-3 text-sm text-slate-700">{{ r.requester?.name ?? '—' }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ r.date_needed }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ r.time_needed || '—' }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ r.purposes || '—' }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">
            <AppBadge :color="statusColor(r.status)">{{ r.status }}</AppBadge>
          </td>
          <td class="px-4 py-3">
            <div class="flex items-center gap-1.5">
              <AppIconButton v-if="r.status === 'Pending' && roleNames.some(rn => rn !== 'Staff')" label="Edit request" size="sm" @click.prevent="openEdit(r)">
                <PencilSquareIcon class="w-4 h-4" />
              </AppIconButton>
              <AppIconButton v-if="r.status === 'Pending' && roleNames.some(rn => rn !== 'Staff')" label="Delete request" variant="danger" size="sm" @click.prevent="remove(r.id)">
                <TrashIcon class="w-4 h-4" />
              </AppIconButton>
              <AppIconButton v-if="r.status === 'Pending' && hasRole('DivisionChief')" label="Approve request" variant="success" size="sm" @click.prevent="approveRequest(r)">
                <CheckIcon class="w-4 h-4" />
              </AppIconButton>
              <AppIconButton v-if="r.status === 'Pending' && hasRole('DivisionChief')" label="Decline request" variant="danger" size="sm" @click.prevent="declineRequest(r)">
                <XMarkIcon class="w-4 h-4" />
              </AppIconButton>
              <AppButton v-if="r.status === 'Approved' && r.requestor_id === page.props.auth.user.id" size="sm" variant="success" @click.prevent="openCsmModal(r)">Confirm &amp; Rate</AppButton>
              <AppButton v-if="canPrint(r)" as="a" :href="route('service-requests.print', r.id)" target="_blank" variant="secondary" size="sm" title="Print">
                <PrinterIcon class="w-4 h-4" />
              </AppButton>
            </div>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="r in filteredRequests" :key="r.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0">
                <p class="text-xs text-slate-500">Request #{{ r.id }}</p>
                <p class="text-sm font-semibold text-slate-800 mt-0.5">{{ r.service_type ?? '—' }}</p>
                <p v-if="hasAnyRole('Administrator', 'GSU Head', 'DivisionChief')" class="text-xs text-slate-600 mt-1">Requestor: {{ r.requester?.name ?? '—' }}</p>
              </div>
              <div class="shrink-0 text-right text-xs text-slate-600">
                <div>{{ r.date_needed ?? '—' }}</div>
                <div class="text-slate-400">{{ r.time_needed ?? '—' }}</div>
              </div>
            </div>
            <div class="space-y-1 text-xs text-slate-700">
              <div><span class="font-medium text-slate-500">Purpose:</span> {{ r.purposes || '—' }}</div>
              <div class="flex items-center gap-2"><span class="font-medium text-slate-500">Status:</span>
                <AppBadge :color="statusColor(r.status)">{{ r.status }}</AppBadge>
              </div>
            </div>
            <div class="flex flex-wrap items-center gap-2 pt-1">
              <AppIconButton v-if="r.status === 'Pending' && roleNames.some(rn => rn !== 'Staff')" label="Edit request" size="sm" @click.prevent="openEdit(r)">
                <PencilSquareIcon class="w-4 h-4" />
              </AppIconButton>
              <AppIconButton v-if="r.status === 'Pending' && roleNames.some(rn => rn !== 'Staff')" label="Delete request" variant="danger" size="sm" @click.prevent="remove(r.id)">
                <TrashIcon class="w-4 h-4" />
              </AppIconButton>
              <AppButton v-if="canPrint(r)" as="a" :href="route('service-requests.print', r.id)" target="_blank" variant="secondary" size="sm" title="Print">
                <PrinterIcon class="w-4 h-4" />
              </AppButton>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No requests" />
        </template>

        <template #footer>
          <PaginationControl
            :current-page="currentPage"
            :total-pages="totalPages"
            :total="props.requests?.total ?? 0"
            @prev="goToPage(currentPage - 1)"
            @next="goToPage(currentPage + 1)"
            @page="goToPage"
          />
        </template>
      </AppTable>

      <!-- Modal -->
      <AppModal :show="showModal" :title="editingId ? 'Edit Service Request' : 'New Service Request'" @close="closeModal">
        <div class="space-y-4">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Service requested</label>
            <select v-model="form.service_type" @change="validateField('service_type')" :class="['w-full rounded-lg border bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500', fieldErrors.service_type ? 'border-red-400' : 'border-slate-200 focus:border-indigo-400']">
              <option>Reproduction</option>
              <option>Security</option>
              <option>Janitorial</option>
            </select>
            <p v-if="fieldErrors.service_type" class="mt-1 text-xs text-red-600">{{ fieldErrors.service_type }}</p>
          </div>

          <div v-if="form.service_type === 'Reproduction'" class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Number of copies</label>
              <input v-model.number="form.copies" @input="validateField('copies')" type="number" min="1" :class="['w-full rounded-lg border bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500', fieldErrors.copies ? 'border-red-400' : 'border-slate-200 focus:border-indigo-400']" />
              <p v-if="fieldErrors.copies" class="mt-1 text-xs text-red-600">{{ fieldErrors.copies }}</p>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Sheets per set</label>
              <input v-model.number="form.sheets_per_set" @input="validateField('sheets_per_set')" type="number" min="1" :class="['w-full rounded-lg border bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500', fieldErrors.sheets_per_set ? 'border-red-400' : 'border-slate-200 focus:border-indigo-400']" />
              <p v-if="fieldErrors.sheets_per_set" class="mt-1 text-xs text-red-600">{{ fieldErrors.sheets_per_set }}</p>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Date Needed</label>
              <input v-model="form.date_needed" @change="validateField('date_needed')" type="date" :class="['w-full rounded-lg border bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500', fieldErrors.date_needed ? 'border-red-400' : 'border-slate-200 focus:border-indigo-400']" />
              <p v-if="fieldErrors.date_needed" class="mt-1 text-xs text-red-600">{{ fieldErrors.date_needed }}</p>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Time Needed</label>
              <input v-model="form.time_needed" @change="validateField('time_needed')" type="time" :class="['w-full rounded-lg border bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500', fieldErrors.time_needed ? 'border-red-400' : 'border-slate-200 focus:border-indigo-400']" />
              <p v-if="fieldErrors.time_needed" class="mt-1 text-xs text-red-600">{{ fieldErrors.time_needed }}</p>
            </div>
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Purpose(s)</label>
            <input v-model="form.purposes" @input="validateField('purposes')" :class="['w-full rounded-lg border bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500', fieldErrors.purposes ? 'border-red-400' : 'border-slate-200 focus:border-indigo-400']" />
            <p v-if="fieldErrors.purposes" class="mt-1 text-xs text-red-600">{{ fieldErrors.purposes }}</p>
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Details</label>
            <textarea v-model="form.details" @input="validateField('details')" :class="['w-full rounded-lg border bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500', fieldErrors.details ? 'border-red-400' : 'border-slate-200 focus:border-indigo-400']" rows="4"></textarea>
            <p v-if="fieldErrors.details" class="mt-1 text-xs text-red-600">{{ fieldErrors.details }}</p>
          </div>
        </div>

        <template #footer>
          <AppButton variant="secondary" @click="closeModal">Cancel</AppButton>
          <AppButton :loading="form.processing" @click="editingId ? submit() : openPinModal()">
            {{ editingId ? 'Update' : 'Submit' }}
          </AppButton>
        </template>
      </AppModal>
    </div>
    <DigitalSignaturePin
      :show="showSubmitPin"
      :hasPin="hasPin"
      :signatureUri="signatureUri"
      :loading="pinLoading"
      confirmLabel="Sign & Submit"
      @confirm="handlePinConfirm"
      @cancel="handlePinCancel"
    />
    <CsmForm
      :show="showCsmModal"
      respondable-type="service-request"
      :respondable-id="requestToCsm?.id ?? 0"
      :transaction-date="requestToCsm?.created_at?.slice(0,10) ?? ''"
      office-availed="General Services Unit"
      service-key="others"
      service-other-label="Request for Service"
      @close="showCsmModal = false"
      @submitted="showCsmModal = false"
    />
  </AdminLayout>
</template>

<script setup>
import { Head, useForm, router, usePage } from "@inertiajs/vue3";
import { ref, reactive, computed } from "vue";
import Swal from 'sweetalert2'
import AdminLayout from "@/Layouts/AdminLayout.vue";
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppModal from '@/Components/AppModal.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { PencilSquareIcon, TrashIcon, PrinterIcon, XMarkIcon, CheckIcon } from "@heroicons/vue/24/outline";
import { useSubmit } from "@/Composables/useSubmit";
import CsmForm from '@/Components/CsmForm.vue'
import DigitalSignaturePin from '@/Components/DigitalSignaturePin.vue'
import PaginationControl from '@/Components/PaginationControl.vue'

const props = defineProps({
  requests:        Object,
  filters:         { type: Object, default: () => ({}) },
  isDivisionChief: { type: Boolean, default: false },
  canViewAll:      { type: Boolean, default: false },
  hasPin:          { type: Boolean, default: false },
  signatureUri:    { type: String,  default: null },
});
const page = usePage();

const showCsmModal = ref(false)
const requestToCsm = ref(null)
function openCsmModal(req) { requestToCsm.value = req; showCsmModal.value = true }

const hasPendingConfirmation = computed(() => {
  const uid = page.props.auth?.user?.id
  if (!uid) return false
  return requestsList.value.some(r => r.status === 'Approved' && r.requestor_id === uid)
})

async function handleNewRequest() {
  if (hasPendingConfirmation.value) {
    await Swal.fire({
      icon: 'warning',
      title: 'Action Required',
      text: 'You have a Service Request that has been approved and is pending your confirmation. Please rate the service first before submitting a new request.',
      confirmButtonText: 'OK',
    })
    return
  }
  openModal()
}

const searchQuery = ref(props.filters?.search ?? '')
const isLoading = ref(false)

const buildParams = (pageNum = undefined) => ({
  search: searchQuery.value || undefined,
  per_page: props.filters?.per_page || undefined,
  page: pageNum || undefined,
})

function applyFilters() {
    isLoading.value = true
    router.get(route('service-requests.index'), buildParams(), {
      preserveState: true,
      replace: true,
      only: ['requests', 'filters'],
      onFinish: () => { isLoading.value = false },
    })
}

function clearFilters() {
  searchQuery.value = ''
  isLoading.value = true
  router.get(route('service-requests.index'), {}, {
    preserveState: true,
    replace: true,
    only: ['requests', 'filters'],
    onFinish: () => { isLoading.value = false },
  })
}

function goToPage(pageNum) {
  isLoading.value = true
  router.get(route('service-requests.index'), buildParams(pageNum), {
    preserveState: true,
    replace: true,
    only: ['requests', 'filters'],
    onFinish: () => { isLoading.value = false },
  })
}

const requestsList = computed(() => props.requests?.data ?? [])
const filteredRequests = computed(() => props.requests?.data ?? [])
const currentPage = computed(() => props.requests?.current_page ?? 1)
const totalPages = computed(() => props.requests?.last_page ?? 1)

const roleName = computed(() => page.props.auth?.user?.role?.name ?? '');
const roleNames = computed(() => page.props.auth?.user?.roleNames ?? (roleName.value ? [roleName.value] : []));
const hasRole = (role) => roleNames.value.includes(role);
const hasAnyRole = (...roles) => roles.some(r => roleNames.value.includes(r));

const showModal = ref(false);
const editingId = ref(null);
const form = useForm({
  service_type: 'Reproduction',
  copies: null,
  sheets_per_set: null,
  date_needed: '',
  time_needed: '',
  purposes: '',
  details: '',
  pin: null,
});

const showSubmitPin = ref(false)
const pinLoading = ref(false)

const openPinModal = () => {
  if (!validateAll()) { Swal.fire({ icon: 'error', title: 'Validation failed', text: 'Please fix the highlighted errors before submitting.' }); return }
  showSubmitPin.value = true
}
const handlePinCancel = () => { showSubmitPin.value = false }
const handlePinConfirm = (pin) => {
  form.pin = pin || null
  showSubmitPin.value = false
  doPostStore()
}
const doPostStore = () => {
  form.post(route('service-requests.store'), {
    onSuccess: () => { closeModal(); Swal.fire({ icon: 'success', title: 'Request submitted', timer: 1200, showConfirmButton: false }).then(() => { window.location.reload() }) },
    onError: (errs) => { Swal.fire({ icon: 'error', title: 'Failed to submit', text: Object.values(errs || {}).flat().join('\n') || 'Failed to submit' }) }
  })
}

// Inline client-side validation state
const fieldErrors = reactive({
  service_type: '',
  copies: '',
  sheets_per_set: '',
  date_needed: '',
  time_needed: '',
  purposes: '',
  details: '',
});

const validateField = (field) => {
  switch (field) {
    case 'service_type':
      fieldErrors.service_type = form.service_type ? '' : 'Please select a service';
      break;
    case 'copies':
      if (form.service_type === 'Reproduction') {
        fieldErrors.copies = form.copies && Number(form.copies) >= 1 ? '' : 'Enter number of copies';
      } else fieldErrors.copies = '';
      break;
    case 'sheets_per_set':
      if (form.service_type === 'Reproduction') {
        fieldErrors.sheets_per_set = form.sheets_per_set && Number(form.sheets_per_set) >= 1 ? '' : 'Enter sheets per set';
      } else fieldErrors.sheets_per_set = '';
      break;
    case 'date_needed':
      fieldErrors.date_needed = form.date_needed ? '' : 'Date needed is required';
      break;
    case 'time_needed':
      fieldErrors.time_needed = '';
      break;
    case 'purposes':
      fieldErrors.purposes = form.purposes && String(form.purposes).trim() ? '' : 'Purpose is required';
      break;
    case 'details':
      fieldErrors.details = '';
      break;
  }
};

const validateAll = () => {
  ['service_type','copies','sheets_per_set','date_needed','time_needed','purposes','details'].forEach(f => validateField(f));
  // check if any error strings present
  return !Object.values(fieldErrors).some(v => typeof v === 'string' ? v && v.length > 0 : false);
};

const openModal = () => { editingId.value = null; form.reset(); Object.keys(fieldErrors).forEach(k => fieldErrors[k] = ''); showModal.value = true };
const closeModal = () => { editingId.value = null; showModal.value = false; form.reset(); Object.keys(fieldErrors).forEach(k => fieldErrors[k] = '') };

const openEdit = (r) => {
  editingId.value = r.id;
  form.reset();
  form.service_type = r.service_type;
  form.copies = r.copies;
  form.sheets_per_set = r.sheets_per_set;
  form.date_needed = r.date_needed;
  form.time_needed = r.time_needed;
  form.purposes = r.purposes;
  form.details = r.details;
  Object.keys(fieldErrors).forEach(k => fieldErrors[k] = '');
  showModal.value = true;
};

const submit = () => {
  if (!validateAll()) { Swal.fire({ icon: 'error', title: 'Validation failed', text: 'Please fix the highlighted errors before submitting.' }); return }
  if (editingId.value) {
    form.put(route('service-requests.update', editingId.value), {
      onSuccess: () => { closeModal(); Swal.fire({ icon: 'success', title: 'Request updated', timer: 1200, showConfirmButton: false }).then(() => { window.location.reload() }) },
      onError: (errs) => { Swal.fire({ icon: 'error', title: 'Failed to update', text: Object.values(errs || {}).flat().join('\n') || 'Failed to update' }) }
    });
  }
};

const remove = (id) => {
  Swal.fire({ title: 'Delete this service request?', text: 'This action cannot be undone.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Yes, delete', cancelButtonText: 'Cancel' }).then((res) => {
    if (!res.isConfirmed) return
    router.delete(route('service-requests.destroy', id), {
      onSuccess: () => { Swal.fire({ icon: 'success', title: 'Deleted', timer: 1000, showConfirmButton: false }).then(() => { window.location.reload() }) },
      onError: (errs) => { Swal.fire({ icon: 'error', title: 'Failed to delete', text: Object.values(errs || {}).flat().join('\n') || 'Failed to delete' }) }
    })
  })
};

const canPrint = (r) => {
  const st = (r?.status || '').toString().toLowerCase();
  if (!st.includes('approved') && st !== 'completed') return false
  const isOwn = r?.requestor_id === page.props.auth?.user?.id
  return props.canViewAll || isOwn
};

function statusColor(status) {
  const map = {
    'Pending':      'amber',
    'Approved':     'green',
    'Declined':     'red',
    'FAD Approved': 'green',
    'FAD Declined': 'red',
    'GSU Approved': 'green',
  }
  return map[status] ?? 'slate'
}
</script>
