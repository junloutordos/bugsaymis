<script setup>
import { Head, usePage, useForm, router } from "@inertiajs/vue3";
import { ref, computed } from "vue";
import { PencilSquareIcon, TrashIcon, ArrowUpTrayIcon, EyeIcon, PrinterIcon } from "@heroicons/vue/24/outline";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import Swal from 'sweetalert2'
import { storageUrl } from "@/Composables/useStorage.js"
import CsmForm from '@/Components/CsmForm.vue'
import DigitalSignaturePin from '@/Components/DigitalSignaturePin.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import AppModal from '@/Components/AppModal.vue'
import PaginationControl from '@/Components/PaginationControl.vue'

const props = defineProps({
  requests: Object,
  filters: { type: Object, default: () => ({}) },
  hasPendingCsm: { type: Boolean, default: false },
  hasPin: { type: Boolean, default: false },
  signatureUri: { type: String, default: null },
});
const page = usePage();

const searchQuery = ref(props.filters?.search ?? '')
const filterStatus = ref(props.filters?.status ?? '')
const filteredRequests = computed(() => props.requests?.data ?? [])
const currentPage = computed(() => props.requests?.current_page ?? 1)
const totalPages = computed(() => props.requests?.last_page ?? 1)

const statusOptions = [
  { value: '', label: 'All Statuses' },
  { value: 'Pending Division Chief Approval', label: 'Pending DC Approval' },
  { value: 'Approved', label: 'Approved' },
  { value: 'Completed', label: 'Completed' },
  { value: 'Declined', label: 'Declined' },
]

// ── Status badge color mapping ──────────────────────────────────────────────
function statusColor(status) {
  const s = (status ?? '').toString().toLowerCase()
  if (s.includes('declined') || s.includes('rejected')) return 'red'
  if (s.includes('completed') || s.includes('approved')) return 'green'
  if (s.includes('pending')) return 'amber'
  return 'slate'
}

function buildParams(page = undefined) {
  return {
    search: searchQuery.value || undefined,
    status: filterStatus.value || undefined,
    page: page || undefined,
  }
}

function applyFilters() {
  router.get(route('messengerial.index'), buildParams(), {
    preserveState: true,
    replace: true,
    only: ['requests', 'filters'],
  })
}

function clearFilters() {
  searchQuery.value = ''
  filterStatus.value = ''
  router.get(route('messengerial.index'), {}, {
    preserveState: true,
    replace: true,
    only: ['requests', 'filters'],
  })
}

function goToPage(page) {
  router.get(route('messengerial.index'), buildParams(page), {
    preserveState: true,
    replace: true,
    only: ['requests', 'filters'],
  })
}

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

const closeUploadModal = () => { showUploadModal.value = false; selectedRequest.value = null; };

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
    <div class="space-y-5">

      <AppPageHeader title="Messengerial Requests" subtitle="Track and manage document delivery requests">
        <template #actions>
          <AppButton @click.prevent="handleNewRequest()">+ New Request</AppButton>
        </template>
      </AppPageHeader>

      <!-- Filter bar -->
      <AppFilterBar>
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search requests…"
          @keydown.enter.prevent="applyFilters"
          class="w-full sm:w-72 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"
        />
        <select v-model="filterStatus"
                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
          <option v-for="status in statusOptions" :key="status.value" :value="status.value">{{ status.label }}</option>
        </select>
        <template #actions>
          <AppButton @click="applyFilters">Search</AppButton>
          <AppButton v-if="searchQuery || filterStatus" variant="secondary" @click="clearFilters">Clear</AppButton>
        </template>
      </AppFilterBar>

      <!-- Table -->
      <AppTable :is-empty="filteredRequests.length === 0" :skeleton-cols="12">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">#</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Requestor</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Unit</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Purpose</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Destination</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Reference No.</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Consignee</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Contact</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Status</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Package Type(s)</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Delivery</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Action</th>
          </tr>
        </template>

        <tr v-for="r in filteredRequests" :key="r.id" class="hover:bg-indigo-50/40">
          <td class="px-4 py-3 text-sm text-slate-700">{{ r.id }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ r.requestor }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ r.unit ?? '—' }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ r.purpose ?? '—' }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ r.destination ?? '—' }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ r.reference_no ?? '—' }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ r.consignee_name ?? '—' }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ r.consignee_contact ?? '—' }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">
            <AppBadge :color="statusColor(r.status)">{{ r.status }}</AppBadge>
          </td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ (Array.isArray(r.messengerial_kinds) ? r.messengerial_kinds.join(', ') : (r.messengerial_kinds || '—')) }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ (Array.isArray(r.delivery_methods) ? r.delivery_methods.join(', ') : (r.delivery_methods || '—')) }}</td>
          <td class="px-4 py-3 text-center">
            <div class="flex items-center gap-1.5 justify-center">
              <AppIconButton v-if="canModify(r)" label="Edit request" @click.prevent="openModal(r)"><PencilSquareIcon class="w-4 h-4"/></AppIconButton>
              <AppIconButton v-if="canModify(r)" label="Delete request" variant="danger" @click.prevent="destroy(r)"><TrashIcon class="w-4 h-4"/></AppIconButton>
              <AppIconButton v-if="(userRole === 'Administrator' || userRole === 'Records') && ((r.status ?? '').toString().toLowerCase().includes('approved') && !r.proof_of_delivery || r.proof_missing)" label="Upload proof of delivery" @click.prevent="openUpload(r)"><ArrowUpTrayIcon class="w-4 h-4"/></AppIconButton>
              <a v-if="r.proof_of_delivery && !r.proof_missing && (
                  userRole === 'Administrator' ||
                  userRole === 'Records' ||
                  ((r.status ?? '').toString().toLowerCase().includes('completed') && (
                    [r.email, r.requestor_email, r.requester_email, r.user_email].includes(userEmail) ||
                    ((r.requestor || '') && (r.requestor.toString().toLowerCase() === (page.props.auth?.user?.name ?? '').toString().toLowerCase()))
                  ))
                )" :href="route('messengerial.proof', r.id)" target="_blank" aria-label="View proof of delivery" title="View proof of delivery" class="inline-flex items-center justify-center h-8 w-8 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors"><EyeIcon class="w-4 h-4"/></a>
              <a v-if="(
                  (r.status ?? '').toString().toLowerCase().includes('approved') && (
                    userRole === 'Administrator' ||
                    userRole === 'Records' ||
                    [r.email, r.requestor_email, r.requester_email, r.user_email].includes(userEmail) ||
                    ((r.requestor || '') && (r.requestor.toString().toLowerCase() === (page.props.auth?.user?.name ?? '').toString().toLowerCase()))
                  )
                )" :href="route('messengerial.print', r.id)" target="_blank" aria-label="Print request" title="Print request" class="inline-flex items-center justify-center h-8 w-8 rounded-lg hover:bg-success-50 text-slate-500 hover:text-success-700 transition-colors"><PrinterIcon class="w-4 h-4"/></a>
              <AppButton
                v-if="r.status === 'Completed' && r.email === userEmail"
                variant="success"
                size="sm"
                @click.prevent="openCsmModal(r)"
              >Confirm &amp; Rate</AppButton>
            </div>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="r in filteredRequests" :key="r.id" class="p-4 space-y-3">
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
            <div class="space-y-1 text-xs text-slate-700">
              <div><span class="font-medium text-slate-500">Consignee:</span> {{ r.consignee_name ?? '—' }} ({{ r.consignee_contact ?? '—' }})</div>
              <div><span class="font-medium text-slate-500">Package:</span> {{ (Array.isArray(r.messengerial_kinds) ? r.messengerial_kinds.join(', ') : (r.messengerial_kinds || '—')) }}</div>
              <div><span class="font-medium text-slate-500">Delivery:</span> {{ (Array.isArray(r.delivery_methods) ? r.delivery_methods.join(', ') : (r.delivery_methods || '—')) }}</div>
              <div class="flex items-center gap-2"><span class="font-medium text-slate-500">Status:</span>
                <AppBadge :color="statusColor(r.status)">{{ r.status }}</AppBadge>
              </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <AppButton v-if="canModify(r)" size="sm" @click.prevent="openModal(r)"><PencilSquareIcon class="w-3.5 h-3.5"/> Edit</AppButton>
              <AppButton v-if="canModify(r)" size="sm" variant="danger" @click.prevent="destroy(r)"><TrashIcon class="w-3.5 h-3.5"/></AppButton>
              <AppButton v-if="(userRole === 'Administrator' || userRole === 'Records') && ((r.status ?? '').toString().toLowerCase().includes('approved') && !r.proof_of_delivery || r.proof_missing)" size="sm" variant="secondary" @click.prevent="openUpload(r)"><ArrowUpTrayIcon class="w-3.5 h-3.5"/></AppButton>
              <a v-if="r.proof_of_delivery && !r.proof_missing" :href="route('messengerial.proof', r.id)" target="_blank" class="inline-flex items-center gap-1.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-3 py-1.5 rounded-lg text-xs font-medium transition-colors"><EyeIcon class="w-3.5 h-3.5"/></a>
              <a v-if="(r.status ?? '').toString().toLowerCase().includes('approved')" :href="route('messengerial.print', r.id)" target="_blank" class="inline-flex items-center gap-1.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-3 py-1.5 rounded-lg text-xs font-medium transition-colors"><PrinterIcon class="w-3.5 h-3.5"/></a>
              <AppButton v-if="r.status === 'Completed' && r.email === userEmail" size="sm" variant="success" @click.prevent="openCsmModal(r)">Confirm &amp; Rate</AppButton>
            </div>
          </div>
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

      <!-- New Request Modal -->
      <AppModal :show="showModal" title="New Messengerial Request" size="md" @close="closeModal">
        <form @submit.prevent="submit" class="space-y-4">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Reference No.</label>
            <input v-model="form.reference_no" type="text" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" disabled readonly :placeholder="form.reference_no || 'Will be generated upon submission'" />
            <p v-if="form.errors.reference_no" class="text-danger-600 text-xs mt-1">{{ form.errors.reference_no }}</p>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Consignee Name</label>
            <input v-model="form.consignee_name" type="text" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
            <p v-if="form.errors.consignee_name" class="text-danger-600 text-xs mt-1">{{ form.errors.consignee_name }}</p>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Destination</label>
              <input v-model="form.destination" type="text" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
              <p v-if="form.errors.destination" class="text-danger-600 text-xs mt-1">{{ form.errors.destination }}</p>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Consignee Contact No.</label>
              <input v-model="form.consignee_contact" type="text" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
              <p v-if="form.errors.consignee_contact" class="text-danger-600 text-xs mt-1">{{ form.errors.consignee_contact }}</p>
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
            <p v-if="form.errors.messengerial_kinds" class="text-danger-600 text-xs mt-1">{{ form.errors.messengerial_kinds }}</p>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Delivery Method(s)</label>
            <select v-model="form.delivery_methods" multiple class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
              <option value="Hand-Carry">Hand-Carry</option>
              <option value="Courier Services">Courier Services</option>
            </select>
            <p v-if="form.errors.delivery_methods" class="text-danger-600 text-xs mt-1">{{ form.errors.delivery_methods }}</p>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Purpose</label>
            <input v-model="form.purpose" type="text" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
            <p v-if="form.errors.purpose" class="text-danger-600 text-xs mt-1">{{ form.errors.purpose }}</p>
          </div>
        </form>
        <template #footer>
          <AppButton variant="secondary" @click="closeModal">Cancel</AppButton>
          <AppButton :disabled="form.processing" @click.prevent="openPinModal">
            {{ form.processing ? 'Submitting…' : 'Submit' }}
          </AppButton>
        </template>
      </AppModal>

      <!-- Upload Proof Modal -->
      <AppModal :show="showUploadModal" title="Upload Proof of Delivery" size="md" @close="closeUploadModal">
        <form @submit.prevent="submitProof" class="space-y-4">
          <p class="text-xs text-slate-500">Request ID: {{ selectedRequest?.id }}</p>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Proof (PDF/JPG/PNG, max 5 MB)</label>
            <input type="file" accept=".pdf,.jpg,.jpeg,.png" @change="onProofSelected"
              class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-indigo-700 file:text-sm hover:file:bg-indigo-100" />
            <p v-if="proofPreviewName" class="text-xs text-slate-500 mt-1">Selected: {{ proofPreviewName }}</p>
            <p v-if="proofForm.errors.proof_base64" class="text-danger-600 text-xs mt-1">{{ proofForm.errors.proof_base64 }}</p>
          </div>
          <div v-if="selectedRequest?.delivery_methods && selectedRequest.delivery_methods.includes('Courier Services')">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Courier Service Provider</label>
              <input v-model="proofForm.courier_service_provider" type="text" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
              <p v-if="proofForm.errors.courier_service_provider" class="text-danger-600 text-xs mt-1">{{ proofForm.errors.courier_service_provider }}</p>
            </div>
            <div class="mt-3">
              <label class="block text-xs font-medium text-slate-600 mb-1">Cost</label>
              <input v-model="proofForm.courier_cost" type="number" step="0.01" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
              <p v-if="proofForm.errors.courier_cost" class="text-danger-600 text-xs mt-1">{{ proofForm.errors.courier_cost }}</p>
            </div>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Date Received by Courier</label>
            <input v-model="proofForm.date_received_by_courier" type="datetime-local" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
            <p v-if="proofForm.errors.date_received_by_courier" class="text-danger-600 text-xs mt-1">{{ proofForm.errors.date_received_by_courier }}</p>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Date Delivered to Addressee</label>
            <input v-model="proofForm.date_delivered" type="datetime-local" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
            <p v-if="proofForm.errors.date_delivered" class="text-danger-600 text-xs mt-1">{{ proofForm.errors.date_delivered }}</p>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Remarks</label>
            <textarea v-model="proofForm.proof_remarks" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" rows="3"></textarea>
            <p v-if="proofForm.errors.proof_remarks" class="text-danger-600 text-xs mt-1">{{ proofForm.errors.proof_remarks }}</p>
          </div>
        </form>
        <template #footer>
          <AppButton variant="secondary" @click="closeUploadModal">Cancel</AppButton>
          <AppButton :disabled="proofForm.processing" @click="submitProof">{{ proofForm.processing ? 'Uploading…' : 'Upload' }}</AppButton>
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
