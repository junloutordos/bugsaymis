<template>
  <Head title="Gate Pass" />
  <AdminLayout title="Gate Pass">
    <div class="space-y-5">

      <AppPageHeader
        :title="isDivisionChief ? 'Division Gate Pass Requests' : 'Gate Pass'"
        :subtitle="isDivisionChief ? 'Review and act on pending gate pass requests from your division.' : 'Manage your gate pass applications.'"
      >
        <template #actions>
          <AppButton v-if="!isDivisionChief" @click="openAdd">
            <PlusIcon class="w-4 h-4" /> New Gate Pass
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Search -->
      <AppFilterBar>
        <input v-model="searchQuery" placeholder="Search by name, control no, destination, purpose…"
               @keydown.enter.prevent="applyFilters"
               class="w-full sm:w-80 rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
        <select v-model="activeStatus"
                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
          <option v-for="s in statusOptions" :key="s.value" :value="s.value">
            {{ s.label }}{{ s.value ? ` (${countByStatus(s.value)})` : '' }}
          </option>
        </select>
        <template #actions>
          <AppButton size="sm" @click="applyFilters">Search</AppButton>
          <AppButton v-if="searchQuery || activeStatus" size="sm" variant="secondary" @click="clearFilters">Clear</AppButton>
        </template>
      </AppFilterBar>

      <!-- Table -->
      <AppTable :is-empty="!paginated.length" :skeleton-cols="isSelf ? 8 : 9">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Control No.</th>
            <th v-if="!isSelf" class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Employee</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Type</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Date</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Time Out / In</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Destination</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Purpose</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Status</th>
            <th class="px-4 py-3"></th>
          </tr>
        </template>

        <tr v-for="r in paginated" :key="r.id" class="hover:bg-indigo-50/40">
          <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ r.controlno || '—' }}</td>
          <td v-if="!isSelf" class="px-4 py-3">
            <p class="font-medium text-slate-800">{{ r.name || '—' }}</p>
            <p class="text-xs text-slate-400">{{ r.position || '' }}</p>
          </td>
          <td class="px-4 py-3 text-slate-700">{{ r.gatepass_type || '—' }}</td>
          <td class="px-4 py-3 text-slate-600 whitespace-nowrap">{{ r.gatepass_date || '—' }}</td>
          <td class="px-4 py-3 text-slate-600 whitespace-nowrap text-xs">
            <div>{{ r.gatepass_timeout || '—' }} → {{ r.gatepass_timein || '—' }}</div>
            <div v-if="r.actual_timeout || r.actual_timein" class="text-success-600 font-medium mt-0.5">
              Actual: {{ r.actual_timeout || '—' }} → {{ r.actual_timein || '—' }}
            </div>
          </td>
          <td class="px-4 py-3 text-slate-700 max-w-[160px] truncate">{{ r.destination || '—' }}</td>
          <td class="px-4 py-3 text-slate-600 max-w-[200px] truncate">{{ r.purpose || '—' }}</td>
          <td class="px-4 py-3">
            <AppBadge :color="statusColor(r.status)">{{ r.status || '—' }}</AppBadge>
          </td>
          <td class="px-4 py-3">
            <div class="flex items-center gap-1">
              <!-- Division Chief actions -->
              <template v-if="isDivisionChief && r.status === 'Pending'">
                <AppButton size="sm" variant="success" @click="approveDC(r)">Approve</AppButton>
                <AppButton size="sm" variant="danger" @click="openDecline(r)">Decline</AppButton>
              </template>

              <!-- Employee/Admin actions -->
              <template v-else-if="!isDivisionChief">
                <AppIconButton v-if="r.status === 'Pending'" label="Edit gate pass" @click="openEdit(r)">
                  <PencilSquareIcon class="w-4 h-4" />
                </AppIconButton>
                <AppIconButton v-if="canRecordActualTime && r.status === 'OCD Approved'" label="Record actual time" variant="success" @click="openActual(r)">
                  <ClockIcon class="w-4 h-4" />
                </AppIconButton>
                <AppIconButton v-if="r.status === 'OCD Approved'" label="Print gate pass" @click="printGatepass(r)">
                  <PrinterIcon class="w-4 h-4" />
                </AppIconButton>
                <AppIconButton v-if="r.status === 'Pending' || isAdmin" label="Delete gate pass" variant="danger" @click="confirmDelete(r)">
                  <TrashIcon class="w-4 h-4" />
                </AppIconButton>
              </template>

              <!-- View detail button for everyone -->
              <AppIconButton label="View gate pass detail" @click="openView(r)">
                <EyeIcon class="w-4 h-4" />
              </AppIconButton>
            </div>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="r in paginated" :key="r.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0">
                <p class="font-mono text-xs text-slate-500">{{ r.controlno || '—' }}</p>
                <p v-if="!isSelf" class="font-medium text-slate-800">{{ r.name || '—' }}</p>
                <p class="text-sm text-slate-700">{{ r.gatepass_type || '—' }} &middot; {{ r.gatepass_date || '—' }}</p>
              </div>
              <AppBadge :color="statusColor(r.status)">{{ r.status || '—' }}</AppBadge>
            </div>
            <p class="text-xs text-slate-500">{{ r.gatepass_timeout || '—' }} → {{ r.gatepass_timein || '—' }}</p>
            <p v-if="r.actual_timeout || r.actual_timein" class="text-xs text-success-600 font-medium">
              Actual: {{ r.actual_timeout || '—' }} → {{ r.actual_timein || '—' }}
            </p>
            <p class="text-xs text-slate-600 truncate">{{ r.destination || '—' }}</p>
            <div class="flex flex-wrap items-center gap-2 pt-1">
              <template v-if="isDivisionChief && r.status === 'Pending'">
                <AppButton size="sm" variant="success" @click="approveDC(r)">Approve</AppButton>
                <AppButton size="sm" variant="danger" @click="openDecline(r)">Decline</AppButton>
              </template>
              <template v-else-if="!isDivisionChief">
                <AppIconButton v-if="r.status === 'Pending'" label="Edit gate pass" @click="openEdit(r)">
                  <PencilSquareIcon class="w-4 h-4" />
                </AppIconButton>
                <AppIconButton v-if="canRecordActualTime && r.status === 'OCD Approved'" label="Record actual time" variant="success" @click="openActual(r)">
                  <ClockIcon class="w-4 h-4" />
                </AppIconButton>
                <AppIconButton v-if="r.status === 'OCD Approved'" label="Print gate pass" @click="printGatepass(r)">
                  <PrinterIcon class="w-4 h-4" />
                </AppIconButton>
                <AppIconButton v-if="r.status === 'Pending' || isAdmin" label="Delete gate pass" variant="danger" @click="confirmDelete(r)">
                  <TrashIcon class="w-4 h-4" />
                </AppIconButton>
              </template>
              <AppIconButton label="View gate pass detail" @click="openView(r)">
                <EyeIcon class="w-4 h-4" />
              </AppIconButton>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No gate passes found" />
        </template>

        <template #footer>
          <PaginationControl
            :current-page="currentPage"
            :total-pages="totalPages"
            :total="filtered.length"
            @prev="goToPage(currentPage - 1)"
            @next="goToPage(currentPage + 1)"
            @page="goToPage"
          />
        </template>
      </AppTable>

    </div>

    <!-- Add / Edit Modal -->
    <AppModal :show="formModal" :title="editingId ? 'Edit Gate Pass' : 'New Gate Pass'" size="lg" @close="formModal = false">
      <div class="space-y-4">
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Type <span class="text-danger-500">*</span></label>
          <select v-model="form.gatepass_type"
                  class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
            <option value="">— Select type —</option>
            <option value="Official Business">Official Business</option>
            <option value="Personal">Personal</option>
            <option value="Office Time">Office Time</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Date <span class="text-danger-500">*</span></label>
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
          <label class="block text-xs font-medium text-slate-600 mb-1">Destination <span class="text-danger-500">*</span></label>
          <input v-model="form.destination" type="text" placeholder="Where are you going?"
                 class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Purpose <span class="text-danger-500">*</span></label>
          <textarea v-model="form.purpose" rows="3" placeholder="Reason for leaving…"
                    class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 resize-none"></textarea>
        </div>
      </div>
      <template #footer>
        <AppButton variant="secondary" :disabled="saving" @click="formModal = false">Cancel</AppButton>
        <AppButton :loading="saving" @click="editingId ? saveForm() : openPinModal()">
          {{ editingId ? 'Update' : 'Submit' }}
        </AppButton>
      </template>
    </AppModal>

    <!-- Decline Modal -->
    <AppModal :show="declineModal" title="Decline Gate Pass" @close="declineModal = false">
      <p class="text-sm text-slate-600 mb-3">
        Declining gate pass <span class="font-medium">{{ declineTarget?.controlno }}</span> for <span class="font-medium">{{ declineTarget?.name }}</span>.
      </p>
      <label class="block text-xs font-medium text-slate-600 mb-1">Reason <span class="text-danger-500">*</span></label>
      <textarea v-model="declineReason" rows="3" placeholder="Provide a reason for declining…"
                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 resize-none"></textarea>
      <template #footer>
        <AppButton variant="secondary" :disabled="saving" @click="declineModal = false">Cancel</AppButton>
        <AppButton variant="danger" :loading="saving" :disabled="!declineReason.trim()" @click="submitDecline">
          Confirm Decline
        </AppButton>
      </template>
    </AppModal>

    <!-- View Detail Modal -->
    <AppModal :show="viewModal && !!viewTarget" title="Gate Pass Detail" :subtitle="viewTarget?.controlno || '—'" @close="viewModal = false">
      <template #header>
        <AppBadge :color="statusColor(viewTarget?.status)">{{ viewTarget?.status }}</AppBadge>
      </template>
      <div class="space-y-3 text-sm">
        <div class="grid grid-cols-2 gap-x-6 gap-y-3">
          <div>
            <p class="text-xs text-slate-400 mb-0.5">Employee</p>
            <p class="font-medium text-slate-800">{{ viewTarget?.name || '—' }}</p>
            <p class="text-xs text-slate-400">{{ viewTarget?.position || '' }}</p>
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
            <p v-if="viewTarget?.actual_timeout || viewTarget?.actual_timein" class="text-xs text-success-600 mt-0.5">
              Actual: {{ viewTarget?.actual_timeout || '—' }} → {{ viewTarget?.actual_timein || '—' }}
            </p>
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
            <p class="text-xs text-slate-400 mb-0.5">Remarks / Decline Reason</p>
            <p class="text-slate-600 italic">{{ viewTarget?.remarks }}</p>
          </div>
          <div v-if="viewTarget?.date_time_approved">
            <p class="text-xs text-slate-400 mb-0.5">Date Approved</p>
            <p class="text-slate-700">{{ viewTarget?.date_time_approved }}</p>
          </div>
        </div>
      </div>
      <template #footer>
        <AppButton v-if="viewTarget?.status === 'OCD Approved'" variant="secondary" @click="printGatepass(viewTarget)">
          <PrinterIcon class="w-4 h-4" /> Print
        </AppButton>
        <AppButton variant="secondary" @click="viewModal = false">Close</AppButton>
      </template>
    </AppModal>

    <!-- Record Actual Time Modal -->
    <AppModal :show="actualModal && !!actualTarget" title="Record Actual Time" :subtitle="actualTarget ? `${actualTarget.name} — ${actualTarget.controlno}` : ''" size="sm" @close="actualModal = false">
      <div class="space-y-4">
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
          Requested: <span class="font-mono font-medium text-slate-600">{{ actualTarget?.gatepass_timeout || '—' }} → {{ actualTarget?.gatepass_timein || '—' }}</span>
        </p>
      </div>
      <template #footer>
        <AppButton variant="secondary" :disabled="saving" @click="actualModal = false">Cancel</AppButton>
        <AppButton variant="success" :loading="saving" @click="submitActual">Save</AppButton>
      </template>
    </AppModal>

    <!-- Delete Confirm Modal -->
    <AppModal :show="!!deleteTarget" title="Delete Gate Pass" size="sm" @close="deleteTarget = null">
      <p class="text-sm text-slate-600">Remove gate pass <strong>{{ deleteTarget?.controlno }}</strong>? This cannot be undone.</p>
      <template #footer>
        <AppButton variant="secondary" :disabled="saving" @click="deleteTarget = null">Cancel</AppButton>
        <AppButton variant="danger" :loading="saving" @click="doDelete">Delete</AppButton>
      </template>
    </AppModal>

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
import { ref, computed } from 'vue'
import { Head, usePage, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { PlusIcon, PencilSquareIcon, TrashIcon, PrinterIcon, EyeIcon, ClockIcon } from '@heroicons/vue/24/outline'
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

const page        = usePage()
const rows        = computed(() => page.props.rows ?? [])
const hasPin      = computed(() => page.props.hasPin ?? false)
const signatureUri = computed(() => page.props.signatureUri ?? null)
const currentUser = computed(() => page.props.auth?.user ?? {})
const isSelf      = computed(() => ['Staff', 'Faculty'].includes(currentUser.value.role?.name ?? ''))
const isDivisionChief = computed(() => currentUser.value.role?.name === 'DivisionChief')
const isAdmin     = computed(() => (currentUser.value.role?.name ?? '').toLowerCase() === 'administrator')
const canRecordActualTime = computed(() => currentUser.value.permissions?.includes('hr.gatepass.approve') ?? false)

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
const appliedStatus = ref('')
const appliedSearch = ref('')
const currentPage  = ref(1)
const perPage      = 10

function countByStatus(status) {
  return rows.value.filter(r => r.status === status).length
}

const filtered = computed(() => {
  let list = rows.value
  if (appliedStatus.value) list = list.filter(r => r.status === appliedStatus.value)
  const q = appliedSearch.value.trim().toLowerCase()
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

function applyFilters() {
  appliedSearch.value = searchQuery.value
  appliedStatus.value = activeStatus.value
  currentPage.value = 1
}

function clearFilters() {
  searchQuery.value = ''
  activeStatus.value = ''
  appliedSearch.value = ''
  appliedStatus.value = ''
  currentPage.value = 1
}

function goToPage(page) {
  currentPage.value = Math.min(Math.max(Number(page) || 1, 1), totalPages.value)
}

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
