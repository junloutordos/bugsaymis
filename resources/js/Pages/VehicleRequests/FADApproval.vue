<script setup>
import { ref, computed } from "vue"
import { Head, router } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import AppModal from '@/Components/AppModal.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { CheckCircleIcon, XCircleIcon, EyeIcon } from "@heroicons/vue/24/outline"
import Swal from "sweetalert2"
import { statusBadgeClass } from '@/Composables/useStatusBadge.js'
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
const isSubmitting    = ref(false)
const search          = ref(props.filters?.search ?? '')
const isLoading       = ref(false)

const applyFilters = () => {
    isLoading.value = true
    router.get(route('vehicle-requests.fad-approval'), { search: search.value || undefined }, {
      preserveState: true, replace: true,
      only: ['requests', 'filters'],
      onFinish: () => { isLoading.value = false },
    })
}

const clearFilters = () => {
  search.value = ''
  isLoading.value = true
  router.get(route('vehicle-requests.fad-approval'), {}, {
    preserveState: true, replace: true,
    only: ['requests', 'filters'],
    onFinish: () => { isLoading.value = false },
  })
}



const goToPage = (pageNum) => {
  isLoading.value = true
  router.get(route('vehicle-requests.fad-approval'), { search: search.value || undefined, page: pageNum }, {
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

const showApprovePin   = ref(false)
const pendingApproveId = ref(null)
const pinLoading       = ref(false)

const approveRequest = async (id) => {
  const result = await Swal.fire({
    title: 'Recommend approval for this vehicle request?',
    icon: 'question', showCancelButton: true,
    confirmButtonText: 'Yes, recommend', cancelButtonText: 'Cancel', reverseButtons: true,
  })
  if (!result.isConfirmed) return
  pendingApproveId.value = id
  showApprovePin.value = true
}

function handleApproveConfirm(pin) {
  showApprovePin.value = false
  isSubmitting.value = true
  Swal.fire({ title: 'Processing…', allowOutsideClick: false, showConfirmButton: false, didOpen: () => Swal.showLoading() })
  router.post(route('vehicle-requests.fad-action', pendingApproveId.value), { action: 'approve', pin: pin || null }, {
    onSuccess: () => Swal.fire('Forwarded!', 'Vehicle request forwarded to OCD for final approval.', 'success'),
    onFinish:  () => { isSubmitting.value = false },
  })
}

const rejectRequest = async (id) => {
  const result = await Swal.fire({
    title: 'Decline this vehicle request?',
    icon: 'warning', showCancelButton: true,
    confirmButtonText: 'Yes, decline', cancelButtonText: 'Cancel', reverseButtons: true,
  })
  if (!result.isConfirmed) return
  isSubmitting.value = true
  Swal.fire({ title: 'Declining…', allowOutsideClick: false, showConfirmButton: false, didOpen: () => Swal.showLoading() })
  router.post(route('vehicle-requests.fad-action', id), { action: 'reject' }, {
    onSuccess: () => Swal.fire('Declined', 'Vehicle request declined.', 'error'),
    onFinish:  () => { isSubmitting.value = false },
  })
}
</script>

<template>
  <Head title="FAD Approval - Vehicle Requests" />
  <AdminLayout title="FAD Approval - Vehicle Requests">
    <div class="space-y-5">

      <AppPageHeader title="FAD Approval — Vehicle Requests" subtitle="Review and recommend vehicle requests pending FAD approval" />

      <!-- Filter bar -->
      <AppFilterBar>
        <div class="relative flex-1 min-w-[180px] sm:w-64 sm:flex-none">
          <input v-model="search" type="text" placeholder="Search requests…"
                 @keydown.enter.prevent="applyFilters"
                 class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
          <span v-if="isLoading" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs">Loading…</span>
        </div>
        <template #actions>
          <AppButton size="sm" :disabled="isLoading" @click="applyFilters">Search</AppButton>
          <AppButton v-if="search" size="sm" variant="secondary" :disabled="isLoading" @click="clearFilters">Clear</AppButton>
        </template>
      </AppFilterBar>

      <!-- Table -->
      <AppTable :is-empty="!filteredRequests.length" :skeleton-cols="7">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">#</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Requestor</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Purpose</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Destination</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Date Needed</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Status</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Actions</th>
          </tr>
        </template>

        <tr v-for="req in filteredRequests" :key="req.id" class="hover:bg-slate-50/60">
          <td class="px-4 py-3 text-sm text-slate-700">{{ req.id }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ req.requester?.name ?? '—' }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ req.purpose ?? '—' }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ req.destination ?? '—' }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ req.date_needed ?? '—' }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">
            <AppBadge :color="statusColor(req.status)">{{ req.status }}</AppBadge>
          </td>
          <td class="px-4 py-3 text-center">
            <div class="flex items-center gap-1.5 justify-center">
              <AppButton size="sm" variant="success" :disabled="isSubmitting" @click="approveRequest(req.id)">
                <CheckCircleIcon class="w-3.5 h-3.5" /> Recommend
              </AppButton>
              <AppButton size="sm" variant="danger" :disabled="isSubmitting" @click="rejectRequest(req.id)">
                <XCircleIcon class="w-3.5 h-3.5" /> Decline
              </AppButton>
              <AppIconButton label="View details" size="sm" @click="openModal(req)">
                <EyeIcon class="w-4 h-4" />
              </AppIconButton>
            </div>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="req in filteredRequests" :key="req.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="text-xs text-slate-400">#{{ req.id }}</p>
                <p class="font-medium text-slate-800">{{ req.requester?.name ?? '—' }}</p>
                <p class="text-xs text-slate-500">{{ req.purpose ?? '—' }}</p>
              </div>
              <AppBadge :color="statusColor(req.status)">{{ req.status }}</AppBadge>
            </div>
            <p class="text-xs text-slate-500">{{ req.destination ?? '—' }} &middot; {{ req.date_needed ?? '—' }}</p>
            <div class="flex flex-wrap items-center gap-2 pt-1">
              <AppButton size="sm" variant="success" :disabled="isSubmitting" @click="approveRequest(req.id)">
                <CheckCircleIcon class="w-3.5 h-3.5" /> Recommend
              </AppButton>
              <AppButton size="sm" variant="danger" :disabled="isSubmitting" @click="rejectRequest(req.id)">
                <XCircleIcon class="w-3.5 h-3.5" /> Decline
              </AppButton>
              <AppIconButton label="View details" @click="openModal(req)">
                <EyeIcon class="w-4 h-4" />
              </AppIconButton>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No pending vehicle requests for FAD approval" />
        </template>

        <!-- Pagination -->
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
      <AppModal :show="showModal && !!selectedRequest" :title="`Vehicle Request #${selectedRequest?.id}`" size="lg" @close="closeModal">
        <div v-if="selectedRequest" class="grid grid-cols-2 gap-3 text-sm text-slate-700">
          <div><span class="text-xs font-medium text-slate-500 uppercase tracking-wide">Requestor</span><p class="mt-0.5">{{ selectedRequest.requester?.name ?? '—' }}</p></div>
          <div><span class="text-xs font-medium text-slate-500 uppercase tracking-wide">Status</span><p class="mt-0.5"><AppBadge :color="statusColor(selectedRequest.status)">{{ selectedRequest.status }}</AppBadge></p></div>
          <div><span class="text-xs font-medium text-slate-500 uppercase tracking-wide">Purpose</span><p class="mt-0.5">{{ selectedRequest.purpose ?? '—' }}</p></div>
          <div><span class="text-xs font-medium text-slate-500 uppercase tracking-wide">Destination</span><p class="mt-0.5">{{ selectedRequest.destination ?? '—' }}</p></div>
          <div><span class="text-xs font-medium text-slate-500 uppercase tracking-wide">Date Needed</span><p class="mt-0.5">{{ selectedRequest.date_needed ?? '—' }}</p></div>
          <div><span class="text-xs font-medium text-slate-500 uppercase tracking-wide">Vehicle Type</span><p class="mt-0.5">{{ selectedRequest.vehicle_type ?? '—' }}</p></div>
          <div><span class="text-xs font-medium text-slate-500 uppercase tracking-wide">Passengers</span><p class="mt-0.5">{{ selectedRequest.passengers ?? '—' }}</p></div>
        </div>
        <template #footer>
          <AppButton variant="secondary" @click="closeModal">Close</AppButton>
        </template>
      </AppModal>
    </div>
    <DigitalSignaturePin
      :show="showApprovePin"
      :hasPin="props.hasPin"
      :signatureUri="props.signatureUri"
      :loading="pinLoading"
      confirmLabel="Sign & Recommend"
      @confirm="handleApproveConfirm"
      @cancel="showApprovePin = false"
    />
  </AdminLayout>
</template>
