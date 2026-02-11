<script setup>
import { Head, useForm, router, usePage } from "@inertiajs/vue3";
import { ref, reactive, computed, watch, onMounted, onBeforeUnmount } from "vue";
import Swal from 'sweetalert2'
import AdminLayout from "@/Layouts/AdminLayout.vue";
import { PencilSquareIcon, TrashIcon, PrinterIcon, CheckIcon, XMarkIcon } from "@heroicons/vue/24/outline";

const props = defineProps({ requests: Object });
const page = usePage();

// client-side search + pagination (requests may be paginated server-side in props.requests.data)
const requestsList = ref((props.requests && props.requests.data) ? props.requests.data : (props.requests || []))
const searchQuery = ref('')
const currentPage = ref(1)
const perPage = 10

// responsive: track window width to toggle between table and card layouts
const windowWidth = ref(typeof window !== 'undefined' ? window.innerWidth : 1200)
const isMobile = computed(() => windowWidth.value < 768)
const handleResize = () => { windowWidth.value = window.innerWidth }
onMounted(() => { window.addEventListener('resize', handleResize) })
onBeforeUnmount(() => { window.removeEventListener('resize', handleResize) })

const filteredRequests = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  const results = (requestsList.value || []).filter(r => (r.service_type || '').toString().toLowerCase().includes(q) || (r.purposes || '').toString().toLowerCase().includes(q) || (r.id || '').toString().includes(q))
  const start = (currentPage.value - 1) * perPage
  return results.slice(start, start + perPage)
})

const totalPages = computed(() => Math.max(1, Math.ceil((requestsList.value || []).filter(r => (r.service_type || '').toString().toLowerCase().includes(searchQuery.value.trim().toLowerCase()) || (r.purposes || '').toString().toLowerCase().includes(searchQuery.value.trim().toLowerCase()) || (r.id || '').toString().includes(searchQuery.value.trim())).length / perPage)))

watch(searchQuery, () => { currentPage.value = 1 })
const roleName = computed(() => page.props.auth?.user?.role?.name ?? '');

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
});

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
  } else {
    form.post(route('service-requests.store'), {
      onSuccess: () => { closeModal(); Swal.fire({ icon: 'success', title: 'Request submitted', timer: 1200, showConfirmButton: false }).then(() => { window.location.reload() }) },
      onError: (errs) => { Swal.fire({ icon: 'error', title: 'Failed to submit', text: Object.values(errs || {}).flat().join('\n') || 'Failed to submit' }) }
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

const approveRequest = (r) => {
  Swal.fire({ title: 'Approve this request?', icon: 'question', showCancelButton: true, confirmButtonText: 'Approve' }).then(res => {
    if (!res.isConfirmed) return;
    router.post(route('service-requests.approve.inapp', r.id), {}, {
      onSuccess: () => { Swal.fire({ icon: 'success', title: 'Approved', timer: 1000, showConfirmButton: false }).then(() => window.location.reload()) },
      onError: (errs) => { Swal.fire({ icon: 'error', title: 'Failed', text: Object.values(errs || {}).flat().join('\n') || 'Failed to approve' }) }
    })
  })
}

const declineRequest = (r) => {
  Swal.fire({ title: 'Reason for declining', input: 'text', inputPlaceholder: 'Enter reason', showCancelButton: true }).then(res => {
    if (!res.isConfirmed || !res.value) return;
    router.post(route('service-requests.decline.inapp', r.id), { reason: res.value }, {
      onSuccess: () => { Swal.fire({ icon: 'success', title: 'Declined', timer: 1000, showConfirmButton: false }).then(() => window.location.reload()) },
      onError: (errs) => { Swal.fire({ icon: 'error', title: 'Failed', text: Object.values(errs || {}).flat().join('\n') || 'Failed to decline' }) }
    })
  })
}

const statusClass = (s) => {
  if (!s) return 'bg-gray-100 text-gray-800';
  const st = String(s).toLowerCase();
  if (st.includes('decline')) return 'bg-red-100 text-red-800';
  if (st.includes('approved')) return 'bg-green-100 text-green-800';
  return 'bg-gray-100 text-gray-800';
};

const canPrint = (r) => {
  const st = (r?.status || '').toString().toLowerCase();
  return (roleName.value === 'Administrator' || roleName.value === 'GSU Head') && st.includes('approved');
};
</script>

<template>
  <Head title="Request for Services" />
  <AdminLayout title="Request for Services">
    <div class="p-6">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">Request for Services</h1>
        <button @click="openModal" class="bg-blue-600 text-white px-4 py-2 rounded">+ New Request</button>
      </div>

      <div class="bg-white rounded-xl shadow p-4">
        <!-- Search -->
        <div class="mb-4">
          <input v-model="searchQuery" type="text" placeholder="Search requests..." class="w-1/3 rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" />
        </div>

        <!-- Mobile cards -->
        <div v-if="isMobile" class="space-y-3">
          <div v-for="r in filteredRequests" :key="r.id" class="border rounded-lg p-3 bg-white shadow-sm">
            <div class="flex items-start justify-between">
              <div>
                <div class="text-sm text-gray-500">Request #{{ r.id }}</div>
                <div class="font-semibold text-gray-800">{{ r.service_type ?? '—' }}</div>
                <div v-if="['Administrator','GSU Head','DivisionChief'].includes(roleName)" class="text-sm text-gray-600">Requestor: {{ r.requester?.name ?? '—' }}</div>
                <div class="text-sm text-gray-600">{{ r.purposes ?? '—' }}</div>
              </div>
              <div class="text-right text-sm">
                <div class="text-gray-600">{{ r.date_needed ?? '—' }}</div>
                <div class="text-gray-500 text-xs">{{ r.time_needed ?? '—' }}</div>
              </div>
            </div>

            <div class="mt-2 text-sm text-gray-700">
              <div><strong>Purpose:</strong> <span class="ml-1">{{ r.purposes || '—' }}</span></div>
              <div class="mt-1"><strong>Status:</strong> <span class="ml-1"><span :class="['inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold', statusClass(r.status)]">{{ r.status }}</span></span></div>
            </div>

              <div class="mt-3 flex items-center gap-2">
              <button v-if="r.status === 'Pending' && roleName !== 'Staff'" @click.prevent="openEdit(r)" class="p-2 rounded-full bg-blue-100 hover:bg-blue-200 text-blue-700 inline-flex items-center justify-center gap-2"><PencilSquareIcon class="w-4 h-4"/></button>
              <button v-if="r.status === 'Pending' && roleName !== 'Staff'" @click.prevent="remove(r.id)" class="p-2 rounded-full bg-red-100 hover:bg-red-200 text-red-700 inline-flex items-center gap-2"><TrashIcon class="w-4 h-4"/></button>
              <button v-if="r.status === 'Pending' && roleName === 'DivisionChief'" @click.prevent="approveRequest(r)" class="p-2 rounded-full bg-green-100 hover:bg-green-200 text-green-700 inline-flex items-center gap-2" title="Approve"><CheckIcon class="w-4 h-4"/></button>
              <button v-if="r.status === 'Pending' && roleName === 'DivisionChief'" @click.prevent="declineRequest(r)" class="p-2 rounded-full bg-red-100 hover:bg-red-200 text-red-700 inline-flex items-center gap-2" title="Decline"><XMarkIcon class="w-4 h-4"/></button>
              <a v-if="canPrint(r)" :href="route('service-requests.print', r.id)" target="_blank" class="p-2 rounded-full bg-gray-200 hover:bg-gray-300 text-green-700 inline-flex items-center gap-2" title="Print"><PrinterIcon class="w-4 h-4"/></a>
            </div>
          </div>

          <div v-if="filteredRequests.length === 0" class="text-center text-gray-500 py-6">No requests</div>
        </div>

        <!-- Pagination (mobile) -->
        <div v-if="isMobile" class="flex justify-center items-center gap-2 mt-4">
          <button
            @click="currentPage--"
            :disabled="currentPage === 1"
            class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50"
          >Prev</button>
          <span>Page {{ currentPage }} of {{ totalPages }}</span>
          <button
            @click="currentPage++"
            :disabled="currentPage === totalPages"
            class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50"
          >Next</button>
        </div>

        <!-- Pagination (moved below table for desktop) -->
        <div v-if="!isMobile" class="overflow-x-auto">
          <table class="min-w-full border">
            <thead class="bg-gray-100 text-sm text-gray-700">
              <tr>
                <th class="px-4 py-2">#</th>
                <th class="px-4 py-2">Service</th>
                <th v-if="['Administrator','GSU Head','DivisionChief'].includes(roleName)" class="px-4 py-2">Requestor</th>
                <th class="px-4 py-2">Date Needed</th>
                <th class="px-4 py-2">Time Needed</th>
                <th class="px-4 py-2">Purpose(s)</th>
                <th class="px-4 py-2">Status</th>
                <th class="px-4 py-2">Actions</th>
              </tr>
            </thead>
            <tbody class="text-sm divide-y">
              <tr v-for="r in filteredRequests" :key="r.id">
                <td class="px-4 py-2">{{ r.id }}</td>
                <td class="px-4 py-2">{{ r.service_type }} <div v-if="r.service_type==='Reproduction'" class="text-xs text-gray-600">{{ r.copies }} copies × {{ r.sheets_per_set }} sheets</div></td>
                <td v-if="['Administrator','GSU Head','DivisionChief'].includes(roleName)" class="px-4 py-2">{{ r.requester?.name ?? '—' }}</td>
                <td class="px-4 py-2">{{ r.date_needed }}</td>
                <td class="px-4 py-2">{{ r.time_needed || '—' }}</td>
                <td class="px-4 py-2">{{ r.purposes || '—' }}</td>
                <td class="px-4 py-2">
                  <span :class="['inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold', statusClass(r.status)]">{{ r.status }}</span>
                </td>
                <td class="px-4 py-2">
                  <div class="flex items-center gap-2">
                    <button v-if="r.status === 'Pending' && roleName !== 'Staff'" @click.prevent="openEdit(r)" class="p-2 rounded-full bg-blue-100 hover:bg-blue-200 text-blue-700" title="Edit">
                      <PencilSquareIcon class="w-5 h-5" />
                    </button>
                    <button v-if="r.status === 'Pending' && roleName !== 'Staff'" @click.prevent="remove(r.id)" class="p-2 rounded-full bg-red-100 hover:bg-red-200 text-red-700" title="Delete">
                      <TrashIcon class="w-5 h-5" />
                    </button>
                    <button v-if="r.status === 'Pending' && roleName === 'DivisionChief'" @click.prevent="approveRequest(r)" class="p-2 rounded-full bg-green-100 hover:bg-green-200 text-green-700" title="Approve"><CheckIcon class="w-5 h-5"/></button>
                    <button v-if="r.status === 'Pending' && roleName === 'DivisionChief'" @click.prevent="declineRequest(r)" class="p-2 rounded-full bg-red-100 hover:bg-red-200 text-red-700" title="Decline"><XMarkIcon class="w-5 h-5"/></button>
                    <a v-if="canPrint(r)" :href="route('service-requests.print', r.id)" target="_blank" class="p-2 rounded-full bg-gray-100 hover:bg-gray-200 text-gray-700" title="Print">
                      <PrinterIcon class="w-5 h-5" />
                    </a>
                  </div>
                </td>
              </tr>
              <tr v-if="filteredRequests.length === 0"><td :colspan="(['Administrator','GSU Head','DivisionChief'].includes(roleName) ? 8 : 7)" class="px-4 py-6 text-center text-gray-500">No requests</td></tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination (desktop) -->
        <div v-if="!isMobile" class="flex justify-center items-center gap-2 mt-4">
          <button
            @click="currentPage--"
            :disabled="currentPage === 1"
            class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50"
          >Prev</button>
          <span>Page {{ currentPage }} of {{ totalPages }}</span>
          <button
            @click="currentPage++"
            :disabled="currentPage === totalPages"
            class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50"
          >Next</button>
        </div>
      </div>

      <!-- Modal -->
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
        <div class="bg-white w-full max-w-lg rounded p-4">
          <h2 class="text-xl font-semibold mb-4">{{ editingId ? 'Edit Service Request' : 'New Service Request' }}</h2>
          <div class="space-y-3">
            <div>
              <label class="block text-sm">Service requested</label>
              <select v-model="form.service_type" @change="validateField('service_type')" :class="['w-full rounded p-2', fieldErrors.service_type ? 'border-red-600' : 'border-gray-300']">
                <option>Reproduction</option>
                <option>Security</option>
                <option>Janitorial</option>
              </select>
              <p v-if="fieldErrors.service_type" class="mt-1 text-xs text-red-600">{{ fieldErrors.service_type }}</p>
            </div>

            <div v-if="form.service_type === 'Reproduction'" class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm">Number of copies</label>
                <input v-model.number="form.copies" @input="validateField('copies')" type="number" min="1" :class="['w-full rounded p-2', fieldErrors.copies ? 'border-red-600' : 'border-gray-300']" />
                <p v-if="fieldErrors.copies" class="mt-1 text-xs text-red-600">{{ fieldErrors.copies }}</p>
              </div>
              <div>
                <label class="block text-sm">Sheets per set</label>
                <input v-model.number="form.sheets_per_set" @input="validateField('sheets_per_set')" type="number" min="1" :class="['w-full rounded p-2', fieldErrors.sheets_per_set ? 'border-red-600' : 'border-gray-300']" />
                <p v-if="fieldErrors.sheets_per_set" class="mt-1 text-xs text-red-600">{{ fieldErrors.sheets_per_set }}</p>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm">Date Needed</label>
                <input v-model="form.date_needed" @change="validateField('date_needed')" type="date" :class="['w-full rounded p-2', fieldErrors.date_needed ? 'border-red-600' : 'border-gray-300']" />
                <p v-if="fieldErrors.date_needed" class="mt-1 text-xs text-red-600">{{ fieldErrors.date_needed }}</p>
              </div>
              <div>
                <label class="block text-sm">Time Needed</label>
                <input v-model="form.time_needed" @change="validateField('time_needed')" type="time" :class="['w-full rounded p-2', fieldErrors.time_needed ? 'border-red-600' : 'border-gray-300']" />
                <p v-if="fieldErrors.time_needed" class="mt-1 text-xs text-red-600">{{ fieldErrors.time_needed }}</p>
              </div>
            </div>

            <div>
              <label class="block text-sm">Purpose(s)</label>
              <input v-model="form.purposes" @input="validateField('purposes')" :class="['w-full rounded p-2', fieldErrors.purposes ? 'border-red-600' : 'border-gray-300']" />
              <p v-if="fieldErrors.purposes" class="mt-1 text-xs text-red-600">{{ fieldErrors.purposes }}</p>
            </div>

            <div>
              <label class="block text-sm">Details</label>
              <textarea v-model="form.details" @input="validateField('details')" :class="['w-full rounded p-2', fieldErrors.details ? 'border-red-600' : 'border-gray-300']" rows="4"></textarea>
              <p v-if="fieldErrors.details" class="mt-1 text-xs text-red-600">{{ fieldErrors.details }}</p>
            </div>

            <div class="flex justify-end gap-2">
              <button @click="closeModal" class="px-4 py-2 rounded border">Cancel</button>
              <button @click="submit" :disabled="form.processing" class="px-4 py-2 bg-blue-600 text-white rounded disabled:opacity-60 inline-flex items-center justify-center">
                <span v-if="form.processing" class="inline-flex items-center">
                  <svg class="animate-spin mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                  </svg>
                  Processing...
                </span>
                <span v-else>{{ editingId ? 'Update' : 'Submit' }}</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
