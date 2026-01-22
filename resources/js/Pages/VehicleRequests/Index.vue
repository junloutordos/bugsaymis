<script setup>
import { Head, usePage, useForm } from "@inertiajs/vue3";
import { ref, reactive, computed, watch } from "vue";
import { PencilSquareIcon, TrashIcon, UserIcon, PrinterIcon } from "@heroicons/vue/24/outline";
import Swal from 'sweetalert2'
import AdminLayout from "@/Layouts/AdminLayout.vue";

const props = defineProps({ requests: Array, vehicles: Array, divisionChiefs: Array });
const page = usePage();

// search + client-side pagination
const requestsList = ref(props.requests || [])
const searchQuery = ref('')
const currentPage = ref(1)
const perPage = 10

const filteredRequests = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  const results = (requestsList.value || []).filter(req => {
    return (req.purpose || '').toString().toLowerCase().includes(q) ||
      (req.vehicle_type || '').toString().toLowerCase().includes(q) ||
      (req.user?.name || '').toString().toLowerCase().includes(q)
  })
  const start = (currentPage.value - 1) * perPage
  return results.slice(start, start + perPage)
})

const totalPages = computed(() => Math.max(1, Math.ceil((requestsList.value || []).filter(req => {
  const q = searchQuery.value.trim().toLowerCase()
  return (req.purpose || '').toString().toLowerCase().includes(q) ||
    (req.vehicle_type || '').toString().toLowerCase().includes(q) ||
    (req.user?.name || '').toString().toLowerCase().includes(q)
}).length / perPage)))

watch(searchQuery, () => { currentPage.value = 1 })

const showModal = ref(false);
const editingRequest = ref(null);

const banner = ref(null);

// Assign Driver Modal State
const showAssignDriverModal = ref(false);
const assignDriverRequest = ref(null);
const drivers = ref([]);
const selectedDriverId = ref(null);
const selectedVehicleId = ref(null);
const assignLoading = ref(false);
// Open assign driver modal
const openAssignDriverModal = async (req) => {
  console.log('openAssignDriverModal', req);
  assignDriverRequest.value = req;
  showAssignDriverModal.value = true;
  selectedDriverId.value = req.driver_id ?? req.driver?.id ?? null;
  // preselect vehicle by matching name to vehicles list
  try {
    const v = props.vehicles?.find(v => (v.name ?? '') === (req.vehicle_type ?? ''));
    selectedVehicleId.value = v?.id ?? null;
  } catch (e) {
    selectedVehicleId.value = null;
  }
  assignLoading.value = true;
  try {
    const res = await fetch('/api/drivers');
    drivers.value = await res.json();
  } catch (e) {
    console.error('Failed to load drivers', e);
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

// Calendar modal state (vehicle bookings)
const showCalendar = ref(false);
const calendarMonth = ref(new Date());
const bookings = ref([]);

const monthLabel = computed(() => calendarMonth.value.toLocaleString(undefined, { month: 'long', year: 'numeric' }));

const fetchBookings = async () => {
  try {
    const res = await fetch('/vehicle-bookings');
    const data = await res.json();
    bookings.value = Array.isArray(data) ? data : [];
  } catch (e) {
    console.error('Failed to load bookings', e);
    bookings.value = [];
  }
};

const openCalendar = async () => {
  await fetchBookings();
  showCalendar.value = true;
};

const prevMonth = () => {
  const d = calendarMonth.value;
  calendarMonth.value = new Date(d.getFullYear(), d.getMonth() - 1, 1);
};

const nextMonth = () => {
  const d = calendarMonth.value;
  calendarMonth.value = new Date(d.getFullYear(), d.getMonth() + 1, 1);
};

const monthDays = computed(() => {
  const d = calendarMonth.value;
  const year = d.getFullYear();
  const month = d.getMonth();
  const days = [];
  // Determine weekday of the 1st (0=Sun..6=Sat) and pad leading blanks
  const firstWeekday = new Date(year, month, 1).getDay();
  for (let i = 0; i < firstWeekday; i++) days.push(null);
  const last = new Date(year, month + 1, 0).getDate();
  for (let i = 1; i <= last; i++) {
    days.push(new Date(year, month, i));
  }
  // Append trailing blanks so the grid always fills full weeks
  while (days.length % 7 !== 0) days.push(null);
  return days;
});

const bookingsForDate = (dt) => {
  const key = dt.toISOString().slice(0,10);
  return bookings.value.filter(b => (b.date || '').toString().slice(0,10) === key);
};

const assignDriver = async () => {
  if (!selectedDriverId.value || !assignDriverRequest.value) {
    console.warn('assignDriver called without selection or request', { selectedDriverId: selectedDriverId.value, assignDriverRequest: assignDriverRequest.value });
    return;
  }
  assignLoading.value = true;
  try {
    const url = `/vehicle-requests/${assignDriverRequest.value.id}/assign-driver`;
    console.log('assignDriver posting to', url, { driver_id: selectedDriverId.value, vehicle_id: selectedVehicleId.value });
    const payload = { driver_id: selectedDriverId.value };
    if (selectedVehicleId.value) payload.vehicle_id = selectedVehicleId.value;
    if (window.axios && typeof window.axios.post === 'function') {
      await window.axios.post(url, payload);
    } else {
      // fallback to fetch with JSON (may require CSRF token setup in browser)
      const resp = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
        credentials: 'same-origin'
      });
      if (!resp.ok) {
        const data = await resp.json().catch(() => null);
        throw { response: { data, status: resp.status } };
      }
    }
    setBanner('success', 'Driver assigned successfully');
    closeAssignDriverModal();
    window.location.reload();
    } catch (e) {
    console.error('Failed to assign driver', e);
    // Try to extract structured error info from server response
    const srv = e?.response?.data ?? (e?.response ?? null);
    const errMsg = e && e.message ? String(e.message) : '';
    // For assignment errors show an alert instead of the top banner so it's immediately visible.
    if (errMsg && (errMsg.toLowerCase().includes('trailing data') || errMsg.toLowerCase().includes('unexpected token') || errMsg.toLowerCase().includes('syntaxerror') || (e?.response?.status === 422 && !srv))) {
      alert(sanitizeErrorMessage(errMsg));
    } else if (srv && srv.type && srv.message) {
      // Emphasize problem and show whether it's a vehicle or driver conflict
      const kind = srv.type === 'vehicle' ? 'Vehicle conflict' : (srv.type === 'driver' ? 'Driver conflict' : 'Conflict');
      const dates = Array.isArray(srv.dates) && srv.dates.length ? ` Dates: ${srv.dates.join(', ')}` : '';
      alert(`${kind}: ${sanitizeErrorMessage(srv.message)}${dates}`);
    } else if (srv && srv.message) {
      alert(sanitizeErrorMessage(srv.message));
    } else {
      alert(sanitizeErrorMessage(errMsg) || 'Failed to assign driver');
    }
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

// Normalize server/parse errors into friendly messages
const sanitizeErrorMessage = (raw) => {
  if (!raw && raw !== '') return raw;
  const s = String(raw || '').trim();
  const lower = s.toLowerCase();
  if (!s) return s;
  if (lower.includes('trailing data') || lower.includes('unexpected token') || lower.includes('syntaxerror') || lower.includes('json')) {
    return 'Conflict Detected';
  }
  return s;
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

// Client-side inline validation state
const fieldErrors = reactive({
  purpose: '',
  destination: '',
  date_needed: '',
  time_of_departure: '',
  eta: '',
  vehicle_type: '',
  division_chief_id: '',
  passengers: '',
});

const validateField = (field) => {
  switch (field) {
    case 'purpose':
      fieldErrors.purpose = form.purpose && String(form.purpose).trim() ? '' : 'Purpose is required';
      break;
    case 'destination':
      fieldErrors.destination = form.destination && String(form.destination).trim() ? '' : 'Destination is required';
      break;
    case 'date_needed':
      fieldErrors.date_needed = Array.isArray(form.date_needed) && form.date_needed.length > 0 ? '' : 'Add at least one date';
      break;
    case 'time_of_departure':
      fieldErrors.time_of_departure = form.time_of_departure ? '' : 'Time of departure is required';
      break;
    case 'eta':
      fieldErrors.eta = form.eta ? '' : 'Estimated time of arrival is required';
      break;
    case 'vehicle_type':
      fieldErrors.vehicle_type = form.vehicle_type ? '' : 'Select a vehicle type';
      break;
    case 'division_chief_id':
      fieldErrors.division_chief_id = form.division_chief_id ? '' : 'Select a division chief';
      break;
    case 'passengers':
      fieldErrors.passengers = (form.passengers && Number(form.passengers) >= 1) ? '' : 'Passengers must be at least 1';
      break;
  }
};

const validateAll = () => {
  ['purpose','destination','date_needed','time_of_departure','eta','vehicle_type','division_chief_id','passengers'].forEach(f => validateField(f));
  return !Object.values(fieldErrors).some(v => v && v.length > 0);
};

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
  // Validate all fields client-side and prevent submission if invalid
  if (!validateAll()) {
    return;
  }

  try {
    console.log('Submitting vehicle request', { editing: !!editingRequest.value, data: form.data() });
    // clear any existing banner state (no UI banner for form submissions)
    if (editingRequest.value) {
      // resolve id from various possible shapes
      const resolvedId = editingRequest.value?.id ?? editingRequest.value?.vehicleRequest ?? editingRequest.value?.vehicle_request_id ?? editingRequest.value?.attributes?.id;
      if (!resolvedId) {
        console.error('No id found on editingRequest', editingRequest.value);
        alert('Cannot determine request id for update. See console.');
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
      // no banner shown for submit
      form.put(updateUrl, {
        onSuccess: () => {
          closeModal()
          Swal.fire({ icon: 'success', title: 'Request updated', timer: 1200, showConfirmButton: false }).then(() => { window.location.reload() })
        },
        onError: (errs) => {
          console.error('Vehicle request update errors', errs)
          const msg = Object.values(errs || {}).flat().join('\n') || 'Failed to update request'
          Swal.fire({ icon: 'error', title: 'Failed to update', text: msg })
        }
      })
      } else {
      const storeUrl = route('vehicle-requests.store');
      console.log('Store URL:', storeUrl);
      // no banner shown for submit
      form.post(storeUrl, {
        onSuccess: () => {
          closeModal()
          Swal.fire({ icon: 'success', title: 'Request created', timer: 1200, showConfirmButton: false }).then(() => { window.location.reload() })
        },
        onError: (errs) => {
          console.error('Vehicle request create errors', errs)
          const msg = Object.values(errs || {}).flat().join('\n') || 'Failed to create request'
          Swal.fire({ icon: 'error', title: 'Failed to create', text: msg })
        }
      })
    }
  } catch (e) {
    console.error('Submit failed', e);
    alert('Unexpected error. See console.');
  }
}

const destroy = (req) => {
  Swal.fire({
    title: 'Delete this vehicle request?',
    text: 'This action cannot be undone.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Yes, delete',
    cancelButtonText: 'Cancel'
  }).then((res) => {
    if (!res.isConfirmed) return
    import('@inertiajs/vue3').then(({ router }) => {
      let destroyUrl
      try { destroyUrl = route('vehicle-requests.destroy', req.id) } catch (e) { destroyUrl = `/vehicle-requests/${req.id}` }
      router.delete(destroyUrl, {
        onSuccess: () => { Swal.fire({ icon: 'success', title: 'Deleted', timer: 1000, showConfirmButton: false }).then(() => { window.location.reload() }) },
        onError: (e) => { Swal.fire({ icon: 'error', title: 'Failed to delete' }) }
      })
    })
  })
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
        <div class="flex items-center gap-2">
          <button
            v-if="page.props.auth?.user?.role?.name !== 'GSU Head'"
            @click.prevent="openModal()"
            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow"
          >
            + New Request
          </button>
          <button
            @click.prevent="openCalendar()"
            class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded-lg shadow"
            title="View calendar"
          >
            View Calendar
          </button>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow p-4">
        <!-- Search -->
        <div class="mb-4">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search vehicle requests..."
            class="w-1/3 rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
          />
        </div>
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
              <tr v-for="req in filteredRequests" :key="req.id">
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
              <tr v-if="filteredRequests.length === 0">
                <td colspan="10" class="px-4 py-6 text-center text-gray-500">No vehicle requests found.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="flex justify-center items-center gap-2 mt-4">
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

        <!-- Mobile cards -->
        <div class="sm:hidden space-y-3">
          <div v-for="req in filteredRequests" :key="req.id" class="border rounded-lg p-3 bg-white shadow-sm">
            <div class="flex items-start justify-between">
              <div>
                <div class="text-sm text-gray-500">Request #{{ req.id }}</div>
                <!-- Assign Driver Modal removed here; using global modal to avoid duplication -->

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

          <div v-if="filteredRequests.length === 0" class="text-center text-gray-500 py-6">No vehicle requests found.</div>
        </div>
      </div>
    </div>
    <!-- Calendar Modal -->
    <div v-if="showCalendar" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div class="bg-white w-full sm:rounded-xl sm:shadow-lg sm:max-w-4xl p-4 sm:p-6 relative overflow-auto max-h-[90vh]">
        <button class="absolute top-3 right-3 text-gray-500 hover:text-gray-800" @click.prevent="showCalendar = false">✕</button>
        <div class="flex items-center justify-between mb-4">
          <div class="flex items-center gap-2">
            <button @click.prevent="prevMonth" class="px-3 py-1 bg-gray-100 rounded">‹</button>
            <div class="font-semibold">{{ monthLabel }}</div>
            <button @click.prevent="nextMonth" class="px-3 py-1 bg-gray-100 rounded">›</button>
          </div>
          <div>
            <button @click.prevent="fetchBookings" class="px-3 py-1 bg-blue-600 text-white rounded">Refresh</button>
          </div>
        </div>

        <div class="grid grid-cols-7 gap-2 mt-2">
          <div class="text-center font-semibold">Sun</div>
          <div class="text-center font-semibold">Mon</div>
          <div class="text-center font-semibold">Tue</div>
          <div class="text-center font-semibold">Wed</div>
          <div class="text-center font-semibold">Thu</div>
          <div class="text-center font-semibold">Fri</div>
          <div class="text-center font-semibold">Sat</div>
        </div>

        <div class="grid grid-cols-7 gap-2 mt-2">
          <template v-for="(d, idx) in monthDays" :key="d ? d.toISOString() : 'blank-' + idx">
            <div class="border rounded p-2 min-h-[80px] bg-white">
              <div class="text-xs text-gray-600 mb-1">{{ d ? d.getDate() : '' }}</div>
              <div class="space-y-1 text-xs">
                <div v-if="d" v-for="b in bookingsForDate(d)" :key="b.id" class="bg-gray-50 p-1 rounded border">
                  <div class="font-medium">{{ b.vehicle_name }}{{ b.plate_no ? ' — ' + b.plate_no : '' }}</div>
                  <div class="text-gray-600">{{ b.start_time ?? '—' }} — {{ b.end_time ?? '—' }}</div>
                  <div class="text-gray-700 truncate">{{ b.purpose }}</div>
                </div>
                <div v-else class="h-4"></div>
                <div v-if="d && bookingsForDate(d).length === 0" class="text-gray-300 text-xs">-</div>
              </div>
            </div>
          </template>
        </div>
      </div>
    </div>
    <!-- Create Modal -->
    <!-- Assign Driver Modal (global) -->
    <div v-if="showAssignDriverModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div class="bg-white w-full sm:h-auto sm:rounded-xl sm:shadow-lg sm:max-w-md p-4 sm:p-6 relative overflow-auto">
        <button class="absolute top-3 right-3 text-gray-500 hover:text-gray-800" @click="closeAssignDriverModal">✕</button>
        <h2 class="text-xl font-semibold mb-4">Assign Driver</h2>
        <div class="space-y-4 max-h-[70vh] overflow-auto">
          <div v-if="assignLoading" class="py-8 text-center text-gray-600">Loading drivers...</div>
          <div v-else>
            <div>
              <label class="block text-sm font-medium text-gray-700">Vehicle (change if needed)</label>
              <select v-model="selectedVehicleId" class="mt-1 block w-full rounded border-gray-300">
                <option value="">Keep requested vehicle</option>
                <option v-for="v in props.vehicles" :key="v.id" :value="v.id">{{ v.name }}</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700">Driver</label>
              <select v-model="selectedDriverId" class="mt-1 block w-full rounded border-gray-300">
                <option value="">Select driver</option>
                <option v-for="d in drivers" :key="d.id" :value="d.id">{{ d.name }}{{ d.position ? ' — ' + d.position : '' }}</option>
              </select>
            </div>

            <div class="flex gap-2 mt-4">
              <button @click.prevent="assignDriver" :disabled="assignLoading || !selectedDriverId" class="bg-indigo-600 text-white px-4 py-2 rounded">Assign</button>
              <button @click.prevent="closeAssignDriverModal" class="px-4 py-2 rounded border">Cancel</button>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div class="bg-white w-full h-full sm:h-auto sm:rounded-xl sm:shadow-lg sm:max-w-md p-4 sm:p-6 relative overflow-auto">
        <button class="absolute top-3 right-3 text-gray-500 hover:text-gray-800" @click="closeModal">✕</button>
        <h2 class="text-xl font-semibold mb-4">New Vehicle Request</h2>
        <div class="space-y-4 max-h-[90vh] overflow-auto">
          <div>
            <label class="block text-sm font-medium text-gray-700">Purpose</label>
                    <input required v-model="form.purpose" @input="() => validateField('purpose')" type="text" :class="['mt-1 block w-full rounded', fieldErrors.purpose ? 'border-red-500 ring-1 ring-red-200' : 'border-gray-300']" />
                    <p v-if="fieldErrors.purpose" class="text-red-600 text-sm mt-1">{{ fieldErrors.purpose }}</p>
                    <p v-else-if="form.errors.purpose" class="text-red-600 text-sm mt-1">{{ form.errors.purpose }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700">Destination</label>
            <input required v-model="form.destination" @input="() => validateField('destination')" type="text" :class="['mt-1 block w-full rounded', fieldErrors.destination ? 'border-red-500 ring-1 ring-red-200' : 'border-gray-300']" />
            <p v-if="fieldErrors.destination" class="text-red-600 text-sm mt-1">{{ fieldErrors.destination }}</p>
            <p v-else-if="form.errors.destination" class="text-red-600 text-sm mt-1">{{ form.errors.destination }}</p>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700">Time of Departure</label>
              <input required v-model="form.time_of_departure" @input="() => validateField('time_of_departure')" type="time" :class="['mt-1 block w-full rounded', fieldErrors.time_of_departure ? 'border-red-500 ring-1 ring-red-200' : 'border-gray-300']" />
              <p v-if="fieldErrors.time_of_departure" class="text-red-600 text-sm mt-1">{{ fieldErrors.time_of_departure }}</p>
              <p v-else-if="form.errors.time_of_departure" class="text-red-600 text-sm mt-1">{{ form.errors.time_of_departure }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Estimated Time of Arrival</label>
              <input required v-model="form.eta" @input="() => validateField('eta')" type="time" :class="['mt-1 block w-full rounded', fieldErrors.eta ? 'border-red-500 ring-1 ring-red-200' : 'border-gray-300']" />
              <p v-if="fieldErrors.eta" class="text-red-600 text-sm mt-1">{{ fieldErrors.eta }}</p>
              <p v-else-if="form.errors.eta" class="text-red-600 text-sm mt-1">{{ form.errors.eta }}</p>
            </div>
          </div>

          <div>
              <label class="block text-sm font-medium text-gray-700">Date(s) Needed</label>
            <div class="mt-1 flex flex-col sm:flex-row sm:items-start gap-2">
              <input v-model="dateInput" type="date" class="block rounded border-gray-300" />
              <button @click.prevent="addDate" class="px-3 py-1 bg-blue-600 text-white rounded">Add</button>
            </div>
            <p v-if="fieldErrors.date_needed" class="text-red-600 text-sm mt-1">{{ fieldErrors.date_needed }}</p>
            <p v-else-if="form.errors['date_needed']" class="text-red-600 text-sm mt-1">{{ form.errors['date_needed'] }}</p>
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
            <select required v-model="form.vehicle_type" @change="() => validateField('vehicle_type')" :class="['mt-1 block w-full rounded', fieldErrors.vehicle_type ? 'border-red-500 ring-1 ring-red-200' : 'border-gray-300']">
              <option value="">Select vehicle</option>
              <option v-for="v in props.vehicles" :key="v.id" :value="v.name">{{ v.name }}</option>
            </select>
            <p v-if="fieldErrors.vehicle_type" class="text-red-600 text-sm mt-1">{{ fieldErrors.vehicle_type }}</p>
            <p v-else-if="form.errors.vehicle_type" class="text-red-600 text-sm mt-1">{{ form.errors.vehicle_type }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700">Division Chief (Approver)</label>
            <select required v-model="form.division_chief_id" @change="() => validateField('division_chief_id')" :class="['mt-1 block w-full rounded', fieldErrors.division_chief_id ? 'border-red-500 ring-1 ring-red-200' : 'border-gray-300']">
              <option value="">Select division chief</option>
              <option v-for="d in props.divisionChiefs" :key="d.id" :value="d.id">{{ d.name }}</option>
            </select>
            <p v-if="fieldErrors.division_chief_id" class="text-red-600 text-sm mt-1">{{ fieldErrors.division_chief_id }}</p>
            <p v-else-if="form.errors.division_chief_id" class="text-red-600 text-sm mt-1">{{ form.errors.division_chief_id }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700">Passengers</label>
            <input required v-model.number="form.passengers" @input="() => validateField('passengers')" type="number" min="1" :class="['mt-1 block w-24 rounded', fieldErrors.passengers ? 'border-red-500 ring-1 ring-red-200' : 'border-gray-300']" />
            <p v-if="fieldErrors.passengers" class="text-red-600 text-sm mt-1">{{ fieldErrors.passengers }}</p>
            <p v-else-if="form.errors.passengers" class="text-red-600 text-sm mt-1">{{ form.errors.passengers }}</p>
          </div>

          <div class="flex justify-end gap-2">
            <button @click.prevent="submit" :disabled="form.processing" class="bg-blue-600 text-white px-4 py-2 rounded sm:w-auto disabled:opacity-60 inline-flex items-center justify-center">
              <span v-if="form.processing" class="inline-flex items-center">
                <svg class="animate-spin mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                </svg>
                Submitting...
              </span>
              <span v-else>Submit</span>
            </button>
            <button @click.prevent="closeModal" class="px-4 py-2 rounded border">Cancel</button>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
