<script setup>
import { Head, usePage, router } from "@inertiajs/vue3";
import { computed, ref, watch } from "vue";
import axios from "axios";
import Swal from 'sweetalert2'
import { PencilSquareIcon, TrashIcon, UserIcon, PrinterIcon } from "@heroicons/vue/24/outline";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import AppModal from '@/Components/AppModal.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { useVehicleRequests } from "@/Composables/useVehicleRequests";
import { statusBadgeClass } from '@/Composables/useStatusBadge.js'
import CsmForm from '@/Components/CsmForm.vue'
import DigitalSignaturePin from '@/Components/DigitalSignaturePin.vue'
import PaginationControl from '@/Components/PaginationControl.vue'

const props = defineProps({
  requests:       Object,
  vehicles:       Array,
  divisionChiefs: Array,
  filters:        { type: Object, default: () => ({}) },
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
} = useVehicleRequests(props.requests?.data ?? [], props.vehicles || [])

const searchQuery = ref(props.filters?.search ?? '')
const isLoading = ref(false)

const buildParams = (pageNum = undefined) => ({
  search: searchQuery.value || undefined,
  per_page: props.filters?.per_page || undefined,
  page: pageNum || undefined,
})

function applyFilters() {
    isLoading.value = true
    router.get(route('vehicle-requests.index'), buildParams(), {
      preserveState: true,
      replace: true,
      only: ['requests', 'filters', 'hasPendingCsm'],
      onFinish: () => { isLoading.value = false },
    })
}

function clearFilters() {
  searchQuery.value = ''
  isLoading.value = true
  router.get(route('vehicle-requests.index'), {}, {
    preserveState: true,
    replace: true,
    only: ['requests', 'filters', 'hasPendingCsm'],
    onFinish: () => { isLoading.value = false },
  })
}

function goToPage(pageNum) {
  isLoading.value = true
  router.get(route('vehicle-requests.index'), buildParams(pageNum), {
    preserveState: true,
    replace: true,
    only: ['requests', 'filters', 'hasPendingCsm'],
    onFinish: () => { isLoading.value = false },
  })
}

const filteredRequests = computed(() => props.requests?.data ?? [])
const currentPage = computed(() => props.requests?.current_page ?? 1)
const totalPages = computed(() => props.requests?.last_page ?? 1)
const visibleColumnCount = computed(() => hasAnyRole('Staff','Faculty') ? 9 : 10)

// Status badge color (AppBadge color keyword) derived from the shared status-class mapping
function statusColor(status) {
  const cls = statusBadgeClass(status)
  if (cls.includes('emerald')) return 'green'
  if (cls.includes('blue'))    return 'blue'
  if (cls.includes('amber'))   return 'amber'
  if (cls.includes('purple'))  return 'purple'
  if (cls.includes('orange'))  return 'orange'
  if (cls.includes('red'))     return 'red'
  return 'slate'
}

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
    <div class="space-y-5">

      <AppPageHeader title="Vehicle Requests">
        <template #actions>
          <AppButton v-if="!hasRole('GSU Head')" @click.prevent="handleNewRequest()">+ New Request</AppButton>
          <AppButton variant="secondary" @click.prevent="openCalendar()">View Calendar</AppButton>
        </template>
      </AppPageHeader>

      <!-- Flash / banner -->
      <div v-if="page.props.flash?.success" class="bg-success-50 border border-success-100 text-success-700 rounded-lg px-4 py-3 text-sm">{{ page.props.flash.success }}</div>
      <div v-if="banner">
        <div v-if="banner.type === 'success'" class="bg-success-50 border border-success-100 text-success-700 rounded-lg px-4 py-3 text-sm">{{ banner.message }}</div>
        <div v-else-if="banner.type === 'error'" class="bg-danger-50 border border-danger-100 text-danger-600 rounded-lg px-4 py-3 text-sm">{{ banner.message }}</div>
        <div v-else class="bg-slate-50 border border-slate-100 text-slate-700 rounded-lg px-4 py-3 text-sm">{{ banner.message }}</div>
      </div>

      <!-- Filter bar -->
      <AppFilterBar>
        <div class="relative flex-1 min-w-[180px] sm:w-64 sm:flex-none">
          <input v-model="searchQuery" type="text" placeholder="Search vehicle requests..."
                 @keydown.enter.prevent="applyFilters"
                 class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
          <span v-if="isLoading" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs">Searching…</span>
        </div>
        <template #actions>
          <AppButton size="sm" :disabled="isLoading" @click="applyFilters">Search</AppButton>
          <AppButton v-if="searchQuery" size="sm" variant="secondary" :disabled="isLoading" @click="clearFilters">Clear</AppButton>
        </template>
      </AppFilterBar>

      <!-- Table -->
      <AppTable :is-empty="!filteredRequests.length" :skeleton-cols="visibleColumnCount">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">#</th>
            <th v-if="!hasAnyRole('Staff','Faculty')" class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">{{ hasRole('GSU Head') ? 'Requestor' : 'Submitted By' }}</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Purpose</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Vehicle</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Date Needed</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Departure</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">ETA</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Status</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Driver</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Action</th>
          </tr>
        </template>

        <tr v-for="req in filteredRequests" :key="req.id" class="hover:bg-indigo-50/40">
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
            <AppBadge :color="statusColor(req.status)">{{ req.status }}</AppBadge>
          </td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ req.driver?.name ?? '—' }}</td>
          <td class="px-4 py-3">
            <div class="flex items-center gap-1 justify-center">
              <AppIconButton v-if="roleName === 'Administrator' && req.status !== 'Approved' && req.status !== 'Declined' && req.status !== 'OCD Approved'" label="Edit request" size="sm" @click.prevent="openModal(req)"><PencilSquareIcon class="w-4 h-4" /></AppIconButton>
              <AppIconButton v-if="roleName === 'Administrator' && req.status !== 'Approved' && req.status !== 'Declined' && req.status !== 'OCD Approved'" label="Delete request" variant="danger" size="sm" @click.prevent="destroy(req)"><TrashIcon class="w-4 h-4" /></AppIconButton>
              <AppIconButton v-if="hasAnyRole('Administrator','GSU Head') && ['Approved','FAD Approved','OCD Approved'].includes(req.status) && !req.driver" label="Assign driver" size="sm" @click.prevent="openAssignDriverModal(req)"><UserIcon class="w-4 h-4" /></AppIconButton>
              <AppIconButton v-if="hasAnyRole('Administrator','GSU Head') && (req.status === 'OCD Approved' || req.status === 'Completed')" label="Print request" size="sm" @click.prevent="openPrint(req)"><PrinterIcon class="w-4 h-4" /></AppIconButton>
              <AppButton v-if="req.status === 'OCD Approved' && req.requestor_id === page.props.auth.user.id" size="sm" variant="success" @click.prevent="openCsmModal(req)">Confirm &amp; Rate</AppButton>
            </div>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="req in filteredRequests" :key="req.id" class="p-4 space-y-3">
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
            <div class="text-sm text-slate-700 space-y-1">
              <div><strong>ETA:</strong> {{ req.eta ?? '—' }}</div>
              <div><strong>Driver:</strong> {{ req.driver?.name ?? '—' }}</div>
              <div class="flex items-center gap-1.5"><strong>Status:</strong> <AppBadge :color="statusColor(req.status)">{{ req.status }}</AppBadge></div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <AppButton v-if="roleName === 'Administrator' && req.status !== 'Approved' && req.status !== 'Declined' && req.status !== 'OCD Approved'" size="sm" class="flex-1" @click.prevent="openModal(req)"><PencilSquareIcon class="w-4 h-4" /> Edit</AppButton>
              <AppIconButton v-if="roleName === 'Administrator' && req.status !== 'Approved' && req.status !== 'Declined' && req.status !== 'OCD Approved'" label="Delete request" variant="danger" @click.prevent="destroy(req)"><TrashIcon class="w-4 h-4" /></AppIconButton>
              <AppButton v-if="hasAnyRole('Administrator','GSU Head') && ['Approved','FAD Approved','OCD Approved'].includes(req.status) && !req.driver" size="sm" variant="secondary" @click.prevent="openAssignDriverModal(req)"><UserIcon class="w-4 h-4" /> Assign</AppButton>
              <AppButton v-if="hasAnyRole('Administrator','GSU Head') && (req.status === 'OCD Approved' || req.status === 'Completed')" size="sm" variant="secondary" @click.prevent="openPrint(req)"><PrinterIcon class="w-4 h-4" /> Print</AppButton>
              <AppButton v-if="req.status === 'OCD Approved' && req.requestor_id === page.props.auth.user.id" size="sm" variant="success" class="flex-1" @click.prevent="openCsmModal(req)">Confirm &amp; Rate</AppButton>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No vehicle requests found" />
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
    </div>

    <!-- Calendar Modal -->
    <AppModal :show="showCalendar" title="Booking Calendar" size="4xl" @close="showCalendar = false">
      <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-2">
          <AppButton variant="secondary" size="sm" @click.prevent="prevMonth">‹</AppButton>
          <div class="font-semibold text-slate-800">{{ monthLabel }}</div>
          <AppButton variant="secondary" size="sm" @click.prevent="nextMonth">›</AppButton>
        </div>
        <AppButton size="sm" @click.prevent="fetchBookings">Refresh</AppButton>
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
    </AppModal>

    <!-- Assign Driver Modal -->
    <AppModal :show="showAssignDriverModal" title="Assign Driver" size="md" @close="closeAssignDriverModal">
      <div class="space-y-4">
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
      <template #footer>
        <AppButton variant="secondary" @click.prevent="closeAssignDriverModal">Cancel</AppButton>
        <AppButton :disabled="assignLoading || !selectedDriverId" @click.prevent="assignDriver">Assign</AppButton>
      </template>
    </AppModal>

    <!-- Create / Edit Modal -->
    <AppModal :show="showModal" :title="editingRequest ? 'Edit Vehicle Request' : 'New Vehicle Request'" size="md" @close="closeModal">
      <div class="space-y-4">

        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Purpose</label>
          <input v-model="form.purpose" @input="() => validateField('purpose')" type="text"
                 :class="['rounded-lg border bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full', fieldErrors.purpose ? 'border-danger-500' : 'border-slate-200']" />
          <p v-if="fieldErrors.purpose" class="text-danger-600 text-xs mt-1">{{ fieldErrors.purpose }}</p>
          <p v-else-if="form.errors.purpose" class="text-danger-600 text-xs mt-1">{{ form.errors.purpose }}</p>
        </div>

        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Destination</label>
          <input v-model="form.destination" @input="() => validateField('destination')" type="text"
                 :class="['rounded-lg border bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full', fieldErrors.destination ? 'border-danger-500' : 'border-slate-200']" />
          <p v-if="fieldErrors.destination" class="text-danger-600 text-xs mt-1">{{ fieldErrors.destination }}</p>
          <p v-else-if="form.errors.destination" class="text-danger-600 text-xs mt-1">{{ form.errors.destination }}</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Time of Departure</label>
            <input v-model="form.time_of_departure" @input="() => validateField('time_of_departure')" type="time"
                   :class="['rounded-lg border bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full', fieldErrors.time_of_departure ? 'border-danger-500' : 'border-slate-200']" />
            <p v-if="fieldErrors.time_of_departure" class="text-danger-600 text-xs mt-1">{{ fieldErrors.time_of_departure }}</p>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Estimated Time of Arrival</label>
            <input v-model="form.eta" @input="() => validateField('eta')" type="time"
                   :class="['rounded-lg border bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full', fieldErrors.eta ? 'border-danger-500' : 'border-slate-200']" />
            <p v-if="fieldErrors.eta" class="text-danger-600 text-xs mt-1">{{ fieldErrors.eta }}</p>
          </div>
        </div>

        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Date(s) Needed</label>
          <div class="mt-1 flex flex-col sm:flex-row sm:items-start gap-2">
            <input v-model="dateInput" type="date"
                   class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
            <AppButton @click.prevent="addDate">Add</AppButton>
          </div>
          <p v-if="fieldErrors.date_needed" class="text-danger-600 text-xs mt-1">{{ fieldErrors.date_needed }}</p>
          <ul class="mt-2 list-disc pl-5 text-sm text-slate-700">
            <li v-for="(d, idx) in form.date_needed" :key="idx" class="flex items-center justify-between">
              <span>{{ new Date(d).toLocaleDateString() }}</span>
              <button @click.prevent="form.date_needed.splice(idx, 1)" class="text-danger-600 text-xs">Remove</button>
            </li>
            <li v-if="form.date_needed.length === 0" class="text-slate-400">No dates added.</li>
          </ul>
        </div>

        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Vehicle Type</label>
          <select v-model="form.vehicle_type" @change="() => validateField('vehicle_type')"
                  :class="['rounded-lg border bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full', fieldErrors.vehicle_type ? 'border-danger-500' : 'border-slate-200']">
            <option value="">Select vehicle</option>
            <option v-for="v in props.vehicles" :key="v.id" :value="v.name">{{ v.name }}</option>
          </select>
          <p v-if="fieldErrors.vehicle_type" class="text-danger-600 text-xs mt-1">{{ fieldErrors.vehicle_type }}</p>
        </div>

        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Division Chief (Approver)</label>
          <select v-model="form.division_chief_id" @change="() => validateField('division_chief_id')"
                  :class="['rounded-lg border bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full', fieldErrors.division_chief_id ? 'border-danger-500' : 'border-slate-200']">
            <option value="">Select division chief</option>
            <option v-for="d in props.divisionChiefs" :key="d.id" :value="d.id">{{ d.name }}</option>
          </select>
          <p v-if="fieldErrors.division_chief_id" class="text-danger-600 text-xs mt-1">{{ fieldErrors.division_chief_id }}</p>
        </div>

        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Passengers</label>
          <input v-model.number="form.passengers" @input="() => validateField('passengers')" type="number" min="1"
                 :class="['rounded-lg border bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-24', fieldErrors.passengers ? 'border-danger-500' : 'border-slate-200']" />
          <p v-if="fieldErrors.passengers" class="text-danger-600 text-xs mt-1">{{ fieldErrors.passengers }}</p>
        </div>
      </div>
      <template #footer>
        <AppButton variant="secondary" @click.prevent="closeModal">Cancel</AppButton>
        <AppButton :loading="form.processing" :disabled="form.processing" @click.prevent="editingRequest ? submit() : openPinModal()">
          {{ form.processing ? 'Submitting...' : 'Submit' }}
        </AppButton>
      </template>
    </AppModal>

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
