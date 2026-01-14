<script setup>
import { Head, usePage, useForm } from "@inertiajs/vue3";
import { ref } from "vue";
import { PencilSquareIcon, TrashIcon, ArrowUpTrayIcon, EyeIcon, PrinterIcon } from "@heroicons/vue/24/outline";
import AdminLayout from "@/Layouts/AdminLayout.vue";

const props = defineProps({ requests: Array });
const page = usePage();
const userRole = page.props.auth?.user?.role?.name ?? null;
const userEmail = page.props.auth?.user?.email ?? null;

const canModify = (r) => {
  // Only Administrators can edit or delete requests.
  return userRole === 'Administrator';
};

const showModal = ref(false);
const showUploadModal = ref(false);
const selectedRequest = ref(null);
const proofForm = useForm({
  proof: null,
  courier_service_provider: '',
  courier_cost: '',
  date_received_by_courier: '',
  date_delivered: '',
  proof_remarks: '',
});
const form = useForm({ purpose: '', destination: '', reference_no: '', delivery_methods: [], messengerial_kinds: [], consignee_name: '', consignee_contact: '', consignee_email: '' });

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
      alert('Request submitted. Note: This request cannot be edited after submission.');
      window.location.reload();
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
  if (!confirm('Delete this messengerial request?')) return;
  import('@inertiajs/vue3').then(({ router }) => {
    router.delete(route('messengerial.destroy', req.id));
  });
};

const openUpload = (req) => {
  selectedRequest.value = req;
  proofForm.reset();
  // prefill courier fields if any existing values
  
  proofForm.courier_service_provider = req.courier_service_provider ?? '';
  proofForm.courier_cost = req.courier_cost ?? '';
  proofForm.date_received_by_courier = req.date_received_by_courier ?? '';
  proofForm.date_delivered = req.date_delivered ?? '';
  proofForm.proof_remarks = req.proof_remarks ?? '';
  showUploadModal.value = true;
};

const submitProof = () => {
  if (!selectedRequest.value) return;
  proofForm.post(route('messengerial.upload_proof', selectedRequest.value.id), {
    forceFormData: true,
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
    <div class="p-6">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Messengerial Requests</h1>
        <button @click.prevent="openModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow">+ New Request</button>
      </div>

      <div class="bg-white rounded-xl shadow p-4">
        <!-- Desktop table -->
        <div class="hidden sm:block overflow-x-auto">
          <table class="table-fixed w-full border border-gray-200">
          <thead class="bg-gray-100 text-gray-700 uppercase text-sm">
            <tr>
              <th class="px-4 py-3 text-left whitespace-normal break-words">#</th>
              <th class="px-4 py-3 text-left whitespace-normal break-words">Requestor</th>
              <th class="px-4 py-3 text-left whitespace-normal break-words">Unit</th>
              <th class="px-4 py-3 text-left whitespace-normal break-words">Purpose</th>
              <th class="px-4 py-3 text-left whitespace-normal break-words">Destination</th>
              <th class="px-4 py-3 text-left whitespace-normal break-words">Reference No.</th>
              <th class="px-4 py-3 text-left whitespace-normal break-words">Consignee</th>
              <th class="px-4 py-3 text-left whitespace-normal break-words">Contact</th>
              <th class="px-4 py-3 text-left whitespace-normal break-words">Status</th>
              <th class="px-4 py-3 text-left whitespace-normal break-words">Package Type(s)</th>
              <th class="px-4 py-3 text-left whitespace-normal break-words">Delivery</th>
              <th class="px-4 py-3 text-center whitespace-normal break-words">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 text-sm">
            <tr v-for="r in props.requests" :key="r.id">
              <td class="px-4 py-3 whitespace-normal break-words">{{ r.id }}</td>
              <td class="px-4 py-3 whitespace-normal break-words">{{ r.requestor }}</td>
              <td class="px-4 py-3 whitespace-normal break-words">{{ r.unit ?? '—' }}</td>
              <td class="px-4 py-3 whitespace-normal break-words">{{ r.purpose ?? '—' }}</td>
              <td class="px-4 py-3 whitespace-normal break-words">{{ r.destination ?? '—' }}</td>
              <td class="px-4 py-3 whitespace-normal break-words">{{ r.reference_no ?? '—' }}</td>
              <td class="px-4 py-3 whitespace-normal break-words">{{ r.consignee_name ?? '—' }}</td>
              <td class="px-4 py-3 whitespace-normal break-words">{{ r.consignee_contact ?? '—' }}</td>
              <td class="px-4 py-3 whitespace-normal break-words">
                <span :class="[ 'px-3 py-1 text-xs rounded-full font-semibold',
                  (r.status ?? '').toString().toLowerCase().includes('approved') ? 'bg-green-100 text-green-800' :
                  (r.status ?? '').toString().toLowerCase().includes('declined') ? 'bg-red-100 text-red-700' :
                  'bg-gray-100 text-gray-700'
                ]">{{ r.status }}</span>
              </td>
              <td class="px-4 py-3">{{ (Array.isArray(r.messengerial_kinds) ? r.messengerial_kinds.join(', ') : (r.messengerial_kinds || '—')) }}</td>
              <td class="px-4 py-3">{{ (Array.isArray(r.delivery_methods) ? r.delivery_methods.join(', ') : (r.delivery_methods || '—')) }}</td>
              <td class="px-4 py-3 text-center">
                <div class="flex items-center gap-2 justify-center">
                  <button v-if="canModify(r)" @click.prevent="openModal(r)" class="p-2 rounded-full bg-blue-100 hover:bg-blue-200 text-blue-700" title="Edit"><PencilSquareIcon class="w-5 h-5"/></button>
                  <button v-if="canModify(r)" @click.prevent="destroy(r)" class="p-2 rounded-full bg-red-100 hover:bg-red-200 text-red-700" title="Delete"><TrashIcon class="w-5 h-5"/></button>
                  <!-- Upload proof (Records/Admin) -->
                  <button v-if="(userRole === 'Administrator' || userRole === 'Records') && (r.status ?? '').toString().toLowerCase().includes('approved') && !r.proof_of_delivery" @click.prevent="openUpload(r)" class="p-2 rounded-full bg-indigo-100 hover:bg-indigo-200 text-indigo-700" title="Upload Proof"><ArrowUpTrayIcon class="w-5 h-5"/></button>
                  <!-- View proof (requester/admin/records) -->
                  <a v-if="r.proof_of_delivery && (
                      userRole === 'Administrator' ||
                      userRole === 'Records' ||
                      ((r.status ?? '').toString().toLowerCase().includes('completed') && (
                        [r.email, r.requestor_email, r.requester_email, r.user_email].includes(userEmail) ||
                        ((r.requestor || '') && (r.requestor.toString().toLowerCase() === (page.props.auth?.user?.name ?? '').toString().toLowerCase()))
                      ))
                    )" :href="('/storage/' + r.proof_of_delivery)" target="_blank" class="p-2 rounded-full bg-gray-100 hover:bg-gray-200 text-gray-700" title="View Proof"><EyeIcon class="w-5 h-5"/></a>
                  <!-- Print ticket (requester/admin/records) -->
                  <a v-if="(
                      (r.status ?? '').toString().toLowerCase().includes('approved') && (
                        userRole === 'Administrator' ||
                        userRole === 'Records' ||
                        [r.email, r.requestor_email, r.requester_email, r.user_email].includes(userEmail) ||
                        ((r.requestor || '') && (r.requestor.toString().toLowerCase() === (page.props.auth?.user?.name ?? '').toString().toLowerCase()))
                      )
                    )" :href="route('messengerial.print', r.id)" target="_blank" class="p-2 rounded-full bg-green-100 hover:bg-green-200 text-green-700" title="Print"><PrinterIcon class="w-5 h-5"/></a>
                </div>
              </td>
            </tr>
            <tr v-if="props.requests.length === 0">
              <td colspan="12" class="px-4 py-6 text-center text-gray-500">No messengerial requests found.</td>
            </tr>
          </tbody>
          </table>
        </div>

        <!-- Mobile cards -->
        <div class="sm:hidden space-y-3">
          <div v-for="r in props.requests" :key="r.id" class="border rounded-lg p-3 bg-white shadow-sm">
            <div class="flex items-start justify-between">
              <div>
                <div class="text-sm text-gray-500">Request #{{ r.id }}</div>
                <div class="font-semibold text-gray-800">{{ r.purpose ?? '—' }}</div>
                <div class="text-sm text-gray-600">{{ r.requestor }} — {{ r.unit ?? '—' }}</div>
              </div>
              <div class="text-right text-sm">
                <div class="text-gray-600">{{ r.destination ?? '—' }}</div>
                <div class="text-gray-500 text-xs">Ref: {{ r.reference_no ?? '—' }}</div>
              </div>
            </div>

            <div class="mt-2 text-sm text-gray-700">
              <div><strong>Consignee:</strong> <span class="ml-1">{{ r.consignee_name ?? '—' }} ({{ r.consignee_contact ?? '—' }})</span></div>
              <div class="mt-1"><strong>Package:</strong> <span class="ml-1">{{ (Array.isArray(r.messengerial_kinds) ? r.messengerial_kinds.join(', ') : (r.messengerial_kinds || '—')) }}</span></div>
              <div class="mt-1"><strong>Delivery:</strong> <span class="ml-1">{{ (Array.isArray(r.delivery_methods) ? r.delivery_methods.join(', ') : (r.delivery_methods || '—')) }}</span></div>
              <div class="mt-1"><strong>Status:</strong> <span class="ml-1">{{ r.status }}</span></div>
            </div>

            <div class="mt-3 flex items-center gap-2">
              <button v-if="canModify(r)" @click.prevent="openModal(r)" class="flex-1 inline-flex items-center justify-center gap-2 px-3 py-2 bg-blue-600 text-white rounded-md"><PencilSquareIcon class="w-4 h-4"/> Edit</button>
              <button v-if="canModify(r)" @click.prevent="destroy(r)" class="inline-flex items-center gap-2 px-3 py-2 bg-red-100 text-red-700 rounded-md"><TrashIcon class="w-4 h-4"/></button>
              <button v-if="(userRole === 'Administrator' || userRole === 'Records') && (r.status ?? '').toString().toLowerCase().includes('approved') && !r.proof_of_delivery" @click.prevent="openUpload(r)" class="inline-flex items-center gap-2 px-3 py-2 bg-indigo-100 text-indigo-700 rounded-md"><ArrowUpTrayIcon class="w-4 h-4"/></button>
              <a v-if="r.proof_of_delivery" :href="('/storage/' + r.proof_of_delivery)" target="_blank" class="inline-flex items-center gap-2 px-3 py-2 bg-gray-100 text-gray-700 rounded-md"><EyeIcon class="w-4 h-4"/></a>
              <a v-if="(r.status ?? '').toString().toLowerCase().includes('approved')" :href="route('messengerial.print', r.id)" target="_blank" class="inline-flex items-center gap-2 px-3 py-2 bg-green-100 text-green-700 rounded-md"><PrinterIcon class="w-4 h-4"/></a>
            </div>
          </div>

          <div v-if="props.requests.length === 0" class="text-center text-gray-500 py-6">No messengerial requests found.</div>
        </div>
      </div>

      <!-- Modal -->
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
        <div class="bg-white w-full h-full sm:h-auto sm:rounded-xl sm:shadow-lg sm:max-w-md p-4 sm:p-6 relative overflow-auto">
          <button class="absolute top-3 right-3 text-gray-500 hover:text-gray-800" @click="closeModal">✕</button>
          <h2 class="text-xl font-semibold mb-4">New Messengerial Request</h2>
          <form @submit.prevent="submit" class="space-y-4 max-h-[90vh] overflow-auto">
            <div>
              <label class="block text-sm font-medium text-gray-700">Reference No.</label>
              <input v-model="form.reference_no" type="text" class="mt-1 block w-full rounded border-gray-300" disabled readonly :placeholder="form.reference_no || 'Will be generated upon submission'" />
              <p v-if="form.errors.reference_no" class="text-red-600 text-sm mt-1">{{ form.errors.reference_no }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700">Consignee Name</label>
              <input v-model="form.consignee_name" type="text" class="mt-1 block w-full rounded border-gray-300" />
              <p v-if="form.errors.consignee_name" class="text-red-600 text-sm mt-1">{{ form.errors.consignee_name }}</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700">Destination</label>
                <input v-model="form.destination" type="text" class="mt-1 block w-full rounded border-gray-300" />
                <p v-if="form.errors.destination" class="text-red-600 text-sm mt-1">{{ form.errors.destination }}</p>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700">Consignee Contact No.</label>
                <input v-model="form.consignee_contact" type="text" class="mt-1 block w-full rounded border-gray-300" />
                <p v-if="form.errors.consignee_contact" class="text-red-600 text-sm mt-1">{{ form.errors.consignee_contact }}</p>
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700">Package Type(s)</label>
              <select v-model="form.messengerial_kinds" multiple class="mt-1 block w-full rounded border-gray-300">
                <option value="Letter Envelope">Letter Envelope</option>
                <option value="Folder or Brown Envelope">Folder or Brown Envelope</option>
                <option value="Box Small">Box Small</option>
                <option value="Box Medium">Box Medium</option>
                <option value="Box Large">Box Large</option>
              </select>
              <p v-if="form.errors.messengerial_kinds" class="text-red-600 text-sm mt-1">{{ form.errors.messengerial_kinds }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700">Delivery Method(s)</label>
                <select v-model="form.delivery_methods" multiple class="mt-1 block w-full rounded border-gray-300">
                <option value="Hand-Carry">Hand-Carry</option>
                <option value="Courier Services">Courier Services</option>
              </select>
              <p v-if="form.errors.delivery_methods" class="text-red-600 text-sm mt-1">{{ form.errors.delivery_methods }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700">Purpose</label>
              <input v-model="form.purpose" type="text" class="mt-1 block w-full rounded border-gray-300" />
              <p v-if="form.errors.purpose" class="text-red-600 text-sm mt-1">{{ form.errors.purpose }}</p>
            </div>

            <!-- Consignee email removed because delivery methods no longer include email option -->

            <div class="flex flex-col sm:flex-row gap-2">
              <button :disabled="form.processing" type="submit" class="bg-blue-600 text-white px-4 py-2 rounded w-full sm:w-auto disabled:opacity-60">{{ form.processing ? 'Submitting...' : 'Submit' }}</button>
              <button @click.prevent="closeModal" type="button" class="px-4 py-2 rounded border w-full sm:w-auto">Cancel</button>
            </div>
          </form>
        </div>
      </div>

      <!-- Upload Proof Modal -->
      <div v-if="showUploadModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
        <div class="bg-white w-full h-full sm:h-auto sm:rounded-xl sm:shadow-lg sm:max-w-md p-4 sm:p-6 relative overflow-auto">
          <button class="absolute top-3 right-3 text-gray-500 hover:text-gray-800" @click="showUploadModal = false; selectedRequest = null">✕</button>
          <h2 class="text-xl font-semibold mb-4">Upload Proof of Delivery</h2>
          <p class="text-sm text-gray-600 mb-3">Request ID: {{ selectedRequest?.id }}</p>
          <form @submit.prevent="submitProof" class="space-y-4 max-h-[85vh] overflow-auto" enctype="multipart/form-data">
            <div>
              <label class="block text-sm font-medium text-gray-700">Proof (PDF/JPG/PNG)</label>
              <input type="file" @change="e => proofForm.proof = e.target.files[0]" class="mt-1 block w-full" />
              <p v-if="proofForm.errors.proof" class="text-red-600 text-sm mt-1">{{ proofForm.errors.proof }}</p>
            </div>
            <!-- RFSF Reference No. removed as requested -->

            <div v-if="selectedRequest?.delivery_methods && selectedRequest.delivery_methods.includes('Courier Services')">
              <div>
                <label class="block text-sm font-medium text-gray-700">Courier Service Provider</label>
                <input v-model="proofForm.courier_service_provider" type="text" class="mt-1 block w-full rounded border-gray-300" />
                <p v-if="proofForm.errors.courier_service_provider" class="text-red-600 text-sm mt-1">{{ proofForm.errors.courier_service_provider }}</p>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700">Cost</label>
                <input v-model="proofForm.courier_cost" type="number" step="0.01" class="mt-1 block w-full rounded border-gray-300" />
                <p v-if="proofForm.errors.courier_cost" class="text-red-600 text-sm mt-1">{{ proofForm.errors.courier_cost }}</p>
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700">Date Received by Courier</label>
              <input v-model="proofForm.date_received_by_courier" type="datetime-local" class="mt-1 block w-full rounded border-gray-300" />
              <p v-if="proofForm.errors.date_received_by_courier" class="text-red-600 text-sm mt-1">{{ proofForm.errors.date_received_by_courier }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700">Date Delivered to Addressee</label>
              <input v-model="proofForm.date_delivered" type="datetime-local" class="mt-1 block w-full rounded border-gray-300" />
              <p v-if="proofForm.errors.date_delivered" class="text-red-600 text-sm mt-1">{{ proofForm.errors.date_delivered }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700">Remarks</label>
              <textarea v-model="proofForm.proof_remarks" class="mt-1 block w-full rounded border-gray-300" rows="3"></textarea>
              <p v-if="proofForm.errors.proof_remarks" class="text-red-600 text-sm mt-1">{{ proofForm.errors.proof_remarks }}</p>
            </div>
            <div class="flex flex-col sm:flex-row gap-2">
              <button :disabled="proofForm.processing" type="submit" class="bg-blue-600 text-white px-4 py-2 rounded w-full sm:w-auto disabled:opacity-60">{{ proofForm.processing ? 'Uploading...' : 'Upload' }}</button>
              <button @click.prevent="showUploadModal = false; selectedRequest = null" type="button" class="px-4 py-2 rounded border w-full sm:w-auto">Cancel</button>
            </div>
          </form>
        </div>
      </div>

    </div>
  </AdminLayout>
</template>
