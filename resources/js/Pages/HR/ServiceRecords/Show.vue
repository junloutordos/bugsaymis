<script setup>
import { computed, ref, watch } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import Swal from 'sweetalert2'
import {
  ArrowLeftIcon, ArrowUpTrayIcon, CheckBadgeIcon, ClockIcon, DocumentCheckIcon,
  DocumentTextIcon, ExclamationTriangleIcon, PencilSquareIcon, PlusIcon, ShieldCheckIcon,
} from '@heroicons/vue/24/outline'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppButton from '@/Components/AppButton.vue'
import AppCard from '@/Components/AppCard.vue'
import AppInput from '@/Components/AppInput.vue'
import AppModal from '@/Components/AppModal.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppSelect from '@/Components/AppSelect.vue'
import AppTextarea from '@/Components/AppTextarea.vue'

const props = defineProps({
  employee: Object,
  entries: { type: Array, default: () => [] },
  actions: { type: Array, default: () => [] },
  versions: { type: Array, default: () => [] },
  diagnostics: Object,
  hasPdsWorkExperience: Boolean,
  permissions: Object,
})

const activeTab = ref('record')
const showEntryModal = ref(false)
const showEvidenceModal = ref(false)
const showActionModal = ref(false)
const editingEntry = ref(null)
const evidenceEntry = ref(null)
const selectedFile = ref(null)

const blankEntry = () => ({
  service_from: '', service_to: '', appointment_issued_at: '', assumed_at: '', position_title: '',
  employment_status: '', appointment_nature: '', agency_name: 'Philippine Science High School – Caraga Region Campus',
  station_place: 'Butuan City', branch_division: '', plantilla_item_no: '', salary_amount: '', salary_basis: 'annual',
  salary_grade: '', salary_step: '', csc_action: '', csc_action_at: '', credited_as_government_service: true,
  separation_date: '', separation_cause: '', remarks: '', lwop_periods: [],
})
const entryForm = useForm(blankEntry())
const evidenceForm = useForm({ document_type: 'appointment', title: '', filename: '', data_uri: '' })
const actionForm = useForm({ action_type: 'designation', effective_from: '', effective_to: '', office_order_no: '', station_place: '', branch_division: '', remarks: '' })

const visibleEntries = computed(() => props.entries.filter((entry) => entry.status !== 'superseded'))
const supersededEntries = computed(() => props.entries.filter((entry) => entry.status === 'superseded'))
const currentEntry = computed(() => visibleEntries.value.filter((entry) => !entry.service_to).sort((a, b) => String(b.service_from).localeCompare(String(a.service_from)))[0])
const isOwnView = computed(() => !props.permissions.manage)

const statusOptions = [
  ['permanent', 'Permanent'], ['temporary', 'Temporary'], ['coterminous', 'Coterminous'], ['fixed_term', 'Fixed Term'],
  ['contractual', 'Contractual Appointment'], ['substitute', 'Substitute'], ['provisional', 'Provisional'], ['casual', 'Casual'],
  ['job_order', 'Job Order (non-creditable)'], ['contract_of_service', 'Contract of Service (non-creditable)'],
]
const natureOptions = [
  ['original', 'Original'], ['promotion', 'Promotion'], ['transfer', 'Transfer'], ['reemployment', 'Reemployment'],
  ['reappointment', 'Reappointment'], ['reinstatement', 'Reinstatement'], ['reclassification', 'Reclassification'], ['demotion', 'Demotion'],
]

watch(() => entryForm.employment_status, (status) => {
  if (['job_order', 'contract_of_service'].includes(status)) entryForm.credited_as_government_service = false
})

function openCreate() {
  editingEntry.value = null
  entryForm.defaults(blankEntry())
  entryForm.reset()
  showEntryModal.value = true
}

function openEdit(entry) {
  editingEntry.value = entry
  entryForm.defaults({
    ...blankEntry(), ...entry,
    salary_amount: entry.salary_amount ?? '', salary_grade: entry.salary_grade ?? '', salary_step: entry.salary_step ?? '',
    lwop_periods: (entry.lwop_periods ?? []).map((period) => ({ ...period })),
  })
  entryForm.reset()
  showEntryModal.value = true
}

function saveEntry() {
  const options = { preserveScroll: true, onSuccess: () => { showEntryModal.value = false; editingEntry.value = null } }
  if (editingEntry.value) entryForm.put(route('hr.service-records.update', [props.employee.id, editingEntry.value.id]), options)
  else entryForm.post(route('hr.service-records.store', props.employee.id), options)
}

function addLwop() { entryForm.lwop_periods.push({ date_from: '', date_to: '', days: '', reference_no: '', remarks: '' }) }

async function verifyEntry(entry) {
  const result = await Swal.fire({ icon: 'question', title: 'Verify this service entry?', text: 'Only evidence-backed, policy-compliant entries should be verified.', showCancelButton: true, confirmButtonText: 'Verify entry' })
  if (result.isConfirmed) router.post(route('hr.service-records.verify', entry.id), {}, { preserveScroll: true })
}

async function supersedeEntry(entry) {
  const result = await Swal.fire({ title: 'Supersede service entry', input: 'textarea', inputLabel: 'Reason for superseding', inputPlaceholder: 'State the correction or documentary basis…', showCancelButton: true, confirmButtonColor: '#dc2626', inputValidator: (value) => !value?.trim() ? 'A reason is required.' : undefined })
  if (result.isConfirmed) router.post(route('hr.service-records.supersede', entry.id), { reason: result.value }, { preserveScroll: true })
}

async function importPds() {
  const result = await Swal.fire({ icon: 'info', title: 'Import PDS work experience?', text: 'Imported rows remain drafts until HR verifies their documentary basis.', showCancelButton: true, confirmButtonText: 'Import as drafts' })
  if (result.isConfirmed) router.post(route('hr.service-records.import-pds', props.employee.id), {}, { preserveScroll: true })
}

function openEvidence(entry) {
  evidenceEntry.value = entry
  evidenceForm.reset()
  evidenceForm.document_type = 'appointment'
  selectedFile.value = null
  showEvidenceModal.value = true
}

function readEvidence(event) {
  const file = event.target.files?.[0]
  selectedFile.value = file ?? null
  if (!file) return
  evidenceForm.filename = file.name
  if (!evidenceForm.title) evidenceForm.title = file.name.replace(/\.[^.]+$/, '')
  const reader = new FileReader()
  reader.onload = () => { evidenceForm.data_uri = reader.result }
  reader.readAsDataURL(file)
}

function uploadEvidence() {
  evidenceForm.post(route('hr.service-records.evidence.store', evidenceEntry.value.id), {
    preserveScroll: true,
    onSuccess: () => { showEvidenceModal.value = false; evidenceEntry.value = null },
  })
}

async function removeEvidence(evidence) {
  const result = await Swal.fire({ icon: 'warning', title: 'Remove supporting document?', showCancelButton: true, confirmButtonText: 'Remove', confirmButtonColor: '#dc2626' })
  if (result.isConfirmed) router.delete(route('hr.service-records.evidence.destroy', evidence.id), { preserveScroll: true })
}

function saveAction() {
  actionForm.post(route('hr.service-records.actions.store', props.employee.id), {
    preserveScroll: true,
    onSuccess: () => { showActionModal.value = false; actionForm.reset() },
  })
}

function formatDate(value) { return value ? new Date(`${String(value).slice(0, 10)}T00:00:00`).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' }) : 'Present' }
function money(value, basis) { return value !== null && value !== '' ? `₱${Number(value).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${basis ? `/ ${basis}` : ''}` : '—' }
function label(value) { return String(value ?? '—').replaceAll('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase()) }
function badgeColor(status) { return ({ verified: 'green', draft: 'amber', superseded: 'slate' })[status] ?? 'slate' }
</script>

<template>
  <Head :title="`Service Record — ${employee.name}`" />
  <AdminLayout title="Service Record">
    <div class="mx-auto max-w-7xl">
      <AppPageHeader :title="employee.name" :subtitle="`${employee.employee_no || 'No employee number'} · ${employee.position || 'Position not recorded'}`" hero>
        <template #actions>
          <AppButton v-if="permissions.manage" as="link" :href="route('hr.service-records.index')" variant="secondary"><ArrowLeftIcon class="h-4 w-4" /> Registry</AppButton>
          <AppButton v-if="permissions.export" as="a" :href="route('hr.service-records.export', employee.id)" variant="secondary"><DocumentTextIcon class="h-4 w-4" /> Export CSV</AppButton>
          <AppButton v-if="!permissions.manage" as="link" :href="route('hr.document-requests.index')" variant="secondary"><DocumentCheckIcon class="h-4 w-4" /> Request Certified Copy</AppButton>
          <AppButton v-if="permissions.manage && hasPdsWorkExperience" variant="secondary" @click="importPds"><ArrowUpTrayIcon class="h-4 w-4" /> Import PDS</AppButton>
          <AppButton v-if="permissions.manage" @click="openCreate"><PlusIcon class="h-4 w-4" /> Add Entry</AppButton>
        </template>
      </AppPageHeader>

      <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <AppCard><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Current appointment</p><p class="mt-2 font-semibold text-slate-900">{{ currentEntry?.position_title || employee.position || 'Not recorded' }}</p><p class="mt-1 text-xs capitalize text-slate-500">{{ label(currentEntry?.employment_status) }}</p></AppCard>
        <AppCard><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Credited service</p><p class="mt-2 text-2xl font-semibold text-slate-900">{{ Number(diagnostics.credited_calendar_days).toLocaleString('en-PH') }}</p><p class="mt-1 text-xs text-slate-500">verified calendar days, net of LWOP</p></AppCard>
        <AppCard><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Leave without pay</p><p class="mt-2 text-2xl font-semibold text-slate-900">{{ Number(diagnostics.lwop_days).toLocaleString('en-PH') }}</p><p class="mt-1 text-xs text-slate-500">recorded days</p></AppCard>
        <AppCard><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Record quality</p><div class="mt-2 flex items-center gap-2"><p class="text-2xl font-semibold text-slate-900">{{ diagnostics.quality_score }}%</p><AppBadge :color="diagnostics.quality_score === 100 ? 'green' : 'amber'">{{ diagnostics.warnings.length ? 'Review' : 'Ready' }}</AppBadge></div><p class="mt-1 text-xs text-slate-500">{{ diagnostics.verified_entries }} verified · {{ diagnostics.draft_entries }} draft</p></AppCard>
      </div>

      <div class="mb-5 flex gap-1 rounded-xl bg-slate-100 p-1">
        <button v-for="tab in [['record','Service Record'],['actions','HR Actions'],['quality','Quality Review'],['versions','Certified Versions']]" :key="tab[0]" class="rounded-lg px-4 py-2 text-sm font-medium transition" :class="activeTab === tab[0] ? 'bg-white text-indigo-700 shadow-sm' : 'text-slate-600 hover:text-slate-900'" @click="activeTab = tab[0]">{{ tab[1] }}</button>
      </div>

      <AppCard v-if="activeTab === 'record'" :padded="false">
        <div class="border-b border-slate-100 px-5 py-4"><h2 class="font-semibold text-slate-900">Chronological service ledger</h2><p class="mt-0.5 text-xs text-slate-500">Verified entries are the source of certified Service Records. Salary changes should be encoded as separate effective-dated rows.</p></div>
        <div class="overflow-x-auto">
          <table class="w-full min-w-[1180px] text-left text-xs">
            <thead class="bg-slate-50 font-semibold uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3">Inclusive dates</th><th class="px-4 py-3">Appointment</th><th class="px-4 py-3">Agency / station</th><th class="px-4 py-3">Salary</th><th class="px-4 py-3">LWOP</th><th class="px-4 py-3">Evidence</th><th class="px-4 py-3">Status</th><th v-if="permissions.manage" class="px-4 py-3 text-right">Actions</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="entry in visibleEntries" :key="entry.id" class="align-top hover:bg-slate-50/60">
                <td class="px-4 py-4"><p class="font-medium text-slate-800">{{ formatDate(entry.service_from) }}</p><p class="text-slate-500">to {{ formatDate(entry.service_to) }}</p><p v-if="entry.assumed_at" class="mt-1 text-[11px] text-indigo-600">Assumed {{ formatDate(entry.assumed_at) }}</p></td>
                <td class="px-4 py-4"><p class="font-medium text-slate-900">{{ entry.position_title }}</p><p class="mt-1 text-slate-500">{{ label(entry.employment_status) }} · {{ label(entry.appointment_nature) }}</p><p v-if="entry.plantilla_item_no" class="text-slate-500">Item {{ entry.plantilla_item_no }}</p></td>
                <td class="px-4 py-4"><p class="text-slate-800">{{ entry.agency_name }}</p><p class="mt-1 text-slate-500">{{ [entry.station_place, entry.branch_division].filter(Boolean).join(' · ') || '—' }}</p></td>
                <td class="px-4 py-4"><p class="font-medium text-slate-800">{{ money(entry.salary_amount, entry.salary_basis) }}</p><p v-if="entry.salary_grade" class="mt-1 text-slate-500">SG {{ entry.salary_grade }}, Step {{ entry.salary_step || '—' }}</p></td>
                <td class="px-4 py-4"><p class="text-slate-700">{{ entry.lwop_periods?.length || 0 }} period{{ entry.lwop_periods?.length === 1 ? '' : 's' }}</p><p v-if="entry.separation_date" class="mt-1 text-rose-600">Separated {{ formatDate(entry.separation_date) }} · {{ entry.separation_cause }}</p></td>
                <td class="px-4 py-4"><div class="space-y-1"><div v-for="evidence in entry.evidence" :key="evidence.id" class="flex items-center gap-1.5"><a :href="route('hr.service-records.evidence.show', evidence.id)" target="_blank" class="max-w-[150px] truncate text-indigo-600 hover:underline">{{ evidence.title }}</a><button v-if="permissions.manage" class="text-rose-500 hover:text-rose-700" @click="removeEvidence(evidence)">×</button></div><button v-if="permissions.manage" class="text-indigo-600 hover:underline" @click="openEvidence(entry)">+ Add evidence</button><span v-else-if="!entry.evidence?.length" class="text-slate-400">No evidence</span></div></td>
                <td class="px-4 py-4"><AppBadge :color="badgeColor(entry.status)">{{ label(entry.status) }}</AppBadge><p class="mt-1 text-[11px]" :class="entry.credited_as_government_service ? 'text-emerald-600' : 'text-slate-500'">{{ entry.credited_as_government_service ? 'Creditable service' : 'Non-creditable engagement' }}</p></td>
                <td v-if="permissions.manage" class="px-4 py-4"><div class="flex justify-end gap-1"><button class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-indigo-600" title="Edit" @click="openEdit(entry)"><PencilSquareIcon class="h-4 w-4" /></button><button v-if="permissions.verify && entry.status === 'draft'" class="rounded-lg p-2 text-slate-500 hover:bg-emerald-50 hover:text-emerald-600" title="Verify" @click="verifyEntry(entry)"><CheckBadgeIcon class="h-4 w-4" /></button><button class="rounded-lg p-2 text-slate-500 hover:bg-rose-50 hover:text-rose-600" title="Supersede" @click="supersedeEntry(entry)"><DocumentCheckIcon class="h-4 w-4" /></button></div></td>
              </tr>
              <tr v-if="visibleEntries.length === 0"><td :colspan="permissions.manage ? 8 : 7" class="px-5 py-14 text-center text-sm text-slate-500">{{ isOwnView ? 'No verified service entries are available yet.' : 'No service entries have been encoded.' }}</td></tr>
            </tbody>
          </table>
        </div>
        <details v-if="supersededEntries.length && permissions.manage" class="border-t border-slate-100 px-5 py-4"><summary class="cursor-pointer text-sm font-medium text-slate-600">Superseded history ({{ supersededEntries.length }})</summary><ul class="mt-3 space-y-2 text-sm text-slate-500"><li v-for="entry in supersededEntries" :key="entry.id">{{ formatDate(entry.service_from) }} — {{ entry.position_title }} · {{ entry.remarks }}</li></ul></details>
      </AppCard>

      <div v-else-if="activeTab === 'actions'" class="space-y-4">
        <div class="flex justify-end"><AppButton v-if="permissions.manage" @click="showActionModal = true"><PlusIcon class="h-4 w-4" /> Add HR Action</AppButton></div>
        <AppCard v-for="action in actions" :key="action.id"><div class="flex items-start justify-between gap-4"><div class="flex gap-3"><div class="rounded-xl bg-indigo-50 p-2.5 text-indigo-600"><ClockIcon class="h-5 w-5" /></div><div><div class="flex items-center gap-2"><p class="font-semibold text-slate-900">{{ label(action.action_type) }}</p><AppBadge color="indigo">Office-order action</AppBadge></div><p class="mt-1 text-sm text-slate-600">{{ formatDate(action.effective_from) }} to {{ formatDate(action.effective_to) }}</p><p class="mt-1 text-xs text-slate-500">{{ action.office_order_no || 'No order number' }} · {{ [action.station_place, action.branch_division].filter(Boolean).join(' · ') || 'Station not specified' }}</p><p v-if="action.remarks" class="mt-2 text-sm text-slate-600">{{ action.remarks }}</p></div></div><button v-if="permissions.manage" class="text-sm text-rose-600" @click="router.delete(route('hr.service-records.actions.destroy', action.id), { preserveScroll: true })">Remove</button></div></AppCard>
        <AppCard v-if="actions.length === 0"><p class="py-8 text-center text-sm text-slate-500">No reassignment, detail, designation, or secondment annotations recorded.</p></AppCard>
      </div>

      <AppCard v-else-if="activeTab === 'quality'" title="Policy and data-quality review" subtitle="Warnings identify records that need documentary or chronological review.">
        <div v-if="diagnostics.warnings.length" class="space-y-3"><div v-for="(warning, index) in diagnostics.warnings" :key="`${warning.entry_id}-${index}`" class="flex gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4"><ExclamationTriangleIcon class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" /><div><p class="text-sm font-medium text-amber-900">{{ label(warning.type) }}</p><p class="mt-0.5 text-sm text-amber-800">{{ warning.message }}</p></div></div></div>
        <div v-else class="flex flex-col items-center py-10 text-center"><ShieldCheckIcon class="h-12 w-12 text-emerald-500" /><p class="mt-3 font-semibold text-slate-900">Record ready for certification</p><p class="mt-1 text-sm text-slate-500">All visible entries are verified and no chronological issues were detected.</p></div>
      </AppCard>

      <AppCard v-else title="Certified versions" subtitle="Immutable snapshots created when official Service Records are issued.">
        <div v-if="versions.length" class="divide-y divide-slate-100"><div v-for="version in versions" :key="version.id" class="flex items-center justify-between py-3"><div class="flex items-center gap-3"><DocumentTextIcon class="h-5 w-5 text-indigo-500" /><div><p class="text-sm font-medium text-slate-900">Version {{ version.version }}</p><p class="text-xs text-slate-500">Certified {{ formatDate(version.certified_at || version.created_at) }}</p></div></div><code class="text-[10px] text-slate-400">{{ version.content_hash.slice(0, 16) }}…</code></div></div><p v-else class="py-10 text-center text-sm text-slate-500">No certified Service Record has been issued from this ledger.</p>
      </AppCard>
    </div>

    <AppModal :show="showEntryModal" :title="editingEntry ? 'Edit service entry' : 'Add service entry'" subtitle="All official dates and amounts must match supporting appointment records." size="4xl" @close="showEntryModal = false">
      <form class="space-y-6" @submit.prevent="saveEntry">
        <section><h3 class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Service period and appointment</h3><div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4"><AppInput v-model="entryForm.service_from" type="date" label="Service from" required :error="entryForm.errors.service_from" /><AppInput v-model="entryForm.service_to" type="date" label="Service to" :error="entryForm.errors.service_to" /><AppInput v-model="entryForm.appointment_issued_at" type="date" label="Appointment issued" :error="entryForm.errors.appointment_issued_at" /><AppInput v-model="entryForm.assumed_at" type="date" label="Actual assumption to duty" :error="entryForm.errors.assumed_at" /></div></section>
        <section><div class="grid gap-4 sm:grid-cols-2"><AppInput v-model="entryForm.position_title" label="Official position / designation" required :error="entryForm.errors.position_title" /><AppInput v-model="entryForm.plantilla_item_no" label="Plantilla item number" :error="entryForm.errors.plantilla_item_no" /><AppSelect v-model="entryForm.employment_status" label="Employment status" :error="entryForm.errors.employment_status"><option v-for="option in statusOptions" :key="option[0]" :value="option[0]">{{ option[1] }}</option></AppSelect><AppSelect v-model="entryForm.appointment_nature" label="Nature of appointment" :error="entryForm.errors.appointment_nature"><option v-for="option in natureOptions" :key="option[0]" :value="option[0]">{{ option[1] }}</option></AppSelect></div></section>
        <section><h3 class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Agency and station</h3><div class="grid gap-4 sm:grid-cols-3"><AppInput v-model="entryForm.agency_name" label="Agency" required :error="entryForm.errors.agency_name" /><AppInput v-model="entryForm.station_place" label="Station / place" :error="entryForm.errors.station_place" /><AppInput v-model="entryForm.branch_division" label="Branch / division" :error="entryForm.errors.branch_division" /></div></section>
        <section><h3 class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Historical compensation and CSC action</h3><div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4"><AppInput v-model="entryForm.salary_amount" type="number" step="0.01" label="Salary amount" :error="entryForm.errors.salary_amount" /><AppSelect v-model="entryForm.salary_basis" label="Salary basis" :error="entryForm.errors.salary_basis"><option value="annual">Annual</option><option value="monthly">Monthly</option><option value="daily">Daily</option></AppSelect><AppInput v-model="entryForm.salary_grade" type="number" min="1" max="33" label="Salary grade" :error="entryForm.errors.salary_grade" /><AppInput v-model="entryForm.salary_step" type="number" min="1" max="8" label="Step" :error="entryForm.errors.salary_step" /><AppSelect v-model="entryForm.csc_action" label="CSC action" :error="entryForm.errors.csc_action"><option v-for="value in ['approved','validated','attested','pending','disapproved','invalidated','not_applicable']" :key="value" :value="value">{{ label(value) }}</option></AppSelect><AppInput v-model="entryForm.csc_action_at" type="date" label="CSC action date" :error="entryForm.errors.csc_action_at" /></div></section>
        <section><div class="flex items-start gap-3 rounded-xl border p-4" :class="entryForm.credited_as_government_service ? 'border-emerald-200 bg-emerald-50' : 'border-slate-200 bg-slate-50'"><input v-model="entryForm.credited_as_government_service" type="checkbox" class="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"><div><p class="text-sm font-medium text-slate-900">Credit as government service</p><p class="mt-0.5 text-xs text-slate-600">COS and Job Order engagements cannot be credited. Verification also requires assumption-to-duty and supporting evidence.</p><p v-if="entryForm.errors.credited_as_government_service" class="mt-1 text-xs text-rose-600">{{ entryForm.errors.credited_as_government_service }}</p></div></div></section>
        <section><div class="mb-3 flex items-center justify-between"><h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Leave without pay</h3><AppButton size="sm" variant="secondary" @click="addLwop">Add LWOP Period</AppButton></div><div v-for="(period, index) in entryForm.lwop_periods" :key="index" class="mb-3 grid gap-3 rounded-xl border border-slate-200 p-3 sm:grid-cols-5"><AppInput v-model="period.date_from" type="date" label="From" /><AppInput v-model="period.date_to" type="date" label="To" /><AppInput v-model="period.days" type="number" step="0.001" label="Days" /><AppInput v-model="period.reference_no" label="Reference no." /><div class="flex items-end"><AppButton variant="danger" size="sm" @click="entryForm.lwop_periods.splice(index, 1)">Remove</AppButton></div></div><p v-if="entryForm.lwop_periods.length === 0" class="rounded-xl border border-dashed border-slate-200 py-5 text-center text-xs text-slate-500">No LWOP periods recorded.</p></section>
        <section><h3 class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Separation and remarks</h3><div class="grid gap-4 sm:grid-cols-2"><AppInput v-model="entryForm.separation_date" type="date" label="Separation date" :error="entryForm.errors.separation_date" /><AppInput v-model="entryForm.separation_cause" label="Separation cause" :error="entryForm.errors.separation_cause" /><div class="sm:col-span-2"><AppTextarea v-model="entryForm.remarks" label="Remarks / documentary notes" :error="entryForm.errors.remarks" /></div></div></section>
      </form>
      <template #footer><AppButton variant="secondary" @click="showEntryModal = false">Cancel</AppButton><AppButton :loading="entryForm.processing" @click="saveEntry">Save Draft</AppButton></template>
    </AppModal>

    <AppModal :show="showEvidenceModal" title="Upload supporting evidence" subtitle="Files are base64-encoded in JSON and stored privately in S3." size="lg" @close="showEvidenceModal = false">
      <div class="space-y-4"><AppSelect v-model="evidenceForm.document_type" label="Document type" required :error="evidenceForm.errors.document_type"><option v-for="type in ['appointment','assumption_to_duty','nosa_nosi','prior_service_record','office_order','separation','lwop_certification','other']" :key="type" :value="type">{{ label(type) }}</option></AppSelect><AppInput v-model="evidenceForm.title" label="Document title" required :error="evidenceForm.errors.title" /><div><label class="mb-1 block text-xs font-medium text-slate-600">PDF, JPG, or PNG (maximum 10 MB)</label><input type="file" accept="application/pdf,image/jpeg,image/png" class="block w-full rounded-lg border border-slate-200 p-2 text-sm" @change="readEvidence"><p v-if="evidenceForm.errors.data_uri" class="mt-1 text-xs text-rose-600">{{ evidenceForm.errors.data_uri }}</p></div></div>
      <template #footer><AppButton variant="secondary" @click="showEvidenceModal = false">Cancel</AppButton><AppButton :loading="evidenceForm.processing" :disabled="!selectedFile || !evidenceForm.data_uri" @click="uploadEvidence">Upload Securely</AppButton></template>
    </AppModal>

    <AppModal :show="showActionModal" title="Add HR action annotation" subtitle="Use for reassignment, detail, designation, secondment, or concurrent duties that do not issue a new appointment." size="2xl" @close="showActionModal = false">
      <div class="grid gap-4 sm:grid-cols-2"><AppSelect v-model="actionForm.action_type" label="Action type" required><option v-for="type in ['reassignment','detail','designation','secondment','concurrent','other']" :key="type" :value="type">{{ label(type) }}</option></AppSelect><AppInput v-model="actionForm.office_order_no" label="Office order number" /><AppInput v-model="actionForm.effective_from" type="date" label="Effective from" required :error="actionForm.errors.effective_from" /><AppInput v-model="actionForm.effective_to" type="date" label="Effective to" :error="actionForm.errors.effective_to" /><AppInput v-model="actionForm.station_place" label="Station / place" /><AppInput v-model="actionForm.branch_division" label="Branch / division" /><div class="sm:col-span-2"><AppTextarea v-model="actionForm.remarks" label="Remarks" /></div></div>
      <template #footer><AppButton variant="secondary" @click="showActionModal = false">Cancel</AppButton><AppButton :loading="actionForm.processing" @click="saveAction">Add Annotation</AppButton></template>
    </AppModal>
  </AdminLayout>
</template>
