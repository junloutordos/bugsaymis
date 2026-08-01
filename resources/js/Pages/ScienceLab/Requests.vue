<script setup>
import { computed, ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppModal from '@/Components/AppModal.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import CsmForm from '@/Components/CsmForm.vue'
import {
  PlusIcon, PencilSquareIcon, PrinterIcon, CheckBadgeIcon, XCircleIcon,
  ChevronDownIcon, ChevronUpIcon, TrashIcon, BeakerIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  bundles: Array, labRooms: Array, equipment: Array, reagents: Array, endorsers: Array,
  requesterType: String, schoolYear: Object, hasOutstandingCsm: Boolean,
  hasPin: Boolean, currentUserId: Number, canManage: Boolean,
})

const showEditor = ref(false)
const editing = ref(null)
const step = ref(1)
const expanded = ref(null)
const signTarget = ref(null)
const reasonTarget = ref(null)
const csmTarget = ref(null)
const issueTarget = ref(null)
const returnTarget = ref(null)
const releaseTarget = ref(null)
const groupText = ref('')
const queueTab = ref('all')

const blankEquipment = () => ({ lab_equipment_id: '', quantity: 1, item: '', description: '' })
const blankReagent = () => ({ lab_consumable_id: '', quantity: 1, reagent: '', sds_read: false })
const form = useForm({
  assigned_endorser_id: props.endorsers?.length === 1 ? props.endorsers[0].id : '',
  grade_level_section: '', number_of_students: '', subject: '', teacher_in_charge: '', room_id: '',
  date_start: '', date_end: '', time_start: '', time_end: '', student_group: [],
  concurrent_topic: '', unit: '', venue: '', remarks: '',
  needs_equipment: false, equipment_items: [blankEquipment()],
  needs_reagents: false, reagent_items: [blankReagent()],
})
const signForm = useForm({ pin: '' })
const reasonForm = useForm({ reason: '' })
const issueForm = useForm({ received_by_name: '', items: [] })
const returnForm = useForm({ items: [] })
const releaseForm = useForm({ received_by_name: '', items: [] })

const statusTone = {
  pending_endorsement: 'bg-amber-50 text-amber-700', pending_approval: 'bg-sky-50 text-sky-700',
  approved: 'bg-emerald-50 text-emerald-700', ongoing: 'bg-violet-50 text-violet-700',
  completed: 'bg-indigo-50 text-indigo-700', declined: 'bg-rose-50 text-rose-700',
  cancelled: 'bg-slate-100 text-slate-500',
}
const statusLabel = s => ({
  pending_endorsement: 'Pending Endorsement', pending_approval: 'Pending SRA/SRS Approval',
  approved: 'Approved', ongoing: 'Ongoing', completed: 'Completed', declined: 'Declined', cancelled: 'Cancelled',
}[s] || s)
const requesterOwns = b => b.requested_by_id === props.currentUserId
const canEndorse = b => b.assigned_endorser_id === props.currentUserId && b.status === 'pending_endorsement'
const canApprove = b => b.assigned_approver_id === props.currentUserId && b.status === 'pending_approval'
const requestError = computed(() => form.errors.csm || '')
const firstFormError = computed(() => Object.values(form.errors)[0] || '')
const visibleBundles = computed(() => props.bundles.filter(bundle => {
  if (queueTab.value === 'equipment') return !!bundle.equipment_request
  if (queueTab.value === 'reagent') return !!bundle.reagent_request
  return true
}))

function resetEditor() {
  editing.value = null
  step.value = 1
  form.reset()
  form.assigned_endorser_id = props.endorsers?.length === 1 ? props.endorsers[0].id : ''
  form.equipment_items = [blankEquipment()]
  form.reagent_items = [blankReagent()]
  groupText.value = ''
  form.clearErrors()
}
function openCreate() { resetEditor(); showEditor.value = true }
function openEdit(b) {
  resetEditor(); editing.value = b
  const r = b.reservation
  Object.assign(form, {
    assigned_endorser_id: b.assigned_endorser_id || '', grade_level_section: r.grade_level_section || '',
    number_of_students: r.number_of_students || '', subject: r.subject || '', teacher_in_charge: r.teacher_in_charge || '',
    room_id: r.room_id || '', date_start: r.date_start || '', date_end: r.date_end || '',
    time_start: (r.time_start || '').slice(0, 5), time_end: (r.time_end || '').slice(0, 5), remarks: r.remarks || '',
    concurrent_topic: b.equipment_request?.concurrent_topic || b.reagent_request?.concurrent_topic || '',
    unit: b.equipment_request?.unit || b.reagent_request?.unit || '', venue: b.equipment_request?.venue || b.reagent_request?.venue || '',
    needs_equipment: !!b.equipment_request,
    equipment_items: b.equipment_request?.items?.map(i => ({ lab_equipment_id: i.lab_equipment_id || '', quantity: i.quantity, item: i.item, description: i.description || '' })) || [blankEquipment()],
    needs_reagents: !!b.reagent_request,
    reagent_items: b.reagent_request?.items?.map(i => ({ lab_consumable_id: i.lab_consumable_id || '', quantity: i.quantity, reagent: i.reagent, sds_read: !!i.sds_read })) || [blankReagent()],
  })
  groupText.value = (r.student_group || []).join('\n')
  showEditor.value = true
}
function submit() {
  if (!form.needs_equipment) form.needs_reagents = false
  form.student_group = groupText.value.split('\n').map(v => v.trim()).filter(Boolean)
  const options = { preserveScroll: true, onSuccess: () => { showEditor.value = false; resetEditor() } }
  editing.value ? form.put(route('science-lab.requests.update', editing.value.id), options) : form.post(route('science-lab.requests.store'), options)
}
function chooseEquipment(row) {
  const item = props.equipment.find(e => e.id === Number(row.lab_equipment_id))
  if (item) { row.item = item.description; row.description = item.property_no || '' }
}
function chooseReagent(row) {
  const item = props.reagents.find(e => e.id === Number(row.lab_consumable_id))
  if (item) row.reagent = item.name
}
function openSign(b, action) { signTarget.value = { b, action }; signForm.reset() }
function submitSign() {
  signForm.post(route(`science-lab.requests.${signTarget.value.action}`, signTarget.value.b.id), {
    preserveScroll: true, onSuccess: () => (signTarget.value = null),
  })
}
function openReason(b, action) { reasonTarget.value = { b, action }; reasonForm.reset() }
function submitReason() {
  reasonForm.post(route(`science-lab.requests.${reasonTarget.value.action}`, reasonTarget.value.b.id), {
    preserveScroll: true, onSuccess: () => (reasonTarget.value = null),
  })
}
function complete(b) { router.post(route('science-lab.requests.complete', b.id), {}, { preserveScroll: true }) }
function openIssue(b) {
  issueTarget.value = b
  issueForm.items = b.equipment_request.items.map(i => ({ id: i.id, issued_condition: '' }))
  issueForm.received_by_name = b.requester_name
}
function submitIssue() { issueForm.post(route('science-lab.equipment-requests.issue', issueTarget.value.equipment_request.id), { preserveScroll: true, onSuccess: () => (issueTarget.value = null) }) }
function openReturn(b) {
  returnTarget.value = b
  returnForm.items = b.equipment_request.items.map(i => ({ id: i.id, returned_condition: '', needs_repair: false, needs_replacement: false }))
}
function submitReturn() { returnForm.post(route('science-lab.equipment-requests.return', returnTarget.value.equipment_request.id), { preserveScroll: true, onSuccess: () => (returnTarget.value = null) }) }
function openRelease(b) {
  releaseTarget.value = b
  releaseForm.received_by_name = b.requester_name
  releaseForm.items = b.reagent_request.items.map(i => ({ id: i.id, issued_amount: '' }))
}
function submitRelease() { releaseForm.post(route('science-lab.reagent-requests.release', releaseTarget.value.reagent_request.id), { preserveScroll: true, onSuccess: () => (releaseTarget.value = null) }) }
const prettyDate = value => value ? new Date(`${value}T00:00:00`).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' }) : '—'
</script>

<template>
  <Head title="Laboratory Request and Reservation" />
  <AdminLayout title="Laboratory Request and Reservation">
    <div class="space-y-5">
      <AppPageHeader title="Request and Reservation" :subtitle="`LRF → Equipment Accountability → Reagent Request · SY ${schoolYear?.name || '—'}`">
        <template #actions>
          <button @click="openCreate" :disabled="hasOutstandingCsm" class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"><PlusIcon class="h-4 w-4" /> New Request</button>
        </template>
      </AppPageHeader>

      <div v-if="hasOutstandingCsm" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        Complete the Client Satisfaction Survey on your previous completed request before filing another.
      </div>

      <div v-if="canManage" class="flex flex-wrap gap-1 rounded-lg bg-slate-100 p-1">
        <button v-for="option in [{ key: 'all', label: 'All Packages' }, { key: 'reservation', label: 'B.1 Laboratory Reservations' }, { key: 'equipment', label: 'B.2 Equipment Accountability' }, { key: 'reagent', label: 'B.3 Reagent Requests' }]" :key="option.key" @click="queueTab = option.key" :class="['rounded-md px-3 py-1.5 text-xs font-medium', queueTab === option.key ? 'bg-white text-indigo-600 shadow-sm' : 'text-slate-500']">{{ option.label }}</button>
      </div>

      <div class="space-y-3">
        <article v-for="b in visibleBundles" :key="b.id" class="rounded-xl border border-slate-200 bg-white">
          <div class="flex flex-wrap items-start justify-between gap-3 p-4">
            <div>
              <div class="flex flex-wrap items-center gap-2">
                <span class="font-mono text-xs text-slate-500">{{ b.reference_no }}</span>
                <span :class="['rounded-full px-2 py-0.5 text-xs font-medium', statusTone[b.status]]">{{ statusLabel(b.status) }}</span>
              </div>
              <h2 class="mt-1 font-semibold text-slate-800">{{ b.reservation?.subject }} · {{ b.reservation?.room?.name || 'Laboratory' }}</h2>
              <p class="text-xs text-slate-500">{{ prettyDate(b.reservation?.date_start) }} · {{ b.requester?.name || b.requester_name }} · Endorser: {{ b.endorser?.name || '—' }} · SRA/SRS: {{ b.approver?.name || '—' }}</p>
            </div>
            <div class="flex flex-wrap items-center justify-end gap-1">
              <button v-if="requesterOwns(b) && b.status === 'pending_endorsement'" @click="openEdit(b)" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" title="Edit"><PencilSquareIcon class="h-4 w-4" /></button>
              <button v-if="canEndorse(b)" @click="openSign(b, 'endorse')" class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-sky-700 hover:bg-sky-50">Endorse</button>
              <button v-if="canApprove(b)" @click="openSign(b, 'approve')" class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-50">Approve</button>
              <button v-if="canManage && b.status === 'approved' && b.equipment_request?.status === 'approved'" @click="openIssue(b)" class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-violet-700 hover:bg-violet-50">Mark Received</button>
              <button v-if="canManage && b.equipment_request?.status === 'issued'" @click="openReturn(b)" class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-50">Receive & Inspect</button>
              <button v-if="canManage && b.reagent_request?.status === 'approved'" @click="openRelease(b)" class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-violet-700 hover:bg-violet-50">Release Reagents</button>
              <button v-if="canManage && b.status === 'approved' && !b.equipment_request && !b.reagent_request" @click="complete(b)" class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-50">Complete</button>
              <button v-if="b.status === 'completed' && requesterOwns(b) && !b.csm_response" @click="csmTarget = b" class="rounded-lg bg-amber-500 px-2.5 py-1.5 text-xs font-medium text-white hover:bg-amber-600">Complete CSM</button>
              <button v-if="(requesterOwns(b) || canManage) && ['pending_endorsement','pending_approval','approved'].includes(b.status)" @click="openReason(b, 'cancel')" class="rounded-lg px-2 py-1.5 text-xs text-rose-600 hover:bg-rose-50">Cancel</button>
              <button v-if="(canEndorse(b) || canApprove(b) || canManage) && ['pending_endorsement','pending_approval'].includes(b.status)" @click="openReason(b, 'decline')" class="rounded-lg px-2 py-1.5 text-xs text-rose-600 hover:bg-rose-50">Decline</button>
              <button @click="expanded = expanded === b.id ? null : b.id" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100"><ChevronUpIcon v-if="expanded === b.id" class="h-4 w-4" /><ChevronDownIcon v-else class="h-4 w-4" /></button>
            </div>
          </div>
          <div v-if="expanded === b.id" class="border-t border-slate-100 p-4">
            <div class="grid gap-4 md:grid-cols-3">
              <div class="rounded-lg bg-slate-50 p-3"><div class="text-xs font-semibold uppercase text-slate-500">Laboratory Reservation</div><div class="mt-1 font-mono text-xs">{{ b.reservation?.control_no }}</div><a :href="route('science-lab.reservations.pdf', b.reservation.id)" target="_blank" class="mt-2 inline-flex items-center gap-1 text-xs text-indigo-600"><PrinterIcon class="h-4 w-4" /> Print LRF</a></div>
              <div class="rounded-lg bg-slate-50 p-3"><div class="text-xs font-semibold uppercase text-slate-500">Equipment Accountability</div><template v-if="b.equipment_request"><div class="mt-1 font-mono text-xs">{{ b.equipment_request.control_no }}</div><div class="text-xs text-slate-500">{{ b.equipment_request.items.length }} item(s)</div><a :href="route('science-lab.equipment-requests.pdf', b.equipment_request.id)" target="_blank" class="mt-2 inline-flex items-center gap-1 text-xs text-indigo-600"><PrinterIcon class="h-4 w-4" /> Print CID-20</a></template><span v-else class="mt-2 block text-xs text-slate-400">Not requested</span></div>
              <div class="rounded-lg bg-slate-50 p-3"><div class="text-xs font-semibold uppercase text-slate-500">Reagent Request</div><template v-if="b.reagent_request"><div class="mt-1 font-mono text-xs">{{ b.reagent_request.control_no }}</div><div class="text-xs text-slate-500">{{ b.reagent_request.items.length }} item(s)</div><a :href="route('science-lab.reagent-requests.pdf', b.reagent_request.id)" target="_blank" class="mt-2 inline-flex items-center gap-1 text-xs text-indigo-600"><PrinterIcon class="h-4 w-4" /> Print CID-19</a></template><span v-else class="mt-2 block text-xs text-slate-400">Not requested</span></div>
            </div>
          </div>
        </article>
        <div v-if="!visibleBundles.length" class="rounded-xl border border-dashed border-slate-300 bg-white py-12 text-center text-sm text-slate-400">No laboratory requests in this section.</div>
      </div>
    </div>

    <AppModal :show="showEditor" :title="editing ? 'Edit Request Package' : 'New Request Package'" size="3xl" @close="showEditor = false">
      <div class="mb-5 flex items-center gap-2 text-xs font-medium"><span v-for="n in 3" :key="n" :class="['rounded-full px-3 py-1.5', step === n ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-500']">{{ n }}. {{ ['Reservation','Equipment','Reagents'][n-1] }}</span></div>
      <div v-if="step === 1" class="grid grid-cols-2 gap-3">
        <label v-if="endorsers.length > 1" class="col-span-2 text-sm">Endorsed by<select v-model="form.assigned_endorser_id" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2"><option value="">Select subject teacher/adviser…</option><option v-for="u in endorsers" :key="u.id" :value="u.id">{{ u.name }} — {{ u.position }}</option></select></label>
        <label class="text-sm">Grade Level & Section<input v-model="form.grade_level_section" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" /></label>
        <label class="text-sm">Number of Students<input type="number" min="0" v-model="form.number_of_students" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" /></label>
        <label class="text-sm">Subject *<input v-model="form.subject" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" /></label>
        <label class="text-sm">Teacher In-Charge *<input v-model="form.teacher_in_charge" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" /></label>
        <label class="col-span-2 text-sm">Preferred Laboratory *<select v-model="form.room_id" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2"><option value="">Select…</option><option v-for="r in labRooms" :key="r.id" :value="r.id">{{ r.name }}</option></select></label>
        <label class="text-sm">Date Start *<input type="date" v-model="form.date_start" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" /></label><label class="text-sm">Date End<input type="date" v-model="form.date_end" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" /></label>
        <label class="text-sm">Time Start *<input type="time" v-model="form.time_start" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" /></label><label class="text-sm">Time End *<input type="time" v-model="form.time_end" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" /></label>
        <label class="col-span-2 text-sm">Students in group (one per line)<textarea v-model="groupText" rows="3" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" /></label>
      </div>
      <div v-else-if="step === 2">
        <label class="flex items-center gap-2 rounded-lg bg-slate-50 p-3 text-sm font-medium"><input type="checkbox" v-model="form.needs_equipment" /> I need laboratory equipment or materials.</label>
        <template v-if="form.needs_equipment"><div class="mt-3 grid grid-cols-3 gap-3"><input v-model="form.concurrent_topic" placeholder="Concurrent topic" class="rounded-lg border border-slate-200 px-3 py-2 text-sm" /><input v-model="form.unit" placeholder="Unit" class="rounded-lg border border-slate-200 px-3 py-2 text-sm" /><input v-model="form.venue" placeholder="Venue of experiment" class="rounded-lg border border-slate-200 px-3 py-2 text-sm" /></div><div v-for="(row,i) in form.equipment_items" :key="i" class="mt-2 grid grid-cols-12 gap-2"><select v-model="row.lab_equipment_id" @change="chooseEquipment(row)" class="col-span-4 rounded-lg border border-slate-200 px-2 py-2 text-sm"><option value="">Select or enter item…</option><option v-for="e in equipment" :key="e.id" :value="e.id">{{ e.description }}</option></select><input v-model="row.item" placeholder="Item" class="col-span-3 rounded-lg border border-slate-200 px-2 py-2 text-sm" /><input v-model="row.description" placeholder="Description" class="col-span-3 rounded-lg border border-slate-200 px-2 py-2 text-sm" /><input type="number" min="0.01" step="0.01" v-model="row.quantity" class="col-span-1 rounded-lg border border-slate-200 px-2 py-2 text-sm" /><button @click="form.equipment_items.splice(i,1)" class="text-slate-400 hover:text-rose-600"><TrashIcon class="h-4 w-4" /></button></div><button @click="form.equipment_items.push(blankEquipment())" class="mt-2 text-xs text-indigo-600">+ Add equipment</button></template>
      </div>
      <div v-else>
        <label class="flex items-center gap-2 rounded-lg bg-slate-50 p-3 text-sm font-medium"><input type="checkbox" v-model="form.needs_reagents" /> I need reagents, chemicals, or consumables.</label>
        <template v-if="form.needs_reagents"><div v-for="(row,i) in form.reagent_items" :key="i" class="mt-2 grid grid-cols-12 items-center gap-2"><select v-model="row.lab_consumable_id" @change="chooseReagent(row)" class="col-span-4 rounded-lg border border-slate-200 px-2 py-2 text-sm"><option value="">Select reagent…</option><option v-for="r in reagents" :key="r.id" :value="r.id">{{ r.name }} ({{ r.balance }} {{ r.unit }})</option></select><input v-model="row.reagent" placeholder="Reagent" class="col-span-3 rounded-lg border border-slate-200 px-2 py-2 text-sm" /><input type="number" min="0.001" step="0.001" v-model="row.quantity" class="col-span-2 rounded-lg border border-slate-200 px-2 py-2 text-sm" /><label class="col-span-2 flex items-center gap-1 text-xs"><input type="checkbox" v-model="row.sds_read" /> SDS read</label><button @click="form.reagent_items.splice(i,1)" class="text-slate-400 hover:text-rose-600"><TrashIcon class="h-4 w-4" /></button></div><button @click="form.reagent_items.push(blankReagent())" class="mt-2 text-xs text-indigo-600">+ Add reagent</button></template>
        <label class="mt-4 block text-sm">Remarks<textarea v-model="form.remarks" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" /></label>
        <p v-if="requestError" class="mt-2 text-xs text-rose-600">{{ requestError }}</p>
      </div>
      <p v-if="firstFormError" class="mt-3 text-xs text-rose-600">{{ firstFormError }}</p>
      <template #footer><button v-if="step > 1" @click="step--" class="rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-100">Back</button><button v-if="step === 1 || (step === 2 && form.needs_equipment)" @click="step++" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white">Continue</button><button v-else @click="submit" :disabled="form.processing" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white">{{ editing ? 'Save Changes' : 'Submit Package' }}</button></template>
    </AppModal>

    <AppModal :show="!!signTarget" :title="signTarget?.action === 'endorse' ? 'Endorse Request Package' : 'Approve Request Package'" size="sm" @close="signTarget = null"><p v-if="!hasPin" class="rounded-lg bg-amber-50 p-3 text-sm text-amber-700">Set a signing PIN in your profile first.</p><input v-else v-model="signForm.pin" type="password" placeholder="Signing PIN" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-center tracking-widest" /><p v-if="signForm.errors.pin" class="mt-1 text-xs text-rose-600">{{ signForm.errors.pin }}</p><template #footer><button @click="signTarget = null" class="rounded-lg px-4 py-2 text-sm">Cancel</button><button v-if="hasPin" @click="submitSign" class="inline-flex items-center gap-1 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white"><CheckBadgeIcon class="h-4 w-4" /> Sign</button></template></AppModal>
    <AppModal :show="!!reasonTarget" :title="reasonTarget?.action === 'cancel' ? 'Cancel Request Package' : 'Decline Request Package'" size="sm" @close="reasonTarget = null"><textarea v-model="reasonForm.reason" rows="3" placeholder="Reason *" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" /><p v-if="reasonForm.errors.reason" class="mt-1 text-xs text-rose-600">{{ reasonForm.errors.reason }}</p><template #footer><button @click="reasonTarget = null" class="rounded-lg px-4 py-2 text-sm">Back</button><button @click="submitReason" class="inline-flex items-center gap-1 rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white"><XCircleIcon class="h-4 w-4" /> Confirm</button></template></AppModal>

    <AppModal :show="!!issueTarget" title="Mark Equipment Received" size="lg" @close="issueTarget = null"><label class="text-sm">Received by (wet-signature name) *<input v-model="issueForm.received_by_name" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" /></label><div v-for="(row,i) in issueForm.items" :key="row.id" class="mt-2 grid grid-cols-2 gap-2"><span class="text-sm">{{ issueTarget?.equipment_request.items[i].item }}</span><input v-model="row.issued_condition" placeholder="Issued condition/remarks" class="rounded-lg border border-slate-200 px-3 py-2 text-sm" /></div><template #footer><button @click="submitIssue" class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white">Mark Received / Ongoing</button></template></AppModal>
    <AppModal :show="!!returnTarget" title="Receive and Inspect Equipment" size="lg" @close="returnTarget = null"><div v-for="(row,i) in returnForm.items" :key="row.id" class="mb-3 rounded-lg bg-slate-50 p-3"><div class="text-sm font-medium">{{ returnTarget?.equipment_request.items[i].item }}</div><input v-model="row.returned_condition" placeholder="Returned condition/remarks" class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" /><div class="mt-2 flex gap-4 text-xs"><label><input type="checkbox" v-model="row.needs_repair" /> Needs repair</label><label><input type="checkbox" v-model="row.needs_replacement" /> Needs replacement</label></div></div><template #footer><button @click="submitReturn" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white">Received and Inspected / Complete</button></template></AppModal>
    <AppModal :show="!!releaseTarget" title="Release Reagents" size="lg" @close="releaseTarget = null"><label class="text-sm">Received by (wet-signature name) *<input v-model="releaseForm.received_by_name" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" /></label><div v-for="(row,i) in releaseForm.items" :key="row.id" class="mt-2 grid grid-cols-2 gap-2"><span class="text-sm">{{ releaseTarget?.reagent_request.items[i].reagent }}</span><input v-model="row.issued_amount" placeholder="Issued amount/remarks" class="rounded-lg border border-slate-200 px-3 py-2 text-sm" /></div><template #footer><button @click="submitRelease" class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white">Release / Complete</button></template></AppModal>

    <CsmForm v-if="csmTarget" :show="!!csmTarget" respondable-type="lab-request" :respondable-id="csmTarget.id" :transaction-date="(csmTarget.completed_at || csmTarget.updated_at).slice(0,10)" office-availed="Science Laboratory" service-key="others" service-other-label="Science Laboratory Request and Reservation" @close="csmTarget = null" @submitted="csmTarget.csm_response = { id: true }" />
  </AdminLayout>
</template>
