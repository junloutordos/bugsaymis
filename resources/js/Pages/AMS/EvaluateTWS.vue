<script setup>
import { computed } from 'vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'
import AppInput from '@/Components/AppInput.vue'
import AppTextarea from '@/Components/AppTextarea.vue'
import AppButton from '@/Components/AppButton.vue'
import { CheckBadgeIcon, ClipboardDocumentListIcon, MicrophoneIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  activity:         Object,
  speakers:         Array,
  participant:      Object,
  hash:             { type: String, default: null },
  qrToken:          { type: String, default: null },
  walkin:           { type: Boolean, default: false },
  alreadyEvaluated: Boolean,
  evaluationClosed: { type: Boolean, default: false },
})

const page        = usePage()
const flashSuccess = computed(() => page.props.flash?.success)

function formatDate(d) {
  if (!d) return '—'
  return new Date(d + 'T00:00:00').toLocaleDateString('en-PH', {
    year: 'numeric', month: 'long', day: 'numeric',
  })
}

// ── Scales ───────────────────────────────────────────────────────────────────

const agreeFull = [
  { value: 'strongly_agree',    label: 'Strongly Agree' },
  { value: 'agree',             label: 'Agree' },
  { value: 'neutral',           label: 'Neutral' },
  { value: 'disagree',          label: 'Disagree' },
  { value: 'strongly_disagree', label: 'Strongly Disagree' },
  { value: 'not_applicable',    label: 'Not Applicable' },
]
const overallScale = agreeFull.slice(0, 5)   // excludes Not Applicable

const satisfactionFull = [
  { value: 'very_satisfied',    label: 'Very Satisfied' },
  { value: 'satisfied',         label: 'Satisfied' },
  { value: 'neutral',           label: 'Neutral' },
  { value: 'dissatisfied',      label: 'Dissatisfied' },
  { value: 'very_dissatisfied', label: 'Very Dissatisfied' },
  { value: 'not_applicable',    label: 'Not Applicable' },
]

const topicScale = [
  { value: 'excellent',    label: 'Excellent' },
  { value: 'satisfactory', label: 'Satisfactory' },
  { value: 'low',          label: 'Low' },
]

// ── Sections & questions ─────────────────────────────────────────────────────

const sectionA = [
  { field: 'content_1', text: 'The training/workshop/activity objectives were clearly stated.' },
  { field: 'content_2', text: 'Information was clear and well organized.' },
  { field: 'content_3', text: 'Examples/exercises reinforced training/workshop/activity objectives.' },
  { field: 'content_4', text: 'Audio/Visuals reinforced program content.' },
  { field: 'content_5', text: 'Content was relevant to my needs/functions.' },
]

const sectionB = [
  { field: 'mgmt_length_of_program',   text: 'Length of the program.' },
  { field: 'mgmt_schedule',            text: 'Schedule of activities.' },
  { field: 'mgmt_secretariat_support', text: 'Secretariat & support staff, if any.' },
  { field: 'mgmt_venue',               text: 'Venue of training/workshop/activity (sound system, etc).' },
  { field: 'mgmt_accommodation',       text: 'Hotel accommodation/lodging.' },
  { field: 'mgmt_food_meals',          text: 'Food/meals served.' },
]

const sectionC = [
  { field: 'overall_1_objectives_accomplished', text: 'Training/workshop/activity objectives were accomplished.' },
  { field: 'overall_2_knowledge_increased',     text: 'This training/workshop/activity has increased my knowledge and capabilities.' },
]

const speakerPartI = [
  { field: 'topic_depth_of_content',          text: 'Depth of Content' },
  { field: 'topic_scope_coverage',            text: 'Scope/Coverage' },
  { field: 'topic_relevance_appropriateness', text: 'Relevance/Appropriateness' },
]

const speakerPartII = [
  { field: 'attainment_of_objectives',              text: 'Attainment of Topic Objectives' },
  { field: 'mastery_1_command_of_subject',          text: 'Demonstrated command of subject matter' },
  { field: 'mastery_2_pace_timing',                 text: 'Maintained comfortable pace and timing' },
  { field: 'mastery_3_theory_application_balance',  text: 'Balanced the theories/principles with applications' },
  { field: 'mastery_4_current_trends',              text: 'Presented current trends/developments related to the topic' },
  { field: 'presentation_1_listened',               text: 'Listened to what I and other attendees had to say' },
  { field: 'presentation_2_answered_questions',     text: 'Answered questions effectively' },
  { field: 'presentation_3_inspired_participation', text: 'Inspired participation of attendees' },
  { field: 'presentation_4_held_interest',          text: 'Held my interest in the topic' },
  { field: 'acceptability_as_speaker',              text: 'Acceptability of the Speaker as Resource Person' },
]

// ── Form ─────────────────────────────────────────────────────────────────────

const form = useForm({
  evaluator_name: '',
  sex: '',
  position_function: '',
  content_1: '', content_2: '', content_3: '', content_4: '', content_5: '',
  mgmt_length_of_program: '', mgmt_schedule: '', mgmt_secretariat_support: '',
  mgmt_venue: '', mgmt_accommodation: '', mgmt_food_meals: '',
  overall_1_objectives_accomplished: '', overall_2_knowledge_increased: '',
  suggestions: '',
  other_comments: '',
  speakers: props.speakers.map(s => ({
    speaker_id: s.id,
    topic_depth_of_content: '', topic_scope_coverage: '', topic_relevance_appropriateness: '',
    attainment_of_objectives: '',
    mastery_1_command_of_subject: '', mastery_2_pace_timing: '',
    mastery_3_theory_application_balance: '', mastery_4_current_trends: '',
    presentation_1_listened: '', presentation_2_answered_questions: '',
    presentation_3_inspired_participation: '', presentation_4_held_interest: '',
    acceptability_as_speaker: '',
    effectiveness_reason: '', improvement_suggestions: '', other_topics_wanted: '',
  })),
})

function submit() {
  const url = props.walkin
    ? route('ams.activities.evaluate.walkin.store', [props.activity.id, props.qrToken])
    : route('ams.activities.evaluate.store', [props.activity.id, props.hash])
  form.post(url, { preserveScroll: true })
}
</script>

<template>
  <Head :title="`Evaluate — ${activity.title}`" />

  <div class="min-h-screen bg-gradient-to-br from-slate-50 to-indigo-50 py-10 px-4">
    <div class="max-w-2xl mx-auto space-y-6">

      <!-- Header card -->
      <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="bg-gradient-to-r from-indigo-600 to-indigo-500 px-6 py-5 text-white">
          <div class="flex items-center gap-3 mb-1">
            <ClipboardDocumentListIcon class="w-6 h-6 opacity-80" />
            <span class="text-sm font-medium opacity-80 uppercase tracking-wide">Training/Workshop/Activity Evaluation</span>
          </div>
          <h1 class="text-xl font-bold leading-snug">{{ activity.title }}</h1>
        </div>
        <div class="px-6 py-4 flex flex-wrap gap-x-6 gap-y-1 text-sm text-slate-600">
          <span>
            <span class="font-medium text-slate-500">Date:</span>
            {{ formatDate(activity.start_date) }}
            <template v-if="activity.end_date && activity.end_date !== activity.start_date">
              – {{ formatDate(activity.end_date) }}
            </template>
          </span>
          <span v-if="activity.venue">
            <span class="font-medium text-slate-500">Venue:</span> {{ activity.venue }}
          </span>
        </div>
      </div>

      <!-- Already evaluated -->
      <div v-if="alreadyEvaluated || flashSuccess"
           class="bg-white rounded-2xl shadow-sm border border-success-100 p-8 text-center">
        <div class="flex justify-center mb-4">
          <div class="w-16 h-16 rounded-full bg-success-100 flex items-center justify-center">
            <CheckBadgeIcon class="w-9 h-9 text-success-600" />
          </div>
        </div>
        <h2 class="text-lg font-semibold text-slate-800 mb-1">Evaluation Submitted</h2>
        <p class="text-sm text-slate-500">
          {{ flashSuccess ?? 'You have already submitted an evaluation for this activity. Thank you!' }}
        </p>
      </div>

      <!-- Evaluation period closed -->
      <div v-else-if="evaluationClosed"
           class="bg-white rounded-2xl shadow-sm border border-slate-200 p-8 text-center">
        <h2 class="text-lg font-semibold text-slate-800 mb-1">Evaluation Period Closed</h2>
        <p class="text-sm text-slate-500">
          The evaluation period for this activity has closed. Evaluations are no longer being accepted.
        </p>
      </div>

      <!-- Evaluation form -->
      <form v-else @submit.prevent="submit" class="space-y-6">

        <!-- Evaluator info -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 px-6 py-5 space-y-4">
          <AppInput v-model="form.evaluator_name"
                    type="text"
                    label="Name of Evaluator (Optional)"
                    placeholder="Leave blank to stay anonymous" />
          <AppInput v-model="form.position_function"
                    type="text"
                    label="Function / Position (Optional)"
                    placeholder="e.g. Faculty, HR Officer…" />

          <!-- Sex at Birth (walk-in only — registered participants resolve this from their profile) -->
          <div v-if="walkin">
            <p class="text-sm font-medium text-slate-700 mb-3">Sex at Birth</p>
            <div class="flex flex-wrap gap-2">
              <label v-for="opt in [{ value: 'male', label: 'Male' }, { value: 'female', label: 'Female' }]"
                     :key="opt.value"
                     class="flex items-center gap-1.5 cursor-pointer text-sm px-3 py-1.5 rounded-full border transition-colors"
                     :class="form.sex === opt.value
                       ? 'bg-indigo-600 text-white border-indigo-600'
                       : 'border-slate-200 text-slate-600 hover:border-indigo-300 hover:text-indigo-600'">
                <input type="radio" name="sex" :value="opt.value" v-model="form.sex" class="sr-only" />
                {{ opt.label }}
              </label>
            </div>
            <p v-if="form.errors.sex" class="text-xs text-red-500 mt-1.5">{{ form.errors.sex }}</p>
          </div>
        </div>

        <!-- Section A — Content -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
          <div class="bg-indigo-50 border-b border-indigo-100 px-6 py-3">
            <h2 class="text-sm font-semibold text-indigo-700 uppercase tracking-wide">A — Training/Workshop/Activity Content</h2>
          </div>
          <div class="divide-y divide-slate-50">
            <div v-for="(q, i) in sectionA" :key="q.field" class="px-6 py-4">
              <p class="text-sm text-slate-700 mb-3">
                <span class="font-semibold text-slate-500 mr-1.5">{{ i + 1 }}.</span>{{ q.text }}
              </p>
              <div class="flex flex-wrap gap-2">
                <label v-for="opt in agreeFull" :key="opt.value"
                       class="flex items-center gap-1.5 cursor-pointer text-sm px-3 py-1.5 rounded-full border transition-colors"
                       :class="form[q.field] === opt.value
                         ? 'bg-indigo-600 text-white border-indigo-600'
                         : 'border-slate-200 text-slate-600 hover:border-indigo-300 hover:text-indigo-600'">
                  <input type="radio" :name="q.field" :value="opt.value" v-model="form[q.field]" class="sr-only" />
                  {{ opt.label }}
                </label>
              </div>
              <p v-if="form.errors[q.field]" class="text-xs text-red-500 mt-1.5">{{ form.errors[q.field] }}</p>
            </div>
          </div>
        </div>

        <!-- Section B — Management -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
          <div class="bg-indigo-50 border-b border-indigo-100 px-6 py-3">
            <h2 class="text-sm font-semibold text-indigo-700 uppercase tracking-wide">B — Management of Training/Workshop/Activity</h2>
          </div>
          <div class="divide-y divide-slate-50">
            <div v-for="(q, i) in sectionB" :key="q.field" class="px-6 py-4">
              <p class="text-sm text-slate-700 mb-3">
                <span class="font-semibold text-slate-500 mr-1.5">{{ i + 1 }}.</span>{{ q.text }}
              </p>
              <div class="flex flex-wrap gap-2">
                <label v-for="opt in satisfactionFull" :key="opt.value"
                       class="flex items-center gap-1.5 cursor-pointer text-sm px-3 py-1.5 rounded-full border transition-colors"
                       :class="form[q.field] === opt.value
                         ? 'bg-indigo-600 text-white border-indigo-600'
                         : 'border-slate-200 text-slate-600 hover:border-indigo-300 hover:text-indigo-600'">
                  <input type="radio" :name="q.field" :value="opt.value" v-model="form[q.field]" class="sr-only" />
                  {{ opt.label }}
                </label>
              </div>
              <p v-if="form.errors[q.field]" class="text-xs text-red-500 mt-1.5">{{ form.errors[q.field] }}</p>
            </div>
          </div>
        </div>

        <!-- Section C — Overall -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
          <div class="bg-indigo-50 border-b border-indigo-100 px-6 py-3">
            <h2 class="text-sm font-semibold text-indigo-700 uppercase tracking-wide">C — Overall Evaluation</h2>
          </div>
          <div class="divide-y divide-slate-50">
            <div v-for="(q, i) in sectionC" :key="q.field" class="px-6 py-4">
              <p class="text-sm text-slate-700 mb-3">
                <span class="font-semibold text-slate-500 mr-1.5">{{ i + 1 }}.</span>{{ q.text }}
              </p>
              <div class="flex flex-wrap gap-2">
                <label v-for="opt in overallScale" :key="opt.value"
                       class="flex items-center gap-1.5 cursor-pointer text-sm px-3 py-1.5 rounded-full border transition-colors"
                       :class="form[q.field] === opt.value
                         ? 'bg-indigo-600 text-white border-indigo-600'
                         : 'border-slate-200 text-slate-600 hover:border-indigo-300 hover:text-indigo-600'">
                  <input type="radio" :name="q.field" :value="opt.value" v-model="form[q.field]" class="sr-only" />
                  {{ opt.label }}
                </label>
              </div>
              <p v-if="form.errors[q.field]" class="text-xs text-red-500 mt-1.5">{{ form.errors[q.field] }}</p>
            </div>
          </div>
        </div>

        <!-- Open-ended (overall) -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 px-6 py-5 space-y-4">
          <AppTextarea v-model="form.suggestions" :rows="3" label="In what ways could we make this more effective?" placeholder="Your suggestions…" />
          <AppTextarea v-model="form.other_comments" :rows="3" label="Any other comments?" placeholder="Any other comments…" />
        </div>

        <!-- ── Speaker/Topic Evaluation — one block per speaker ────────────── -->
        <div v-for="(sp, si) in speakers" :key="sp.id" class="space-y-4">
          <div class="flex items-center gap-2 pt-2">
            <MicrophoneIcon class="w-5 h-5 text-indigo-500" />
            <h2 class="text-base font-bold text-slate-800">
              Speaker/Topic Evaluation — {{ sp.name }}
              <span v-if="sp.topic" class="font-normal text-slate-500">({{ sp.topic }})</span>
            </h2>
          </div>

          <!-- Part I — Topic/Subject Matter -->
          <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="bg-indigo-50 border-b border-indigo-100 px-6 py-3">
              <h3 class="text-sm font-semibold text-indigo-700 uppercase tracking-wide">Part I — Topic/Subject Matter</h3>
            </div>
            <div class="divide-y divide-slate-50">
              <div v-for="(q, i) in speakerPartI" :key="q.field" class="px-6 py-4">
                <p class="text-sm text-slate-700 mb-3">
                  <span class="font-semibold text-slate-500 mr-1.5">{{ String.fromCharCode(65 + i) }}.</span>{{ q.text }}
                </p>
                <div class="flex flex-wrap gap-2">
                  <label v-for="opt in topicScale" :key="opt.value"
                         class="flex items-center gap-1.5 cursor-pointer text-sm px-3 py-1.5 rounded-full border transition-colors"
                         :class="form.speakers[si][q.field] === opt.value
                           ? 'bg-indigo-600 text-white border-indigo-600'
                           : 'border-slate-200 text-slate-600 hover:border-indigo-300 hover:text-indigo-600'">
                    <input type="radio" :name="`${q.field}_${si}`" :value="opt.value" v-model="form.speakers[si][q.field]" class="sr-only" />
                    {{ opt.label }}
                  </label>
                </div>
                <p v-if="form.errors[`speakers.${si}.${q.field}`]" class="text-xs text-red-500 mt-1.5">{{ form.errors[`speakers.${si}.${q.field}`] }}</p>
              </div>
            </div>
          </div>

          <!-- Part II — Resource Person -->
          <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="bg-indigo-50 border-b border-indigo-100 px-6 py-3">
              <h3 class="text-sm font-semibold text-indigo-700 uppercase tracking-wide">Part II — Resource Person</h3>
            </div>
            <div class="divide-y divide-slate-50">
              <div v-for="(q, i) in speakerPartII" :key="q.field" class="px-6 py-4">
                <p class="text-sm text-slate-700 mb-3">
                  <span class="font-semibold text-slate-500 mr-1.5">{{ i + 1 }}.</span>{{ q.text }}
                </p>
                <div class="flex flex-wrap gap-2">
                  <label v-for="opt in agreeFull" :key="opt.value"
                         class="flex items-center gap-1.5 cursor-pointer text-sm px-3 py-1.5 rounded-full border transition-colors"
                         :class="form.speakers[si][q.field] === opt.value
                           ? 'bg-indigo-600 text-white border-indigo-600'
                           : 'border-slate-200 text-slate-600 hover:border-indigo-300 hover:text-indigo-600'">
                    <input type="radio" :name="`${q.field}_${si}`" :value="opt.value" v-model="form.speakers[si][q.field]" class="sr-only" />
                    {{ opt.label }}
                  </label>
                </div>
                <p v-if="form.errors[`speakers.${si}.${q.field}`]" class="text-xs text-red-500 mt-1.5">{{ form.errors[`speakers.${si}.${q.field}`] }}</p>
              </div>
            </div>
          </div>

          <!-- Part III — Open-ended (per speaker) -->
          <div class="bg-white rounded-2xl shadow-sm border border-slate-200 px-6 py-5 space-y-4">
            <AppTextarea v-model="form.speakers[si].effectiveness_reason" :rows="2"
                         label="Over-all, was the resource person effective? Why or why not?" />
            <AppTextarea v-model="form.speakers[si].improvement_suggestions" :rows="2"
                         label="How can he/she best improve on this topic/presentation?" />
            <AppTextarea v-model="form.speakers[si].other_topics_wanted" :rows="2"
                         label="What other topic would you have wanted covered?" />
          </div>
        </div>

        <!-- Submit -->
        <div class="flex justify-end pb-4">
          <AppButton type="submit" size="lg" :loading="form.processing" :disabled="form.processing">
            {{ form.processing ? 'Submitting…' : 'Submit Evaluation' }}
          </AppButton>
        </div>

      </form>

      <!-- Footer -->
      <p class="text-center text-xs text-slate-400 pb-6">
        Philippine Science High School – Caraga Region Campus &bull; Atlas
      </p>

    </div>
  </div>
</template>
