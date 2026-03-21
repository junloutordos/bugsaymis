<script setup>
import { ref, computed, watch } from "vue"
import { Head, router } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import { CheckCircleIcon, XCircleIcon, EyeIcon } from "@heroicons/vue/24/outline"
import Swal from "sweetalert2"

const props = defineProps({ requests: Object, filters: Object })

const showModal       = ref(false)
const selectedRequest = ref(null)
const isSubmitting    = ref(false)
const search          = ref(props.filters?.search ?? '')
const isLoading       = ref(false)
let debounceTimer     = null

const applyFilters = (immediate = true) => {
  clearTimeout(debounceTimer)
  const go = () => {
    isLoading.value = true
    router.get(route('gatepass.ocd-approval'), { search: search.value || undefined }, {
      preserveState: true, replace: true,
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
  router.get(route('gatepass.ocd-approval'), { search: search.value || undefined, page: pageNum }, {
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

const approveRequest = async (id) => {
  const result = await Swal.fire({
    title: 'Approve this gate pass?',
    icon: 'question', showCancelButton: true,
    confirmButtonText: 'Yes, approve', cancelButtonText: 'Cancel', reverseButtons: true,
  })
  if (!result.isConfirmed) return
  isSubmitting.value = true
  Swal.fire({ title: 'Approving…', allowOutsideClick: false, showConfirmButton: false, didOpen: () => Swal.showLoading() })
  router.post(route('gatepass.ocd-action', id), { action: 'approve' }, {
    onSuccess: () => Swal.fire('Approved!', 'Gate pass approved by OCD.', 'success'),
    onFinish:  () => { isSubmitting.value = false },
  })
}

const rejectRequest = async (id) => {
  const result = await Swal.fire({
    title: 'Decline this gate pass?',
    icon: 'warning', showCancelButton: true,
    confirmButtonText: 'Yes, decline', cancelButtonText: 'Cancel', reverseButtons: true,
  })
  if (!result.isConfirmed) return
  isSubmitting.value = true
  Swal.fire({ title: 'Declining…', allowOutsideClick: false, showConfirmButton: false, didOpen: () => Swal.showLoading() })
  router.post(route('gatepass.ocd-action', id), { action: 'reject' }, {
    onSuccess: () => Swal.fire('Declined', 'Gate pass declined.', 'error'),
    onFinish:  () => { isSubmitting.value = false },
  })
}
</script>

<template>
  <Head title="OCD Approval - Gate Pass" />
  <AdminLayout title="OCD Approval - Gate Pass">
    <div>
      <!-- Page header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <h1 class="text-xl font-semibold text-slate-800">OCD Approval — Gate Pass</h1>
      </div>

      <!-- Filter bar -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 mb-4 flex flex-wrap items-center gap-3">
        <div class="relative flex-1 sm:w-64 sm:flex-none">
          <input v-model="search" type="text" placeholder="Search gate passes…"
                 @keydown.enter.prevent="applyFilters(true)"
                 class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
          <span v-if="isLoading" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">⏳</span>
        </div>
        <button @click="applyFilters(true)" :disabled="isLoading"
                class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50">
          Search
        </button>
      </div>

      <!-- Table card -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <div class="overflow-x-auto rounded-xl border border-slate-100">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">#</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Requestor</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Purpose</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Date</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Status</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="req in filteredRequests" :key="req.id" class="hover:bg-slate-50/60">
                <td class="px-4 py-3 text-sm text-slate-700">{{ req.id }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ req.requester_name ?? '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ req.purpose ?? '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ req.date ?? req.created_at?.slice(0,10) ?? '—' }}</td>
                <td class="px-4 py-3">
                  <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-amber-50 text-amber-700">{{ req.status }}</span>
                </td>
                <td class="px-4 py-3">
                  <div class="flex items-center gap-2">
                    <button v-if="req.status === 'Division Approved'" @click="approveRequest(req.id)" :disabled="isSubmitting"
                            class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50">
                      <CheckCircleIcon class="w-4 h-4 text-emerald-600" /> Approve
                    </button>
                    <button v-if="req.status === 'Division Approved'" @click="rejectRequest(req.id)" :disabled="isSubmitting"
                            class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50">
                      <XCircleIcon class="w-4 h-4" /> Decline
                    </button>
                    <button @click="openModal(req)"
                            class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
                      <EyeIcon class="w-4 h-4" /> View
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="filteredRequests.length === 0">
                <td colspan="6" class="py-16 text-center text-slate-400 text-sm">No pending gate passes for OCD approval.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="totalPages > 1" class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-sm text-slate-600">
          <span>Page {{ currentPage }} of {{ totalPages }}</span>
          <div class="flex gap-2">
            <button @click="goToPage(currentPage - 1)" :disabled="currentPage === 1 || isLoading"
                    class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50">Prev</button>
            <button @click="goToPage(currentPage + 1)" :disabled="currentPage === totalPages || isLoading"
                    class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50">Next</button>
          </div>
        </div>
      </div>

      <!-- Detail Modal -->
      <div v-if="showModal && selectedRequest" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50">
        <div class="bg-white rounded-2xl shadow-xl max-w-lg w-full relative">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-800">Gate Pass #{{ selectedRequest.id }}</h2>
            <button @click="closeModal" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors">✖</button>
          </div>
          <div class="px-6 py-5 space-y-2 text-sm text-slate-700">
            <p><strong>Requestor:</strong> {{ selectedRequest.requester_name ?? '—' }}</p>
            <p><strong>Purpose:</strong> {{ selectedRequest.purpose ?? '—' }}</p>
            <p><strong>Date:</strong> {{ selectedRequest.date ?? '—' }}</p>
            <p><strong>Destination:</strong> {{ selectedRequest.destination ?? '—' }}</p>
            <p><strong>Status:</strong> {{ selectedRequest.status }}</p>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
