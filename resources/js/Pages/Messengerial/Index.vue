<script setup>
import { Head, usePage, useForm, router } from "@inertiajs/vue3";
import { ref, computed, watch } from "vue";
import { PencilSquareIcon, TrashIcon, ArrowUpTrayIcon, EyeIcon, PrinterIcon, XMarkIcon } from "@heroicons/vue/24/outline";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import Swal from 'sweetalert2'
import { statusBadgeClass, badgeBase } from '@/Composables/useStatusBadge.js'
import { storageUrl } from "@/Composables/useStorage.js"
import CsmForm from '@/Components/CsmForm.vue'
import DigitalSignaturePin from '@/Components/DigitalSignaturePin.vue'

const props = defineProps({
  requests: Array,
  hasPendingCsm: { type: Boolean, default: false },
  hasPin: { type: Boolean, default: false },
  signatureUri: { type: String, default: null },
});
const page = usePage();

// client-side search & pagination
const requestsList = computed(() => props.requests || [])
const searchQuery = ref('')
const currentPage = ref(1)
const perPage = 10

const filteredRequestsAll = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  return requestsList.value.filter(r =>
    (r.purpose || '').toString().toLowerCase().includes(q) ||
    (r.requestor || '').toString().toLowerCase().includes(q) ||
    (r.reference_no || '').toString().toLowerCase().includes(q) ||
    (r.destination || '').toString().toLowerCase().includes(q) ||
    (r.status || '').toString().toLowerCase().includes(q) ||
    (r.consignee_name || '').toString().toLowerCase().includes(q) ||
    (r.consignee_contact || '').toString().toLowerCase().includes(q) ||
    (r.unit || '').toString().toLowerCase().includes(q) ||
    (r.id || '').toString().includes(q)
  )
})

const filteredRequests = computed(() => {
  const start = (currentPage.value - 1) * perPage
  return filteredRequestsAll.value.slice(start, start + perPage)
})

const totalPages = computed(() => Math.max(1, Math.ceil(filteredRequestsAll.value.length / perPage)))

watch(searchQuery, () => { currentPage.value = 1 })
const userRole = page.props.auth?.user?.role?.name ?? null;
const userEmail = page.props.auth?.user?.email ?? null;

const canModify = (r) => {
  return userRole === 'Administrator';
};

// CSM gate
async function handleNewRequest() {
  if (props.hasPendingCsm) {
    await Swal.fire({
      icon: 'warning',
      title: 'Action Required',
      text: 'You have a Messengerial Request that has been completed and is pending your confirmation. Please rate the service first before submitting a new request.',
      confirmButtonText: 'OK',
    })
    return
  }
  openModal()
}

const showCsmModal = ref(false)
const requestToCsm = ref(null)
function openCsmModal(req) { requestToCsm.value = req; showCsmModal.value = true }
function onCsmSubmitted() { showCsmModal.value = false; router.reload() }

const showModal = ref(false);
const showUploadModal = ref(false);
const selectedRequest = ref(null);
const proofForm = useForm({
  proof_base64: null,
  proof_name: '',
  courier_service_provider: '',
  courier_cost: '',
  date_received_by_courier: '',
  date_delivered: '',
  proof_remarks: '',
});
const proofFile = ref(null);
const proofPreviewName = ref('');

function onProofSelected(e) {
  const file = e.target.files?.[0];
  if (!file) return;
  proofPreviewName.value = file.name;
  const reader = new FileReader();
  reader.onload = ev => {
    proofForm.proof_base64 = ev.target.result;
    proofForm.proof_name   = file.name;
  };
  reader.readAsDataURL(file);
}
const form = useForm({ purpose: '', destination: '', reference_no: '', delivery_methods: [], messengerial_kinds: [], consignee_name: '', consignee_contact: '', consignee_email: '', pin: null });

const showSubmitPin = ref(false)
const pinLoading = ref(false)

const openPinModal = () => { showSubmitPin.value = true }
const handlePinCancel = () => { showSubmitPin.value = false }
const handlePinConfirm = (pin) => {
  form.pin = pin || null
  showSubmitPin.value = false
  submit()
}

const openModal = (req = null) => {
  if (req) {
    form.reset();
    form.purpose = req.purpose ?? '';
    form.destination = req.destination ?? '';
    form.reference_no = req.reference_no ?? '';
    form.delivery_methods = req.delivery_methods ?? [];
    form.messengerial_kinds = req.messengerial_kinds ?? [];
    form.consignee_name = req.consignee_name ?? '';
    form.consignee_contact = req.consignee_contact ?? '';
    form.consignee_email = req.consignee_email ?? '';
  } else {
    form.reset();
  }
  showModal.value = true;
};
const closeModal = () => { showModal.value = false; form.reset(); };

const submit = () => {
  form.post(route('messengerial.store'), {
    onSuccess: () => {
      closeModal();
      Swal.fire({ icon: 'success', title: 'Request submitted', text: 'Note: This request cannot be edited after submission.', timer: 1500, showConfirmButton: false }).then(() => { window.location.reload() });
    },
    onError: (errors) => {
      console.warn('Validation errors:', errors);
    },
    onFinish: () => {
      // no-op; form.processing is handled by useForm
    }
  });
};

const destroy = (req) => {
  Swal.fire({ title: 'Delete this messengerial request?', text: 'This action cannot be undone.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Yes, delete' }).then(res => {
    if (!res.isConfirmed) return;
    import('@inertiajs/vue3').then(({ router }) => {
      router.delete(route('messengerial.destroy', req.id));
    });
  });
};

const openUpload = (req) => {
  selectedRequest.value = req;
  proofForm.reset();
  proofPreviewName.value = '';
  proofForm.courier_service_provider = req.courier_service_provider ?? '';
  proofForm.courier_cost             = req.courier_cost             ?? '';
  proofForm.date_received_by_courier = req.date_received_by_courier ?? '';
  proofForm.date_delivered           = req.date_delivered           ?? '';
  proofForm.proof_remarks            = req.proof_remarks            ?? '';
  showUploadModal.value = true;
};

const submitProof = () => {
  if (!selectedRequest.value) return;
  if (!proofForm.proof_base64) {
    proofForm.setError('proof_base64', 'Please select a file.');
    return;
  }
  // Send as JSON — Cloudflare WAF blocks multipart/form-data file uploads
  proofForm.post(route('messengerial.upload_proof', selectedRequest.value.id), {
    onSuccess: () => {
      showUploadModal.value = false;
      selectedRequest.value = null;
      window.location.reload();
    },
    onError: (errors) => {
      console.warn('Upload errors', errors);
    }
  });
};

</script>

<template>
  <Head title="Messengerial" />
  <AdminLayout title="Messengerial">
    <div>
      <!-- Page header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Messengerial Requests</h1>
          <p class="text-sm text-slate-500 mt-0.5">Track and manage document delivery requests</p>
        </div>
        <button @click.prevent="handleNewRequest()" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
          + New Request
        </button>
      </div>

      <!-- Table card -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <!-- Search -->
        <div class="px-5 py-4 border-b border-slate-100">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search requests…"
            class="w-full sm:w-72 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"
          />
        </div>

        <!-- Desktop table -->
        <div class="hidden sm:block overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">#</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Requestor</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Unit</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Purpose</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Destination</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Reference No.</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Consignee</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Contact</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Status</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Package Type(s)</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Delivery</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="r in filteredRequests" :key="r.id" class="hover:bg-slate-50/60">
                <td class="px-4 py-3 text-sm text-slate-700">{{ r.id }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ r.requestor }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ r.unit ?? '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ r.purpose ?? '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ r.destination ?? '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ r.reference_no ?? '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ r.consignee_name ?? '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ r.consignee_contact ?? '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">
                  <span :class="[badgeBase, statusBadgeClass(r.status)]">{{ r.status }}</span>
                </td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ (Array.isArray(r.messengerial_kinds) ? r.messengerial_kinds.join(', ') : (r.messengerial_kinds || '—')) }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ (Array.isArray(r.delivery_methods) ? r.delivery_methods.join(', ') : (r.delivery_methods || '—')) }}</td>
                <td class="px-4 py-3 text-center">
                  <div class="flex items-center gap-1.5 justify-center">
                    <button v-if="canModify(r)" @click.prevent="openModal(r)" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" title="Edit"><PencilSquareIcon class="w-4 h-4"/></button>
                    <button v-if="canModify(r)" @click.prevent="destroy(r)" class="p-1.5 rounded-lg hover:bg-red-50 text-slate-500 hover:text-red-600 transition-colors" title="Delete"><TrashIcon class="w-4 h-4"/></button>
                    <button v-if="(userRole === 'Administrator' || userRole === 'Records') && ((r.status ?? '').toString().toLowerCase().includes('approved') && !r.proof_of_delivery || r.proof_missing)" @click.prevent="openUpload(r)" class="p-1.5 rounded-lg hover:bg-indigo-50 text-slate-500 hover:text-indigo-700 transition-colors" title="Upload Proof"><ArrowUpTrayIcon class="w-4 h-4"/></button>
                    <a v-if="r.proof_of_delivery && !r.proof_missing && (
                        userRole === 'Administrator' ||
                        userRole === 'Records' ||
                        ((r.status ?? '').toString().toLowerCase().includes('completed') && (
                          [r.email, r.requestor_email, r.requester_email, r.user_email].includes(userEmail) ||
                          ((r.requestor || '') && (r.requestor.toString().toLowerCase() === (page.props.auth?.user?.name ?? '').toString().toLowerCase()))
                        ))
                      )" :href="route('messengerial.proof', r.id)" target="_blank" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" title="View Proof"><EyeIcon class="w-4 h-4"/></a>
                    <a v-if="(
                        (r.status ?? '').toString().toLowerCase().includes('approved') && (
                          userRole === 'Administrator' ||
                          userRole === 'Records' ||
                          [r.email, r.requestor_email, r.requester_email, r.user_email].includes(userEmail) ||
                          ((r.requestor || '') && (r.requestor.toString().toLowerCase() === (page.props.auth?.user?.name ?? '').toString().toLowerCase()))
                        )
                      )" :href="route('messengerial.print', r.id)" target="_blank" class="p-1.5 rounded-lg hover:bg-emerald-50 text-slate-500 hover:text-emerald-700 transition-colors" title="Print"><PrinterIcon class="w-4 h-4"/></a>
                    <button
                      v-if="r.status === 'Completed' && r.email === userEmail"
                      @click.prevent="openCsmModal(r)"
                      class="inline-flex items-center gap-1 bg-emerald-600 hover:bg-emerald-700 text-white px-2 py-1 rounded-lg text-xs font-medium transition-colors"
                      title="Confirm & Rate"
                    >Confirm &amp; Rate</button>
                  </div>
                </td>
              </tr>
              <tr v-if="filteredRequests.length === 0">
                <td colspan="12" class="py-16 text-center text-slate-400 text-sm">No messengerial requests found.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Mobile cards -->
        <div class="sm:hidden p-4 space-y-3">
          <div v-for="r in filteredRequests" :key="r.id" class="bg-white border border-slate-100 rounded-xl p-4 shadow-sm">
            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0">
                <p class="text-xs text-slate-500">Request #{{ r.id }}</p>
                <p class="text-sm font-semibold text-slate-800 mt-0.5 truncate">{{ r.purpose ?? '—' }}</p>
                <p class="text-xs text-slate-600 mt-1">{{ r.requestor }} — {{ r.unit ?? '—' }}</p>
              </div>
              <div class="shrink-0 text-right text-xs text-slate-600">
                <div>{{ r.destination ?? '—' }}</div>
                <div class="text-slate-400">Ref: {{ r.reference_no ?? '—' }}</div>
              </div>
            </div>
            <div class="mt-2 space-y-1 text-xs text-slate-700">
              <div><span class="font-medium text-slate-500">Consignee:</span> {{ r.consignee_name ?? '—' }} ({{ r.consignee_contact ?? '—' }})</div>
              <div><span class="font-medium text-slate-500">Package:</span> {{ (Array.isArray(r.messengerial_kinds) ? r.messengerial_kinds.join(', ') : (r.messengerial_kinds || '—')) }}</div>
              <div><span class="font-medium text-slate-500">Delivery:</span> {{ (Array.isArray(r.delivery_methods) ? r.delivery_methods.join(', ') : (r.delivery_methods || '—')) }}</div>
              <div class="flex items-center gap-2"><span class="font-medium text-slate-500">Status:</span>
                <span :class="[badgeBase, statusBadgeClass(r.status)]">{{ r.status }}</span>
              </div>
            </div>
            <div class="mt-3 flex flex-wrap items-center gap-2">
              <button v-if="canModify(r)" @click.prevent="openModal(r)" class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium transition-colors"><PencilSquareIcon class="w-3.5 h-3.5"/> Edit</button>
              <button v-if="canModify(r)" @click.prevent="destroy(r)" class="inline-flex items-center gap-1.5 bg-red-600 hover:bg-red-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium transition-colors"><TrashIcon class="w-3.5 h-3.5"/></button>
              <button v-if="(userRole === 'Administrator' || userRole === 'Records') && ((r.status ?? '').toString().toLowerCase().includes('approved') && !r.proof_of_delivery || r.proof_missing)" @click.prevent="openUpload(r)" class="inline-flex items-center gap-1.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-3 py-1.5 rounded-lg text-xs font-medium transition-colors"><ArrowUpTrayIcon class="w-3.5 h-3.5"/></button>
              <a v-if="r.proof_of_delivery && !r.proof_missing" :href="route('messengerial.proof', r.id)" target="_blank" class="inline-flex items-center gap-1.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-3 py-1.5 rounded-lg text-xs font-medium transition-colors"><EyeIcon class="w-3.5 h-3.5"/></a>
              <a v-if="(r.status ?? '').toString().toLowerCase().includes('approved')" :href="route('messengerial.print', r.id)" target="_blank" class="inline-flex items-center gap-1.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-3 py-1.5 rounded-lg text-xs font-medium transition-colors"><PrinterIcon class="w-3.5 h-3.5"/></a>
              <button v-if="r.status === 'Completed' && r.email === userEmail" @click.prevent="openCsmModal(r)" class="inline-flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium transition-colors shadow-sm">Confirm &amp; Rate</button>
            </div>
          </div>
          <div v-if="filteredRequests.length === 0" class="py-16 text-center text-slate-400 text-sm">No messengerial requests found.</div>
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

      <!-- New Request Modal -->
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50">
        <div class="bg-white w-full h-full sm:h-auto sm:rounded-2xl sm:shadow-xl sm:max-w-md relative overflow-auto">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-800">New Messengerial Request</h2>
            <button class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" @click="closeModal">
              <XMarkIcon class="w-4 h-4" />
            </button>
          </div>
          <form @submit.prevent="submit" class="px-6 py-5 space-y-4 max-h-[80vh] overflow-auto">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Reference No.</label>
              <input v-model="form.reference_no" type="text" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" disabled readonly :placeholder="form.reference_no || 'Will be generated upon submission'" />
              <p v-if="form.errors.reference_no" class="text-red-600 text-xs mt-1">{{ form.errors.reference_no }}</p>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Consignee Name</label>
              <input v-model="form.consignee_name" type="text" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
              <p v-if="form.errors.consignee_name" class="text-red-600 text-xs mt-1">{{ form.errors.consignee_name }}</p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Destination</label>
                <input v-model="form.destination" type="text" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
                <p v-if="form.errors.destination" class="text-red-600 text-xs mt-1">{{ form.errors.destination }}</p>
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Consignee Contact No.</label>
                <input v-model="form.consignee_contact" type="text" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
                <p v-if="form.errors.consignee_contact" class="text-red-600 text-xs mt-1">{{ form.errors.consignee_contact }}</p>
              </div>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Package Type(s)</label>
              <select v-model="form.messengerial_kinds" multiple class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
                <option value="Letter Envelope">Letter Envelope</option>
                <option value="Folder or Brown Envelope">Folder or Brown Envelope</option>
                <option value="Box Small">Box Small</option>
                <option value="Box Medium">Box Medium</option>
                <option value="Box Large">Box Large</option>
              </select>
              <p v-if="form.errors.messengerial_kinds" class="text-red-600 text-xs mt-1">{{ form.errors.messengerial_kinds }}</p>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Delivery Method(s)</label>
              <select v-model="form.delivery_methods" multiple class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
                <option value="Hand-Carry">Hand-Carry</option>
                <option value="Courier Services">Courier Services</option>
              </select>
              <p v-if="form.errors.delivery_methods" class="text-red-600 text-xs mt-1">{{ form.errors.delivery_methods }}</p>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Purpose</label>
              <input v-model="form.purpose" type="text" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
              <p v-if="form.errors.purpose" class="text-red-600 text-xs mt-1">{{ form.errors.purpose }}</p>
            </div>
            <div class="flex justify-end gap-2 pt-2">
              <button @click.prevent="closeModal" type="button" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">Cancel</button>
              <button :disabled="form.processing" type="button" @click.prevent="openPinModal" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-60">
                <span v-if="form.processing">Submitting…</span>
                <span v-else>Submit</span>
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Upload Proof Modal -->
      <div v-if="showUploadModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50">
        <div class="bg-white w-full h-full sm:h-auto sm:rounded-2xl sm:shadow-xl sm:max-w-md relative overflow-auto">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-800">Upload Proof of Delivery</h2>
            <button class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" @click="showUploadModal = false; selectedRequest = null">
              <XMarkIcon class="w-4 h-4" />
            </button>
          </div>
          <form @submit.prevent="submitProof" class="px-6 py-5 space-y-4 max-h-[80vh] overflow-auto">
            <p class="text-xs text-slate-500">Request ID: {{ selectedRequest?.id }}</p>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Proof (PDF/JPG/PNG, max 5 MB)</label>
              <input type="file" accept=".pdf,.jpg,.jpeg,.png" @change="onProofSelected"
                class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-indigo-700 file:text-sm hover:file:bg-indigo-100" />
              <p v-if="proofPreviewName" class="text-xs text-slate-500 mt-1">Selected: {{ proofPreviewName }}</p>
              <p v-if="proofForm.errors.proof_base64" class="text-red-600 text-xs mt-1">{{ proofForm.errors.proof_base64 }}</p>
            </div>
            <div v-if="selectedRequest?.delivery_methods && selectedRequest.delivery_methods.includes('Courier Services')">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Courier Service Provider</label>
                <input v-model="proofForm.courier_service_provider" type="text" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
                <p v-if="proofForm.errors.courier_service_provider" class="text-red-600 text-xs mt-1">{{ proofForm.errors.courier_service_provider }}</p>
              </div>
              <div class="mt-3">
                <label class="block text-xs font-medium text-slate-600 mb-1">Cost</label>
                <input v-model="proofForm.courier_cost" type="number" step="0.01" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
                <p v-if="proofForm.errors.courier_cost" class="text-red-600 text-xs mt-1">{{ proofForm.errors.courier_cost }}</p>
              </div>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Date Received by Courier</label>
              <input v-model="proofForm.date_received_by_courier" type="datetime-local" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
              <p v-if="proofForm.errors.date_received_by_courier" class="text-red-600 text-xs mt-1">{{ proofForm.errors.date_received_by_courier }}</p>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Date Delivered to Addressee</label>
              <input v-model="proofForm.date_delivered" type="datetime-local" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
              <p v-if="proofForm.errors.date_delivered" class="text-red-600 text-xs mt-1">{{ proofForm.errors.date_delivered }}</p>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Remarks</label>
              <textarea v-model="proofForm.proof_remarks" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" rows="3"></textarea>
              <p v-if="proofForm.errors.proof_remarks" class="text-red-600 text-xs mt-1">{{ proofForm.errors.proof_remarks }}</p>
            </div>
            <div class="flex justify-end gap-2 pt-2">
              <button @click.prevent="showUploadModal = false; selectedRequest = null" type="button" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">Cancel</button>
              <button :disabled="proofForm.processing" type="submit" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-60">{{ proofForm.processing ? 'Uploading…' : 'Upload' }}</button>
            </div>
          </form>
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
      respondable-type="messengerial-request"
      :respondable-id="requestToCsm?.id ?? 0"
      :transaction-date="requestToCsm?.created_at?.slice(0,10) ?? ''"
      office-availed="Records Office"
      service-key="others"
      service-other-label="Messengerial / Delivery Request"
      @close="showCsmModal = false"
      @submitted="onCsmSubmitted"
    />
  </AdminLayout>
</template>
