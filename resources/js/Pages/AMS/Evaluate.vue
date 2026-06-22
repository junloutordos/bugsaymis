<script setup>
import { computed } from 'vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'
import { CheckBadgeIcon, ClipboardDocumentListIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  activity:         Object,
  participant:      Object,
  hash:             String,
  alreadyEvaluated: Boolean,
})

const page        = usePage()
const flashSuccess = computed(() => page.props.flash?.success)

function formatDate(d) {
  if (!d) return '—'
  return new Date(d + 'T00:00:00').toLocaleDateString('en-PH', {
    year: 'numeric', month: 'long', day: 'numeric',
  })
}

// ── Likert options ─────────────────────────────────────────────────────────

const likertFull = [
  { value: 'strongly_agree',    label: 'Strongly Agree' },
  { value: 'agree',             label: 'Agree' },
  { value: 'neutral',           label: 'Neutral' },
  { value: 'disagree',          label: 'Disagree' },
  { value: 'strongly_disagree', label: 'Strongly Disagree' },
  { value: 'not_applicable',    label: 'Not Applicable' },
]

const likertBase = likertFull.slice(0, 5)   // excludes Not Applicable

// ── Sections & questions ───────────────────────────────────────────────────

const sectionA = [
  { field: 'obj_1', text: 'The activity was aligned with the school\'s vision and mission.' },
  { field: 'obj_2', text: 'The objectives were relevant to the needs of the participants.' },
  { field: 'obj_3', text: 'The activity contributed to the academic, personal, or social development of the participants.' },
  { field: 'obj_4', text: 'The objectives were achieved.' },
]

const sectionB = [
  { field: 'mgmt_1', text: 'Organizers and staff were visible and responsive.' },
  { field: 'mgmt_2', text: 'Coordination and flow of the program were clear.' },
  { field: 'mgmt_3', text: 'Time was managed effectively.' },
  { field: 'mgmt_4', text: 'Transitions between parts of the activity were smooth.' },
  { field: 'mgmt_5', text: 'The activity was well-organized.' },
  { field: 'mgmt_6', text: 'Participants were actively engaged.' },
]

const sectionC = [
  { field: 'phys_1', text: 'Venue and materials were ready and adequate.' },
  { field: 'phys_2', text: 'The sound system and equipment functioned properly.' },
  { field: 'phys_3', text: 'Participants were properly guided within the venue.' },
]

// ── Form ───────────────────────────────────────────────────────────────────

const form = useForm({
  evaluator_name: '',
  // Section A
  obj_1: '', obj_2: '', obj_3: '', obj_4: '',
  // Section B
  mgmt_1: '', mgmt_2: '', mgmt_3: '', mgmt_4: '', mgmt_5: '', mgmt_6: '',
  // Section C
  phys_1: '', phys_2: '', phys_3: '',
  // Open-ended
  suggestions: '',
  other_comments: '',
})

function submit() {
  form.post(route('ams.activities.evaluate.store', [props.activity.id, props.hash]), {
    preserveScroll: true,
  })
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
            <span class="text-sm font-medium opacity-80 uppercase tracking-wide">Activity Evaluation</span>
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
           class="bg-white rounded-2xl shadow-sm border border-green-200 p-8 text-center">
        <div class="flex justify-center mb-4">
          <div class="w-16 h-16 rounded-full bg-green-100 flex items-center justify-center">
            <CheckBadgeIcon class="w-9 h-9 text-green-600" />
          </div>
        </div>
        <h2 class="text-lg font-semibold text-slate-800 mb-1">Evaluation Submitted</h2>
        <p class="text-sm text-slate-500">
          {{ flashSuccess ?? 'You have already submitted an evaluation for this activity. Thank you!' }}
        </p>
      </div>

      <!-- Evaluation form -->
      <form v-else @submit.prevent="submit" class="space-y-6">

        <!-- Evaluator name (optional) -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 px-6 py-5">
          <label class="block text-sm font-medium text-slate-700 mb-1.5">
            Name of Evaluator <span class="text-slate-400 font-normal">(Optional)</span>
          </label>
          <input v-model="form.evaluator_name"
                 type="text"
                 placeholder="Leave blank to stay anonymous"
                 class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>

        <!-- Section A -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
          <div class="bg-indigo-50 border-b border-indigo-100 px-6 py-3">
            <h2 class="text-sm font-semibold text-indigo-700 uppercase tracking-wide">Section A — Objectives</h2>
          </div>
          <div class="divide-y divide-slate-50">
            <div v-for="(q, i) in sectionA" :key="q.field" class="px-6 py-4">
              <p class="text-sm text-slate-700 mb-3">
                <span class="font-semibold text-slate-500 mr-1.5">{{ i + 1 }}.</span>{{ q.text }}
              </p>
              <div class="flex flex-wrap gap-2">
                <label v-for="opt in likertFull" :key="opt.value"
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

        <!-- Section B -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
          <div class="bg-indigo-50 border-b border-indigo-100 px-6 py-3">
            <h2 class="text-sm font-semibold text-indigo-700 uppercase tracking-wide">Section B — Management</h2>
          </div>
          <div class="divide-y divide-slate-50">
            <div v-for="(q, i) in sectionB" :key="q.field" class="px-6 py-4">
              <p class="text-sm text-slate-700 mb-3">
                <span class="font-semibold text-slate-500 mr-1.5">{{ i + 1 }}.</span>{{ q.text }}
              </p>
              <div class="flex flex-wrap gap-2">
                <label v-for="opt in likertFull" :key="opt.value"
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

        <!-- Section C -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
          <div class="bg-indigo-50 border-b border-indigo-100 px-6 py-3">
            <h2 class="text-sm font-semibold text-indigo-700 uppercase tracking-wide">Section C — Physical Arrangements</h2>
          </div>
          <div class="divide-y divide-slate-50">
            <div v-for="(q, i) in sectionC" :key="q.field" class="px-6 py-4">
              <p class="text-sm text-slate-700 mb-3">
                <span class="font-semibold text-slate-500 mr-1.5">{{ i + 1 }}.</span>{{ q.text }}
              </p>
              <div class="flex flex-wrap gap-2">
                <label v-for="opt in likertBase" :key="opt.value"
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

        <!-- Open-ended -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 px-6 py-5 space-y-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1.5">Suggestions for Improvement</label>
            <textarea v-model="form.suggestions" rows="3" placeholder="Your suggestions…"
                      class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none" />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1.5">Other Comments</label>
            <textarea v-model="form.other_comments" rows="3" placeholder="Any other comments…"
                      class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none" />
          </div>
        </div>

        <!-- Submit -->
        <div class="flex justify-end pb-4">
          <button type="submit"
                  :disabled="form.processing"
                  class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white px-6 py-2.5 rounded-xl text-sm font-semibold shadow-sm transition-colors">
            {{ form.processing ? 'Submitting…' : 'Submit Evaluation' }}
          </button>
        </div>

      </form>

      <!-- Footer -->
      <p class="text-center text-xs text-slate-400 pb-6">
        Philippine Science High School – Caraga Region Campus &bull; Atlas
      </p>

    </div>
  </div>
</template>
