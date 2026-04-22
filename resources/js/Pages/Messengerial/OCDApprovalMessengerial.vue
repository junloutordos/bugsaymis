<script setup>
import { ref, computed, watch } from "vue"
import { Head, router } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import { CheckCircleIcon, XCircleIcon, EyeIcon } from "@heroicons/vue/24/outline"
import Swal from "sweetalert2"
import "sweetalert2/dist/sweetalert2.min.css"
import { statusBadgeClass, badgeBase } from '@/Composables/useStatusBadge.js'

const props = defineProps({
  requests: Object,
  filters: Object,
})

const showModal = ref(false)
const selectedRequest = ref(null)
const isSubmitting = ref(false)

const search = ref(props.filters?.search ?? '')
const isLoading = ref(false)
let debounceTimer = null

const buildParams = (page = undefined) => ({
  search: search.value || undefined,
  page: page || undefined,
})

const applyFilters = (immediate = true) => {
  clearTimeout(debounceTimer)
  const go = () => {
    isLoading.value = true
    router.get(route('messengerial.ocd-approval'), buildParams(), {
      preserveState: true,
      replace: true,
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
    <div>
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <h1 class="text-xl font-semibold text-slate-800">OCD Approval — Messengerial Requests</h1>
      </div>

      <!-- Filter bar -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 mb-4 flex flex-wrap items-center gap-3">
        <div class="relative flex-1 sm:w-64 sm:flex-none">
          <input
            v-model="search"
            type="text"
            placeholder="Search requests..."
            @keydown.enter.prevent="applyFilters(true)"
            class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full"
          />
          <span v-if="isLoading" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">⏳</span>
        </div>
        <button
          @click="applyFilters(true)"
          :disabled="isLoading"
          class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50 whitespace-nowrap"
        >
          Search
        </button>
      </div>

      <!-- Table card -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Ref No.</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Requestor</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Unit</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Purpose</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Destination</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Status</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="req in filteredRequests" :key="req.id" class="hover:bg-slate-50/60">
                <td class="px-4 py-3 text-sm text-slate-700">{{ req.reference_no ?? '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ req.requestor ?? '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ req.unit ?? '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ req.purpose ?? '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ req.destination ?? '—' }}</td>
                <td class="px-4 py-3">
                  <span :class="[badgeBase, statusBadgeClass(req.status)]">{{ req.status }}</span>
                </td>
                <td class="px-4 py-3 text-center">
                  <div class="flex items-center gap-2 justify-center">
                    <button
                      v-if="req.status === 'Pending OCD Approval'"
                      @click="approveRequest(req.id)"
                      :disabled="isSubmitting"
                      class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-xs font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                      <CheckCircleIcon class="w-4 h-4" />
                      <span>Approve</span>
                    </button>
                    <button
                      v-if="req.status === 'Pending OCD Approval'"
                      @click="rejectRequest(req.id)"
                      :disabled="isSubmitting"
                      class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-red-50 hover:bg-red-100 text-red-600 text-xs font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                      <XCircleIcon class="w-4 h-4" />
                      <span>Reject</span>
                    </button>
                    <button
                      @click="openModal(req)"
                      class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors"
                      title="View"
                    >
                      <EyeIcon class="w-4 h-4" />
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="filteredRequests.length === 0">
                <td colspan="7" class="py-16 text-center text-slate-400 text-sm">No requests pending OCD approval.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="totalPages > 1" class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-sm text-slate-600">
          <button @click="goToPage(currentPage - 1)" :disabled="currentPage === 1 || isLoading" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-40">Prev</button>
          <span>Page {{ currentPage }} of {{ totalPages }}</span>
          <button @click="goToPage(currentPage + 1)" :disabled="currentPage === totalPages || isLoading" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-40">Next</button>
        </div>
      </div>

      <!-- Detail Modal -->
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-800">Request Details</h2>
            <button @click="closeModal" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors">✖</button>
          </div>
          <div class="px-6 py-5 space-y-2 text-sm text-slate-700">
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
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
