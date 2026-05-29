<script setup>
import { ref, computed, watch } from "vue"
import { Head, usePage, router } from "@inertiajs/vue3"
import Swal from 'sweetalert2'
import AdminLayout from "@/Layouts/AdminLayout.vue"
import { badgeBase, statusBadgeClass, priorityBadgeClass } from '@/Composables/useStatusBadge.js'
// ↑ combined import — priorityBadgeClass replaces the old inline PRIORITY_COLORS object
import {
  EyeIcon,
  PencilSquareIcon,
  DocumentArrowDownIcon,
  TrashIcon,
  ArrowDownTrayIcon,
  DocumentChartBarIcon,
  QueueListIcon,
} from "@heroicons/vue/24/outline"
import CsmForm from '@/Components/CsmForm.vue'
import DigitalSignaturePin from '@/Components/DigitalSignaturePin.vue'
import MISAssessmentModal from '@/Components/MISAssessmentModal.vue'
import { useJobRequests } from "@/Composables/useJobRequests.js"

// Props from backend — requests is now a paginator object
const props = defineProps({
  requests: Object,
  filters: Object,
  categories: Array,
  // divisionChiefs and misPersonnel removed — auto-resolved server-side
  ictEquipment: Array,
  isAdmin: { type: Boolean, default: false },
  hasPin: { type: Boolean, default: false },
  signatureUri: { type: String, default: null },
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
  deleteRequest,
} = useJobRequests(props.requests?.data ?? [])

// Auth info — use server-resolved isAdmin prop for reliable role check
const page = usePage()
const currentUser = page.props.auth?.user ?? null
const userRole = props.isAdmin ? 'Administrator' : (currentUser?.role?.name ?? null)

// ── MIS Assessment modal (dedicated component) ────────────────────────────────
const showAssessmentModal = ref(false)
const assessmentRequest   = ref(null)

function openMISAssessment(request) {
  assessmentRequest.value   = request
  showAssessmentModal.value = true
}

// Server-side filters
const search         = ref(props.filters?.search   ?? '')
const filterCategory = ref(props.filters?.category ?? '')
const filterStatus   = ref(props.filters?.status   ?? '')
const perPage        = ref(props.filters?.per_page ?? 15) // Default to 15 items per page
const isLoading      = ref(false)
let debounceTimer    = null

const buildParams = (page = undefined) => ({
  search:   search.value   || undefined,
  category: filterCategory.value || undefined,
  status:   filterStatus.value   || undefined,
  per_page: perPage.value  || undefined,
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

const showAllChecked = ref((props.filters?.per_page ?? 15) >= 1000)

watch(showAllChecked, (val) => {
  perPage.value = val ? 1000 : 15
  applyFilters(true)
})

// Current page data from paginator
const visibleRequests = computed(() => props.requests?.data ?? [])
const currentPage = computed(() => props.requests?.current_page ?? 1)
const totalPages = computed(() => props.requests?.last_page ?? 1)

const hasPendingConfirmation = computed(() => {
  if (!currentUser || userRole === 'Administrator') return false
  return visibleRequests.value.some(req => req.status === 'Acted by MIS')
})

// ── CSM Survey modal ──────────────────────────────────────────────────────────
const showCsmModal    = ref(false)
const requestToRate   = ref(null)

// ── Completion digital signature ──────────────────────────────────────────────
const completionSigShow    = ref(false)
const completionSigLoading = ref(false)
const completionItem       = ref(null)

function openConfirmRating(request) {
  completionItem.value = request
  completionSigShow.value = true
}

async function handleCompletionSigConfirm(pin) {
  completionSigLoading.value = true
  try {
    await axios.post(route('jobrequests.sign-completion', completionItem.value.id), { pin })
  } catch (e) {
    // Non-fatal — proceed with CSM form even if signing fails
  } finally {
    completionSigLoading.value = false
  }
  completionSigShow.value = false
  requestToRate.value = completionItem.value
  showCsmModal.value = true
}

function handleCompletionSigCancel() {
  completionSigShow.value = false
  completionItem.value = null
}
// ── Export PDF modal ──────────────────────────────────────────────────────────
const showExportModal  = ref(false)
const exportDateFrom   = ref('')
const exportDateTo     = ref('')
const exportCategory   = ref('')
const exportScope      = ref('mine')

function openExportModal() {
  exportDateFrom.value  = ''
  exportDateTo.value    = ''
  exportCategory.value  = ''
  exportScope.value     = props.isAdmin ? 'all' : 'mine'
  showExportModal.value = true
}

function runExport() {
  const params = new URLSearchParams()
  if (exportDateFrom.value)  params.set('date_from', exportDateFrom.value)
  if (exportDateTo.value)    params.set('date_to',   exportDateTo.value)
  if (exportCategory.value)  params.set('category',  exportCategory.value)
  params.set('scope', exportScope.value)
  window.open(route('jobrequests.export-pdf') + '?' + params.toString(), '_blank')
  showExportModal.value = false
}

// ── Priority helpers — colours from centralised composable ───────────────────
const PRIORITY_LABELS = { urgent: 'Urgent', high: 'High', normal: 'Normal', low: 'Low' }

const POSTING_CATEGORIES = ['Posting to Website', 'Posting to Social Media']
const isPostingCategory = computed(() => POSTING_CATEGORIES.includes(form.category))

// Clear dependent fields when category changes
watch(() => form.category, (val) => {
  if (val !== 'Technical Assistance on Events') {
    form.event_date = ''
  }
  if (!POSTING_CATEGORIES.includes(val)) {
    form.posting_type = null
  }
})

// Layer 1: min date for the event date picker (today + 3 days)
const minEventDate = computed(() => {
  const d = new Date()
  d.setDate(d.getDate() + 3)
  return d.toISOString().slice(0, 10)
})

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

// ── Digital Signature PIN modal ───────────────────────────────────────────────
const sigShow    = ref(false)
const sigLoading = ref(false)

const sigConfirmLabel = computed(() =>
  modalMode.value === 'create' ? 'Sign & Submit' : 'Sign & Save'
)

function handleFormSubmit() {
  sigShow.value = true
}

function handleSigConfirm(pin) {
  sigShow.value = false
  form.pin = pin
  submitRequest()
}

function handleSigCancel() {
  sigShow.value = false
}

</script>


<template>
  <Head title="IT Job Requests" />
  <AdminLayout title="IT Job Requests">
    <div>
      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <h1 class="text-xl font-semibold text-slate-800">IT Job Requests</h1>
        <div class="flex items-center gap-2">
          <a
            v-if="props.isAdmin"
            :href="route('jobrequests.queue')"
            class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm"
          >
            <QueueListIcon class="w-4 h-4 text-indigo-500" />
            Queue
          </a>
          <button
            @click="handleNewRequest"
            class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm"
          >
            + New Request
          </button>
        </div>
      </div>

      <!-- Filter bar -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 mb-4">
        <div class="flex flex-wrap justify-between items-center gap-3">
          <div class="flex items-center gap-2 flex-1 min-w-0">
            <div class="relative flex-1 sm:w-64 sm:flex-none">
              <input
                v-model="search"
                type="text"
                placeholder="Search requests..."
                @keydown.enter.prevent="applyFilters(true)"
                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full"
              />
              <span v-if="isLoading" class="absolute right-3 top-1/2 -translate-y-1/2">
                <svg class="animate-spin h-4 w-4 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
              </span>
            </div>
            <button
              @click="applyFilters(true)"
              :disabled="isLoading"
              class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50 whitespace-nowrap"
            >
              Search
            </button>
            <label class="inline-flex items-center gap-1.5 cursor-pointer select-none whitespace-nowrap text-sm text-slate-600">
              <input type="checkbox" v-model="showAllChecked" class="h-4 w-4 rounded border-slate-300 text-indigo-600" />
              Show All
            </label>
          </div>
          <!-- Dropdown Filters -->
          <div class="flex items-center gap-2 flex-wrap">
            <select
              v-model="filterCategory"
              class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"
            >
              <option value="">All Categories</option>
              <option v-for="cat in props.categories" :key="cat.id" :value="cat.name">{{ cat.name }}</option>
            </select>
            <select
              v-model="filterStatus"
              class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"
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
            
            <button @click="openExportModal"
              class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium transition-colors shadow-sm"
              title="Generate PDF report">
              <DocumentChartBarIcon class="w-4 h-4" />
              
            </button>
          </div>
        </div>
      </div>

      <!-- Main card -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <!-- Loading overlay -->
        <div v-if="isLoading" class="relative">
          <div class="absolute inset-0 bg-white/70 flex items-center justify-center z-10 rounded-xl">
            <div class="flex flex-col items-center gap-2 text-indigo-600">
              <svg class="animate-spin h-8 w-8" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
              </svg>
              <span class="text-sm font-medium">Loading...</span>
            </div>
          </div>
        </div>

        <!-- Desktop table -->
        <div class="hidden sm:block overflow-x-auto" :class="{ 'opacity-50 pointer-events-none': isLoading }">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">ITJR #</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Title</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Category</th>
                <th v-if="userRole === 'Administrator'" class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Submitted By</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Date Filed</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Priority</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Status</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap text-center">Action</th>
              </tr>
            </thead>

            <tbody class="divide-y divide-slate-100">
              <tr v-for="req in visibleRequests" :key="req.id" class="hover:bg-slate-50/60">
                <td class="px-4 py-3 text-sm text-slate-700">{{ req.itjr_no }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ req.title }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ req.category }}</td>

                <td v-if="userRole === 'Administrator'" class="px-4 py-3 text-sm text-slate-700">
                  {{ req.user?.name ?? '—' }}
                </td>

                <td class="px-4 py-3 text-sm text-slate-700">{{ formatDate(req.created_at) }}</td>

                <td class="px-4 py-3">
                  <span
                    v-if="['In Progress', 'MIS Assessed the Request'].includes(req.status)"
                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold"
                    :class="priorityBadgeClass(req.priority)"
                  >
                    {{ PRIORITY_LABELS[req.priority] ?? 'Normal' }}
                  </span>
                  <span v-else class="text-slate-300 text-xs">—</span>
                </td>

                <td class="px-4 py-3">
                  <span :class="[badgeBase, statusBadgeClass(req.status)]">{{ req.status }}</span>
                </td>

                <td class="px-4 py-3 text-center">
                  <div class="flex justify-center gap-1">
                    <button @click="viewRequest(req)" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors">
                      <EyeIcon class="w-4 h-4"/>
                    </button>

                    <a :href="route('jobrequests.print', req.id)" target="_blank" title="Download PDF" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors">
                      <DocumentArrowDownIcon class="w-4 h-4"/>
                    </a>

                    <template v-if="userRole === 'Administrator'">
                      <button @click="openMISAssessment(req)" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors">
                        <PencilSquareIcon class="w-4 h-4"/>
                      </button>
                      <button @click="deleteRequest(req)" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-red-600 transition-colors">
                        <TrashIcon class="w-4 h-4"/>
                      </button>
                    </template>

                    <button
                      v-if="req.status === 'Acted by MIS' && req.user_id === currentUser?.id"
                      @click="openConfirmRating(req)"
                      class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1 rounded-lg text-xs font-medium transition-colors shadow-sm"
                    >
                      Confirm & Rate
                    </button>
                  </div>
                </td>
              </tr>

              <tr v-if="visibleRequests.length === 0">
                <td :colspan="userRole === 'Administrator' ? 8 : 7" class="py-16 text-center text-slate-400 text-sm">
                  No requests found.
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Mobile cards -->
        <div class="sm:hidden p-4 space-y-3" :class="{ 'opacity-50 pointer-events-none': isLoading }">
          <div
            v-for="req in visibleRequests"
            :key="req.id"
            class="border border-slate-100 rounded-lg p-4"
          >
            <div class="flex justify-between items-start">
              <div>
                <div class="text-xs text-slate-400">ITJR #{{ req.itjr_no }}</div>
                <div class="font-semibold text-slate-800">{{ req.title }}</div>
                <div class="text-sm text-slate-500">{{ req.category }}</div>
              </div>

              <span :class="[badgeBase, statusBadgeClass(req.status)]">{{ req.status }}</span>
            </div>

            <div class="mt-2 text-sm text-slate-600 space-y-1">
              <div><strong>Date:</strong> {{ formatDate(req.created_at) }}</div>
              <div v-if="userRole === 'Administrator'">
                <strong>Submitted By:</strong> {{ req.user?.name ?? '—' }}
              </div>
            </div>

            <div class="mt-3 flex gap-2 flex-wrap">
              <button
                @click="viewRequest(req)"
                class="flex-1 inline-flex items-center justify-center gap-2 px-3 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium transition-colors"
              >
                <EyeIcon class="w-4 h-4"/> View
              </button>

              <a :href="route('jobrequests.print', req.id)" target="_blank" title="Download PDF" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors">
                <DocumentArrowDownIcon class="w-4 h-4"/>
              </a>

              <template v-if="userRole === 'Administrator'">
                <button
                  @click="openMISAssessment(req)"
                  class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors"
                >
                  <PencilSquareIcon class="w-4 h-4"/>
                </button>

                <button
                  @click="deleteRequest(req)"
                  class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-red-600 transition-colors"
                >
                  <TrashIcon class="w-4 h-4"/>
                </button>
              </template>

              <button
                v-if="req.status === 'Acted by MIS' && userRole !== 'Administrator'"
                @click="openConfirmRating(req)"
                class="w-full inline-flex items-center justify-center px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-medium transition-colors"
              >
                Confirm & Rate
              </button>
            </div>
          </div>

          <div v-if="visibleRequests.length === 0" class="py-16 text-center text-slate-400 text-sm">
            No requests found.
          </div>
        </div>

        <!-- Pagination -->
        <div class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-sm text-slate-600">
          <button @click="goToPage(currentPage - 1)" :disabled="currentPage === 1 || isLoading" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-40">Prev</button>
          <span>Page {{ currentPage }} of {{ totalPages }}</span>
          <button @click="goToPage(currentPage + 1)" :disabled="currentPage === totalPages || isLoading" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-40">Next</button>
        </div>
      </div>

      <!-- Modal -->
      <div v-if="showModal" class="fixed inset-0 flex items-center justify-center bg-slate-900/50 z-50 p-4">
        <div
          class="bg-white w-full max-w-2xl rounded-2xl shadow-xl max-h-[90vh] overflow-y-auto"
        >
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-800">
              {{ modalMode==='create' ? 'New IT Job Request' : modalMode==='mis-assessment' ? 'MIS Assessment' : 'View Request' }}
            </h2>
            <button class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" @click="closeModal"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4 shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg></button>
          </div>

          <div class="px-6 py-5">
            <!-- VIEW MODE -->
            <div
              v-if="modalMode === 'view' && selectedRequest"
              class="space-y-6"
            >
              <!-- Request Summary Card -->
              <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-6">
                <div class="flex items-start justify-between mb-4">
                  <div>
                    <h2 class="text-xl font-semibold text-slate-800">
                      {{ selectedRequest.title }}
                    </h2>
                    <p class="text-sm text-slate-500">
                      Submitted by {{ selectedRequest.user?.name ?? '—' }}
                    </p>
                  </div>

                  <!-- Status Badge -->
                  <span :class="[badgeBase, statusBadgeClass(selectedRequest.status)]">{{ selectedRequest.status ?? 'Pending' }}</span>
                </div>

                <!-- Details Grid -->
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 text-sm">
                  <div>
                    <dt class="text-slate-500">Category</dt>
                    <dd class="font-medium text-slate-800">
                      {{ selectedRequest.category }}
                    </dd>
                  </div>

                  <div>
                    <dt class="text-slate-500">Assigned Personnel</dt>
                    <dd class="font-medium text-slate-800">
                      {{ selectedRequest.assigned_personnel ?? 'Not Assigned' }}
                    </dd>
                  </div>

                  <div>
                    <dt class="text-slate-500">Attended By</dt>
                    <dd class="font-medium text-slate-800">
                      {{ selectedRequest.attendedby ?? '—' }}
                    </dd>
                  </div>

                  <div>
                    <dt class="text-slate-500">Date Submitted</dt>
                    <dd class="font-medium text-slate-800">
                      {{ formatDate(selectedRequest.created_at) }}
                    </dd>
                  </div>

                  <!-- Full-width description -->
                  <div class="sm:col-span-2">
                    <dt class="text-slate-500">Description</dt>
                    <dd class="mt-1 text-slate-700 leading-relaxed whitespace-pre-line">
                      {{ selectedRequest.description }}
                    </dd>
                  </div>
                </dl>
              </div>

              <!-- Progress Tracking -->
              <div
                v-if="selectedRequest.tracking_logs?.length"
                class="bg-white rounded-xl border border-slate-100 shadow-sm p-6"
              >
                <h3 class="text-base font-semibold text-slate-800 mb-5">
                  Progress Tracking
                </h3>

                <!-- Timeline -->
                <div class="relative max-h-80 overflow-y-auto pl-6 border-l border-slate-200">
                  <div
                    v-for="log in selectedRequest.tracking_logs"
                    :key="log.id"
                    class="relative mb-6"
                  >
                    <!-- Timeline Dot -->
                    <span
                      class="absolute -left-[9px] top-1 h-4 w-4 rounded-full bg-indigo-500 ring-4 ring-white"
                    ></span>

                    <!-- Log Card -->
                    <div class="bg-slate-50 rounded-lg border border-slate-100 p-4 shadow-sm">
                      <div class="flex justify-between items-start">
                        <p class="text-sm font-semibold text-indigo-600">
                          {{ log.status }}
                        </p>
                        <span class="text-xs text-slate-400">
                          {{ formatDate(log.created_at) }}
                        </span>
                      </div>

                      <p class="mt-2 text-sm text-slate-600 whitespace-pre-line">
                        {{ log.remarks ?? 'No remarks provided.' }}
                      </p>

                      <p
                        v-if="log.it_job_request?.attendedby"
                        class="mt-2 text-xs text-slate-500"
                      >
                        Attended by:
                        <span class="font-medium text-slate-700">
                          {{ log.it_job_request.attendedby }}
                        </span>
                      </p>
                    </div>
                  </div>
                </div>
              </div>
            </div>


            <!-- CREATE / MIS ASSESSMENT FORM -->
            <form v-else @submit.prevent="handleFormSubmit" class="space-y-4">

              <!-- Fields common to CREATE -->
              <template v-if="modalMode==='create'">
                <div>
                  <label class="block text-xs font-medium text-slate-600 mb-1">Request</label>
                  <input v-model="form.title" type="text" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full" required />
                </div>
                <!-- Category -->
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Category</label>
                    <select
                    v-model="form.category"
                    class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full"
                    required
                    >
                    <option value="">-- Select Request Type --</option>
                    <option v-for="cat in props.categories" :key="cat.id" :value="cat.name">
                      {{ cat.name }}
                    </option>

                    </select>
                </div>

                <!-- Date of Event — only for Technical Assistance on Events -->
                <div v-if="form.category === 'Technical Assistance on Events'">
                  <label class="block text-xs font-medium text-slate-600 mb-1">
                    Date of Event <span class="text-red-500">*</span>
                  </label>
                  <input
                    v-model="form.event_date"
                    type="date"
                    :min="minEventDate"
                    class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full"
                    required
                  />
                  <p class="text-xs text-amber-600 mt-1">
                    Event must be filed at least 3 days in advance. Dates within 3 days from today are disabled.
                  </p>
                </div>

                <!-- Description -->
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Description</label>
                    <textarea
                    v-model="form.description"
                    rows="4"
                    class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full"
                    required
                    ></textarea>
                </div>

                <!-- Urgency -->
                <div>
                  <label class="block text-xs font-medium text-slate-600 mb-2">Urgency</label>
                  <div class="flex gap-2 flex-wrap">
                    <label
                      v-for="opt in [
                        { value: 'low',    label: 'Low',    color: 'peer-checked:bg-slate-100 peer-checked:border-slate-400 peer-checked:text-slate-700' },
                        { value: 'normal', label: 'Normal', color: 'peer-checked:bg-blue-50 peer-checked:border-blue-400 peer-checked:text-blue-700' },
                        { value: 'high',   label: 'High',   color: 'peer-checked:bg-orange-50 peer-checked:border-orange-400 peer-checked:text-orange-700' },
                        { value: 'urgent', label: 'Urgent', color: 'peer-checked:bg-red-50 peer-checked:border-red-400 peer-checked:text-red-700' },
                      ]"
                      :key="opt.value"
                      class="relative cursor-pointer"
                    >
                      <input type="radio" v-model="form.priority" :value="opt.value" class="peer sr-only" />
                      <span
                        class="inline-flex items-center px-3 py-1.5 rounded-lg border text-sm font-medium transition-colors border-slate-200 text-slate-500 hover:bg-slate-50"
                        :class="opt.color"
                      >{{ opt.label }}</span>
                    </label>
                  </div>
                  <p class="text-xs text-slate-400 mt-1">MIS may adjust the final priority based on workload.</p>
                </div>

                <!-- Posting Content Type — only for posting categories -->
                <div v-if="isPostingCategory">
                  <label class="block text-xs font-medium text-slate-600 mb-2">
                    Posting Content Type <span class="text-red-500">*</span>
                  </label>
                  <div class="flex gap-3">
                    <label v-for="opt in [
                      { value: 'financial', label: 'Financial', sub: 'Routed to your Division Chief' },
                      { value: 'general',   label: 'General / Informational', sub: 'Routed to the Information Officer' },
                    ]" :key="opt.value"
                      class="flex-1 flex items-start gap-2.5 p-3 rounded-xl border cursor-pointer transition-colors"
                      :class="form.posting_type === opt.value
                        ? 'border-indigo-500 bg-indigo-50'
                        : 'border-slate-200 hover:border-slate-300'">
                      <input type="radio" v-model="form.posting_type" :value="opt.value" class="mt-0.5 accent-indigo-600" required />
                      <div>
                        <p class="text-sm font-semibold" :class="form.posting_type === opt.value ? 'text-indigo-700' : 'text-slate-700'">{{ opt.label }}</p>
                        <p class="text-xs text-slate-400 mt-0.5">{{ opt.sub }}</p>
                      </div>
                    </label>
                  </div>
                </div>

                <!-- Approver info (auto-resolved) -->
                <div v-if="!isPostingCategory" class="flex items-start gap-2 rounded-lg bg-slate-50 border border-slate-200 px-3 py-2.5">
                  <svg class="h-4 w-4 text-slate-400 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  <p class="text-xs text-slate-500 leading-relaxed">
                    Your request will be automatically sent to your <strong>Division Chief</strong> for approval. MIS personnel will be auto-assigned based on workload.
                  </p>
                </div>

              </template>

              <div class="flex justify-end gap-2 pt-4">
                <button type="button" @click="closeModal" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">Cancel</button>
                <button type="submit" :disabled="form.processing" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-60 disabled:cursor-not-allowed min-w-[80px]">
                  Save
                </button>
              </div>

            </form>
          </div>
        </div>
      </div>

      <!-- CSM Survey Modal -->
      <CsmForm
        :show="showCsmModal"
        respondable-type="it-job-request"
        :respondable-id="requestToRate?.id ?? 0"
        :transaction-date="requestToRate?.created_at?.slice(0,10) ?? ''"
        office-availed="MIS/ICT Unit"
        service-key="others"
        service-other-label="IT Job Request / Technical Assistance"
        @close="showCsmModal = false"
        @submitted="showCsmModal = false"
      />

      <DigitalSignaturePin
        :show="sigShow"
        :has-pin="props.hasPin"
        :signature-uri="props.signatureUri"
        :loading="sigLoading"
        :confirm-label="sigConfirmLabel"
        @confirm="handleSigConfirm"
        @cancel="handleSigCancel"
      />

      <DigitalSignaturePin
        :show="completionSigShow"
        :has-pin="props.hasPin"
        :signature-uri="props.signatureUri"
        :loading="completionSigLoading"
        confirm-label="Sign & Confirm"
        @confirm="handleCompletionSigConfirm"
        @cancel="handleCompletionSigCancel"
      />

    </div>
    <!-- Export PDF Modal -->
    <Teleport to="body">
      <div v-if="showExportModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-md mx-4">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-base font-semibold text-slate-800">Export IT Job Request Report</h3>
            <button @click="showExportModal = false" class="text-slate-400 hover:text-slate-600"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4 shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg></button>
          </div>

          <div class="space-y-4">
            <!-- Date range -->
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Date From</label>
                <input v-model="exportDateFrom" type="date"
                  class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-400" />
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Date To</label>
                <input v-model="exportDateTo" type="date"
                  class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-400" />
              </div>
            </div>

            <!-- Category -->
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Category</label>
              <select v-model="exportCategory"
                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-400">
                <option value="">All Categories</option>
                <option v-for="cat in props.categories" :key="cat.id" :value="cat.name">{{ cat.name }}</option>
              </select>
            </div>

            <!-- Scope (admin only) -->
            <div v-if="props.isAdmin">
              <label class="block text-xs font-medium text-slate-600 mb-2">Records to include</label>
              <div class="flex gap-4">
                <label class="flex items-center gap-2 cursor-pointer text-sm text-slate-700">
                  <input type="radio" v-model="exportScope" value="all" class="text-emerald-600" />
                  All requests
                </label>
                <label class="flex items-center gap-2 cursor-pointer text-sm text-slate-700">
                  <input type="radio" v-model="exportScope" value="mine" class="text-emerald-600" />
                  Requests I attended
                </label>
              </div>
            </div>
          </div>

          <div class="flex gap-3 justify-end mt-6">
            <button @click="showExportModal = false"
              class="px-4 py-2 text-sm text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50">
              Cancel
            </button>
            <button @click="runExport"
              class="inline-flex items-center gap-2 px-4 py-2 text-sm bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium transition-colors">
              <DocumentChartBarIcon class="w-4 h-4" />
              Generate PDF
            </button>
          </div>
        </div>
      </div>
    </Teleport>

      <MISAssessmentModal
        :show="showAssessmentModal"
        :request="assessmentRequest"
        :ict-equipment="props.ictEquipment ?? []"
        :has-pin="props.hasPin"
        :signature-uri="props.signatureUri"
        @close="showAssessmentModal = false"
        @saved="showAssessmentModal = false"
      />

  </AdminLayout>
</template>
