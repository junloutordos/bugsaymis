<script setup>
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
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

function checkOut() {
  if (!confirm('Mark this intern as checked out?')) return
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

function removeAppliance(id) {
  if (!confirm('Remove this appliance?')) return
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

const statusClass = (s) => ({
  active:      'bg-emerald-100 text-emerald-700',
  checked_out: 'bg-slate-100 text-slate-600',
  suspended:   'bg-amber-100 text-amber-700',
  terminated:  'bg-rose-100 text-rose-700',
}[s] || 'bg-slate-100 text-slate-600')

const passClass = (s) => ({
  pending:  'bg-amber-100 text-amber-700',
  approved: 'bg-sky-100 text-sky-700',
  departed: 'bg-indigo-100 text-indigo-700',
  returned: 'bg-emerald-100 text-emerald-700',
  overdue:  'bg-rose-100 text-rose-700',
  rejected: 'bg-rose-100 text-rose-600',
}[s] || 'bg-slate-100 text-slate-600')

const incidentClass = (t) => ({
  health:        'bg-rose-100 text-rose-700',
  behavioral:    'bg-amber-100 text-amber-700',
  psychological: 'bg-purple-100 text-purple-700',
}[t] || 'bg-slate-100 text-slate-600')

const purposeLabel = (p) => ({ go_home: 'Go Home', school_activity: 'School Activity', other: 'Other' }[p] || p)
</script>

<template>
  <Head :title="student.name || 'Intern Profile'" />
  <AdminLayout title="Residence Hall">
    <div class="space-y-5">

      <!-- Header -->
      <div class="flex items-start gap-3">
        <Link :href="route('rh.interns.index')"
              class="p-2 rounded-lg border border-slate-200 hover:bg-slate-50 text-slate-600 mt-0.5">
          <ArrowLeftIcon class="w-4 h-4" />
        </Link>
        <div class="flex-1">
          <div class="flex items-center gap-3 flex-wrap">
            <h1 class="text-xl font-semibold text-slate-800">{{ student.name || 'Intern #' + intern.id }}</h1>
            <span :class="['text-xs px-3 py-1 rounded-full font-semibold capitalize', statusClass(intern.status)]">
              {{ intern.status.replace('_', ' ') }}
            </span>
            <span v-if="intern.room" :class="['text-xs px-2 py-0.5 rounded-full font-medium',
              intern.room.residence_hall === 'BRH' ? 'bg-indigo-100 text-indigo-700' : 'bg-pink-100 text-pink-700']">
              {{ intern.room.residence_hall }}
            </span>
          </div>
          <p class="text-sm text-slate-500 mt-0.5">
            PISAY ID: {{ student.barcode || '—' }}
            <span v-if="student.grade_section"> · {{ student.grade_section }}</span>
          </p>
        </div>
        <div class="flex gap-2 flex-wrap">
          <button @click="showLPModal = true"
                  class="inline-flex items-center gap-2 border border-indigo-200 text-indigo-600 hover:bg-indigo-50 px-3 py-2 rounded-lg text-sm">
            Leave Pass
          </button>
          <button @click="showEditModal = true"
                  class="inline-flex items-center gap-2 border border-slate-200 text-slate-600 hover:bg-slate-50 px-3 py-2 rounded-lg text-sm">
            <PencilIcon class="w-4 h-4" /> Edit
          </button>
          <button v-if="intern.status === 'active'" @click="checkOut"
                  class="inline-flex items-center gap-2 border border-amber-200 text-amber-700 hover:bg-amber-50 px-3 py-2 rounded-lg text-sm">
            <ArrowRightEndOnRectangleIcon class="w-4 h-4" /> Check Out
          </button>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        <!-- Left: details -->
        <div class="lg:col-span-2 space-y-4">

          <!-- Contract Details -->
          <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
            <div class="flex items-center gap-2 mb-3">
              <HomeModernIcon class="w-4 h-4 text-indigo-500" />
              <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Contract Details</h2>
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
          </div>

          <!-- Recent Leave Passes -->
          <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
              <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Recent Leave Passes</h2>
              <Link :href="route('rh.leave-passes.index')"
                    class="text-xs text-indigo-600 hover:underline">View All</Link>
            </div>
            <div v-if="intern.leave_passes?.length" class="divide-y divide-slate-50">
              <div v-for="lp in intern.leave_passes" :key="lp.id" class="px-5 py-3 flex items-center justify-between">
                <div>
                  <p class="text-sm text-slate-700 capitalize">{{ purposeLabel(lp.purpose) }}</p>
                  <p class="text-xs text-slate-400">{{ fmtDateTime(lp.created_at) }}</p>
                </div>
                <span :class="['text-xs px-2 py-0.5 rounded-full font-medium capitalize', passClass(lp.status)]">
                  {{ lp.status }}
                </span>
              </div>
            </div>
            <div v-else class="px-5 py-6 text-center text-slate-400 text-sm">No leave passes yet.</div>
          </div>

          <!-- Incidents -->
          <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
              <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Health / Behavioral Incidents</h2>
              <Link :href="route('rh.incidents.index')"
                    class="text-xs text-indigo-600 hover:underline">View All</Link>
            </div>
            <div v-if="intern.incidents?.length" class="divide-y divide-slate-50">
              <div v-for="inc in intern.incidents" :key="inc.id" class="px-5 py-3">
                <div class="flex items-center gap-2 mb-1">
                  <span :class="['text-xs px-2 py-0.5 rounded-full font-medium capitalize', incidentClass(inc.incident_type)]">
                    {{ inc.incident_type }}
                  </span>
                  <span class="text-xs text-slate-400">{{ fmtDateTime(inc.created_at) }}</span>
                  <span v-if="inc.status === 'open'" class="ml-auto text-xs text-amber-600 font-medium">Open</span>
                  <span v-else class="ml-auto text-xs text-slate-400">Resolved</span>
                </div>
                <p class="text-sm text-slate-700">{{ inc.description }}</p>
              </div>
            </div>
            <div v-else class="px-5 py-6 text-center text-slate-400 text-sm">No incidents recorded.</div>
          </div>

        </div>

        <!-- Right rail -->
        <div class="space-y-4">

          <!-- Waiver Status -->
          <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
            <div class="flex items-center justify-between mb-3">
              <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Parent's Waiver (F-RHU-03)</h2>
              <button @click="showWaiverModal = true"
                      class="text-xs text-indigo-600 hover:underline">{{ intern.waiver ? 'Edit' : 'Record' }}</button>
            </div>
            <div v-if="intern.waiver">
              <div class="flex items-center gap-2 mb-2">
                <div :class="['w-3 h-3 rounded-full', intern.waiver.can_go_home_alone ? 'bg-emerald-400' : 'bg-slate-300']"></div>
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
          </div>

          <!-- Appliances -->
          <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
            <div class="flex items-center justify-between mb-3">
              <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Appliances (F-RHU-04)</h2>
              <button @click="showApplianceModal = true"
                      class="inline-flex items-center gap-1 text-xs text-indigo-600 hover:underline">
                <PlusIcon class="w-3 h-3" /> Add
              </button>
            </div>
            <div v-if="intern.appliances?.length" class="space-y-2">
              <div v-for="a in intern.appliances" :key="a.id"
                   class="flex items-center justify-between text-sm gap-2">
                <div class="flex-1 min-w-0">
                  <span class="text-slate-700 truncate block">{{ a.device_name || a.device_type }}</span>
                  <span class="text-xs text-slate-400">{{ a.unit_count }} unit{{ a.unit_count > 1 ? 's' : '' }}<span v-if="a.wattage"> · {{ a.wattage }}W</span></span>
                </div>
                <span class="text-slate-600 whitespace-nowrap text-xs">₱{{ Number(a.fee_amount).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}</span>
                <div class="flex items-center gap-1">
                  <CheckCircleIcon v-if="a.is_approved" class="w-4 h-4 text-emerald-500" title="Approved" />
                  <button v-else @click="approveAppliance(a.id)"
                          class="text-xs text-emerald-600 hover:underline">Approve</button>
                  <button @click="removeAppliance(a.id)"
                          class="text-rose-400 hover:text-rose-600">
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
          </div>

          <!-- Fee Ledger Summary -->
          <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
            <div class="flex items-center justify-between mb-3">
              <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Fee Ledger</h2>
              <Link :href="route('rh.fees.index')" class="text-xs text-indigo-600 hover:underline">Full Ledger</Link>
            </div>
            <div v-if="intern.fee_ledger?.length" class="space-y-2">
              <div v-for="f in intern.fee_ledger.slice(0, 5)" :key="f.id"
                   class="flex items-center justify-between text-xs">
                <span class="text-slate-600">{{ f.period_label }}</span>
                <div class="flex items-center gap-2">
                  <span class="text-slate-700">₱{{ Number(f.total_fee).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}</span>
                  <span :class="['px-1.5 py-0.5 rounded-full font-medium', f.is_paid ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700']">
                    {{ f.is_paid ? 'Paid' : 'Unpaid' }}
                  </span>
                </div>
              </div>
            </div>
            <div v-else class="text-sm text-slate-400">No fee records yet.</div>
          </div>

          <!-- File Anecdotal Report link -->
          <a :href="'/discipline/cases/create?student_id=' + intern.student_id"
             class="flex items-center gap-2 w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm text-slate-700 hover:bg-slate-50 transition-colors">
            <ExclamationTriangleIcon class="w-4 h-4 text-amber-500" />
            File Anecdotal Report (SDO)
          </a>

        </div>

      </div>

    </div>

    <!-- Edit Intern Modal -->
    <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4 p-6 max-h-[90vh] overflow-y-auto">
        <h3 class="text-base font-semibold text-slate-800 mb-4">Edit Intern Record</h3>
        <div class="space-y-3 text-sm">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Room</label>
            <select v-model.number="editForm.rh_room_id"
                    class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500">
              <option value="">— Unassigned —</option>
              <option v-for="r in availableRooms" :key="r.id" :value="r.id">
                {{ r.residence_hall }} – Room {{ r.room_number }} ({{ r.active_interns_count }}/{{ r.capacity }})
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
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Status</label>
            <select v-model="editForm.status"
                    class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500">
              <option value="active">Active</option>
              <option value="suspended">Suspended</option>
              <option value="checked_out">Checked Out</option>
              <option value="terminated">Terminated</option>
            </select>
          </div>
        </div>
        <div class="flex gap-3 mt-5">
          <button @click="showEditModal = false"
                  class="flex-1 px-4 py-2 rounded-lg border border-slate-200 text-slate-600 text-sm hover:bg-slate-50">
            Cancel
          </button>
          <button @click="saveEdit"
                  class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
            Save Changes
          </button>
        </div>
      </div>
    </div>

    <!-- Waiver Modal -->
    <div v-if="showWaiverModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 p-6">
        <h3 class="text-base font-semibold text-slate-800 mb-4">Parent's Waiver (F-RHU-03)</h3>
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
        <div class="flex gap-3 mt-5">
          <button @click="showWaiverModal = false"
                  class="flex-1 px-4 py-2 rounded-lg border border-slate-200 text-slate-600 text-sm hover:bg-slate-50">Cancel</button>
          <button @click="saveWaiver"
                  class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
            Save Waiver
          </button>
        </div>
      </div>
    </div>

    <!-- Add Appliance Modal -->
    <div v-if="showApplianceModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 p-6">
        <h3 class="text-base font-semibold text-slate-800 mb-4">Declare Appliance (F-RHU-04)</h3>
        <div class="space-y-3">
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Device Type</label>
              <select v-model="applianceForm.device_type"
                      class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">— Select —</option>
                <option value="Electric Fan">Electric Fan (max 65W)</option>
                <option value="Rechargeable Fan">Rechargeable Fan</option>
                <option value="Study Lamp">Study Lamp (LED, max 5W)</option>
                <option value="Laptop">Laptop + Charger</option>
                <option value="Tablet">Notepad / Tablet + Charger</option>
                <option value="Cellphone">Cellphone + Charger</option>
                <option value="Power Bank">Power Bank</option>
                <option value="Other">Other (max 3)</option>
              </select>
            </div>
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
        <div class="flex gap-3 mt-5">
          <button @click="showApplianceModal = false"
                  class="flex-1 px-4 py-2 rounded-lg border border-slate-200 text-slate-600 text-sm hover:bg-slate-50">Cancel</button>
          <button @click="submitAppliance" :disabled="!applianceForm.device_type"
                  class="flex-1 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white px-4 py-2 rounded-lg text-sm font-medium">
            Add Appliance
          </button>
        </div>
      </div>
    </div>

    <!-- Leave Pass Modal -->
    <div v-if="showLPModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 p-6">
        <h3 class="text-base font-semibold text-slate-800 mb-1">New Leave Pass</h3>
        <p class="text-sm text-slate-500 mb-4">{{ student.name }}</p>
        <div class="space-y-3">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Purpose</label>
            <select v-model="lpForm.purpose"
                    class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500">
              <option value="go_home">Go Home</option>
              <option value="school_activity">School Activity</option>
              <option value="other">Other</option>
            </select>
          </div>
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
        <div class="flex gap-3 mt-5">
          <button @click="showLPModal = false"
                  class="flex-1 px-4 py-2 rounded-lg border border-slate-200 text-slate-600 text-sm hover:bg-slate-50">Cancel</button>
          <button @click="submitLP" :disabled="!lpForm.destination"
                  class="flex-1 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white px-4 py-2 rounded-lg text-sm font-medium">
            Issue Pass
          </button>
        </div>
      </div>
    </div>

  </AdminLayout>
</template>
