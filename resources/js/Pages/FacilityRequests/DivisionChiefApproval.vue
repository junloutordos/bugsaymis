<script setup>
import { ref, computed } from "vue"
import { Head, router, usePage } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppModal from '@/Components/AppModal.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { CheckCircleIcon, XCircleIcon, EyeIcon } from "@heroicons/vue/24/outline"
import Swal from "sweetalert2"
import DigitalSignaturePin from '@/Components/DigitalSignaturePin.vue'
import PaginationControl from '@/Components/PaginationControl.vue'

const props = defineProps({
  requests:     Object,
  filters:      Object,
  hasPin:       { type: Boolean, default: false },
  signatureUri: { type: String, default: null },
})

const showModal       = ref(false)
const selectedRequest = ref(null)
const declineModal    = ref(false)
const declineId       = ref(null)
const declineReason   = ref('')
const isSubmitting    = ref(false)
const search          = ref(props.filters?.search ?? '')
const isLoading       = ref(false)

const applyFilters = () => {
    isLoading.value = true
    router.get(route('facility-requests.dc-approval'), { search: search.value || undefined }, {
      preserveState: true, replace: true,
      only: ['requests', 'filters'],
      onFinish: () => { isLoading.value = false },
    })
}

const clearFilters = () => {
  search.value = ''
  isLoading.value = true
  router.get(route('facility-requests.dc-approval'), {}, {
    preserveState: true, replace: true,
    only: ['requests', 'filters'],
    onFinish: () => { isLoading.value = false },
  })
}



const goToPage = (pageNum) => {
  isLoading.value = true
  router.get(route('facility-requests.dc-approval'), { search: search.value || undefined, page: pageNum }, {
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

// ── Status badge color mapping (AppBadge color keywords) ─────────────────────
function statusColor(status) {
  const s = (status ?? '').toString().toLowerCase().trim()
  if (['approved', 'ocd approved', 'fad approved'].includes(s)) return 'green'
  if (['pending', 'pending division chief approval', 'pending fad approval', 'pending ocd approval'].includes(s)) return 'amber'
  if (['declined', 'rejected'].includes(s)) return 'red'
  return 'slate'
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
  router.post(route('facility-requests.approve.inapp', pendingApproveId.value), { pin: pin || null }, {
    onSuccess: () => {
      const err = usePage().props.flash?.error
      if (err) Swal.fire('Not approved', err, 'error')
      else Swal.fire('Approved!', 'Facility request approved. FAD has been notified.', 'success')
    },
    onFinish:  () => { isSubmitting.value = false },
  })
}

const openDecline = (id) => { declineId.value = id; declineReason.value = ''; declineModal.value = true }
const closeDecline = () => { declineModal.value = false; declineId.value = null }

const submitDecline = () => {
  if (!declineReason.value.trim()) return
  isSubmitting.value = true
  router.post(route('facility-requests.decline.inapp', declineId.value), { reason: declineReason.value }, {
    onSuccess: () => { closeDecline(); Swal.fire('Declined', 'Facility request declined.', 'error') },
    onFinish:  () => { isSubmitting.value = false },
  })
}
</script>

<template>
  <Head title="DC Approval - Facility Requests" />
  <AdminLayout title="DC Approval - Facility Requests">
    <div class="space-y-5">

      <AppPageHeader title="Division Chief Approval — Facility Requests" subtitle="Pending facility requests from your division awaiting your approval" />

      <AppFilterBar>
        <div class="relative flex-1 min-w-[180px] sm:w-64 sm:flex-none">
          <input v-model="search" type="text" placeholder="Search requests…"
                 @keydown.enter.prevent="applyFilters"
                 class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
          <span v-if="isLoading" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs">Loading…</span>
        </div>
        <template #actions>
          <AppButton :disabled="isLoading" @click="applyFilters">Search</AppButton>
          <AppButton v-if="search" variant="secondary" :disabled="isLoading" @click="clearFilters">Clear</AppButton>
        </template>
      </AppFilterBar>

      <AppTable :is-empty="filteredRequests.length === 0" :skeleton-cols="7">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">#</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Requestor</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Activity</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Purpose</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Date</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Status</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Actions</th>
          </tr>
        </template>

        <tr v-for="req in filteredRequests" :key="req.id" class="hover:bg-indigo-50/40">
          <td class="px-4 py-3 text-slate-700">{{ req.id }}</td>
          <td class="px-4 py-3 text-slate-700">{{ req.requester?.name ?? '—' }}</td>
          <td class="px-4 py-3 text-slate-700">{{ req.activity ?? '—' }}</td>
          <td class="px-4 py-3 text-slate-700">{{ req.purpose ?? '—' }}</td>
          <td class="px-4 py-3 text-slate-700">{{ req.date_start ?? '—' }}</td>
          <td class="px-4 py-3">
            <AppBadge :color="statusColor(req.status)">{{ req.status }}</AppBadge>
          </td>
          <td class="px-4 py-3 text-center">
            <div class="flex items-center gap-1.5 justify-center">
              <AppButton size="sm" variant="success" :disabled="isSubmitting" @click="approveRequest(req.id)">
                <CheckCircleIcon class="w-3.5 h-3.5" /> Approve
              </AppButton>
              <AppButton size="sm" variant="danger" :disabled="isSubmitting" @click="openDecline(req.id)">
                <XCircleIcon class="w-3.5 h-3.5" /> Decline
              </AppButton>
              <AppIconButton label="View facility request details" @click="openModal(req)">
                <EyeIcon class="w-4 h-4" />
              </AppIconButton>
            </div>
          </td>
        </tr>

        <template #empty>
          <EmptyState title="No pending facility requests from your division." />
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

      <!-- Detail Modal -->
      <AppModal :show="showModal && !!selectedRequest" :title="`Facility Request #${selectedRequest?.id}`" size="lg" @close="closeModal">
        <div class="grid grid-cols-2 gap-3 text-sm text-slate-700">
          <div><span class="text-xs font-medium text-slate-500 uppercase">Requestor</span><p class="mt-0.5">{{ selectedRequest?.requester?.name ?? '—' }}</p></div>
          <div><span class="text-xs font-medium text-slate-500 uppercase">Status</span><p class="mt-0.5"><AppBadge :color="statusColor(selectedRequest?.status)">{{ selectedRequest?.status }}</AppBadge></p></div>
          <div><span class="text-xs font-medium text-slate-500 uppercase">Activity</span><p class="mt-0.5">{{ selectedRequest?.activity ?? '—' }}</p></div>
          <div><span class="text-xs font-medium text-slate-500 uppercase">Purpose</span><p class="mt-0.5">{{ selectedRequest?.purpose ?? '—' }}</p></div>
          <div><span class="text-xs font-medium text-slate-500 uppercase">Date Start</span><p class="mt-0.5">{{ selectedRequest?.date_start ?? '—' }}</p></div>
          <div><span class="text-xs font-medium text-slate-500 uppercase">Date End</span><p class="mt-0.5">{{ selectedRequest?.date_end ?? '—' }}</p></div>
          <div><span class="text-xs font-medium text-slate-500 uppercase">Time</span><p class="mt-0.5">{{ selectedRequest?.time_start ?? '—' }} – {{ selectedRequest?.time_end ?? '—' }}</p></div>
          <div><span class="text-xs font-medium text-slate-500 uppercase">Nature</span><p class="mt-0.5">{{ selectedRequest?.nature ?? '—' }}</p></div>
        </div>
        <template #footer>
          <AppButton variant="secondary" @click="closeModal">Close</AppButton>
        </template>
      </AppModal>

      <!-- Decline Modal -->
      <AppModal :show="declineModal" title="Decline Facility Request" size="md" @close="closeDecline">
        <label class="block text-sm font-medium text-slate-700 mb-2">Reason for Decline <span class="text-red-500">*</span></label>
        <textarea v-model="declineReason" rows="4" placeholder="Provide a reason for declining this request…"
                  class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400"></textarea>
        <template #footer>
          <AppButton variant="secondary" @click="closeDecline">Cancel</AppButton>
          <AppButton variant="danger" :disabled="!declineReason.trim() || isSubmitting" @click="submitDecline">
            {{ isSubmitting ? 'Declining…' : 'Decline Request' }}
          </AppButton>
        </template>
      </AppModal>
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
