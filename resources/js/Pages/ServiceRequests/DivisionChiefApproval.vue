<template>
  <Head title="DC Approval - Service Requests" />
  <AdminLayout title="DC Approval - Service Requests">
    <div class="space-y-5">

      <AppPageHeader title="Division Chief Approval — Service Requests" subtitle="Pending service requests from your division awaiting your approval" />

      <!-- Filters -->
      <AppFilterBar>
        <div class="relative flex-1 sm:w-64 sm:flex-none">
          <input v-model="search" type="text" placeholder="Search requests…"
                 @keydown.enter.prevent="applyFilters"
                 class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
          <span v-if="isLoading" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs">Loading…</span>
        </div>
        <template #actions>
          <AppButton size="sm" :disabled="isLoading" @click="applyFilters">Search</AppButton>
          <AppButton v-if="search" size="sm" variant="secondary" :disabled="isLoading" @click="clearFilters">Clear</AppButton>
        </template>
      </AppFilterBar>

      <!-- Table -->
      <AppTable :is-empty="filteredRequests.length === 0" :skeleton-cols="7">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">#</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Requestor</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Service Type</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Date Needed</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Purposes</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Actions</th>
          </tr>
        </template>

        <tr v-for="req in filteredRequests" :key="req.id" class="hover:bg-slate-50/60">
          <td class="px-4 py-3 text-slate-700">{{ req.id }}</td>
          <td class="px-4 py-3 text-slate-700">{{ req.requester?.name ?? '—' }}</td>
          <td class="px-4 py-3 text-slate-700">{{ req.service_type ?? '—' }}</td>
          <td class="px-4 py-3 text-slate-700">{{ req.date_needed ?? '—' }}</td>
          <td class="px-4 py-3 text-slate-700">{{ req.purposes ?? '—' }}</td>
          <td class="px-4 py-3">
            <AppBadge :color="statusColor(req.status)">{{ req.status }}</AppBadge>
          </td>
          <td class="px-4 py-3 text-center">
            <div class="flex items-center gap-1.5 justify-center">
              <AppButton size="sm" variant="success" :disabled="isSubmitting" @click="approveRequest(req.id)">
                <CheckCircleIcon class="w-3.5 h-3.5" /> Approve
              </AppButton>
              <AppButton size="sm" variant="danger" :disabled="isSubmitting" @click="openDecline(req.id)">
                <XCircleIcon class="w-3.5 h-3.5" /> Decline
              </AppButton>
              <AppIconButton label="View details" size="sm" @click="openModal(req)">
                <EyeIcon class="w-4 h-4" />
              </AppIconButton>
            </div>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="req in filteredRequests" :key="req.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0">
                <p class="text-xs text-slate-500">Request #{{ req.id }}</p>
                <p class="text-sm font-semibold text-slate-800 mt-0.5">{{ req.service_type ?? '—' }}</p>
                <p class="text-xs text-slate-600 mt-1">Requestor: {{ req.requester?.name ?? '—' }}</p>
              </div>
              <AppBadge :color="statusColor(req.status)">{{ req.status }}</AppBadge>
            </div>
            <div class="space-y-1 text-xs text-slate-700">
              <div><span class="font-medium text-slate-500">Date Needed:</span> {{ req.date_needed ?? '—' }}</div>
              <div><span class="font-medium text-slate-500">Purpose:</span> {{ req.purposes ?? '—' }}</div>
            </div>
            <div class="flex flex-wrap items-center gap-2 pt-1">
              <AppButton size="sm" variant="success" :disabled="isSubmitting" @click="approveRequest(req.id)">
                <CheckCircleIcon class="w-3.5 h-3.5" /> Approve
              </AppButton>
              <AppButton size="sm" variant="danger" :disabled="isSubmitting" @click="openDecline(req.id)">
                <XCircleIcon class="w-3.5 h-3.5" /> Decline
              </AppButton>
              <AppIconButton label="View details" size="sm" @click="openModal(req)">
                <EyeIcon class="w-4 h-4" />
              </AppIconButton>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No pending service requests from your division." />
        </template>

        <template #footer>
          <PaginationControl
            :current-page="currentPage"
            :total-pages="totalPages"
            :total="props.requests?.total ?? 0"
            @prev="goToPage(currentPage - 1)"
            @next="goToPage(currentPage + 1)"
            @page="goToPage"
          />
        </template>
      </AppTable>

      <!-- Detail Modal -->
      <AppModal :show="showModal && !!selectedRequest" :title="`Service Request #${selectedRequest?.id}`" @close="closeModal">
        <div v-if="selectedRequest" class="space-y-3 text-sm text-slate-700">
          <div class="grid grid-cols-2 gap-3">
            <div><span class="text-xs font-medium text-slate-500 uppercase">Requestor</span><p class="mt-0.5">{{ selectedRequest.requester?.name ?? '—' }}</p></div>
            <div><span class="text-xs font-medium text-slate-500 uppercase">Status</span><p class="mt-0.5"><AppBadge :color="statusColor(selectedRequest.status)">{{ selectedRequest.status }}</AppBadge></p></div>
            <div><span class="text-xs font-medium text-slate-500 uppercase">Service Type</span><p class="mt-0.5">{{ selectedRequest.service_type ?? '—' }}</p></div>
            <div><span class="text-xs font-medium text-slate-500 uppercase">Date Needed</span><p class="mt-0.5">{{ selectedRequest.date_needed ?? '—' }}</p></div>
            <div v-if="selectedRequest.copies"><span class="text-xs font-medium text-slate-500 uppercase">Copies</span><p class="mt-0.5">{{ selectedRequest.copies }}</p></div>
            <div v-if="selectedRequest.sheets_per_set"><span class="text-xs font-medium text-slate-500 uppercase">Sheets/Set</span><p class="mt-0.5">{{ selectedRequest.sheets_per_set }}</p></div>
            <div class="col-span-2" v-if="selectedRequest.purposes"><span class="text-xs font-medium text-slate-500 uppercase">Purposes</span><p class="mt-0.5">{{ selectedRequest.purposes }}</p></div>
            <div class="col-span-2" v-if="selectedRequest.details"><span class="text-xs font-medium text-slate-500 uppercase">Details</span><p class="mt-0.5">{{ selectedRequest.details }}</p></div>
          </div>
        </div>
        <template #footer>
          <AppButton variant="secondary" @click="closeModal">Close</AppButton>
        </template>
      </AppModal>

      <!-- Decline Modal -->
      <AppModal :show="declineModal" title="Decline Service Request" @close="closeDecline">
        <label class="block text-sm font-medium text-slate-700 mb-2">Reason for Decline <span class="text-red-500">*</span></label>
        <textarea v-model="declineReason" rows="4" placeholder="Provide a reason for declining this request…"
                  class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400"></textarea>
        <template #footer>
          <AppButton variant="secondary" @click="closeDecline">Cancel</AppButton>
          <AppButton variant="danger" :disabled="!declineReason.trim() || isSubmitting" :loading="isSubmitting" @click="submitDecline">
            {{ isSubmitting ? 'Declining…' : 'Decline Request' }}
          </AppButton>
        </template>
      </AppModal>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed } from "vue"
import { Head, router } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppModal from '@/Components/AppModal.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { CheckCircleIcon, XCircleIcon, EyeIcon } from "@heroicons/vue/24/outline"
import Swal from "sweetalert2"
import PaginationControl from '@/Components/PaginationControl.vue'

const props = defineProps({ requests: Object, filters: Object })

const showModal       = ref(false)
const selectedRequest = ref(null)
const declineModal    = ref(false)
const declineId       = ref(null)
const declineReason   = ref('')
const isSubmitting    = ref(false)
const search          = ref(props.filters?.search ?? '')
const isLoading       = ref(false)

const applyFilters = () => {
    isLoading.value = true
    router.get(route('service-requests.dc-approval'), { search: search.value || undefined }, {
      preserveState: true, replace: true,
      only: ['requests', 'filters'],
      onFinish: () => { isLoading.value = false },
    })
}

const clearFilters = () => {
  search.value = ''
  isLoading.value = true
  router.get(route('service-requests.dc-approval'), {}, {
    preserveState: true, replace: true,
    only: ['requests', 'filters'],
    onFinish: () => { isLoading.value = false },
  })
}



const goToPage = (pageNum) => {
  isLoading.value = true
  router.get(route('service-requests.dc-approval'), { search: search.value || undefined, page: pageNum }, {
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
    title: 'Approve this service request?',
    icon: 'question', showCancelButton: true,
    confirmButtonText: 'Yes, approve', cancelButtonText: 'Cancel', reverseButtons: true,
  })
  if (!result.isConfirmed) return
  isSubmitting.value = true
  Swal.fire({ title: 'Approving…', allowOutsideClick: false, showConfirmButton: false, didOpen: () => Swal.showLoading() })
  router.post(route('service-requests.approve.inapp', id), {}, {
    onSuccess: () => Swal.fire('Approved!', 'Service request approved. FAD has been notified.', 'success'),
    onFinish:  () => { isSubmitting.value = false },
  })
}

const openDecline = (id) => { declineId.value = id; declineReason.value = ''; declineModal.value = true }
const closeDecline = () => { declineModal.value = false; declineId.value = null }

const submitDecline = () => {
  if (!declineReason.value.trim()) return
  isSubmitting.value = true
  router.post(route('service-requests.decline.inapp', declineId.value), { reason: declineReason.value }, {
    onSuccess: () => { closeDecline(); Swal.fire('Declined', 'Service request declined.', 'error') },
    onFinish:  () => { isSubmitting.value = false },
  })
}

function statusColor(status) {
  const map = {
    'Pending':      'amber',
    'Approved':     'green',
    'Declined':     'red',
    'FAD Approved': 'green',
    'FAD Declined': 'red',
    'GSU Approved': 'green',
  }
  return map[status] ?? 'slate'
}
</script>
