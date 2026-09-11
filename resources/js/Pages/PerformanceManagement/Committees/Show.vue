<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppCard from '@/Components/AppCard.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppSelect from '@/Components/AppSelect.vue'
import AppTable from '@/Components/AppTable.vue'
import AppModal from '@/Components/AppModal.vue'
import AppInput from '@/Components/AppInput.vue'
import AppTextarea from '@/Components/AppTextarea.vue'
import EmptyState from '@/Components/EmptyState.vue'
import AppTabs from '@/Components/AppTabs.vue'
import TaskBoard from '@/Components/Committee/TaskBoard.vue'
import { ArrowLeftIcon, ClipboardDocumentListIcon, ClockIcon, StarIcon, UsersIcon, PlusIcon, TrashIcon } from '@heroicons/vue/24/outline'
import { ipcrAdjectivalRating } from '@/Composables/ipcrAdjectivalRating'
import { useSubmit } from '@/Composables/useSubmit'
import { confirmDelete } from '@/Composables/useConfirm.js'
import Swal from 'sweetalert2'

const props = defineProps({
  committee:      Object,
  members:        Array,
  roster:          { type: Array, default: () => [] },
  canManageRoster: { type: Boolean, default: false },
  availableUsers:  { type: Array, default: () => [] },
  terms:          Array,
  selectedTermId: Number,
  authUser:       Object,
  isChairperson:  Boolean,
  canManage:      Boolean,
  tasks:            { type: Array, default: () => [] },
  boardMembers:     { type: Array, default: () => [] },
  ratingPeriods:    { type: Array, default: () => [] },
  selectedPeriodId: { type: Number, default: null },
  canManageBoard:   { type: Boolean, default: false },
  auditTrail:       { type: Array, default: () => [] },
  defaultSubmissionFrequency: { type: String, default: null },
})

const VALID_TABS = ['members', 'board', 'ratings', 'history']
const requestedTab = new URLSearchParams(window.location.search).get('tab')
const activeTab = ref(VALID_TABS.includes(requestedTab) ? requestedTab : 'members')
const boardTabs = [
  { key: 'members', label: 'Members',                   icon: UsersIcon },
  { key: 'board',   label: 'Task Board',                icon: ClipboardDocumentListIcon },
  { key: 'ratings', label: 'Accomplishments & Ratings', icon: StarIcon },
  { key: 'history', label: 'History',                   icon: ClockIcon },
]
const boardPlans = computed(() => (props.members ?? [])
  .flatMap(m => m.items)
  .filter(i => i.success_indicator)
  .map(i => ({ id: i.id, success_indicator: i.success_indicator })))

const { isSubmitting, submit } = useSubmit()

// Statuses before which an IPCR V2 item cannot be rated yet — mirrors
// CommitteeIpcrRatingService::NOT_YET_RATABLE_STATUSES on the backend.
const NOT_YET_RATABLE_STATUSES = ['New Target', 'For Review', 'Returned for Revision']

// ── Term switching ─────────────────────────────────────────────────────────
function switchTerm(termId) {
  router.get(
    route('pm-committees.show', props.committee.id),
    { term_id: termId },
    { preserveState: false }
  )
}

// ── Rate modal (chairperson / admin only — writes IPCR V2 directly) ───────
const showModal  = ref(false)
const modalEntry = ref(null) // { member, item }

const rateForm = ref({
  support_item_id: null,
  accomplishment: '',
  mov_link: '',
  quality_rating: null,
  efficiency_rating: null,
  timeliness_rating: null,
})

function openRateModal(member, item) {
  modalEntry.value = { member, item }
  rateForm.value = {
    support_item_id: item.id,
    accomplishment: item.actual_accomplishment ?? '',
    mov_link: item.mov_link ?? '',
    quality_rating: item.quality_rating,
    efficiency_rating: item.efficiency_rating,
    timeliness_rating: item.timeliness_rating,
  }
  showModal.value = true
}

function closeModal() { showModal.value = false; modalEntry.value = null }

function submitModal() {
  const entry = modalEntry.value
  if (!entry) return

  submit.post(
    route('pm-committees.rate', entry.member.assignment_id),
    rateForm.value,
    {
      onSuccess: () => { closeModal(); Swal.fire({ icon: 'success', title: 'Saved', timer: 1200, showConfirmButton: false }) },
      onError: (err) => Swal.fire('Error', Object.values(err).flat().join('\n') || 'Failed to save.', 'error'),
    }
  )
}

// monday-style rollup: pull the member's most recent accomplishment-flagged
// task update (body + MOV link) as a one-click starting point for the
// chairperson's rating, instead of manually re-typing it from the board.
const latestAccomplishmentFor = (userId) => {
  const candidates = props.tasks
    .filter(t => (t.assignees ?? []).some(a => a.id === userId) && t.latest_accomplishment)
    .map(t => t.latest_accomplishment)
  if (!candidates.length) return null
  return candidates.reduce((latest, c) => (!latest || new Date(c.created_at) > new Date(latest.created_at)) ? c : latest, null)
}
function useLatestAccomplishment() {
  const member = modalEntry.value?.member
  if (!member) return
  const latest = latestAccomplishmentFor(member.user_id)
  if (!latest) return
  const existing = rateForm.value.accomplishment?.trim()
  rateForm.value.accomplishment = (existing ? existing + '\n' : '') + latest.body
  if (latest.mov_link && !rateForm.value.mov_link?.trim()) {
    rateForm.value.mov_link = latest.mov_link
  }
}

// ── Add / Remove Member (Members tab) ──────────────────────────────────────
const { isSubmitting: isSubmittingMember, submit: submitMember } = useSubmit()

const showAddMemberModal = ref(false)
const memberSearch = ref('')
const addMemberForm = ref({ user_id: '', role: 'member', task: '', load_units_override: '' })

const filteredAvailableUsers = computed(() => {
  const q = memberSearch.value.toLowerCase()
  if (!q) return props.availableUsers
  return props.availableUsers.filter(u => u.name.toLowerCase().includes(q))
})

function openAddMemberModal() {
  addMemberForm.value = { user_id: '', role: 'member', task: '', load_units_override: '' }
  memberSearch.value = ''
  showAddMemberModal.value = true
}
function closeAddMemberModal() { showAddMemberModal.value = false }

function submitAddMember() {
  submitMember.post(route('pm-committees.members.store', props.committee.id), addMemberForm.value, {
    onSuccess: () => { closeAddMemberModal(); Swal.fire({ icon: 'success', title: 'Member added', timer: 1200, showConfirmButton: false }) },
    onError: (err) => Swal.fire('Error', Object.values(err).flat().join('\n') || 'Failed to add member.', 'error'),
  })
}

async function removeMember(member) {
  const ok = await confirmDelete(`Remove ${member.name} from this committee?`)
  if (!ok) return
  router.delete(route('pm-committees.members.destroy', [props.committee.id, member.user_id]), {
    onSuccess: () => Swal.fire({ icon: 'success', title: 'Member removed', timer: 1200, showConfirmButton: false }),
  })
}

// ── Helpers ────────────────────────────────────────────────────────────────
const adjectival = ipcrAdjectivalRating

const liveAvg = computed(() => {
  const vals = [rateForm.value.quality_rating, rateForm.value.efficiency_rating, rateForm.value.timeliness_rating]
    .filter(v => v !== null && v !== '' && !isNaN(v))
    .map(Number)
  if (!vals.length) return null
  return (vals.reduce((a, b) => a + b, 0) / vals.length).toFixed(2)
})

function roleBadge(role) {
  return {
    chairperson: 'amber',
    co_chair:    'amber',
    secretary:   'blue',
    member:      'slate',
  }[role] ?? 'slate'
}

function roleLabel(role) {
  return { chairperson: 'Chairperson', co_chair: 'Co-Chair', secretary: 'Secretary', member: 'Member' }[role] ?? role
}

function canRateItem(item) {
  return (props.isChairperson || props.canManage) && !NOT_YET_RATABLE_STATUSES.includes(item.ipcr_status)
}

const lifecycleBadge = computed(() => {
  if (props.committee.is_revoked) return 'red'
  return { perpetual: 'green', school_year: 'blue', seasonal: 'amber' }[props.committee.scope_type] ?? 'slate'
})
const lifecycleLabel = computed(() => {
  if (props.committee.is_revoked) return props.committee.amended_into ? 'Amended' : 'Revoked'
  return { perpetual: 'Perpetual', school_year: 'School Year', seasonal: 'Seasonal' }[props.committee.scope_type] ?? props.committee.scope_type
})

function formatDate(value) {
  if (!value) return '—'
  return new Date(value).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })
}

const AUDIT_ACTION_LABELS = {
  created: 'Committee created',
  updated: 'Committee edited',
  committee_member_added: 'Member added',
  committee_member_removed: 'Member removed',
  committee_role_changed: 'Role changed',
  committee_revoked: 'Committee revoked',
  committee_amended: 'Committee amended',
}
function auditLabel(log) { return AUDIT_ACTION_LABELS[log.action] ?? log.action }
</script>

<template>
  <Head :title="`${committee.name} — Committee`" />
  <AdminLayout :title="committee.name">
    <div class="space-y-5">

      <!-- Header -->
      <div class="flex items-center gap-3">
        <Link :href="route('pm-committees.index')"
          class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-800 transition-colors">
          <ArrowLeftIcon class="w-4 h-4" /> Back to Committees
        </Link>
      </div>

      <!-- Committee Info Card -->
      <AppCard>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
          <div>
            <p class="text-slate-500 font-medium text-xs uppercase tracking-wide">Committee</p>
            <p class="font-semibold text-slate-800 mt-0.5">{{ committee.name }}</p>
            <span v-if="committee.code" class="font-mono text-xs text-indigo-600">{{ committee.code }}</span>
            <div class="mt-1 flex flex-wrap gap-1">
              <AppBadge :color="lifecycleBadge">{{ lifecycleLabel }}</AppBadge>
              <AppBadge v-if="committee.is_revoked" color="red">Revoked</AppBadge>
            </div>
          </div>
          <div>
            <p class="text-slate-500 font-medium text-xs uppercase tracking-wide">Chairperson</p>
            <p class="font-semibold text-slate-800 mt-0.5">{{ committee.head?.name ?? '—' }}</p>
            <p v-if="committee.head?.position" class="text-xs text-slate-400">{{ committee.head.position }}</p>
          </div>
          <div>
            <p class="text-slate-500 font-medium text-xs uppercase tracking-wide">Load Rates</p>
            <p class="text-slate-700 mt-0.5">Chair: <strong>{{ committee.chairperson_load_units }}</strong> units</p>
            <p class="text-slate-700">Member: <strong>{{ committee.member_load_units }}</strong> units</p>
          </div>
          <div>
            <p class="text-slate-500 font-medium text-xs uppercase tracking-wide">SO / Issuance</p>
            <p v-if="committee.issuance" class="text-slate-700 mt-0.5 text-xs">{{ committee.issuance.label }}</p>
            <p v-else-if="committee.so_number" class="text-slate-700 mt-0.5 text-xs">SO {{ committee.so_number }}</p>
            <p v-else class="text-slate-400 mt-0.5 text-xs">—</p>
          </div>
          <div v-if="committee.description" class="col-span-2">
            <p class="text-slate-500 font-medium text-xs uppercase tracking-wide">Description</p>
            <p class="text-slate-700 mt-0.5 text-xs leading-relaxed">{{ committee.description }}</p>
          </div>
          <div v-if="committee.is_revoked" class="col-span-2">
            <p class="text-slate-500 font-medium text-xs uppercase tracking-wide">Revocation</p>
            <p class="text-slate-700 mt-0.5 text-xs">
              By {{ committee.revoked_by?.name ?? '—' }} on {{ formatDate(committee.revoked_at) }}
              <span v-if="committee.revocation_reason">— {{ committee.revocation_reason }}</span>
            </p>
            <p v-if="committee.amended_into" class="text-xs text-indigo-600 mt-1">
              Amended into: <Link :href="route('pm-committees.show', committee.amended_into.id)" class="hover:underline">{{ committee.amended_into.name }}</Link>
            </p>
          </div>
        </div>
      </AppCard>

      <!-- Term selector -->
      <div class="flex flex-wrap items-center gap-3">
        <span class="text-sm font-medium text-slate-600">Term:</span>
        <div class="w-64">
          <AppSelect :model-value="selectedTermId" :show-blank="false"
            @update:model-value="v => switchTerm(Number(v))">
            <option v-for="t in terms" :key="t.id" :value="t.id">
              {{ t.label }}{{ t.is_current ? ' (current)' : '' }}
            </option>
          </AppSelect>
        </div>
      </div>

      <AppTabs v-model="activeTab" :tabs="boardTabs">
        <template #tab-members>
          <AppCard :padded="false">
            <div class="flex items-center justify-end p-3 border-b border-slate-100" v-if="canManageRoster">
              <AppButton size="sm" @click="openAddMemberModal">
                <PlusIcon class="w-4 h-4" /> Add Member
              </AppButton>
            </div>
            <AppTable :is-empty="!roster?.length">
              <template #head>
                <tr>
                  <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Name</th>
                  <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Role</th>
                  <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Task</th>
                  <th class="px-3 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Load Units</th>
                  <th class="px-3 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider">This Term</th>
                  <th v-if="canManageRoster" class="px-3 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider w-16">Action</th>
                </tr>
              </template>

              <tr v-for="member in roster" :key="member.user_id" class="hover:bg-indigo-50/40">
                <td class="px-4 py-3">
                  <p class="font-medium text-slate-800">{{ member.name }}</p>
                  <p v-if="member.position" class="text-xs text-slate-400">{{ member.position }}</p>
                </td>
                <td class="px-4 py-3">
                  <AppBadge :color="roleBadge(member.is_head ? 'chairperson' : member.role)">
                    {{ member.is_head ? 'Chairperson' : roleLabel(member.role) }}
                  </AppBadge>
                </td>
                <td class="px-4 py-3 text-slate-700 text-xs">{{ member.task || '—' }}</td>
                <td class="px-3 py-3 text-center text-sm text-slate-700">
                  {{ member.effective_load_units }}
                  <span v-if="member.load_units_override !== null" class="ml-1 text-xs text-indigo-500" title="Override">*</span>
                </td>
                <td class="px-3 py-3 text-center">
                  <AppBadge :color="member.term_status === 'active' ? 'green' : 'slate'">{{ member.term_status }}</AppBadge>
                </td>
                <td v-if="canManageRoster" class="px-3 py-3 text-center">
                  <AppIconButton v-if="!member.is_head" label="Remove" variant="danger" @click="removeMember(member)">
                    <TrashIcon class="w-4 h-4" />
                  </AppIconButton>
                </td>
              </tr>

              <template #empty>
                <EmptyState title="No members in this committee yet." :icon="UsersIcon" />
              </template>
            </AppTable>
          </AppCard>
        </template>

        <template #tab-board>
          <TaskBoard
            :committee="committee"
            :tasks="tasks"
            :board-members="boardMembers"
            :rating-periods="ratingPeriods"
            :selected-period-id="selectedPeriodId"
            :can-manage-board="canManageBoard"
            :current-user-id="authUser.id"
            :plans="boardPlans"
            :default-submission-frequency="defaultSubmissionFrequency"
          />
        </template>

        <template #tab-ratings>
          <AppCard :padded="false">
            <AppTable :is-empty="!members?.length">
              <template #head>
                <tr>
                  <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Member</th>
                  <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Role</th>
                  <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider max-w-xs">Accomplishment / MOV</th>
                  <th class="px-3 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider w-10">Q</th>
                  <th class="px-3 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider w-10">E</th>
                  <th class="px-3 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider w-10">T</th>
                  <th class="px-3 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider w-14">Avg</th>
                  <th class="px-3 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider w-40">Action</th>
                </tr>
              </template>

              <template v-for="member in members" :key="member.assignment_id">
                <tr v-if="!member.items.length" class="hover:bg-indigo-50/40">
                  <td class="px-4 py-3">
                    <p class="font-medium text-slate-800">{{ member.user_name }}</p>
                    <p v-if="member.user_position" class="text-xs text-slate-400">{{ member.user_position }}</p>
                  </td>
                  <td class="px-4 py-3"><AppBadge :color="roleBadge(member.role)">{{ roleLabel(member.role) }}</AppBadge></td>
                  <td class="px-4 py-3 text-xs text-slate-400 italic" colspan="6">No IPCR V2 targets generated yet for this rating period.</td>
                </tr>
                <tr v-for="item in member.items" :key="item.id" class="hover:bg-indigo-50/40">
                  <td class="px-4 py-3">
                    <p class="font-medium text-slate-800">{{ member.user_name }}</p>
                    <p v-if="member.user_position" class="text-xs text-slate-400">{{ member.user_position }}</p>
                  </td>
                  <td class="px-4 py-3"><AppBadge :color="roleBadge(member.role)">{{ roleLabel(member.role) }}</AppBadge></td>
                  <td class="px-4 py-3 max-w-xs">
                    <p class="text-slate-700 text-xs truncate">{{ item.actual_accomplishment || '—' }}</p>
                    <a v-if="item.mov_link" :href="item.mov_link" target="_blank"
                      class="text-indigo-600 text-xs hover:underline">MOV Link ↗</a>
                  </td>
                  <td class="px-3 py-3 text-center text-sm text-slate-700">{{ item.quality_rating ?? '—' }}</td>
                  <td class="px-3 py-3 text-center text-sm text-slate-700">{{ item.efficiency_rating ?? '—' }}</td>
                  <td class="px-3 py-3 text-center text-sm text-slate-700">{{ item.timeliness_rating ?? '—' }}</td>
                  <td class="px-3 py-3 text-center text-sm font-semibold text-slate-800">{{ item.row_average ?? '—' }}</td>
                  <td class="px-3 py-3 text-center">
                    <AppButton v-if="canRateItem(item)" size="sm" @click="openRateModal(member, item)">Rate</AppButton>
                    <span v-else-if="isChairperson || canManage" class="text-xs text-slate-400 italic">Targets not yet approved</span>
                  </td>
                </tr>
              </template>

              <template #mobileCard>
                <template v-for="member in members" :key="member.assignment_id">
                  <div v-for="item in (member.items.length ? member.items : [null])" :key="item?.id ?? 'none'" class="p-4 space-y-2">
                    <div class="flex items-start justify-between gap-2">
                      <div>
                        <p class="font-medium text-slate-800">{{ member.user_name }}</p>
                        <p v-if="member.user_position" class="text-xs text-slate-400">{{ member.user_position }}</p>
                      </div>
                      <AppBadge :color="roleBadge(member.role)">{{ roleLabel(member.role) }}</AppBadge>
                    </div>
                    <template v-if="item">
                      <p class="text-xs text-slate-700">{{ item.actual_accomplishment || '—' }}</p>
                      <a v-if="item.mov_link" :href="item.mov_link" target="_blank"
                        class="text-indigo-600 text-xs hover:underline">MOV Link ↗</a>
                      <div class="flex justify-between text-xs text-slate-500">
                        <span>Q {{ item.quality_rating ?? '—' }} · E {{ item.efficiency_rating ?? '—' }} · T {{ item.timeliness_rating ?? '—' }}</span>
                        <span class="font-semibold text-slate-800">Avg {{ item.row_average ?? '—' }}</span>
                      </div>
                      <div class="pt-1">
                        <AppButton v-if="canRateItem(item)" size="sm" @click="openRateModal(member, item)">Rate</AppButton>
                        <span v-else-if="isChairperson || canManage" class="text-xs text-slate-400 italic">Targets not yet approved</span>
                      </div>
                    </template>
                    <p v-else class="text-xs text-slate-400 italic">No IPCR V2 targets generated yet for this rating period.</p>
                  </div>
                </template>
              </template>

              <template #empty>
                <EmptyState title="No active members for this committee this term." />
              </template>
            </AppTable>
          </AppCard>
        </template>

        <template #tab-history>
          <AppCard>
            <div v-if="!auditTrail.length" class="text-sm text-slate-400 italic py-6 text-center">
              No history recorded for this committee yet.
            </div>
            <ol v-else class="relative border-l border-slate-200 ml-2 space-y-6">
              <li v-for="log in auditTrail" :key="log.id" class="ml-4">
                <span class="absolute -left-[7px] h-3 w-3 rounded-full bg-indigo-500 border-2 border-white"></span>
                <p class="text-sm font-medium text-slate-800">{{ auditLabel(log) }}</p>
                <p class="text-xs text-slate-400">{{ log.user_name }} — {{ formatDate(log.created_at) }}</p>
                <div v-if="log.new_values" class="mt-1 text-xs text-slate-500 bg-slate-50 rounded px-2 py-1 inline-block">
                  <span v-for="(v, k) in log.new_values" :key="k" class="mr-2">{{ k }}: {{ v }}</span>
                </div>
              </li>
            </ol>
          </AppCard>
        </template>
      </AppTabs>
    </div>

    <!-- Rate Modal -->
    <AppModal :show="showModal"
      :title="'Rate Member — ' + (modalEntry?.member?.user_name ?? '')"
      :subtitle="committee.name" size="lg" @close="closeModal">
      <form @submit.prevent="submitModal" class="space-y-4">
        <AppTextarea v-model="rateForm.accomplishment" label="Accomplishment" :rows="3" />
        <button v-if="modalEntry && latestAccomplishmentFor(modalEntry.member.user_id)" type="button"
          class="text-xs text-indigo-600 hover:underline -mt-2"
          @click="useLatestAccomplishment">
          + Use latest accomplishment submitted from the board
        </button>
        <AppInput v-model="rateForm.mov_link" type="url" label="MOV Link" placeholder="https://…" />

        <div class="text-xs text-slate-500 bg-slate-50 rounded-lg p-3">
          <p class="font-semibold text-slate-700 mb-1">Rating Scale:</p>
          <p>5 — Outstanding &nbsp; 4 — Very Satisfactory &nbsp; 3 — Satisfactory &nbsp; 2 — Unsatisfactory &nbsp; 1 — Poor</p>
        </div>
        <div class="grid grid-cols-3 gap-3">
          <AppInput v-model.number="rateForm.quality_rating" type="number" min="1" max="5" label="Quality (1–5)" />
          <AppInput v-model.number="rateForm.efficiency_rating" type="number" min="1" max="5" label="Efficiency (1–5)" />
          <AppInput v-model.number="rateForm.timeliness_rating" type="number" min="1" max="5" label="Timeliness (1–5)" />
        </div>
        <div class="text-sm text-slate-700">
          Live Average: <strong class="text-indigo-700">{{ liveAvg ?? '—' }}</strong>
          <span v-if="liveAvg" class="ml-2 text-slate-500">— {{ adjectival(liveAvg) }}</span>
        </div>

        <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
          <AppButton type="button" variant="secondary" @click="closeModal">Cancel</AppButton>
          <AppButton type="submit" :loading="isSubmitting" :disabled="isSubmitting">{{ isSubmitting ? 'Saving…' : 'Save' }}</AppButton>
        </div>
      </form>
    </AppModal>

    <!-- Add Member Modal -->
    <AppModal :show="showAddMemberModal" title="Add Member" :subtitle="committee.name" size="md" @close="closeAddMemberModal">
      <form @submit.prevent="submitAddMember" class="space-y-4">
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Member <span class="text-red-500">*</span></label>
          <AppInput v-model="memberSearch" placeholder="Search users…" class="mb-2" />
          <div class="border border-slate-200 rounded-lg max-h-48 overflow-y-auto">
            <button v-for="u in filteredAvailableUsers" :key="u.id" type="button"
              class="w-full text-left px-3 py-2 text-sm hover:bg-slate-50 flex items-center justify-between"
              :class="{ 'bg-indigo-50': addMemberForm.user_id === u.id }"
              @click="addMemberForm.user_id = u.id">
              <span>{{ u.name }}<span v-if="u.position" class="text-slate-400"> ({{ u.position }})</span></span>
              <span v-if="addMemberForm.user_id === u.id" class="text-indigo-600 text-xs font-medium">Selected</span>
            </button>
            <p v-if="!filteredAvailableUsers.length" class="text-slate-400 text-xs px-3 py-3">No users match.</p>
          </div>
        </div>

        <AppSelect v-model="addMemberForm.role" label="Role">
          <option value="member">Member</option>
          <option value="secretary">Secretary</option>
          <option value="co_chair">Co-Chairperson</option>
        </AppSelect>

        <AppInput v-model="addMemberForm.task" label="Task" placeholder="e.g. Documentation, Logistics…" />

        <AppInput v-model.number="addMemberForm.load_units_override" type="number" step="0.25" min="0" max="5"
          label="Load Units Override (optional)" placeholder="Uses committee default if left blank" />

        <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
          <AppButton type="button" variant="secondary" @click="closeAddMemberModal">Cancel</AppButton>
          <AppButton type="submit" :loading="isSubmittingMember" :disabled="isSubmittingMember || !addMemberForm.user_id">
            {{ isSubmittingMember ? 'Saving…' : 'Add Member' }}
          </AppButton>
        </div>
      </form>
    </AppModal>
  </AdminLayout>
</template>
