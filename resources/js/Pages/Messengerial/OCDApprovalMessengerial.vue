<script setup>
import { ref, computed } from "vue"
import { Head, router } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import { CheckCircleIcon, XCircleIcon, EyeIcon } from "@heroicons/vue/24/outline"
import Swal from "sweetalert2"
import "sweetalert2/dist/sweetalert2.min.css"
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import AppModal from '@/Components/AppModal.vue'
import EmptyState from '@/Components/EmptyState.vue'
import PaginationControl from '@/Components/PaginationControl.vue'

const props = defineProps({
  requests: Object,
  filters: Object,
})

const showModal = ref(false)
const selectedRequest = ref(null)
const isSubmitting = ref(false)

const search = ref(props.filters?.search ?? '')
const isLoading = ref(false)

const buildParams = (page = undefined) => ({
  search: search.value || undefined,
  page: page || undefined,
})

const applyFilters = () => {
    isLoading.value = true
    router.get(route('messengerial.ocd-approval'), buildParams(), {
      preserveState: true,
      replace: true,
      only: ['requests', 'filters'],
      onFinish: () => { isLoading.value = false },
    })
}

const clearFilters = () => {
  search.value = ''
  isLoading.value = true
  router.get(route('messengerial.ocd-approval'), {}, {
    preserveState: true, replace: true,
    only: ['requests', 'filters'],
    onFinish: () => { isLoading.value = false },
  })
}



const goToPage = (pageNum) => {
  isLoading.value = true
  router.get(route('messengerial.ocd-approval'), buildParams(pageNum), {
    preserveState: true,
    replace: true,
    only: ['requests', 'filters'],
    onFinish: () => { isLoading.value = false },
  })
}

const currentPage = computed(() => props.requests?.current_page ?? 1)
const totalPages = computed(() => props.requests?.last_page ?? 1)
const filteredRequests = computed(() => props.requests?.data ?? [])

// ── Status badge color mapping ──────────────────────────────────────────────
function statusColor(status) {
  const s = (status ?? '').toString().toLowerCase()
  if (s.includes('declined') || s.includes('rejected')) return 'red'
  if (s.includes('completed') || s.includes('approved')) return 'green'
  if (s.includes('pending')) return 'amber'
  return 'slate'
}

const openModal = (request) => {
  selectedRequest.value = request
  showModal.value = true
}

const closeModal = () => {
  selectedRequest.value = null
  showModal.value = false
}

const approveRequest = async (id) => {
  const result = await Swal.fire({
    title: "Approve this request?",
    text: "The requester will be notified and Records will be able to process it.",
    icon: "question",
    showCancelButton: true,
    confirmButtonText: "Yes, approve it!",
    cancelButtonText: "Cancel",
    reverseButtons: true,
  })

  if (result.isConfirmed) {
    isSubmitting.value = true
    Swal.fire({ title: 'Approving...', allowOutsideClick: false, allowEscapeKey: false, showConfirmButton: false, didOpen: () => { Swal.showLoading() } })
    router.post(route('messengerial.ocd-action', id), { action: 'approve' }, {
      onSuccess: () => Swal.fire("Approved!", "The request has been approved.", "success"),
      onFinish: () => { isSubmitting.value = false },
    })
  }
}

const rejectRequest = async (id) => {
  const result = await Swal.fire({
    title: "Reject this request?",
    input: "textarea",
    inputLabel: "Reason for rejection",
    inputPlaceholder: "Enter reason...",
    inputAttributes: { "aria-label": "Reason" },
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: "Yes, reject it!",
    cancelButtonText: "Cancel",
    reverseButtons: true,
    preConfirm: (reason) => {
      if (!reason || !reason.trim()) {
        Swal.showValidationMessage("Please provide a reason.")
        return false
      }
      return reason
    },
  })

  if (result.isConfirmed) {
    isSubmitting.value = true
    Swal.fire({ title: 'Rejecting...', allowOutsideClick: false, allowEscapeKey: false, showConfirmButton: false, didOpen: () => { Swal.showLoading() } })
    router.post(route('messengerial.ocd-action', id), { action: 'reject', reason: result.value }, {
      onSuccess: () => Swal.fire("Rejected!", "The request has been rejected.", "error"),
      onFinish: () => { isSubmitting.value = false },
    })
  }
}
</script>

<template>
  <Head title="OCD Approval — Messengerial" />
  <AdminLayout title="OCD Approval — Messengerial">
    <div class="space-y-5">

      <AppPageHeader title="OCD Approval — Messengerial Requests" />

      <!-- Filter bar -->
      <AppFilterBar>
        <div class="relative flex-1 sm:w-64 sm:flex-none">
          <input
            v-model="search"
            type="text"
            placeholder="Search requests..."
            @keydown.enter.prevent="applyFilters"
            class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full"
          />
          <span v-if="isLoading" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">⏳</span>
        </div>
        <template #actions>
          <AppButton :disabled="isLoading" @click="applyFilters">Search</AppButton>
        </template>
      </AppFilterBar>

      <!-- Table -->
      <AppTable :is-empty="filteredRequests.length === 0" :skeleton-cols="7">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Ref No.</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Requestor</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Unit</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Purpose</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Destination</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Status</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Actions</th>
          </tr>
        </template>

        <tr v-for="req in filteredRequests" :key="req.id" class="hover:bg-slate-50/60">
          <td class="px-4 py-3 text-sm text-slate-700">{{ req.reference_no ?? '—' }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ req.requestor ?? '—' }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ req.unit ?? '—' }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ req.purpose ?? '—' }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ req.destination ?? '—' }}</td>
          <td class="px-4 py-3">
            <AppBadge :color="statusColor(req.status)">{{ req.status }}</AppBadge>
          </td>
          <td class="px-4 py-3 text-center">
            <div class="flex items-center gap-2 justify-center">
              <AppButton
                v-if="req.status === 'Pending OCD Approval'"
                size="sm"
                variant="success"
                :disabled="isSubmitting"
                @click="approveRequest(req.id)"
              >
                <CheckCircleIcon class="w-4 h-4" />
                <span>Approve</span>
              </AppButton>
              <AppButton
                v-if="req.status === 'Pending OCD Approval'"
                size="sm"
                variant="danger"
                :disabled="isSubmitting"
                @click="rejectRequest(req.id)"
              >
                <XCircleIcon class="w-4 h-4" />
                <span>Reject</span>
              </AppButton>
              <AppIconButton label="View request details" @click="openModal(req)">
                <EyeIcon class="w-4 h-4" />
              </AppIconButton>
            </div>
          </td>
        </tr>

        <template #empty>
          <EmptyState title="No requests pending OCD approval" />
        </template>

        <template #footer>
          <PaginationControl
            v-if="totalPages > 1"
            :current-page="currentPage"
            :total-pages="totalPages"
            @prev="goToPage(currentPage - 1)"
            @next="goToPage(currentPage + 1)"
            @page="goToPage"
          />
        </template>
      </AppTable>

      <!-- Detail Modal -->
      <AppModal :show="showModal" title="Request Details" size="lg" @close="closeModal">
        <div class="space-y-2 text-sm text-slate-700">
          <p><strong>Reference No.:</strong> {{ selectedRequest?.reference_no ?? '—' }}</p>
          <p><strong>Requestor:</strong> {{ selectedRequest?.requestor ?? '—' }}</p>
          <p><strong>Unit:</strong> {{ selectedRequest?.unit ?? '—' }}</p>
          <p><strong>Purpose:</strong> {{ selectedRequest?.purpose ?? '—' }}</p>
          <p><strong>Destination:</strong> {{ selectedRequest?.destination ?? '—' }}</p>
          <p><strong>Consignee:</strong> {{ selectedRequest?.consignee_name ?? '—' }} ({{ selectedRequest?.consignee_contact ?? '—' }})</p>
          <p><strong>Package Type(s):</strong> {{ Array.isArray(selectedRequest?.messengerial_kinds) ? selectedRequest.messengerial_kinds.join(', ') : (selectedRequest?.messengerial_kinds || '—') }}</p>
          <p><strong>Delivery Method(s):</strong> {{ Array.isArray(selectedRequest?.delivery_methods) ? selectedRequest.delivery_methods.join(', ') : (selectedRequest?.delivery_methods || '—') }}</p>
          <p><strong>Status:</strong> {{ selectedRequest?.status }}</p>
        </div>
      </AppModal>
    </div>
  </AdminLayout>
</template>
