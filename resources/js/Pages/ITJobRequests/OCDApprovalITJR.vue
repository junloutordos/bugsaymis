<script setup>
import { ref, computed, watch } from "vue"
import { Head, router } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import { CheckCircleIcon, XCircleIcon, EyeIcon } from "@heroicons/vue/24/outline"
import Swal from "sweetalert2"
import "sweetalert2/dist/sweetalert2.min.css"
import { statusBadgeClass, badgeBase } from '@/Composables/useStatusBadge.js'
import DigitalSignaturePin from '@/Components/DigitalSignaturePin.vue'

// Props
const props = defineProps({
  requests: Object,
  filters: Object,
  categories: Array,
  hasPin: { type: Boolean, default: false },
  signatureUri: { type: String, default: null },
})

// State
const showModal = ref(false)
const selectedRequest = ref(null)
const isSubmitting = ref(false)
const sigShow = ref(false)
const sigLoading = ref(false)
const pendingApproveId = ref(null)

// Server-side filters
const search         = ref(props.filters?.search   ?? '')
const filterCategory = ref(props.filters?.category ?? '')
const isLoading      = ref(false)
let debounceTimer    = null

const buildParams = (page = undefined) => ({
  search:   search.value         || undefined,
  category: filterCategory.value || undefined,
  page:     page                 || undefined,
})

const applyFilters = (immediate = true) => {
  clearTimeout(debounceTimer)
  const go = () => {
    isLoading.value = true
    router.get(route('job-requests.ocd-approval'), buildParams(), {
      preserveState: true,
      replace: true,
      only: ['requests', 'filters'],
      onFinish: () => { isLoading.value = false },
    })
  }
  if (immediate) go()
  else debounceTimer = setTimeout(go, 400)
}

watch(search,         () => applyFilters(false))
watch(filterCategory, () => applyFilters(true))

const goToPage = (pageNum) => {
  isLoading.value = true
  router.get(route('job-requests.ocd-approval'), buildParams(pageNum), {
    preserveState: true,
    replace: true,
    only: ['requests', 'filters'],
    onFinish: () => { isLoading.value = false },
  })
}

const currentPage = computed(() => props.requests?.current_page ?? 1)
const totalPages = computed(() => props.requests?.last_page ?? 1)
const filteredRequests = computed(() => props.requests?.data ?? [])

// Modal
const openModal = (request) => {
  selectedRequest.value = request
  showModal.value = true
}

const closeModal = () => {
  selectedRequest.value = null
  showModal.value = false
}

// SweetAlert actions
const approveRequest = (id) => {
  pendingApproveId.value = id
  sigShow.value = true
}

const handleApproveConfirm = (pin) => {
  sigShow.value = false
  sigLoading.value = false
  const id = pendingApproveId.value
  pendingApproveId.value = null
  isSubmitting.value = true
  Swal.fire({ title: 'Approving request...', allowOutsideClick: false, allowEscapeKey: false, showConfirmButton: false, didOpen: () => { Swal.showLoading() } })
  router.post(route("job-requests.ocd-action", id), { action: "approve", pin }, {
    onSuccess: () => Swal.fire("Approved!", "The request has been approved.", "success"),
    onFinish: () => { isSubmitting.value = false },
  })
}

const handleApproveCancel = () => {
  sigShow.value = false
  pendingApproveId.value = null
}

const rejectRequest = async (id) => {
  const result = await Swal.fire({
    title: "Are you sure?",
    text: "Do you want to reject this request?",
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: "Yes, reject it!",
    cancelButtonText: "Cancel",
    reverseButtons: true
  })

  if (result.isConfirmed) {
    isSubmitting.value = true
    Swal.fire({ title: 'Rejecting request...', allowOutsideClick: false, allowEscapeKey: false, showConfirmButton: false, didOpen: () => { Swal.showLoading() } })
    router.post(route("job-requests.ocd-action", id), { action: "reject" }, {
      onSuccess: () => Swal.fire("Rejected!", "The request has been rejected.", "error"),
      onFinish: () => { isSubmitting.value = false },
    })
  }
}
</script>

<template>
  <Head title="OCD Approval - IT Job Requests" />
  <AdminLayout title="OCD Approval - IT Job Requests">
    <div>
      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <h1 class="text-xl font-semibold text-slate-800">OCD Approval — IT Job Requests</h1>
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
        <select
          v-model="filterCategory"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"
        >
          <option value="">All Categories</option>
          <option v-for="cat in props.categories" :key="cat.id" :value="cat.name">{{ cat.name }}</option>
        </select>
      </div>

      <!-- Table card -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">ITJR #</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Title</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Submitted By</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Status</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap text-center">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr
                v-for="req in filteredRequests"
                :key="req.id"
                class="hover:bg-slate-50/60"
              >
                <td class="px-4 py-3 text-sm text-slate-700">{{ req.itjr_no ?? req.id }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ req.title }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ req.user?.name ?? "—" }}</td>
                <td class="px-4 py-3">
                  <span :class="[badgeBase, statusBadgeClass(req.status)]">{{ req.status }}</span>
                </td>
                <td class="px-4 py-3 text-center">
                  <div class="flex items-center gap-2 justify-center">
                    <!-- Approve Button -->
                    <button
                        v-if="req.status === 'Pending OCD Approval'"
                        @click="approveRequest(req.id)"
                        :disabled="isSubmitting"
                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-xs font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <CheckCircleIcon class="w-4 h-4" />
                        <span>Approve</span>
                    </button>

                    <!-- Reject Button -->
                    <button
                        v-if="req.status === 'Pending OCD Approval'"
                        @click="rejectRequest(req.id)"
                        :disabled="isSubmitting"
                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-red-50 hover:bg-red-100 text-red-600 text-xs font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <XCircleIcon class="w-4 h-4" />
                        <span>Reject</span>
                    </button>

                    <!-- View Button -->
                    <button
                        @click="openModal(req)"
                        class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors"
                    >
                        <EyeIcon class="w-4 h-4" />
                    </button>
                  </div>
                </td>
              </tr>

              <tr v-if="filteredRequests.length === 0">
                <td colspan="5" class="py-16 text-center text-slate-400 text-sm">
                  No requests found.
                </td>
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

      <!-- Modal -->
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-800">{{ selectedRequest.title }}</h2>
            <button @click="closeModal" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors">✖</button>
          </div>
          <div class="px-6 py-5 space-y-2 text-sm text-slate-700">
            <p><strong>ITJR #:</strong> {{ selectedRequest.itjr_no ?? selectedRequest.id }}</p>
            <p><strong>Submitted By:</strong> {{ selectedRequest.user?.name ?? '—' }}</p>
            <p><strong>Status:</strong> {{ selectedRequest.status }}</p>
            <p><strong>Description:</strong> {{ selectedRequest.description ?? '—' }}</p>
            <p><strong>Division Chief:</strong> {{ selectedRequest.divisionchief ?? '—' }}</p>
            <p><strong>Assigned To:</strong> {{ selectedRequest.assignedto ?? '—' }}</p>
            <p><strong>Created At:</strong> {{ selectedRequest.created_at }}</p>
            <p><strong>Updated At:</strong> {{ selectedRequest.updated_at }}</p>
          </div>
        </div>
      </div>
    </div>

    <DigitalSignaturePin
      :show="sigShow"
      :has-pin="props.hasPin"
      :signature-uri="props.signatureUri"
      :loading="sigLoading"
      confirm-label="Approve & Sign"
      @confirm="handleApproveConfirm"
      @cancel="handleApproveCancel"
    />
  </AdminLayout>
</template>
