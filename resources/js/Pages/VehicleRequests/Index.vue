<script setup>
import { Head, usePage, useForm } from "@inertiajs/vue3";
import { ref, reactive } from "vue";
import { PencilSquareIcon, TrashIcon, UserIcon, PrinterIcon } from "@heroicons/vue/24/outline";
import AdminLayout from "@/Layouts/AdminLayout.vue";

const props = defineProps({ requests: Array, vehicles: Array, divisionChiefs: Array });
const page = usePage();

const showModal = ref(false);
const editingRequest = ref(null);

const banner = ref(null);

// Assign Driver Modal State
const showAssignDriverModal = ref(false);
const assignDriverRequest = ref(null);
const drivers = ref([]);
const selectedDriverId = ref(null);
const assignLoading = ref(false);
// Open assign driver modal
const openAssignDriverModal = async (req) => {
  assignDriverRequest.value = req;
  showAssignDriverModal.value = true;
  selectedDriverId.value = req.driver_id || null;
  assignLoading.value = true;
  try {
    const res = await fetch('/api/drivers');
    drivers.value = await res.json();
  } catch (e) {
    setBanner('error', 'Failed to load drivers');
    drivers.value = [];
  }
  assignLoading.value = false;
};

const closeAssignDriverModal = () => {
  showAssignDriverModal.value = false;
  assignDriverRequest.value = null;
  selectedDriverId.value = null;
  drivers.value = [];
};

const assignDriver = async () => {
  if (!selectedDriverId.value || !assignDriverRequest.value) return;
  assignLoading.value = true;
  try {
    await window.axios.post(`/vehicle-requests/${assignDriverRequest.value.id}/assign-driver`, {
      driver_id: selectedDriverId.value
    });
    setBanner('success', 'Driver assigned successfully');
    closeAssignDriverModal();
    window.location.reload();
  } catch (e) {
    setBanner('error', 'Failed to assign driver');
  }
  assignLoading.value = false;
};

const openPrint = (req) => {
  let url;
  try {
    url = route('vehicle-requests.print', req.id);
  } catch (e) {
    url = `/vehicle-requests/${req.id}/print`;
  }
  window.open(url, '_blank');
};

const setBanner = (type, message, ms = 5000) => {
  banner.value = { type, message };
  if (ms > 0) {
    setTimeout(() => { banner.value = null; }, ms);
  }
};

const form = useForm({
  purpose: '',
  destination: '',
  // support multiple dates as an array
  date_needed: [],
  time_of_departure: '',
  eta: '',
  vehicle_type: '',
  division_chief_id: '',
  passengers: 1,
  status: '',
});

const dateInput = ref('');
const addDate = () => {
  if (!dateInput.value) return;
  // prevent duplicates
  if (!form.date_needed.includes(dateInput.value)) {
    form.date_needed.push(dateInput.value);
  }
  dateInput.value = '';
};

const openModal = (req = null) => {
  editingRequest.value = req;
  if (req) {
    form.reset();
    form.purpose = req.purpose;
    form.destination = req.destination;
    // prefer multiple dates array if present, otherwise use single date as array
    form.date_needed = req.date_needed_multiple ?? (req.date_needed ? [req.date_needed] : []);
    form.time_of_departure = req.time_of_departure ?? '';
    form.eta = req.eta ?? '';
    form.vehicle_type = req.vehicle_type ?? '';
    form.passengers = req.passengers ?? 1;
    form.division_chief_id = req.division_chief_id ?? '';
    form.status = req.status ?? '';
  } else {
    form.reset();
    form.status = '';
  }
  showModal.value = true;
}

const closeModal = () => { showModal.value = false; editingRequest.value = null; form.reset(); };

const submit = () => {
  try {
    console.log('Submitting vehicle request', { editing: !!editingRequest.value, data: form.data() });
    setBanner(null, null); // clear any existing banner
    if (editingRequest.value) {
      // resolve id from various possible shapes
      const resolvedId = editingRequest.value?.id ?? editingRequest.value?.vehicleRequest ?? editingRequest.value?.vehicle_request_id ?? editingRequest.value?.attributes?.id;
      if (!resolvedId) {
        console.error('No id found on editingRequest', editingRequest.value);
        setBanner('error', 'Cannot determine request id for update. See console.');
        return;
      }

      let updateUrl;
      try {
        updateUrl = route('vehicle-requests.update', resolvedId);
      } catch (e) {
        console.error('Ziggy route generation failed for update, falling back to raw URL', e);
        updateUrl = `/vehicle-requests/${resolvedId}`;
      }
      console.log('Update URL:', updateUrl);
      setBanner(null, `Calling ${updateUrl}` , 2000);
      form.put(updateUrl, {
        onSuccess: () => { setBanner('success', 'Vehicle request updated'); },
        onError: (errs) => {
          console.error('Vehicle request update errors', errs);
          const serverErr = window.lastVehicleRequestError ?? {};
          const status = serverErr.status ?? (errs?.status ?? 'unknown');
          const data = serverErr.data ?? errs;
          setBanner('error', `Error ${status}: ${serverErr.data?.message ?? JSON.stringify(data)}`);
          const vmsg = serverErr.data?.errors?.vehicle ?? errs?.vehicle ?? serverErr.data?.vehicle;
          if (vmsg) { alert(Array.isArray(vmsg) ? vmsg.join('\n') : vmsg); }
          console.log('Captured server error', serverErr);
        },
        onFinish: () => {
          console.log('Vehicle request update finished');
          if (Object.keys(form.errors).length === 0) {
            // if axios captured an error body, show it briefly then close
            if (window.lastVehicleRequestError && window.lastVehicleRequestError.data) {
              setBanner('error', JSON.stringify(window.lastVehicleRequestError.data), 4000);
            }
            closeModal();
          }
        }
      })
      } else {
      const storeUrl = route('vehicle-requests.store');
      console.log('Store URL:', storeUrl);
      setBanner(null, `Calling ${storeUrl}` , 2000);
      form.post(storeUrl, {
        onSuccess: () => {},
        onError: (errs) => {
          console.error('Vehicle request create errors', errs);
          const serverErr = window.lastVehicleRequestError ?? {};
          const status = serverErr.status ?? (errs?.status ?? 'unknown');
          const data = serverErr.data ?? errs;
          setBanner('error', `Error ${status}: ${serverErr.data?.message ?? JSON.stringify(data)}`);
          const vmsg = serverErr.data?.errors?.vehicle ?? errs?.vehicle ?? serverErr.data?.vehicle;
          if (vmsg) { alert(Array.isArray(vmsg) ? vmsg.join('\n') : vmsg); }
          console.log('Captured server error', serverErr);
        },
        onFinish: () => {
          console.log('Vehicle request create finished');
          if (Object.keys(form.errors).length === 0) {
            if (window.lastVehicleRequestError && window.lastVehicleRequestError.data) {
              setBanner('error', JSON.stringify(window.lastVehicleRequestError.data), 4000);
            }
            closeModal();
          }
        }
      })
    }
  } catch (e) {
    console.error('Submit failed', e);
    setBanner('error', 'Unexpected error. See console.');
  }
}

const destroy = (req) => {
  if (!confirm('Delete this vehicle request?')) return;
    import('@inertiajs/vue3').then(({ router }) => {
    console.log('Deleting vehicle request', req.id);
    setBanner(null, null);
    let destroyUrl;
    try {
      destroyUrl = route('vehicle-requests.destroy', req.id);
    } catch (e) {
      console.error('Ziggy route generation failed for destroy, falling back to raw URL', e);
      destroyUrl = `/vehicle-requests/${req.id}`;
    }
    router.delete(destroyUrl, {
      onSuccess: () => {},
      onError: (e) => { console.error('Delete error', e); setBanner('error', 'Failed to delete request'); },
      onFinish: () => console.log('Delete finished')
    })
  }).catch(e => { console.error('Router import failed', e); setBanner('error', 'Internal error'); })
}
</script>

<template>
  <Head title="Vehicle Requests" />
  <AdminLayout title="Vehicle Requests">
    <div class="p-6">
      <div v-if="page.props.flash?.success" class="mb-4">
        <div class="px-4 py-3 rounded bg-green-50 border border-green-100 text-green-700">{{ page.props.flash.success }}</div>
      </div>
      <div v-if="banner" class="mb-4">
        <div v-if="banner.type === 'success'" class="px-4 py-3 rounded bg-green-50 border border-green-100 text-green-700">{{ banner.message }}</div>
        <div v-else-if="banner.type === 'error'" class="px-4 py-3 rounded bg-red-50 border border-red-100 text-red-700">{{ banner.message }}</div>
        <div v-else class="px-4 py-3 rounded bg-gray-50 border border-gray-100 text-gray-700">{{ banner.message }}</div>
      </div>
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Vehicle Requests</h1>
        <button
          v-if="page.props.auth?.user?.role?.name !== 'GSU Head'"
          @click.prevent="openModal()"
          class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow"
        >
          + New Request
        </button>
      </div>

      <div class="bg-white rounded-xl shadow p-4">
        <!-- Desktop table -->
        <div class="hidden sm:block overflow-x-auto">
          <table class="table-fixed w-full border border-gray-200">
            <thead class="bg-gray-100 text-gray-700 uppercase text-sm">
              <tr>
                <th class="px-4 py-3 text-left whitespace-normal break-words">#</th>
                <th class="px-4 py-3 text-left whitespace-normal break-words">Purpose</th>
                <th class="px-4 py-3 text-left whitespace-normal break-words">Vehicle</th>
                <th class="px-4 py-3 text-left whitespace-normal break-words">Date Needed</th>
                <th class="px-4 py-3 text-left whitespace-normal break-words">Departure</th>
                <th class="px-4 py-3 text-left whitespace-normal break-words">ETA</th>
                <th class="px-4 py-3 text-left whitespace-normal break-words">Status</th>
                <th class="px-4 py-3 text-left whitespace-normal break-words">Submitted By</th>
                <th class="px-4 py-3 text-left whitespace-normal break-words">Driver</th>
                <th class="px-4 py-3 text-center whitespace-normal break-words">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 text-sm">
              <tr v-for="req in props.requests" :key="req.id">
                <td class="px-4 py-3 whitespace-normal break-words">{{ req.id }}</td>
                <td class="px-4 py-3 whitespace-normal break-words">{{ req.purpose }}</td>
                <td class="px-4 py-3 whitespace-normal break-words">{{ req.vehicle_type ?? '—' }}</td>
                <td class="px-4 py-3"> 
                  <div v-if="req.date_needed_multiple && req.date_needed_multiple.length">
                    <div v-for="(d, i) in req.date_needed_multiple" :key="i">{{ new Date(d).toLocaleDateString() }}</div>
                  </div>
                  <div v-else>{{ req.date_needed ? new Date(req.date_needed).toLocaleDateString() : '—' }}</div>
                </td>
                <td class="px-4 py-3">{{ req.time_of_departure ?? '—' }}</td>
                <td class="px-4 py-3">{{ req.eta ?? '—' }}</td>
                <td class="px-4 py-3">
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
                <td class="px-4 py-3 whitespace-normal break-words">{{ req.user?.name ?? '—' }}</td>
                <td class="px-4 py-3 whitespace-normal break-words">{{ req.driver?.name ?? '—' }}</td>
                <td class="px-4 py-3 text-center whitespace-normal break-words">
                  <div class="flex items-center gap-2 justify-center">
                    <!-- Edit (pencil) -->
                    <button
                      v-if="page.props.auth?.user?.role?.name === 'Administrator' && req.status !== 'Approved' && req.status !== 'Declined' && req.status !== 'OCD Approved'"
                      @click.prevent="openModal(req)"
                      class="p-2 rounded-full bg-blue-100 hover:bg-blue-200 text-blue-700"
                      title="Edit"
                    >
                      <PencilSquareIcon class="w-5 h-5" />
                    </button>

                    <!-- Delete (trash) -->
                    <button
                      v-if="page.props.auth?.user?.role?.name === 'Administrator' && req.status !== 'Approved' && req.status !== 'Declined' && req.status !== 'OCD Approved'"
                      @click.prevent="destroy(req)"
                      class="p-2 rounded-full bg-red-100 hover:bg-red-200 text-red-700"
                      title="Delete"
                    >
                      <TrashIcon class="w-5 h-5" />
                    </button>

                    <!-- Assign Driver (user) -->
                    <button
                      v-if="['Administrator','GSU Head'].includes(page.props.auth?.user?.role?.name) && req.status === 'Approved' && !req.driver"
                      @click.prevent="openAssignDriverModal(req)"
                      class="p-2 rounded-full bg-indigo-100 hover:bg-indigo-200 text-indigo-700"
                      title="Assign Driver"
                    >
                      <UserIcon class="w-5 h-5" />
                    </button>

                    <!-- Print (printer) -->
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
                <td colspan="10" class="px-4 py-6 text-center text-gray-500">No vehicle requests found.</td>
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
                <div class="font-semibold text-gray-800">{{ req.purpose ?? '—' }}</div>
                <div class="text-sm text-gray-600">{{ req.vehicle_type ?? '—' }} — {{ req.user?.name ?? '—' }}</div>
              </div>
              <div class="text-right text-sm">
                <div class="text-gray-600">{{ req.date_needed_multiple && req.date_needed_multiple.length ? (new Date(req.date_needed_multiple[0]).toLocaleDateString()) : (req.date_needed ? new Date(req.date_needed).toLocaleDateString() : '—') }}</div>
                <div class="text-gray-500 text-xs">{{ req.time_of_departure ?? '—' }}</div>
              </div>
            </div>

            <div class="mt-2 text-sm text-gray-700">
              <div><strong>ETA:</strong> <span class="ml-1">{{ req.eta ?? '—' }}</span></div>
              <div class="mt-1"><strong>Driver:</strong> <span class="ml-1">{{ req.driver?.name ?? '—' }}</span></div>
              <div class="mt-1"><strong>Status:</strong> <span class="ml-1">{{ req.status }}</span></div>
            </div>

            <div class="mt-3 flex items-center gap-2">
              <button
                v-if="page.props.auth?.user?.role?.name === 'Administrator' && req.status !== 'Approved' && req.status !== 'Declined' && req.status !== 'OCD Approved'"
                @click.prevent="openModal(req)"
                class="flex-1 inline-flex items-center justify-center gap-2 px-3 py-2 bg-blue-600 text-white rounded-md"
              >
                <PencilSquareIcon class="w-4 h-4" /> Edit
              </button>

              <button
                v-if="page.props.auth?.user?.role?.name === 'Administrator' && req.status !== 'Approved' && req.status !== 'Declined' && req.status !== 'OCD Approved'"
                @click.prevent="destroy(req)"
                class="inline-flex items-center gap-2 px-3 py-2 bg-red-100 text-red-700 rounded-md"
              >
                <TrashIcon class="w-4 h-4" />
              </button>

              <button
                v-if="['Administrator','GSU Head'].includes(page.props.auth?.user?.role?.name) && req.status === 'Approved' && !req.driver"
                @click.prevent="openAssignDriverModal(req)"
                class="inline-flex items-center gap-2 px-3 py-2 bg-indigo-100 text-indigo-700 rounded-md"
              >
                <UserIcon class="w-4 h-4" /> Assign
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

          <div v-if="props.requests.length === 0" class="text-center text-gray-500 py-6">No vehicle requests found.</div>
        </div>
      </div>
    </div>
    <!-- Create Modal -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div class="bg-white w-full h-full sm:h-auto sm:rounded-xl sm:shadow-lg sm:max-w-md p-4 sm:p-6 relative overflow-auto">
        <button class="absolute top-3 right-3 text-gray-500 hover:text-gray-800" @click="closeModal">✕</button>
        <h2 class="text-xl font-semibold mb-4">New Vehicle Request</h2>
        <div class="space-y-4 max-h-[90vh] overflow-auto">
          <div>
            <label class="block text-sm font-medium text-gray-700">Purpose</label>
            <input v-model="form.purpose" type="text" class="mt-1 block w-full rounded border-gray-300" />
            <p v-if="form.errors.purpose" class="text-red-600 text-sm mt-1">{{ form.errors.purpose }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700">Destination</label>
            <input v-model="form.destination" type="text" class="mt-1 block w-full rounded border-gray-300" />
            <p v-if="form.errors.destination" class="text-red-600 text-sm mt-1">{{ form.errors.destination }}</p>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700">Time of Departure</label>
              <input v-model="form.time_of_departure" type="time" class="mt-1 block w-full rounded border-gray-300" />
              <p v-if="form.errors.time_of_departure" class="text-red-600 text-sm mt-1">{{ form.errors.time_of_departure }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Estimated Time of Arrival</label>
              <input v-model="form.eta" type="time" class="mt-1 block w-full rounded border-gray-300" />
              <p v-if="form.errors.eta" class="text-red-600 text-sm mt-1">{{ form.errors.eta }}</p>
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700">Date(s) Needed</label>
            <div class="mt-1 flex flex-col sm:flex-row sm:items-start gap-2">
              <input v-model="dateInput" type="date" class="block rounded border-gray-300" />
              <button @click.prevent="addDate" class="px-3 py-1 bg-blue-600 text-white rounded">Add</button>
            </div>
            <p v-if="form.errors['date_needed']" class="text-red-600 text-sm mt-1">{{ form.errors['date_needed'] }}</p>
            <ul class="mt-2 list-disc pl-5 text-sm text-gray-700">
              <li v-for="(d, idx) in form.date_needed" :key="idx" class="flex items-center justify-between">
                <span>{{ new Date(d).toLocaleDateString() }}</span>
                <button @click.prevent="form.date_needed.splice(idx,1)" class="text-red-500 text-sm">Remove</button>
              </li>
              <li v-if="form.date_needed.length === 0" class="text-gray-400">No dates added.</li>
            </ul>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700">Vehicle Type</label>
            <select v-model="form.vehicle_type" class="mt-1 block w-full rounded border-gray-300">
              <option value="">Select vehicle</option>
              <option v-for="v in props.vehicles" :key="v.id" :value="v.name">{{ v.name }}</option>
            </select>
            <p v-if="form.errors.vehicle_type" class="text-red-600 text-sm mt-1">{{ form.errors.vehicle_type }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700">Division Chief (Approver)</label>
            <select v-model="form.division_chief_id" class="mt-1 block w-full rounded border-gray-300">
              <option value="">Select division chief</option>
              <option v-for="d in props.divisionChiefs" :key="d.id" :value="d.id">{{ d.name }}</option>
            </select>
            <p v-if="form.errors.division_chief_id" class="text-red-600 text-sm mt-1">{{ form.errors.division_chief_id }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700">Passengers</label>
            <input v-model.number="form.passengers" type="number" min="1" class="mt-1 block w-24 rounded border-gray-300" />
            <p v-if="form.errors.passengers" class="text-red-600 text-sm mt-1">{{ form.errors.passengers }}</p>
          </div>

          <div class="flex flex-col sm:flex-row gap-2">
            <button @click.prevent="submit" :disabled="form.processing" class="bg-blue-600 text-white px-4 py-2 rounded w-full sm:w-auto disabled:opacity-60">Submit</button>
            <button @click.prevent="closeModal" class="px-4 py-2 rounded border w-full sm:w-auto">Cancel</button>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
