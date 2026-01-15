<script setup>
import { Head, usePage, useForm } from "@inertiajs/vue3";
import { ref, reactive } from "vue";
import { PencilSquareIcon, TrashIcon, PrinterIcon } from "@heroicons/vue/24/outline";
import AdminLayout from "@/Layouts/AdminLayout.vue";

const props = defineProps({ requests: Array, facilities: Array });
const page = usePage();

const showModal = ref(false);
const form = useForm({
  unit: '',
  activity: '',
  purpose: '',
  venue: [],
  date_start: '',
  date_end: '',
  time_start: '',
  time_end: '',
  equipment: [],
  equipment_quantities: {},
  others: '',
  nature: '',
  nature_other: '',
  participants: '',
  male: null,
  female: null,
});

// Client-side inline validation state
const fieldErrors = reactive({
  activity: '',
  purpose: '',
  nature: '',
  nature_other: '',
  participants: '',
  date_start: '',
  date_end: '',
  time_start: '',
  time_end: '',
  venue: '',
  equipment_quantities: {},
});

const validateField = (field) => {
  switch (field) {
    case 'activity':
      fieldErrors.activity = form.activity && String(form.activity).trim() ? '' : 'Activity is required';
      break;
    case 'purpose':
      fieldErrors.purpose = form.purpose && String(form.purpose).trim() ? '' : 'Purpose is required';
      break;
    case 'nature':
      fieldErrors.nature = form.nature ? '' : 'Nature of activity is required';
      if (form.nature !== 'Others') fieldErrors.nature_other = '';
      break;
    case 'nature_other':
      fieldErrors.nature_other = (form.nature === 'Others' && (!form.nature_other || !String(form.nature_other).trim())) ? 'Please specify nature' : '';
      break;
    case 'participants':
      fieldErrors.participants = form.participants && String(form.participants).trim() ? '' : 'Participants description is required';
      break;
    case 'date_start':
      fieldErrors.date_start = form.date_start ? '' : 'Start date is required';
      if (form.date_start && form.date_end) {
        fieldErrors.date_end = (new Date(form.date_end) < new Date(form.date_start)) ? 'End date cannot be before start date' : '';
      }
      break;
    case 'date_end':
      fieldErrors.date_end = form.date_end ? '' : 'End date is required';
      if (form.date_start && form.date_end) {
        fieldErrors.date_end = (new Date(form.date_end) < new Date(form.date_start)) ? 'End date cannot be before start date' : '';
      }
      break;
    case 'time_start':
      fieldErrors.time_start = form.time_start ? '' : 'Start time is required';
      if (form.time_start && form.time_end) {
        fieldErrors.time_end = (form.time_end <= form.time_start) ? 'End time must be after start time' : '';
      }
      break;
    case 'time_end':
      fieldErrors.time_end = form.time_end ? '' : 'End time is required';
      if (form.time_start && form.time_end) {
        fieldErrors.time_end = (form.time_end <= form.time_start) ? 'End time must be after start time' : '';
      }
      break;
    case 'venue':
      fieldErrors.venue = Array.isArray(form.venue) && form.venue.length > 0 ? '' : 'Select at least one venue';
      break;
    case 'equipment_quantities':
      // ensure quantities for selected equipment are >=1 if provided
      fieldErrors.equipment_quantities = {};
      for (const eq of form.equipment || []) {
        const q = Number(form.equipment_quantities?.[eq] ?? 0);
        fieldErrors.equipment_quantities[eq] = q >= 1 ? '' : 'Enter quantity >= 1';
      }
      break;
  }
};

const validateAll = () => {
  ['activity','purpose','nature','nature_other','participants','date_start','date_end','time_start','time_end','venue','equipment_quantities'].forEach(f => validateField(f));
  // check if any errors exist
  const hasFieldErr = Object.values(fieldErrors).some(v => {
    if (typeof v === 'string') return v && v.length > 0;
    return false;
  });
  // check equipment quantities errors
  const eqErr = Object.values(fieldErrors.equipment_quantities || {}).some(v => v && v.length > 0);
  return !hasFieldErr && !eqErr;
};

const facilityMap = (props.facilities || []).reduce((m, f) => {
  m[f.id] = f.name;
  return m;
}, {});

const venueDisplay = (venue) => {
  if (!venue) return '—';
  const arr = Array.isArray(venue) ? venue : [venue];
  if (arr.length === 0) return '—';
  return arr.map(v => facilityMap[v] ?? v).join(', ');
};

const openModal = (req = null) => {
  if (req) {
    form.reset();
    form.requestor = req.requestor;
    form.unit = req.unit;
    form.activity = req.activity;
    form.purpose = req.purpose;
    // parse stored nature: may contain "Others: detail"
    if (req.nature && req.nature.toString().startsWith('Others:')) {
      form.nature = 'Others';
      form.nature_other = req.nature.toString().replace(/^Others:\s*/i, '');
    } else {
      form.nature = req.nature ?? '';
      form.nature_other = '';
    }
    form.participants = req.participants ?? '';
    form.male = req.male ?? null;
    form.female = req.female ?? null;
    form.venue = Array.isArray(req.venue) ? req.venue : (req.venue ? req.venue : []);
    form.equipment = Array.isArray(req.equipment) ? req.equipment : (req.equipment ? req.equipment : []);
    form.equipment_quantities = req.equipment_quantities ?? {};
    form.others = req.others ?? '';
    form.date_start = req.date_start;
    form.date_end = req.date_end;
    form.time_start = req.time_start ?? '';
    form.time_end = req.time_end ?? '';
  } else {
    form.reset();
  }
  // reset client-side field errors when opening modal
  Object.keys(fieldErrors).forEach(k => {
    if (k === 'equipment_quantities') fieldErrors[k] = {};
    else fieldErrors[k] = '';
  });
  showModal.value = true;
};

const closeModal = () => { showModal.value = false; form.reset(); };

const submit = () => {
  // run client-side validation first
  if (!validateAll()) {
    alert('Please fix the highlighted errors before submitting the form.');
    return;
  }

  form.post(route('facility-requests.store'), {
    onSuccess: () => {
      closeModal();
    },
    onError: (errors) => {
      const venueErr = errors?.venue ?? page.props.errors?.venue;
      if (venueErr) {
        const text = Array.isArray(venueErr) ? venueErr.join(', ') : venueErr;
        alert(text);
      }
    }
  });
};

const destroy = (req) => {
  if (!confirm('Delete this facility request?')) return;
  import('@inertiajs/vue3').then(({ router }) => {
    router.delete(route('facility-requests.destroy', req.id));
  });
};

const openPrint = (req) => {
  let url;
  try {
    url = route('facility-requests.print', req.id);
  } catch (e) {
    url = `/facility-requests/${req.id}/print`;
  }
  window.open(url, '_blank');
};
</script>

<template>
  <Head title="Facility Requests" />
  <AdminLayout title="Facility Requests">
    <div class="p-6">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Facility Requests</h1>
        <button
          v-if="page.props.auth?.user?.role?.name !== 'GSU Head'"
          @click.prevent="openModal()"
          class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow"
        >
          + New Request
        </button>
      </div>

      <div class="bg-white rounded-xl shadow p-4">
        <!-- Desktop table: allow horizontal scroll and fixed layout to avoid overlap -->
        <div class="hidden sm:block overflow-x-auto">
          <table class="table-fixed w-full border border-gray-200">
            <thead class="bg-gray-100 text-gray-700 uppercase text-sm">
              <tr>
                <th class="px-4 py-3 text-left whitespace-normal break-words">#</th>
                <th class="px-4 py-3 text-left whitespace-normal break-words">Requestor</th>
                <th class="px-4 py-3 text-left whitespace-normal break-words">Unit</th>
                <th class="px-4 py-3 text-left whitespace-normal break-words">Activity</th>
                <th class="px-4 py-3 text-left whitespace-normal break-words">Date(s)</th>
                <th class="px-4 py-3 text-left whitespace-normal break-words">Time(s)</th>
                <th class="px-4 py-3 text-left whitespace-normal break-words">Venue</th>
                <th class="px-4 py-3 text-left whitespace-normal break-words">Status</th>
                <th class="px-4 py-3 text-center whitespace-normal break-words">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 text-sm">
              <tr v-for="req in props.requests" :key="req.id">
                <td class="px-4 py-3 whitespace-normal break-words">{{ req.id }}</td>
                <td class="px-4 py-3 whitespace-normal break-words">{{ req.requestor }}</td>
                <td class="px-4 py-3 whitespace-normal break-words">{{ req.unit ?? '—' }}</td>
                <td class="px-4 py-3 whitespace-normal break-words">{{ req.activity ?? '—' }}</td>
                <td class="px-4 py-3 whitespace-normal break-words">{{ req.date_start ? new Date(req.date_start).toLocaleDateString() : '—' }}
                  <span v-if="req.date_end"> — {{ new Date(req.date_end).toLocaleDateString() }}</span>
                </td>
                <td class="px-4 py-3 whitespace-normal break-words">
                  <span v-if="req.time_start">{{ (req.time_start || '').slice(0,5) }}</span>
                  <span v-if="req.time_end"> <span v-if="req.time_start">—</span> {{ (req.time_end || '').slice(0,5) }}</span>
                  <span v-if="!req.time_start && !req.time_end">—</span>
                </td>
                <td class="px-4 py-3 whitespace-normal break-words">{{ venueDisplay(req.venue) }}</td>
                <td class="px-4 py-3 whitespace-normal break-words">
                  <span v-if="req.status && req.status.includes('Approved')" class="bg-green-100 text-green-800 px-2 py-1 rounded font-semibold">
                    {{ req.status }}
                  </span>
                  <span v-else-if="req.status === 'Declined'" class="bg-red-100 text-red-800 px-2 py-1 rounded font-semibold">
                    {{ req.status }}
                  </span>
                  <span v-else>
                    {{ req.status }}
                  </span>
                </td>
                <td class="px-4 py-3 text-center whitespace-normal break-words">
                  <div class="flex items-center gap-2 justify-center">
                    <button
                        v-if="page.props.auth?.user?.role?.name === 'Administrator'"
                        @click.prevent="openModal(req)"
                        class="p-2 rounded-full bg-blue-100 hover:bg-blue-200 text-blue-700"
                        title="Edit"
                    >
                      <PencilSquareIcon class="w-5 h-5" />
                    </button>

                    <button
                      v-if="page.props.auth?.user?.role?.name === 'Administrator'"
                      @click.prevent="destroy(req)"
                      class="p-2 rounded-full bg-red-100 hover:bg-red-200 text-red-700"
                      title="Delete"
                    >
                      <TrashIcon class="w-5 h-5" />
                    </button>
                    
                    <!-- Print button shown when OCD Approved for Admin or GSU Head -->
                    <button
                      v-if="['Administrator','GSU Head'].includes(page.props.auth?.user?.role?.name) && req.status === 'OCD Approved'"
                      @click.prevent="openPrint(req)"
                      class="p-2 rounded-full bg-gray-100 hover:bg-gray-200 text-gray-700"
                      title="Print"
                    >
                      <PrinterIcon class="w-5 h-5" />
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="props.requests.length === 0">
                <td colspan="9" class="px-4 py-6 text-center text-gray-500">No facility requests found.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Mobile cards -->
        <div class="sm:hidden space-y-3">
          <div v-for="req in props.requests" :key="req.id" class="border rounded-lg p-3 bg-white shadow-sm">
            <div class="flex items-start justify-between">
              <div>
                <div class="text-sm text-gray-500">Request #{{ req.id }}</div>
                <div class="font-semibold text-gray-800">{{ req.activity ?? '—' }}</div>
                <div class="text-sm text-gray-600">{{ req.requestor }} — {{ req.unit ?? '—' }}</div>
              </div>
              <div class="text-right text-sm">
                <div class="text-gray-600">{{ req.date_start ? new Date(req.date_start).toLocaleDateString() : '—' }}
                  <div v-if="req.date_end">— {{ new Date(req.date_end).toLocaleDateString() }}</div>
                </div>
              </div>
            </div>

            <div class="mt-2 text-sm text-gray-700">
              <div><strong>Time:</strong> <span class="ml-1">{{ req.time_start ? (req.time_start || '').slice(0,5) : '—' }}<span v-if="req.time_end"> — {{ (req.time_end || '').slice(0,5) }}</span></span></div>
              <div class="mt-1"><strong>Venue:</strong> <span class="ml-1">{{ venueDisplay(req.venue) }}</span></div>
              <div class="mt-1"><strong>Status:</strong> <span class="ml-1">{{ req.status }}</span></div>
            </div>

            <div class="mt-3 flex items-center gap-2">
              <button
                v-if="page.props.auth?.user?.role?.name === 'Administrator'"
                @click.prevent="openModal(req)"
                class="flex-1 inline-flex items-center justify-center gap-2 px-3 py-2 bg-blue-600 text-white rounded-md"
              >
                <PencilSquareIcon class="w-4 h-4" /> Edit
              </button>

              <button
                v-if="page.props.auth?.user?.role?.name === 'Administrator'"
                @click.prevent="destroy(req)"
                class="inline-flex items-center gap-2 px-3 py-2 bg-red-100 text-red-700 rounded-md"
              >
                <TrashIcon class="w-4 h-4" />
              </button>

              <button
                v-if="['Administrator','GSU Head'].includes(page.props.auth?.user?.role?.name) && req.status === 'OCD Approved'"
                @click.prevent="openPrint(req)"
                class="inline-flex items-center gap-2 px-3 py-2 bg-gray-100 text-gray-700 rounded-md"
              >
                <PrinterIcon class="w-4 h-4" /> Print
              </button>
            </div>
          </div>

          <div v-if="props.requests.length === 0" class="text-center text-gray-500 py-6">No facility requests found.</div>
        </div>
      </div>

      <!-- Create Modal -->
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
        <!-- On small screens use full-screen panel; on larger screens use centered modal -->
        <div class="bg-white w-full h-full sm:h-auto sm:rounded-xl sm:shadow-lg sm:max-w-lg p-4 sm:p-6 relative overflow-auto">
          <button class="absolute top-3 right-3 text-gray-500 hover:text-gray-800" @click="closeModal">✕</button>
          <h2 class="text-xl font-semibold mb-4">New Facility Request</h2>
          <div class="space-y-4 max-h-[90vh] overflow-auto">
            <!-- Requestor is set automatically from the authenticated user -->

            <!-- Unit is assigned automatically from the requestor's division -->

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700">Activity</label>
                <input v-model="form.activity" @input="validateField('activity')" type="text" :class="['mt-1 block w-full rounded', fieldErrors.activity ? 'border-red-600' : 'border-gray-300']" />
                <p v-if="fieldErrors.activity" class="mt-1 text-xs text-red-600">{{ fieldErrors.activity }}</p>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700">Purpose</label>
                <input v-model="form.purpose" @input="validateField('purpose')" type="text" :class="['mt-1 block w-full rounded', fieldErrors.purpose ? 'border-red-600' : 'border-gray-300']" />
                <p v-if="fieldErrors.purpose" class="mt-1 text-xs text-red-600">{{ fieldErrors.purpose }}</p>
              </div>
            </div>


            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700">Nature of Activity</label>
                <select v-model="form.nature" @change="validateField('nature')" :class="['mt-1 block w-full rounded', fieldErrors.nature ? 'border-red-600' : 'border-gray-300']">
                  <option value="">-- Select Nature --</option>
                  <option value="Curricular">Curricular</option>
                  <option value="Co-Curricular">Co-Curricular</option>
                  <option value="Others">Others (please specify)</option>
                </select>
                <p v-if="fieldErrors.nature" class="mt-1 text-xs text-red-600">{{ fieldErrors.nature }}</p>
              </div>

            <div v-if="form.nature === 'Others'">
              <label class="block text-sm font-medium text-gray-700">Please specify</label>
              <input v-model="form.nature_other" @input="validateField('nature_other')" type="text" :class="['mt-1 block w-full rounded', fieldErrors.nature_other ? 'border-red-600' : 'border-gray-300']" />
              <p v-if="fieldErrors.nature_other" class="mt-1 text-xs text-red-600">{{ fieldErrors.nature_other }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700">Participants (description)</label>
              <input v-model="form.participants" @input="validateField('participants')" type="text" :class="['mt-1 block w-full rounded', fieldErrors.participants ? 'border-red-600' : 'border-gray-300']" placeholder="e.g. Students, Faculty" />
              <p v-if="fieldErrors.participants" class="mt-1 text-xs text-red-600">{{ fieldErrors.participants }}</p>
            </div>

            </div>















            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700">Number of Male Participants</label>
                <input v-model.number="form.male" type="number" min="0" class="mt-1 block w-full rounded border-gray-300" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Number of Female Participants</label>
                <input v-model.number="form.female" type="number" min="0" class="mt-1 block w-full rounded border-gray-300" />
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700">Start Date</label>
                <input v-model="form.date_start" @change="validateField('date_start')" type="date" :class="['mt-1 block w-full rounded', fieldErrors.date_start ? 'border-red-600' : 'border-gray-300']" />
                <p v-if="fieldErrors.date_start" class="mt-1 text-xs text-red-600">{{ fieldErrors.date_start }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">End Date</label>
                <input v-model="form.date_end" @change="validateField('date_end')" type="date" :class="['mt-1 block w-full rounded', fieldErrors.date_end ? 'border-red-600' : 'border-gray-300']" />
                <p v-if="fieldErrors.date_end" class="mt-1 text-xs text-red-600">{{ fieldErrors.date_end }}</p>
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700">Start Time</label>
                <input v-model="form.time_start" @change="validateField('time_start')" type="time" :class="['mt-1 block w-full rounded', fieldErrors.time_start ? 'border-red-600' : 'border-gray-300']" />
                <p v-if="fieldErrors.time_start" class="mt-1 text-xs text-red-600">{{ fieldErrors.time_start }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">End Time</label>
                <input v-model="form.time_end" @change="validateField('time_end')" type="time" :class="['mt-1 block w-full rounded', fieldErrors.time_end ? 'border-red-600' : 'border-gray-300']" />
                <p v-if="fieldErrors.time_end" class="mt-1 text-xs text-red-600">{{ fieldErrors.time_end }}</p>
              </div>
            </div>


            <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700">Venue</label>
              <select v-model="form.venue" multiple @change="validateField('venue')" :class="['mt-1 block w-full rounded', fieldErrors.venue ? 'border-red-600' : 'border-gray-300']">
                <option v-for="f in props.facilities" :key="f.id" :value="f.id">{{ f.name }}</option>
              </select>
              <p v-if="fieldErrors.venue" class="mt-1 text-xs text-red-600">{{ fieldErrors.venue }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700">Equipment Needed</label>
              <select v-model="form.equipment" multiple @change="validateField('equipment_quantities')" class="mt-1 block w-full rounded border-gray-300">
                <option value="Chairs">Chairs</option>
                <option value="Tables">Tables</option>
                <option value="Microphone">Microphone</option>
                <option value="Whiteboard">Whiteboard</option>
                <option value="Projector">Projector</option>
                <option value="Electric Fans">Electric Fans</option>
                <option value="Airconditioner">Airconditioner</option>
                <option value="Trashbins">Trashbins</option>
              </select>
            </div>

            </div>




















            <div v-if="form.equipment && form.equipment.length" class="space-y-2">
              <label class="block text-sm font-medium text-gray-700">Equipment Quantities</label>
              <div v-for="eq in form.equipment" :key="eq" class="flex flex-col sm:flex-row items-start sm:items-center gap-2">
                <div class="w-full sm:w-1/2">{{ eq }}</div>
                <div class="w-full sm:w-1/2">
                  <input type="number" min="1" v-model.number="form.equipment_quantities[eq]" @input="validateField('equipment_quantities')" :class="['mt-1 block w-full rounded', fieldErrors.equipment_quantities?.[eq] ? 'border-red-600' : 'border-gray-300']" placeholder="Quantity" />
                  <p v-if="fieldErrors.equipment_quantities?.[eq]" class="mt-1 text-xs text-red-600">{{ fieldErrors.equipment_quantities[eq] }}</p>
                </div>
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700">Other Equipment (describe)</label>
              <input v-model="form.others" type="text" class="mt-1 block w-full rounded border-gray-300" placeholder="e.g. podium, stage lights" />
            </div>

            <div class="flex flex-col sm:flex-row gap-2">
              <button @click.prevent="submit" class="bg-blue-600 text-white px-4 py-2 rounded w-full sm:w-auto">Submit</button>
              <button @click.prevent="closeModal" class="px-4 py-2 rounded border w-full sm:w-auto">Cancel</button>
            </div>
          </div>
        </div>
      </div>

    </div>
  </AdminLayout>
</template>
