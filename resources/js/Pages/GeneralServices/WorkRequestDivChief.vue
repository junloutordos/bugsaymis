<script setup>
import { ref, computed } from "vue"
import { Head, router } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import AppModal from '@/Components/AppModal.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { CheckCircleIcon, XCircleIcon, EyeIcon } from "@heroicons/vue/24/outline"
import Swal from "sweetalert2"
import { statusBadgeClass, badgeBase } from '@/Composables/useStatusBadge.js'
import { confirmAction } from '@/Composables/useConfirm.js'
import PaginationControl from '@/Components/PaginationControl.vue'

const props = defineProps({ workRequests: Object, filters: Object })

const showModal     = ref(false)
const selectedWR    = ref(null)
const declineModal  = ref(false)
const declineId     = ref(null)
const declineReason = ref('')
const isSubmitting  = ref(false)
const search        = ref(props.filters?.search ?? '')
const isLoading     = ref(false)

const applyFilters = () => {
    isLoading.value = true
    router.get(route('work-requests.dc-approval'), { search: search.value || undefined }, {
      preserveState: true, replace: true,
      only: ['workRequests', 'filters'],
      onFinish: () => { isLoading.value = false },
    })
}

const clearFilters = () => {
  search.value = ''
  isLoading.value = true
  router.get(route('work-requests.dc-approval'), {}, {
    preserveState: true, replace: true,
    only: ['requests', 'filters'],
    onFinish: () => { isLoading.value = false },
  })
}



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
  const confirmed = await confirmAction({
    title: 'Approve this work request?',
    confirmText: 'Yes, approve',
  })
  if (!confirmed) return
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
    <div class="space-y-5">

      <AppPageHeader title="Division Chief Approval — Work Requests" subtitle="Pending work requests from your division awaiting your approval" />

      <AppFilterBar>
        <div class="relative flex-1 sm:w-64 sm:flex-none">
          <input v-model="search" type="text" placeholder="Search requests…"
                 @keydown.enter.prevent="applyFilters"
                 class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
          <span v-if="isLoading" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs">Loading…</span>
        </div>
        <template #actions>
          <AppButton :disabled="isLoading" @click="applyFilters">Search</AppButton>
          <AppButton v-if="search" variant="secondary" :disabled="isLoading" @click="clearFilters">Clear</AppButton>
        </template>
      </AppFilterBar>

      <AppTable :is-empty="filteredItems.length === 0" :skeleton-cols="7">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">#</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Requestor</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Issue</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Category</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Priority</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Actions</th>
          </tr>
        </template>

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
              <AppButton size="sm" variant="success" :disabled="isSubmitting" @click="approveRequest(wr.id)">
                <CheckCircleIcon class="w-3.5 h-3.5" /> Approve
              </AppButton>
              <AppButton size="sm" variant="danger" :disabled="isSubmitting" @click="openDecline(wr.id)">
                <XCircleIcon class="w-3.5 h-3.5" /> Decline
              </AppButton>
              <AppIconButton label="View details" @click="openModal(wr)">
                <EyeIcon class="w-4 h-4" />
              </AppIconButton>
            </div>
          </td>
        </tr>

        <template #empty>
          <EmptyState title="No pending work requests from your division." />
        </template>

        <template #footer>
          <PaginationControl
            :current-page="currentPage"
            :total-pages="totalPages"
            :total="props.workRequests?.total ?? 0"
            @prev="goToPage(currentPage - 1)"
            @next="goToPage(currentPage + 1)"
            @page="goToPage"
          />
        </template>
      </AppTable>

      <!-- Detail Modal -->
      <AppModal :show="showModal && !!selectedWR" :title="`Work Request #${selectedWR?.id}`" size="lg" @close="closeModal">
        <div class="grid grid-cols-2 gap-3 text-sm text-slate-700">
          <div><span class="text-xs font-medium text-slate-500 uppercase">Requestor</span><p class="mt-0.5">{{ selectedWR?.requester?.name ?? '—' }}</p></div>
          <div><span class="text-xs font-medium text-slate-500 uppercase">Status</span><p class="mt-0.5"><span :class="[badgeBase, statusBadgeClass(selectedWR?.status)]">{{ selectedWR?.status }}</span></p></div>
          <div class="col-span-2"><span class="text-xs font-medium text-slate-500 uppercase">Issue</span><p class="mt-0.5">{{ selectedWR?.issue ?? '—' }}</p></div>
          <div><span class="text-xs font-medium text-slate-500 uppercase">Category</span><p class="mt-0.5">{{ selectedWR?.category ?? '—' }}</p></div>
          <div><span class="text-xs font-medium text-slate-500 uppercase">Priority</span><p class="mt-0.5">{{ selectedWR?.priority ?? '—' }}</p></div>
          <div class="col-span-2" v-if="selectedWR?.description"><span class="text-xs font-medium text-slate-500 uppercase">Description</span><p class="mt-0.5">{{ selectedWR.description }}</p></div>
        </div>
        <template #footer>
          <AppButton variant="secondary" @click="closeModal">Close</AppButton>
        </template>
      </AppModal>

      <!-- Decline Modal -->
      <AppModal :show="declineModal" title="Decline Work Request" size="md" @close="closeDecline">
        <label class="block text-sm font-medium text-slate-700 mb-2">Reason for Decline <span class="text-red-500">*</span></label>
        <textarea v-model="declineReason" rows="4" placeholder="Provide a reason for declining this request…"
                  class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400"></textarea>
        <template #footer>
          <AppButton variant="secondary" @click="closeDecline">Cancel</AppButton>
          <AppButton variant="danger" :disabled="!declineReason.trim() || isSubmitting" @click="submitDecline">
            {{ isSubmitting ? 'Declining…' : 'Decline Request' }}
          </AppButton>
        </template>
      </AppModal>
    </div>
  </AdminLayout>
</template>
