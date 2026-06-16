<template>
  <Head title="OCD Approval — Gate Pass" />
  <AdminLayout title="OCD Approval — Gate Pass">
    <div class="space-y-5">

      <!-- Header -->
      <div>
        <h1 class="text-xl font-semibold text-slate-800">OCD Approval — Gate Pass</h1>
        <p class="text-sm text-slate-500 mt-0.5">Gate passes forwarded by Division Chiefs awaiting Campus Director approval.</p>
      </div>

      <!-- Search -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm px-4 py-3 flex flex-wrap items-center gap-3">
        <input v-model="search" type="text" placeholder="Search by employee name or purpose…"
               class="w-full sm:w-80 rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
        <span v-if="isLoading" class="text-xs text-slate-400">Searching…</span>
      </div>

      <!-- Table -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Control No.</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Employee</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Type</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Date</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Destination</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
                <th class="px-4 py-3"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-if="!filteredRequests.length">
                <td colspan="7" class="px-4 py-12 text-center text-slate-400 text-sm">
                  No gate passes pending OCD approval.
                </td>
              </tr>
              <tr v-for="req in filteredRequests" :key="req.id" class="hover:bg-slate-50/60">
                <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ req.controlno || '—' }}</td>
                <td class="px-4 py-3">
                  <p class="font-medium text-slate-800">{{ req.requester_name ?? '—' }}</p>
                </td>
                <td class="px-4 py-3 text-slate-700">{{ req.gatepass_type || '—' }}</td>
                <td class="px-4 py-3 text-slate-600 whitespace-nowrap">{{ req.gatepass_date || '—' }}</td>
                <td class="px-4 py-3 text-slate-600 max-w-[160px] truncate">{{ req.destination || '—' }}</td>
                <td class="px-4 py-3">
                  <span :class="[badgeBase, statusBadgeClass(req.status)]">{{ req.status }}</span>
                </td>
                <td class="px-4 py-3">
                  <div class="flex items-center gap-2">
                    <template v-if="req.status === 'Division Approved'">
                      <button @click="approveRequest(req.id)" :disabled="isSubmitting"
                              class="px-3 py-1.5 text-xs bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white rounded-lg font-medium transition-colors">
                        Approve
                      </button>
                      <button @click="openDecline(req)" :disabled="isSubmitting"
                              class="px-3 py-1.5 text-xs border border-red-200 text-red-600 hover:bg-red-50 disabled:opacity-50 rounded-lg font-medium transition-colors">
                        Decline
                      </button>
                    </template>
                    <button @click="openView(req)"
                            class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" title="View details">
                      <EyeIcon class="w-4 h-4" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="totalPages > 1" class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-sm text-slate-600">
          <span>Page {{ currentPage }} of {{ totalPages }} &bull; {{ props.requests?.total ?? 0 }} total</span>
          <div class="flex gap-2">
            <button @click="goToPage(currentPage - 1)" :disabled="currentPage === 1 || isLoading"
                    class="px-3 py-1.5 rounded-lg border border-slate-200 disabled:opacity-40 hover:bg-slate-50">Prev</button>
            <button @click="goToPage(currentPage + 1)" :disabled="currentPage === totalPages || isLoading"
                    class="px-3 py-1.5 rounded-lg border border-slate-200 disabled:opacity-40 hover:bg-slate-50">Next</button>
          </div>
        </div>
      </div>

    </div>

    <!-- View Detail Modal -->
    <Teleport to="body">
      <div v-if="viewModal && viewTarget" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <div>
              <h2 class="text-base font-semibold text-slate-800">Gate Pass Detail</h2>
              <p class="text-xs text-slate-400 font-mono mt-0.5">{{ viewTarget.controlno || '—' }}</p>
            </div>
            <div class="flex items-center gap-2">
              <span :class="[badgeBase, statusBadgeClass(viewTarget.status)]">{{ viewTarget.status }}</span>
              <button @click="viewModal = false" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4 shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg></button>
            </div>
          </div>
          <div class="px-6 py-5 space-y-3 text-sm">
            <div class="grid grid-cols-2 gap-x-6 gap-y-3">
              <div>
                <p class="text-xs text-slate-400 mb-0.5">Employee</p>
                <p class="font-medium text-slate-800">{{ viewTarget.requester_name ?? '—' }}</p>
              </div>
              <div>
                <p class="text-xs text-slate-400 mb-0.5">Type</p>
                <p class="font-medium text-slate-800">{{ viewTarget.gatepass_type || '—' }}</p>
              </div>
              <div>
                <p class="text-xs text-slate-400 mb-0.5">Date</p>
                <p class="font-medium text-slate-800">{{ viewTarget.gatepass_date || '—' }}</p>
              </div>
              <div>
                <p class="text-xs text-slate-400 mb-0.5">Time Out → In</p>
                <p class="font-medium text-slate-800">{{ viewTarget.gatepass_timeout || '—' }} → {{ viewTarget.gatepass_timein || '—' }}</p>
              </div>
              <div class="col-span-2">
                <p class="text-xs text-slate-400 mb-0.5">Destination</p>
                <p class="font-medium text-slate-800">{{ viewTarget.destination || '—' }}</p>
              </div>
              <div class="col-span-2">
                <p class="text-xs text-slate-400 mb-0.5">Purpose</p>
                <p class="text-slate-700">{{ viewTarget.purpose || '—' }}</p>
              </div>
              <div v-if="viewTarget.remarks" class="col-span-2">
                <p class="text-xs text-slate-400 mb-0.5">Remarks</p>
                <p class="text-slate-600 italic">{{ viewTarget.remarks }}</p>
              </div>
            </div>
          </div>
          <div class="px-6 py-4 border-t border-slate-100 flex justify-between">
            <div class="flex gap-2" v-if="viewTarget.status === 'Division Approved'">
              <button @click="approveRequest(viewTarget.id); viewModal = false" :disabled="isSubmitting"
                      class="px-4 py-2 text-sm bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white rounded-lg transition-colors font-medium">
                Approve
              </button>
              <button @click="openDecline(viewTarget); viewModal = false" :disabled="isSubmitting"
                      class="px-4 py-2 text-sm border border-red-200 text-red-600 hover:bg-red-50 rounded-lg transition-colors font-medium">
                Decline
              </button>
            </div>
            <span v-else></span>
            <button @click="viewModal = false"
                    class="px-4 py-2 text-sm text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50">Close</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Decline Modal -->
    <Teleport to="body">
      <div v-if="declineModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-800">Decline Gate Pass</h2>
            <button @click="declineModal = false" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4 shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg></button>
          </div>
          <div class="px-6 py-5">
            <p class="text-sm text-slate-600 mb-3">
              Declining gate pass from <span class="font-medium">{{ declineTarget?.requester_name }}</span>.
            </p>
            <label class="block text-xs font-medium text-slate-600 mb-1">Reason <span class="text-red-500">*</span></label>
            <textarea v-model="declineReason" rows="3" placeholder="Provide a reason for declining…"
                      class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 resize-none"></textarea>
          </div>
          <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-3">
            <button @click="declineModal = false" :disabled="isSubmitting"
                    class="px-4 py-2 text-sm text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50">Cancel</button>
            <button @click="submitDecline" :disabled="isSubmitting || !declineReason.trim()"
                    class="px-4 py-2 text-sm bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white rounded-lg transition-colors font-medium">
              {{ isSubmitting ? 'Declining…' : 'Confirm Decline' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

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
import { ref, computed, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { EyeIcon } from '@heroicons/vue/24/outline'
import { statusBadgeClass, badgeBase } from '@/Composables/useStatusBadge.js'
import DigitalSignaturePin from '@/Components/DigitalSignaturePin.vue'

const props = defineProps({
  requests:      Object,
  filters:       Object,
  has_pin:       { type: Boolean, default: false },
  signature_uri: { type: String,  default: null },
})

const search      = ref(props.filters?.search ?? '')
const isLoading   = ref(false)
const isSubmitting = ref(false)
let debounceTimer  = null

watch(search, () => {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => {
    isLoading.value = true
    router.get(route('gatepass.ocd-approval'), { search: search.value || undefined }, {
      preserveState: true, replace: true,
      only: ['requests', 'filters'],
      onFinish: () => { isLoading.value = false },
    })
  }, 400)
})

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
