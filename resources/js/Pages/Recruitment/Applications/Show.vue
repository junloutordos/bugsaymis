<script setup>
import { ref, computed } from 'vue'
import { Head, router, usePage, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Swal from 'sweetalert2'
import PlacementApprovalForm from './PlacementApprovalForm.vue'

const props = defineProps({
  application:          { type: Object, required: true },
  availableTransitions: { type: Array,  default: () => [] },
})

const page = usePage()

// ── Active tab ─────────────────────────────────────────────────────────────────
const activeTab = ref('overview')

// ── Stage helpers ──────────────────────────────────────────────────────────────
const stageColors = {
  submitted:  'bg-slate-100 text-slate-600',
  screening:  'bg-amber-50 text-amber-700',
  exam:       'bg-amber-50 text-amber-700',
  interview:  'bg-blue-50 text-blue-700',
  ranking:    'bg-blue-50 text-blue-700',
  selection:  'bg-blue-50 text-blue-700',
  placement:  'bg-emerald-50 text-emerald-700',
  rejected:   'bg-red-50 text-red-600',
  withdrawn:  'bg-slate-100 text-slate-600',
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
  const result = await Swal.fire({
    title: `Move to "${toStage}"?`,
    icon: 'question', showCancelButton: true,
    confirmButtonText: 'Yes', reverseButtons: true,
  })
  if (!result.isConfirmed) return

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
      <div v-if="page.props.flash?.success" class="px-4 py-3 rounded-lg bg-emerald-50 border border-emerald-100 text-emerald-700 text-sm">
        {{ page.props.flash.success }}
      </div>
      <div v-if="page.props.flash?.error" class="px-4 py-3 rounded-lg bg-red-50 border border-red-100 text-red-600 text-sm">
        {{ page.props.flash.error }}
      </div>

      <!-- Back -->
      <Link :href="route('recruitment.applications.index')"
            class="inline-flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-800">
        &larr; Back to Applications
      </Link>

      <!-- Summary Card -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div class="space-y-1">
            <h1 class="text-xl font-semibold text-slate-800">
              {{ application.applicant?.last_name }}, {{ application.applicant?.first_name }}
            </h1>
            <p class="text-sm text-slate-500">{{ application.applicant?.email }}</p>
            <p class="text-sm text-slate-700 font-medium">
              {{ application.job_vacancy?.job_item?.position_title ?? '—' }}
            </p>
            <p class="text-xs text-slate-400">
              {{ type.name }} · Applied {{ formatDate(application.application_date) }}
              <span v-if="application.is_internal" class="ml-2 inline-flex items-center px-1.5 py-0.5 bg-indigo-50 text-indigo-600 rounded text-xs">Internal</span>
            </p>
          </div>
          <div class="flex flex-col items-end gap-2">
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium capitalize" :class="stageColors[application.current_stage]">
              {{ application.current_stage }}
            </span>
            <div v-if="application.ranking_summary" class="text-sm text-right">
              <span class="text-slate-500 text-xs">Score: </span>
              <span class="font-bold text-indigo-600">{{ parseFloat(application.ranking_summary.total_score).toFixed(2) }}</span>
              <span v-if="application.ranking_summary.rank" class="ml-2 font-bold text-indigo-600">Rank #{{ application.ranking_summary.rank }}</span>
              <span v-if="application.ranking_summary.is_recommended" class="ml-2 inline-flex items-center px-1.5 py-0.5 bg-emerald-50 text-emerald-700 rounded text-xs">Recommended</span>
            </div>
          </div>
        </div>

        <!-- Stage Transition Buttons -->
        <div v-if="!isTerminal" class="mt-4 pt-4 border-t border-slate-100 flex flex-wrap gap-2">
          <button v-for="toStage in availableTransitions" :key="toStage"
                  @click="advanceTo(toStage)" :disabled="advanceLoading"
                  class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50 capitalize">
            &rarr; {{ toStage }}
          </button>
          <button @click="rejectApp" :disabled="advanceLoading"
                  class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white px-3 py-1.5 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50">
            Reject
          </button>
          <button @click="withdrawApp" :disabled="advanceLoading"
                  class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50">
            Withdraw
          </button>
        </div>

        <div v-if="application.remarks" class="mt-3 text-sm text-slate-500 bg-slate-50 rounded-lg p-3 border border-slate-100">
          <span class="font-medium">Remarks:</span> {{ application.remarks }}
        </div>
      </div>

      <!-- Tabs -->
      <div class="flex border-b border-slate-200 bg-white rounded-t-xl border border-slate-100 shadow-sm px-4 pt-3 gap-1 overflow-x-auto">
        <button v-for="tab in ['overview', 'evaluation', 'interview', 'ranking', 'selection']"
                :key="tab" @click="activeTab = tab"
                class="px-4 py-2 text-sm font-medium capitalize rounded-t-lg whitespace-nowrap transition"
                :class="activeTab === tab
                  ? 'bg-white border border-b-white border-slate-200 text-indigo-600 -mb-px'
                  : 'text-slate-500 hover:text-slate-700'">
          {{ tab }}
        </button>
      </div>

      <!-- ── Overview Tab ──────────────────────────────────────────────────── -->
      <div v-if="activeTab === 'overview'" class="bg-white rounded-b-xl border border-slate-100 shadow-sm p-6 space-y-6">
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
              <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium"
                    :class="{
                      'bg-emerald-50 text-emerald-700': doc.status === 'verified',
                      'bg-amber-50 text-amber-700':    doc.status === 'pending',
                      'bg-red-50 text-red-600':        doc.status === 'rejected',
                    }">
                {{ doc.status }}
              </span>
            </div>
          </div>
          <div v-else class="text-slate-400 text-sm">No documents on file.</div>
        </div>
      </div>

      <!-- ── Evaluation Tab ────────────────────────────────────────────────── -->
      <div v-else-if="activeTab === 'evaluation'" class="bg-white rounded-b-xl border border-slate-100 shadow-sm p-6">
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
              <div class="flex-1">
                <label class="block text-xs font-medium text-slate-600 mb-1">Score (0–100)</label>
                <input v-model="scoreForm[criterion.id].score" type="number" min="0" max="100" step="0.01"
                       class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
              </div>
              <div class="flex-1">
                <label class="block text-xs font-medium text-slate-600 mb-1">Remarks</label>
                <input v-model="scoreForm[criterion.id].remarks" type="text"
                       class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
              </div>
            </div>
          </div>

          <div class="flex justify-end gap-3 pt-2">
            <button @click="saveScores" :disabled="scoreSaving"
                    class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50">
              {{ scoreSaving ? 'Saving…' : 'Save Scores' }}
            </button>
          </div>
        </div>
        <div v-else class="py-16 text-center text-slate-400 text-sm">
          No evaluation criteria configured for this recruitment type.
        </div>
      </div>

      <!-- ── Interview Tab ──────────────────────────────────────────────────── -->
      <div v-else-if="activeTab === 'interview'" class="bg-white rounded-b-xl border border-slate-100 shadow-sm p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-sm font-semibold text-slate-700">Interviews</h3>
          <button v-if="type.has_interview && !isTerminal"
                  @click="showInterviewModal = true"
                  class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg text-sm font-medium transition-colors shadow-sm">
            + Schedule Interview
          </button>
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
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium capitalize"
                      :class="{
                        'bg-blue-50 text-blue-700':     iv.status === 'scheduled',
                        'bg-emerald-50 text-emerald-700': iv.status === 'completed',
                        'bg-red-50 text-red-600':       iv.status === 'cancelled',
                        'bg-slate-100 text-slate-600':  iv.status === 'no_show',
                      }">
                  {{ iv.status }}
                </span>
                <button v-if="iv.status === 'scheduled'"
                        @click="openResult(iv)"
                        class="text-xs text-indigo-600 hover:underline">
                  Record Result
                </button>
              </div>
            </div>
          </div>
        </div>
        <div v-else class="py-16 text-center text-slate-400 text-sm">No interviews scheduled.</div>
      </div>

      <!-- ── Ranking Tab ────────────────────────────────────────────────────── -->
      <div v-else-if="activeTab === 'ranking'" class="bg-white rounded-b-xl border border-slate-100 shadow-sm p-6 space-y-4">
        <div class="flex items-center justify-between">
          <h3 class="text-sm font-semibold text-slate-700">Ranking Summary</h3>
          <button @click="computeRank" :disabled="rankLoading"
                  class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50">
            {{ rankLoading ? 'Computing…' : 'Compute / Refresh Rank' }}
          </button>
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
            <div class="text-lg font-bold" :class="application.ranking_summary.is_recommended ? 'text-emerald-700' : 'text-slate-400'">
              {{ application.ranking_summary.is_recommended ? 'Recommended' : 'Not Recommended' }}
            </div>
            <div class="text-xs text-slate-400 mt-1">Selection Recommendation</div>
          </div>
        </div>

        <div v-if="application.ranking_summary">
          <label class="block text-xs font-medium text-slate-600 mb-1">Deliberation Notes</label>
          <textarea v-model="deliberationNotes" rows="3"
                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"
                    placeholder="Optional notes from the selection board…"></textarea>
          <div class="flex justify-between items-center mt-2">
            <button @click="toggleRecommend" :disabled="recommendLoading"
                    class="inline-flex items-center gap-2 px-4 py-2 text-sm rounded-lg font-medium disabled:opacity-50 transition-colors shadow-sm"
                    :class="application.ranking_summary?.is_recommended
                      ? 'bg-white border border-slate-200 hover:bg-slate-50 text-slate-700'
                      : 'bg-emerald-600 hover:bg-emerald-700 text-white'">
              {{ recommendLoading ? 'Saving…' : (application.ranking_summary?.is_recommended ? 'Remove Recommendation' : 'Mark as Recommended') }}
            </button>
          </div>
        </div>

        <div v-else class="py-16 text-center text-slate-400 text-sm">
          Scores must be saved before computing ranking. Go to the Evaluation tab first.
        </div>
      </div>

      <!-- ── Selection Tab ─────────────────────────────────────────────────── -->
      <div v-else-if="activeTab === 'selection'" class="bg-white rounded-b-xl border border-slate-100 shadow-sm p-6 space-y-4">
        <h3 class="text-sm font-semibold text-slate-700">Selection &amp; Placement</h3>

        <div v-if="application.selection">
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-sm">
            <div>
              <span class="text-slate-400 text-xs block">Approval Status</span>
              <span class="font-medium capitalize"
                    :class="{
                      'text-emerald-700': application.selection.approval_status === 'approved',
                      'text-red-600':     application.selection.approval_status === 'disapproved',
                      'text-amber-600':   application.selection.approval_status === 'pending',
                    }">
                {{ application.selection.approval_status }}
              </span>
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
              <span class="text-red-600 text-sm">{{ application.selection.disapproval_reason }}</span>
            </div>
          </div>
        </div>

        <!-- Approve/Disapprove (only when in selection stage) -->
        <div v-if="application.current_stage === 'selection'" class="space-y-3">
          <PlacementApprovalForm :application="application" />
        </div>

        <div v-else-if="!application.selection" class="py-16 text-center text-slate-400 text-sm">
          Application must reach the <strong>selection</strong> stage before approval.
        </div>

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
    </div>

    <!-- ── Schedule Interview Modal ─────────────────────────────────────────── -->
    <div v-if="showInterviewModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4">
      <div class="bg-white rounded-2xl w-full max-w-md shadow-xl relative">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
          <h2 class="text-base font-semibold text-slate-800">Schedule Interview</h2>
          <button @click="showInterviewModal = false" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors">✕</button>
        </div>

        <form @submit.prevent="submitInterview" class="px-6 py-5 space-y-4">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Date &amp; Time *</label>
            <input v-model="interviewForm.interview_date" type="datetime-local" required
                   class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
            <p v-if="interviewErrors.interview_date" class="text-red-500 text-xs mt-1">{{ interviewErrors.interview_date }}</p>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Format *</label>
            <select v-model="interviewForm.format" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
              <option value="panel">Panel Interview</option>
              <option value="individual">Individual</option>
              <option value="demo-teaching">Demo Teaching</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Venue</label>
            <input v-model="interviewForm.venue" type="text"
                   class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
          </div>
          <div class="flex justify-end gap-3 pt-2 border-t border-slate-100">
            <button type="button" @click="showInterviewModal = false"
                    class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">Cancel</button>
            <button type="submit" :disabled="interviewLoading"
                    class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50">
              {{ interviewLoading ? 'Scheduling…' : 'Schedule' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- ── Record Interview Result Modal ───────────────────────────────────── -->
    <div v-if="showResultModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4">
      <div class="bg-white rounded-2xl w-full max-w-sm shadow-xl relative">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
          <h2 class="text-base font-semibold text-slate-800">Record Interview Result</h2>
          <button @click="showResultModal = false" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors">✕</button>
        </div>

        <form @submit.prevent="submitResult" class="px-6 py-5 space-y-4">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Rating (0–100) *</label>
            <input v-model="resultForm.rating" type="number" min="0" max="100" step="0.01" required
                   class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Remarks</label>
            <textarea v-model="resultForm.remarks" rows="3"
                      class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"></textarea>
          </div>
          <div class="flex justify-end gap-3 pt-2 border-t border-slate-100">
            <button type="button" @click="showResultModal = false"
                    class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">Cancel</button>
            <button type="submit" :disabled="resultLoading"
                    class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50">
              {{ resultLoading ? 'Saving…' : 'Save Result' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </AdminLayout>
</template>
