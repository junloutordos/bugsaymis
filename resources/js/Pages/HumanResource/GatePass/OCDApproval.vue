<template>
  <Head title="OCD Approval — Gate Pass" />
  <AdminLayout title="OCD Approval — Gate Pass">
    <div class="space-y-5">

      <AppPageHeader title="OCD Approval — Gate Pass" subtitle="Gate passes forwarded by Division Chiefs awaiting Campus Director approval." />

      <!-- Search -->
      <AppFilterBar>
        <input v-model="search" type="text" placeholder="Search by employee name or purpose…"
               @keydown.enter.prevent="applyFilters"
               class="w-full sm:w-80 rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
        <template #actions>
          <span v-if="isLoading" class="text-xs text-slate-400">Searching…</span>
          <AppButton size="sm" :disabled="isLoading" @click="applyFilters">Search</AppButton>
          <AppButton v-if="search" size="sm" variant="secondary" :disabled="isLoading" @click="clearFilters">Clear</AppButton>
        </template>
      </AppFilterBar>

      <!-- Table -->
      <AppTable :is-empty="!filteredRequests.length" :skeleton-cols="7">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Control No.</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Employee</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Type</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Date</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Destination</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Status</th>
            <th class="px-4 py-3"></th>
          </tr>
        </template>

        <tr v-for="req in filteredRequests" :key="req.id" class="hover:bg-indigo-50/40">
          <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ req.controlno || '—' }}</td>
          <td class="px-4 py-3">
            <p class="font-medium text-slate-800">{{ req.requester_name ?? '—' }}</p>
          </td>
          <td class="px-4 py-3 text-slate-700">{{ req.gatepass_type || '—' }}</td>
          <td class="px-4 py-3 text-slate-600 whitespace-nowrap">{{ req.gatepass_date || '—' }}</td>
          <td class="px-4 py-3 text-slate-600 max-w-[160px] truncate">{{ req.destination || '—' }}</td>
          <td class="px-4 py-3">
            <AppBadge :color="statusColor(req.status)">{{ req.status }}</AppBadge>
          </td>
          <td class="px-4 py-3">
            <div class="flex items-center gap-2">
              <template v-if="req.status === 'Division Approved'">
                <AppButton size="sm" variant="success" :disabled="isSubmitting" @click="approveRequest(req.id)">Approve</AppButton>
                <AppButton size="sm" variant="danger" :disabled="isSubmitting" @click="openDecline(req)">Decline</AppButton>
              </template>
              <AppIconButton label="View gate pass detail" @click="openView(req)">
                <EyeIcon class="w-4 h-4" />
              </AppIconButton>
            </div>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="req in filteredRequests" :key="req.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0">
                <p class="font-mono text-xs text-slate-500">{{ req.controlno || '—' }}</p>
                <p class="font-medium text-slate-800">{{ req.requester_name ?? '—' }}</p>
                <p class="text-sm text-slate-700">{{ req.gatepass_type || '—' }} &middot; {{ req.gatepass_date || '—' }}</p>
              </div>
              <AppBadge :color="statusColor(req.status)">{{ req.status }}</AppBadge>
            </div>
            <p class="text-xs text-slate-600 truncate">{{ req.destination || '—' }}</p>
            <div class="flex items-center gap-2 pt-1">
              <template v-if="req.status === 'Division Approved'">
                <AppButton size="sm" variant="success" :disabled="isSubmitting" @click="approveRequest(req.id)">Approve</AppButton>
                <AppButton size="sm" variant="danger" :disabled="isSubmitting" @click="openDecline(req)">Decline</AppButton>
              </template>
              <AppIconButton label="View gate pass detail" @click="openView(req)">
                <EyeIcon class="w-4 h-4" />
              </AppIconButton>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No gate passes pending OCD approval" />
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

    </div>

    <!-- View Detail Modal -->
    <AppModal :show="viewModal && !!viewTarget" title="Gate Pass Detail" :subtitle="viewTarget?.controlno || '—'" @close="viewModal = false">
      <template #header>
        <AppBadge :color="statusColor(viewTarget?.status)">{{ viewTarget?.status }}</AppBadge>
      </template>
      <div class="space-y-3 text-sm">
        <div class="grid grid-cols-2 gap-x-6 gap-y-3">
          <div>
            <p class="text-xs text-slate-400 mb-0.5">Employee</p>
            <p class="font-medium text-slate-800">{{ viewTarget?.requester_name ?? '—' }}</p>
          </div>
          <div>
            <p class="text-xs text-slate-400 mb-0.5">Type</p>
            <p class="font-medium text-slate-800">{{ viewTarget?.gatepass_type || '—' }}</p>
          </div>
          <div>
            <p class="text-xs text-slate-400 mb-0.5">Date</p>
            <p class="font-medium text-slate-800">{{ viewTarget?.gatepass_date || '—' }}</p>
          </div>
          <div>
            <p class="text-xs text-slate-400 mb-0.5">Time Out → In</p>
            <p class="font-medium text-slate-800">{{ viewTarget?.gatepass_timeout || '—' }} → {{ viewTarget?.gatepass_timein || '—' }}</p>
          </div>
          <div class="col-span-2">
            <p class="text-xs text-slate-400 mb-0.5">Destination</p>
            <p class="font-medium text-slate-800">{{ viewTarget?.destination || '—' }}</p>
          </div>
          <div class="col-span-2">
            <p class="text-xs text-slate-400 mb-0.5">Purpose</p>
            <p class="text-slate-700">{{ viewTarget?.purpose || '—' }}</p>
          </div>
          <div v-if="viewTarget?.remarks" class="col-span-2">
            <p class="text-xs text-slate-400 mb-0.5">Remarks</p>
            <p class="text-slate-600 italic">{{ viewTarget?.remarks }}</p>
          </div>
        </div>
      </div>
      <template #footer>
        <template v-if="viewTarget?.status === 'Division Approved'">
          <AppButton variant="success" :disabled="isSubmitting" @click="approveRequest(viewTarget.id); viewModal = false">Approve</AppButton>
          <AppButton variant="danger" @click="openDecline(viewTarget); viewModal = false">Decline</AppButton>
        </template>
        <AppButton variant="secondary" @click="viewModal = false">Close</AppButton>
      </template>
    </AppModal>

    <!-- Decline Modal -->
    <AppModal :show="declineModal" title="Decline Gate Pass" @close="declineModal = false">
      <p class="text-sm text-slate-600 mb-3">
        Declining gate pass from <span class="font-medium">{{ declineTarget?.requester_name }}</span>.
      </p>
      <label class="block text-xs font-medium text-slate-600 mb-1">Reason <span class="text-danger-500">*</span></label>
      <textarea v-model="declineReason" rows="3" placeholder="Provide a reason for declining…"
                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 resize-none"></textarea>
      <template #footer>
        <AppButton variant="secondary" :disabled="isSubmitting" @click="declineModal = false">Cancel</AppButton>
        <AppButton variant="danger" :loading="isSubmitting" :disabled="!declineReason.trim()" @click="submitDecline">
          Confirm Decline
        </AppButton>
      </template>
    </AppModal>

    <DigitalSignaturePin
      :show="showPinModal"
      :hasPin="props.has_pin"
      :signatureUri="props.signature_uri"
      :loading="pinLoading"
      confirmLabel="Sign & Approve"
      @confirm="handlePinConfirm"
      @cancel="handlePinCancel"
    />

  </AdminLayout>
</template>

<script setup>
import { ref, computed } from "vue"
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { EyeIcon } from '@heroicons/vue/24/outline'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import AppModal from '@/Components/AppModal.vue'
import EmptyState from '@/Components/EmptyState.vue'
import DigitalSignaturePin from '@/Components/DigitalSignaturePin.vue'
import PaginationControl from '@/Components/PaginationControl.vue'

const props = defineProps({
  requests:      Object,
  filters:       Object,
  has_pin:       { type: Boolean, default: false },
  signature_uri: { type: String,  default: null },
})

const search      = ref(props.filters?.search ?? '')
const isLoading   = ref(false)
const isSubmitting = ref(false)

// ── Status badge color mapping ────────────────────────────────────────────────
function statusColor(status) {
  const map = {
    'Pending':            'amber',
    'Division Approved':  'green',
    'OCD Approved':       'green',
    'Division Declined':  'red',
    'OCD Declined':       'red',
  }
  return map[status] ?? 'slate'
}

function applyFilters() {
  isLoading.value = true
  router.get(route('gatepass.ocd-approval'), { search: search.value || undefined }, {
    preserveState: true, replace: true,
    only: ['requests', 'filters'],
    onFinish: () => { isLoading.value = false },
  })
}

function clearFilters() {
  search.value = ''
  isLoading.value = true
  router.get(route('gatepass.ocd-approval'), {}, {
    preserveState: true, replace: true,
    only: ['requests', 'filters'],
    onFinish: () => { isLoading.value = false },
  })
}

function goToPage(pageNum) {
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

// ── View ──────────────────────────────────────────────────────────────────────
const viewModal  = ref(false)
const viewTarget = ref(null)
function openView(req) { viewTarget.value = req; viewModal.value = true }

// ── Approve ───────────────────────────────────────────────────────────────────
const showPinModal     = ref(false)
const pendingApproveId = ref(null)
const pinLoading       = ref(false)

function approveRequest(id) {
  if (props.has_pin) {
    pendingApproveId.value = id
    showPinModal.value = true
    return
  }
  submitApprove(id, null)
}

function handlePinConfirm(pin) {
  showPinModal.value = false
  submitApprove(pendingApproveId.value, pin)
}

function handlePinCancel() {
  showPinModal.value = false
  pendingApproveId.value = null
}

function submitApprove(id, pin) {
  isSubmitting.value = true
  pinLoading.value = true
  router.post(route('gatepass.ocd-action', id), { action: 'approve', pin: pin ?? undefined }, {
    onFinish: () => { isSubmitting.value = false; pinLoading.value = false },
  })
}

// ── Decline ───────────────────────────────────────────────────────────────────
const declineModal  = ref(false)
const declineTarget = ref(null)
const declineReason = ref('')

function openDecline(req) {
  declineTarget.value = req
  declineReason.value = ''
  declineModal.value  = true
}

function submitDecline() {
  if (!declineReason.value.trim() || isSubmitting.value) return
  isSubmitting.value = true
  router.post(route('gatepass.ocd-action', declineTarget.value.id), { action: 'reject', reason: declineReason.value }, {
    onSuccess: () => { declineModal.value = false },
    onFinish:  () => { isSubmitting.value = false },
  })
}
</script>
