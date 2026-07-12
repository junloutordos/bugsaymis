<script setup>
import { ref, computed } from "vue"
import { Head, router } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import { CheckCircleIcon, XCircleIcon, EyeIcon, FunnelIcon } from "@heroicons/vue/24/outline"
import Swal from "sweetalert2"
import "sweetalert2/dist/sweetalert2.min.css"
import { statusBadgeClass, badgeBase } from '@/Composables/useStatusBadge.js'
import DigitalSignaturePin from '@/Components/DigitalSignaturePin.vue'
import PaginationControl from '@/Components/PaginationControl.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppInput from '@/Components/AppInput.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppTable from '@/Components/AppTable.vue'
import AppModal from '@/Components/AppModal.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { confirmAction } from '@/Composables/useConfirm.js'

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
const sigShow = ref(false)
const sigLoading = ref(false)
const pendingApproveId = ref(null)

// Server-side filters
const search         = ref(props.filters?.search   ?? '')
const filterCategory = ref(props.filters?.category ?? '')
const isLoading      = ref(false)

const buildParams = (page = undefined) => ({
  search:   search.value         || undefined,
  category: filterCategory.value || undefined,
  page:     page                 || undefined,
})

const applyFilters = () => {
    isLoading.value = true
    router.get(route('job-requests.for-approval'), buildParams(1), {
      preserveState: true,
      replace: true,
      only: ['requests', 'filters'],
      onFinish: () => { isLoading.value = false },
    })
}

const clearFilters = () => {
  search.value = ''
  filterCategory.value = ''
  isLoading.value = true
  router.get(route('job-requests.for-approval'), {}, {
    preserveState: true,
    replace: true,
    only: ['requests', 'filters'],
    onFinish: () => { isLoading.value = false },
  })
}


const goToPage = (pageNum) => {
  const page = Math.min(totalPages.value, Math.max(1, Number.parseInt(pageNum, 10) || 1))
  if (page === currentPage.value) return

  isLoading.value = true
  router.get(route('job-requests.for-approval'), buildParams(page), {
    preserveState: true,
    replace: true,
    only: ['requests', 'filters'],
    onFinish: () => { isLoading.value = false },
  })
}

const currentPage = computed(() => props.requests?.current_page ?? 1)
const totalPages = computed(() => props.requests?.last_page ?? 1)
const filteredRequests = computed(() => props.requests?.data ?? [])

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
  Swal.fire({ title: 'Approving request...', allowOutsideClick: false, allowEscapeKey: false, showConfirmButton: false, didOpen: () => { Swal.showLoading() } })
  router.post(route("job-requests.division-chief-action", id), { action: "approve", pin }, {
    onSuccess: () => Swal.fire("Approved!", "The request has been approved.", "success"),
  })
}

const handleApproveCancel = () => {
  sigShow.value = false
  pendingApproveId.value = null
}

const rejectRequest = async (id) => {
  const confirmed = await confirmAction({
    title: "Are you sure?",
    text: "Do you want to reject this request?",
    confirmText: "Yes, reject it!",
    icon: "warning",
  })

  if (confirmed) {
    Swal.fire({ title: 'Rejecting request...', allowOutsideClick: false, allowEscapeKey: false, showConfirmButton: false, didOpen: () => { Swal.showLoading() } })
    router.post(route("job-requests.division-chief-action", id), { action: "reject" }, {
      onSuccess: () => Swal.fire("Rejected!", "The request has been rejected.", "error"),
    })
  }
}

// Modal
const openModal = (request) => {
  selectedRequest.value = request
  showModal.value = true
}

const closeModal = () => {
  selectedRequest.value = null
  showModal.value = false
}
</script>

<template>
  <Head title="For Approval IT Job Requests" />
  <AdminLayout title="For Approval IT Job Requests">
    <div>
      <AppPageHeader title="For Approval — IT Job Requests" />

      <!-- Filter bar -->
      <AppFilterBar class="mb-4">
        <div class="relative flex-1 sm:w-64 sm:flex-none">
          <AppInput
            v-model="search"
            type="text"
            placeholder="Search requests..."
            @keydown.enter.prevent="applyFilters"
          />
          <span v-if="isLoading" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">⏳</span>
        </div>
        <select
          v-model="filterCategory"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"
        >
          <option value="">All Categories</option>
          <option v-for="cat in props.categories" :key="cat.id" :value="cat.name">{{ cat.name }}</option>
        </select>

        <template #actions>
          <AppButton @click="applyFilters" :disabled="isLoading">Search</AppButton>
          <AppButton v-if="search || filterCategory" @click="clearFilters" :disabled="isLoading" variant="secondary">Clear</AppButton>
        </template>
      </AppFilterBar>

      <!-- Table card -->
      <AppTable :is-empty="filteredRequests.length === 0" :skeleton-cols="5">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">ITJR #</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Title</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Submitted By</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Status</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap text-center">Actions</th>
          </tr>
        </template>

        <tr
          v-for="req in filteredRequests"
          :key="req.id"
          class="hover:bg-indigo-50/40"
        >
          <td class="px-4 py-3 text-sm text-slate-700">{{ req.itjr_no ?? req.id }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ req.title }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ req.user?.name ?? "—" }}</td>
          <td class="px-4 py-3">
            <span :class="[badgeBase, statusBadgeClass(req.status)]">{{ req.status }}</span>
          </td>
          <td class="px-4 py-3 text-center">
            <div class="flex items-center gap-2 justify-center">
              <AppButton
                  v-if="req.status === 'Pending Division Chief Approval'"
                  @click="approveRequest(req.id)"
                  variant="success"
                  size="sm"
              >
                  <CheckCircleIcon class="w-4 h-4" />
                  <span>Approve</span>
              </AppButton>

              <AppButton
                  v-if="req.status === 'Pending Division Chief Approval'"
                  @click="rejectRequest(req.id)"
                  variant="danger"
                  size="sm"
              >
                  <XCircleIcon class="w-4 h-4" />
                  <span>Reject</span>
              </AppButton>

              <AppIconButton
                  label="View details"
                  @click="openModal(req)"
              >
                  <EyeIcon class="w-4 h-4" />
              </AppIconButton>
            </div>
          </td>
        </tr>

        <template #empty>
          <EmptyState title="No requests found" />
        </template>

        <template #footer>
          <PaginationControl
            :links="props.requests?.links"
            :current-page="currentPage"
            :total-pages="totalPages"
            :total="props.requests?.total ?? 0"
            @prev="goToPage(currentPage - 1)"
            @next="goToPage(currentPage + 1)"
            @page="goToPage"
          />
        </template>
      </AppTable>

      <!-- Modal -->
      <AppModal :show="showModal" :title="selectedRequest?.title" @close="closeModal">
        <div class="space-y-2 text-sm text-slate-700">
          <p><strong>ITJR #:</strong> {{ selectedRequest?.itjr_no ?? selectedRequest?.id }}</p>
          <p><strong>Submitted By:</strong> {{ selectedRequest?.user?.name ?? '—' }}</p>
          <p><strong>Status:</strong> {{ selectedRequest?.status }}</p>
          <p><strong>Description:</strong> {{ selectedRequest?.description ?? '—' }}</p>
          <p><strong>Created At:</strong> {{ selectedRequest?.created_at }}</p>
          <p><strong>Updated At:</strong> {{ selectedRequest?.updated_at }}</p>
        </div>
      </AppModal>
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
