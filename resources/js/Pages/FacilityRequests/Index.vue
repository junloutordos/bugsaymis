<script setup>
import { Head, usePage, useForm } from "@inertiajs/vue3";
import { ref, reactive, computed, watch } from "vue";
import { PencilSquareIcon, TrashIcon, PrinterIcon, CheckIcon, XMarkIcon } from "@heroicons/vue/24/outline";
import Swal from 'sweetalert2'
import AdminLayout from "@/Layouts/AdminLayout.vue";

const props = defineProps({ requests: Array, facilities: Array, misUsers: Array });
const page = usePage();

const usersList = ref(props.misUsers || [])

// client-side search + pagination
const requestsList = ref(props.requests || [])
const searchQuery = ref('')
const currentPage = ref(1)
const perPage = 10

const filteredRequestsAll = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  return (requestsList.value || []).filter(req => {
    const venue = Array.isArray(req.venue)
      ? req.venue.map(v => facilityMap[v] ?? v).join(' ')
      : (facilityMap[req.venue] ?? req.venue ?? '')
    return (
      (req.activity || '').toString().toLowerCase().includes(q) ||
      ((req.requester?.name ?? req.requestor) || '').toString().toLowerCase().includes(q) ||
      (req.unit || req.requester?.division?.division_name || '').toString().toLowerCase().includes(q) ||
      (req.status || '').toString().toLowerCase().includes(q) ||
      (req.purpose || '').toString().toLowerCase().includes(q) ||
      (req.nature || '').toString().toLowerCase().includes(q) ||
      (req.participants || '').toString().toLowerCase().includes(q) ||
      venue.toLowerCase().includes(q) ||
      (req.date_start || '').toString().includes(q) ||
      (req.id || '').toString().includes(q)
    )
  })
})

const filteredRequests = computed(() => {
  const start = (currentPage.value - 1) * perPage
  return filteredRequestsAll.value.slice(start, start + perPage)
})

const totalPages = computed(() => Math.max(1, Math.ceil(filteredRequestsAll.value.length / perPage)))

watch(searchQuery, () => { currentPage.value = 1 })

const showModal = ref(false);
const editingRequest = ref(null);
const form = useForm({
  unit: '',
  activity: '',
  purpose: '',
  requires_it_assistance: false,
  assigned_mis_user: null,
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
  editingRequest.value = req ?? null;
  if (req) {
    form.reset();
    form.requestor = req.requester?.name ?? req.requestor;
    form.unit = req.requester?.division?.division_name ?? req.unit;
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
    form.assigned_mis_user = req.assigned_mis_user ?? null;
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

const closeModal = () => { showModal.value = false; editingRequest.value = null; form.reset(); };

const submit = () => {
  if (!validateAll()) { Swal.fire({ icon: 'error', title: 'Validation failed', text: 'Please fix the highlighted errors before submitting.' }); return }
  const onError = (errors) => {
    const venueErr = errors?.venue ?? page.props.errors?.venue;
    const text = venueErr ? (Array.isArray(venueErr) ? venueErr.join(', ') : venueErr) : (Object.values(errors || {}).flat().join(', ') || 'Failed to submit');
    Swal.fire({ icon: 'error', title: editingRequest.value ? 'Failed to update' : 'Failed to submit', text })
  }
  if (editingRequest.value) {
    form.put(route('facility-requests.update', editingRequest.value.id), {
      onSuccess: () => { closeModal(); Swal.fire({ icon: 'success', title: 'Request updated', timer: 1200, showConfirmButton: false }).then(() => { window.location.reload() }) },
      onError,
    })
  } else {
    form.post(route('facility-requests.store'), {
      onSuccess: () => { closeModal(); Swal.fire({ icon: 'success', title: 'Request submitted', timer: 1200, showConfirmButton: false }).then(() => { window.location.reload() }) },
      onError,
    })
  }
}

const destroy = (req) => {
  Swal.fire({ title: 'Delete this facility request?', text: 'This action cannot be undone.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Yes, delete', cancelButtonText: 'Cancel' }).then((res) => {
    if (!res.isConfirmed) return
    import('@inertiajs/vue3').then(({ router }) => {
      router.delete(route('facility-requests.destroy', req.id), {
        onSuccess: () => { Swal.fire({ icon: 'success', title: 'Deleted', timer: 1000, showConfirmButton: false }).then(() => { window.location.reload() }) },
        onError: () => { Swal.fire({ icon: 'error', title: 'Failed to delete' }) }
      })
    })
  })
}

const approveRequest = (req) => {
  Swal.fire({ title: 'Approve this facility request?', icon: 'question', showCancelButton: true, confirmButtonText: 'Approve' }).then(res => {
    if (!res.isConfirmed) return;
    import('@inertiajs/vue3').then(({ router }) => {
      router.post(route('facility-requests.approve.inapp', req.id), {}, {
        onSuccess: () => { Swal.fire({ icon: 'success', title: 'Approved', timer: 1000, showConfirmButton: false }).then(() => window.location.reload()) },
        onError: () => { Swal.fire({ icon: 'error', title: 'Failed to approve' }) }
      })
    })
  })
}

const declineRequest = (req) => {
  Swal.fire({ title: 'Reason for declining', input: 'text', inputPlaceholder: 'Enter reason', showCancelButton: true }).then(res => {
    if (!res.isConfirmed || !res.value) return;
    import('@inertiajs/vue3').then(({ router }) => {
      router.post(route('facility-requests.decline.inapp', req.id), { reason: res.value }, {
        onSuccess: () => { Swal.fire({ icon: 'success', title: 'Declined', timer: 1000, showConfirmButton: false }).then(() => window.location.reload()) },
        onError: () => { Swal.fire({ icon: 'error', title: 'Failed to decline' }) }
      })
    })
  })
}

const openPrint = (req) => {
  let url;
  try {
    url = route('facility-requests.print', req.id);
  } catch (e) {
    url = `/facility-requests/${req.id}/print`;
  }
  window.open(url, '_blank');
};

// Calendar modal state (facility bookings)
const showCalendar = ref(false);
const calendarMonth = ref(new Date());
const bookings = ref([]);

const monthLabel = computed(() => calendarMonth.value.toLocaleString(undefined, { month: 'long', year: 'numeric' }));

const fetchBookings = async () => {
  try {
    const res = await fetch('/facility-bookings');
    const data = await res.json();
    bookings.value = Array.isArray(data) ? data : [];
  } catch (e) {
    console.error('Failed to load facility bookings', e);
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
  const firstWeekday = new Date(year, month, 1).getDay();
  for (let i = 0; i < firstWeekday; i++) days.push(null);
  const last = new Date(year, month + 1, 0).getDate();
  for (let i = 1; i <= last; i++) {
    days.push(new Date(year, month, i));
  }
  while (days.length % 7 !== 0) days.push(null);
  return days;
});

const pad2 = (n) => (n < 10 ? '0' + n : String(n));
const formatYMD = (d) => `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())}`;
const bookingsForDate = (dt) => {
  if (!dt) return [];
  const key = formatYMD(dt);
  return bookings.value.filter(b => (b.date || '').toString().slice(0,10) === key);
};
</script>

<template>
  <Head title="Facility Requests" />
  <AdminLayout title="Facility Requests">
    <div>
      <div class="flex items-center justify-between mb-4 gap-2">
        <h1 class="text-xl md:text-2xl font-bold text-gray-800 truncate">Facility Requests</h1>
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
            placeholder="Search facility requests..."
            class="w-full sm:w-1/2 md:w-1/3 rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
          />
        </div>
        <!-- Desktop table: allow horizontal scroll and fixed layout to avoid overlap -->
        <div class="hidden sm:block overflow-x-auto">
          <table class="table-fixed w-full border border-gray-200">
            <thead class="bg-gray-100 text-gray-700 uppercase text-sm">
              <tr>
                <th class="px-4 py-3 text-left whitespace-normal break-words">#</th>
                <th v-if="!['Staff','Faculty'].includes(page.props.auth?.user?.role?.name)" class="px-4 py-3 text-left whitespace-normal break-words">Requestor</th>
                <th v-if="!['Staff','Faculty','GSU Head','Administrator','DivisionChief'].includes(page.props.auth?.user?.role?.name)" class="px-4 py-3 text-left whitespace-normal break-words">Unit</th>
                <th class="px-4 py-3 text-left whitespace-normal break-words">Activity</th>
                <th class="px-4 py-3 text-left whitespace-normal break-words">Date(s)</th>
                <th class="px-4 py-3 text-left whitespace-normal break-words">Time(s)</th>
                <th class="px-4 py-3 text-left whitespace-normal break-words">Venue</th>
                <th class="px-4 py-3 text-left whitespace-normal break-words">Status</th>
                <th class="px-4 py-3 text-center whitespace-normal break-words">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 text-sm">
              <tr v-for="req in filteredRequests" :key="req.id">
                <td class="px-4 py-3 whitespace-normal break-words">{{ req.id }}</td>
                <td v-if="!['Staff','Faculty'].includes(page.props.auth?.user?.role?.name)" class="px-4 py-3 whitespace-normal break-words">{{ req.requester?.name ?? req.requestor ?? '—' }}</td>
                <td v-if="!['Staff','Faculty','GSU Head','Administrator','DivisionChief'].includes(page.props.auth?.user?.role?.name)" class="px-4 py-3 whitespace-normal break-words">{{ req.requester?.division?.division_name ?? req.unit ?? '—' }}</td>
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
                      v-if="(page.props.auth?.user?.role?.name === 'Administrator' && (req.status === 'OCD Approved' || req.status === 'FAD Approved')) || (page.props.auth?.user?.role?.name === 'GSU Head' && (req.status === 'OCD Approved' || req.status === 'FAD Approved'))"
                      @click.prevent="openPrint(req)"
                      class="p-2 rounded-full bg-gray-100 hover:bg-gray-200 text-gray-700"
                      title="Print"
                    >
                      <PrinterIcon class="w-5 h-5" />
                    </button>
                    <button v-if="page.props.auth?.user?.role?.name === 'DivisionChief' && req.status === 'Pending'" @click.prevent="approveRequest(req)" class="p-2 rounded-full bg-green-100 hover:bg-green-200 text-green-700" title="Approve"><CheckIcon class="w-5 h-5"/></button>
                    <button v-if="page.props.auth?.user?.role?.name === 'DivisionChief' && req.status === 'Pending'" @click.prevent="declineRequest(req)" class="p-2 rounded-full bg-red-100 hover:bg-red-200 text-red-700" title="Decline"><XMarkIcon class="w-5 h-5"/></button>
                  </div>
                </td>
              </tr>
                <tr v-if="filteredRequests.length === 0">
                <td :colspan="(['Staff','Faculty','GSU Head','Administrator','DivisionChief'].includes(page.props.auth?.user?.role?.name) ? 8 : 9)" class="px-4 py-6 text-center text-gray-500">No facility requests found.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Mobile cards -->
        <div class="sm:hidden space-y-3">
          <div v-for="req in filteredRequests" :key="req.id" class="border rounded-lg p-3 bg-white shadow-sm">
            <div class="flex items-start justify-between">
              <div>
                <div class="text-sm text-gray-500">Request #{{ req.id }}</div>
                <div class="font-semibold text-gray-800">{{ req.activity ?? '—' }}</div>
                <div class="text-sm text-gray-600"><span v-if="!['Staff','Faculty'].includes(page.props.auth?.user?.role?.name)">{{ req.requester?.name ?? req.requestor ?? '—' }} — </span><span v-if="!['Staff','Faculty','GSU Head','Administrator','DivisionChief'].includes(page.props.auth?.user?.role?.name)">{{ req.requester?.division?.division_name ?? req.unit ?? '—' }}</span></div>
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
                v-if="(page.props.auth?.user?.role?.name === 'Administrator' && (req.status === 'OCD Approved' || req.status === 'FAD Approved')) || (page.props.auth?.user?.role?.name === 'GSU Head' && (req.status === 'OCD Approved' || req.status === 'FAD Approved'))"
                @click.prevent="openPrint(req)"
                class="inline-flex items-center gap-2 px-3 py-2 bg-gray-100 text-gray-700 rounded-md"
              >
                <PrinterIcon class="w-4 h-4" /> Print
              </button>
              <button v-if="page.props.auth?.user?.role?.name === 'DivisionChief' && req.status === 'Pending'" @click.prevent="approveRequest(req)" class="inline-flex items-center gap-2 px-3 py-2 bg-green-100 text-green-700 rounded-md"><CheckIcon class="w-4 h-4"/> Approve</button>
              <button v-if="page.props.auth?.user?.role?.name === 'DivisionChief' && req.status === 'Pending'" @click.prevent="declineRequest(req)" class="inline-flex items-center gap-2 px-3 py-2 bg-red-100 text-red-700 rounded-md"><XMarkIcon class="w-4 h-4"/> Decline</button>
            </div>
          </div>

          <div v-if="filteredRequests.length === 0" class="text-center text-gray-500 py-6">No facility requests found.</div>
        </div>
        <!-- Pagination -->
        <div class="flex justify-center items-center gap-2 mt-4">
          <button
            @click="currentPage--"
            :disabled="currentPage === 1"
            class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50"
          >
            Prev
          </button>
          <span>Page {{ currentPage }} of {{ totalPages }}</span>
          <button
            @click="currentPage++"
            :disabled="currentPage === totalPages"
            class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50"
          >
            Next
          </button>
        </div>
      </div>

      <!-- Create Modal -->
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
                    <div class="font-medium">{{ b.facility_name ?? '—' }}</div>
                    <div class="text-gray-600">{{ b.start_time ?? '—' }} — {{ b.end_time ?? '—' }}</div>
                    <div class="text-gray-700 truncate">{{ b.activity }}</div>
                  </div>
                  <div v-else class="h-4"></div>
                  <div v-if="d && bookingsForDate(d).length === 0" class="text-gray-300 text-xs"></div>
                </div>
              </div>
            </template>
          </div>
        </div>
      </div>
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
        <!-- On small screens use full-screen panel; on larger screens use centered modal -->
        <div class="bg-white w-full h-full sm:h-auto sm:rounded-xl sm:shadow-lg sm:max-w-lg p-4 sm:p-6 relative overflow-auto">
          <button class="absolute top-3 right-3 text-gray-500 hover:text-gray-800" @click="closeModal">✕</button>
          <h2 class="text-xl font-semibold mb-4">{{ editingRequest ? 'Edit Facility Request' : 'New Facility Request' }}</h2>
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

            <div class="mt-2">
              <label class="inline-flex items-center gap-3">
                <input type="checkbox" v-model="form.requires_it_assistance" class="h-4 w-4" />
                <span class="text-sm text-gray-700">Requires IT Technical Assistance</span>
              </label>
              <p class="text-xs text-gray-500 mt-1">If enabled, an IT Job Request will be automatically created for this event.</p>
            </div>

            <div v-if="form.requires_it_assistance" class="mt-3">
              <label class="block text-sm font-medium text-gray-700">Assign IT Personnel</label>
              <select v-model="form.assigned_mis_user" class="mt-1 block w-full rounded border-gray-300">
                <option :value="null">-- Assign Personnel--</option>
                <option v-for="u in usersList" :key="u.id" :value="u.id">{{ u.name }} - {{ u.position ?? '' }}</option>
              </select>
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

    </div>
  </AdminLayout>
</template>
