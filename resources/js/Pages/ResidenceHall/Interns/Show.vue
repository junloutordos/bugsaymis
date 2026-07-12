<script setup>
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppCard from '@/Components/AppCard.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppButton from '@/Components/AppButton.vue'
import AppModal from '@/Components/AppModal.vue'
import AppSelect from '@/Components/AppSelect.vue'
import { confirmAction } from '@/Composables/useConfirm.js'
import {
  ArrowLeftIcon, HomeModernIcon, PencilIcon,
  ArrowRightEndOnRectangleIcon, ExclamationTriangleIcon,
  PlusIcon, TrashIcon, CheckCircleIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  intern: Object,
  student: Object,
  availableRooms: Array,
})

// ── Edit intern modal ─────────────────────────────────────────────────────────
const showEditModal = ref(false)
const editForm = ref({
  rh_room_id:          props.intern.rh_room_id || '',
  bed_number:          props.intern.bed_number || '',
  check_in_date:       props.intern.check_in_date || '',
  check_out_date:      props.intern.check_out_date || '',
  lodging_fee_monthly: props.intern.lodging_fee_monthly || 0,
  contract_start:      props.intern.contract_start || '',
  contract_end:        props.intern.contract_end || '',
  status:              props.intern.status,
})

function saveEdit() {
  router.put(route('rh.interns.update', props.intern.id), editForm.value, {
    preserveScroll: true,
    onSuccess: () => { showEditModal.value = false },
  })
}

async function checkOut() {
  const confirmed = await confirmAction({
    title: 'Mark this intern as checked out?',
    confirmText: 'Check Out',
    icon: 'warning',
  })
  if (!confirmed) return
  router.post(route('rh.interns.check-out', props.intern.id), {}, { preserveScroll: true })
}

// ── Waiver modal ──────────────────────────────────────────────────────────────
const showWaiverModal = ref(false)
const waiverForm = ref({
  can_go_home_alone:     props.intern.waiver?.can_go_home_alone ?? false,
  guardian_name:         props.intern.waiver?.guardian_name || '',
  guardian_contact:      props.intern.waiver?.guardian_contact || '',
  signed_by_student_at:  props.intern.waiver?.signed_by_student_at?.slice(0, 10) || '',
  signed_by_guardian_at: props.intern.waiver?.signed_by_guardian_at?.slice(0, 10) || '',
})

function saveWaiver() {
  router.post(route('rh.interns.waiver.save', props.intern.id), waiverForm.value, {
    preserveScroll: true,
    onSuccess: () => { showWaiverModal.value = false },
  })
}

// ── Appliance modal ───────────────────────────────────────────────────────────
const showApplianceModal = ref(false)
const applianceForm = ref({ device_type: '', device_name: '', unit_count: 1, wattage: '', fee_amount: 0 })

function submitAppliance() {
  router.post(route('rh.appliances.store', props.intern.id), applianceForm.value, {
    preserveScroll: true,
    onSuccess: () => { showApplianceModal.value = false; applianceForm.value = { device_type: '', device_name: '', unit_count: 1, wattage: '', fee_amount: 0 } },
  })
}

async function removeAppliance(id) {
  const confirmed = await confirmAction({
    title: 'Remove this appliance?',
    confirmText: 'Remove',
    icon: 'warning',
  })
  if (!confirmed) return
  router.delete(route('rh.appliances.destroy', id), { preserveScroll: true })
}

function approveAppliance(id) {
  router.post(route('rh.appliances.approve', id), {}, { preserveScroll: true })
}

// ── Leave Pass modal ──────────────────────────────────────────────────────────
const showLPModal = ref(false)
const lpForm = ref({
  rh_intern_id:       props.intern.id,
  purpose:            'go_home',
  destination:        '',
  with_companion:     false,
  companion_name:     '',
  companion_contact:  '',
  expected_return_at: '',
})

function submitLP() {
  router.post(route('rh.leave-passes.store'), lpForm.value, {
    preserveScroll: true,
    onSuccess: () => {
      showLPModal.value = false
      lpForm.value = { rh_intern_id: props.intern.id, purpose: 'go_home', destination: '', with_companion: false, companion_name: '', companion_contact: '', expected_return_at: '' }
    },
  })
}

// ── Helpers ───────────────────────────────────────────────────────────────────
const fmtDate = (d) => d
  ? new Date(d + 'T00:00:00').toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })
  : '—'

const fmtDateTime = (d) => d
  ? new Date(d).toLocaleString('en-PH', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
  : '—'

const statusColor = (s) => ({
  active:      'green',
  checked_out: 'slate',
  suspended:   'amber',
  terminated:  'red',
}[s] || 'slate')

const passColor = (s) => ({
  pending:  'amber',
  approved: 'blue',
  departed: 'indigo',
  returned: 'green',
  overdue:  'red',
  rejected: 'red',
}[s] || 'slate')

const incidentColor = (t) => ({
  health:        'red',
  behavioral:    'amber',
  psychological: 'purple',
}[t] || 'slate')

const purposeLabel = (p) => ({ go_home: 'Go Home', school_activity: 'School Activity', other: 'Other' }[p] || p)
</script>

<template>
  <Head :title="student.name || 'Dormer Profile'" />
  <AdminLayout title="Residence Hall">
    <div class="space-y-5">

      <!-- Header -->
      <div class="flex items-start gap-3">
        <AppButton as="link" variant="secondary" size="sm" :href="route('rh.interns.index')" class="mt-0.5">
          <ArrowLeftIcon class="w-4 h-4" />
        </AppButton>
        <div class="flex-1">
          <div class="flex items-center gap-3 flex-wrap">
            <h1 class="text-xl font-semibold text-slate-800">{{ student.name || 'Dormer #' + intern.id }}</h1>
            <AppBadge :color="statusColor(intern.status)"><span class="capitalize">{{ intern.status.replace('_', ' ') }}</span></AppBadge>
            <AppBadge v-if="intern.room" :color="intern.room.residence_hall === 'BRH' ? 'indigo' : 'purple'">
              {{ intern.room.residence_hall }}
            </AppBadge>
          </div>
          <p class="text-sm text-slate-500 mt-0.5">
            PISAY ID: {{ student.barcode || '—' }}
            <span v-if="student.grade_section"> · {{ student.grade_section }}</span>
          </p>
        </div>
        <div class="flex gap-2 flex-wrap">
          <AppButton variant="secondary" size="sm" @click="showLPModal = true">
            Leave Pass
          </AppButton>
          <AppButton variant="secondary" size="sm" @click="showEditModal = true">
            <PencilIcon class="w-4 h-4" /> Edit
          </AppButton>
          <AppButton v-if="intern.status === 'active'" variant="warning" size="sm" @click="checkOut">
            <ArrowRightEndOnRectangleIcon class="w-4 h-4" /> Check Out
          </AppButton>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        <!-- Left: details -->
        <div class="lg:col-span-2 space-y-4">

          <!-- Contract Details -->
          <AppCard>
            <div class="flex items-center gap-2 mb-3">
              <HomeModernIcon class="w-4 h-4 text-indigo-500" />
              <h2 class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Contract Details</h2>
            </div>
            <div class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
              <div>
                <span class="text-slate-500">Room</span>
                <p class="font-medium text-slate-800 mt-0.5">
                  {{ intern.room ? intern.room.residence_hall + ' – Room ' + intern.room.room_number : '—' }}
                </p>
              </div>
              <div>
                <span class="text-slate-500">Bed No.</span>
                <p class="font-medium text-slate-800 mt-0.5">{{ intern.bed_number || '—' }}</p>
              </div>
              <div>
                <span class="text-slate-500">Check-in</span>
                <p class="font-medium text-slate-800 mt-0.5">{{ fmtDate(intern.check_in_date) }}</p>
              </div>
              <div>
                <span class="text-slate-500">Check-out</span>
                <p class="font-medium text-slate-800 mt-0.5">{{ fmtDate(intern.check_out_date) }}</p>
              </div>
              <div>
                <span class="text-slate-500">Contract Period</span>
                <p class="font-medium text-slate-800 mt-0.5">
                  {{ fmtDate(intern.contract_start) }} – {{ fmtDate(intern.contract_end) }}
                </p>
              </div>
              <div>
                <span class="text-slate-500">Monthly Lodging Fee</span>
                <p class="font-medium text-slate-800 mt-0.5">
                  ₱{{ Number(intern.lodging_fee_monthly).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}
                </p>
              </div>
            </div>
          </AppCard>

          <!-- Recent Leave Passes -->
          <AppCard :padded="false">
            <template #header>
              <div class="flex items-center justify-between gap-3 w-full">
                <h2 class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Recent Leave Passes</h2>
                <Link :href="route('rh.leave-passes.index')"
                      class="text-xs text-indigo-600 hover:underline">View All</Link>
              </div>
            </template>
            <div v-if="intern.leave_passes?.length" class="divide-y divide-slate-50">
              <div v-for="lp in intern.leave_passes" :key="lp.id" class="px-5 py-3 flex items-center justify-between">
                <div>
                  <p class="text-sm text-slate-700 capitalize">{{ purposeLabel(lp.purpose) }}</p>
                  <p class="text-xs text-slate-400">{{ fmtDateTime(lp.created_at) }}</p>
                </div>
                <AppBadge :color="passColor(lp.status)"><span class="capitalize">{{ lp.status }}</span></AppBadge>
              </div>
            </div>
            <div v-else class="px-5 py-6 text-center text-slate-400 text-sm">No leave passes yet.</div>
          </AppCard>

          <!-- Incidents -->
          <AppCard :padded="false">
            <template #header>
              <div class="flex items-center justify-between gap-3 w-full">
                <h2 class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Health / Behavioral Incidents</h2>
                <Link :href="route('rh.incidents.index')"
                      class="text-xs text-indigo-600 hover:underline">View All</Link>
              </div>
            </template>
            <div v-if="intern.incidents?.length" class="divide-y divide-slate-50">
              <div v-for="inc in intern.incidents" :key="inc.id" class="px-5 py-3">
                <div class="flex items-center gap-2 mb-1">
                  <AppBadge :color="incidentColor(inc.incident_type)"><span class="capitalize">{{ inc.incident_type }}</span></AppBadge>
                  <span class="text-xs text-slate-400">{{ fmtDateTime(inc.created_at) }}</span>
                  <span v-if="inc.status === 'open'" class="ml-auto text-xs text-amber-600 font-medium">Open</span>
                  <span v-else class="ml-auto text-xs text-slate-400">Resolved</span>
                </div>
                <p class="text-sm text-slate-700">{{ inc.description }}</p>
              </div>
            </div>
            <div v-else class="px-5 py-6 text-center text-slate-400 text-sm">No incidents recorded.</div>
          </AppCard>

        </div>

        <!-- Right rail -->
        <div class="space-y-4">

          <!-- Waiver Status -->
          <AppCard>
            <template #header>
              <div class="flex items-center justify-between gap-3 w-full">
                <h2 class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Parent's Waiver (F-RHU-03)</h2>
                <button @click="showWaiverModal = true"
                        class="text-xs text-indigo-600 hover:underline">{{ intern.waiver ? 'Edit' : 'Record' }}</button>
              </div>
            </template>
            <div v-if="intern.waiver">
              <div class="flex items-center gap-2 mb-2">
                <div :class="['w-3 h-3 rounded-full', intern.waiver.can_go_home_alone ? 'bg-success-500' : 'bg-slate-300']"></div>
                <span class="text-sm text-slate-700">
                  {{ intern.waiver.can_go_home_alone ? 'Allowed to go home alone' : 'Requires adult supervision' }}
                </span>
              </div>
              <p v-if="intern.waiver.guardian_name" class="text-xs text-slate-500">
                Guardian: {{ intern.waiver.guardian_name }}
                <span v-if="intern.waiver.guardian_contact"> · {{ intern.waiver.guardian_contact }}</span>
              </p>
              <p class="text-xs text-slate-400 mt-1">On file</p>
            </div>
            <div v-else class="text-sm text-amber-600 flex items-center gap-2">
              <ExclamationTriangleIcon class="w-4 h-4" />
              No waiver on file yet.
            </div>
          </AppCard>

          <!-- Appliances -->
          <AppCard>
            <template #header>
              <div class="flex items-center justify-between gap-3 w-full">
                <h2 class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Appliances (F-RHU-04)</h2>
                <button @click="showApplianceModal = true"
                        class="inline-flex items-center gap-1 text-xs text-indigo-600 hover:underline">
                  <PlusIcon class="w-3 h-3" /> Add
                </button>
              </div>
            </template>
            <div v-if="intern.appliances?.length" class="space-y-2">
              <div v-for="a in intern.appliances" :key="a.id"
                   class="flex items-center justify-between text-sm gap-2">
                <div class="flex-1 min-w-0">
                  <span class="text-slate-700 truncate block">{{ a.device_name || a.device_type }}</span>
                  <span class="text-xs text-slate-400">{{ a.unit_count }} unit{{ a.unit_count > 1 ? 's' : '' }}<span v-if="a.wattage"> · {{ a.wattage }}W</span></span>
                </div>
                <span class="text-slate-600 whitespace-nowrap text-xs">₱{{ Number(a.fee_amount).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}</span>
                <div class="flex items-center gap-1">
                  <CheckCircleIcon v-if="a.is_approved" class="w-4 h-4 text-success-500" title="Approved" />
                  <button v-else @click="approveAppliance(a.id)"
                          class="text-xs text-success-600 hover:underline">Approve</button>
                  <button @click="removeAppliance(a.id)"
                          class="text-danger-400 hover:text-danger-600">
                    <TrashIcon class="w-3.5 h-3.5" />
                  </button>
                </div>
              </div>
              <div class="border-t border-slate-100 pt-2 flex justify-between text-sm font-semibold text-slate-800">
                <span>Total</span>
                <span>₱{{ Number(intern.appliances.reduce((s, a) => s + Number(a.fee_amount), 0)).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}</span>
              </div>
            </div>
            <div v-else class="text-sm text-slate-400">No appliances declared.</div>
          </AppCard>

          <!-- Fee Ledger Summary -->
          <AppCard>
            <template #header>
              <div class="flex items-center justify-between gap-3 w-full">
                <h2 class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Fee Ledger</h2>
                <Link :href="route('rh.fees.index')" class="text-xs text-indigo-600 hover:underline">Full Ledger</Link>
              </div>
            </template>
            <div v-if="intern.fee_ledger?.length" class="space-y-2">
              <div v-for="f in intern.fee_ledger.slice(0, 5)" :key="f.id"
                   class="flex items-center justify-between text-xs">
                <span class="text-slate-600">{{ f.period_label }}</span>
                <div class="flex items-center gap-2">
                  <span class="text-slate-700">₱{{ Number(f.total_fee).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}</span>
                  <AppBadge :color="f.is_paid ? 'green' : 'amber'">{{ f.is_paid ? 'Paid' : 'Unpaid' }}</AppBadge>
                </div>
              </div>
            </div>
            <div v-else class="text-sm text-slate-400">No fee records yet.</div>
          </AppCard>

          <!-- File Anecdotal Report link -->
          <a :href="'/discipline/cases/create?student_id=' + intern.student_id"
             class="flex items-center gap-2 w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm text-slate-700 hover:bg-slate-50 transition-colors">
            <ExclamationTriangleIcon class="w-4 h-4 text-amber-500" />
            File Anecdotal Report (SDO)
          </a>

        </div>

      </div>

    </div>

    <!-- Edit Dormer Modal -->
    <AppModal :show="showEditModal" title="Edit Dormer Record" size="lg" @close="showEditModal = false">
      <div class="space-y-3 text-sm">
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Room</label>
          <select v-model.number="editForm.rh_room_id"
                  class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="">— Unassigned —</option>
            <option v-for="r in availableRooms" :key="r.id" :value="r.id">
              {{ r.residence_hall }} – Room {{ r.room_number }} ({{ r.active_interns_count }}/{{ r.capacity }} dormers)
            </option>
          </select>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Bed No.</label>
            <input v-model="editForm.bed_number" type="text"
                   class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Monthly Lodging Fee (₱)</label>
            <input v-model.number="editForm.lodging_fee_monthly" type="number" min="0"
                   class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Check-in</label>
            <input v-model="editForm.check_in_date" type="date"
                   class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Check-out</label>
            <input v-model="editForm.check_out_date" type="date"
                   class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Contract Start</label>
            <input v-model="editForm.contract_start" type="date"
                   class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Contract End</label>
            <input v-model="editForm.contract_end" type="date"
                   class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
        </div>
        <AppSelect v-model="editForm.status" label="Status" :show-blank="false">
          <option value="active">Active</option>
          <option value="suspended">Suspended</option>
          <option value="checked_out">Checked Out</option>
          <option value="terminated">Terminated</option>
        </AppSelect>
      </div>
      <template #footer>
        <AppButton block variant="secondary" @click="showEditModal = false">Cancel</AppButton>
        <AppButton block @click="saveEdit">Save Changes</AppButton>
      </template>
    </AppModal>

    <!-- Waiver Modal -->
    <AppModal :show="showWaiverModal" title="Parent's Waiver (F-RHU-03)" @close="showWaiverModal = false">
      <div class="space-y-3">
        <div class="flex items-center gap-3 p-3 rounded-lg border border-slate-200 cursor-pointer"
             @click="waiverForm.can_go_home_alone = !waiverForm.can_go_home_alone">
          <input type="checkbox" v-model="waiverForm.can_go_home_alone" class="rounded" @click.stop />
          <span class="text-sm text-slate-700">Student is allowed to go home alone</span>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Guardian Name</label>
          <input v-model="waiverForm.guardian_name" type="text"
                 class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Guardian Contact</label>
          <input v-model="waiverForm.guardian_contact" type="text"
                 class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Student Signed</label>
            <input v-model="waiverForm.signed_by_student_at" type="date"
                   class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Guardian Signed</label>
            <input v-model="waiverForm.signed_by_guardian_at" type="date"
                   class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
        </div>
      </div>
      <template #footer>
        <AppButton block variant="secondary" @click="showWaiverModal = false">Cancel</AppButton>
        <AppButton block @click="saveWaiver">Save Waiver</AppButton>
      </template>
    </AppModal>

    <!-- Add Appliance Modal -->
    <AppModal :show="showApplianceModal" title="Declare Appliance (F-RHU-04)" @close="showApplianceModal = false">
      <div class="space-y-3">
        <div class="grid grid-cols-2 gap-3">
          <AppSelect v-model="applianceForm.device_type" label="Device Type" placeholder="— Select —">
            <option value="Electric Fan">Electric Fan (max 65W)</option>
            <option value="Rechargeable Fan">Rechargeable Fan</option>
            <option value="Study Lamp">Study Lamp (LED, max 5W)</option>
            <option value="Laptop">Laptop + Charger</option>
            <option value="Tablet">Notepad / Tablet + Charger</option>
            <option value="Cellphone">Cellphone + Charger</option>
            <option value="Power Bank">Power Bank</option>
            <option value="Other">Other (max 3)</option>
          </AppSelect>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Device Name / Brand</label>
            <input v-model="applianceForm.device_name" type="text"
                   class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
        </div>
        <div class="grid grid-cols-3 gap-3">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Units</label>
            <input v-model.number="applianceForm.unit_count" type="number" min="1"
                   class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Wattage (W)</label>
            <input v-model.number="applianceForm.wattage" type="number" min="0"
                   class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Fee (₱/mo)</label>
            <input v-model.number="applianceForm.fee_amount" type="number" min="0"
                   class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
        </div>
      </div>
      <template #footer>
        <AppButton block variant="secondary" @click="showApplianceModal = false">Cancel</AppButton>
        <AppButton block :disabled="!applianceForm.device_type" @click="submitAppliance">
          Add Appliance
        </AppButton>
      </template>
    </AppModal>

    <!-- Leave Pass Modal -->
    <AppModal :show="showLPModal" title="New Leave Pass" :subtitle="student.name" @close="showLPModal = false">
      <div class="space-y-3">
        <AppSelect v-model="lpForm.purpose" label="Purpose" :show-blank="false">
          <option value="go_home">Go Home</option>
          <option value="school_activity">School Activity</option>
          <option value="other">Other</option>
        </AppSelect>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Destination</label>
          <input v-model="lpForm.destination" type="text"
                 class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>
        <div class="flex items-center gap-2">
          <input v-model="lpForm.with_companion" type="checkbox" id="lp_companion" class="rounded" />
          <label for="lp_companion" class="text-sm text-slate-700">With Companion</label>
        </div>
        <div v-if="lpForm.with_companion" class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Companion Name</label>
            <input v-model="lpForm.companion_name" type="text"
                   class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Companion Contact</label>
            <input v-model="lpForm.companion_contact" type="text"
                   class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Expected Return</label>
          <input v-model="lpForm.expected_return_at" type="datetime-local"
                 class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>
      </div>
      <template #footer>
        <AppButton block variant="secondary" @click="showLPModal = false">Cancel</AppButton>
        <AppButton block :disabled="!lpForm.destination" @click="submitLP">
          Issue Pass
        </AppButton>
      </template>
    </AppModal>

  </AdminLayout>
</template>
