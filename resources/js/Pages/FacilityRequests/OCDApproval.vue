<script setup>
import { ref, computed, watch } from "vue"
import { Head, router } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import { CheckCircleIcon, XCircleIcon, EyeIcon, XMarkIcon } from "@heroicons/vue/24/outline"
import Swal from "sweetalert2"
import { statusBadgeClass, badgeBase } from '@/Composables/useStatusBadge.js'
import DigitalSignaturePin from '@/Components/DigitalSignaturePin.vue'

const props = defineProps({
  requests:     Object,
  filters:      Object,
  hasPin:       { type: Boolean, default: false },
  signatureUri: { type: String, default: null },
})

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

const showApprovePin   = ref(false)
const pendingApproveId = ref(null)
const pinLoading       = ref(false)

const approveRequest = async (id) => {
  const result = await Swal.fire({
    title: 'Approve this facility request?',
    icon: 'question', showCancelButton: true,
    confirmButtonText: 'Yes, approve', cancelButtonText: 'Cancel', reverseButtons: true,
  })
  if (!result.isConfirmed) return
  pendingApproveId.value = id
  showApprovePin.value = true
}

function handleApproveConfirm(pin) {
  showApprovePin.value = false
  isSubmitting.value = true
  Swal.fire({ title: 'Approving…', allowOutsideClick: false, showConfirmButton: false, didOpen: () => Swal.showLoading() })
  router.post(route('facility-requests.ocd-action', pendingApproveId.value), { action: 'approve', pin: pin || null }, {
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
      <!-- Page header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">OCD Approval — Facility Requests</h1>
          <p class="text-sm text-slate-500 mt-0.5">Review and act on facility requests pending OCD approval</p>
        </div>
      </div>

      <!-- Filter bar -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 mb-4 flex flex-wrap items-center gap-3">
        <div class="relative flex-1 sm:w-64 sm:flex-none">
          <input v-model="search" type="text" placeholder="Search requests…"
                 @keydown.enter.prevent="applyFilters(true)"
                 class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
          <span v-if="isLoading" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs">Loading…</span>
        </div>
        <button @click="applyFilters(true)" :disabled="isLoading"
                class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50">
          Search
        </button>
      </div>

      <!-- Table card -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">#</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Requestor</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Activity</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Venue</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Date</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Status</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="req in filteredRequests" :key="req.id" class="hover:bg-slate-50/60">
                <td class="px-4 py-3 text-sm text-slate-700">{{ req.id }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ req.requester?.name ?? '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ req.activity ?? '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ venueLabel(req.venue) }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ req.date_start ?? '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">
                  <span :class="[badgeBase, statusBadgeClass(req.status)]">{{ req.status }}</span>
                </td>
                <td class="px-4 py-3 text-center">
                  <div class="flex items-center gap-1.5 justify-center">
                    <button v-if="req.status === 'Approved'" @click="approveRequest(req.id)" :disabled="isSubmitting"
                            class="inline-flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium transition-colors disabled:opacity-50">
                      <CheckCircleIcon class="w-3.5 h-3.5" /> Approve
                    </button>
                    <button v-if="req.status === 'Approved'" @click="rejectRequest(req.id)" :disabled="isSubmitting"
                            class="inline-flex items-center gap-1.5 bg-red-600 hover:bg-red-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium transition-colors disabled:opacity-50">
                      <XCircleIcon class="w-3.5 h-3.5" /> Decline
                    </button>
                    <button @click="openModal(req)"
                            class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" title="View details">
                      <EyeIcon class="w-4 h-4" />
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="filteredRequests.length === 0">
                <td colspan="7" class="py-16 text-center text-slate-400 text-sm">No pending facility requests for OCD approval.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="totalPages > 1" class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-sm text-slate-600">
          <span>Page {{ currentPage }} of {{ totalPages }}</span>
          <div class="flex items-center gap-2">
            <button @click="goToPage(currentPage - 1)" :disabled="currentPage === 1 || isLoading"
                    class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors disabled:opacity-50">Prev</button>
            <button @click="goToPage(currentPage + 1)" :disabled="currentPage === totalPages || isLoading"
                    class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors disabled:opacity-50">Next</button>
          </div>
        </div>
      </div>

      <!-- Detail Modal -->
      <div v-if="showModal && selectedRequest" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-800">Facility Request #{{ selectedRequest.id }}</h2>
            <button @click="closeModal" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors">
              <XMarkIcon class="w-4 h-4" />
            </button>
          </div>
          <div class="px-6 py-5 space-y-3 text-sm text-slate-700">
            <div class="grid grid-cols-2 gap-3">
              <div><span class="text-xs font-medium text-slate-500 uppercase tracking-wide">Requestor</span><p class="mt-0.5">{{ selectedRequest.requester?.name ?? '—' }}</p></div>
              <div><span class="text-xs font-medium text-slate-500 uppercase tracking-wide">Status</span><p class="mt-0.5"><span :class="[badgeBase, statusBadgeClass(selectedRequest.status)]">{{ selectedRequest.status }}</span></p></div>
              <div><span class="text-xs font-medium text-slate-500 uppercase tracking-wide">Activity</span><p class="mt-0.5">{{ selectedRequest.activity ?? '—' }}</p></div>
              <div><span class="text-xs font-medium text-slate-500 uppercase tracking-wide">Purpose</span><p class="mt-0.5">{{ selectedRequest.purpose ?? '—' }}</p></div>
              <div><span class="text-xs font-medium text-slate-500 uppercase tracking-wide">Venue</span><p class="mt-0.5">{{ venueLabel(selectedRequest.venue) }}</p></div>
              <div><span class="text-xs font-medium text-slate-500 uppercase tracking-wide">Date</span><p class="mt-0.5">{{ selectedRequest.date_start ?? '—' }} — {{ selectedRequest.date_end ?? '—' }}</p></div>
              <div><span class="text-xs font-medium text-slate-500 uppercase tracking-wide">Time</span><p class="mt-0.5">{{ selectedRequest.time_start ?? '—' }} — {{ selectedRequest.time_end ?? '—' }}</p></div>
              <div><span class="text-xs font-medium text-slate-500 uppercase tracking-wide">Nature</span><p class="mt-0.5">{{ selectedRequest.nature ?? '—' }}</p></div>
              <div class="col-span-2"><span class="text-xs font-medium text-slate-500 uppercase tracking-wide">Participants</span><p class="mt-0.5">{{ selectedRequest.participants ?? '—' }}</p></div>
            </div>
          </div>
          <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
            <button @click="closeModal" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">Close</button>
          </div>
        </div>
      </div>
    </div>
    <DigitalSignaturePin
      :show="showApprovePin"
      :hasPin="props.hasPin"
      :signatureUri="props.signatureUri"
      :loading="pinLoading"
      confirmLabel="Sign & Approve"
      @confirm="handleApproveConfirm"
      @cancel="showApprovePin = false"
    />
  </AdminLayout>
</template>
