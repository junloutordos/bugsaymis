<script setup>
import { Head, usePage, useForm } from "@inertiajs/vue3";
import DigitalSignaturePin from '@/Components/DigitalSignaturePin.vue'
import { ref, reactive, computed, watch } from "vue";
import axios from "axios";
import { PencilSquareIcon, TrashIcon, PrinterIcon, XMarkIcon } from "@heroicons/vue/24/outline";
import CsmForm from '@/Components/CsmForm.vue'
import Swal from 'sweetalert2'
import AdminLayout from "@/Layouts/AdminLayout.vue";
import { statusBadgeClass, badgeBase } from '@/Composables/useStatusBadge.js'

const props = defineProps({
  requests:     Array,
  facilities:   Array,
  misUsers:     Array,
  hasPin:       { type: Boolean, default: false },
  signatureUri: { type: String,  default: null },
});
const page = usePage();
const roleName = computed(() => page.props.auth?.user?.role?.name ?? '');
const roleNames = computed(() => page.props.auth?.user?.roleNames ?? (roleName.value ? [roleName.value] : []));
const hasRole = (role) => roleNames.value.includes(role);
const hasAnyRole = (...roles) => roles.some(r => roleNames.value.includes(r));

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
      if (form.date_start) {
        const [y, m, d] = form.date_start.split('-').map(Number)
        const picked = new Date(y, m - 1, d)
        const minD   = new Date(minEventDate.value)
        if (picked < minD) fieldErrors.date_start = 'Event date must be at least 3 days from today.'
      }
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

// Layer 1: min date for event date pickers — today + 3 days
const minEventDate = computed(() => {
  const d = new Date()
  d.setDate(d.getDate() + 3)
  return d.toISOString().slice(0, 10)
})

// ── CSM ───────────────────────────────────────────────────────────────────────
const showCsmModal   = ref(false)
const requestToCsm   = ref(null)
const showCsmPin     = ref(false)
const csmPinLoading  = ref(false)

function openCsmModal(req) {
  requestToCsm.value = req
  if (props.hasPin) {
    showCsmPin.value = true
  } else {
    handleCsmPinConfirm(null)
  }
}

async function handleCsmPinConfirm(pin) {
  showCsmPin.value = false
  if (!requestToCsm.value) return
  csmPinLoading.value = true
  try {
    await axios.post(route('facility-requests.sign-completion', requestToCsm.value.id), { pin })
  } catch {
    // non-blocking — signature failure doesn't block CSM survey
  } finally {
    csmPinLoading.value = false
  }
  showCsmModal.value = true
}

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
      text: 'You have a Facility Request that has been approved and is pending your confirmation. Please rate the service first before submitting a new request.',
      confirmButtonText: 'OK',
    })
    return
  }
  openModal()
}

const onItAssistanceChange = async () => {
  if (!form.requires_it_assistance) return  // unchecking — nothing to check

  try {
    const { data } = await axios.get(route('jobrequests.check-pending'))
    if (data.has_pending) {
      form.requires_it_assistance = false
      form.assigned_mis_user = null
      await Swal.fire({
        icon: 'warning',
        title: 'Cannot Require Technical Assistance',
        html: `You have <strong>${data.count}</strong> unrated IT Job Request${data.count > 1 ? 's' : ''} with status <em>Acted by MIS</em>.<br><br>
               Please go to <strong>IT Job Requests</strong>, confirm completion, and rate the service first before requesting technical assistance.`,
        confirmButtonText: 'Understood',
      })
    }
  } catch {
    // silently ignore network errors
  }
}

// ── PIN modal for submission ──────────────────────────────────────────────────
const showSubmitPin = ref(false)
const pinLoading    = ref(false)
function handlePinCancel() { showSubmitPin.value = false }
function handlePinConfirm(pin) {
  pinLoading.value = true
  if (pin) form.pin = pin
  doPostStore()
}

function openPinModal() {
  if (!validateAll()) { Swal.fire({ icon: 'error', title: 'Validation failed', text: 'Please fix the highlighted errors before submitting.' }); return }
  if (form.date_start) {
    const [y, m, d] = form.date_start.split('-').map(Number)
    const picked = new Date(y, m - 1, d)
    const minD   = new Date(); minD.setDate(minD.getDate() + 3); minD.setHours(0, 0, 0, 0)
    if (picked < minD) { Swal.fire('Filing Not Allowed', 'Facility requests must be filed at least 3 days before the event date.', 'error'); return }
  }
  showSubmitPin.value = true
}

function doPostStore() {
  const onError = (errors) => {
    const venueErr = errors?.venue ?? page.props.errors?.venue
    const text = venueErr ? (Array.isArray(venueErr) ? venueErr.join(', ') : venueErr) : (Object.values(errors || {}).flat().join(', ') || 'Failed to submit')
    Swal.fire({ icon: 'error', title: 'Failed to submit', text })
    pinLoading.value = false; showSubmitPin.value = false
  }
  form.post(route('facility-requests.store'), {
    onSuccess: () => { closeModal(); showSubmitPin.value = false; Swal.fire({ icon: 'success', title: 'Request submitted', timer: 1200, showConfirmButton: false }).then(() => { window.location.reload() }) },
    onError,
    onFinish: () => { pinLoading.value = false },
  })
}

const submit = () => {
  if (!validateAll()) { Swal.fire({ icon: 'error', title: 'Validation failed', text: 'Please fix the highlighted errors before submitting.' }); return }

  // Layer 2: pre-submit guard — catches manually typed dates that bypass the min attribute
  if (form.date_start) {
    const [y, m, d] = form.date_start.split('-').map(Number)
    const picked = new Date(y, m - 1, d)
    const minD   = new Date(); minD.setDate(minD.getDate() + 3); minD.setHours(0, 0, 0, 0)
    if (picked < minD) {
      Swal.fire('Filing Not Allowed', 'Facility requests must be filed at least 3 days before the event date.', 'error')
      return
    }
  }
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
      <!-- Page header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Facility Requests</h1>
          <p class="text-sm text-slate-500 mt-0.5">Manage facility and venue booking requests</p>
        </div>
        <div class="flex items-center gap-2">
          <button
            v-if="!hasRole('GSU Head')"
            @click.prevent="handleNewRequest()"
            class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm"
          >
            + New Request
          </button>
          <button
            @click.prevent="openCalendar()"
            class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm"
            title="View calendar"
          >
            View Calendar
          </button>
        </div>
      </div>

      <!-- Table card -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <!-- Search -->
        <div class="px-5 py-4 border-b border-slate-100">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search facility requests…"
            class="w-full sm:w-72 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"
          />
        </div>

        <!-- Desktop table -->
        <div class="hidden sm:block overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">#</th>
                <th v-if="!hasAnyRole('Staff','Faculty')" class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Requestor</th>
                <th v-if="!hasAnyRole('Staff','Faculty','GSU Head','Administrator','DivisionChief')" class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Unit</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Activity</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Date(s)</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Time(s)</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Venue</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Status</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="req in filteredRequests" :key="req.id" class="hover:bg-slate-50/60">
                <td class="px-4 py-3 text-sm text-slate-700">{{ req.id }}</td>
                <td v-if="!hasAnyRole('Staff','Faculty')" class="px-4 py-3 text-sm text-slate-700">{{ req.requester?.name ?? req.requestor ?? '—' }}</td>
                <td v-if="!hasAnyRole('Staff','Faculty','GSU Head','Administrator','DivisionChief')" class="px-4 py-3 text-sm text-slate-700">{{ req.requester?.division?.division_name ?? req.unit ?? '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ req.activity ?? '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700 whitespace-nowrap">
                  {{ req.date_start ? new Date(req.date_start).toLocaleDateString() : '—' }}
                  <span v-if="req.date_end"> — {{ new Date(req.date_end).toLocaleDateString() }}</span>
                </td>
                <td class="px-4 py-3 text-sm text-slate-700 whitespace-nowrap">
                  <span v-if="req.time_start">{{ (req.time_start || '').slice(0,5) }}</span>
                  <span v-if="req.time_end"> <span v-if="req.time_start">—</span> {{ (req.time_end || '').slice(0,5) }}</span>
                  <span v-if="!req.time_start && !req.time_end">—</span>
                </td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ venueDisplay(req.venue) }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">
                  <span :class="[badgeBase, statusBadgeClass(req.status)]">{{ req.status }}</span>
                </td>
                <td class="px-4 py-3 text-center">
                  <div class="flex items-center gap-1.5 justify-center">
                    <button v-if="hasRole('Administrator')" @click.prevent="openModal(req)"
                            class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" title="Edit">
                      <PencilSquareIcon class="w-4 h-4" />
                    </button>
                    <button v-if="hasRole('Administrator')" @click.prevent="destroy(req)"
                            class="p-1.5 rounded-lg hover:bg-red-50 text-slate-500 hover:text-red-600 transition-colors" title="Delete">
                      <TrashIcon class="w-4 h-4" />
                    </button>
                    <button
                      v-if="(hasRole('Administrator') && (req.status === 'OCD Approved' || req.status === 'FAD Approved')) || (hasRole('GSU Head') && (req.status === 'OCD Approved' || req.status === 'FAD Approved'))"
                      @click.prevent="openPrint(req)"
                      class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" title="Print">
                      <PrinterIcon class="w-4 h-4" />
                    </button>
                    <!-- CSM Survey button — shown to requestor when FAD approves -->
                    <button
                      v-if="req.status === 'Approved' && req.requestor_id === page.props.auth.user.id"
                      @click.prevent="openCsmModal(req)"
                      class="inline-flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1 rounded-lg text-xs font-medium transition-colors shadow-sm">
                      Confirm &amp; Rate
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="filteredRequests.length === 0">
                <td :colspan="(hasAnyRole('Staff','Faculty','GSU Head','Administrator','DivisionChief') ? 8 : 9)" class="py-16 text-center text-slate-400 text-sm">No facility requests found.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Mobile cards -->
        <div class="sm:hidden p-4 space-y-3">
          <div v-for="req in filteredRequests" :key="req.id" class="bg-white border border-slate-100 rounded-xl p-4 shadow-sm">
            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0">
                <p class="text-xs text-slate-500">Request #{{ req.id }}</p>
                <p class="text-sm font-semibold text-slate-800 mt-0.5 truncate">{{ req.activity ?? '—' }}</p>
                <p class="text-xs text-slate-600 mt-1">
                  <span v-if="!hasAnyRole('Staff','Faculty')">{{ req.requester?.name ?? req.requestor ?? '—' }}</span>
                  <span v-if="!hasAnyRole('Staff','Faculty','GSU Head','Administrator','DivisionChief')"> — {{ req.requester?.division?.division_name ?? req.unit ?? '—' }}</span>
                </p>
              </div>
              <div class="shrink-0 text-right text-xs text-slate-600">
                {{ req.date_start ? new Date(req.date_start).toLocaleDateString() : '—' }}
                <div v-if="req.date_end">— {{ new Date(req.date_end).toLocaleDateString() }}</div>
              </div>
            </div>
            <div class="mt-2 space-y-1 text-xs text-slate-700">
              <div><span class="font-medium text-slate-500">Time:</span> {{ req.time_start ? (req.time_start || '').slice(0,5) : '—' }}<span v-if="req.time_end"> — {{ (req.time_end || '').slice(0,5) }}</span></div>
              <div><span class="font-medium text-slate-500">Venue:</span> {{ venueDisplay(req.venue) }}</div>
              <div class="flex items-center gap-2"><span class="font-medium text-slate-500">Status:</span>
                <span :class="[badgeBase, statusBadgeClass(req.status)]">{{ req.status }}</span>
              </div>
            </div>
            <div class="mt-3 flex flex-wrap items-center gap-2">
              <button v-if="hasRole('Administrator')" @click.prevent="openModal(req)"
                      class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium transition-colors">
                <PencilSquareIcon class="w-3.5 h-3.5" /> Edit
              </button>
              <button v-if="hasRole('Administrator')" @click.prevent="destroy(req)"
                      class="inline-flex items-center gap-1.5 bg-red-600 hover:bg-red-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium transition-colors">
                <TrashIcon class="w-3.5 h-3.5" />
              </button>
              <button
                v-if="(hasRole('Administrator') && (req.status === 'OCD Approved' || req.status === 'FAD Approved')) || (hasRole('GSU Head') && (req.status === 'OCD Approved' || req.status === 'FAD Approved'))"
                @click.prevent="openPrint(req)"
                class="inline-flex items-center gap-1.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-3 py-1.5 rounded-lg text-xs font-medium transition-colors">
                <PrinterIcon class="w-3.5 h-3.5" /> Print
              </button>
            </div>
          </div>
          <div v-if="filteredRequests.length === 0" class="py-16 text-center text-slate-400 text-sm">No facility requests found.</div>
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

      <!-- Calendar Modal -->
      <div v-if="showCalendar" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50">
        <div class="bg-white w-full sm:rounded-2xl sm:shadow-xl sm:max-w-4xl relative overflow-auto max-h-[90vh]">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <div class="flex items-center gap-2">
              <button @click.prevent="prevMonth" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 transition-colors">‹</button>
              <span class="text-sm font-semibold text-slate-800">{{ monthLabel }}</span>
              <button @click.prevent="nextMonth" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 transition-colors">›</button>
            </div>
            <div class="flex items-center gap-2">
              <button @click.prevent="fetchBookings" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium transition-colors">Refresh</button>
              <button @click.prevent="showCalendar = false" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 transition-colors">
                <XMarkIcon class="w-4 h-4" />
              </button>
            </div>
          </div>
          <div class="p-4">
            <div class="grid grid-cols-7 gap-1 mb-1">
              <div v-for="d in ['Sun','Mon','Tue','Wed','Thu','Fri','Sat']" :key="d" class="text-center text-xs font-semibold text-slate-500 py-1">{{ d }}</div>
            </div>
            <div class="grid grid-cols-7 gap-1">
              <template v-for="(d, idx) in monthDays" :key="d ? d.toISOString() : 'blank-' + idx">
                <div class="border border-slate-100 rounded-lg p-1.5 min-h-[72px] bg-white">
                  <div class="text-xs text-slate-500 mb-1">{{ d ? d.getDate() : '' }}</div>
                  <div class="space-y-0.5 text-xs">
                    <div v-if="d" v-for="b in bookingsForDate(d)" :key="b.id" class="bg-indigo-50 text-indigo-700 p-1 rounded text-[10px]">
                      <div class="font-medium truncate">{{ b.facility_name ?? '—' }}</div>
                      <div class="text-indigo-500">{{ b.start_time ?? '—' }} — {{ b.end_time ?? '—' }}</div>
                    </div>
                  </div>
                </div>
              </template>
            </div>
          </div>
        </div>
      </div>

      <!-- Create / Edit Modal -->
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50">
        <div class="bg-white w-full h-full sm:h-auto sm:rounded-2xl sm:shadow-xl sm:max-w-lg relative overflow-auto">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-800">{{ editingRequest ? 'Edit Facility Request' : 'New Facility Request' }}</h2>
            <button class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" @click="closeModal">
              <XMarkIcon class="w-4 h-4" />
            </button>
          </div>
          <div class="px-6 py-5 space-y-4 max-h-[80vh] overflow-auto">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Activity</label>
                <input v-model="form.activity" @input="validateField('activity')" type="text" :class="['w-full rounded-lg border bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500', fieldErrors.activity ? 'border-red-400' : 'border-slate-200 focus:border-indigo-400']" />
                <p v-if="fieldErrors.activity" class="mt-1 text-xs text-red-600">{{ fieldErrors.activity }}</p>
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Purpose</label>
                <input v-model="form.purpose" @input="validateField('purpose')" type="text" :class="['w-full rounded-lg border bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500', fieldErrors.purpose ? 'border-red-400' : 'border-slate-200 focus:border-indigo-400']" />
                <p v-if="fieldErrors.purpose" class="mt-1 text-xs text-red-600">{{ fieldErrors.purpose }}</p>
              </div>
            </div>

            <div class="mt-2">
              <label class="inline-flex items-center gap-3">
                <input type="checkbox" v-model="form.requires_it_assistance" class="h-4 w-4"
                       @change="onItAssistanceChange" />
                <span class="text-sm text-slate-700">Requires IT Technical Assistance</span>
              </label>
              <p class="text-xs text-slate-500 mt-1">If enabled, an IT Job Request will be automatically created for this event.</p>
            </div>

            <div v-if="form.requires_it_assistance" class="mt-3">
              <label class="block text-xs font-medium text-slate-600 mb-1">Assign IT Personnel</label>
              <select v-model="form.assigned_mis_user" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
                <option :value="null">-- Assign Personnel--</option>
                <option v-for="u in usersList" :key="u.id" :value="u.id">{{ u.name }} - {{ u.position ?? '' }}</option>
              </select>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Nature of Activity</label>
                <select v-model="form.nature" @change="validateField('nature')" :class="['w-full rounded-lg border bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500', fieldErrors.nature ? 'border-red-400' : 'border-slate-200 focus:border-indigo-400']">
                  <option value="">-- Select Nature --</option>
                  <option value="Curricular">Curricular</option>
                  <option value="Co-Curricular">Co-Curricular</option>
                  <option value="Others">Others (please specify)</option>
                </select>
                <p v-if="fieldErrors.nature" class="mt-1 text-xs text-red-600">{{ fieldErrors.nature }}</p>
              </div>

              <div v-if="form.nature === 'Others'">
                <label class="block text-xs font-medium text-slate-600 mb-1">Please specify</label>
                <input v-model="form.nature_other" @input="validateField('nature_other')" type="text" :class="['w-full rounded-lg border bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500', fieldErrors.nature_other ? 'border-red-400' : 'border-slate-200 focus:border-indigo-400']" />
                <p v-if="fieldErrors.nature_other" class="mt-1 text-xs text-red-600">{{ fieldErrors.nature_other }}</p>
              </div>

              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Participants (description)</label>
                <input v-model="form.participants" @input="validateField('participants')" type="text" :class="['w-full rounded-lg border bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500', fieldErrors.participants ? 'border-red-400' : 'border-slate-200 focus:border-indigo-400']" placeholder="e.g. Students, Faculty" />
                <p v-if="fieldErrors.participants" class="mt-1 text-xs text-red-600">{{ fieldErrors.participants }}</p>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Number of Male Participants</label>
                <input v-model.number="form.male" type="number" min="0" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Number of Female Participants</label>
                <input v-model.number="form.female" type="number" min="0" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Start Date</label>
                <input v-model="form.date_start" @change="validateField('date_start')" type="date" :min="minEventDate" :class="['w-full rounded-lg border bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500', fieldErrors.date_start ? 'border-red-400' : 'border-slate-200 focus:border-indigo-400']" />
                <p v-if="fieldErrors.date_start" class="mt-1 text-xs text-red-600">{{ fieldErrors.date_start }}</p>
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">End Date</label>
                <input v-model="form.date_end" @change="validateField('date_end')" type="date" :min="form.date_start || minEventDate" :class="['w-full rounded-lg border bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500', fieldErrors.date_end ? 'border-red-400' : 'border-slate-200 focus:border-indigo-400']" />
                <p v-if="fieldErrors.date_end" class="mt-1 text-xs text-red-600">{{ fieldErrors.date_end }}</p>
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Start Time</label>
                <input v-model="form.time_start" @change="validateField('time_start')" type="time" :class="['w-full rounded-lg border bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500', fieldErrors.time_start ? 'border-red-400' : 'border-slate-200 focus:border-indigo-400']" />
                <p v-if="fieldErrors.time_start" class="mt-1 text-xs text-red-600">{{ fieldErrors.time_start }}</p>
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">End Time</label>
                <input v-model="form.time_end" @change="validateField('time_end')" type="time" :class="['w-full rounded-lg border bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500', fieldErrors.time_end ? 'border-red-400' : 'border-slate-200 focus:border-indigo-400']" />
                <p v-if="fieldErrors.time_end" class="mt-1 text-xs text-red-600">{{ fieldErrors.time_end }}</p>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Venue</label>
                <select v-model="form.venue" multiple @change="validateField('venue')" :class="['w-full rounded-lg border bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500', fieldErrors.venue ? 'border-red-400' : 'border-slate-200 focus:border-indigo-400']">
                  <option v-for="f in props.facilities" :key="f.id" :value="f.id">{{ f.name }}</option>
                </select>
                <p v-if="fieldErrors.venue" class="mt-1 text-xs text-red-600">{{ fieldErrors.venue }}</p>
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Equipment Needed</label>
                <select v-model="form.equipment" multiple @change="validateField('equipment_quantities')" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
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
              <label class="block text-xs font-medium text-slate-600">Equipment Quantities</label>
              <div v-for="eq in form.equipment" :key="eq" class="flex flex-col sm:flex-row items-start sm:items-center gap-2">
                <div class="w-full sm:w-1/2 text-sm text-slate-700">{{ eq }}</div>
                <div class="w-full sm:w-1/2">
                  <input type="number" min="1" v-model.number="form.equipment_quantities[eq]" @input="validateField('equipment_quantities')" :class="['w-full rounded-lg border bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500', fieldErrors.equipment_quantities?.[eq] ? 'border-red-400' : 'border-slate-200 focus:border-indigo-400']" placeholder="Quantity" />
                  <p v-if="fieldErrors.equipment_quantities?.[eq]" class="mt-1 text-xs text-red-600">{{ fieldErrors.equipment_quantities[eq] }}</p>
                </div>
              </div>
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Other Equipment (describe)</label>
              <input v-model="form.others" type="text" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" placeholder="e.g. podium, stage lights" />
            </div>
          </div>
          <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
            <button @click.prevent="closeModal" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">Cancel</button>
            <button @click.prevent="editingRequest ? submit() : openPinModal()" :disabled="form.processing || pinLoading" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-60">
              <span v-if="form.processing">Submitting…</span>
              <span v-else>Submit</span>
            </button>
          </div>
        </div>
      </div>

    </div>
    <CsmForm
      :show="showCsmModal"
      respondable-type="facility-request"
      :respondable-id="requestToCsm?.id ?? 0"
      :transaction-date="requestToCsm?.date_filed?.slice(0,10) ?? requestToCsm?.created_at?.slice(0,10) ?? ''"
      office-availed="General Services Unit"
      service-key="facility"
      service-other-label=""
      @close="showCsmModal = false"
      @submitted="showCsmModal = false"
    />
    <DigitalSignaturePin
      :show="showSubmitPin"
      :hasPin="props.hasPin"
      :signatureUri="props.signatureUri"
      :loading="pinLoading"
      confirmLabel="Sign & Submit"
      @confirm="handlePinConfirm"
      @cancel="handlePinCancel"
    />
    <DigitalSignaturePin
      :show="showCsmPin"
      :hasPin="props.hasPin"
      :signatureUri="props.signatureUri"
      :loading="csmPinLoading"
      confirmLabel="Sign & Confirm"
      @confirm="handleCsmPinConfirm"
      @cancel="showCsmPin = false"
    />
  </AdminLayout>
</template>
