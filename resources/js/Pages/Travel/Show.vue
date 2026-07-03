<script setup>
import { computed, ref } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import {
  ArrowLeftIcon,
  CheckCircleIcon,
  ClipboardDocumentCheckIcon,
  PaperClipIcon,
  PlusIcon,
  PrinterIcon,
  TrashIcon,
  XCircleIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  travel: Object,
  lookups: Object,
  currentUser: Object,
})

const statusClass = (status) => {
  if (['liquidated', 'completed', 'released'].includes(status)) return 'bg-emerald-100 text-emerald-700'
  if (['returned', 'cancelled'].includes(status)) return 'bg-red-100 text-red-700'
  if (status === 'draft') return 'bg-slate-100 text-slate-600'
  return 'bg-amber-100 text-amber-700'
}

const money = (value) => Number(value || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const fmtDate = (date) => date ? new Date(date).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }) : '-'

const perms = computed(() => props.currentUser?.permissions ?? {})
const isOwner = computed(() => [props.travel.traveler_id, props.travel.created_by].includes(props.currentUser?.id))
const canEdit = computed(() => perms.value.canManage || perms.value.canFinance || (isOwner.value && ['draft', 'returned'].includes(props.travel.status)))
const canSubmit = computed(() => canEdit.value && ['draft', 'returned'].includes(props.travel.status))
const canDivisionApprove = computed(() => perms.value.canDivisionApprove && props.travel.status === 'submitted')
const canFadReview = computed(() => perms.value.canFadReview && ['division_approved', 'ocd_approved', 'transport_arranged', 'released', 'completed'].includes(props.travel.status))
const canOcdApprove = computed(() => perms.value.canOcdApprove && props.travel.status === 'fad_reviewed')
const canReturn = computed(() => (perms.value.canManage || perms.value.canDivisionApprove || perms.value.canFadReview || perms.value.canOcdApprove) && !['liquidated', 'cancelled'].includes(props.travel.status))

const detailsForm = useForm({
  traveler_id: props.travel.traveler_id,
  division_chief_id: props.travel.division_chief_id || '',
  travel_type: props.travel.travel_type,
  funding_source: props.travel.funding_source,
  fund_cluster: props.travel.fund_cluster || '',
  travel_mode: props.travel.travel_mode,
  origin: props.travel.origin || '',
  destination: props.travel.destination || '',
  purpose: props.travel.purpose || '',
  start_date: props.travel.start_date || '',
  end_date: props.travel.end_date || '',
  passenger_count: props.travel.passenger_count || 1,
  requires_cash_advance: props.travel.requires_cash_advance,
  cash_advance_amount: props.travel.cash_advance_amount || 0,
})

const itineraryRows = ref((props.travel.itinerary_items?.length ? props.travel.itinerary_items : [{
  travel_date: props.travel.start_date,
  places_to_visit: `${props.travel.origin || 'PSHS-CRC'} - ${props.travel.destination}`,
  departure_time: '',
  arrival_time: '',
  means_of_transportation: props.travel.travel_mode === 'air' ? 'Air' : "Gov't. Vehicle",
  transportation_cost: 0,
  per_diem: 0,
  other_cost: 0,
  remarks: '',
}]).map(row => ({ ...row })))

const itineraryForm = useForm({ items: itineraryRows.value })
const linksForm = useForm({
  travel_order_issuance_id: props.travel.travel_order_issuance_id || '',
  special_order_issuance_id: props.travel.special_order_issuance_id || '',
  vehicle_request_id: props.travel.vehicle_request_id || '',
  ors_id: props.travel.ors_id || '',
  dv_id: props.travel.dv_id || '',
})
const flightForm = useForm({
  passenger_name: props.travel.flight_authority?.passenger_name || props.travel.traveler?.name || '',
  route: props.travel.flight_authority?.route || `${props.travel.origin || 'PSHS-CRC'} - ${props.travel.destination}`,
  preferred_departure_date: props.travel.flight_authority?.preferred_departure_date || props.travel.start_date || '',
  preferred_return_date: props.travel.flight_authority?.preferred_return_date || props.travel.end_date || '',
  estimated_airfare: props.travel.flight_authority?.estimated_airfare || 0,
  booking_notes: props.travel.flight_authority?.booking_notes || '',
  submit: false,
})
const uploadForm = useForm({
  type: 'oed_travel_order',
  file_base64: '',
  file_name: '',
  mime_type: '',
})
const returnForm = useForm({ return_reason: '' })
const liquidationForm = useForm({ liquidation_remarks: props.travel.liquidation_remarks || '' })

const rowTotal = (row) => Number(row.transportation_cost || 0) + Number(row.per_diem || 0) + Number(row.other_cost || 0)
const totals = computed(() => itineraryRows.value.reduce((acc, row) => {
  acc.transportation += Number(row.transportation_cost || 0)
  acc.per_diem += Number(row.per_diem || 0)
  acc.others += Number(row.other_cost || 0)
  acc.total += rowTotal(row)
  return acc
}, { transportation: 0, per_diem: 0, others: 0, total: 0 }))

const addRow = () => {
  itineraryRows.value.push({
    travel_date: props.travel.start_date,
    places_to_visit: '',
    departure_time: '',
    arrival_time: '',
    means_of_transportation: '',
    transportation_cost: 0,
    per_diem: 0,
    other_cost: 0,
    remarks: '',
  })
}
const removeRow = (index) => {
  itineraryRows.value.splice(index, 1)
}

const saveDetails = () => detailsForm.put(route('travel.update', props.travel.id), { preserveScroll: true })
const saveItinerary = () => {
  itineraryForm.items = itineraryRows.value
  itineraryForm.post(route('travel.itinerary.save', props.travel.id), { preserveScroll: true })
}
const saveLinks = () => linksForm.post(route('travel.links.save', props.travel.id), { preserveScroll: true })
const submitTravel = () => router.post(route('travel.submit', props.travel.id), {}, { preserveScroll: true })
const approveDivision = () => router.post(route('travel.division-approve', props.travel.id), {}, { preserveScroll: true })
const reviewFad = () => router.post(route('travel.fad-review', props.travel.id), {}, { preserveScroll: true })
const approveOcd = () => router.post(route('travel.ocd-approve', props.travel.id), {}, { preserveScroll: true })
const returnTravel = () => returnForm.post(route('travel.return', props.travel.id), { preserveScroll: true, onSuccess: () => { returnForm.reset() } })
const completeTravel = () => router.post(route('travel.complete', props.travel.id), {}, { preserveScroll: true })
const liquidateTravel = () => liquidationForm.post(route('travel.liquidate', props.travel.id), { preserveScroll: true })

const saveFlight = (submit = false) => {
  flightForm.submit = submit
  flightForm.post(route('travel.flight-authority.save', props.travel.id), { preserveScroll: true })
}
const approveFlight = () => router.post(route('travel.flight-authority.approve', props.travel.id), {}, { preserveScroll: true })

const onFile = (event) => {
  const file = event.target.files?.[0]
  if (!file) return
  const reader = new FileReader()
  reader.onload = () => {
    uploadForm.file_base64 = reader.result
    uploadForm.file_name = file.name
    uploadForm.mime_type = file.type || 'application/octet-stream'
  }
  reader.readAsDataURL(file)
}

const uploadAttachment = () => uploadForm.post(route('travel.attachments.store', props.travel.id), {
  preserveScroll: true,
  onSuccess: () => {
    uploadForm.reset()
    uploadForm.type = 'oed_travel_order'
  },
})
const deleteAttachment = (attachment) => router.delete(route('travel.attachments.destroy', [props.travel.id, attachment.id]), { preserveScroll: true })
</script>

<template>
  <Head :title="travel.control_no || 'Travel'" />
  <AdminLayout :title="travel.control_no || 'Travel'">
    <div class="space-y-6">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <Link :href="route('travel.index')" class="mb-3 inline-flex items-center gap-1 text-sm font-medium text-slate-500 hover:text-indigo-600">
            <ArrowLeftIcon class="h-4 w-4" />
            Travel Requests
          </Link>
          <div class="flex flex-wrap items-center gap-3">
            <h1 class="text-xl font-semibold text-slate-900">{{ travel.control_no }}</h1>
            <span :class="['rounded-full px-2 py-0.5 text-xs font-medium', statusClass(travel.status)]">{{ travel.status_label }}</span>
          </div>
          <p class="mt-1 text-sm text-slate-500">{{ travel.traveler?.name }} · {{ travel.destination }} · {{ fmtDate(travel.start_date) }} to {{ fmtDate(travel.end_date) }}</p>
          <p v-if="travel.return_reason" class="mt-2 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ travel.return_reason }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
          <a :href="route('travel.iot.print', travel.id)" target="_blank" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
            <PrinterIcon class="h-4 w-4" />
            Print IOT
          </a>
          <button v-if="canSubmit" @click="submitTravel" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
            <ClipboardDocumentCheckIcon class="h-4 w-4" />
            Submit
          </button>
          <button v-if="canDivisionApprove" @click="approveDivision" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-700">
            <CheckCircleIcon class="h-4 w-4" />
            Division Approve
          </button>
          <button v-if="canFadReview && travel.status === 'division_approved'" @click="reviewFad" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-700">
            <CheckCircleIcon class="h-4 w-4" />
            FAD Review
          </button>
          <button v-if="canOcdApprove" @click="approveOcd" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-700">
            <CheckCircleIcon class="h-4 w-4" />
            OCD Approve
          </button>
        </div>
      </div>

      <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <div class="mb-4 flex items-center justify-between">
          <h2 class="text-sm font-semibold text-slate-900">Travel Details</h2>
          <button v-if="canEdit" @click="saveDetails" :disabled="detailsForm.processing" class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-60">Save Details</button>
        </div>
        <div class="grid gap-4 md:grid-cols-3">
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Type</label>
            <select v-model="detailsForm.travel_type" :disabled="!canEdit" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm disabled:bg-slate-100">
              <option value="oed_initiated">OED Initiated</option>
              <option value="campus_initiated">Campus Initiated</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Funding</label>
            <select v-model="detailsForm.funding_source" :disabled="!canEdit" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm disabled:bg-slate-100">
              <option value="campus_funds">Campus Funds</option>
              <option value="oed_funds">OED Funds</option>
              <option value="external_funds">External Funds</option>
              <option value="personal_no_cost">No Cost to Campus</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Travel Mode</label>
            <select v-model="detailsForm.travel_mode" :disabled="!canEdit" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm disabled:bg-slate-100">
              <option value="land">Land</option>
              <option value="air">Air</option>
              <option value="sea">Sea</option>
              <option value="mixed">Mixed</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Origin</label>
            <input v-model="detailsForm.origin" :disabled="!canEdit" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm disabled:bg-slate-100" />
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Destination</label>
            <input v-model="detailsForm.destination" :disabled="!canEdit" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm disabled:bg-slate-100" />
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Division Chief</label>
            <select v-model="detailsForm.division_chief_id" :disabled="!canEdit" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm disabled:bg-slate-100">
              <option value="">Select approver</option>
              <option v-for="chief in lookups.divisionChiefs" :key="chief.id" :value="chief.id">{{ chief.name }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Start Date</label>
            <input v-model="detailsForm.start_date" type="date" :disabled="!canEdit" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm disabled:bg-slate-100" />
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">End Date</label>
            <input v-model="detailsForm.end_date" type="date" :disabled="!canEdit" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm disabled:bg-slate-100" />
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Cash Advance</label>
            <input v-model="detailsForm.cash_advance_amount" type="number" min="0" step="0.01" :disabled="!canEdit || !detailsForm.requires_cash_advance" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm disabled:bg-slate-100" />
          </div>
          <label class="flex items-center gap-2 text-sm text-slate-700">
            <input v-model="detailsForm.requires_cash_advance" :disabled="!canEdit" type="checkbox" class="rounded border-slate-300 text-indigo-600" />
            Requires cash advance
          </label>
          <div class="md:col-span-3">
            <label class="mb-1 block text-xs font-medium text-slate-600">Purpose</label>
            <textarea v-model="detailsForm.purpose" :disabled="!canEdit" rows="3" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm disabled:bg-slate-100"></textarea>
          </div>
        </div>
      </section>

      <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
          <h2 class="text-sm font-semibold text-slate-900">Proposed Itinerary of Travel</h2>
          <div class="flex gap-2">
            <button v-if="canEdit" @click="addRow" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
              <PlusIcon class="h-4 w-4" />
              Row
            </button>
            <button v-if="canEdit" @click="saveItinerary" :disabled="itineraryForm.processing" class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-60">Save IOT</button>
          </div>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-slate-500">Date</th>
                <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-slate-500">Places to be visited</th>
                <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-slate-500">Departure</th>
                <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-slate-500">Arrival</th>
                <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-slate-500">Means</th>
                <th class="px-3 py-2 text-right text-xs font-semibold uppercase text-slate-500">Transport</th>
                <th class="px-3 py-2 text-right text-xs font-semibold uppercase text-slate-500">Per Diem</th>
                <th class="px-3 py-2 text-right text-xs font-semibold uppercase text-slate-500">Others</th>
                <th class="px-3 py-2 text-right text-xs font-semibold uppercase text-slate-500">Total</th>
                <th class="px-3 py-2"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="(row, index) in itineraryRows" :key="index">
                <td class="px-3 py-2"><input v-model="row.travel_date" type="date" :disabled="!canEdit" class="w-36 rounded border border-slate-200 px-2 py-1 text-sm disabled:bg-slate-100" /></td>
                <td class="px-3 py-2"><input v-model="row.places_to_visit" :disabled="!canEdit" class="w-64 rounded border border-slate-200 px-2 py-1 text-sm disabled:bg-slate-100" /></td>
                <td class="px-3 py-2"><input v-model="row.departure_time" type="time" :disabled="!canEdit" class="w-28 rounded border border-slate-200 px-2 py-1 text-sm disabled:bg-slate-100" /></td>
                <td class="px-3 py-2"><input v-model="row.arrival_time" type="time" :disabled="!canEdit" class="w-28 rounded border border-slate-200 px-2 py-1 text-sm disabled:bg-slate-100" /></td>
                <td class="px-3 py-2"><input v-model="row.means_of_transportation" :disabled="!canEdit" class="w-40 rounded border border-slate-200 px-2 py-1 text-sm disabled:bg-slate-100" /></td>
                <td class="px-3 py-2"><input v-model="row.transportation_cost" type="number" min="0" step="0.01" :disabled="!canEdit" class="w-28 rounded border border-slate-200 px-2 py-1 text-right text-sm disabled:bg-slate-100" /></td>
                <td class="px-3 py-2"><input v-model="row.per_diem" type="number" min="0" step="0.01" :disabled="!canEdit" class="w-28 rounded border border-slate-200 px-2 py-1 text-right text-sm disabled:bg-slate-100" /></td>
                <td class="px-3 py-2"><input v-model="row.other_cost" type="number" min="0" step="0.01" :disabled="!canEdit" class="w-28 rounded border border-slate-200 px-2 py-1 text-right text-sm disabled:bg-slate-100" /></td>
                <td class="px-3 py-2 text-right text-sm font-medium text-slate-900">{{ money(rowTotal(row)) }}</td>
                <td class="px-3 py-2">
                  <button v-if="canEdit" @click="removeRow(index)" class="rounded p-1 text-slate-400 hover:bg-red-50 hover:text-red-600">
                    <TrashIcon class="h-4 w-4" />
                  </button>
                </td>
              </tr>
            </tbody>
            <tfoot class="bg-slate-50">
              <tr>
                <td colspan="5" class="px-3 py-3 text-right text-sm font-semibold text-slate-700">TOTAL</td>
                <td class="px-3 py-3 text-right text-sm font-semibold text-slate-900">{{ money(totals.transportation) }}</td>
                <td class="px-3 py-3 text-right text-sm font-semibold text-slate-900">{{ money(totals.per_diem) }}</td>
                <td class="px-3 py-3 text-right text-sm font-semibold text-slate-900">{{ money(totals.others) }}</td>
                <td class="px-3 py-3 text-right text-sm font-semibold text-slate-900">{{ money(totals.total) }}</td>
                <td></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </section>

      <div class="grid gap-6 xl:grid-cols-2">
        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
          <h2 class="text-sm font-semibold text-slate-900">Documents and Linked Records</h2>
          <div class="mt-4 grid gap-4 md:grid-cols-2">
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-600">Travel Order Issuance</label>
              <select v-model="linksForm.travel_order_issuance_id" :disabled="!canEdit" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm disabled:bg-slate-100">
                <option value="">None</option>
                <option v-for="item in lookups.travelOrders" :key="item.id" :value="item.id">{{ item.control_number }} - {{ item.title }}</option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-600">Special Order Issuance</label>
              <select v-model="linksForm.special_order_issuance_id" :disabled="!canEdit" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm disabled:bg-slate-100">
                <option value="">None</option>
                <option v-for="item in lookups.specialOrders" :key="item.id" :value="item.id">{{ item.control_number }} - {{ item.title }}</option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-600">Vehicle Request</label>
              <select v-model="linksForm.vehicle_request_id" :disabled="!canEdit" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm disabled:bg-slate-100">
                <option value="">None</option>
                <option v-for="item in lookups.vehicleRequests" :key="item.id" :value="item.id">#{{ item.id }} - {{ item.destination }} ({{ item.status }})</option>
              </select>
              <Link :href="route('vehicle-requests.index')" class="mt-1 inline-block text-xs font-medium text-indigo-600">Create or view vehicle requests</Link>
            </div>
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-600">ORS</label>
              <select v-model="linksForm.ors_id" :disabled="!canEdit" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm disabled:bg-slate-100">
                <option value="">None</option>
                <option v-for="item in lookups.orsRecords" :key="item.id" :value="item.id">{{ item.ors_number || `ORS #${item.id}` }} - {{ item.activity_title }}</option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-600">DV</label>
              <select v-model="linksForm.dv_id" :disabled="!canEdit" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm disabled:bg-slate-100">
                <option value="">None</option>
                <option v-for="item in lookups.dvRecords" :key="item.id" :value="item.id">{{ item.dv_number || `DV #${item.id}` }} - {{ item.activity_title }}</option>
              </select>
            </div>
          </div>
          <button v-if="canEdit" @click="saveLinks" class="mt-4 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">Save Links</button>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
          <h2 class="text-sm font-semibold text-slate-900">Attachments</h2>
          <form v-if="canEdit" @submit.prevent="uploadAttachment" class="mt-4 grid gap-3 md:grid-cols-[180px_1fr_auto]">
            <select v-model="uploadForm.type" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
              <option value="oed_travel_order">OED Travel Order</option>
              <option value="special_order">Special Order</option>
              <option value="ticket">Ticket</option>
              <option value="boarding_pass">Boarding Pass</option>
              <option value="certificate_of_appearance">Certificate of Appearance</option>
              <option value="receipt">Receipt</option>
              <option value="liquidation">Liquidation</option>
              <option value="other">Other</option>
            </select>
            <input type="file" @change="onFile" class="rounded-lg border border-slate-200 px-3 py-2 text-sm" />
            <button :disabled="uploadForm.processing || !uploadForm.file_base64" class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-60">Upload</button>
          </form>
          <div class="mt-4 divide-y divide-slate-100 rounded-lg border border-slate-100">
            <div v-for="attachment in travel.attachments" :key="attachment.id" class="flex items-center justify-between gap-3 px-3 py-3">
              <a :href="attachment.url" target="_blank" class="flex min-w-0 items-center gap-2 text-sm font-medium text-slate-700 hover:text-indigo-600">
                <PaperClipIcon class="h-4 w-4 shrink-0" />
                <span class="truncate">{{ attachment.file_name }}</span>
              </a>
              <button v-if="canEdit" @click="deleteAttachment(attachment)" class="rounded p-1 text-slate-400 hover:bg-red-50 hover:text-red-600">
                <TrashIcon class="h-4 w-4" />
              </button>
            </div>
            <p v-if="!travel.attachments?.length" class="px-3 py-8 text-center text-sm text-slate-400">No attachments uploaded.</p>
          </div>
        </section>
      </div>

      <div class="grid gap-6 xl:grid-cols-2">
        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
          <div class="flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-900">Authority to Book Flights</h2>
            <span v-if="travel.flight_authority" :class="['rounded-full px-2 py-0.5 text-xs font-medium', statusClass(travel.flight_authority.status)]">{{ travel.flight_authority.status }}</span>
          </div>
          <div class="mt-4 grid gap-4 md:grid-cols-2">
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-600">Passenger</label>
              <input v-model="flightForm.passenger_name" :disabled="!canEdit" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm disabled:bg-slate-100" />
            </div>
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-600">Route</label>
              <input v-model="flightForm.route" :disabled="!canEdit" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm disabled:bg-slate-100" />
            </div>
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-600">Departure</label>
              <input v-model="flightForm.preferred_departure_date" type="date" :disabled="!canEdit" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm disabled:bg-slate-100" />
            </div>
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-600">Return</label>
              <input v-model="flightForm.preferred_return_date" type="date" :disabled="!canEdit" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm disabled:bg-slate-100" />
            </div>
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-600">Estimated Airfare</label>
              <input v-model="flightForm.estimated_airfare" type="number" min="0" step="0.01" :disabled="!canEdit" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm disabled:bg-slate-100" />
            </div>
            <div class="md:col-span-2">
              <label class="mb-1 block text-xs font-medium text-slate-600">Notes</label>
              <textarea v-model="flightForm.booking_notes" :disabled="!canEdit" rows="3" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm disabled:bg-slate-100"></textarea>
            </div>
          </div>
          <div class="mt-4 flex flex-wrap gap-2">
            <button v-if="canEdit" @click="saveFlight(false)" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Save Draft</button>
            <button v-if="canEdit" @click="saveFlight(true)" class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">Request Booking</button>
            <button v-if="canFadReview && travel.flight_authority?.status === 'requested'" @click="approveFlight" class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-700">Approve Booking</button>
          </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
          <h2 class="text-sm font-semibold text-slate-900">Finance and Liquidation</h2>
          <dl class="mt-4 grid gap-3 sm:grid-cols-2">
            <div class="rounded-lg bg-slate-50 p-3">
              <dt class="text-xs font-medium text-slate-500">Cash Advance</dt>
              <dd class="mt-1 text-sm font-semibold text-slate-900">PHP {{ money(travel.cash_advance_amount) }}</dd>
            </div>
            <div class="rounded-lg bg-slate-50 p-3">
              <dt class="text-xs font-medium text-slate-500">Liquidation</dt>
              <dd class="mt-1 text-sm font-semibold capitalize text-slate-900">{{ travel.liquidation_status || '-' }}</dd>
            </div>
            <div class="rounded-lg bg-slate-50 p-3">
              <dt class="text-xs font-medium text-slate-500">ORS</dt>
              <dd class="mt-1 text-sm font-semibold text-slate-900">{{ travel.ors?.ors_number || '-' }}</dd>
            </div>
            <div class="rounded-lg bg-slate-50 p-3">
              <dt class="text-xs font-medium text-slate-500">DV</dt>
              <dd class="mt-1 text-sm font-semibold text-slate-900">{{ travel.dv?.dv_number || '-' }}</dd>
            </div>
          </dl>
          <div class="mt-4">
            <label class="mb-1 block text-xs font-medium text-slate-600">Liquidation Remarks</label>
            <textarea v-model="liquidationForm.liquidation_remarks" :disabled="!perms.canFinance" rows="3" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm disabled:bg-slate-100"></textarea>
          </div>
          <div class="mt-4 flex flex-wrap gap-2">
            <button v-if="canFadReview && !['completed', 'liquidated'].includes(travel.status)" @click="completeTravel" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Mark Completed</button>
            <button v-if="perms.canFinance && travel.status === 'completed'" @click="liquidateTravel" class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-700">Mark Liquidated</button>
          </div>
        </section>
      </div>

      <section v-if="canReturn" class="rounded-lg border border-red-200 bg-red-50 p-5">
        <h2 class="text-sm font-semibold text-red-900">Return for Revision</h2>
        <div class="mt-3 flex flex-col gap-2 sm:flex-row">
          <input v-model="returnForm.return_reason" placeholder="Reason for return" class="min-w-0 flex-1 rounded-lg border border-red-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500" />
          <button @click="returnTravel" :disabled="returnForm.processing || !returnForm.return_reason" class="inline-flex items-center justify-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-60">
            <XCircleIcon class="h-4 w-4" />
            Return
          </button>
        </div>
      </section>
    </div>
  </AdminLayout>
</template>
