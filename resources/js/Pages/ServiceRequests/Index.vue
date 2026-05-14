<script setup>
import { Head, useForm, router, usePage } from "@inertiajs/vue3";
import { ref, reactive, computed, watch, onMounted, onBeforeUnmount } from "vue";
import Swal from 'sweetalert2'
import AdminLayout from "@/Layouts/AdminLayout.vue";
import { statusBadgeClass, badgeBase } from '@/Composables/useStatusBadge.js'
import { PencilSquareIcon, TrashIcon, PrinterIcon, XMarkIcon } from "@heroicons/vue/24/outline";
import { useSubmit } from "@/Composables/useSubmit";
import CsmForm from '@/Components/CsmForm.vue'
import DigitalSignaturePin from '@/Components/DigitalSignaturePin.vue'

const props = defineProps({
  requests:        Object,
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

const requestsList = ref(Array.isArray(props.requests) ? props.requests : (props.requests?.data ?? []))
const searchQuery = ref('')
const currentPage = ref(1)
const perPage = 10

// responsive: track window width to toggle between table and card layouts
const windowWidth = ref(typeof window !== 'undefined' ? window.innerWidth : 1200)
const isMobile = computed(() => windowWidth.value < 768)
const handleResize = () => { windowWidth.value = window.innerWidth }
onMounted(() => { window.addEventListener('resize', handleResize) })
onBeforeUnmount(() => { window.removeEventListener('resize', handleResize) })

const filteredRequestsAll = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  return (requestsList.value || []).filter(r =>
    (r.service_type || '').toString().toLowerCase().includes(q) ||
    (r.purposes || '').toString().toLowerCase().includes(q) ||
    (r.id || '').toString().includes(q) ||
    (r.status || '').toString().toLowerCase().includes(q) ||
    (r.requester?.name || r.requestor || '').toString().toLowerCase().includes(q) ||
    (r.date_needed || '').toString().includes(q) ||
    (r.details || '').toString().toLowerCase().includes(q)
  )
})

const filteredRequests = computed(() => {
  const start = (currentPage.value - 1) * perPage
  return filteredRequestsAll.value.slice(start, start + perPage)
})

const totalPages = computed(() => Math.max(1, Math.ceil(filteredRequestsAll.value.length / perPage)))

watch(searchQuery, () => { currentPage.value = 1 })
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
</script>

<template>
  <Head title="Request for Services" />
  <AdminLayout title="Request for Services">
    <div>
      <!-- Page header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Request for Services</h1>
          <p class="text-sm text-slate-500 mt-0.5">Manage reproduction, security, and janitorial service requests</p>
        </div>
        <button @click="handleNewRequest" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
          + New Request
        </button>
      </div>

      <!-- Table card -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <!-- Search -->
        <div class="px-5 py-4 border-b border-slate-100">
          <input v-model="searchQuery" type="text" placeholder="Search requests…"
                 class="w-full sm:w-72 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
        </div>

        <!-- Mobile cards -->
        <div v-if="isMobile" class="p-4 space-y-3">
          <div v-for="r in filteredRequests" :key="r.id" class="bg-white border border-slate-100 rounded-xl p-4 shadow-sm">
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
            <div class="mt-2 space-y-1 text-xs text-slate-700">
              <div><span class="font-medium text-slate-500">Purpose:</span> {{ r.purposes || '—' }}</div>
              <div class="flex items-center gap-2"><span class="font-medium text-slate-500">Status:</span>
                <span :class="[badgeBase, statusBadgeClass(r.status)]">{{ r.status }}</span>
              </div>
            </div>
            <div class="mt-3 flex flex-wrap items-center gap-2">
              <button v-if="r.status === 'Pending' && roleNames.some(r => r !== 'Staff')" @click.prevent="openEdit(r)" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors"><PencilSquareIcon class="w-4 h-4"/></button>
              <button v-if="r.status === 'Pending' && roleNames.some(r => r !== 'Staff')" @click.prevent="remove(r.id)" class="p-1.5 rounded-lg hover:bg-red-50 text-slate-500 hover:text-red-600 transition-colors"><TrashIcon class="w-4 h-4"/></button>
              <a v-if="canPrint(r)" :href="route('service-requests.print', r.id)" target="_blank" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" title="Print"><PrinterIcon class="w-4 h-4"/></a>
            </div>
          </div>
          <div v-if="filteredRequests.length === 0" class="py-16 text-center text-slate-400 text-sm">No requests</div>
        </div>

        <!-- Desktop table -->
        <div v-if="!isMobile" class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">#</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Service</th>
                <th v-if="hasAnyRole('Administrator', 'GSU Head', 'DivisionChief')" class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Requestor</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Date Needed</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Time Needed</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Purpose(s)</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Status</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="r in filteredRequests" :key="r.id" class="hover:bg-slate-50/60">
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
                  <span :class="[badgeBase, statusBadgeClass(r.status)]">{{ r.status }}</span>
                </td>
                <td class="px-4 py-3">
                  <div class="flex items-center gap-1.5">
                    <button v-if="r.status === 'Pending' && roleNames.some(r => r !== 'Staff')" @click.prevent="openEdit(r)" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" title="Edit">
                      <PencilSquareIcon class="w-4 h-4" />
                    </button>
                    <button v-if="r.status === 'Pending' && roleNames.some(r => r !== 'Staff')" @click.prevent="remove(r.id)" class="p-1.5 rounded-lg hover:bg-red-50 text-slate-500 hover:text-red-600 transition-colors" title="Delete">
                      <TrashIcon class="w-4 h-4" />
                    </button>
                    <button v-if="r.status === 'Pending' && hasRole('DivisionChief')" @click.prevent="approveRequest(r)" class="p-1.5 rounded-lg hover:bg-emerald-50 text-slate-500 hover:text-emerald-700 transition-colors" title="Approve"><CheckIcon class="w-4 h-4"/></button>
                    <button v-if="r.status === 'Pending' && hasRole('DivisionChief')" @click.prevent="declineRequest(r)" class="p-1.5 rounded-lg hover:bg-red-50 text-slate-500 hover:text-red-600 transition-colors" title="Decline"><XMarkIcon class="w-4 h-4"/></button>
                    <button v-if="r.status === 'Approved' && r.requestor_id === page.props.auth.user.id" @click.prevent="openCsmModal(r)" class="inline-flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1 rounded-lg text-xs font-medium transition-colors shadow-sm">Confirm &amp; Rate</button>
                    <a v-if="canPrint(r)" :href="route('service-requests.print', r.id)" target="_blank" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" title="Print">
                      <PrinterIcon class="w-4 h-4" />
                    </a>
                  </div>
                </td>
              </tr>
              <tr v-if="filteredRequests.length === 0">
                <td :colspan="(hasAnyRole('Administrator', 'GSU Head', 'DivisionChief') ? 8 : 7)" class="py-16 text-center text-slate-400 text-sm">No requests</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-sm text-slate-600">
          <span>Page {{ currentPage }} of {{ totalPages }}</span>
          <div class="flex items-center gap-2">
            <button @click="currentPage--" :disabled="currentPage === 1"
                    class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors disabled:opacity-50">Prev</button>
            <button @click="currentPage++" :disabled="currentPage === totalPages"
                    class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors disabled:opacity-50">Next</button>
          </div>
        </div>
      </div>

      <!-- Modal -->
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-800">{{ editingId ? 'Edit Service Request' : 'New Service Request' }}</h2>
            <button @click="closeModal" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors">
              <XMarkIcon class="w-4 h-4" />
            </button>
          </div>
          <div class="px-6 py-5 space-y-4">
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
          <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
            <button @click="closeModal" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">Cancel</button>
            <button @click="editingId ? submit() : openPinModal()" :disabled="form.processing" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-60">
              <span v-if="form.processing">Processing…</span>
              <span v-else>{{ editingId ? 'Update' : 'Submit' }}</span>
            </button>
          </div>
        </div>
      </div>
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
