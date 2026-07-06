<script setup>
import { computed, ref } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppCard from '@/Components/AppCard.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppInput from '@/Components/AppInput.vue'
import AppSelect from '@/Components/AppSelect.vue'
import AppTextarea from '@/Components/AppTextarea.vue'
import EmptyState from '@/Components/EmptyState.vue'
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

const statusBadgeColor = (status) => {
  if (['liquidated', 'completed', 'released'].includes(status)) return 'green'
  if (['returned', 'cancelled'].includes(status)) return 'red'
  if (status === 'draft') return 'slate'
  return 'amber'
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
      <Link :href="route('travel.index')" class="inline-flex items-center gap-1 text-sm font-medium text-slate-500 hover:text-indigo-600">
        <ArrowLeftIcon class="h-4 w-4" />
        Travel Requests
      </Link>

      <AppCard>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
          <div>
            <div class="flex flex-wrap items-center gap-3">
              <h1 class="text-xl font-semibold text-slate-900">{{ travel.control_no }}</h1>
              <AppBadge :color="statusBadgeColor(travel.status)">{{ travel.status_label }}</AppBadge>
            </div>
            <p class="mt-1 text-sm text-slate-500">{{ travel.traveler?.name }} · {{ travel.destination }} · {{ fmtDate(travel.start_date) }} to {{ fmtDate(travel.end_date) }}</p>
            <p v-if="travel.return_reason" class="mt-2 rounded-lg bg-danger-50 px-3 py-2 text-sm text-danger-700">{{ travel.return_reason }}</p>
          </div>
          <div class="flex flex-wrap gap-2">
            <AppButton as="a" :href="route('travel.iot.print', travel.id)" target="_blank" variant="secondary">
              <PrinterIcon class="h-4 w-4" />
              Print IOT
            </AppButton>
            <AppButton v-if="canSubmit" @click="submitTravel">
              <ClipboardDocumentCheckIcon class="h-4 w-4" />
              Submit
            </AppButton>
            <AppButton v-if="canDivisionApprove" variant="success" @click="approveDivision">
              <CheckCircleIcon class="h-4 w-4" />
              Division Approve
            </AppButton>
            <AppButton v-if="canFadReview && travel.status === 'division_approved'" variant="success" @click="reviewFad">
              <CheckCircleIcon class="h-4 w-4" />
              FAD Review
            </AppButton>
            <AppButton v-if="canOcdApprove" variant="success" @click="approveOcd">
              <CheckCircleIcon class="h-4 w-4" />
              OCD Approve
            </AppButton>
          </div>
        </div>
      </AppCard>

      <AppCard title="Travel Details">
        <template #header>
          <AppButton v-if="canEdit" size="sm" :loading="detailsForm.processing" :disabled="detailsForm.processing" @click="saveDetails">Save Details</AppButton>
        </template>
        <div class="grid gap-4 md:grid-cols-3">
          <AppSelect v-model="detailsForm.travel_type" label="Type" :disabled="!canEdit" :show-blank="false">
            <option value="oed_initiated">OED Initiated</option>
            <option value="campus_initiated">Campus Initiated</option>
          </AppSelect>
          <AppSelect v-model="detailsForm.funding_source" label="Funding" :disabled="!canEdit" :show-blank="false">
            <option value="campus_funds">Campus Funds</option>
            <option value="oed_funds">OED Funds</option>
            <option value="external_funds">External Funds</option>
            <option value="personal_no_cost">No Cost to Campus</option>
          </AppSelect>
          <AppSelect v-model="detailsForm.travel_mode" label="Travel Mode" :disabled="!canEdit" :show-blank="false">
            <option value="land">Land</option>
            <option value="air">Air</option>
            <option value="sea">Sea</option>
            <option value="mixed">Mixed</option>
          </AppSelect>
          <AppInput v-model="detailsForm.origin" label="Origin" :disabled="!canEdit" />
          <AppInput v-model="detailsForm.destination" label="Destination" :disabled="!canEdit" />
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Division Chief</label>
            <select v-model="detailsForm.division_chief_id" :disabled="!canEdit" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm disabled:bg-slate-100">
              <option value="">Select approver</option>
              <option v-for="chief in lookups.divisionChiefs" :key="chief.id" :value="chief.id">{{ chief.name }}</option>
            </select>
          </div>
          <AppInput v-model="detailsForm.start_date" type="date" label="Start Date" :disabled="!canEdit" />
          <AppInput v-model="detailsForm.end_date" type="date" label="End Date" :disabled="!canEdit" />
          <AppInput v-model="detailsForm.cash_advance_amount" type="number" min="0" step="0.01" label="Cash Advance" :disabled="!canEdit || !detailsForm.requires_cash_advance" />
          <label class="flex items-center gap-2 text-sm text-slate-700">
            <input v-model="detailsForm.requires_cash_advance" :disabled="!canEdit" type="checkbox" class="rounded border-slate-300 text-indigo-600" />
            Requires cash advance
          </label>
          <div class="md:col-span-3">
            <AppTextarea v-model="detailsForm.purpose" label="Purpose" :disabled="!canEdit" :rows="3" />
          </div>
        </div>
      </AppCard>

      <AppCard title="Proposed Itinerary of Travel" :padded="false">
        <template #header>
          <div class="flex gap-2">
            <AppButton v-if="canEdit" variant="secondary" size="sm" @click="addRow">
              <PlusIcon class="h-4 w-4" />
              Row
            </AppButton>
            <AppButton v-if="canEdit" size="sm" :loading="itineraryForm.processing" :disabled="itineraryForm.processing" @click="saveItinerary">Save IOT</AppButton>
          </div>
        </template>
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
                  <AppIconButton v-if="canEdit" label="Remove row" variant="danger" size="sm" @click="removeRow(index)">
                    <TrashIcon class="h-4 w-4" />
                  </AppIconButton>
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
      </AppCard>

      <div class="grid gap-6 xl:grid-cols-2">
        <AppCard title="Documents and Linked Records">
          <div class="grid gap-4 md:grid-cols-2">
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
          <AppButton v-if="canEdit" class="mt-4" @click="saveLinks">Save Links</AppButton>
        </AppCard>

        <AppCard title="Attachments">
          <form v-if="canEdit" @submit.prevent="uploadAttachment" class="grid gap-3 md:grid-cols-[180px_1fr_auto]">
            <AppSelect v-model="uploadForm.type" :show-blank="false">
              <option value="oed_travel_order">OED Travel Order</option>
              <option value="special_order">Special Order</option>
              <option value="ticket">Ticket</option>
              <option value="boarding_pass">Boarding Pass</option>
              <option value="certificate_of_appearance">Certificate of Appearance</option>
              <option value="receipt">Receipt</option>
              <option value="liquidation">Liquidation</option>
              <option value="other">Other</option>
            </AppSelect>
            <input type="file" @change="onFile" class="rounded-lg border border-slate-200 px-3 py-2 text-sm" />
            <AppButton type="submit" :disabled="uploadForm.processing || !uploadForm.file_base64">Upload</AppButton>
          </form>
          <div class="mt-4 divide-y divide-slate-100 rounded-lg border border-slate-100">
            <div v-for="attachment in travel.attachments" :key="attachment.id" class="flex items-center justify-between gap-3 px-3 py-3">
              <a :href="attachment.url" target="_blank" class="flex min-w-0 items-center gap-2 text-sm font-medium text-slate-700 hover:text-indigo-600">
                <PaperClipIcon class="h-4 w-4 shrink-0" />
                <span class="truncate">{{ attachment.file_name }}</span>
              </a>
              <AppIconButton v-if="canEdit" label="Delete attachment" variant="danger" size="sm" @click="deleteAttachment(attachment)">
                <TrashIcon class="h-4 w-4" />
              </AppIconButton>
            </div>
            <EmptyState v-if="!travel.attachments?.length" title="No attachments uploaded." />
          </div>
        </AppCard>
      </div>

      <div class="grid gap-6 xl:grid-cols-2">
        <AppCard title="Authority to Book Flights">
          <template #header>
            <AppBadge v-if="travel.flight_authority" :color="statusBadgeColor(travel.flight_authority.status)">{{ travel.flight_authority.status }}</AppBadge>
          </template>
          <div class="grid gap-4 md:grid-cols-2">
            <AppInput v-model="flightForm.passenger_name" label="Passenger" :disabled="!canEdit" />
            <AppInput v-model="flightForm.route" label="Route" :disabled="!canEdit" />
            <AppInput v-model="flightForm.preferred_departure_date" type="date" label="Departure" :disabled="!canEdit" />
            <AppInput v-model="flightForm.preferred_return_date" type="date" label="Return" :disabled="!canEdit" />
            <AppInput v-model="flightForm.estimated_airfare" type="number" min="0" step="0.01" label="Estimated Airfare" :disabled="!canEdit" />
            <div class="md:col-span-2">
              <AppTextarea v-model="flightForm.booking_notes" label="Notes" :disabled="!canEdit" :rows="3" />
            </div>
          </div>
          <div class="mt-4 flex flex-wrap gap-2">
            <AppButton v-if="canEdit" variant="secondary" @click="saveFlight(false)">Save Draft</AppButton>
            <AppButton v-if="canEdit" @click="saveFlight(true)">Request Booking</AppButton>
            <AppButton v-if="canFadReview && travel.flight_authority?.status === 'requested'" variant="success" @click="approveFlight">Approve Booking</AppButton>
          </div>
        </AppCard>

        <AppCard title="Finance and Liquidation">
          <dl class="grid gap-3 sm:grid-cols-2">
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
            <AppTextarea v-model="liquidationForm.liquidation_remarks" label="Liquidation Remarks" :disabled="!perms.canFinance" :rows="3" />
          </div>
          <div class="mt-4 flex flex-wrap gap-2">
            <AppButton v-if="canFadReview && !['completed', 'liquidated'].includes(travel.status)" variant="secondary" @click="completeTravel">Mark Completed</AppButton>
            <AppButton v-if="perms.canFinance && travel.status === 'completed'" variant="success" @click="liquidateTravel">Mark Liquidated</AppButton>
          </div>
        </AppCard>
      </div>

      <div v-if="canReturn" class="rounded-lg border border-danger-100 bg-danger-50 p-5">
        <h2 class="text-sm font-semibold text-danger-700">Return for Revision</h2>
        <div class="mt-3 flex flex-col gap-2 sm:flex-row">
          <div class="min-w-0 flex-1">
            <AppInput v-model="returnForm.return_reason" placeholder="Reason for return" />
          </div>
          <AppButton variant="danger" :disabled="returnForm.processing || !returnForm.return_reason" @click="returnTravel">
            <XCircleIcon class="h-4 w-4" />
            Return
          </AppButton>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
