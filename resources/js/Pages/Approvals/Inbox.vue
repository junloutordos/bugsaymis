<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { EyeIcon, CheckCircleIcon, XCircleIcon, XMarkIcon } from '@heroicons/vue/24/outline'
import Swal from 'sweetalert2'
import { statusBadgeClass, badgeBase } from '@/Composables/useStatusBadge.js'
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

<template>
  <Head title="Approvals Inbox" />
  <AdminLayout title="Approvals Inbox">
    <div>
      <!-- Page header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Approvals Inbox</h1>
          <p class="text-sm text-slate-500 mt-0.5">All pending requests awaiting your approval</p>
        </div>
        <div v-if="totalCount > 0" class="inline-flex items-center gap-1.5 bg-indigo-50 text-indigo-700 px-3 py-1.5 rounded-full text-sm font-medium">
          {{ totalCount }} pending
        </div>
      </div>

      <!-- Empty state -->
      <div v-if="visibleTabs.length === 0" class="bg-white rounded-xl border border-slate-100 shadow-sm py-20 text-center">
        <CheckCircleIcon class="w-12 h-12 text-emerald-400 mx-auto mb-3" />
        <p class="text-slate-600 font-medium">All clear!</p>
        <p class="text-slate-400 text-sm mt-1">No pending requests require your approval.</p>
      </div>

      <template v-else>
        <!-- Tab bar -->
        <div class="flex flex-wrap gap-2 mb-4">
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
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 mb-4">
          <input
            v-model="search"
            type="text"
            placeholder="Search by requestor, reference no., or summary…"
            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"
          />
        </div>

        <!-- Table -->
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-sm">
              <thead class="bg-slate-50">
                <tr>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">#</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Requestor</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Reference No.</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Summary</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Filed At</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
                  <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Review</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="(item, idx) in filteredItems" :key="item.id" class="hover:bg-slate-50/60">
                  <td class="px-4 py-3 text-slate-500 text-xs">{{ idx + 1 }}</td>
                  <td class="px-4 py-3 text-slate-700">{{ item.requester_name }}</td>
                  <td class="px-4 py-3 text-slate-700 font-mono text-xs">{{ item.reference_no }}</td>
                  <td class="px-4 py-3 text-slate-700 max-w-xs truncate">{{ item.summary }}</td>
                  <td class="px-4 py-3 text-slate-500 text-xs whitespace-nowrap">{{ formatDate(item.filed_at) }}</td>
                  <td class="px-4 py-3">
                    <span :class="[badgeBase, statusBadgeClass(item.status)]">{{ item.status }}</span>
                  </td>
                  <td class="px-4 py-3 text-center">
                    <button
                      @click="openModal(item)"
                      class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium transition-colors"
                      title="View details and take action"
                    >
                      <EyeIcon class="w-3.5 h-3.5" />
                      Review
                    </button>
                  </td>
                </tr>
                <tr v-if="filteredItems.length === 0">
                  <td colspan="7" class="py-16 text-center text-slate-400 text-sm">
                    {{ search ? 'No results match your search.' : 'No pending items in this tab.' }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
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
            <button @click="closeModal" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors">
              <XMarkIcon class="w-4 h-4" />
            </button>
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
                <p class="mt-0.5"><span :class="[badgeBase, statusBadgeClass(selectedItem.status)]">{{ selectedItem.status }}</span></p>
              </div>
              <div>
                <span class="text-xs font-medium text-slate-500 uppercase tracking-wide">Summary</span>
                <p class="mt-0.5 text-slate-700">{{ selectedItem.summary }}</p>
              </div>
            </div>

            <!-- Dynamic sections per request type -->
            <template v-for="section in (selectedItem.sections ?? [])" :key="section.title">
              <div class="border-t border-slate-100 pt-4 mb-4">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">{{ section.title }}</p>
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
                Reason for Decline <span class="text-red-500">*</span>
              </label>
              <textarea
                v-model="declineReason"
                rows="3"
                placeholder="Provide a reason for declining this request…"
                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400"
              ></textarea>
            </div>
          </div>

          <!-- Footer -->
          <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-end gap-2 flex-shrink-0">
            <template v-if="!showDecline">
              <button
                @click="closeModal"
                class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors"
              >
                Close
              </button>
              <button
                @click="showDecline = true"
                :disabled="isSubmitting"
                class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors disabled:opacity-50"
              >
                <XCircleIcon class="w-4 h-4" /> Decline
              </button>
              <button
                @click="confirmApprove(selectedItem)"
                :disabled="isSubmitting"
                class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors disabled:opacity-50"
              >
                <CheckCircleIcon class="w-4 h-4" />
                {{ isSubmitting ? 'Processing…' : 'Approve' }}
              </button>
            </template>
            <template v-else>
              <button
                @click="showDecline = false; declineReason = ''"
                class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors"
              >
                Cancel
              </button>
              <button
                @click="submitDecline(selectedItem)"
                :disabled="!canSubmitDecline || isSubmitting"
                class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors"
              >
                {{ isSubmitting ? 'Declining…' : 'Submit Decline' }}
              </button>
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
