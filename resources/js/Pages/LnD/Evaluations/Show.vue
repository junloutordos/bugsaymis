<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppCard from '@/Components/AppCard.vue'
import AppButton from '@/Components/AppButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import Swal from 'sweetalert2'

const props = defineProps({
  participant: { type: Object, required: true },
  evaluation:  { type: Object, required: true },
})

// ── Rating helpers ─────────────────────────────────────────────────────────────
const ratingLabels = { 1: 'Poor', 2: 'Unsatisfactory', 3: 'Satisfactory', 4: 'Very Satisfactory', 5: 'Outstanding' }
const ratingColors = {
  1: 'bg-red-500',
  2: 'bg-orange-400',
  3: 'bg-yellow-400',
  4: 'bg-blue-500',
  5: 'bg-green-500',
}
const overallColor = (avg) => {
  if (!avg) return 'text-gray-400'
  if (avg >= 4.5) return 'text-green-600'
  if (avg >= 3.5) return 'text-blue-600'
  if (avg >= 2.5) return 'text-yellow-600'
  return 'text-red-600'
}
const overallLabel = (avg) => {
  if (!avg) return 'Not Rated'
  if (avg >= 4.5) return 'Outstanding'
  if (avg >= 3.5) return 'Very Satisfactory'
  if (avg >= 2.5) return 'Satisfactory'
  if (avg >= 1.5) return 'Unsatisfactory'
  return 'Poor'
}

const fmt = (d) => d ? new Date(d).toLocaleDateString('en-PH', { month: 'long', day: 'numeric', year: 'numeric' }) : '—'

const breadcrumb = computed(() => [
  { label: 'Sessions', href: route('lnd.sessions.index') },
  { label: fmt(props.participant.session?.session_date), href: route('lnd.sessions.show', props.participant.session_id) },
  { label: 'Evaluation' },
])

// ── Level 1 form ──────────────────────────────────────────────────────────────
const l1Form = ref({
  reaction_score:     props.evaluation.reaction_score     ?? null,
  relevance_score:    props.evaluation.relevance_score    ?? null,
  facilitation_score: props.evaluation.facilitation_score ?? null,
  logistics_score:    props.evaluation.logistics_score    ?? null,
  reaction_feedback:  props.evaluation.reaction_feedback  ?? '',
})
const l1Saving = ref(false)

const l1Average = computed(() => {
  const scores = [l1Form.value.reaction_score, l1Form.value.relevance_score,
    l1Form.value.facilitation_score, l1Form.value.logistics_score].filter(Boolean)
  if (!scores.length) return null
  return (scores.reduce((a, b) => a + b, 0) / scores.length).toFixed(2)
})

const saveL1 = () => {
  if ([l1Form.value.reaction_score, l1Form.value.relevance_score,
       l1Form.value.facilitation_score, l1Form.value.logistics_score].some(s => !s)) {
    Swal.fire({ icon: 'warning', title: 'All 4 scores required for Level 1', timer: 2000, showConfirmButton: false })
    return
  }
  l1Saving.value = true
  router.post(route('lnd.evaluations.reaction', props.participant.id), l1Form.value, {
    preserveState: true,
    onSuccess: () => Swal.fire({ icon: 'success', title: 'Level 1 saved', timer: 1400, showConfirmButton: false }),
    onError: (errs) => Swal.fire({ icon: 'error', title: 'Error', text: Object.values(errs)[0] }),
    onFinish: () => { l1Saving.value = false },
  })
}

// ── Level 2 form ──────────────────────────────────────────────────────────────
const l2Form = ref({
  learning_score:    props.evaluation.learning_score    ?? null,
  learning_feedback: props.evaluation.learning_feedback ?? '',
})
const l2Saving = ref(false)

const saveL2 = () => {
  if (!l2Form.value.learning_score) {
    Swal.fire({ icon: 'warning', title: 'Learning score is required', timer: 2000, showConfirmButton: false })
    return
  }
  l2Saving.value = true
  router.post(route('lnd.evaluations.learning', props.participant.id), l2Form.value, {
    preserveState: true,
    onSuccess: () => Swal.fire({ icon: 'success', title: 'Level 2 saved', timer: 1400, showConfirmButton: false }),
    onError: (errs) => Swal.fire({ icon: 'error', title: 'Error', text: Object.values(errs)[0] }),
    onFinish: () => { l2Saving.value = false },
  })
}

// ── Level 3 form ──────────────────────────────────────────────────────────────
const l3Form = ref({
  behavior_score:         props.evaluation.behavior_score         ?? null,
  behavior_assessed_date: props.evaluation.behavior_assessed_date
    ? props.evaluation.behavior_assessed_date.substring(0, 10) : '',
  behavior_feedback: props.evaluation.behavior_feedback ?? '',
})
const l3Saving = ref(false)

const saveL3 = () => {
  if (!l3Form.value.behavior_score || !l3Form.value.behavior_assessed_date) {
    Swal.fire({ icon: 'warning', title: 'Score and assessment date are required', timer: 2000, showConfirmButton: false })
    return
  }
  l3Saving.value = true
  router.post(route('lnd.evaluations.behavior', props.participant.id), l3Form.value, {
    preserveState: true,
    onSuccess: () => Swal.fire({ icon: 'success', title: 'Level 3 saved', timer: 1400, showConfirmButton: false }),
    onError: (errs) => Swal.fire({ icon: 'error', title: 'Error', text: Object.values(errs)[0] }),
    onFinish: () => { l3Saving.value = false },
  })
}

// ── Level 4 form ──────────────────────────────────────────────────────────────
const l4Form = ref({
  results_score:         props.evaluation.results_score         ?? null,
  results_assessed_date: props.evaluation.results_assessed_date
    ? props.evaluation.results_assessed_date.substring(0, 10) : '',
  results_feedback: props.evaluation.results_feedback ?? '',
})
const l4Saving = ref(false)

const saveL4 = () => {
  if (!l4Form.value.results_score || !l4Form.value.results_assessed_date) {
    Swal.fire({ icon: 'warning', title: 'Score and assessment date are required', timer: 2000, showConfirmButton: false })
    return
  }
  l4Saving.value = true
  router.post(route('lnd.evaluations.results', props.participant.id), l4Form.value, {
    preserveState: true,
    onSuccess: () => Swal.fire({ icon: 'success', title: 'Level 4 saved', timer: 1400, showConfirmButton: false }),
    onError: (errs) => Swal.fire({ icon: 'error', title: 'Error', text: Object.values(errs)[0] }),
    onFinish: () => { l4Saving.value = false },
  })
}

// ── General feedback form ──────────────────────────────────────────────────────
const fbForm = ref({
  feedback:        props.evaluation.feedback        ?? '',
  recommendations: props.evaluation.recommendations ?? '',
})
const fbSaving = ref(false)

const saveFeedback = () => {
  fbSaving.value = true
  router.patch(route('lnd.evaluations.feedback', props.participant.id), fbForm.value, {
    preserveState: true,
    onSuccess: () => Swal.fire({ icon: 'success', title: 'Feedback saved', timer: 1400, showConfirmButton: false }),
    onError: (errs) => Swal.fire({ icon: 'error', title: 'Error', text: Object.values(errs)[0] }),
    onFinish: () => { fbSaving.value = false },
  })
}

// ── Overall average ────────────────────────────────────────────────────────────
const computedOverall = computed(() => {
  const scores = [
    l1Average.value ? parseFloat(l1Average.value) : null,
    l2Form.value.learning_score,
    l3Form.value.behavior_score,
    l4Form.value.results_score,
  ].filter(Boolean)
  if (!scores.length) return null
  return (scores.reduce((a, b) => a + b, 0) / scores.length).toFixed(2)
})

// ── Star rating component helper ───────────────────────────────────────────────
const setScore = (formObj, field, val) => { formObj[field] = val }
</script>

<template>
  <Head :title="`Evaluation · ${participant.employee?.name}`" />
  <AdminLayout :title="`Evaluation — ${participant.employee?.name}`">
    <div class="space-y-6">

      <AppPageHeader title="Evaluation" :breadcrumb="breadcrumb" />

      <!-- Participant info + overall score -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <AppCard class="md:col-span-2" title="Participant">
          <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
            <dt class="text-slate-500">Name</dt>
            <dd class="font-medium text-slate-800">{{ participant.employee?.name }}</dd>
            <dt class="text-slate-500">Program</dt>
            <dd class="text-slate-700">{{ participant.session?.program?.title ?? '—' }}</dd>
            <dt class="text-slate-500">Session Date</dt>
            <dd class="text-slate-700">{{ fmt(participant.session?.session_date) }}</dd>
            <dt class="text-slate-500">Attendance</dt>
            <dd>
              <AppBadge :color="participant.attendance_status === 'attended' ? 'green' : 'slate'" class="capitalize">
                {{ participant.attendance_status }}
              </AppBadge>
            </dd>
          </dl>
        </AppCard>

        <!-- Overall Score Card -->
        <AppCard class="flex flex-col items-center justify-center text-center" :class="computedOverall ? 'border-indigo-200' : ''">
          <div class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1">Overall Average</div>
          <div :class="['text-5xl font-bold', overallColor(computedOverall)]">
            {{ computedOverall ?? '—' }}
          </div>
          <div :class="['mt-1 text-sm font-medium', overallColor(computedOverall)]">
            {{ overallLabel(computedOverall) }}
          </div>
          <div class="mt-3 grid grid-cols-4 gap-2 w-full text-center">
            <div v-for="(label, lvl) in { 'L1': l1Average, 'L2': l2Form.learning_score, 'L3': l3Form.behavior_score, 'L4': l4Form.results_score }"
              :key="lvl" class="rounded-lg bg-slate-50 p-2">
              <div class="text-xs text-slate-500">{{ lvl }}</div>
              <div class="text-sm font-bold" :class="lvl ? overallColor(parseFloat(label)) : 'text-slate-400'">
                {{ label ?? '—' }}
              </div>
            </div>
          </div>
        </AppCard>
      </div>

      <!-- Level 1: Reaction -->
      <AppCard :padded="false">
        <div class="flex items-center gap-3 border-b border-slate-100 bg-purple-50 px-5 py-4 rounded-t-xl">
          <span class="flex h-8 w-8 items-center justify-center rounded-full bg-purple-600 text-xs font-bold text-white">1</span>
          <div>
            <h3 class="font-semibold text-slate-800">Level 1 — Reaction</h3>
            <p class="text-xs text-slate-500">How did participants react to the training?</p>
          </div>
          <div v-if="l1Average" class="ml-auto text-right">
            <div class="text-xs text-slate-500">L1 Average</div>
            <div :class="['text-lg font-bold', overallColor(parseFloat(l1Average))]">{{ l1Average }}</div>
          </div>
        </div>
        <div class="p-5 space-y-5">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div v-for="({ label, field }) in [
              { label: 'Overall Reaction', field: 'reaction_score' },
              { label: 'Relevance to Work', field: 'relevance_score' },
              { label: 'Facilitation Quality', field: 'facilitation_score' },
              { label: 'Logistics & Venue', field: 'logistics_score' },
            ]" :key="field">
              <label class="block text-xs font-medium text-slate-600 mb-2">{{ label }} <span class="text-danger-600">*</span></label>
              <div class="flex gap-2">
                <button v-for="n in 5" :key="n"
                  type="button"
                  @click="setScore(l1Form, field, n)"
                  :class="[
                    'flex-1 rounded-lg py-2 text-xs font-semibold border-2 transition',
                    l1Form[field] === n
                      ? `${ratingColors[n]} text-white border-transparent`
                      : 'bg-white text-slate-500 border-slate-200 hover:border-slate-400'
                  ]">
                  {{ n }}
                </button>
              </div>
              <div class="mt-1 text-xs text-slate-400 text-right">
                {{ l1Form[field] ? ratingLabels[l1Form[field]] : 'Not rated' }}
              </div>
            </div>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Feedback / Comments</label>
            <textarea v-model="l1Form.reaction_feedback" rows="3"
              class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none" />
          </div>
          <div class="flex justify-end">
            <AppButton :loading="l1Saving" @click="saveL1">Save Level 1</AppButton>
          </div>
        </div>
      </AppCard>

      <!-- Level 2: Learning -->
      <AppCard :padded="false">
        <div class="flex items-center gap-3 border-b border-slate-100 bg-blue-50 px-5 py-4 rounded-t-xl">
          <span class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-600 text-xs font-bold text-white">2</span>
          <div>
            <h3 class="font-semibold text-slate-800">Level 2 — Learning</h3>
            <p class="text-xs text-slate-500">Did participants acquire new knowledge, skills, or attitudes?</p>
          </div>
          <div v-if="l2Form.learning_score" class="ml-auto text-right">
            <div class="text-xs text-slate-500">Score</div>
            <div :class="['text-lg font-bold', overallColor(l2Form.learning_score)]">{{ l2Form.learning_score }}</div>
          </div>
        </div>
        <div class="p-5 space-y-4">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-2">Learning Score <span class="text-danger-600">*</span></label>
            <div class="flex gap-2 max-w-sm">
              <button v-for="n in 5" :key="n"
                type="button"
                @click="l2Form.learning_score = n"
                :class="[
                  'flex-1 rounded-lg py-3 text-sm font-semibold border-2 transition',
                  l2Form.learning_score === n
                    ? `${ratingColors[n]} text-white border-transparent`
                    : 'bg-white text-slate-500 border-slate-200 hover:border-slate-400'
                ]">
                {{ n }}
              </button>
            </div>
            <div class="mt-1 text-xs text-slate-400">
              {{ l2Form.learning_score ? ratingLabels[l2Form.learning_score] : 'Not rated' }}
            </div>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Feedback / Comments</label>
            <textarea v-model="l2Form.learning_feedback" rows="3"
              class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none" />
          </div>
          <div class="flex justify-end">
            <AppButton :loading="l2Saving" @click="saveL2">Save Level 2</AppButton>
          </div>
        </div>
      </AppCard>

      <!-- Level 3: Behavior -->
      <AppCard :padded="false">
        <div class="flex items-center gap-3 border-b border-slate-100 bg-amber-50 px-5 py-4 rounded-t-xl">
          <span class="flex h-8 w-8 items-center justify-center rounded-full bg-amber-500 text-xs font-bold text-white">3</span>
          <div>
            <h3 class="font-semibold text-slate-800">Level 3 — Behavior</h3>
            <p class="text-xs text-slate-500">Are participants applying what they learned on the job?</p>
          </div>
          <div v-if="l3Form.behavior_score" class="ml-auto text-right">
            <div class="text-xs text-slate-500">Score</div>
            <div :class="['text-lg font-bold', overallColor(l3Form.behavior_score)]">{{ l3Form.behavior_score }}</div>
          </div>
        </div>
        <div class="p-5 space-y-4">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-2">Behavior Score <span class="text-danger-600">*</span></label>
              <div class="flex gap-2">
                <button v-for="n in 5" :key="n"
                  type="button"
                  @click="l3Form.behavior_score = n"
                  :class="[
                    'flex-1 rounded-lg py-2 text-xs font-semibold border-2 transition',
                    l3Form.behavior_score === n
                      ? `${ratingColors[n]} text-white border-transparent`
                      : 'bg-white text-slate-500 border-slate-200 hover:border-slate-400'
                  ]">
                  {{ n }}
                </button>
              </div>
              <div class="mt-1 text-xs text-slate-400">
                {{ l3Form.behavior_score ? ratingLabels[l3Form.behavior_score] : 'Not rated' }}
              </div>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Assessment Date <span class="text-danger-600">*</span></label>
              <input v-model="l3Form.behavior_assessed_date" type="date"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              <div v-if="evaluation.behavior_assessed_by" class="mt-1 text-xs text-slate-400">
                Assessed by: {{ evaluation.behavior_assessor?.name }}
              </div>
            </div>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Feedback / Comments</label>
            <textarea v-model="l3Form.behavior_feedback" rows="3"
              class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none" />
          </div>
          <div class="flex justify-end">
            <AppButton :loading="l3Saving" @click="saveL3">Save Level 3</AppButton>
          </div>
        </div>
      </AppCard>

      <!-- Level 4: Results -->
      <AppCard :padded="false">
        <div class="flex items-center gap-3 border-b border-slate-100 bg-emerald-50 px-5 py-4 rounded-t-xl">
          <span class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-600 text-xs font-bold text-white">4</span>
          <div>
            <h3 class="font-semibold text-slate-800">Level 4 — Results</h3>
            <p class="text-xs text-slate-500">What organizational outcomes resulted from the training?</p>
          </div>
          <div v-if="l4Form.results_score" class="ml-auto text-right">
            <div class="text-xs text-slate-500">Score</div>
            <div :class="['text-lg font-bold', overallColor(l4Form.results_score)]">{{ l4Form.results_score }}</div>
          </div>
        </div>
        <div class="p-5 space-y-4">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-2">Results Score <span class="text-danger-600">*</span></label>
              <div class="flex gap-2">
                <button v-for="n in 5" :key="n"
                  type="button"
                  @click="l4Form.results_score = n"
                  :class="[
                    'flex-1 rounded-lg py-2 text-xs font-semibold border-2 transition',
                    l4Form.results_score === n
                      ? `${ratingColors[n]} text-white border-transparent`
                      : 'bg-white text-slate-500 border-slate-200 hover:border-slate-400'
                  ]">
                  {{ n }}
                </button>
              </div>
              <div class="mt-1 text-xs text-slate-400">
                {{ l4Form.results_score ? ratingLabels[l4Form.results_score] : 'Not rated' }}
              </div>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Assessment Date <span class="text-danger-600">*</span></label>
              <input v-model="l4Form.results_assessed_date" type="date"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              <div v-if="evaluation.results_assessed_by" class="mt-1 text-xs text-slate-400">
                Assessed by: {{ evaluation.results_assessor?.name }}
              </div>
            </div>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Feedback / Comments</label>
            <textarea v-model="l4Form.results_feedback" rows="3"
              class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none" />
          </div>
          <div class="flex justify-end">
            <AppButton :loading="l4Saving" @click="saveL4">Save Level 4</AppButton>
          </div>
        </div>
      </AppCard>

      <!-- General Feedback -->
      <AppCard title="General Feedback & Recommendations">
        <div class="space-y-4">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Overall Feedback</label>
            <textarea v-model="fbForm.feedback" rows="3"
              class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none" />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Recommendations</label>
            <textarea v-model="fbForm.recommendations" rows="3"
              class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none" />
          </div>
          <div class="flex justify-end">
            <AppButton :loading="fbSaving" @click="saveFeedback">Save Feedback</AppButton>
          </div>
        </div>
      </AppCard>

    </div>
  </AdminLayout>
</template>
