<template>
  <Head title="Gate Pass" />
  <AdminLayout title="Gate Pass">
    <div class="space-y-5">

      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">
            {{ isDivisionChief ? 'Division Gate Pass Requests' : 'Gate Pass' }}
          </h1>
          <p class="text-sm text-slate-500 mt-0.5">
            {{ isDivisionChief ? 'Review and act on pending gate pass requests from your division.' : 'Manage your gate pass applications.' }}
          </p>
        </div>
        <button v-if="!isDivisionChief" @click="openAdd"
                class="inline-flex items-center gap-2 px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition-colors font-medium">
          <PlusIcon class="w-4 h-4" /> New Gate Pass
        </button>
      </div>

      <!-- Status filter tabs -->
      <div class="flex flex-wrap gap-2">
        <button v-for="s in statusOptions" :key="s.value"
                @click="activeStatus = s.value; currentPage = 1"
                :class="[
                  'px-3 py-1.5 rounded-full text-xs font-medium border transition-colors',
                  activeStatus === s.value
                    ? 'bg-indigo-600 text-white border-indigo-600'
                    : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'
                ]">
          {{ s.label }}
          <span v-if="s.value !== ''" class="ml-1 opacity-70">({{ countByStatus(s.value) }})</span>
        </button>
      </div>

      <!-- Search -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm px-4 py-3">
        <input v-model="searchQuery" placeholder="Search by name, control no, destination, purpose…"
               class="w-full sm:w-80 rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
      </div>

      <!-- Table -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Control No.</th>
                <th v-if="!isSelf" class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Employee</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Type</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Date</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Time Out / In</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Destination</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Purpose</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
                <th class="px-4 py-3"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-if="!paginated.length">
                <td :colspan="isSelf ? 8 : 9" class="px-4 py-12 text-center text-slate-400 text-sm">No gate passes found.</td>
              </tr>
              <tr v-for="r in paginated" :key="r.id" class="hover:bg-slate-50/60">
                <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ r.controlno || '—' }}</td>
                <td v-if="!isSelf" class="px-4 py-3">
                  <p class="font-medium text-slate-800">{{ r.name || '—' }}</p>
                  <p class="text-xs text-slate-400">{{ r.position || '' }}</p>
                </td>
                <td class="px-4 py-3 text-slate-700">{{ r.gatepass_type || '—' }}</td>
                <td class="px-4 py-3 text-slate-600 whitespace-nowrap">{{ r.gatepass_date || '—' }}</td>
                <td class="px-4 py-3 text-slate-600 whitespace-nowrap text-xs">
                  <div>{{ r.gatepass_timeout || '—' }} → {{ r.gatepass_timein || '—' }}</div>
                  <div v-if="r.actual_timeout || r.actual_timein" class="text-emerald-600 font-medium mt-0.5">
                    Actual: {{ r.actual_timeout || '—' }} → {{ r.actual_timein || '—' }}
                  </div>
                </td>
                <td class="px-4 py-3 text-slate-700 max-w-[160px] truncate">{{ r.destination || '—' }}</td>
                <td class="px-4 py-3 text-slate-600 max-w-[200px] truncate">{{ r.purpose || '—' }}</td>
                <td class="px-4 py-3">
                  <span :class="[badgeBase, statusBadgeClass(r.status)]">{{ r.status || '—' }}</span>
                </td>
                <td class="px-4 py-3">
                  <div class="flex items-center gap-1">
                    <!-- Division Chief actions -->
                    <template v-if="isDivisionChief && r.status === 'Pending'">
                      <button @click="approveDC(r)"
                              class="px-3 py-1.5 text-xs bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium transition-colors">
                        Approve
                      </button>
                      <button @click="openDecline(r)"
                              class="px-3 py-1.5 text-xs border border-red-200 text-red-600 hover:bg-red-50 rounded-lg font-medium transition-colors">
                        Decline
                      </button>
                    </template>

                    <!-- Employee/Admin actions -->
                    <template v-else-if="!isDivisionChief">
                      <button v-if="r.status === 'Pending'" @click="openEdit(r)"
                              class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-indigo-600 transition-colors" title="Edit">
                        <PencilSquareIcon class="w-4 h-4" />
                      </button>
                      <button v-if="isAdmin && r.status === 'OCD Approved'" @click="openActual(r)"
                              class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-emerald-600 transition-colors" title="Record Actual Time">
                        <ClockIcon class="w-4 h-4" />
                      </button>
                      <button v-if="r.status === 'OCD Approved'" @click="printGatepass(r)"
                              class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" title="Print">
                        <PrinterIcon class="w-4 h-4" />
                      </button>
                      <button v-if="r.status === 'Pending' || isAdmin" @click="confirmDelete(r)"
                              class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-red-600 transition-colors" title="Delete">
                        <TrashIcon class="w-4 h-4" />
                      </button>
                    </template>

                    <!-- View detail button for everyone -->
                    <button @click="openView(r)"
                            class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" title="View">
                      <EyeIcon class="w-4 h-4" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-sm text-slate-600">
          <span>Page {{ currentPage }} of {{ totalPages }} &bull; {{ filtered.length }} total</span>
          <div class="flex gap-2">
            <button @click="currentPage--" :disabled="currentPage === 1"
                    class="px-3 py-1.5 rounded-lg border border-slate-200 disabled:opacity-40 hover:bg-slate-50">Prev</button>
            <button @click="currentPage++" :disabled="currentPage >= totalPages"
                    class="px-3 py-1.5 rounded-lg border border-slate-200 disabled:opacity-40 hover:bg-slate-50">Next</button>
          </div>
        </div>
      </div>

    </div>

    <!-- Add / Edit Modal -->
    <Teleport to="body">
      <div v-if="formModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-800">{{ editingId ? 'Edit Gate Pass' : 'New Gate Pass' }}</h2>
            <button @click="formModal = false" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4 shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg></button>
          </div>
          <div class="px-6 py-5 space-y-4">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Type <span class="text-red-500">*</span></label>
              <select v-model="form.gatepass_type"
                      class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                <option value="">— Select type —</option>
                <option value="Official Business">Official Business</option>
                <option value="Personal">Personal</option>
                <option value="Office Time">Office Time</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Date <span class="text-red-500">*</span></label>
              <input v-model="form.gatepass_date" type="date"
                     class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Time Out</label>
                <input v-model="form.gatepass_timeout" type="time"
                       class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Time In (Return)</label>
                <input v-model="form.gatepass_timein" type="time"
                       class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
              </div>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Destination <span class="text-red-500">*</span></label>
              <input v-model="form.destination" type="text" placeholder="Where are you going?"
                     class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Purpose <span class="text-red-500">*</span></label>
              <textarea v-model="form.purpose" rows="3" placeholder="Reason for leaving…"
                        class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 resize-none"></textarea>
            </div>
          </div>
          <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-3">
            <button @click="formModal = false" :disabled="saving"
                    class="px-4 py-2 text-sm text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50">Cancel</button>
            <button @click="editingId ? saveForm() : openPinModal()" :disabled="saving"
                    class="px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white rounded-lg transition-colors font-medium">
              {{ saving ? 'Saving…' : (editingId ? 'Update' : 'Submit') }}
            </button>
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
              Declining gate pass <span class="font-medium">{{ declineTarget?.controlno }}</span> for <span class="font-medium">{{ declineTarget?.name }}</span>.
            </p>
            <label class="block text-xs font-medium text-slate-600 mb-1">Reason <span class="text-red-500">*</span></label>
            <textarea v-model="declineReason" rows="3" placeholder="Provide a reason for declining…"
                      class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 resize-none"></textarea>
          </div>
          <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-3">
            <button @click="declineModal = false" :disabled="saving"
                    class="px-4 py-2 text-sm text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50">Cancel</button>
            <button @click="submitDecline" :disabled="saving || !declineReason.trim()"
                    class="px-4 py-2 text-sm bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white rounded-lg transition-colors font-medium">
              {{ saving ? 'Declining…' : 'Confirm Decline' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

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
                <p class="font-medium text-slate-800">{{ viewTarget.name || '—' }}</p>
                <p class="text-xs text-slate-400">{{ viewTarget.position || '' }}</p>
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
                <p v-if="viewTarget.actual_timeout || viewTarget.actual_timein" class="text-xs text-emerald-600 mt-0.5">
                  Actual: {{ viewTarget.actual_timeout || '—' }} → {{ viewTarget.actual_timein || '—' }}
                </p>
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
                <p class="text-xs text-slate-400 mb-0.5">Remarks / Decline Reason</p>
                <p class="text-slate-600 italic">{{ viewTarget.remarks }}</p>
              </div>
              <div v-if="viewTarget.date_time_approved">
                <p class="text-xs text-slate-400 mb-0.5">Date Approved</p>
                <p class="text-slate-700">{{ viewTarget.date_time_approved }}</p>
              </div>
            </div>
          </div>
          <div class="px-6 py-4 border-t border-slate-100 flex justify-between">
            <button v-if="viewTarget.status === 'OCD Approved'" @click="printGatepass(viewTarget)"
                    class="inline-flex items-center gap-2 px-4 py-2 text-sm border border-slate-200 text-slate-600 hover:bg-slate-50 rounded-lg">
              <PrinterIcon class="w-4 h-4" /> Print
            </button>
            <span v-else></span>
            <button @click="viewModal = false"
                    class="px-4 py-2 text-sm text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50">Close</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Record Actual Time Modal -->
    <Teleport to="body">
      <div v-if="actualModal && actualTarget" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm mx-4">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <div>
              <h2 class="text-base font-semibold text-slate-800">Record Actual Time</h2>
              <p class="text-xs text-slate-400 mt-0.5">{{ actualTarget.name }} — {{ actualTarget.controlno }}</p>
            </div>
            <button @click="actualModal = false" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4 shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg></button>
          </div>
          <div class="px-6 py-5 space-y-4">
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Actual Time Out</label>
                <input v-model="actualForm.actual_timeout" type="time"
                       class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-400" />
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Actual Time In</label>
                <input v-model="actualForm.actual_timein" type="time"
                       class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-400" />
              </div>
            </div>
            <p class="text-xs text-slate-400">
              Requested: <span class="font-mono font-medium text-slate-600">{{ actualTarget.gatepass_timeout || '—' }} → {{ actualTarget.gatepass_timein || '—' }}</span>
            </p>
          </div>
          <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-3">
            <button @click="actualModal = false" :disabled="saving"
                    class="px-4 py-2 text-sm text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50">Cancel</button>
            <button @click="submitActual" :disabled="saving"
                    class="px-4 py-2 text-sm bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white rounded-lg transition-colors font-medium">
              {{ saving ? 'Saving…' : 'Save' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Delete Confirm Modal -->
    <Teleport to="body">
      <div v-if="deleteTarget" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm mx-4 p-6">
          <h3 class="text-base font-semibold text-slate-800 mb-2">Delete Gate Pass</h3>
          <p class="text-sm text-slate-600 mb-5">Remove gate pass <strong>{{ deleteTarget.controlno }}</strong>? This cannot be undone.</p>
          <div class="flex gap-3 justify-end">
            <button @click="deleteTarget = null"
                    class="px-4 py-2 text-sm text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50">Cancel</button>
            <button @click="doDelete" :disabled="saving"
                    class="px-4 py-2 text-sm bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white rounded-lg transition-colors">Delete</button>
          </div>
        </div>
      </div>
    </Teleport>

    <DigitalSignaturePin
      :show="showSubmitPin"
      :hasPin="hasPin"
      :signatureUri="signatureUri"
      :loading="pinLoading"
      confirmLabel="Sign & Submit"
      @confirm="handlePinConfirm"
      @cancel="handlePinCancel"
    />
  </AdminLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { Head, usePage, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { PlusIcon, PencilSquareIcon, TrashIcon, PrinterIcon, EyeIcon, ClockIcon } from '@heroicons/vue/24/outline'
import { statusBadgeClass, badgeBase } from '@/Composables/useStatusBadge.js'
import DigitalSignaturePin from '@/Components/DigitalSignaturePin.vue'

const page        = usePage()
const rows        = computed(() => page.props.rows ?? [])
const hasPin      = computed(() => page.props.hasPin ?? false)
const signatureUri = computed(() => page.props.signatureUri ?? null)
const currentUser = computed(() => page.props.auth?.user ?? {})
const isSelf      = computed(() => ['Staff', 'Faculty'].includes(currentUser.value.role?.name ?? ''))
const isDivisionChief = computed(() => currentUser.value.role?.name === 'DivisionChief')
const isAdmin     = computed(() => (currentUser.value.role?.name ?? '').toLowerCase() === 'administrator')

// ── Filters ──────────────────────────────────────────────────────────────────
const statusOptions = [
  { value: '',                  label: 'All' },
  { value: 'Pending',           label: 'Pending' },
  { value: 'Division Approved', label: 'Div. Approved' },
  { value: 'OCD Approved',      label: 'OCD Approved' },
  { value: 'Division Declined', label: 'Div. Declined' },
  { value: 'OCD Declined',      label: 'OCD Declined' },
]
const activeStatus = ref('')
const searchQuery  = ref('')
const currentPage  = ref(1)
const perPage      = 10

function countByStatus(status) {
  return rows.value.filter(r => r.status === status).length
}

const filtered = computed(() => {
  let list = rows.value
  if (activeStatus.value) list = list.filter(r => r.status === activeStatus.value)
  const q = searchQuery.value.trim().toLowerCase()
  if (q) {
    list = list.filter(r =>
      [r.controlno, r.name, r.gatepass_type, r.destination, r.purpose, r.status]
        .join(' ').toLowerCase().includes(q)
    )
  }
  return list
})

const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / perPage)))
const paginated  = computed(() => {
  const start = (currentPage.value - 1) * perPage
  return filtered.value.slice(start, start + perPage)
})
watch([searchQuery, activeStatus], () => { currentPage.value = 1 })

// ── Add / Edit form ───────────────────────────────────────────────────────────
const formModal = ref(false)
const editingId = ref(null)
const saving    = ref(false)
const form      = ref({ gatepass_type: '', gatepass_date: '', gatepass_timeout: '', gatepass_timein: '', destination: '', purpose: '' })

const showSubmitPin = ref(false)
const pinLoading = ref(false)

const openPinModal = () => { showSubmitPin.value = true }
const handlePinCancel = () => { showSubmitPin.value = false }
const handlePinConfirm = async (pin) => {
  form.value.pin = pin || null
  showSubmitPin.value = false
  await saveForm()
}

function openAdd() {
  editingId.value = null
  form.value = { gatepass_type: '', gatepass_date: '', gatepass_timeout: '', gatepass_timein: '', destination: '', purpose: '' }
  formModal.value = true
}

function openEdit(r) {
  editingId.value = r.id
  form.value = {
    gatepass_type:    r.gatepass_type    ?? '',
    gatepass_date:    r.gatepass_date    ?? '',
    gatepass_timeout: r.gatepass_timeout ?? '',
    gatepass_timein:  r.gatepass_timein  ?? '',
    destination:      r.destination      ?? '',
    purpose:          r.purpose          ?? '',
  }
  formModal.value = true
}

async function saveForm() {
  if (saving.value) return
  saving.value = true
  const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content')
  const url    = editingId.value ? `/hr/gatepass/${editingId.value}` : '/hr/gatepass'
  const method = editingId.value ? 'PUT' : 'POST'
  try {
    const res = await fetch(url, {
      method,
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
      body: JSON.stringify(form.value),
    })
    if (res.ok) {
      formModal.value = false
      router.reload({ only: ['rows'] })
    } else {
      const data = await res.json().catch(() => ({}))
      alert(Object.values(data.errors ?? {}).flat().join('\n') || 'Save failed')
    }
  } catch (e) { alert(e.message || 'Save failed') }
  finally { saving.value = false }
}

// ── Delete ────────────────────────────────────────────────────────────────────
const deleteTarget = ref(null)
function confirmDelete(r) { deleteTarget.value = r }
async function doDelete() {
  if (saving.value) return
  saving.value = true
  const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content')
  try {
    const res = await fetch(`/hr/gatepass/${deleteTarget.value.id}`, {
      method: 'DELETE',
      headers: { 'X-CSRF-TOKEN': csrf },
    })
    if (res.ok) {
      deleteTarget.value = null
      router.reload({ only: ['rows'] })
    }
  } catch (e) { alert(e.message || 'Delete failed') }
  finally { saving.value = false }
}

// ── Division Chief approve ────────────────────────────────────────────────────
async function approveDC(r) {
  if (saving.value) return
  saving.value = true
  const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content')
  try {
    const res = await fetch(`/hr/gatepass/${r.id}`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
      body: JSON.stringify({ status: 'Division Approved', date_time_approved: new Date().toISOString() }),
    })
    if (res.ok) {
      router.reload({ only: ['rows'] })
    } else {
      const body = await res.json().catch(() => ({}))
      alert(body.message || `Approval failed (HTTP ${res.status}). Please try again.`)
    }
  } catch (e) { alert(e.message || 'Approve failed') }
  finally { saving.value = false }
}

// ── Division Chief decline ────────────────────────────────────────────────────
const declineModal  = ref(false)
const declineTarget = ref(null)
const declineReason = ref('')

function openDecline(r) {
  declineTarget.value = r
  declineReason.value = ''
  declineModal.value  = true
}

async function submitDecline() {
  if (!declineReason.value.trim() || saving.value) return
  saving.value = true
  const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content')
  try {
    const res = await fetch(`/hr/gatepass/${declineTarget.value.id}`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
      body: JSON.stringify({ status: 'Division Declined', decline_reason: declineReason.value, date_time_declined: new Date().toISOString() }),
    })
    if (res.ok) {
      declineModal.value = false
      router.reload({ only: ['rows'] })
    }
  } catch (e) { alert(e.message || 'Decline failed') }
  finally { saving.value = false }
}

// ── View detail ───────────────────────────────────────────────────────────────
const viewModal  = ref(false)
const viewTarget = ref(null)
function openView(r) { viewTarget.value = r; viewModal.value = true }

// ── Record Actual Times (Admin/HR only) ───────────────────────────────────────
const actualModal  = ref(false)
const actualTarget = ref(null)
const actualForm   = ref({ actual_timeout: '', actual_timein: '' })

function openActual(r) {
  actualTarget.value = r
  actualForm.value = {
    actual_timeout: r.actual_timeout ?? '',
    actual_timein:  r.actual_timein  ?? '',
  }
  actualModal.value = true
}

async function submitActual() {
  if (saving.value) return
  saving.value = true
  const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content')
  try {
    const res = await fetch(`/hr/gatepass/${actualTarget.value.id}`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
      body: JSON.stringify(actualForm.value),
    })
    if (res.ok) {
      actualModal.value = false
      router.reload({ only: ['rows'] })
    } else {
      const data = await res.json().catch(() => ({}))
      alert(Object.values(data.errors ?? {}).flat().join('\n') || 'Save failed')
    }
  } catch (e) { alert(e.message || 'Save failed') }
  finally { saving.value = false }
}

// ── Print ─────────────────────────────────────────────────────────────────────
function printGatepass(r) {
  window.open(`/hr/gatepass/${r.id}/print`, '_blank')
}
</script>
