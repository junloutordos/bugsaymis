<script setup>
import { computed, ref } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import axios from 'axios'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import ControlledFormEditor from './Partials/ControlledFormEditor.vue'
import { ArrowLeftIcon, ArrowTopRightOnSquareIcon, CheckCircleIcon, DocumentArrowDownIcon, ExclamationTriangleIcon, MagnifyingGlassIcon, PlusIcon, TrashIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  cycle: Object, checklist: Array, yearEndDeficiencies: Array,
  financialSummary: Object, attendanceSummary: Array, abilities: Object,
  hasPin: Boolean, integrationLinks: Array,
})

const tabs = ['Overview', 'Members & Officers', 'QMS Forms', 'Activities', 'Attendance', 'Finance', 'Reports', 'Audit Trail']
const activeTab = ref('Overview')
const label = (value) => String(value || '').replaceAll('_', ' ').replace(/\b\w/g, c => c.toUpperCase())
const money = (value) => Number(value || 0).toLocaleString('en-PH', { style: 'currency', currency: 'PHP' })
const date = (value) => value ? new Date(`${String(value).slice(0, 10)}T00:00:00`).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' }) : '—'
const statusClass = (status) => ({ accredited: 'bg-emerald-100 text-emerald-700', approved: 'bg-emerald-100 text-emerald-700', completed: 'bg-teal-100 text-teal-700', submitted: 'bg-amber-100 text-amber-700', pending_coordinator: 'bg-amber-100 text-amber-700', reviewed: 'bg-blue-100 text-blue-700', recommended: 'bg-indigo-100 text-indigo-700', returned: 'bg-rose-100 text-rose-700', declined: 'bg-rose-100 text-rose-700', certified: 'bg-emerald-100 text-emerald-700' }[status] || 'bg-slate-100 text-slate-600')
const postAction = (url, data = {}) => router.post(url, data, { preserveScroll: true })

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

const documentDrafts = ref(Object.fromEntries(props.cycle.documents.map(doc => [doc.id, structuredClone(doc.content || {})])))
const saveDocument = (doc, status) => router.put(route('alp.documents.update', [props.cycle.id, doc.id]), { content: documentDrafts.value[doc.id], status }, { preserveScroll: true })

const activityForm = useForm({ title: '', activity_type: 'regular', start_date: '', start_time: '', end_date: '', end_time: '', learning_outcomes: '', target_participants: '', venue: '', is_off_campus: false, budget_amount: '', resources_needed: '', risk_level: 'low', risk_data: { hazards: [], controls: [], emergency_plan: '', responsible_person: '' }, consent_required: true })
const saveActivity = () => activityForm.post(route('alp.activities.store', props.cycle.id), { preserveScroll: true, onSuccess: () => activityForm.reset() })
const activityAction = (activity, action) => postAction(route('alp.activities.action', [props.cycle.id, activity.id]), { action, remarks: action === 'return' || action === 'complete' ? window.prompt('Remarks / completion highlights:') : null, pin: transitionForm.pin })

const sessionForm = useForm({ activity_id: '', session_date: '', start_time: '', end_time: '', topic: '', venue: '' })
const saveSession = () => sessionForm.post(route('alp.sessions.store', props.cycle.id), { preserveScroll: true, onSuccess: () => sessionForm.reset() })
const attendanceDrafts = ref(Object.fromEntries(props.cycle.sessions.map(session => [session.id, Object.fromEntries(session.attendance.map(record => [record.alp_membership_id, { membership_id: record.alp_membership_id, status: record.status, remarks: record.remarks || '' }]))])))
const saveAttendance = (session) => postAction(route('alp.attendance.save', [props.cycle.id, session.id]), { records: Object.values(attendanceDrafts.value[session.id] || {}) })

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
</script>

<template>
  <Head :title="`${cycle.program.code} · ALP`" />
  <AdminLayout :title="cycle.program.name">
    <div class="space-y-5">
      <div class="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm lg:flex-row lg:items-center lg:justify-between">
        <div>
          <Link :href="route('alp.index')" class="mb-2 inline-flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-800"><ArrowLeftIcon class="h-4 w-4" /> All programs</Link>
          <div class="flex flex-wrap items-center gap-2"><h1 class="text-2xl font-bold text-slate-900">{{ cycle.program.name }}</h1><span class="rounded-full px-3 py-1 text-xs font-semibold" :class="statusClass(cycle.status)">{{ label(cycle.status) }}</span></div>
          <p class="mt-1 text-sm text-slate-500">{{ cycle.school_year.name }} · Adviser: {{ cycle.adviser?.name || 'Unassigned' }} · Coordinator: {{ cycle.coordinator?.name || 'Unassigned' }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
          <a :href="route('alp.cycles.package', cycle.id)" target="_blank" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"><DocumentArrowDownIcon class="h-4 w-4" /> QMS package</a>
          <button v-for="action in workflowActions" :key="action[0]" class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-700" @click="transition(action[0])">{{ action[1] }}</button>
        </div>
      </div>

      <div v-if="hasPin && (abilities.approve || abilities.certify)" class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">Approval PIN: <input v-model="transitionForm.pin" type="password" class="ml-2 rounded border border-amber-300 bg-white px-2 py-1" placeholder="Required for signing" /></div>

      <div class="overflow-x-auto border-b border-slate-200"><nav class="flex min-w-max gap-1"><button v-for="tab in tabs" :key="tab" class="border-b-2 px-4 py-3 text-sm font-medium" :class="activeTab === tab ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-slate-500 hover:text-slate-800'" @click="activeTab = tab">{{ tab }}</button></nav></div>

      <template v-if="activeTab === 'Overview'">
        <div class="grid gap-5 lg:grid-cols-3">
          <section class="rounded-xl border border-slate-200 bg-white p-5 lg:col-span-2">
            <h2 class="font-semibold text-slate-900">Program profile</h2>
            <form class="mt-4 space-y-4" @submit.prevent="saveProgram">
              <input v-model="programForm.name" :disabled="!abilities.manage" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Program name" />
              <textarea v-model="programForm.nature" :disabled="!abilities.manage" rows="3" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Nature and description" />
              <textarea v-model="programForm.mission" :disabled="!abilities.manage" rows="3" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Mission" />
              <div v-for="(_, index) in programForm.objectives" :key="index" class="flex gap-2"><input v-model="programForm.objectives[index]" :disabled="!abilities.manage" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" :placeholder="`Objective ${index + 1}`" /><button v-if="abilities.manage && programForm.objectives.length > 1" type="button" class="text-rose-600" @click="programForm.objectives.splice(index, 1)">×</button></div>
              <div v-if="abilities.manage" class="flex gap-2"><button type="button" class="text-sm font-medium text-indigo-600" @click="programForm.objectives.push('')">+ Add objective</button><button class="ml-auto rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white">Save profile</button></div>
            </form>
          </section>
          <section class="rounded-xl border border-slate-200 bg-white p-5"><h2 class="font-semibold text-slate-900">Accreditation checklist</h2><div class="mt-4 space-y-3"><div v-for="item in checklist" :key="item.key" class="flex gap-2 text-sm"><CheckCircleIcon v-if="item.complete" class="h-5 w-5 flex-none text-emerald-500" /><ExclamationTriangleIcon v-else class="h-5 w-5 flex-none text-amber-500" /><div><p class="font-medium text-slate-800">{{ item.label }}</p><p v-if="item.detail" class="text-xs text-slate-500">{{ item.detail }}</p></div></div></div></section>
        </div>
        <section v-if="yearEndDeficiencies.length" class="rounded-xl border border-amber-200 bg-amber-50 p-5"><h2 class="font-semibold text-amber-900">Year-end requirements still open</h2><ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-amber-800"><li v-for="item in yearEndDeficiencies" :key="item">{{ item }}</li></ul></section>
        <section class="rounded-xl border border-slate-200 bg-white p-5"><h2 class="font-semibold text-slate-900">Connected services</h2><div class="mt-3 flex flex-wrap gap-2"><a v-for="item in integrationLinks" :key="item.label" :href="item.href" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">{{ item.label }} <ArrowTopRightOnSquareIcon class="h-4 w-4" /></a></div></section>
      </template>

      <template v-else-if="activeTab === 'Members & Officers'">
        <section v-if="abilities.manage && ['draft', 'returned'].includes(cycle.status)" class="rounded-xl border border-slate-200 bg-white p-5"><h2 class="font-semibold">Add enrolled scholars</h2><div class="mt-3 flex gap-2"><input v-model="search" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Search name or PSHS ID" @keyup.enter="findStudents" /><button class="rounded-lg bg-indigo-600 px-4 text-white" @click="findStudents"><MagnifyingGlassIcon class="h-5 w-5" /></button></div><div v-if="searchResults.length" class="mt-3 max-h-56 overflow-auto rounded-lg border"><label v-for="student in searchResults" :key="student.student_id" class="flex items-center gap-3 border-b px-3 py-2 text-sm"><input v-model="selectedStudents" type="checkbox" :value="student.student_id" :disabled="!!student.assigned_program" /><span class="flex-1">{{ student.name }} · Grade {{ student.grade_level }} {{ student.section }}</span><span v-if="student.assigned_program" class="text-xs text-rose-600">Already in {{ student.assigned_program }}</span></label></div><button v-if="selectedStudents.length" class="mt-3 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white" @click="addMembers">Add {{ selectedStudents.length }} member(s)</button></section>
        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white"><div class="border-b px-5 py-4"><h2 class="font-semibold">Membership roster ({{ activeMembers.length }})</h2></div><div class="overflow-x-auto"><table class="min-w-full text-sm"><thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Scholar</th><th class="px-4 py-3">Grade/Section</th><th class="px-4 py-3">Consent</th><th class="px-4 py-3">Accountability</th><th class="px-4 py-3">Actions</th></tr></thead><tbody><tr v-for="member in cycle.memberships" :key="member.id" class="border-t"><td class="px-4 py-3 font-medium">{{ member.student?.full_name }}</td><td class="px-4 py-3">Grade {{ member.enrollment?.grade_level }} {{ member.enrollment?.section?.sectionname }}</td><td class="px-4 py-3"><span :class="statusClass(member.consent_status)" class="rounded-full px-2 py-1 text-xs">{{ label(member.consent_status) }}</span><div v-if="abilities.manage" class="mt-2 flex gap-1"><select :value="member.consent_status" class="rounded border px-1 py-1 text-xs" @change="setConsent(member, $event.target.value)"><option>pending</option><option>received</option><option>declined</option></select><label class="cursor-pointer rounded border px-2 py-1 text-xs">Upload<input class="hidden" type="file" accept="application/pdf,image/jpeg,image/png" @change="updateConsent(member, $event)" /></label><a v-if="member.consent_file_id" :href="route('alp.files.show', [cycle.id, member.consent_file_id])" target="_blank" class="rounded border px-2 py-1 text-xs">View</a></div></td><td class="px-4 py-3"><span :class="member.accountability ? 'text-rose-700' : 'text-emerald-700'">{{ member.accountability || 'Clear' }}</span></td><td class="px-4 py-3"><div class="flex flex-wrap gap-2"><button v-if="abilities.manage" class="text-indigo-600" @click="setAccountability(member)">Accountability</button><a v-if="member.completed_at" :href="route('alp.members.certificate', [cycle.id, member.id])" target="_blank" class="text-indigo-600">Certificate</a><button v-if="abilities.manage && ['draft', 'returned'].includes(cycle.status)" class="text-rose-600" @click="removeMember(member)"><TrashIcon class="h-4 w-4" /></button></div></td></tr></tbody></table></div></section>
        <section class="rounded-xl border border-slate-200 bg-white p-5"><h2 class="font-semibold">Officers and Registrar certification</h2><form v-if="abilities.manage && ['draft', 'returned'].includes(cycle.status)" class="mt-3 grid gap-2 sm:grid-cols-4" @submit.prevent="saveOfficer"><select v-model="officerForm.membership_id" required class="rounded-lg border px-3 py-2 text-sm sm:col-span-2"><option value="">Select member</option><option v-for="member in activeMembers" :key="member.id" :value="member.id">{{ member.student?.full_name }}</option></select><input v-model="officerForm.position" required class="rounded-lg border px-3 py-2 text-sm" placeholder="Position" /><button class="rounded-lg bg-indigo-600 px-3 py-2 text-sm text-white">Save officer</button></form><div class="mt-4 divide-y"><div v-for="officer in cycle.officers" :key="officer.id" class="flex flex-wrap items-center gap-3 py-3 text-sm"><div class="flex-1"><p class="font-medium">{{ officer.membership?.student?.full_name }} — {{ officer.position }}</p><p class="text-xs text-slate-500">{{ officer.certification_remarks || 'Awaiting Registrar review' }}</p></div><span class="rounded-full px-2 py-1 text-xs" :class="statusClass(officer.registrar_status)">{{ label(officer.registrar_status) }}</span><template v-if="abilities.certify"><button class="text-emerald-700" @click="certify(officer, 'certified')">Certify</button><button class="text-rose-700" @click="certify(officer, 'returned')">Return</button></template></div></div></section>
      </template>

      <template v-else-if="activeTab === 'QMS Forms'">
        <div class="grid gap-5 xl:grid-cols-2"><section v-for="doc in cycle.documents" :key="doc.id" class="rounded-xl border border-slate-200 bg-white p-5"><div class="flex items-start justify-between gap-3"><div><p class="text-xs font-semibold uppercase text-indigo-600">{{ doc.form_code }}</p><h2 class="font-semibold text-slate-900">{{ label(doc.document_type) }}</h2><p class="text-xs text-slate-500">Version {{ doc.version_no }} · Revision {{ doc.revision_no }}</p></div><span class="rounded-full px-2 py-1 text-xs" :class="statusClass(doc.status)">{{ label(doc.status) }}</span></div><div class="mt-4"><ControlledFormEditor v-model="documentDrafts[doc.id]" :disabled="!abilities.manage || !['draft', 'returned'].includes(cycle.status)" /></div><div class="mt-3 flex flex-wrap gap-2"><a :href="route('alp.documents.pdf', [cycle.id, doc.id])" target="_blank" class="rounded-lg border px-3 py-2 text-sm">Preview PDF</a><template v-if="abilities.manage && ['draft', 'returned'].includes(cycle.status)"><button class="rounded-lg border border-indigo-200 px-3 py-2 text-sm text-indigo-700" @click="saveDocument(doc, 'draft')">Save draft</button><button class="rounded-lg bg-indigo-600 px-3 py-2 text-sm text-white" @click="saveDocument(doc, 'submitted')">Mark submitted</button></template></div></section></div>
      </template>

      <template v-else-if="activeTab === 'Activities'">
        <section v-if="abilities.manage" class="rounded-xl border border-slate-200 bg-white p-5"><h2 class="font-semibold">New activity plan</h2><form class="mt-4 grid gap-3 md:grid-cols-2" @submit.prevent="saveActivity"><input v-model="activityForm.title" required class="rounded-lg border px-3 py-2 text-sm md:col-span-2" placeholder="Activity title" /><select v-model="activityForm.activity_type" class="rounded-lg border px-3 py-2 text-sm"><option value="regular">Regular</option><option value="major">Major</option><option value="community_service">Community service</option></select><input v-model="activityForm.venue" required class="rounded-lg border px-3 py-2 text-sm" placeholder="Venue" /><input v-model="activityForm.start_date" required type="date" class="rounded-lg border px-3 py-2 text-sm" /><input v-model="activityForm.end_date" required type="date" class="rounded-lg border px-3 py-2 text-sm" /><textarea v-model="activityForm.learning_outcomes" required rows="3" class="rounded-lg border px-3 py-2 text-sm" placeholder="Learning outcomes" /><textarea v-model="activityForm.target_participants" required rows="3" class="rounded-lg border px-3 py-2 text-sm" placeholder="Target participants" /><select v-model="activityForm.risk_level" class="rounded-lg border px-3 py-2 text-sm"><option>low</option><option>medium</option><option>high</option></select><input v-model="activityForm.risk_data.responsible_person" required class="rounded-lg border px-3 py-2 text-sm" placeholder="Risk owner / responsible person" /><textarea v-model="activityForm.risk_data.emergency_plan" required rows="2" class="rounded-lg border px-3 py-2 text-sm md:col-span-2" placeholder="Emergency and control plan" /><label class="flex items-center gap-2 text-sm"><input v-model="activityForm.consent_required" type="checkbox" /> Parent consent required</label><button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white md:col-start-2">Create activity</button></form></section>
        <div class="grid gap-4 lg:grid-cols-2"><section v-for="activity in cycle.activities" :key="activity.id" class="rounded-xl border border-slate-200 bg-white p-5"><div class="flex justify-between gap-3"><div><h2 class="font-semibold">{{ activity.title }}</h2><p class="text-sm text-slate-500">{{ label(activity.activity_type) }} · {{ date(activity.start_date) }} to {{ date(activity.end_date) }}</p></div><span class="h-fit rounded-full px-2 py-1 text-xs" :class="statusClass(activity.status)">{{ label(activity.status) }}</span></div><p class="mt-3 text-sm">{{ activity.learning_outcomes }}</p><p class="mt-2 text-xs text-slate-500">{{ activity.venue }} · Risk: {{ label(activity.risk_level) }}<span v-if="activity.ams_activity_id"> · Synced to AMS #{{ activity.ams_activity_id }}</span></p><div class="mt-4 flex gap-2"><button v-if="abilities.manage && ['draft', 'returned'].includes(activity.status)" class="rounded border px-3 py-1.5 text-sm text-indigo-700" @click="activityAction(activity, 'submit')">Submit</button><template v-if="abilities.activityApprove && activity.status === 'submitted'"><button class="rounded bg-emerald-600 px-3 py-1.5 text-sm text-white" @click="activityAction(activity, 'approve')">Approve</button><button class="rounded border border-rose-200 px-3 py-1.5 text-sm text-rose-700" @click="activityAction(activity, 'return')">Return</button></template><button v-if="abilities.manage && activity.status === 'approved'" class="rounded bg-teal-600 px-3 py-1.5 text-sm text-white" @click="activityAction(activity, 'complete')">Complete</button></div></section></div>
      </template>

      <template v-else-if="activeTab === 'Attendance'">
        <section v-if="abilities.manage" class="rounded-xl border border-slate-200 bg-white p-5"><h2 class="font-semibold">Create session</h2><form class="mt-3 grid gap-2 md:grid-cols-3" @submit.prevent="saveSession"><input v-model="sessionForm.session_date" required type="date" class="rounded-lg border px-3 py-2 text-sm" /><input v-model="sessionForm.topic" class="rounded-lg border px-3 py-2 text-sm" placeholder="Topic" /><input v-model="sessionForm.venue" class="rounded-lg border px-3 py-2 text-sm" placeholder="Venue" /><button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm text-white md:col-start-3">Create roster</button></form></section>
        <section v-for="session in cycle.sessions" :key="session.id" class="rounded-xl border border-slate-200 bg-white p-5"><div class="flex justify-between"><div><h2 class="font-semibold">{{ session.topic || 'ALP Session' }}</h2><p class="text-sm text-slate-500">{{ date(session.session_date) }} · {{ session.venue || 'No venue' }}</p></div><span class="text-xs text-slate-500">{{ label(session.status) }}</span></div><div class="mt-3 max-h-80 overflow-auto"><div v-for="member in activeMembers" :key="member.id" class="grid grid-cols-1 gap-2 border-t py-2 text-sm sm:grid-cols-[1fr_150px_1fr]"><span>{{ member.student?.full_name }}</span><select v-if="attendanceDrafts[session.id]?.[member.id]" v-model="attendanceDrafts[session.id][member.id].status" class="rounded border px-2 py-1"><option>present</option><option>absent</option><option>tardy</option><option>cutting</option><option>excused</option></select><input v-if="attendanceDrafts[session.id]?.[member.id]" v-model="attendanceDrafts[session.id][member.id].remarks" class="rounded border px-2 py-1" placeholder="Remarks" /></div></div><div class="mt-3 flex gap-2"><button v-if="abilities.manage" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm text-white" @click="saveAttendance(session)">Save attendance</button><a :href="route('alp.attendance.pdf', [cycle.id, session.id])" target="_blank" class="rounded-lg border px-4 py-2 text-sm">Form 33 PDF</a></div></section>
        <section class="overflow-hidden rounded-xl border bg-white"><table class="min-w-full text-sm"><thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Member</th><th class="px-4 py-3">Sessions</th><th class="px-4 py-3">Present</th><th class="px-4 py-3">Absent</th><th class="px-4 py-3">Tardy/Cutting</th></tr></thead><tbody><tr v-for="row in attendanceSummary" :key="row.membership_id" class="border-t"><td class="px-4 py-3">{{ row.name }}</td><td class="px-4 py-3">{{ row.sessions }}</td><td class="px-4 py-3">{{ row.present }}</td><td class="px-4 py-3">{{ row.absent }}</td><td class="px-4 py-3">{{ row.tardy }} / {{ row.cutting }}</td></tr></tbody></table></section>
      </template>

      <template v-else-if="activeTab === 'Finance'">
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5"><div v-for="item in [['Opening', financialSummary.opening], ['Income', financialSummary.income], ['Expenses', financialSummary.expenses], ['Turnover', financialSummary.turnover], ['Available', financialSummary.available]]" :key="item[0]" class="rounded-xl border bg-white p-4"><p class="text-xs uppercase text-slate-500">{{ item[0] }}</p><p class="mt-1 text-xl font-bold">{{ money(item[1]) }}</p></div></div>
        <section v-if="abilities.manage" class="rounded-xl border bg-white p-5"><h2 class="font-semibold">Record transaction</h2><form class="mt-3 grid gap-2 md:grid-cols-3" @submit.prevent="saveFinance"><input v-model="financeForm.transaction_date" required type="date" class="rounded-lg border px-3 py-2 text-sm" /><select v-model="financeForm.entry_type" class="rounded-lg border px-3 py-2 text-sm"><option>opening_balance</option><option>income</option><option>expense</option><option>turnover</option></select><input v-model="financeForm.amount" required type="number" step="0.01" min="0" class="rounded-lg border px-3 py-2 text-sm" placeholder="Amount" /><input v-model="financeForm.description" required class="rounded-lg border px-3 py-2 text-sm md:col-span-2" placeholder="Description" /><input type="file" accept="application/pdf,image/jpeg,image/png" class="rounded-lg border px-3 py-2 text-sm" @change="setReceipt" /><button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm text-white md:col-start-3">Post entry</button></form></section>
        <section class="overflow-hidden rounded-xl border bg-white"><table class="min-w-full text-sm"><thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Date</th><th class="px-4 py-3">Type</th><th class="px-4 py-3">Description</th><th class="px-4 py-3 text-right">Amount</th><th class="px-4 py-3"></th></tr></thead><tbody><tr v-for="entry in cycle.financial_entries" :key="entry.id" class="border-t"><td class="px-4 py-3">{{ date(entry.transaction_date) }}</td><td class="px-4 py-3">{{ label(entry.entry_type) }}</td><td class="px-4 py-3">{{ entry.description }}</td><td class="px-4 py-3 text-right">{{ money(entry.amount) }}</td><td class="px-4 py-3"><button v-if="abilities.manage && entry.status === 'posted'" class="text-rose-600" @click="router.delete(route('alp.finances.destroy', [cycle.id, entry.id]), { preserveScroll: true })"><TrashIcon class="h-4 w-4" /></button></td></tr></tbody></table></section>
      </template>

      <template v-else-if="activeTab === 'Reports'">
        <section v-if="abilities.manage" class="rounded-xl border bg-white p-5"><h2 class="font-semibold">Prepare report</h2><div class="mt-3 grid gap-2 md:grid-cols-2"><select v-model="reportForm.report_type" class="rounded-lg border px-3 py-2 text-sm"><option value="accomplishment">Accomplishment</option><option value="attendance_summary">Attendance summary</option><option value="financial">Financial</option><option value="coordinator">Coordinator</option><option value="adviser_evaluation">Adviser evaluation</option></select><input v-model="reportForm.period" class="rounded-lg border px-3 py-2 text-sm" placeholder="Period" /><textarea v-model="reportForm.data.narrative" rows="6" class="rounded-lg border px-3 py-2 text-sm md:col-span-2" placeholder="Narrative / summary" /></div><div class="mt-3 flex gap-2"><button class="rounded border px-3 py-2 text-sm" @click="saveReport('draft')">Save draft</button><button class="rounded bg-indigo-600 px-3 py-2 text-sm text-white" @click="saveReport('submitted')">Submit report</button></div></section>
        <div class="grid gap-4 lg:grid-cols-2"><section v-for="report in cycle.reports" :key="report.id" class="rounded-xl border bg-white p-5"><div class="flex justify-between"><div><h2 class="font-semibold">{{ label(report.report_type) }}</h2><p class="text-sm text-slate-500">{{ report.period }}</p></div><span class="rounded-full px-2 py-1 text-xs" :class="statusClass(report.status)">{{ label(report.status) }}</span></div><div class="mt-4 flex gap-2"><a :href="route('alp.reports.pdf', [cycle.id, report.id])" target="_blank" class="rounded border px-3 py-1.5 text-sm">PDF</a><button v-if="(abilities.coordinate || abilities.approve) && report.status === 'submitted'" class="rounded bg-emerald-600 px-3 py-1.5 text-sm text-white" @click="postAction(route('alp.reports.approve', [cycle.id, report.id]), { pin: transitionForm.pin })">Approve</button></div></section></div>
      </template>

      <template v-else>
        <section class="rounded-xl border bg-white p-5"><h2 class="font-semibold">Immutable activity trail</h2><div class="mt-4 divide-y"><div v-for="log in cycle.activity_logs" :key="log.id" class="grid gap-1 py-3 text-sm sm:grid-cols-[180px_1fr_200px]"><span class="text-slate-500">{{ new Date(log.created_at).toLocaleString('en-PH') }}</span><span class="font-medium">{{ label(log.action) }}</span><span class="text-slate-500">{{ log.actor?.name || 'System' }}</span></div></div></section>
      </template>
    </div>
  </AdminLayout>
</template>
