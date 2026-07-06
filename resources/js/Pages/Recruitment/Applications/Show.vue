<script setup>
import { ref, computed } from 'vue'
import { Head, router, usePage, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppCard from '@/Components/AppCard.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppButton from '@/Components/AppButton.vue'
import AppTabs from '@/Components/AppTabs.vue'
import AppModal from '@/Components/AppModal.vue'
import AppInput from '@/Components/AppInput.vue'
import AppSelect from '@/Components/AppSelect.vue'
import AppTextarea from '@/Components/AppTextarea.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { confirmAction } from '@/Composables/useConfirm.js'
import Swal from 'sweetalert2'
import {
  ArrowTopRightOnSquareIcon,
  UserIcon,
  ClipboardDocumentCheckIcon,
  CalendarDaysIcon,
  ChartBarIcon,
  CheckBadgeIcon,
} from '@heroicons/vue/24/outline'
import PlacementApprovalForm from './PlacementApprovalForm.vue'

const props = defineProps({
  application:          { type: Object, required: true },
  availableTransitions: { type: Array,  default: () => [] },
})

const page = usePage()

// ── Active tab ─────────────────────────────────────────────────────────────────
const activeTab = ref('overview')
const tabs = [
  { key: 'overview',   label: 'Overview',   icon: UserIcon },
  { key: 'evaluation', label: 'Evaluation', icon: ClipboardDocumentCheckIcon },
  { key: 'interview',  label: 'Interview',  icon: CalendarDaysIcon },
  { key: 'ranking',    label: 'Ranking',    icon: ChartBarIcon },
  { key: 'selection',  label: 'Selection',  icon: CheckBadgeIcon },
]

// ── Stage helpers ──────────────────────────────────────────────────────────────
function stageColor(stage) {
  const map = {
    submitted:  'slate',
    screening:  'amber',
    exam:       'amber',
    interview:  'blue',
    ranking:    'blue',
    selection:  'blue',
    placement:  'green',
    rejected:   'red',
    withdrawn:  'slate',
  }
  return map[stage] ?? 'slate'
}

function docStatusColor(status) {
  const map = { verified: 'green', pending: 'amber', rejected: 'red' }
  return map[status] ?? 'slate'
}

function interviewStatusColor(status) {
  const map = { scheduled: 'blue', completed: 'green', cancelled: 'red', no_show: 'slate' }
  return map[status] ?? 'slate'
}

function approvalStatusColor(status) {
  const map = { approved: 'green', disapproved: 'red', pending: 'amber' }
  return map[status] ?? 'slate'
}

const terminalStages = ['rejected', 'withdrawn', 'placement']
const isTerminal = computed(() => terminalStages.includes(props.application.current_stage))

const formatDate = (iso) => iso
  ? new Date(iso).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
  : '—'

const formatDateTime = (iso) => iso
  ? new Date(iso).toLocaleString('en-PH', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' })
  : '—'

// ── Advance stage ──────────────────────────────────────────────────────────────
const advanceLoading = ref(false)

const advanceTo = async (toStage) => {
  const confirmed = await confirmAction({
    title: `Move to "${toStage}"?`,
    confirmText: 'Yes',
  })
  if (!confirmed) return

  advanceLoading.value = true
  router.patch(route('recruitment.applications.advance', props.application.id), { stage: toStage }, {
    onSuccess: () => Swal.fire({ icon: 'success', title: `Moved to ${toStage}!`, timer: 1400, showConfirmButton: false }),
    onError: (e) => Swal.fire('Error', Object.values(e)[0], 'error'),
    onFinish: () => { advanceLoading.value = false },
  })
}

// ── Reject / Withdraw ──────────────────────────────────────────────────────────
const rejectApp = async () => {
  const { value: reason, isConfirmed } = await Swal.fire({
    title: 'Reject Application',
    input: 'textarea', inputLabel: 'Reason *',
    inputPlaceholder: 'Enter reason for rejection…',
    showCancelButton: true, confirmButtonColor: '#ef4444',
    confirmButtonText: 'Reject', reverseButtons: true,
  })
  if (!isConfirmed || !reason?.trim()) return

  router.patch(route('recruitment.applications.reject', props.application.id), { reason }, {
    onSuccess: () => Swal.fire({ icon: 'success', title: 'Application rejected.', timer: 1500, showConfirmButton: false }),
    onError: (e) => Swal.fire('Error', Object.values(e)[0], 'error'),
  })
}

const withdrawApp = async () => {
  const { value: reason, isConfirmed } = await Swal.fire({
    title: 'Withdraw Application',
    input: 'textarea', inputLabel: 'Reason (optional)',
    showCancelButton: true,
    confirmButtonText: 'Withdraw', reverseButtons: true,
  })
  if (!isConfirmed) return

  router.patch(route('recruitment.applications.withdraw', props.application.id), { reason: reason ?? '' }, {
    onSuccess: () => Swal.fire({ icon: 'success', title: 'Application withdrawn.', timer: 1500, showConfirmButton: false }),
    onError: (e) => Swal.fire('Error', Object.values(e)[0], 'error'),
  })
}

// ── Evaluation scores ──────────────────────────────────────────────────────────
const scoreForm   = ref({})  // { [criteria_id]: { score, remarks } }
const scoreErrors = ref({})
const scoreSaving = ref(false)

// Init score form from existing scores + fill gaps for all criteria
const initScoreForm = () => {
  const criteria = props.application.recruitment_type?.evaluation_criteria ?? []
  criteria.forEach(c => {
    const existing = props.application.evaluation_scores?.find(s => s.criteria_id === c.id)
    scoreForm.value[c.id] = {
      score:   existing?.score ?? '',
      remarks: existing?.remarks ?? '',
    }
  })
}
initScoreForm()

const saveScores = () => {
  scoreSaving.value = true
  scoreErrors.value = {}

  const scores = Object.entries(scoreForm.value).map(([id, data]) => ({
    criteria_id: parseInt(id),
    score:       parseFloat(data.score),
    remarks:     data.remarks,
  }))

  router.post(route('recruitment.evaluations.scores', props.application.id), { scores }, {
    onSuccess: () => Swal.fire({ icon: 'success', title: 'Scores saved!', timer: 1400, showConfirmButton: false }),
    onError: (e) => { scoreErrors.value = e; Swal.fire('Validation Error', Object.values(e)[0], 'error') },
    onFinish: () => { scoreSaving.value = false },
  })
}

const computedTotal = computed(() => {
  const criteria = props.application.recruitment_type?.evaluation_criteria ?? []
  return criteria.reduce((sum, c) => {
    const score = parseFloat(scoreForm.value[c.id]?.score ?? 0)
    return sum + (score * c.weight_percentage / 100)
  }, 0).toFixed(4)
})

// ── Compute ranking ────────────────────────────────────────────────────────────
const rankLoading = ref(false)

const computeRank = () => {
  rankLoading.value = true
  router.post(route('recruitment.evaluations.rank', props.application.id), {}, {
    onSuccess: () => Swal.fire({ icon: 'success', title: 'Ranking computed!', timer: 1400, showConfirmButton: false }),
    onError: (e) => Swal.fire('Error', Object.values(e)[0], 'error'),
    onFinish: () => { rankLoading.value = false },
  })
}

// ── Recommend ──────────────────────────────────────────────────────────────────
const recommendLoading = ref(false)
const deliberationNotes = ref(props.application.ranking_summary?.deliberation_notes ?? '')

const toggleRecommend = () => {
  recommendLoading.value = true
  const current = props.application.ranking_summary?.is_recommended ?? false
  router.patch(route('recruitment.evaluations.recommend', props.application.id), {
    is_recommended:     !current,
    deliberation_notes: deliberationNotes.value,
  }, {
    onSuccess: () => Swal.fire({ icon: 'success', title: current ? 'Recommendation removed.' : 'Marked as recommended!', timer: 1400, showConfirmButton: false }),
    onError: (e) => Swal.fire('Error', Object.values(e)[0], 'error'),
    onFinish: () => { recommendLoading.value = false },
  })
}

// ── Schedule interview ─────────────────────────────────────────────────────────
const showInterviewModal = ref(false)
const interviewForm = ref({
  interview_date: '',
  panel_members:  [],
  venue:          '',
  format:         'panel',
})
const interviewLoading = ref(false)
const interviewErrors  = ref({})

const submitInterview = () => {
  interviewLoading.value = true
  interviewErrors.value  = {}
  router.post(route('recruitment.evaluations.interview.schedule', props.application.id), interviewForm.value, {
    onSuccess: () => {
      showInterviewModal.value = false
      Swal.fire({ icon: 'success', title: 'Interview scheduled!', timer: 1400, showConfirmButton: false })
    },
    onError: (e) => { interviewErrors.value = e },
    onFinish: () => { interviewLoading.value = false },
  })
}

// ── Interview result ───────────────────────────────────────────────────────────
const showResultModal   = ref(false)
const resultTarget      = ref(null)
const resultForm        = ref({ rating: '', remarks: '' })
const resultLoading     = ref(false)

const openResult = (interview) => {
  resultTarget.value = interview
  resultForm.value   = { rating: interview.rating ?? '', remarks: interview.remarks ?? '' }
  showResultModal.value = true
}

const submitResult = () => {
  resultLoading.value = true
  router.patch(
    route('recruitment.evaluations.interview.result', [props.application.id, resultTarget.value.id]),
    resultForm.value,
    {
      onSuccess: () => {
        showResultModal.value = false
        Swal.fire({ icon: 'success', title: 'Result recorded!', timer: 1400, showConfirmButton: false })
      },
      onError: (e) => Swal.fire('Error', Object.values(e)[0], 'error'),
      onFinish: () => { resultLoading.value = false },
    }
  )
}

const type = computed(() => props.application.recruitment_type ?? {})
</script>

<template>
  <Head :title="`Application #${application.id} — Recruitment`" />
  <AdminLayout :title="`Application #${application.id}`">
    <div class="max-w-5xl mx-auto space-y-4">

      <!-- Flash -->
      <div v-if="page.props.flash?.success" class="px-4 py-3 rounded-lg bg-success-50 border border-success-100 text-success-700 text-sm">
        {{ page.props.flash.success }}
      </div>
      <div v-if="page.props.flash?.error" class="px-4 py-3 rounded-lg bg-danger-50 border border-danger-100 text-danger-600 text-sm">
        {{ page.props.flash.error }}
      </div>

      <!-- Back -->
      <Link :href="route('recruitment.applications.index')"
            class="inline-flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-800">
        &larr; Back to Applications
      </Link>

      <!-- Summary Card -->
      <AppCard>
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div class="space-y-1">
            <h1 class="text-xl font-semibold text-slate-800">
              {{ application.applicant?.last_name }}, {{ application.applicant?.first_name }}
            </h1>
            <p class="text-sm text-slate-500">{{ application.applicant?.email }}</p>
            <p class="text-sm text-slate-700 font-medium">
              {{ application.job_vacancy?.job_item?.position_title ?? '—' }}
            </p>
            <p class="text-xs text-slate-400 flex items-center gap-2">
              {{ type.name }} · Applied {{ formatDate(application.application_date) }}
              <AppBadge v-if="application.is_internal" color="indigo">Internal</AppBadge>
            </p>
          </div>
          <div class="flex flex-col items-end gap-2">
            <AppBadge :color="stageColor(application.current_stage)"><span class="capitalize">{{ application.current_stage }}</span></AppBadge>
            <div v-if="application.ranking_summary" class="text-sm text-right">
              <span class="text-slate-500 text-xs">Score: </span>
              <span class="font-bold text-indigo-600">{{ parseFloat(application.ranking_summary.total_score).toFixed(2) }}</span>
              <span v-if="application.ranking_summary.rank" class="ml-2 font-bold text-indigo-600">Rank #{{ application.ranking_summary.rank }}</span>
              <AppBadge v-if="application.ranking_summary.is_recommended" color="green" class="ml-2">Recommended</AppBadge>
            </div>
          </div>
        </div>

        <!-- Stage Transition Buttons -->
        <div v-if="!isTerminal" class="mt-4 pt-4 border-t border-slate-100 flex flex-wrap gap-2">
          <AppButton v-for="toStage in availableTransitions" :key="toStage"
                     @click="advanceTo(toStage)" :disabled="advanceLoading">
            <span class="capitalize">&rarr; {{ toStage }}</span>
          </AppButton>
          <AppButton variant="danger" @click="rejectApp" :disabled="advanceLoading">Reject</AppButton>
          <AppButton variant="secondary" @click="withdrawApp" :disabled="advanceLoading">Withdraw</AppButton>
        </div>

        <div v-if="application.remarks" class="mt-3 text-sm text-slate-500 bg-slate-50 rounded-lg p-3 border border-slate-100">
          <span class="font-medium">Remarks:</span> {{ application.remarks }}
        </div>
      </AppCard>

      <!-- Tabs -->
      <AppTabs :tabs="tabs" v-model="activeTab">

        <!-- ── Overview Tab ──────────────────────────────────────────────────── -->
        <AppCard v-if="activeTab === 'overview'">
          <div class="space-y-6">
            <div>
              <h3 class="text-sm font-semibold text-slate-700 mb-3">Applicant Profile</h3>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-2 text-sm">
                <div>
                  <span class="text-slate-400 text-xs block">CSC Eligibility</span>
                  <span class="text-slate-800">{{ application.applicant?.eligibility ?? '—' }}</span>
                </div>
                <div>
                  <span class="text-slate-400 text-xs block">PRC License No.</span>
                  <span class="text-slate-800">{{ application.applicant?.prc_license_no ?? '—' }}</span>
                </div>
                <div>
                  <span class="text-slate-400 text-xs block">Education</span>
                  <span class="text-slate-800">{{ application.applicant?.course ?? '—' }} ({{ application.applicant?.year_graduated ?? '—' }})</span>
                </div>
                <div>
                  <span class="text-slate-400 text-xs block">School</span>
                  <span class="text-slate-800">{{ application.applicant?.school ?? '—' }}</span>
                </div>
              </div>
            </div>

            <div>
              <h3 class="text-sm font-semibold text-slate-700 mb-3">Submitted Documents</h3>
              <div v-if="application.applicant?.documents?.length" class="space-y-1">
                <div v-for="doc in application.applicant.documents" :key="doc.id"
                     class="flex items-center justify-between text-sm py-1.5 border-b border-slate-100">
                  <span class="text-slate-700">{{ doc.document_type }}</span>
                  <div class="flex items-center gap-2">
                    <a v-if="doc.drive_url" :href="doc.drive_url" target="_blank" rel="noopener"
                       class="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-800 text-xs font-medium">
                      View <ArrowTopRightOnSquareIcon class="h-3.5 w-3.5" />
                    </a>
                    <AppBadge :color="docStatusColor(doc.status)">{{ doc.status }}</AppBadge>
                  </div>
                </div>
              </div>
              <div v-else class="text-slate-400 text-sm">No documents on file.</div>
            </div>
          </div>
        </AppCard>

        <!-- ── Evaluation Tab ────────────────────────────────────────────────── -->
        <AppCard v-if="activeTab === 'evaluation'">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-slate-700">Evaluation Scores</h3>
            <div class="text-sm text-slate-500">
              Computed Total: <span class="font-bold text-indigo-600 text-base">{{ computedTotal }}</span>
            </div>
          </div>

          <div v-if="type.evaluation_criteria?.length" class="space-y-4">
            <div v-for="criterion in type.evaluation_criteria" :key="criterion.id"
                 class="p-4 rounded-lg border border-slate-100 bg-slate-50">
              <div class="flex items-center justify-between mb-2">
                <div>
                  <span class="font-medium text-slate-800 text-sm">{{ criterion.name }}</span>
                  <span class="ml-2 text-xs text-slate-400">Weight: {{ criterion.weight_percentage }}%</span>
                </div>
                <div class="text-xs text-indigo-600 font-medium">
                  Contribution: {{ ((parseFloat(scoreForm[criterion.id]?.score || 0) * criterion.weight_percentage) / 100).toFixed(4) }}
                </div>
              </div>
              <div v-if="criterion.scoring_guide" class="text-xs text-slate-400 mb-2">{{ criterion.scoring_guide }}</div>
              <div class="flex gap-3 items-start">
                <AppInput v-model="scoreForm[criterion.id].score" type="number" min="0" max="100" step="0.01"
                          label="Score (0–100)" class="flex-1" />
                <AppInput v-model="scoreForm[criterion.id].remarks" type="text" label="Remarks" class="flex-1" />
              </div>
            </div>

            <div class="flex justify-end gap-3 pt-2">
              <AppButton :loading="scoreSaving" :disabled="scoreSaving" @click="saveScores">
                {{ scoreSaving ? 'Saving…' : 'Save Scores' }}
              </AppButton>
            </div>
          </div>
          <EmptyState v-else title="No evaluation criteria configured for this recruitment type." />
        </AppCard>

        <!-- ── Interview Tab ──────────────────────────────────────────────────── -->
        <AppCard v-if="activeTab === 'interview'">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-slate-700">Interviews</h3>
            <AppButton v-if="type.has_interview && !isTerminal" size="sm" @click="showInterviewModal = true">
              + Schedule Interview
            </AppButton>
            <span v-else-if="!type.has_interview" class="text-xs text-slate-400">Not applicable for this type</span>
          </div>

          <div v-if="application.interviews?.length" class="space-y-3">
            <div v-for="iv in application.interviews" :key="iv.id"
                 class="p-4 rounded-lg border border-slate-100">
              <div class="flex items-start justify-between">
                <div>
                  <div class="font-medium text-slate-800 text-sm">{{ formatDateTime(iv.interview_date) }}</div>
                  <div class="text-xs text-slate-500 mt-0.5">
                    <span class="capitalize">{{ iv.format }}</span>
                    <span v-if="iv.venue"> · {{ iv.venue }}</span>
                  </div>
                  <div v-if="iv.rating !== null" class="mt-1 text-sm">
                    Rating: <span class="font-bold text-indigo-600">{{ iv.rating }}</span>
                    <span v-if="iv.remarks"> · {{ iv.remarks }}</span>
                  </div>
                </div>
                <div class="flex flex-col items-end gap-2">
                  <AppBadge :color="interviewStatusColor(iv.status)"><span class="capitalize">{{ iv.status }}</span></AppBadge>
                  <AppButton v-if="iv.status === 'scheduled'" variant="ghost" size="sm" @click="openResult(iv)">
                    Record Result
                  </AppButton>
                </div>
              </div>
            </div>
          </div>
          <EmptyState v-else title="No interviews scheduled." />
        </AppCard>

        <!-- ── Ranking Tab ────────────────────────────────────────────────────── -->
        <AppCard v-if="activeTab === 'ranking'">
          <div class="space-y-4">
            <div class="flex items-center justify-between">
              <h3 class="text-sm font-semibold text-slate-700">Ranking Summary</h3>
              <AppButton :loading="rankLoading" :disabled="rankLoading" @click="computeRank">
                {{ rankLoading ? 'Computing…' : 'Compute / Refresh Rank' }}
              </AppButton>
            </div>

            <div v-if="application.ranking_summary" class="grid grid-cols-2 sm:grid-cols-4 gap-4">
              <div class="bg-slate-50 rounded-xl border border-slate-100 p-4 text-center">
                <div class="text-2xl font-bold text-indigo-600">{{ parseFloat(application.ranking_summary.total_score).toFixed(2) }}</div>
                <div class="text-xs text-slate-500 mt-1">Total Score</div>
              </div>
              <div class="bg-slate-50 rounded-xl border border-slate-100 p-4 text-center">
                <div class="text-2xl font-bold text-indigo-600">{{ application.ranking_summary.rank ? '#' + application.ranking_summary.rank : '—' }}</div>
                <div class="text-xs text-slate-500 mt-1">Rank</div>
              </div>
              <div class="bg-slate-50 rounded-xl border border-slate-100 p-4 text-center col-span-2">
                <div class="text-lg font-bold" :class="application.ranking_summary.is_recommended ? 'text-success-700' : 'text-slate-400'">
                  {{ application.ranking_summary.is_recommended ? 'Recommended' : 'Not Recommended' }}
                </div>
                <div class="text-xs text-slate-400 mt-1">Selection Recommendation</div>
              </div>
            </div>

            <div v-if="application.ranking_summary">
              <AppTextarea v-model="deliberationNotes" :rows="3" label="Deliberation Notes"
                           placeholder="Optional notes from the selection board…" />
              <div class="flex justify-between items-center mt-2">
                <AppButton :loading="recommendLoading" :disabled="recommendLoading"
                           :variant="application.ranking_summary?.is_recommended ? 'secondary' : 'success'"
                           @click="toggleRecommend">
                  {{ recommendLoading ? 'Saving…' : (application.ranking_summary?.is_recommended ? 'Remove Recommendation' : 'Mark as Recommended') }}
                </AppButton>
              </div>
            </div>

            <EmptyState v-else title="Scores must be saved before computing ranking. Go to the Evaluation tab first." />
          </div>
        </AppCard>

        <!-- ── Selection Tab ─────────────────────────────────────────────────── -->
        <AppCard v-if="activeTab === 'selection'">
          <div class="space-y-4">
            <h3 class="text-sm font-semibold text-slate-700">Selection &amp; Placement</h3>

            <div v-if="application.selection">
              <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-sm">
                <div>
                  <span class="text-slate-400 text-xs block mb-1">Approval Status</span>
                  <AppBadge :color="approvalStatusColor(application.selection.approval_status)">
                    <span class="capitalize">{{ application.selection.approval_status }}</span>
                  </AppBadge>
                </div>
                <div>
                  <span class="text-slate-400 text-xs block">Approved By</span>
                  <span class="text-slate-800">{{ application.selection.approver?.name ?? '—' }}</span>
                </div>
                <div>
                  <span class="text-slate-400 text-xs block">Approval Date</span>
                  <span class="text-slate-800">{{ formatDate(application.selection.approval_date) }}</span>
                </div>
                <div v-if="application.selection.disapproval_reason" class="col-span-3">
                  <span class="text-slate-400 text-xs block">Reason</span>
                  <span class="text-danger-600 text-sm">{{ application.selection.disapproval_reason }}</span>
                </div>
              </div>
            </div>

            <!-- Approve/Disapprove (only when in selection stage) -->
            <div v-if="application.current_stage === 'selection'" class="space-y-3">
              <PlacementApprovalForm :application="application" />
            </div>

            <EmptyState v-else-if="!application.selection" title="Application must reach the selection stage before approval." />

            <!-- Placement info -->
            <div v-if="application.placement" class="border-t border-slate-100 pt-4">
              <h4 class="text-sm font-semibold text-slate-700 mb-2">Placement</h4>
              <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-sm">
                <div>
                  <span class="text-slate-400 text-xs block">Office</span>
                  <span class="text-slate-800">{{ application.placement.office?.name ?? '—' }}</span>
                </div>
                <div>
                  <span class="text-slate-400 text-xs block">Start Date</span>
                  <span class="text-slate-800">{{ formatDate(application.placement.start_date) }}</span>
                </div>
                <div>
                  <span class="text-slate-400 text-xs block">Status</span>
                  <span class="capitalize font-medium text-slate-800">{{ application.placement.status }}</span>
                </div>
              </div>
              <div class="mt-2">
                <Link :href="route('recruitment.placements.show', application.placement.id)"
                      class="text-sm text-indigo-600 hover:underline">
                  View Onboarding Tasks &rarr;
                </Link>
              </div>
            </div>
          </div>
        </AppCard>
      </AppTabs>
    </div>

    <!-- ── Schedule Interview Modal ─────────────────────────────────────────── -->
    <AppModal :show="showInterviewModal" title="Schedule Interview" @close="showInterviewModal = false">
      <form @submit.prevent="submitInterview" class="space-y-4">
        <AppInput v-model="interviewForm.interview_date" type="datetime-local" label="Date & Time" required
                  :error="interviewErrors.interview_date" />
        <AppSelect v-model="interviewForm.format" label="Format" required :show-blank="false">
          <option value="panel">Panel Interview</option>
          <option value="individual">Individual</option>
          <option value="demo-teaching">Demo Teaching</option>
        </AppSelect>
        <AppInput v-model="interviewForm.venue" type="text" label="Venue" />
      </form>
      <template #footer>
        <AppButton variant="secondary" @click="showInterviewModal = false">Cancel</AppButton>
        <AppButton :loading="interviewLoading" :disabled="interviewLoading" @click="submitInterview">
          {{ interviewLoading ? 'Scheduling…' : 'Schedule' }}
        </AppButton>
      </template>
    </AppModal>

    <!-- ── Record Interview Result Modal ───────────────────────────────────── -->
    <AppModal :show="showResultModal" title="Record Interview Result" size="sm" @close="showResultModal = false">
      <form @submit.prevent="submitResult" class="space-y-4">
        <AppInput v-model="resultForm.rating" type="number" min="0" max="100" step="0.01" label="Rating (0–100)" required />
        <AppTextarea v-model="resultForm.remarks" :rows="3" label="Remarks" />
      </form>
      <template #footer>
        <AppButton variant="secondary" @click="showResultModal = false">Cancel</AppButton>
        <AppButton :loading="resultLoading" :disabled="resultLoading" @click="submitResult">
          {{ resultLoading ? 'Saving…' : 'Save Result' }}
        </AppButton>
      </template>
    </AppModal>
  </AdminLayout>
</template>
