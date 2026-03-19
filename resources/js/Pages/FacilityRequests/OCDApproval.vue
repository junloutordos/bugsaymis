<script setup>
import { ref, computed, watch } from "vue"
import { Head, router } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import { CheckCircleIcon, XCircleIcon, EyeIcon } from "@heroicons/vue/24/outline"
import Swal from "sweetalert2"

const props = defineProps({ requests: Object, filters: Object })

const showModal       = ref(false)
const selectedRequest = ref(null)
const isSubmitting    = ref(false)
const search          = ref(props.filters?.search ?? '')
const isLoading       = ref(false)
let debounceTimer     = null

const applyFilters = (immediate = true) => {
  clearTimeout(debounceTimer)
  const go = () => {
    isLoading.value = true
    router.get(route('facility-requests.ocd-approval'), { search: search.value || undefined }, {
      preserveState: true, replace: true,
      only: ['requests', 'filters'],
      onFinish: () => { isLoading.value = false },
    })
  }
  if (immediate) go()
  else debounceTimer = setTimeout(go, 400)
}

watch(search, () => applyFilters(false))

const goToPage = (pageNum) => {
  isLoading.value = true
  router.get(route('facility-requests.ocd-approval'), { search: search.value || undefined, page: pageNum }, {
    preserveState: true, replace: true,
    only: ['requests', 'filters'],
    onFinish: () => { isLoading.value = false },
  })
}

const currentPage      = computed(() => props.requests?.current_page ?? 1)
const totalPages       = computed(() => props.requests?.last_page ?? 1)
const filteredRequests = computed(() => props.requests?.data ?? [])

const openModal  = (req) => { selectedRequest.value = req; showModal.value = true }
const closeModal = () => { selectedRequest.value = null; showModal.value = false }

const venueLabel = (venue) => {
  if (!venue) return '—'
  try {
    const arr = typeof venue === 'string' ? JSON.parse(venue) : venue
    return Array.isArray(arr) ? arr.join(', ') : String(venue)
  } catch { return String(venue) }
}

const approveRequest = async (id) => {
  const result = await Swal.fire({
    title: 'Approve this facility request?',
    icon: 'question', showCancelButton: true,
    confirmButtonText: 'Yes, approve', cancelButtonText: 'Cancel', reverseButtons: true,
  })
  if (!result.isConfirmed) return
  isSubmitting.value = true
  Swal.fire({ title: 'Approving…', allowOutsideClick: false, showConfirmButton: false, didOpen: () => Swal.showLoading() })
  router.post(route('facility-requests.ocd-action', id), { action: 'approve' }, {
    onSuccess: () => Swal.fire('Approved!', 'Facility request approved by OCD.', 'success'),
    onFinish:  () => { isSubmitting.value = false },
  })
}

const rejectRequest = async (id) => {
  const result = await Swal.fire({
    title: 'Decline this facility request?',
    icon: 'warning', showCancelButton: true,
    confirmButtonText: 'Yes, decline', cancelButtonText: 'Cancel', reverseButtons: true,
  })
  if (!result.isConfirmed) return
  isSubmitting.value = true
  Swal.fire({ title: 'Declining…', allowOutsideClick: false, showConfirmButton: false, didOpen: () => Swal.showLoading() })
  router.post(route('facility-requests.ocd-action', id), { action: 'reject' }, {
    onSuccess: () => Swal.fire('Declined', 'Facility request declined.', 'error'),
    onFinish:  () => { isSubmitting.value = false },
  })
}
</script>

<template>
  <Head title="OCD Approval - Facility Requests" />
  <AdminLayout title="OCD Approval - Facility Requests">
    <div>
      <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl md:text-2xl font-bold text-gray-800">OCD Approval — Facility Requests</h1>
      </div>

      <div class="bg-white rounded-xl shadow p-4">
        <div class="mb-4 flex flex-wrap items-center gap-2">
          <div class="relative flex-1 sm:w-64 sm:flex-none">
            <input v-model="search" type="text" placeholder="Search requests…"
                   @keydown.enter.prevent="applyFilters(true)"
                   class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" />
            <span v-if="isLoading" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">⏳</span>
          </div>
          <button @click="applyFilters(true)" :disabled="isLoading"
                  class="px-3 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50">
            Search
          </button>
        </div>

        <div class="overflow-x-auto">
          <table class="min-w-full border border-gray-200">
            <thead class="bg-gray-100 text-gray-700 uppercase text-sm">
              <tr>
                <th class="px-4 py-3 text-left">#</th>
                <th class="px-4 py-3 text-left">Requestor</th>
                <th class="px-4 py-3 text-left">Activity</th>
                <th class="px-4 py-3 text-left">Venue</th>
                <th class="px-4 py-3 text-left">Date</th>
                <th class="px-4 py-3 text-left">Status</th>
                <th class="px-4 py-3 text-center">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 text-sm">
              <tr v-for="req in filteredRequests" :key="req.id" class="hover:bg-gray-50">
                <td class="px-4 py-3">{{ req.id }}</td>
                <td class="px-4 py-3">{{ req.requester?.name ?? '—' }}</td>
                <td class="px-4 py-3">{{ req.activity ?? '—' }}</td>
                <td class="px-4 py-3">{{ venueLabel(req.venue) }}</td>
                <td class="px-4 py-3">{{ req.date_start ?? '—' }}</td>
                <td class="px-4 py-3">
                  <span class="px-2 py-1 text-xs rounded-full bg-yellow-100 text-yellow-700">{{ req.status }}</span>
                </td>
                <td class="px-4 py-3 text-center">
                  <div class="flex items-center gap-2 justify-center">
                    <button v-if="req.status === 'Approved'" @click="approveRequest(req.id)" :disabled="isSubmitting"
                            class="flex items-center gap-1 px-3 py-1 rounded-full bg-green-100 hover:bg-green-200 text-green-700 font-medium disabled:opacity-50">
                      <CheckCircleIcon class="w-4 h-4" /> Approve
                    </button>
                    <button v-if="req.status === 'Approved'" @click="rejectRequest(req.id)" :disabled="isSubmitting"
                            class="flex items-center gap-1 px-3 py-1 rounded-full bg-red-100 hover:bg-red-200 text-red-700 font-medium disabled:opacity-50">
                      <XCircleIcon class="w-4 h-4" /> Decline
                    </button>
                    <button @click="openModal(req)"
                            class="flex items-center gap-1 px-3 py-1 rounded-full bg-blue-100 hover:bg-blue-200 text-blue-700 font-medium">
                      <EyeIcon class="w-4 h-4" /> View
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="filteredRequests.length === 0">
                <td colspan="7" class="px-4 py-6 text-center text-gray-500">No pending facility requests for OCD approval.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="totalPages > 1" class="flex justify-center items-center gap-2 mt-4">
          <button @click="goToPage(currentPage - 1)" :disabled="currentPage === 1 || isLoading"
                  class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50">Prev</button>
          <span>Page {{ currentPage }} of {{ totalPages }}</span>
          <button @click="goToPage(currentPage + 1)" :disabled="currentPage === totalPages || isLoading"
                  class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50">Next</button>
        </div>
      </div>

      <!-- Detail Modal -->
      <div v-if="showModal && selectedRequest" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
        <div class="bg-white rounded-xl w-full max-w-lg shadow-lg p-6 relative">
          <button @click="closeModal" class="absolute top-3 right-3 text-gray-500 hover:text-gray-700">✖</button>
          <h2 class="text-lg font-bold mb-4">Facility Request #{{ selectedRequest.id }}</h2>
          <div class="space-y-2 text-sm">
            <p><strong>Requestor:</strong> {{ selectedRequest.requester?.name ?? '—' }}</p>
            <p><strong>Activity:</strong> {{ selectedRequest.activity ?? '—' }}</p>
            <p><strong>Purpose:</strong> {{ selectedRequest.purpose ?? '—' }}</p>
            <p><strong>Venue:</strong> {{ venueLabel(selectedRequest.venue) }}</p>
            <p><strong>Date:</strong> {{ selectedRequest.date_start ?? '—' }} — {{ selectedRequest.date_end ?? '—' }}</p>
            <p><strong>Time:</strong> {{ selectedRequest.time_start ?? '—' }} — {{ selectedRequest.time_end ?? '—' }}</p>
            <p><strong>Nature:</strong> {{ selectedRequest.nature ?? '—' }}</p>
            <p><strong>Participants:</strong> {{ selectedRequest.participants ?? '—' }}</p>
            <p><strong>Status:</strong> {{ selectedRequest.status }}</p>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
