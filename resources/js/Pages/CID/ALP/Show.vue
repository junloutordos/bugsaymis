<script setup>
import { computed, ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import axios from 'axios'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppButton from '@/Components/AppButton.vue'
import AppCard from '@/Components/AppCard.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppInput from '@/Components/AppInput.vue'
import AppModal from '@/Components/AppModal.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppSelect from '@/Components/AppSelect.vue'
import AppTable from '@/Components/AppTable.vue'
import AppTabs from '@/Components/AppTabs.vue'
import AppTextarea from '@/Components/AppTextarea.vue'
import EmptyState from '@/Components/EmptyState.vue'
import ControlledFormEditor from './Partials/ControlledFormEditor.vue'
import AlpAttendanceGrid from './components/AlpAttendanceGrid.vue'
import { ArrowTopRightOnSquareIcon, CheckCircleIcon, DocumentArrowDownIcon, ExclamationTriangleIcon, MagnifyingGlassIcon, TrashIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  cycle: Object, checklist: Array, yearEndDeficiencies: Array,
  financialSummary: Object, attendanceSummary: Array, abilities: Object,
  hasPin: Boolean, integrationLinks: Array,
})

const tabs = [
  { key: 'overview', label: 'Overview' },
  { key: 'members', label: 'Members & Officers' },
  { key: 'forms', label: 'QMS Forms' },
  { key: 'activities', label: 'Activities' },
  { key: 'attendance', label: 'Attendance' },
  { key: 'finance', label: 'Finance' },
  { key: 'reports', label: 'Reports' },
  { key: 'audit', label: 'Audit Trail' },
]
const activeTab = ref('overview')
const label = (value) => String(value || '').replaceAll('_', ' ').replace(/\b\w/g, c => c.toUpperCase())
const money = (value) => Number(value || 0).toLocaleString('en-PH', { style: 'currency', currency: 'PHP' })
const date = (value) => value ? new Date(`${String(value).slice(0, 10)}T00:00:00`).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' }) : '—'
const statusColor = (status) => ({ accredited: 'green', approved: 'green', completed: 'green', certified: 'green', received: 'green', submitted: 'amber', pending: 'amber', pending_coordinator: 'amber', reviewed: 'blue', recommended: 'indigo', returned: 'red', declined: 'red' }[status] || 'slate')
const postAction = (url, data = {}) => router.post(url, data, { preserveScroll: true })
const jsonClone = (value) => JSON.parse(JSON.stringify(value ?? {}))

const programForm = useForm({ name: props.cycle.program.name, nature: props.cycle.program.nature || '', mission: props.cycle.program.mission || '', objectives: props.cycle.program.objectives?.length ? props.cycle.program.objectives : [''] })
const saveProgram = () => programForm.put(route('alp.program.update', props.cycle.id), { preserveScroll: true })

const transitionForm = useForm({ action: '', remarks: '', pin: '' })
const transition = (action) => { transitionForm.action = action; transitionForm.post(route('alp.cycles.transition', props.cycle.id), { preserveScroll: true }) }

const search = ref('')
const searchResults = ref([])
const selectedStudents = ref([])
const searching = ref(false)
const findStudents = async () => {
  if (search.value.trim().length < 2) return
  searching.value = true
  try { searchResults.value = (await axios.get(route('alp.students.search', props.cycle.id), { params: { search: search.value } })).data } finally { searching.value = false }
}
const addMembers = () => postAction(route('alp.members.store', props.cycle.id), { student_ids: selectedStudents.value })
const removeMember = (member) => confirm(`Remove ${member.student?.full_name || 'this member'}?`) && router.delete(route('alp.members.destroy', [props.cycle.id, member.id]), { preserveScroll: true })
const updateConsent = async (member, event) => {
  const file = event.target.files?.[0]
  let file_base64 = null
  if (file) file_base64 = await new Promise((resolve, reject) => { const reader = new FileReader(); reader.onload = () => resolve(reader.result); reader.onerror = reject; reader.readAsDataURL(file) })
  router.put(route('alp.members.consent', [props.cycle.id, member.id]), { consent_status: file ? 'received' : member.consent_status, file_base64 }, { preserveScroll: true })
}
const setConsent = (member, status) => router.put(route('alp.members.consent', [props.cycle.id, member.id]), { consent_status: status }, { preserveScroll: true })
const setAccountability = (member) => { const accountability = window.prompt('Outstanding ALP accountability (leave blank to clear):', member.accountability || ''); if (accountability !== null) router.put(route('alp.members.accountability', [props.cycle.id, member.id]), { accountability }, { preserveScroll: true }) }

const officerForm = useForm({ membership_id: '', position: '', sort_order: 0 })
const saveOfficer = () => officerForm.post(route('alp.officers.store', props.cycle.id), { preserveScroll: true, onSuccess: () => officerForm.reset() })
const certify = (officer, status) => postAction(route('alp.officers.certify', [props.cycle.id, officer.id]), { status, remarks: status === 'returned' ? window.prompt('Return remarks:') : null, pin: transitionForm.pin })

const documentDrafts = ref(Object.fromEntries(props.cycle.documents.map(doc => [doc.id, jsonClone(doc.content)])))
const saveDocument = (doc, status) => router.put(route('alp.documents.update', [props.cycle.id, doc.id]), { content: documentDrafts.value[doc.id], status }, { preserveScroll: true })

const activityForm = useForm({ title: '', activity_type: 'regular', start_date: '', start_time: '', end_date: '', end_time: '', learning_outcomes: '', target_participants: '', venue: '', is_off_campus: false, budget_amount: '', resources_needed: '', risk_level: 'low', risk_data: { hazards: [], controls: [], emergency_plan: '', responsible_person: '' }, consent_required: true })
const saveActivity = () => activityForm.post(route('alp.activities.store', props.cycle.id), { preserveScroll: true, onSuccess: () => activityForm.reset() })
const activityAction = (activity, action) => postAction(route('alp.activities.action', [props.cycle.id, activity.id]), { action, remarks: action === 'return' || action === 'complete' ? window.prompt('Remarks / completion highlights:') : null, pin: transitionForm.pin })

const financeForm = useForm({ activity_id: '', transaction_date: '', entry_type: 'expense', category: '', description: '', amount: '', source: '', receipt_base64: null })
const setReceipt = async (event) => { const file = event.target.files?.[0]; if (file) financeForm.receipt_base64 = await new Promise((resolve, reject) => { const reader = new FileReader(); reader.onload = () => resolve(reader.result); reader.onerror = reject; reader.readAsDataURL(file) }) }
const saveFinance = () => financeForm.post(route('alp.finances.store', props.cycle.id), { preserveScroll: true, onSuccess: () => financeForm.reset() })

const reportForm = useForm({ report_type: 'accomplishment', period: 'Year-end', data: { narrative: '', highlights: [], challenges: [], recommendations: [] }, status: 'draft' })
const saveReport = (status) => { reportForm.status = status; reportForm.post(route('alp.reports.store', props.cycle.id), { preserveScroll: true }) }
const activeMembers = computed(() => props.cycle.memberships.filter(item => item.status === 'active'))
const workflowActions = computed(() => {
  const status = props.cycle.status
  const actions = []
  if (props.abilities.manage && ['draft', 'returned'].includes(status)) actions.push(['submit', 'Submit package'])
  if (props.abilities.coordinate && status === 'pending_coordinator') actions.push(['review', 'Begin review'])
  if (props.abilities.recommend && status === 'reviewed') actions.push(['recommend', 'Recommend'])
  if (props.abilities.approve && status === 'recommended') actions.push(['approve', 'Accredit'])
  if ((props.abilities.coordinate || props.abilities.recommend || props.abilities.approve) && ['pending_coordinator', 'reviewed', 'recommended'].includes(status)) actions.push(['return', 'Return'])
  if (props.abilities.manage && status === 'accredited') actions.push(['close', 'Close year'])
  return actions
})

const financeStats = computed(() => [
  ['Opening', props.financialSummary.opening],
  ['Income', props.financialSummary.income],
  ['Expenses', props.financialSummary.expenses],
  ['Turnover', props.financialSummary.turnover],
  ['Available', props.financialSummary.available],
])

const TH = 'px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap'
const TD = 'px-4 py-3 text-sm text-slate-600 align-top'
</script>

<template>
  <Head :title="`${cycle.program.code} · ALP`" />
  <AdminLayout :title="cycle.program.name">
    <div class="space-y-5">
      <AppPageHeader
        hero
        :title="cycle.program.name"
        :subtitle="`${cycle.school_year.name} · Adviser: ${cycle.adviser?.name || 'Unassigned'} · Coordinator: ${cycle.coordinator?.name || 'Unassigned'}`"
        :breadcrumb="[{ label: 'Alternative Learning Program', href: route('alp.index') }, { label: cycle.program.code }]"
      >
        <template #actions>
          <AppBadge :color="statusColor(cycle.status)">{{ label(cycle.status) }}</AppBadge>
          <AppButton as="a" :href="route('alp.cycles.package', cycle.id)" target="_blank" variant="secondary">
            <DocumentArrowDownIcon class="h-4 w-4" />
            QMS package
          </AppButton>
          <AppButton
            v-for="action in workflowActions"
            :key="action[0]"
            :loading="transitionForm.processing"
            @click="transition(action[0])"
          >
            {{ action[1] }}
          </AppButton>
        </template>
      </AppPageHeader>

      <div v-if="hasPin && (abilities.approve || abilities.certify)" class="rounded-xl border border-warning-200 bg-warning-50 p-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
          <div class="min-w-0 flex-1">
            <p class="text-sm font-medium text-warning-800">Approval signature</p>
            <p class="text-xs text-warning-700">Enter your signature PIN before approving or certifying a record.</p>
          </div>
          <AppInput v-model="transitionForm.pin" type="password" placeholder="Signature PIN" class="w-full sm:w-56" />
        </div>
      </div>

      <AppTabs v-model="activeTab" :tabs="tabs">
      <template v-if="activeTab === 'overview'">
        <div class="space-y-5">
        <div class="grid gap-5 lg:grid-cols-3">
          <AppCard title="Program profile" class="lg:col-span-2">
            <form class="mt-4 space-y-4" @submit.prevent="saveProgram">
              <AppInput v-model="programForm.name" label="Program name" :disabled="!abilities.manage" />
              <AppTextarea v-model="programForm.nature" label="Nature and description" :disabled="!abilities.manage" :rows="3" />
              <AppTextarea v-model="programForm.mission" label="Mission" :disabled="!abilities.manage" :rows="3" />
              <div class="space-y-2">
                <p class="text-xs font-medium text-slate-600">Objectives</p>
                <div v-for="(_, index) in programForm.objectives" :key="index" class="flex gap-2">
                  <AppInput v-model="programForm.objectives[index]" :disabled="!abilities.manage" :placeholder="`Objective ${index + 1}`" class="min-w-0 flex-1" />
                  <AppIconButton v-if="abilities.manage && programForm.objectives.length > 1" label="Remove objective" variant="danger" @click="programForm.objectives.splice(index, 1)">
                    <TrashIcon class="h-4 w-4" />
                  </AppIconButton>
                </div>
              </div>
              <div v-if="abilities.manage" class="flex flex-wrap justify-between gap-2">
                <AppButton type="button" variant="ghost" size="sm" @click="programForm.objectives.push('')">Add objective</AppButton>
                <AppButton type="submit" :loading="programForm.processing">Save profile</AppButton>
              </div>
            </form>
          </AppCard>
          <AppCard title="Accreditation checklist">
            <div class="space-y-3">
              <div v-for="item in checklist" :key="item.key" class="flex gap-2.5 text-sm">
                <CheckCircleIcon v-if="item.complete" class="h-5 w-5 flex-none text-success-500" />
                <ExclamationTriangleIcon v-else class="h-5 w-5 flex-none text-warning-500" />
                <div><p class="font-medium text-slate-800">{{ item.label }}</p><p v-if="item.detail" class="text-xs text-slate-500">{{ item.detail }}</p></div>
              </div>
            </div>
          </AppCard>
        </div>
        <div v-if="yearEndDeficiencies.length" class="rounded-xl border border-warning-200 bg-warning-50 p-5">
          <h2 class="font-heading text-sm font-semibold text-warning-900">Year-end requirements still open</h2>
          <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-warning-800"><li v-for="item in yearEndDeficiencies" :key="item">{{ item }}</li></ul>
        </div>
        <AppCard title="Connected services">
          <div class="flex flex-wrap gap-2">
            <AppButton v-for="item in integrationLinks" :key="item.label" as="a" :href="item.href" variant="secondary" size="sm">
              {{ item.label }} <ArrowTopRightOnSquareIcon class="h-4 w-4" />
            </AppButton>
          </div>
        </AppCard>
        </div>
      </template>

      <template v-else-if="activeTab === 'members'">
        <div class="space-y-5">
        <AppCard v-if="abilities.manage && ['draft', 'returned'].includes(cycle.status)" title="Add enrolled scholars" subtitle="Search the current school-year enrollment by name or PSHS ID.">
          <div class="flex gap-2">
            <AppInput v-model="search" placeholder="Search name or PSHS ID" class="min-w-0 flex-1" @keyup.enter="findStudents" />
            <AppButton :loading="searching" aria-label="Search scholars" @click="findStudents"><MagnifyingGlassIcon class="h-5 w-5" /></AppButton>
          </div>
          <div v-if="searchResults.length" class="mt-3 max-h-56 overflow-auto rounded-xl ring-1 ring-slate-200/70">
            <label v-for="student in searchResults" :key="student.student_id" class="flex items-center gap-3 border-b border-slate-100 px-3 py-2.5 text-sm last:border-0 hover:bg-slate-50">
              <input v-model="selectedStudents" type="checkbox" :value="student.student_id" :disabled="!!student.assigned_program" class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
              <span class="min-w-0 flex-1 text-slate-700">{{ student.name }} · Grade {{ student.grade_level }} {{ student.section }}</span>
              <span v-if="student.assigned_program" class="text-xs text-danger-600">Already in {{ student.assigned_program }}</span>
            </label>
          </div>
          <AppButton v-if="selectedStudents.length" class="mt-3" @click="addMembers">Add {{ selectedStudents.length }} member(s)</AppButton>
        </AppCard>

        <AppCard :padded="false" :title="`Membership roster (${activeMembers.length})`">
          <AppTable :is-empty="cycle.memberships.length === 0" :skeleton-cols="5" :card="false">
            <template #head><tr><th :class="TH">Scholar</th><th :class="TH">Grade/Section</th><th :class="TH">Consent</th><th :class="TH">Accountability</th><th :class="TH">Actions</th></tr></template>
            <tr v-for="member in cycle.memberships" :key="member.id" class="hover:bg-indigo-50/40">
              <td :class="TD"><p class="font-medium text-slate-800">{{ member.student?.full_name }}</p></td>
              <td :class="TD">Grade {{ member.enrollment?.grade_level }} {{ member.enrollment?.section?.sectionname }}</td>
              <td :class="TD">
                <AppBadge :color="statusColor(member.consent_status)">{{ label(member.consent_status) }}</AppBadge>
                <div v-if="abilities.manage" class="mt-2 flex flex-wrap gap-1.5">
                  <select :value="member.consent_status" class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500/25" @change="setConsent(member, $event.target.value)"><option>pending</option><option>received</option><option>declined</option></select>
                  <label class="cursor-pointer rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50">Upload<input class="hidden" type="file" accept="application/pdf,image/jpeg,image/png" @change="updateConsent(member, $event)" /></label>
                  <a v-if="member.consent_file_id" :href="route('alp.files.show', [cycle.id, member.consent_file_id])" target="_blank" rel="noopener noreferrer" class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50">View</a>
                </div>
              </td>
              <td :class="TD"><span :class="member.accountability ? 'text-danger-600' : 'text-success-600'">{{ member.accountability || 'Clear' }}</span></td>
              <td :class="TD"><div class="flex flex-wrap gap-1.5"><AppButton v-if="abilities.manage" size="sm" variant="ghost" @click="setAccountability(member)">Accountability</AppButton><AppButton v-if="member.completed_at" as="a" :href="route('alp.members.certificate', [cycle.id, member.id])" target="_blank" size="sm" variant="secondary">Certificate</AppButton><AppIconButton v-if="abilities.manage && ['draft', 'returned'].includes(cycle.status)" label="Remove member" variant="danger" @click="removeMember(member)"><TrashIcon class="h-4 w-4" /></AppIconButton></div></td>
            </tr>
            <template #mobileCard>
              <div v-for="member in cycle.memberships" :key="member.id" class="space-y-2 p-4">
                <div class="flex items-start justify-between gap-2"><div><p class="text-sm font-semibold text-slate-800">{{ member.student?.full_name }}</p><p class="text-xs text-slate-500">Grade {{ member.enrollment?.grade_level }} {{ member.enrollment?.section?.sectionname }}</p></div><AppBadge :color="statusColor(member.consent_status)">{{ label(member.consent_status) }}</AppBadge></div>
                <p class="text-xs" :class="member.accountability ? 'text-danger-600' : 'text-success-600'">Accountability: {{ member.accountability || 'Clear' }}</p>
                <div class="flex flex-wrap gap-1.5 pt-1"><AppButton v-if="abilities.manage" size="sm" variant="ghost" @click="setAccountability(member)">Accountability</AppButton><AppButton v-if="member.completed_at" as="a" :href="route('alp.members.certificate', [cycle.id, member.id])" target="_blank" size="sm" variant="secondary">Certificate</AppButton><AppButton v-if="abilities.manage && ['draft', 'returned'].includes(cycle.status)" size="sm" variant="danger" @click="removeMember(member)">Remove</AppButton></div>
              </div>
            </template>
            <template #empty><EmptyState title="No members assigned" /></template>
          </AppTable>
        </AppCard>

        <AppCard title="Officers and Registrar certification">
          <form v-if="abilities.manage && ['draft', 'returned'].includes(cycle.status)" class="grid gap-3 sm:grid-cols-4" @submit.prevent="saveOfficer">
            <AppSelect v-model="officerForm.membership_id" label="Member" required placeholder="Select member" class="sm:col-span-2"><option v-for="member in activeMembers" :key="member.id" :value="member.id">{{ member.student?.full_name }}</option></AppSelect>
            <AppInput v-model="officerForm.position" label="Position" required />
            <AppButton type="submit" :loading="officerForm.processing" class="self-end">Save officer</AppButton>
          </form>
          <div v-if="cycle.officers.length" class="mt-4 divide-y divide-slate-100">
            <div v-for="officer in cycle.officers" :key="officer.id" class="flex flex-wrap items-center gap-3 py-3 text-sm">
              <div class="min-w-0 flex-1"><p class="font-medium text-slate-800">{{ officer.membership?.student?.full_name }} — {{ officer.position }}</p><p class="text-xs text-slate-500">{{ officer.certification_remarks || 'Awaiting Registrar review' }}</p></div>
              <AppBadge :color="statusColor(officer.registrar_status)">{{ label(officer.registrar_status) }}</AppBadge>
              <template v-if="abilities.certify"><AppButton size="sm" variant="success" @click="certify(officer, 'certified')">Certify</AppButton><AppButton size="sm" variant="danger" @click="certify(officer, 'returned')">Return</AppButton></template>
            </div>
          </div>
          <EmptyState v-else title="No officers assigned" />
        </AppCard>
        </div>
      </template>

      <template v-else-if="activeTab === 'forms'">
        <div v-if="cycle.documents.length" class="grid gap-5 xl:grid-cols-2">
          <AppCard v-for="doc in cycle.documents" :key="doc.id">
            <div class="flex items-start justify-between gap-3 border-b border-slate-100 pb-4">
              <div><p class="text-xs font-semibold uppercase tracking-wide text-indigo-600">{{ doc.form_code }}</p><h2 class="font-heading text-sm font-semibold text-slate-900">{{ label(doc.document_type) }}</h2><p class="text-xs text-slate-500">Version {{ doc.version_no }} · Revision {{ doc.revision_no }}</p></div>
              <AppBadge :color="statusColor(doc.status)">{{ label(doc.status) }}</AppBadge>
            </div>
            <div class="mt-4"><ControlledFormEditor v-model="documentDrafts[doc.id]" :disabled="!abilities.manage || !['draft', 'returned'].includes(cycle.status)" /></div>
            <div class="mt-4 flex flex-wrap gap-2"><AppButton as="a" :href="route('alp.documents.pdf', [cycle.id, doc.id])" target="_blank" variant="secondary" size="sm">Preview PDF</AppButton><template v-if="abilities.manage && ['draft', 'returned'].includes(cycle.status)"><AppButton variant="secondary" size="sm" @click="saveDocument(doc, 'draft')">Save draft</AppButton><AppButton size="sm" @click="saveDocument(doc, 'submitted')">Mark submitted</AppButton></template></div>
          </AppCard>
        </div>
        <AppCard v-else><EmptyState title="No QMS forms generated" /></AppCard>
      </template>

      <template v-else-if="activeTab === 'activities'">
        <div class="space-y-5">
        <AppCard v-if="abilities.manage" title="New activity plan">
          <form class="grid gap-3 md:grid-cols-2" @submit.prevent="saveActivity">
            <AppInput v-model="activityForm.title" label="Activity title" required class="md:col-span-2" />
            <AppSelect v-model="activityForm.activity_type" label="Activity type" :show-blank="false"><option value="regular">Regular</option><option value="major">Major</option><option value="community_service">Community service</option></AppSelect>
            <AppInput v-model="activityForm.venue" label="Venue" required />
            <AppInput v-model="activityForm.start_date" label="Start date" required type="date" />
            <AppInput v-model="activityForm.end_date" label="End date" required type="date" />
            <AppTextarea v-model="activityForm.learning_outcomes" label="Learning outcomes" required :rows="3" />
            <AppTextarea v-model="activityForm.target_participants" label="Target participants" required :rows="3" />
            <AppSelect v-model="activityForm.risk_level" label="Risk level" :show-blank="false"><option value="low">Low</option><option value="medium">Medium</option><option value="high">High</option></AppSelect>
            <AppInput v-model="activityForm.risk_data.responsible_person" label="Risk owner / responsible person" required />
            <AppTextarea v-model="activityForm.risk_data.emergency_plan" label="Emergency and control plan" required :rows="2" class="md:col-span-2" />
            <label class="flex items-center gap-2 text-sm text-slate-700"><input v-model="activityForm.consent_required" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" /> Parent consent required</label>
            <AppButton type="submit" :loading="activityForm.processing" class="justify-self-start md:justify-self-end">Create activity</AppButton>
          </form>
        </AppCard>
        <div v-if="cycle.activities.length" class="grid gap-4 lg:grid-cols-2">
          <AppCard v-for="activity in cycle.activities" :key="activity.id">
            <div class="flex justify-between gap-3"><div><h2 class="font-heading text-sm font-semibold text-slate-800">{{ activity.title }}</h2><p class="text-sm text-slate-500">{{ label(activity.activity_type) }} · {{ date(activity.start_date) }} to {{ date(activity.end_date) }}</p></div><AppBadge :color="statusColor(activity.status)">{{ label(activity.status) }}</AppBadge></div>
            <p class="mt-3 text-sm text-slate-700">{{ activity.learning_outcomes }}</p><p class="mt-2 text-xs text-slate-500">{{ activity.venue }} · Risk: {{ label(activity.risk_level) }}<span v-if="activity.ams_activity_id"> · Synced to AMS #{{ activity.ams_activity_id }}</span></p>
            <div class="mt-4 flex flex-wrap gap-2"><AppButton v-if="abilities.manage && ['draft', 'returned'].includes(activity.status)" size="sm" variant="secondary" @click="activityAction(activity, 'submit')">Submit</AppButton><template v-if="abilities.activityApprove && activity.status === 'submitted'"><AppButton size="sm" variant="success" @click="activityAction(activity, 'approve')">Approve</AppButton><AppButton size="sm" variant="danger" @click="activityAction(activity, 'return')">Return</AppButton></template><AppButton v-if="abilities.manage && activity.status === 'approved'" size="sm" variant="success" @click="activityAction(activity, 'complete')">Complete</AppButton></div>
          </AppCard>
        </div>
        <AppCard v-else><EmptyState title="No activities planned" /></AppCard>
        </div>
      </template>

      <template v-else-if="activeTab === 'attendance'">
        <div class="space-y-5">
        <AppCard>
          <AlpAttendanceGrid :cycle-id="cycle.id" :can-edit="abilities.manage" />
        </AppCard>
        <AppCard :padded="false" title="Attendance summary">
          <AppTable :is-empty="attendanceSummary.length === 0" :skeleton-cols="5" :card="false">
            <template #head><tr><th :class="TH">Member</th><th :class="TH">Sessions</th><th :class="TH">Present</th><th :class="TH">Absent</th><th :class="TH">Tardy/Cutting</th></tr></template>
            <tr v-for="row in attendanceSummary" :key="row.membership_id" class="hover:bg-indigo-50/40"><td :class="TD" class="font-medium text-slate-800">{{ row.name }}</td><td :class="TD">{{ row.sessions }}</td><td :class="TD">{{ row.present }}</td><td :class="TD">{{ row.absent }}</td><td :class="TD">{{ row.tardy }} / {{ row.cutting }}</td></tr>
            <template #empty><EmptyState title="No attendance data" /></template>
          </AppTable>
        </AppCard>
        </div>
      </template>

      <template v-else-if="activeTab === 'finance'">
        <div class="space-y-5">
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5"><AppCard v-for="item in financeStats" :key="item[0]"><p class="text-xs font-medium text-slate-500">{{ item[0] }}</p><p class="mt-1 font-heading text-xl font-semibold text-slate-900">{{ money(item[1]) }}</p></AppCard></div>
        <AppCard v-if="abilities.manage" title="Record transaction">
          <form class="grid gap-3 md:grid-cols-3" @submit.prevent="saveFinance"><AppInput v-model="financeForm.transaction_date" label="Transaction date" required type="date" /><AppSelect v-model="financeForm.entry_type" label="Entry type" :show-blank="false"><option value="opening_balance">Opening balance</option><option value="income">Income</option><option value="expense">Expense</option><option value="turnover">Turnover</option></AppSelect><AppInput v-model="financeForm.amount" label="Amount" required type="number" step="0.01" min="0" /><AppInput v-model="financeForm.description" label="Description" required class="md:col-span-2" /><div><label class="mb-1 block text-xs font-medium text-slate-600">Receipt</label><input type="file" accept="application/pdf,image/jpeg,image/png" class="block w-full rounded-lg border border-slate-200 bg-white text-sm text-slate-600 file:mr-3 file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-xs file:font-medium file:text-slate-700 hover:file:bg-slate-200" @change="setReceipt" /></div><AppButton type="submit" :loading="financeForm.processing" class="md:col-start-3 md:justify-self-end">Post entry</AppButton></form>
        </AppCard>
        <AppCard :padded="false" title="Financial entries">
          <AppTable :is-empty="cycle.financial_entries.length === 0" :skeleton-cols="5" :card="false">
            <template #head><tr><th :class="TH">Date</th><th :class="TH">Type</th><th :class="TH">Description</th><th :class="`${TH} text-right`">Amount</th><th class="w-12 px-4 py-3"><span class="sr-only">Actions</span></th></tr></template>
            <tr v-for="entry in cycle.financial_entries" :key="entry.id" class="hover:bg-indigo-50/40"><td :class="TD">{{ date(entry.transaction_date) }}</td><td :class="TD"><AppBadge :color="statusColor(entry.entry_type)">{{ label(entry.entry_type) }}</AppBadge></td><td :class="TD">{{ entry.description }}</td><td :class="`${TD} text-right font-medium text-slate-800`">{{ money(entry.amount) }}</td><td class="px-4 py-3"><AppIconButton v-if="abilities.manage && entry.status === 'posted'" label="Delete entry" variant="danger" @click="router.delete(route('alp.finances.destroy', [cycle.id, entry.id]), { preserveScroll: true })"><TrashIcon class="h-4 w-4" /></AppIconButton></td></tr>
            <template #empty><EmptyState title="No financial entries" /></template>
          </AppTable>
        </AppCard>
        </div>
      </template>

      <template v-else-if="activeTab === 'reports'">
        <div class="space-y-5">
        <AppCard v-if="abilities.manage" title="Prepare report">
          <div class="grid gap-3 md:grid-cols-2"><AppSelect v-model="reportForm.report_type" label="Report type" :show-blank="false"><option value="accomplishment">Accomplishment</option><option value="attendance_summary">Attendance summary</option><option value="financial">Financial</option><option value="coordinator">Coordinator</option><option value="adviser_evaluation">Adviser evaluation</option></AppSelect><AppInput v-model="reportForm.period" label="Period" /><AppTextarea v-model="reportForm.data.narrative" label="Narrative / summary" :rows="6" class="md:col-span-2" /></div><div class="mt-3 flex gap-2"><AppButton variant="secondary" :loading="reportForm.processing" @click="saveReport('draft')">Save draft</AppButton><AppButton :loading="reportForm.processing" @click="saveReport('submitted')">Submit report</AppButton></div>
        </AppCard>
        <div v-if="cycle.reports.length" class="grid gap-4 lg:grid-cols-2"><AppCard v-for="report in cycle.reports" :key="report.id"><div class="flex justify-between gap-3"><div><h2 class="font-heading text-sm font-semibold text-slate-800">{{ label(report.report_type) }}</h2><p class="text-sm text-slate-500">{{ report.period }}</p></div><AppBadge :color="statusColor(report.status)">{{ label(report.status) }}</AppBadge></div><div class="mt-4 flex gap-2"><AppButton as="a" :href="route('alp.reports.pdf', [cycle.id, report.id])" target="_blank" size="sm" variant="secondary">PDF</AppButton><AppButton v-if="(abilities.coordinate || abilities.approve) && report.status === 'submitted'" size="sm" variant="success" @click="postAction(route('alp.reports.approve', [cycle.id, report.id]), { pin: transitionForm.pin })">Approve</AppButton></div></AppCard></div>
        <AppCard v-else><EmptyState title="No reports prepared" /></AppCard>
        </div>
      </template>

      <template v-else>
        <AppCard :padded="false" title="Immutable activity trail">
          <AppTable :is-empty="cycle.activity_logs.length === 0" :skeleton-cols="3" :card="false">
            <template #head><tr><th :class="TH">Date and time</th><th :class="TH">Action</th><th :class="TH">Actor</th></tr></template>
            <tr v-for="log in cycle.activity_logs" :key="log.id" class="hover:bg-indigo-50/40"><td :class="TD">{{ new Date(log.created_at).toLocaleString('en-PH') }}</td><td :class="TD" class="font-medium text-slate-800">{{ label(log.action) }}</td><td :class="TD">{{ log.actor?.name || 'System' }}</td></tr>
            <template #empty><EmptyState title="No activity recorded" /></template>
          </AppTable>
        </AppCard>
      </template>
      </AppTabs>

      <AppModal
        :show="!!remarkCell"
        title="Attendance Remark"
        :subtitle="remarkCell ? `${remarkCell.member.student?.full_name} · ${date(remarkCell.session.session_date)}` : ''"
        size="md"
        @close="closeRemarks"
      >
        <label for="alp-attendance-remark" class="block text-sm font-medium text-slate-700">
          Remarks <span class="font-normal text-slate-400">(optional)</span>
        </label>
        <textarea
          id="alp-attendance-remark"
          v-model="remarkDraft"
          rows="4"
          maxlength="1000"
          placeholder="Add relevant context for this absence, tardiness, or excuse…"
          class="mt-2 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
        />
        <template #footer>
          <AppButton variant="secondary" @click="closeRemarks">Cancel</AppButton>
          <AppButton @click="applyRemark">Apply Remark</AppButton>
        </template>
      </AppModal>
    </div>
  </AdminLayout>
</template>
