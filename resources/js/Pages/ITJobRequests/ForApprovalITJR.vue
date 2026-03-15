<script setup>
import { ref, computed, watch } from "vue"
import { Head, router } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import { CheckCircleIcon, XCircleIcon, EyeIcon, FunnelIcon } from "@heroicons/vue/24/outline"
import Swal from "sweetalert2"
import "sweetalert2/dist/sweetalert2.min.css"

const props = defineProps({
  requests: Object,
  filters: Object,
  categories: Array,
})

// State
const showModal = ref(false)
const selectedRequest = ref(null)

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
    router.get(route('job-requests.for-approval'), buildParams(), {
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
  router.get(route('job-requests.for-approval'), buildParams(pageNum), {
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
const approveRequest = async (id) => {
  const result = await Swal.fire({
    title: "Are you sure?",
    text: "Do you want to approve this request?",
    icon: "question",
    showCancelButton: true,
    confirmButtonText: "Yes, approve it!",
    cancelButtonText: "Cancel",
    reverseButtons: true
  })

  if (result.isConfirmed) {
    Swal.fire({ title: 'Approving request...', allowOutsideClick: false, allowEscapeKey: false, showConfirmButton: false, didOpen: () => { Swal.showLoading() } })
    router.post(route("job-requests.division-chief-action", id), { action: "approve" }, {
      onSuccess: () => Swal.fire("Approved!", "The request has been approved.", "success"),
    })
  }
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
      <!-- Header -->
      <div class="flex items-center justify-between mb-4 gap-2">
        <h1 class="text-xl md:text-2xl font-bold text-gray-800 truncate">For Approval - IT Job Requests</h1>
      </div>

      <!-- Card: Search + Filter + Table -->
      <div class="bg-white rounded-xl shadow p-4">
        <!-- Search + Filters -->
        <div class="mb-4 flex flex-wrap items-center gap-2">
          <div class="relative flex-1 sm:w-64 sm:flex-none">
            <input
              v-model="search"
              type="text"
              placeholder="Search requests..."
              @keydown.enter.prevent="applyFilters(true)"
              class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
            />
            <span v-if="isLoading" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">⏳</span>
          </div>
          <button
            @click="applyFilters(true)"
            :disabled="isLoading"
            class="px-3 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50 whitespace-nowrap"
          >
            Search
          </button>
          <select
            v-model="filterCategory"
            class="rounded-lg border-gray-300 shadow-sm text-sm focus:ring-blue-500 focus:border-blue-500"
          >
            <option value="">All Categories</option>
            <option v-for="cat in props.categories" :key="cat.id" :value="cat.name">{{ cat.name }}</option>
          </select>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
          <table class="min-w-full border border-gray-200">
            <thead class="bg-gray-100 text-gray-700 uppercase text-sm">
              <tr>
                <th class="px-4 py-3 text-left">ITJR #</th>
                <th class="px-4 py-3 text-left">Title</th>
                <th class="px-4 py-3 text-left">Submitted By</th>
                <th class="px-4 py-3 text-left">Status</th>
                <th class="px-4 py-3 text-center">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 text-sm">
              <tr
                v-for="req in filteredRequests"
                :key="req.id"
                class="hover:bg-gray-50"
              >
                <td class="px-4 py-3">{{ req.itjr_no ?? req.id }}</td>
                <td class="px-4 py-3">{{ req.title }}</td>
                <td class="px-4 py-3">{{ req.user?.name ?? "—" }}</td>
                <td class="px-4 py-3">
                  <span
                    class="px-3 py-1 text-xs rounded-full"
                    :class="{
                      'bg-yellow-100 text-yellow-700': req.status.includes('Pending'),
                      'bg-green-100 text-green-700': req.status.includes('Approved'),
                      'bg-red-100 text-red-700': req.status.includes('Rejected')
                    }"
                  >
                    {{ req.status }}
                  </span>
                </td>
                <td class="px-4 py-3 text-center">
                  <div class="flex items-center gap-2 justify-center">
                    <!-- Approve Button -->
                    <button
                        v-if="req.status === 'Pending Division Chief Approval'"
                        @click="approveRequest(req.id)"
                        class="flex items-center gap-1 px-3 py-1 rounded-full bg-green-100 hover:bg-green-200 text-green-700 font-medium"
                    >
                        <CheckCircleIcon class="w-5 h-5" />
                        <span>Approve</span>
                    </button>

                    <!-- Reject Button -->
                    <button
                        v-if="req.status === 'Pending Division Chief Approval'"
                        @click="rejectRequest(req.id)"
                        class="flex items-center gap-1 px-3 py-1 rounded-full bg-red-100 hover:bg-red-200 text-red-700 font-medium"
                    >
                        <XCircleIcon class="w-5 h-5" />
                        <span>Reject</span>
                    </button>

                    <!-- View Button -->
                    <button
                        @click="openModal(req)"
                        class="flex items-center gap-1 px-3 py-1 rounded-full bg-blue-100 hover:bg-blue-200 text-blue-700 font-medium"
                    >
                        <EyeIcon class="w-5 h-5" />
                        <span>View</span>
                    </button>
                    </div>


                </td>
              </tr>

              <tr v-if="filteredRequests.length === 0">
                <td colspan="5" class="px-4 py-6 text-center text-gray-500">
                  No requests found.
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="totalPages > 1" class="flex justify-center items-center gap-2 mt-4">
          <button @click="goToPage(currentPage - 1)" :disabled="currentPage === 1 || isLoading" class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50">Prev</button>
          <span>Page {{ currentPage }} of {{ totalPages }}</span>
          <button @click="goToPage(currentPage + 1)" :disabled="currentPage === totalPages || isLoading" class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50">Next</button>
        </div>
      </div>

      <!-- Modal -->
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
        <div class="bg-white rounded-xl w-2/3 max-w-3xl shadow-lg p-6 relative">
          <button @click="closeModal" class="absolute top-3 right-3 text-gray-500 hover:text-gray-700">✖</button>
          <h2 class="text-xl font-bold mb-4">{{ selectedRequest.title }}</h2>
          <div class="space-y-2">
            <p><strong>ITJR #:</strong> {{ selectedRequest.itjr_no ?? selectedRequest.id }}</p>
            <p><strong>Submitted By:</strong> {{ selectedRequest.user?.name ?? '—' }}</p>
            <p><strong>Status:</strong> {{ selectedRequest.status }}</p>
            <p><strong>Description:</strong> {{ selectedRequest.description ?? '—' }}</p>
            <p><strong>Created At:</strong> {{ selectedRequest.created_at }}</p>
            <p><strong>Updated At:</strong> {{ selectedRequest.updated_at }}</p>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
