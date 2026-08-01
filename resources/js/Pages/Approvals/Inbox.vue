<template>
  <Head title="Approvals Inbox" />
  <AdminLayout title="Approvals Inbox">
    <div class="space-y-5">

      <AppPageHeader title="Approvals Inbox" subtitle="All pending requests awaiting your approval">
        <template #actions>
          <AppBadge v-if="totalCount > 0" color="indigo">{{ totalCount }} pending</AppBadge>
        </template>
      </AppPageHeader>

      <!-- Empty state -->
      <EmptyState
        v-if="visibleTabs.length === 0"
        title="All clear!"
        subtitle="No pending requests require your approval."
        :icon="CheckCircleIcon"
      />

      <template v-else>
        <!-- Tab bar -->
        <div class="flex flex-wrap gap-2">
          <button
            v-for="tab in visibleTabs"
            :key="tab.type"
            @click="activeTab = tab.type"
            :class="[
              'inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors',
              activeTab === tab.type
                ? 'bg-indigo-600 text-white shadow-sm'
                : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50',
            ]"
          >
            {{ tab.label }}
            <span :class="[
              'inline-flex items-center justify-center w-5 h-5 rounded-full text-xs font-semibold',
              activeTab === tab.type ? 'bg-white/20 text-white' : 'bg-indigo-100 text-indigo-700',
            ]">{{ tab.count }}</span>
          </button>
        </div>

        <!-- Search bar -->
        <AppFilterBar>
          <div class="relative w-full sm:w-96">
            <MagnifyingGlassIcon class="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
            <input
              v-model="search"
              type="text"
              placeholder="Search by requestor, reference no., or summary…"
              class="w-full rounded-lg border border-slate-200 bg-white py-2 pl-9 pr-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
          </div>
        </AppFilterBar>

        <!-- Table -->
        <AppTable :is-empty="filteredItems.length === 0" :skeleton-cols="7">
          <template #head>
            <tr>
              <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">#</th>
              <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Requestor</th>
              <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Reference No.</th>
              <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Summary</th>
              <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Filed At</th>
              <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Status</th>
              <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Review</th>
            </tr>
          </template>

          <tr v-for="(item, idx) in filteredItems" :key="item.id" class="hover:bg-indigo-50/40">
            <td class="px-4 py-3 text-slate-500 text-xs">{{ idx + 1 }}</td>
            <td class="px-4 py-3 text-slate-700">{{ item.requester_name }}</td>
            <td class="px-4 py-3 text-slate-700 font-mono text-xs">{{ item.reference_no }}</td>
            <td class="px-4 py-3 text-slate-700 max-w-xs truncate">{{ item.summary }}</td>
            <td class="px-4 py-3 text-slate-500 text-xs whitespace-nowrap">{{ formatDate(item.filed_at) }}</td>
            <td class="px-4 py-3">
              <AppBadge :color="statusColor(item.status)">{{ item.status }}</AppBadge>
            </td>
            <td class="px-4 py-3 text-center">
              <AppButton size="sm" @click="openModal(item)" title="View details and take action">
                <EyeIcon class="w-3.5 h-3.5" />
                Review
              </AppButton>
            </td>
          </tr>

          <template #mobileCard>
            <div v-for="(item, idx) in filteredItems" :key="item.id" class="p-4 space-y-2">
              <div class="flex items-start justify-between gap-2">
                <div>
                  <p class="text-xs text-slate-500">#{{ idx + 1 }} &middot; {{ item.requester_name }}</p>
                  <p class="font-mono text-xs text-slate-700">{{ item.reference_no }}</p>
                </div>
                <AppBadge :color="statusColor(item.status)">{{ item.status }}</AppBadge>
              </div>
              <p class="text-sm text-slate-700">{{ item.summary }}</p>
              <div class="flex items-center justify-between pt-1">
                <span class="text-xs text-slate-400">Filed {{ formatDate(item.filed_at) }}</span>
                <AppButton size="sm" @click="openModal(item)" title="View details and take action">
                  <EyeIcon class="w-3.5 h-3.5" />
                  Review
                </AppButton>
              </div>
            </div>
          </template>

          <template #empty>
            <EmptyState :title="search ? 'No results match your search.' : 'No pending items in this tab.'" />
          </template>
        </AppTable>
      </template>

      <!-- Detail Modal -->
      <div
        v-if="showModal && selectedItem"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50"
        @click.self="closeModal"
      >
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4 max-h-[90vh] flex flex-col">
          <!-- Header -->
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between flex-shrink-0">
            <div>
              <h2 class="text-base font-semibold text-slate-800">{{ selectedItem.reference_no }}</h2>
              <p class="text-xs text-slate-500 mt-0.5">{{ activeTabData?.label }}</p>
            </div>
            <AppIconButton label="Close" @click="closeModal">
              <XMarkIcon class="w-4 h-4" />
            </AppIconButton>
          </div>

          <!-- Body -->
          <div class="px-6 py-5 overflow-y-auto flex-1">
            <!-- Always-visible header fields -->
            <div class="grid grid-cols-2 gap-3 text-sm mb-5">
              <div>
                <span class="text-xs font-medium text-slate-500 uppercase tracking-wide">Requestor</span>
                <p class="mt-0.5 text-slate-800 font-medium">{{ selectedItem.requester_name }}</p>
              </div>
              <div>
                <span class="text-xs font-medium text-slate-500 uppercase tracking-wide">Filed At</span>
                <p class="mt-0.5 text-slate-700">{{ formatDate(selectedItem.filed_at) }}</p>
              </div>
              <div>
                <span class="text-xs font-medium text-slate-500 uppercase tracking-wide">Status</span>
                <p class="mt-0.5"><AppBadge :color="statusColor(selectedItem.status)">{{ selectedItem.status }}</AppBadge></p>
              </div>
              <div>
                <span class="text-xs font-medium text-slate-500 uppercase tracking-wide">Summary</span>
                <p class="mt-0.5 text-slate-700">{{ selectedItem.summary }}</p>
              </div>
            </div>

            <!-- Dynamic sections per request type -->
            <template v-for="section in (selectedItem.sections ?? [])" :key="section.title">
              <div class="border-t border-slate-100 pt-4 mb-4">
                <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-3">{{ section.title }}</p>
                <div class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                  <template v-for="field in section.fields" :key="field.label">
                    <div :class="field.full ? 'col-span-2' : 'col-span-1'">
                      <span class="text-xs font-medium text-slate-500 uppercase tracking-wide">{{ field.label }}</span>
                      <p class="mt-0.5 text-slate-700 whitespace-pre-wrap break-words">{{ field.value ?? '—' }}</p>
                    </div>
                  </template>
                </div>
              </div>
            </template>

            <!-- Decline textarea (shown when showDecline is true) -->
            <div v-if="showDecline" class="border-t border-slate-100 pt-4 mt-2">
              <label class="block text-sm font-medium text-slate-700 mb-2">
                Reason for Decline <span class="text-danger-600">*</span>
              </label>
              <textarea
                v-model="declineReason"
                rows="3"
                placeholder="Provide a reason for declining this request…"
                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-danger-500"
              ></textarea>
            </div>
          </div>

          <!-- Footer -->
          <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-end gap-2 flex-shrink-0">
            <template v-if="!showDecline">
              <AppButton variant="secondary" @click="closeModal">
                Close
              </AppButton>
              <AppButton v-if="selectedItem.view_url" variant="secondary" as="a" :href="selectedItem.view_url" target="_blank">
                <ArrowTopRightOnSquareIcon class="w-4 h-4" /> View Full Record
              </AppButton>
              <AppButton variant="danger" :disabled="isSubmitting" @click="showDecline = true">
                <XCircleIcon class="w-4 h-4" /> Decline
              </AppButton>
              <AppButton :disabled="isSubmitting" @click="confirmApprove(selectedItem)">
                <CheckCircleIcon class="w-4 h-4" />
                {{ isSubmitting ? 'Processing…' : 'Approve' }}
              </AppButton>
            </template>
            <template v-else>
              <AppButton variant="secondary" @click="showDecline = false; declineReason = ''">
                Cancel
              </AppButton>
              <AppButton variant="danger" :disabled="!canSubmitDecline || isSubmitting" @click="submitDecline(selectedItem)">
                {{ isSubmitting ? 'Declining…' : 'Submit Decline' }}
              </AppButton>
            </template>
          </div>
        </div>
      </div>
    </div>

    <!-- Digital Signature PIN modal for approvals -->
    <DigitalSignaturePin
      :show="showPinModal"
      :hasPin="hasPin"
      :signatureUri="signatureUri"
      :loading="pinModalLoading"
      confirmLabel="Approve"
      @confirm="handlePinConfirm"
      @cancel="handlePinCancel"
    />
  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ArrowTopRightOnSquareIcon, MagnifyingGlassIcon, EyeIcon, CheckCircleIcon, XCircleIcon, XMarkIcon } from '@heroicons/vue/24/outline'
import Swal from 'sweetalert2'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import EmptyState from '@/Components/EmptyState.vue'
import DigitalSignaturePin from '@/Components/DigitalSignaturePin.vue'

const props = defineProps({
  tabs:         { type: Array,   default: () => [] },
  totalCount:   { type: Number,  default: 0 },
  filters:      { type: Object,  default: () => ({}) },
  hasPin:       { type: Boolean, default: false },
  signatureUri: { type: String,  default: null },
})

// ── Local state ───────────────────────────────────────────────────────────────
const localTabs    = ref(props.tabs.map(t => ({ ...t, items: [...t.items] })))
const activeTab    = ref(localTabs.value[0]?.type ?? null)
const search       = ref(props.filters?.search ?? '')
const showModal    = ref(false)
const selectedItem = ref(null)
const showDecline  = ref(false)
const declineReason = ref('')
const isSubmitting  = ref(false)

// PIN modal state
const showPinModal   = ref(false)
const pinModalLoading = ref(false)
const pendingApproveItem = ref(null)

// ── Computed ──────────────────────────────────────────────────────────────────
const visibleTabs = computed(() => localTabs.value.filter(t => t.count > 0))

const activeTabData = computed(() => localTabs.value.find(t => t.type === activeTab.value))

const filteredItems = computed(() => {
  const items = activeTabData.value?.items ?? []
  const q = search.value.trim().toLowerCase()
  if (!q) return items
  return items.filter(i =>
    (i.requester_name ?? '').toLowerCase().includes(q) ||
    (i.reference_no   ?? '').toLowerCase().includes(q) ||
    (i.summary        ?? '').toLowerCase().includes(q)
  )
})

const canSubmitDecline = computed(() => declineReason.value.trim().length > 0)

// ── Helpers ───────────────────────────────────────────────────────────────────
function formatDate(val) {
  if (!val) return '—'
  try {
    return new Date(val).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
  } catch {
    return val
  }
}

function statusColor(status) {
  const s = (status ?? '').toString().toLowerCase().trim()

  if (s === 'in progress') return 'orange'

  if ([
    'approved', 'active', 'completed', 'request completed', 'ocd approved',
    'division approved', 'approved by pmt', 'awarded', 'screened', 'qualified',
    'outstanding', 'very satisfactory', 'pass', 'passed', 'enrolled',
    'confirmed', 'received', 'available', 'submitted to hr', 'released',
    'action taken', 'forwarded', 'published',
  ].includes(s)) return 'green'

  if ([
    'acted by mis', 'mis assessed the request',
    'submitted', 'submitted for rating', 'submitted to pmt',
    'evaluated', 'nominated', 'for review', 'under review',
    'scheduled', 'satisfactory', 'walk-in', 'in-transit',
  ].includes(s)) return 'blue'

  if ([
    'pending', 'pending approval', 'pending division chief approval',
    'pending ocd approval', 'pending fad approval',
    'draft', 'filed', 'needs improvement', 'unsatisfactory', 'under repair',
    'queued', 'for ocd review',
  ].includes(s)) return 'amber'

  if ([
    'rejected', 'declined', 'cancelled', 'returned', 'returned for revision',
    'fail', 'failed', 'disqualified', 'lost', 'damaged', 'overdue', 'dropped',
    'disposed', 'division declined', 'ocd declined',
  ].includes(s)) return 'red'

  if (['for rating', 'rated'].includes(s)) return 'purple'

  return 'slate'
}

function removeItemFromTabs(type, id) {
  const tab = localTabs.value.find(t => t.type === type)
  if (!tab) return
  const idx = tab.items.findIndex(i => i.id === id)
  if (idx !== -1) {
    tab.items.splice(idx, 1)
    tab.count = tab.items.length
  }
  // If active tab is now empty, switch to first visible tab
  if (activeTab.value === type && tab.count === 0) {
    const next = visibleTabs.value.find(t => t.type !== type)
    activeTab.value = next?.type ?? null
  }
}

// ── Modal ─────────────────────────────────────────────────────────────────────
function openModal(item) {
  selectedItem.value = item
  showDecline.value  = false
  declineReason.value = ''
  showModal.value    = true
}

function closeModal() {
  showModal.value     = false
  selectedItem.value  = null
  showDecline.value   = false
  declineReason.value = ''
}

// ── Approve ───────────────────────────────────────────────────────────────────
function confirmApprove(item) {
  pendingApproveItem.value = item
  showPinModal.value = true
}

function handlePinConfirm(pin) {
  const item = pendingApproveItem.value
  if (! item) return

  pinModalLoading.value = true
  isSubmitting.value = true
  Swal.fire({ title: 'Processing…', allowOutsideClick: false, showConfirmButton: false, didOpen: () => Swal.showLoading() })

  const payload = {}
  if (pin) payload.pin = pin

  router.post(
    route('approvals.approve', { type: item.type, id: item.id }),
    payload,
    {
      onSuccess: () => {
        removeItemFromTabs(item.type, item.id)
        closeModal()
        showPinModal.value = false
        pendingApproveItem.value = null
        Swal.fire({
          icon: 'success',
          title: 'Approved',
          text: `${item.reference_no} has been approved.`,
          timer: 2500,
          showConfirmButton: false,
        })
      },
      onError: (errors) => {
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: errors?.message ?? 'Could not approve this request. It may have already been acted upon.',
        })
      },
      onFinish: () => {
        isSubmitting.value = false
        pinModalLoading.value = false
      },
    }
  )
}

function handlePinCancel() {
  showPinModal.value = false
  pendingApproveItem.value = null
}

// ── Decline ───────────────────────────────────────────────────────────────────
function submitDecline(item) {
  if (!canSubmitDecline.value) return

  isSubmitting.value = true
  Swal.fire({ title: 'Processing…', allowOutsideClick: false, showConfirmButton: false, didOpen: () => Swal.showLoading() })

  router.post(
    route('approvals.decline', { type: item.type, id: item.id }),
    { reason: declineReason.value },
    {
      onSuccess: () => {
        removeItemFromTabs(item.type, item.id)
        closeModal()
        Swal.fire({
          icon: 'info',
          title: 'Declined',
          text: `${item.reference_no} has been declined.`,
          timer: 2500,
          showConfirmButton: false,
        })
      },
      onError: (errors) => {
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: errors?.message ?? 'Could not decline this request. It may have already been acted upon.',
        })
      },
      onFinish: () => { isSubmitting.value = false },
    }
  )
}
</script>
