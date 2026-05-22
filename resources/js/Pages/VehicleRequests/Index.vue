<script setup>
import { Head, usePage, router } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import axios from "axios";
import { PencilSquareIcon, TrashIcon, UserIcon, PrinterIcon } from "@heroicons/vue/24/outline";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import { useVehicleRequests } from "@/Composables/useVehicleRequests";
import { statusBadgeClass, badgeBase } from '@/Composables/useStatusBadge.js'
import CsmForm from '@/Components/CsmForm.vue'
import DigitalSignaturePin from '@/Components/DigitalSignaturePin.vue'

const props = defineProps({
  requests:       Array,
  vehicles:       Array,
  divisionChiefs: Array,
  hasPendingCsm:  { type: Boolean, default: false },
  hasPin:         { type: Boolean, default: false },
  signatureUri:   { type: String, default: null },
});
const page  = usePage();

const roleName  = computed(() => page.props.auth?.user?.role?.name ?? '')
const roleNames = computed(() => page.props.auth?.user?.roleNames ?? (roleName.value ? [roleName.value] : []))
const hasRole    = (role)     => roleNames.value.includes(role)
const hasAnyRole = (...roles) => roles.some(r => roleNames.value.includes(r))

const {
  // list
  searchQuery, currentPage, filteredRequests, totalPages,
  // banner
  banner,
  // assign driver
  showAssignDriverModal, drivers, selectedDriverId, selectedVehicleId, assignLoading,
  openAssignDriverModal, closeAssignDriverModal, assignDriver,
  // calendar
  showCalendar, monthLabel, fetchBookings, openCalendar, prevMonth, nextMonth,
  monthDays, bookingsForDate,
  // form
  form, fieldErrors, dateInput,
  validateField, addDate,
  // modal
  showModal, editingRequest,
  openModal, closeModal, submit,
  // actions
  destroy, openPrint,
} = useVehicleRequests(props.requests || [], props.vehicles || [])

// Dynamically add pin field to composable form
form.pin = null

const showSubmitPin = ref(false)
const pinLoading = ref(false)

const openPinModal = () => { showSubmitPin.value = true }
const handlePinCancel = () => { showSubmitPin.value = false }
const handlePinConfirm = (pin) => {
  form.pin = pin || null
  showSubmitPin.value = false
  submit()
}

const showCsmModal = ref(false)
const requestToCsm = ref(null)
const showCsmPin   = ref(false)
const csmPinLoading = ref(false)

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
    await axios.post(route('vehicle-requests.sign-completion', requestToCsm.value.id), { pin })
  } catch {
    // non-blocking — signature failure doesn't block CSM survey
  } finally {
    csmPinLoading.value = false
  }
  showCsmModal.value = true
}

import Swal from 'sweetalert2'

// Use server-side prop — accurate regardless of pagination or filters
const hasPendingConfirmation = computed(() => props.hasPendingCsm)

function onCsmSubmitted() {
  showCsmModal.value = false
  router.reload({ only: ['requests', 'hasPendingCsm'] })
}

async function handleNewRequest() {
  if (hasPendingConfirmation.value) {
    await Swal.fire({
      icon: 'warning',
      title: 'Action Required',
      text: 'You have a Vehicle Request that has been approved and is pending your confirmation. Please rate the service first before submitting a new request.',
      confirmButtonText: 'OK',
    })
    return
  }
  openModal()
}
</script>

<template>
  <Head title="Vehicle Requests" />
  <AdminLayout title="Vehicle Requests">
    <div>
      <!-- Flash / banner -->
      <div v-if="page.props.flash?.success" class="mb-4">
        <div class="px-4 py-3 rounded-lg bg-emerald-50 border border-emerald-100 text-emerald-700 text-sm">{{ page.props.flash.success }}</div>
      </div>
      <div v-if="banner" class="mb-4">
        <div v-if="banner.type === 'success'" class="px-4 py-3 rounded-lg bg-emerald-50 border border-emerald-100 text-emerald-700 text-sm">{{ banner.message }}</div>
        <div v-else-if="banner.type === 'error'" class="px-4 py-3 rounded-lg bg-red-50 border border-red-100 text-red-700 text-sm">{{ banner.message }}</div>
        <div v-else class="px-4 py-3 rounded-lg bg-slate-50 border border-slate-100 text-slate-700 text-sm">{{ banner.message }}</div>
      </div>

      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <h1 class="text-xl font-semibold text-slate-800">Vehicle Requests</h1>
        <div class="flex items-center gap-2">
          <button v-if="!hasRole('GSU Head')" @click.prevent="handleNewRequest()"
                  class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
            + New Request
          </button>
          <button @click.prevent="openCalendar()"
                  class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
            View Calendar
          </button>
        </div>
      </div>

      <!-- Filter bar -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 mb-4 flex flex-wrap items-center gap-3">
        <input v-model="searchQuery" type="text" placeholder="Search vehicle requests..."
               class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full sm:w-64" />
      </div>

      <!-- Table card -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <!-- Desktop table -->
        <div class="hidden sm:block overflow-x-auto rounded-xl border border-slate-100">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">#</th>
                <th v-if="!hasAnyRole('Staff','Faculty')" class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">{{ hasRole('GSU Head') ? 'Requestor' : 'Submitted By' }}</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Purpose</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Vehicle</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Date Needed</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Departure</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">ETA</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Status</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Driver</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="req in filteredRequests" :key="req.id" class="hover:bg-slate-50/60">
                <td class="px-4 py-3 text-sm text-slate-700">{{ req.id }}</td>
                <td v-if="!hasAnyRole('Staff','Faculty')" class="px-4 py-3 text-sm text-slate-700">{{ req.requester?.name ?? '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ req.purpose }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ req.vehicle_type ?? '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">
                  <div v-if="req.date_needed_multiple?.length">
                    <div v-for="(d, i) in req.date_needed_multiple" :key="i">{{ new Date(d).toLocaleDateString() }}</div>
                  </div>
                  <div v-else>{{ req.date_needed ? new Date(req.date_needed).toLocaleDateString() : '—' }}</div>
                </td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ req.time_of_departure ?? '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ req.eta ?? '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">
                  <span :class="[badgeBase, statusBadgeClass(req.status)]">{{ req.status }}</span>
                </td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ req.driver?.name ?? '—' }}</td>
                <td class="px-4 py-3">
                  <div class="flex items-center gap-1 justify-center">
                    <button v-if="roleName === 'Administrator' && req.status !== 'Approved' && req.status !== 'Declined' && req.status !== 'OCD Approved'" @click.prevent="openModal(req)" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" title="Edit"><PencilSquareIcon class="w-4 h-4" /></button>
                    <button v-if="roleName === 'Administrator' && req.status !== 'Approved' && req.status !== 'Declined' && req.status !== 'OCD Approved'" @click.prevent="destroy(req)" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-red-600 transition-colors" title="Delete"><TrashIcon class="w-4 h-4" /></button>
                    <button v-if="hasAnyRole('Administrator','GSU Head') && req.status === 'Approved' && !req.driver" @click.prevent="openAssignDriverModal(req)" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-indigo-600 transition-colors" title="Assign Driver"><UserIcon class="w-4 h-4" /></button>
                    <button v-if="hasAnyRole('Administrator','GSU Head') && (req.status === 'OCD Approved' || req.status === 'Completed')" @click.prevent="openPrint(req)" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" title="Print"><PrinterIcon class="w-4 h-4" /></button>
                    <button v-if="req.status === 'OCD Approved' && req.requestor_id === page.props.auth.user.id" @click.prevent="openCsmModal(req)" class="inline-flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1 rounded-lg text-xs font-medium transition-colors shadow-sm">Confirm &amp; Rate</button>
                  </div>
                </td>
              </tr>
              <tr v-if="filteredRequests.length === 0">
                <td :colspan="hasAnyRole('Staff','Faculty') ? 9 : 10" class="py-16 text-center text-slate-400 text-sm">No vehicle requests found.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-sm text-slate-600">
          <span>Page {{ currentPage }} of {{ totalPages }}</span>
          <div class="flex gap-2">
            <button @click="currentPage--" :disabled="currentPage === 1" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50">Prev</button>
            <button @click="currentPage++" :disabled="currentPage === totalPages" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50">Next</button>
          </div>
        </div>

        <!-- Mobile cards -->
        <div class="sm:hidden space-y-3 p-4">
          <div v-for="req in filteredRequests" :key="req.id" class="border border-slate-200 rounded-lg p-3 bg-white shadow-sm">
            <div class="flex items-start justify-between">
              <div>
                <div class="text-xs text-slate-400">Request #{{ req.id }}</div>
                <div class="text-sm text-slate-600">
                  <span v-if="!hasAnyRole('Staff','Faculty')">{{ req.requester?.name ?? '—' }} — </span>{{ req.vehicle_type ?? '—' }}
                </div>
              </div>
              <div class="text-right text-sm">
                <div class="text-slate-600">{{ req.date_needed_multiple?.length ? new Date(req.date_needed_multiple[0]).toLocaleDateString() : (req.date_needed ? new Date(req.date_needed).toLocaleDateString() : '—') }}</div>
                <div class="text-slate-400 text-xs">{{ req.time_of_departure ?? '—' }}</div>
              </div>
            </div>
            <div class="mt-2 text-sm text-slate-700">
              <div><strong>ETA:</strong> {{ req.eta ?? '—' }}</div>
              <div class="mt-1"><strong>Driver:</strong> {{ req.driver?.name ?? '—' }}</div>
              <div class="mt-1"><strong>Status:</strong> {{ req.status }}</div>
            </div>
            <div class="mt-3 flex items-center gap-2">
              <button v-if="roleName === 'Administrator' && req.status !== 'Approved' && req.status !== 'Declined' && req.status !== 'OCD Approved'" @click.prevent="openModal(req)" class="flex-1 inline-flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm"><PencilSquareIcon class="w-4 h-4" /> Edit</button>
              <button v-if="roleName === 'Administrator' && req.status !== 'Approved' && req.status !== 'Declined' && req.status !== 'OCD Approved'" @click.prevent="destroy(req)" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-3 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm"><TrashIcon class="w-4 h-4" /></button>
              <button v-if="hasAnyRole('Administrator','GSU Head') && req.status === 'Approved' && !req.driver" @click.prevent="openAssignDriverModal(req)" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-3 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm"><UserIcon class="w-4 h-4" /> Assign</button>
              <button v-if="hasAnyRole('Administrator','GSU Head') && (req.status === 'OCD Approved' || req.status === 'Completed')" @click.prevent="openPrint(req)" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-3 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm"><PrinterIcon class="w-4 h-4" /> Print</button>
              <button v-if="req.status === 'OCD Approved' && req.requestor_id === page.props.auth.user.id" @click.prevent="openCsmModal(req)" class="flex-1 inline-flex items-center justify-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-2 rounded-lg text-xs font-medium transition-colors shadow-sm">Confirm &amp; Rate</button>
            </div>
          </div>
          <div v-if="filteredRequests.length === 0" class="py-16 text-center text-slate-400 text-sm">No vehicle requests found.</div>
        </div>
      </div>
    </div>

    <!-- Calendar Modal -->
    <div v-if="showCalendar" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50">
      <div class="bg-white w-full sm:rounded-2xl sm:shadow-xl sm:max-w-4xl p-4 sm:p-6 relative overflow-auto max-h-[90vh]">
        <button class="absolute top-3 right-3 p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" @click.prevent="showCalendar = false"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4 shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg></button>
        <div class="flex items-center justify-between mb-4">
          <div class="flex items-center gap-2">
            <button @click.prevent="prevMonth" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors shadow-sm">‹</button>
            <div class="font-semibold text-slate-800">{{ monthLabel }}</div>
            <button @click.prevent="nextMonth" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors shadow-sm">›</button>
          </div>
          <button @click.prevent="fetchBookings" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">Refresh</button>
        </div>
        <div class="grid grid-cols-7 gap-2 mt-2">
          <div v-for="day in ['Sun','Mon','Tue','Wed','Thu','Fri','Sat']" :key="day" class="text-center text-xs font-semibold text-slate-500">{{ day }}</div>
        </div>
        <div class="grid grid-cols-7 gap-2 mt-2">
          <template v-for="(d, idx) in monthDays" :key="d ? d.toISOString() : 'blank-' + idx">
            <div class="border border-slate-100 rounded-lg p-2 min-h-[80px] bg-white">
              <div class="text-xs text-slate-500 mb-1">{{ d ? d.getDate() : '' }}</div>
              <div class="space-y-1 text-xs">
                <template v-if="d">
                  <div v-for="b in bookingsForDate(d)" :key="b.id" class="bg-slate-50 p-1 rounded border border-slate-100">
                    <div class="font-medium text-slate-700">{{ b.vehicle_name }}{{ b.plate_no ? ' — ' + b.plate_no : '' }}</div>
                    <div class="text-slate-500">{{ b.start_time ?? '—' }} — {{ b.end_time ?? '—' }}</div>
                    <div class="text-slate-600 truncate">{{ b.purpose }}</div>
                  </div>
                  <div v-if="bookingsForDate(d).length === 0" class="text-slate-300">-</div>
                </template>
              </div>
            </div>
          </template>
        </div>
      </div>
    </div>

    <!-- Assign Driver Modal -->
    <div v-if="showAssignDriverModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50">
      <div class="bg-white w-full sm:h-auto sm:rounded-2xl sm:shadow-xl sm:max-w-md relative overflow-auto">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
          <h2 class="text-xl font-semibold text-slate-800">Assign Driver</h2>
          <button class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" @click="closeAssignDriverModal"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4 shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg></button>
        </div>
        <div class="px-6 py-5 space-y-4 max-h-[70vh] overflow-auto">
          <div v-if="assignLoading" class="py-8 text-center text-slate-400 text-sm">Loading drivers...</div>
          <div v-else class="space-y-4">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Vehicle (change if needed)</label>
              <select v-model="selectedVehicleId" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full">
                <option value="">Keep requested vehicle</option>
                <option v-for="v in props.vehicles" :key="v.id" :value="v.id">{{ v.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Driver</label>
              <select v-model="selectedDriverId" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full">
                <option value="">Select driver</option>
                <option v-for="d in drivers" :key="d.id" :value="d.id">{{ d.name }}{{ d.position ? ' — ' + d.position : '' }}</option>
              </select>
            </div>
          </div>
        </div>
        <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
          <button @click.prevent="closeAssignDriverModal" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">Cancel</button>
          <button @click.prevent="assignDriver" :disabled="assignLoading || !selectedDriverId" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50">Assign</button>
        </div>
      </div>
    </div>

    <!-- Create / Edit Modal -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50">
      <div class="bg-white w-full h-full sm:h-auto sm:rounded-2xl sm:shadow-xl sm:max-w-md relative overflow-auto">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
          <h2 class="text-xl font-semibold text-slate-800">{{ editingRequest ? 'Edit Vehicle Request' : 'New Vehicle Request' }}</h2>
          <button class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" @click="closeModal"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4 shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg></button>
        </div>
        <div class="px-6 py-5 space-y-4 max-h-[80vh] overflow-auto">

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Purpose</label>
            <input v-model="form.purpose" @input="() => validateField('purpose')" type="text"
                   :class="['rounded-lg border bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full', fieldErrors.purpose ? 'border-red-400' : 'border-slate-200']" />
            <p v-if="fieldErrors.purpose" class="text-red-500 text-xs mt-1">{{ fieldErrors.purpose }}</p>
            <p v-else-if="form.errors.purpose" class="text-red-500 text-xs mt-1">{{ form.errors.purpose }}</p>
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Destination</label>
            <input v-model="form.destination" @input="() => validateField('destination')" type="text"
                   :class="['rounded-lg border bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full', fieldErrors.destination ? 'border-red-400' : 'border-slate-200']" />
            <p v-if="fieldErrors.destination" class="text-red-500 text-xs mt-1">{{ fieldErrors.destination }}</p>
            <p v-else-if="form.errors.destination" class="text-red-500 text-xs mt-1">{{ form.errors.destination }}</p>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Time of Departure</label>
              <input v-model="form.time_of_departure" @input="() => validateField('time_of_departure')" type="time"
                     :class="['rounded-lg border bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full', fieldErrors.time_of_departure ? 'border-red-400' : 'border-slate-200']" />
              <p v-if="fieldErrors.time_of_departure" class="text-red-500 text-xs mt-1">{{ fieldErrors.time_of_departure }}</p>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Estimated Time of Arrival</label>
              <input v-model="form.eta" @input="() => validateField('eta')" type="time"
                     :class="['rounded-lg border bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full', fieldErrors.eta ? 'border-red-400' : 'border-slate-200']" />
              <p v-if="fieldErrors.eta" class="text-red-500 text-xs mt-1">{{ fieldErrors.eta }}</p>
            </div>
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Date(s) Needed</label>
            <div class="mt-1 flex flex-col sm:flex-row sm:items-start gap-2">
              <input v-model="dateInput" type="date"
                     class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
              <button @click.prevent="addDate"
                      class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">Add</button>
            </div>
            <p v-if="fieldErrors.date_needed" class="text-red-500 text-xs mt-1">{{ fieldErrors.date_needed }}</p>
            <ul class="mt-2 list-disc pl-5 text-sm text-slate-700">
              <li v-for="(d, idx) in form.date_needed" :key="idx" class="flex items-center justify-between">
                <span>{{ new Date(d).toLocaleDateString() }}</span>
                <button @click.prevent="form.date_needed.splice(idx, 1)" class="text-red-500 text-xs">Remove</button>
              </li>
              <li v-if="form.date_needed.length === 0" class="text-slate-400">No dates added.</li>
            </ul>
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Vehicle Type</label>
            <select v-model="form.vehicle_type" @change="() => validateField('vehicle_type')"
                    :class="['rounded-lg border bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full', fieldErrors.vehicle_type ? 'border-red-400' : 'border-slate-200']">
              <option value="">Select vehicle</option>
              <option v-for="v in props.vehicles" :key="v.id" :value="v.name">{{ v.name }}</option>
            </select>
            <p v-if="fieldErrors.vehicle_type" class="text-red-500 text-xs mt-1">{{ fieldErrors.vehicle_type }}</p>
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Division Chief (Approver)</label>
            <select v-model="form.division_chief_id" @change="() => validateField('division_chief_id')"
                    :class="['rounded-lg border bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full', fieldErrors.division_chief_id ? 'border-red-400' : 'border-slate-200']">
              <option value="">Select division chief</option>
              <option v-for="d in props.divisionChiefs" :key="d.id" :value="d.id">{{ d.name }}</option>
            </select>
            <p v-if="fieldErrors.division_chief_id" class="text-red-500 text-xs mt-1">{{ fieldErrors.division_chief_id }}</p>
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Passengers</label>
            <input v-model.number="form.passengers" @input="() => validateField('passengers')" type="number" min="1"
                   :class="['rounded-lg border bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-24', fieldErrors.passengers ? 'border-red-400' : 'border-slate-200']" />
            <p v-if="fieldErrors.passengers" class="text-red-500 text-xs mt-1">{{ fieldErrors.passengers }}</p>
          </div>
        </div>
        <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
          <button @click.prevent="closeModal" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">Cancel</button>
          <button @click.prevent="editingRequest ? submit() : openPinModal()" :disabled="form.processing"
                  class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-60">
            <span v-if="form.processing" class="inline-flex items-center">
              <svg class="animate-spin mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
              </svg>
              Submitting...
            </span>
            <span v-else>Submit</span>
          </button>
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
    <DigitalSignaturePin
      :show="showCsmPin"
      :hasPin="hasPin"
      :signatureUri="signatureUri"
      :loading="csmPinLoading"
      confirmLabel="Sign & Confirm"
      @confirm="handleCsmPinConfirm"
      @cancel="showCsmPin = false"
    />
    <CsmForm
      :show="showCsmModal"
      respondable-type="vehicle-request"
      :respondable-id="requestToCsm?.id ?? 0"
      :transaction-date="requestToCsm?.created_at?.slice(0,10) ?? ''"
      office-availed="General Services Unit"
      service-key="others"
      service-other-label="Vehicle Request"
      @close="showCsmModal = false"
      @submitted="onCsmSubmitted"
    />
  </AdminLayout>
</template>
