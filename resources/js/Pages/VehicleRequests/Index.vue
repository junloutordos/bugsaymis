<script setup>
import { Head, usePage } from "@inertiajs/vue3";
import { computed } from "vue";
import { PencilSquareIcon, TrashIcon, UserIcon, PrinterIcon, CheckIcon, XMarkIcon } from "@heroicons/vue/24/outline";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import { useVehicleRequests } from "@/Composables/useVehicleRequests";

const props = defineProps({ requests: Array, vehicles: Array, divisionChiefs: Array });
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
  destroy, approveRequest, declineRequest, openPrint,
} = useVehicleRequests(props.requests || [], props.vehicles || [])
</script>

<template>
  <Head title="Vehicle Requests" />
  <AdminLayout title="Vehicle Requests">
    <div>
      <!-- Flash / banner -->
      <div v-if="page.props.flash?.success" class="mb-4">
        <div class="px-4 py-3 rounded bg-green-50 border border-green-100 text-green-700">{{ page.props.flash.success }}</div>
      </div>
      <div v-if="banner" class="mb-4">
        <div v-if="banner.type === 'success'" class="px-4 py-3 rounded bg-green-50 border border-green-100 text-green-700">{{ banner.message }}</div>
        <div v-else-if="banner.type === 'error'"   class="px-4 py-3 rounded bg-red-50 border border-red-100 text-red-700">{{ banner.message }}</div>
        <div v-else class="px-4 py-3 rounded bg-gray-50 border border-gray-100 text-gray-700">{{ banner.message }}</div>
      </div>

      <!-- Header -->
      <div class="flex items-center justify-between mb-4 gap-2">
        <h1 class="text-xl md:text-2xl font-bold text-gray-800 truncate">Vehicle Requests</h1>
        <div class="flex items-center gap-2">
          <button v-if="!hasRole('GSU Head')" @click.prevent="openModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow">
            + New Request
          </button>
          <button @click.prevent="openCalendar()" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded-lg shadow" title="View calendar">
            View Calendar
          </button>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow p-4">
        <!-- Search -->
        <div class="mb-4">
          <input v-model="searchQuery" type="text" placeholder="Search vehicle requests..." class="w-full sm:w-1/2 md:w-1/3 rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" />
        </div>

        <!-- Desktop table -->
        <div class="hidden sm:block overflow-x-auto">
          <table class="table-fixed w-full border border-gray-200">
            <thead class="bg-gray-100 text-gray-700 uppercase text-sm">
              <tr>
                <th class="px-4 py-3 text-left whitespace-normal break-words">#</th>
                <th v-if="!hasAnyRole('Staff','Faculty')" class="px-4 py-3 text-left whitespace-normal break-words">{{ hasRole('GSU Head') ? 'Requestor' : 'Submitted By' }}</th>
                <th class="px-4 py-3 text-left whitespace-normal break-words">Purpose</th>
                <th class="px-4 py-3 text-left whitespace-normal break-words">Vehicle</th>
                <th class="px-4 py-3 text-left whitespace-normal break-words">Date Needed</th>
                <th class="px-4 py-3 text-left whitespace-normal break-words">Departure</th>
                <th class="px-4 py-3 text-left whitespace-normal break-words">ETA</th>
                <th class="px-4 py-3 text-left whitespace-normal break-words">Status</th>
                <th class="px-4 py-3 text-left whitespace-normal break-words">Driver</th>
                <th class="px-4 py-3 text-center whitespace-normal break-words">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 text-sm">
              <tr v-for="req in filteredRequests" :key="req.id">
                <td class="px-4 py-3">{{ req.id }}</td>
                <td v-if="!hasAnyRole('Staff','Faculty')" class="px-4 py-3">{{ req.requester?.name ?? '—' }}</td>
                <td class="px-4 py-3 whitespace-normal break-words">{{ req.purpose }}</td>
                <td class="px-4 py-3">{{ req.vehicle_type ?? '—' }}</td>
                <td class="px-4 py-3">
                  <div v-if="req.date_needed_multiple?.length">
                    <div v-for="(d, i) in req.date_needed_multiple" :key="i">{{ new Date(d).toLocaleDateString() }}</div>
                  </div>
                  <div v-else>{{ req.date_needed ? new Date(req.date_needed).toLocaleDateString() : '—' }}</div>
                </td>
                <td class="px-4 py-3">{{ req.time_of_departure ?? '—' }}</td>
                <td class="px-4 py-3">{{ req.eta ?? '—' }}</td>
                <td class="px-4 py-3">
                  <span v-if="req.status?.includes('Approved')" class="bg-green-100 text-green-800 px-2 py-1 rounded font-semibold">{{ req.status }}</span>
                  <span v-else-if="req.status === 'Declined'"   class="bg-red-100 text-red-800 px-2 py-1 rounded font-semibold">{{ req.status }}</span>
                  <span v-else>{{ req.status }}</span>
                </td>
                <td class="px-4 py-3">{{ req.driver?.name ?? '—' }}</td>
                <td class="px-4 py-3 text-center">
                  <div class="flex items-center gap-2 justify-center">
                    <button v-if="roleName === 'Administrator' && req.status !== 'Approved' && req.status !== 'Declined' && req.status !== 'OCD Approved'" @click.prevent="openModal(req)" class="p-2 rounded-full bg-blue-100 hover:bg-blue-200 text-blue-700" title="Edit"><PencilSquareIcon class="w-5 h-5" /></button>
                    <button v-if="roleName === 'Administrator' && req.status !== 'Approved' && req.status !== 'Declined' && req.status !== 'OCD Approved'" @click.prevent="destroy(req)" class="p-2 rounded-full bg-red-100 hover:bg-red-200 text-red-700" title="Delete"><TrashIcon class="w-5 h-5" /></button>
                    <button v-if="hasAnyRole('Administrator','GSU Head') && req.status === 'Approved' && !req.driver" @click.prevent="openAssignDriverModal(req)" class="p-2 rounded-full bg-indigo-100 hover:bg-indigo-200 text-indigo-700" title="Assign Driver"><UserIcon class="w-5 h-5" /></button>
                    <button v-if="hasAnyRole('Administrator','GSU Head') && req.status === 'OCD Approved'" @click.prevent="openPrint(req)" class="p-2 rounded-full bg-gray-100 hover:bg-gray-200 text-gray-700" title="Print"><PrinterIcon class="w-5 h-5" /></button>
                    <button v-if="roleName === 'DivisionChief' && req.status === 'Pending'" @click.prevent="approveRequest(req)" class="p-2 rounded-full bg-green-100 hover:bg-green-200 text-green-700" title="Approve"><CheckIcon class="w-5 h-5" /></button>
                    <button v-if="roleName === 'DivisionChief' && req.status === 'Pending'" @click.prevent="declineRequest(req)" class="p-2 rounded-full bg-red-100 hover:bg-red-200 text-red-700" title="Decline"><XMarkIcon class="w-5 h-5" /></button>
                  </div>
                </td>
              </tr>
              <tr v-if="filteredRequests.length === 0">
                <td :colspan="hasAnyRole('Staff','Faculty') ? 9 : 10" class="px-4 py-6 text-center text-gray-500">No vehicle requests found.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="flex justify-center items-center gap-2 mt-4">
          <button @click="currentPage--" :disabled="currentPage === 1" class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50">Prev</button>
          <span>Page {{ currentPage }} of {{ totalPages }}</span>
          <button @click="currentPage++" :disabled="currentPage === totalPages" class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50">Next</button>
        </div>

        <!-- Mobile cards -->
        <div class="sm:hidden space-y-3 mt-4">
          <div v-for="req in filteredRequests" :key="req.id" class="border rounded-lg p-3 bg-white shadow-sm">
            <div class="flex items-start justify-between">
              <div>
                <div class="text-sm text-gray-500">Request #{{ req.id }}</div>
                <div class="text-sm text-gray-600">
                  <span v-if="!hasAnyRole('Staff','Faculty')">{{ req.requester?.name ?? '—' }} — </span>{{ req.vehicle_type ?? '—' }}
                </div>
              </div>
              <div class="text-right text-sm">
                <div class="text-gray-600">{{ req.date_needed_multiple?.length ? new Date(req.date_needed_multiple[0]).toLocaleDateString() : (req.date_needed ? new Date(req.date_needed).toLocaleDateString() : '—') }}</div>
                <div class="text-gray-500 text-xs">{{ req.time_of_departure ?? '—' }}</div>
              </div>
            </div>
            <div class="mt-2 text-sm text-gray-700">
              <div><strong>ETA:</strong> {{ req.eta ?? '—' }}</div>
              <div class="mt-1"><strong>Driver:</strong> {{ req.driver?.name ?? '—' }}</div>
              <div class="mt-1"><strong>Status:</strong> {{ req.status }}</div>
            </div>
            <div class="mt-3 flex items-center gap-2">
              <button v-if="roleName === 'Administrator' && req.status !== 'Approved' && req.status !== 'Declined' && req.status !== 'OCD Approved'" @click.prevent="openModal(req)" class="flex-1 inline-flex items-center justify-center gap-2 px-3 py-2 bg-blue-600 text-white rounded-md"><PencilSquareIcon class="w-4 h-4" /> Edit</button>
              <button v-if="roleName === 'Administrator' && req.status !== 'Approved' && req.status !== 'Declined' && req.status !== 'OCD Approved'" @click.prevent="destroy(req)" class="inline-flex items-center gap-2 px-3 py-2 bg-red-100 text-red-700 rounded-md"><TrashIcon class="w-4 h-4" /></button>
              <button v-if="hasAnyRole('Administrator','GSU Head') && req.status === 'Approved' && !req.driver" @click.prevent="openAssignDriverModal(req)" class="inline-flex items-center gap-2 px-3 py-2 bg-indigo-100 text-indigo-700 rounded-md"><UserIcon class="w-4 h-4" /> Assign</button>
              <button v-if="hasAnyRole('Administrator','GSU Head') && req.status === 'OCD Approved'" @click.prevent="openPrint(req)" class="inline-flex items-center gap-2 px-3 py-2 bg-gray-100 text-gray-700 rounded-md"><PrinterIcon class="w-4 h-4" /> Print</button>
            </div>
          </div>
          <div v-if="filteredRequests.length === 0" class="text-center text-gray-500 py-6">No vehicle requests found.</div>
        </div>
      </div>
    </div>

    <!-- ── Calendar Modal ───────────────────────────────────────────────────── -->
    <div v-if="showCalendar" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div class="bg-white w-full sm:rounded-xl sm:shadow-lg sm:max-w-4xl p-4 sm:p-6 relative overflow-auto max-h-[90vh]">
        <button class="absolute top-3 right-3 text-gray-500 hover:text-gray-800" @click.prevent="showCalendar = false">✕</button>
        <div class="flex items-center justify-between mb-4">
          <div class="flex items-center gap-2">
            <button @click.prevent="prevMonth" class="px-3 py-1 bg-gray-100 rounded">‹</button>
            <div class="font-semibold">{{ monthLabel }}</div>
            <button @click.prevent="nextMonth" class="px-3 py-1 bg-gray-100 rounded">›</button>
          </div>
          <button @click.prevent="fetchBookings" class="px-3 py-1 bg-blue-600 text-white rounded">Refresh</button>
        </div>
        <div class="grid grid-cols-7 gap-2 mt-2">
          <div v-for="day in ['Sun','Mon','Tue','Wed','Thu','Fri','Sat']" :key="day" class="text-center font-semibold">{{ day }}</div>
        </div>
        <div class="grid grid-cols-7 gap-2 mt-2">
          <template v-for="(d, idx) in monthDays" :key="d ? d.toISOString() : 'blank-' + idx">
            <div class="border rounded p-2 min-h-[80px] bg-white">
              <div class="text-xs text-gray-600 mb-1">{{ d ? d.getDate() : '' }}</div>
              <div class="space-y-1 text-xs">
                <template v-if="d">
                  <div v-for="b in bookingsForDate(d)" :key="b.id" class="bg-gray-50 p-1 rounded border">
                    <div class="font-medium">{{ b.vehicle_name }}{{ b.plate_no ? ' — ' + b.plate_no : '' }}</div>
                    <div class="text-gray-600">{{ b.start_time ?? '—' }} — {{ b.end_time ?? '—' }}</div>
                    <div class="text-gray-700 truncate">{{ b.purpose }}</div>
                  </div>
                  <div v-if="bookingsForDate(d).length === 0" class="text-gray-300">-</div>
                </template>
              </div>
            </div>
          </template>
        </div>
      </div>
    </div>

    <!-- ── Assign Driver Modal ──────────────────────────────────────────────── -->
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

    <!-- ── Create / Edit Modal ─────────────────────────────────────────────── -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div class="bg-white w-full h-full sm:h-auto sm:rounded-xl sm:shadow-lg sm:max-w-md p-4 sm:p-6 relative overflow-auto">
        <button class="absolute top-3 right-3 text-gray-500 hover:text-gray-800" @click="closeModal">✕</button>
        <h2 class="text-xl font-semibold mb-4">{{ editingRequest ? 'Edit Vehicle Request' : 'New Vehicle Request' }}</h2>
        <div class="space-y-4 max-h-[90vh] overflow-auto">

          <div>
            <label class="block text-sm font-medium text-gray-700">Purpose</label>
            <input v-model="form.purpose" @input="() => validateField('purpose')" type="text" :class="['mt-1 block w-full rounded', fieldErrors.purpose ? 'border-red-500 ring-1 ring-red-200' : 'border-gray-300']" />
            <p v-if="fieldErrors.purpose" class="text-red-600 text-sm mt-1">{{ fieldErrors.purpose }}</p>
            <p v-else-if="form.errors.purpose" class="text-red-600 text-sm mt-1">{{ form.errors.purpose }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700">Destination</label>
            <input v-model="form.destination" @input="() => validateField('destination')" type="text" :class="['mt-1 block w-full rounded', fieldErrors.destination ? 'border-red-500 ring-1 ring-red-200' : 'border-gray-300']" />
            <p v-if="fieldErrors.destination" class="text-red-600 text-sm mt-1">{{ fieldErrors.destination }}</p>
            <p v-else-if="form.errors.destination" class="text-red-600 text-sm mt-1">{{ form.errors.destination }}</p>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700">Time of Departure</label>
              <input v-model="form.time_of_departure" @input="() => validateField('time_of_departure')" type="time" :class="['mt-1 block w-full rounded', fieldErrors.time_of_departure ? 'border-red-500 ring-1 ring-red-200' : 'border-gray-300']" />
              <p v-if="fieldErrors.time_of_departure" class="text-red-600 text-sm mt-1">{{ fieldErrors.time_of_departure }}</p>
              <p v-else-if="form.errors.time_of_departure" class="text-red-600 text-sm mt-1">{{ form.errors.time_of_departure }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Estimated Time of Arrival</label>
              <input v-model="form.eta" @input="() => validateField('eta')" type="time" :class="['mt-1 block w-full rounded', fieldErrors.eta ? 'border-red-500 ring-1 ring-red-200' : 'border-gray-300']" />
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
                <button @click.prevent="form.date_needed.splice(idx, 1)" class="text-red-500 text-sm">Remove</button>
              </li>
              <li v-if="form.date_needed.length === 0" class="text-gray-400">No dates added.</li>
            </ul>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700">Vehicle Type</label>
            <select v-model="form.vehicle_type" @change="() => validateField('vehicle_type')" :class="['mt-1 block w-full rounded', fieldErrors.vehicle_type ? 'border-red-500 ring-1 ring-red-200' : 'border-gray-300']">
              <option value="">Select vehicle</option>
              <option v-for="v in props.vehicles" :key="v.id" :value="v.name">{{ v.name }}</option>
            </select>
            <p v-if="fieldErrors.vehicle_type" class="text-red-600 text-sm mt-1">{{ fieldErrors.vehicle_type }}</p>
            <p v-else-if="form.errors.vehicle_type" class="text-red-600 text-sm mt-1">{{ form.errors.vehicle_type }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700">Division Chief (Approver)</label>
            <select v-model="form.division_chief_id" @change="() => validateField('division_chief_id')" :class="['mt-1 block w-full rounded', fieldErrors.division_chief_id ? 'border-red-500 ring-1 ring-red-200' : 'border-gray-300']">
              <option value="">Select division chief</option>
              <option v-for="d in props.divisionChiefs" :key="d.id" :value="d.id">{{ d.name }}</option>
            </select>
            <p v-if="fieldErrors.division_chief_id" class="text-red-600 text-sm mt-1">{{ fieldErrors.division_chief_id }}</p>
            <p v-else-if="form.errors.division_chief_id" class="text-red-600 text-sm mt-1">{{ form.errors.division_chief_id }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700">Passengers</label>
            <input v-model.number="form.passengers" @input="() => validateField('passengers')" type="number" min="1" :class="['mt-1 block w-24 rounded', fieldErrors.passengers ? 'border-red-500 ring-1 ring-red-200' : 'border-gray-300']" />
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
