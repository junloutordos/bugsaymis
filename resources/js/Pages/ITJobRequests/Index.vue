<script setup>
import { computed } from "vue"
import { Head, usePage } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import {
  EyeIcon,
  PencilSquareIcon,
  PrinterIcon,
  TrashIcon,
  ArrowDownTrayIcon
} from "@heroicons/vue/24/outline"
import { useJobRequests } from "@/Composables/useJobRequests.js"

// Props from backend
const props = defineProps({ 
  requests: Array,
  categories: Array,
  divisionChiefs: Array,   
  administrators: Array    })

// Composable: all request logic
const {
  requestsList,
  showModal,
  modalMode,
  selectedRequest,
  searchQuery,
  currentPage,
  totalPages,
  filteredRequests,
  form,
  formatDate,
  openModal,
  closeModal,
  submitRequest,
  viewRequest,
  misAssessment,
  deleteRequest,
  sortBy,
  exportCSV,
  printTable,
} = useJobRequests(props.requests)

// Auth info
const page = usePage()
const userRole = page.props.auth?.user?.role?.name ?? null

// Open MIS Assessment modal
function openMISAssessment(request) {
  misAssessment(request)
}
// Ensure frontend filtering too for administrators
const visibleRequests = computed(() => {
  if (userRole === "Administrator") {
    return filteredRequests.value
  }
  return filteredRequests.value.filter(req => req.user_id === user?.id)
})
</script>

<template>
  <Head title="IT Job Requests" />
  <AdminLayout title="IT Job Requests">
    <div class="p-6">
      <!-- Header -->
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">IT Job Requests</h1>
        <button
          @click="openModal('create')"
          class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow"
        >
          + New Request
        </button>
      </div>

      <!-- Search & Actions -->
      <div class="bg-white rounded-xl shadow p-4 mb-4">
        <div class="flex justify-between items-center mb-4">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search requests..."
            class="w-1/3 rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
          />
          <div class="flex gap-2">
            <button @click="exportCSV">
              <ArrowDownTrayIcon class="w-5 h-5 text-blue-600" />
            </button>
            <button @click="printTable">
              <PrinterIcon class="w-5 h-5 text-blue-600" />
            </button>
          </div>
        </div>

        <!-- Requests Table -->
        <div class="overflow-x-auto">
          <table class="min-w-full border border-gray-200">
            <thead class="bg-gray-100 text-gray-700 uppercase text-sm">
              <tr>
                <th class="px-4 py-3 text-left cursor-pointer" @click="sortBy('id')">ITJR #</th>
                <th class="px-4 py-3 text-left cursor-pointer" @click="sortBy('title')">Title</th>
                <th class="px-4 py-3 text-left cursor-pointer" @click="sortBy('category')">Category</th>
                <th v-if="userRole==='Administrator'" class="px-4 py-3 text-left">Submitted By</th>
                <th class="px-4 py-3 text-left cursor-pointer" @click="sortBy('created_at')">Date Filed</th>
                <th class="px-4 py-3 text-left">Status</th>
                <th class="px-4 py-3 text-center">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 text-sm">
              <tr v-for="req in visibleRequests" :key="req.id" class="hover:bg-gray-50">
                <td class="px-4 py-3">{{ req.itjr_no }}</td>
                <td class="px-4 py-3">{{ req.title }}</td>
                <td class="px-4 py-3">{{ req.category }}</td>
                <td v-if="userRole==='Administrator'" class="px-4 py-3">{{ req.user?.name ?? '—' }}</td>
                <td class="px-4 py-3">{{ formatDate(req.created_at) }}</td>
                <td class="px-4 py-3">
                  <span
                    class="px-3 py-1 text-xs rounded-full"
                    :class="{
                      'bg-yellow-100 text-yellow-700': req.status==='Pending Division Chief Approval',
                      'bg-orange-100 text-orange-700': req.status==='MIS Assessed the Request',
                      'bg-violet-100 text-violet-700': req.status==='Pending OCD Approval',
                      'bg-blue-100 text-blue-700': req.status==='In Progress',
                      'bg-green-100 text-green-700': req.status==='Acted by MIS',
                      'bg-red-100 text-red-700': req.status==='Request Completed'
                    }"
                  >
                    {{ req.status }}
                  </span>
                </td>
                <td class="px-4 py-3 text-center">
                  <div class="flex justify-center gap-1 items-center">
                    <button @click="viewRequest(req)" class="p-1 hover:bg-gray-100 rounded">
                      <EyeIcon class="w-5 h-5 text-blue-600"/>
                    </button>
                    <template v-if="userRole==='Administrator'">
                      <button @click="openMISAssessment(req)" class="p-1 hover:bg-gray-100 rounded">
                        <PencilSquareIcon class="w-5 h-5 text-yellow-600"/>
                      </button>
                      <button @click="deleteRequest(req)" class="p-1 hover:bg-gray-100 rounded">
                        <TrashIcon class="w-5 h-5 text-red-600"/>
                      </button>
                    </template>
                  </div>
                </td>
              </tr>
              <tr v-if="visibleRequests.length===0">
                <td :colspan="userRole==='Administrator'?7:6" class="px-4 py-6 text-center text-gray-500">
                  No requests found.
                </td>
              </tr>

            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="flex justify-center items-center gap-2 mt-4">
          <button @click="currentPage--" :disabled="currentPage===1" class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50">Prev</button>
          <span>Page {{ currentPage }} of {{ totalPages }}</span>
          <button @click="currentPage++" :disabled="currentPage===totalPages" class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50">Next</button>
        </div>
      </div>

      <!-- Modal -->
      <div v-show="showModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50 transition-opacity">
        <div class="bg-white rounded-xl shadow-lg w-full max-w-md p-6 relative">

          <button class="absolute top-3 right-3 text-gray-500 hover:text-gray-800" @click="closeModal">✕</button>

          <h2 class="text-xl font-semibold mb-4">
            {{ modalMode==='create' ? 'New IT Job Request' : modalMode==='mis-assessment' ? 'MIS Assessment' : 'View Request' }}
          </h2>

          <!-- VIEW MODE -->
          <div v-if="modalMode==='view' && selectedRequest" class="space-y-2">
            <p>Title: <strong>{{ selectedRequest.title }}</strong></p>
            <p>Category: <strong>{{ selectedRequest.category }}</strong></p>
            <p>Description: <strong>{{ selectedRequest.description }}</strong></p>
            <p>Division Approval:<strong>{{ selectedRequest.divisionchief }}</strong></p>
            <p>Assign Personnel:<strong>{{ selectedRequest.assignedto }} </strong></p>
            <p>Submitted By: <strong>{{ selectedRequest.user?.name ?? '—' }}</strong></p>
            <hr></hr>
            <!-- Tracking Logs -->
            <div v-if="selectedRequest.tracking_logs?.length" class="mt-6">
              <h3 class="text-lg font-semibold mb-4">Progress Tracking</h3>
              
              <!-- Scrollable container -->
              <div class="relative border-l border-gray-300 ml-3 max-h-80 overflow-y-auto pr-2 custom-scroll">
                <div 
                  v-for="log in selectedRequest.tracking_logs" 
                  :key="log.id" 
                  class="mb-6 ml-6"
                >
                  <span 
                    class="absolute -left-1.5 flex h-3 w-3 items-center justify-center rounded-full border border-white bg-blue-500"
                  ></span>
                  <div class="p-4 bg-gray-50 rounded-lg shadow-sm border">
                    <p class="text-sm font-medium text-gray-800">{{ log.status }}</p>
                    <p class="text-sm text-gray-600 whitespace-pre-line">
                      {{ log.remarks ?? 'No remarks' }}
                    </p>
                    <p class="text-xs text-gray-400 mt-1">Updated: {{ formatDate(log.created_at) }}</p>
                  </div>
                  <!-- ✅ Show attended_by if present -->
                  <p v-if="log.it_job_request?.attendedby" class="text-xs text-gray-500 mt-1">
                    Attended by: <span class="font-medium">{{ log.it_job_request.attendedby }}</span>
                  </p>
                </div>
              </div>
            </div>

          </div>

          <!-- CREATE / MIS ASSESSMENT FORM -->
          <form v-else @submit.prevent="submitRequest" class="space-y-4">

            <!-- Fields common to CREATE -->
            <template v-if="modalMode==='create'">
              <div>
                <label class="block text-sm font-medium text-gray-700">Request</label>
                <input v-model="form.title" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" required />
              </div>
              <!-- Category -->
              <div>
                  <label class="block text-sm font-medium text-gray-700">Category</label>
                  <select
                  v-model="form.category"
                  class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                  required
                  >
                  <option value="">-- Select Request Type --</option>
                  <option v-for="cat in props.categories" :key="cat.id" :value="cat.name">
                    {{ cat.name }}
                  </option>

                  </select>
              </div>

              <!-- Description -->
              <div>
                  <label class="block text-sm font-medium text-gray-700">Description</label>
                  <textarea
                  v-model="form.description"
                  rows="4"
                  class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                  required
                  ></textarea>
              </div>

              <!-- Division Approval -->
              <div>
                <label class="block text-sm font-medium text-gray-700">Division Approval</label>
                <select
                  v-model="form.divisionchief"
                  class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                  required
                >
                  <option value="">-- Select Division Chief --</option>
                  <option v-for="chief in props.divisionChiefs" :key="chief.id" :value="chief.name">
                    {{ chief.name }}
                  </option>
                </select>
              </div>

              <!-- Assign Personnel -->
              <div>
                <label class="block text-sm font-medium text-gray-700">Assign Personnel</label>
                <select
                  v-model="form.assignedto"
                  class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                  required
                >
                  <option value="">-- Select MIS Personnel --</option>
                  <option v-for="admin in props.administrators" :key="admin.id" :value="admin.name">
                    {{ admin.name }}
                  </option>
                </select>
              </div>

            </template>

            <!-- MIS Assessment fields -->
            <template v-if="modalMode==='mis-assessment'">
              <div>
                <label class="block text-sm font-medium text-gray-700">Initial Assessment</label>
                <textarea
                  v-model="form.mis_assessment"
                  rows="3"
                  class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                ></textarea>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Expected Date of Completion</label>
                <input
                  v-model="form.expected_completion_date"
                  type="date"
                  class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                  required
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Action Taken</label>
                <textarea
                  v-model="form.action_taken"
                  rows="3"
                  class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                ></textarea>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Date Completed</label>
                <input
                  v-model="form.completed_at"
                  type="date"
                  class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                />
              </div>
            </template>


            <div class="flex justify-end space-x-3 pt-4">
              <button type="button" @click="closeModal" class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400">Cancel</button>
              <button type="submit" :disabled="form.processing" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Save</button>
            </div>

          </form>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
