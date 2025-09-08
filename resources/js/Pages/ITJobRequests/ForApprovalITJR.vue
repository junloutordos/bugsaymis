<script setup>
import { ref, computed } from "vue"
import { Head, router } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import { CheckCircleIcon, XCircleIcon, EyeIcon, FunnelIcon } from "@heroicons/vue/24/outline"
import Swal from "sweetalert2"
import "sweetalert2/dist/sweetalert2.min.css"

const props = defineProps({
  requests: Array
})

// State
const filterStatus = ref("all")
const searchQuery = ref("")
const showModal = ref(false)
const selectedRequest = ref(null)

// Computed: filter + search
const filteredRequests = computed(() => {
  let results = props.requests

  if (filterStatus.value !== "all") {
    results = results.filter(r => r.status === filterStatus.value)
  }

  if (searchQuery.value) {
    results = results.filter(r =>
      r.title.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      r.user?.name?.toLowerCase().includes(searchQuery.value.toLowerCase())
    )
  }

  return results
})

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
    router.post(route("job-requests.division-chief-action", id), { action: "approve" })
    Swal.fire("Approved!", "The request has been approved.", "success")
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
    router.post(route("job-requests.division-chief-action", id), { action: "reject" })
    Swal.fire("Rejected!", "The request has been rejected.", "error")
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
    <div class="p-6">
      <!-- Header -->
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">For Approval - IT Job Requests</h1>
      </div>

      <!-- Card: Search + Filter + Table -->
      <div class="bg-white rounded-xl shadow p-4">
        <!-- Search + Filter -->
        <div class="mb-4 flex items-center justify-between">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search requests..."
            class="w-1/3 rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
          />
          <div class="flex items-center gap-2">
            <FunnelIcon class="w-5 h-5 text-gray-500" />
            <select
              v-model="filterStatus"
              class="rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="all">All</option>
              <option value="Pending Division Chief Approval">Pending</option>
              <option value="Pending OCD Approval">Approved</option>
              <option value="Rejected by Division Chief">Rejected</option>
            </select>
          </div>
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
