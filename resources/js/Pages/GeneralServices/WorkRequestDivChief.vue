<script setup>
import { ref, computed, watch } from "vue"
import { Head, router } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import { CheckCircleIcon, XCircleIcon, EyeIcon, XMarkIcon } from "@heroicons/vue/24/outline"
import Swal from "sweetalert2"
import { statusBadgeClass, badgeBase } from '@/Composables/useStatusBadge.js'

const props = defineProps({ workRequests: Object, filters: Object })

const showModal     = ref(false)
const selectedWR    = ref(null)
const declineModal  = ref(false)
const declineId     = ref(null)
const declineReason = ref('')
const isSubmitting  = ref(false)
const search        = ref(props.filters?.search ?? '')
const isLoading     = ref(false)
let debounceTimer   = null

const applyFilters = (immediate = true) => {
  clearTimeout(debounceTimer)
  const go = () => {
    isLoading.value = true
    router.get(route('work-requests.dc-approval'), { search: search.value || undefined }, {
      preserveState: true, replace: true,
      only: ['workRequests', 'filters'],
      onFinish: () => { isLoading.value = false },
    })
  }
  if (immediate) go()
  else debounceTimer = setTimeout(go, 400)
}

watch(search, () => applyFilters(false))

const goToPage = (pageNum) => {
  isLoading.value = true
  router.get(route('work-requests.dc-approval'), { search: search.value || undefined, page: pageNum }, {
    preserveState: true, replace: true,
    only: ['workRequests', 'filters'],
    onFinish: () => { isLoading.value = false },
  })
}

const currentPage   = computed(() => props.workRequests?.current_page ?? 1)
const totalPages    = computed(() => props.workRequests?.last_page ?? 1)
const filteredItems = computed(() => props.workRequests?.data ?? [])

const openModal  = (wr) => { selectedWR.value = wr; showModal.value = true }
const closeModal = () => { selectedWR.value = null; showModal.value = false }

const approveRequest = async (id) => {
  const result = await Swal.fire({
    title: 'Approve this work request?',
    icon: 'question', showCancelButton: true,
    confirmButtonText: 'Yes, approve', cancelButtonText: 'Cancel', reverseButtons: true,
  })
  if (!result.isConfirmed) return
  isSubmitting.value = true
  Swal.fire({ title: 'Approving…', allowOutsideClick: false, showConfirmButton: false, didOpen: () => Swal.showLoading() })
  router.post(route('work-requests.approve.inapp', id), {}, {
    onSuccess: () => Swal.fire('Approved!', 'Work request approved. GSU Head has been notified.', 'success'),
    onFinish:  () => { isSubmitting.value = false },
  })
}

const openDecline = (id) => { declineId.value = id; declineReason.value = ''; declineModal.value = true }
const closeDecline = () => { declineModal.value = false; declineId.value = null }

const submitDecline = () => {
  if (!declineReason.value.trim()) return
  isSubmitting.value = true
  router.post(route('work-requests.decline.inapp', declineId.value), { reason: declineReason.value }, {
    onSuccess: () => { closeDecline(); Swal.fire('Declined', 'Work request declined.', 'error') },
    onFinish:  () => { isSubmitting.value = false },
  })
}
</script>

<template>
  <Head title="DC Approval - Work Requests" />
  <AdminLayout title="DC Approval - Work Requests">
    <div>
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Division Chief Approval — Work Requests</h1>
          <p class="text-sm text-slate-500 mt-0.5">Pending work requests from your division awaiting your approval</p>
        </div>
      </div>

      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 mb-4 flex flex-wrap items-center gap-3">
        <div class="relative flex-1 sm:w-64 sm:flex-none">
          <input v-model="search" type="text" placeholder="Search requests…"
                 @keydown.enter.prevent="applyFilters(true)"
                 class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
          <span v-if="isLoading" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs">Loading…</span>
        </div>
        <button @click="applyFilters(true)" :disabled="isLoading"
                class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50">
          Search
        </button>
      </div>

      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">#</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Requestor</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Issue</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Category</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Priority</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="wr in filteredItems" :key="wr.id" class="hover:bg-slate-50/60">
                <td class="px-4 py-3 text-slate-700">{{ wr.id }}</td>
                <td class="px-4 py-3 text-slate-700">{{ wr.requester?.name ?? '—' }}</td>
                <td class="px-4 py-3 text-slate-700">{{ wr.issue ?? '—' }}</td>
                <td class="px-4 py-3 text-slate-700">{{ wr.category ?? '—' }}</td>
                <td class="px-4 py-3 text-slate-700">{{ wr.priority ?? '—' }}</td>
                <td class="px-4 py-3">
                  <span :class="[badgeBase, statusBadgeClass(wr.status)]">{{ wr.status }}</span>
                </td>
                <td class="px-4 py-3 text-center">
                  <div class="flex items-center gap-1.5 justify-center">
                    <button @click="approveRequest(wr.id)" :disabled="isSubmitting"
                            class="inline-flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium transition-colors disabled:opacity-50">
                      <CheckCircleIcon class="w-3.5 h-3.5" /> Approve
                    </button>
                    <button @click="openDecline(wr.id)" :disabled="isSubmitting"
                            class="inline-flex items-center gap-1.5 bg-red-600 hover:bg-red-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium transition-colors disabled:opacity-50">
                      <XCircleIcon class="w-3.5 h-3.5" /> Decline
                    </button>
                    <button @click="openModal(wr)"
                            class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" title="View details">
                      <EyeIcon class="w-4 h-4" />
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="filteredItems.length === 0">
                <td colspan="7" class="py-16 text-center text-slate-400 text-sm">No pending work requests from your division.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="totalPages > 1" class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-sm text-slate-600">
          <span>Page {{ currentPage }} of {{ totalPages }}</span>
          <div class="flex items-center gap-2">
            <button @click="goToPage(currentPage - 1)" :disabled="currentPage === 1 || isLoading"
                    class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors disabled:opacity-50">Prev</button>
            <button @click="goToPage(currentPage + 1)" :disabled="currentPage === totalPages || isLoading"
                    class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors disabled:opacity-50">Next</button>
          </div>
        </div>
      </div>

      <!-- Detail Modal -->
      <div v-if="showModal && selectedWR" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-800">Work Request #{{ selectedWR.id }}</h2>
            <button @click="closeModal" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors">
              <XMarkIcon class="w-4 h-4" />
            </button>
          </div>
          <div class="px-6 py-5 space-y-3 text-sm text-slate-700">
            <div class="grid grid-cols-2 gap-3">
              <div><span class="text-xs font-medium text-slate-500 uppercase">Requestor</span><p class="mt-0.5">{{ selectedWR.requester?.name ?? '—' }}</p></div>
              <div><span class="text-xs font-medium text-slate-500 uppercase">Status</span><p class="mt-0.5"><span :class="[badgeBase, statusBadgeClass(selectedWR.status)]">{{ selectedWR.status }}</span></p></div>
              <div class="col-span-2"><span class="text-xs font-medium text-slate-500 uppercase">Issue</span><p class="mt-0.5">{{ selectedWR.issue ?? '—' }}</p></div>
              <div><span class="text-xs font-medium text-slate-500 uppercase">Category</span><p class="mt-0.5">{{ selectedWR.category ?? '—' }}</p></div>
              <div><span class="text-xs font-medium text-slate-500 uppercase">Priority</span><p class="mt-0.5">{{ selectedWR.priority ?? '—' }}</p></div>
              <div class="col-span-2" v-if="selectedWR.description"><span class="text-xs font-medium text-slate-500 uppercase">Description</span><p class="mt-0.5">{{ selectedWR.description }}</p></div>
            </div>
          </div>
          <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
            <button @click="closeModal" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">Close</button>
          </div>
        </div>
      </div>

      <!-- Decline Modal -->
      <div v-if="declineModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-800">Decline Work Request</h2>
            <button @click="closeDecline" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors">
              <XMarkIcon class="w-4 h-4" />
            </button>
          </div>
          <div class="px-6 py-5">
            <label class="block text-sm font-medium text-slate-700 mb-2">Reason for Decline <span class="text-red-500">*</span></label>
            <textarea v-model="declineReason" rows="4" placeholder="Provide a reason for declining this request…"
                      class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400"></textarea>
          </div>
          <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
            <button @click="closeDecline" class="bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors">Cancel</button>
            <button @click="submitDecline" :disabled="!declineReason.trim() || isSubmitting"
                    class="bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
              {{ isSubmitting ? 'Declining…' : 'Decline Request' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
