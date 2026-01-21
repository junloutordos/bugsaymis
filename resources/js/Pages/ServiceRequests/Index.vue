<script setup>
import { Head, useForm, router, usePage } from "@inertiajs/vue3";
import { ref, reactive, computed } from "vue";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import { PencilSquareIcon, TrashIcon, PrinterIcon } from "@heroicons/vue/24/outline";

const props = defineProps({ requests: Object });
const page = usePage();
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
  if (!validateAll()) { alert('Please fix the highlighted errors before submitting.'); return; }
  if (editingId.value) {
    form.put(route('service-requests.update', editingId.value), {
      onSuccess: () => { closeModal(); }
    });
  } else {
    form.post(route('service-requests.store'), {
      onSuccess: () => { closeModal(); }
    });
  }
};

const remove = (id) => {
  if (!confirm('Delete this service request?')) return;
  router.delete(route('service-requests.destroy', id));
};

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
        <div class="overflow-x-auto">
          <table class="min-w-full border">
            <thead class="bg-gray-100 text-sm text-gray-700">
              <tr>
                <th class="px-4 py-2">#</th>
                <th class="px-4 py-2">Service</th>
                <th class="px-4 py-2">Date Needed</th>
                <th class="px-4 py-2">Time Needed</th>
                <th class="px-4 py-2">Purpose(s)</th>
                <th class="px-4 py-2">Status</th>
                <th class="px-4 py-2">Actions</th>
              </tr>
            </thead>
            <tbody class="text-sm divide-y">
              <tr v-for="r in props.requests.data" :key="r.id">
                <td class="px-4 py-2">{{ r.id }}</td>
                <td class="px-4 py-2">{{ r.service_type }} <div v-if="r.service_type==='Reproduction'" class="text-xs text-gray-600">{{ r.copies }} copies × {{ r.sheets_per_set }} sheets</div></td>
                <td class="px-4 py-2">{{ r.date_needed }}</td>
                <td class="px-4 py-2">{{ r.time_needed || '—' }}</td>
                <td class="px-4 py-2">{{ r.purposes || '—' }}</td>
                <td class="px-4 py-2">
                  <span :class="['inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold', statusClass(r.status)]">{{ r.status }}</span>
                </td>
                <td class="px-4 py-2">
                  <div class="flex items-center gap-2">
                    <button v-if="r.status === 'Pending'" @click.prevent="openEdit(r)" class="text-blue-600 hover:text-blue-800 p-1 rounded">
                      <PencilSquareIcon class="w-5 h-5" />
                    </button>
                    <button v-if="r.status === 'Pending'" @click.prevent="remove(r.id)" class="text-red-600 hover:text-red-800 p-1 rounded">
                      <TrashIcon class="w-5 h-5" />
                    </button>
                    <a v-if="canPrint(r)" :href="route('service-requests.print', r.id)" target="_blank" class="p-2 rounded-full bg-gray-200 hover:bg-gray-200 text-green-700" title="Print">
                      <PrinterIcon class="w-5 h-5" />
                    </a>
                  </div>
                </td>
              </tr>
              <tr v-if="(props.requests.data || []).length === 0"><td :colspan="6" class="px-4 py-6 text-center text-gray-500">No requests</td></tr>
            </tbody>
          </table>
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
