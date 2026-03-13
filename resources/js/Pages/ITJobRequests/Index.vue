<script setup>
import { ref, computed, watch } from "vue"
import { Head, usePage, router } from "@inertiajs/vue3"
import Swal from 'sweetalert2'
import AdminLayout from "@/Layouts/AdminLayout.vue"
import {
  EyeIcon,
  PencilSquareIcon,
  PrinterIcon,
  TrashIcon,
  ArrowDownTrayIcon
} from "@heroicons/vue/24/outline"
import { useJobRequests } from "@/Composables/useJobRequests.js"

// Props from backend — requests is now a paginator object
const props = defineProps({
  requests: Object,
  filters: Object,
  categories: Array,
  divisionChiefs: Array,
  misPersonnel: Array,
  ictEquipment: Array,
})

// Composable: modal/form/submit logic (pass current page's data)
const {
  showModal,
  modalMode,
  selectedRequest,
  form,
  formatDate,
  openModal,
  closeModal,
  submitRequest,
  viewRequest,
  misAssessment,
  deleteRequest,
  exportCSV,
  printTable,
} = useJobRequests(props.requests?.data ?? [])

// Auth info
const page = usePage()
const currentUser = page.props.auth?.user ?? null
const userRole = currentUser?.role?.name ?? null

function openMISAssessment(request) {
  misAssessment(request)
}

// Server-side filters
const search         = ref(props.filters?.search   ?? '')
const filterCategory = ref(props.filters?.category ?? '')
const filterStatus   = ref(props.filters?.status   ?? '')
const isLoading      = ref(false)
let debounceTimer    = null

const buildParams = (page = undefined) => ({
  search:   search.value   || undefined,
  category: filterCategory.value || undefined,
  status:   filterStatus.value   || undefined,
  page:     page            || undefined,
})

const applyFilters = (immediate = true) => {
  clearTimeout(debounceTimer)
  const go = () => {
    isLoading.value = true
    router.get(route('jobrequests.index'), buildParams(), {
      preserveState: true,
      replace: true,
      only: ['requests', 'filters'],
      onFinish: () => { isLoading.value = false },
    })
  }
  if (immediate) go()
  else debounceTimer = setTimeout(go, 400)
}

// Auto-search while typing (debounced); dropdowns fire immediately
watch(search, () => applyFilters(false))
watch(filterCategory, () => applyFilters(true))
watch(filterStatus,   () => applyFilters(true))

const goToPage = (pageNum) => {
  isLoading.value = true
  router.get(route('jobrequests.index'), buildParams(pageNum), {
    preserveState: true,
    replace: true,
    only: ['requests', 'filters'],
    onFinish: () => { isLoading.value = false },
  })
}

// Current page data from paginator
const visibleRequests = computed(() => props.requests?.data ?? [])
const currentPage = computed(() => props.requests?.current_page ?? 1)
const totalPages = computed(() => props.requests?.last_page ?? 1)

const hasPendingConfirmation = computed(() => {
  if (!currentUser || userRole === 'Administrator') return false
  return visibleRequests.value.some(req => req.status === 'Acted by MIS')
})

/* ---------------------------------------
   ✅ Rating Modal Logic: Confirm Completion & Rate Service
--------------------------------------- */

// State for rating modal
const showRatingModal = ref(false)
const requestToRate = ref(null)
const rating = ref(0)
const ratingRemarks = ref("")
const isRatingSubmitting = ref(false)

// Open rating modal
function openRatingModal(request) {
  requestToRate.value = request
  rating.value = 0
  ratingRemarks.value = ""
  showRatingModal.value = true
}

// Submit rating and confirm completion
const submitRating = async () => {
  if (!requestToRate.value) return

  // Prevent submitting without selecting a rating
  if (rating.value < 1) {
    await Swal.fire("Error", "Please select a rating before submitting.", "error")
    return
  }

  isRatingSubmitting.value = true

  router.post(
    `/it-job-requests/${requestToRate.value.id}/confirm`,
    {
      rating: rating.value,
      remarks: ratingRemarks.value,
    },
    {
      preserveScroll: true,
      onSuccess: async () => {
        requestToRate.value.status = "Request Completed"
        showRatingModal.value = false
        requestToRate.value = null
        rating.value = 0
        ratingRemarks.value = ""
        await Swal.fire("Success", "Request confirmed and rated successfully!", "success")
        window.location.reload()
      },
      onError: async (errors) => {
        console.error(errors)
        await Swal.fire("Error", "Failed to submit rating. Please try again.", "error")
      },
      onFinish: () => {
        isRatingSubmitting.value = false
      },
    }
  )
}
const handleNewRequest = async () => {
  if (hasPendingConfirmation.value) {
    await Swal.fire({
      icon: 'warning',
      title: 'Action Required',
      text: 'You still have a request that was already acted by MIS. Please confirm completion and rate the service before creating a new request.',
      confirmButtonText: 'OK',
    })
    return
  }

  openModal('create')
}

</script>


<template>
  <Head title="IT Job Requests" />
  <AdminLayout title="IT Job Requests">
    <div>
      <!-- Header -->
      <div class="flex items-center justify-between mb-4 gap-2">
        <h1 class="text-xl md:text-2xl font-bold text-gray-800 truncate">IT Job Requests</h1>
        <button
          @click="handleNewRequest"
          class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow"
        >
          + New Request
        </button>

      </div>

      <!-- Search & Actions -->
      <div class="bg-white rounded-xl shadow p-4 mb-4">
        <div class="flex justify-between items-center mb-4">
          <div class="flex items-center gap-2 w-full sm:w-auto">
            <div class="relative flex-1 sm:w-64">
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
          </div>
          <!-- Dropdown Filters -->
          <div class="flex items-center gap-2 flex-wrap">
            <select
              v-model="filterCategory"
              class="rounded-lg border-gray-300 shadow-sm text-sm focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="">All Categories</option>
              <option v-for="cat in props.categories" :key="cat.id" :value="cat.name">{{ cat.name }}</option>
            </select>
            <select
              v-model="filterStatus"
              class="rounded-lg border-gray-300 shadow-sm text-sm focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="">All Statuses</option>
              <option value="Pending Division Chief Approval">Pending DC Approval</option>
              <option value="Pending OCD Approval">Pending OCD Approval</option>
              <option value="In Progress">In Progress</option>
              <option value="MIS Assessed the Request">MIS Assessed</option>
              <option value="Acted by MIS">Acted by MIS</option>
              <option value="Request Completed">Completed</option>
              <option value="Rejected by Division Chief">Rejected by DC</option>
              <option value="Rejected by OCD">Rejected by OCD</option>
            </select>
          </div>
          <div class="flex gap-2">
            <button @click="exportCSV">
              <ArrowDownTrayIcon class="w-5 h-5 text-blue-600" />
            </button>
            <button @click="printTable">
              <PrinterIcon class="w-5 h-5 text-blue-600" />
            </button>
          </div>
        </div>

        <!-- Desktop table -->
<div class="hidden sm:block overflow-x-auto">
  <table class="w-full table-fixed border border-gray-200">
    <thead class="bg-gray-100 text-gray-700 uppercase text-sm">
      <tr>
        <th class="px-4 py-3 text-left">ITJR #</th>
        <th class="px-4 py-3 text-left">Title</th>
        <th class="px-4 py-3 text-left">Category</th>
        <th v-if="userRole === 'Administrator'" class="px-4 py-3 text-left">Submitted By</th>
        <th class="px-4 py-3 text-left">Date Filed</th>
        <th class="px-4 py-3 text-left">Status</th>
        <th class="px-4 py-3 text-center">Action</th>
      </tr>
    </thead>

    <tbody class="divide-y divide-gray-200 text-sm">
      <tr v-for="req in visibleRequests" :key="req.id">
        <td class="px-4 py-3">{{ req.itjr_no }}</td>
        <td class="px-4 py-3">{{ req.title }}</td>
        <td class="px-4 py-3">{{ req.category }}</td>

        <td v-if="userRole === 'Administrator'" class="px-4 py-3">
          {{ req.user?.name ?? '—' }}
        </td>

        <td class="px-4 py-3">{{ formatDate(req.created_at) }}</td>

        <td class="px-4 py-3">
          <span
            class="px-3 py-1 text-xs rounded-full font-semibold"
            :class="{
              'bg-yellow-100 text-yellow-700': req.status==='Pending Division Chief Approval',
              'bg-orange-100 text-orange-700': req.status==='MIS Assessed the Request',
              'bg-violet-100 text-violet-700': req.status==='Pending OCD Approval',
              'bg-blue-100 text-blue-700': req.status==='In Progress',
              'bg-green-100 text-green-700': req.status==='Acted by MIS',
              'bg-green-500 text-white': req.status==='Request Completed'
            }"
          >
            {{ req.status }}
          </span>
        </td>

        <td class="px-4 py-3 text-center">
          <div class="flex justify-center gap-2">
            <button @click="viewRequest(req)" class="p-2 bg-blue-100 rounded hover:bg-blue-200">
              <EyeIcon class="w-5 h-5 text-blue-700"/>
            </button>

            <template v-if="userRole === 'Administrator'">
              <button @click="openMISAssessment(req)" class="p-2 bg-yellow-100 rounded hover:bg-yellow-200">
                <PencilSquareIcon class="w-5 h-5 text-yellow-700"/>
              </button>
              <button @click="deleteRequest(req)" class="p-2 bg-red-100 rounded hover:bg-red-200">
                <TrashIcon class="w-5 h-5 text-red-700"/>
              </button>
            </template>

            <button
              v-if="req.status === 'Acted by MIS' && userRole !== 'Administrator'"
              @click="openRatingModal(req)"
              class="px-3 py-1 text-xs bg-green-600 text-white rounded hover:bg-green-700"
            >
              Confirm & Rate
            </button>
          </div>
        </td>
      </tr>

      <tr v-if="visibleRequests.length === 0">
        <td :colspan="userRole === 'Administrator' ? 7 : 6" class="px-4 py-6 text-center text-gray-500">
          No requests found.
        </td>
      </tr>
    </tbody>
  </table>
</div>
<!-- Mobile cards -->
<div class="sm:hidden space-y-3">
  <div
    v-for="req in visibleRequests"
    :key="req.id"
    class="border rounded-lg p-4 bg-white shadow-sm"
  >
    <div class="flex justify-between items-start">
      <div>
        <div class="text-xs text-gray-500">ITJR #{{ req.itjr_no }}</div>
        <div class="font-semibold text-gray-800">{{ req.title }}</div>
        <div class="text-sm text-gray-600">{{ req.category }}</div>
      </div>

      <span
        class="px-2 py-1 text-xs rounded-full font-semibold"
        :class="{
          'bg-yellow-100 text-yellow-700': req.status==='Pending Division Chief Approval',
          'bg-orange-100 text-orange-700': req.status==='MIS Assessed the Request',
          'bg-violet-100 text-violet-700': req.status==='Pending OCD Approval',
          'bg-blue-100 text-blue-700': req.status==='In Progress',
          'bg-green-100 text-green-700': req.status==='Acted by MIS',
          'bg-green-500 text-white': req.status==='Request Completed'
        }"
      >
        {{ req.status }}
      </span>
    </div>

    <div class="mt-2 text-sm text-gray-700 space-y-1">
      <div><strong>Date:</strong> {{ formatDate(req.created_at) }}</div>
      <div v-if="userRole === 'Administrator'">
        <strong>Submitted By:</strong> {{ req.user?.name ?? '—' }}
      </div>
    </div>

    <div class="mt-3 flex gap-2 flex-wrap">
      <button
        @click="viewRequest(req)"
        class="flex-1 inline-flex items-center justify-center gap-2 px-3 py-2 bg-blue-600 text-white rounded-md"
      >
        <EyeIcon class="w-4 h-4"/> View
      </button>

      <template v-if="userRole === 'Administrator'">
        <button
          @click="openMISAssessment(req)"
          class="px-3 py-2 bg-yellow-100 text-yellow-700 rounded-md"
        >
          <PencilSquareIcon class="w-4 h-4"/>
        </button>

        <button
          @click="deleteRequest(req)"
          class="px-3 py-2 bg-red-100 text-red-700 rounded-md"
        >
          <TrashIcon class="w-4 h-4"/>
        </button>
      </template>

      <button
        v-if="req.status === 'Acted by MIS' && userRole !== 'Administrator'"
        @click="openRatingModal(req)"
        class="w-full px-3 py-2 bg-green-600 text-white rounded-md"
      >
        Confirm & Rate
      </button>
    </div>
  </div>

  <div v-if="visibleRequests.length === 0" class="text-center text-gray-500 py-6">
    No requests found.
  </div>
</div>


        <!-- Pagination -->
        <div class="flex justify-center items-center gap-2 mt-4">
          <button @click="goToPage(currentPage - 1)" :disabled="currentPage === 1 || isLoading" class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50">Prev</button>
          <span>Page {{ currentPage }} of {{ totalPages }}</span>
          <button @click="goToPage(currentPage + 1)" :disabled="currentPage === totalPages || isLoading" class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50">Next</button>
        </div>
      </div>

      <!-- Modal -->
      <div v-show="showModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50 transition-opacity">
        <div
          class="bg-white w-full max-w-2xl rounded-xl shadow-lg
                 max-h-[90vh] overflow-y-auto relative p-4 sm:p-6"
        >

          <button class="absolute top-3 right-3 text-gray-500 hover:text-gray-800" @click="closeModal">✕</button>

          <h2 class="text-xl font-semibold mb-4">
            {{ modalMode==='create' ? 'New IT Job Request' : modalMode==='mis-assessment' ? 'MIS Assessment' : 'View Request' }}
          </h2>

          <!-- VIEW MODE -->
<div
  v-if="modalMode === 'view' && selectedRequest"
  class="space-y-6"
>
  <!-- 📌 Request Summary Card -->
  <div class="bg-white rounded-xl border shadow-sm p-6">
    <div class="flex items-start justify-between mb-4">
      <div>
        <h2 class="text-xl font-semibold text-gray-800">
          {{ selectedRequest.title }}
        </h2>
        <p class="text-sm text-gray-500">
          Submitted by {{ selectedRequest.user?.name ?? '—' }}
        </p>
      </div>

      <!-- Status Badge -->
      <span
        class="px-3 py-1 text-xs font-medium rounded-full"
        :class="{
          
          'bg-yellow-100 text-yellow-700': selectedRequest.status==='Pending Division Chief Approval',
          'bg-orange-100 text-orange-700': selectedRequest.status==='MIS Assessed the Request',
          'bg-violet-100 text-violet-700': selectedRequest.status==='Pending OCD Approval',
          'bg-blue-100 text-blue-700': selectedRequest.status==='In Progress',
          'bg-green-100 text-green-700': selectedRequest.status==='Acted by MIS',
          'bg-green-500 text-white': selectedRequest.status==='Request Completed'
        }"
      >
        {{ selectedRequest.status ?? 'Pending' }}
      </span>
    </div>

    <!-- 📋 Details Grid -->
    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 text-sm">
      <div>
        <dt class="text-gray-500">Category</dt>
        <dd class="font-medium text-gray-800">
          {{ selectedRequest.category }}
        </dd>
      </div>

      <div>
        <dt class="text-gray-500">Assigned Personnel</dt>
        <dd class="font-medium text-gray-800">
          {{ selectedRequest.assigned_personnel ?? 'Not Assigned' }}
        </dd>
      </div>

      <div>
        <dt class="text-gray-500">Attended By</dt>
        <dd class="font-medium text-gray-800">
          {{ selectedRequest.attendedby ?? '—' }}
        </dd>
      </div>

      <div>
        <dt class="text-gray-500">Date Submitted</dt>
        <dd class="font-medium text-gray-800">
          {{ formatDate(selectedRequest.created_at) }}
        </dd>
      </div>

      <!-- Full-width description -->
      <div class="sm:col-span-2">
        <dt class="text-gray-500">Description</dt>
        <dd class="mt-1 text-gray-700 leading-relaxed whitespace-pre-line">
          {{ selectedRequest.description }}
        </dd>
      </div>
    </dl>
  </div>

  <!-- 🧭 Progress Tracking -->
  <div
    v-if="selectedRequest.tracking_logs?.length"
    class="bg-white rounded-xl border shadow-sm p-6"
  >
    <h3 class="text-lg font-semibold text-gray-800 mb-5">
      Progress Tracking
    </h3>

    <!-- Timeline -->
    <div class="relative max-h-80 overflow-y-auto pl-6 border-l border-gray-200 custom-scroll">
      <div
        v-for="log in selectedRequest.tracking_logs"
        :key="log.id"
        class="relative mb-6"
      >
        <!-- Timeline Dot -->
        <span
          class="absolute -left-[9px] top-1 h-4 w-4 rounded-full bg-blue-500 ring-4 ring-white"
        ></span>

        <!-- Log Card -->
        <div class="bg-gray-50 rounded-lg border p-4 shadow-sm">
          <div class="flex justify-between items-start">
            <p class="text-sm font-semibold text-orange-600">
              {{ log.status }}
            </p>
            <span class="text-xs text-gray-400">
              {{ formatDate(log.created_at) }}
            </span>
          </div>

          <p class="mt-2 text-sm text-gray-600 whitespace-pre-line">
            {{ log.remarks ?? 'No remarks provided.' }}
          </p>

          <p
            v-if="log.it_job_request?.attendedby"
            class="mt-2 text-xs text-gray-500"
          >
            Attended by:
            <span class="font-medium text-gray-700">
              {{ log.it_job_request.attendedby }}
            </span>
          </p>
        </div>
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
                  v-model="form.divisionchief_id"
                  class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                  required
                >
                  <option value="">-- Select Division Chief --</option>
                  <option
                    v-for="chief in props.divisionChiefs"
                    :key="chief.id"
                    :value="chief.id"
                  >
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
                  <option v-for="mis in props.misPersonnel" :key="mis.id" :value="mis.id">
                    {{ mis.name }}
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
              <!-- ✅ ICT Equipment (ONLY for Hardware Repair) -->
              <div v-if="selectedRequest?.category?.toLowerCase().includes('hardware')">
                <label class="block text-sm font-medium text-gray-700">
                  Tag ICT Equipment
                </label>

                <select
                  v-model="form.ict_equipment_id"
                  class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                >
                  <option value="">-- Select Equipment --</option>
                  <option
                    v-for="eq in props.ictEquipment"
                    :key="eq.id"
                    :value="eq.id"
                  >
                    {{ eq.room?.name }} - {{ eq.owner?.name }} - {{ eq.description }} - ({{ eq.serial_no }})
                  </option>
                </select>
              </div>

            </template>


            <div class="flex justify-end space-x-3 pt-4">
              <button type="button" @click="closeModal" class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400">Cancel</button>
              <button type="submit" :disabled="form.processing" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-60 disabled:cursor-not-allowed min-w-[80px]">
                <span v-if="form.processing">Saving…</span>
                <span v-else>Save</span>
              </button>
            </div>

          </form>
        </div>
      </div>

      <!-- Rating Modal -->
    <div v-show="showRatingModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50 transition-opacity">
      <div class="bg-white rounded-xl shadow-lg w-full max-w-md p-6 relative">
        <button class="absolute top-3 right-3 text-gray-500 hover:text-gray-800" @click="showRatingModal = false">✕</button>

        <h2 class="text-xl font-semibold mb-4">Confirm Completion & Rate Service</h2>

        <p class="mb-3">Request: <strong>{{ requestToRate?.title }}</strong></p>

        <!-- Star Rating -->
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">Rating</label>
          <div class="flex space-x-1">
            <template v-for="star in 5" :key="star">
              <button
                type="button"
                @click="rating = star"
                class="text-2xl focus:outline-none"
              >
                <span
                  class="cursor-pointer"
                  :class="{
                    'text-yellow-400': star <= rating,
                    'text-gray-300': star > rating
                  }"
                >★</span>
              </button>
            </template>
          </div>
        </div>

        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">Remarks (optional)</label>
          <textarea v-model="ratingRemarks" rows="3" class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"></textarea>
        </div>

        <div class="flex justify-end gap-3">
          <button @click="showRatingModal=false" :disabled="isRatingSubmitting" class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400 disabled:opacity-60">Cancel</button>
          <button @click="submitRating" :disabled="isRatingSubmitting" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 disabled:opacity-60 disabled:cursor-not-allowed min-w-[90px]">
            <span v-if="isRatingSubmitting">Submitting…</span>
            <span v-else>Submit</span>
          </button>
        </div>
      </div>
    </div>

    </div>
  </AdminLayout>
</template>
