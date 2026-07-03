<script setup>
const props = defineProps({
  title:      { type: String, default: null },
  count:      { type: Number, required: true },
  sections:   { type: Array, required: true },   // [{key,label,avg,options,questions:[{field,label,avg,dist,count}]}]
  responses:  { type: Array, required: true },
  // Extra free-text fields per response beyond the default suggestions/other_comments,
  // e.g. TWS's position_function or a speaker's effectiveness_reason/other_topics_wanted.
  extraTextFields: {
    type: Array,
    default: () => [
      { key: 'suggestions', label: 'Suggestions' },
      { key: 'other_comments', label: 'Comments' },
    ],
  },
})

const PILL_COLORS = [
  'bg-green-50 text-green-700 border-green-200',
  'bg-emerald-50 text-emerald-700 border-emerald-200',
  'bg-slate-50 text-slate-600 border-slate-200',
  'bg-orange-50 text-orange-700 border-orange-200',
  'bg-red-50 text-red-700 border-red-200',
  'bg-gray-50 text-gray-500 border-gray-200',
]

function pillClass(i) {
  return PILL_COLORS[Math.min(i, PILL_COLORS.length - 1)]
}

function formatOption(opt) {
  return opt.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
}

function scaleMax(sec) {
  // topic scale (low/satisfactory/excellent) tops out at 3; everything else at 5
  return sec.options.length <= 3 ? 3 : 5
}
</script>

<template>
  <div class="space-y-6">
    <h3 v-if="title" class="text-base font-semibold text-slate-800">{{ title }}</h3>

    <!-- Summary cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="bg-white rounded-xl border border-slate-200 p-4 text-center">
        <p class="text-2xl font-bold text-indigo-600">{{ count }}</p>
        <p class="text-xs text-slate-500 mt-1 uppercase tracking-wide">Responses</p>
      </div>
      <div v-for="sec in sections" :key="sec.key"
           class="bg-white rounded-xl border border-slate-200 p-4 text-center">
        <p class="text-2xl font-bold text-indigo-600">
          {{ sec.avg !== null ? sec.avg.toFixed(2) : '—' }}
        </p>
        <p class="text-xs text-slate-500 mt-1 uppercase tracking-wide">Section {{ sec.key }} — {{ sec.label }}</p>
      </div>
    </div>

    <!-- Per-section question breakdown -->
    <div v-for="sec in sections" :key="sec.key"
         class="bg-white rounded-xl border border-slate-200 overflow-hidden">
      <div class="bg-indigo-50 border-b border-indigo-100 px-5 py-3 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-indigo-700 uppercase tracking-wide">
          Section {{ sec.key }} — {{ sec.label }}
        </h3>
        <span class="text-sm font-bold text-indigo-600">
          Avg: {{ sec.avg !== null ? sec.avg.toFixed(2) : '—' }} / {{ scaleMax(sec) }}
        </span>
      </div>
      <div class="divide-y divide-slate-50">
        <div v-for="(q, qi) in sec.questions" :key="q.field" class="px-5 py-4">
          <div class="flex items-start justify-between gap-4 mb-2">
            <p class="text-sm text-slate-700">
              <span class="font-semibold text-slate-400 mr-1.5">{{ qi + 1 }}.</span>{{ q.label }}
            </p>
            <span class="text-sm font-bold text-slate-700 shrink-0">
              {{ q.avg !== null ? q.avg.toFixed(2) : '—' }}
            </span>
          </div>
          <!-- Score bar -->
          <div class="w-full bg-slate-100 rounded-full h-2 mb-3">
            <div class="bg-indigo-500 h-2 rounded-full transition-all"
                 :style="{ width: q.avg !== null ? ((q.avg / scaleMax(sec)) * 100) + '%' : '0%' }" />
          </div>
          <!-- Distribution pills -->
          <div class="flex flex-wrap gap-1.5">
            <template v-for="(opt, oi) in sec.options" :key="opt">
              <span v-if="q.dist[opt]"
                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs border"
                    :class="pillClass(oi)">
                {{ formatOption(opt) }}
                <span class="font-semibold">{{ q.dist[opt] }}</span>
              </span>
            </template>
          </div>
        </div>
      </div>
    </div>

    <!-- Individual responses -->
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
      <div class="px-5 py-3 bg-slate-50 border-b border-slate-100">
        <h3 class="text-sm font-semibold text-slate-700">Individual Responses ({{ count }})</h3>
      </div>
      <div class="divide-y divide-slate-100">
        <div v-for="r in responses" :key="r.id" class="px-5 py-4 space-y-1.5">
          <div class="flex items-center justify-between">
            <span class="text-sm font-medium text-slate-700">{{ r.evaluator_name }}</span>
            <div class="flex items-center gap-2">
              <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium"
                    :class="r.participant_type === 'employee' ? 'bg-indigo-50 text-indigo-600' : 'bg-amber-50 text-amber-600'">
                {{ r.participant_type === 'employee' ? 'Employee' : 'Student' }}
              </span>
              <span class="text-xs text-slate-400">{{ new Date(r.submitted_at).toLocaleDateString('en-PH', { year:'numeric', month:'short', day:'numeric', hour:'2-digit', minute:'2-digit' }) }}</span>
            </div>
          </div>
          <template v-for="f in extraTextFields" :key="f.key">
            <p v-if="r[f.key]" class="text-xs text-slate-600">
              <span class="font-medium text-slate-500">{{ f.label }}:</span> {{ r[f.key] }}
            </p>
          </template>
        </div>
        <div v-if="!responses.length" class="px-5 py-8 text-center text-sm text-slate-400 italic">No responses yet.</div>
      </div>
    </div>
  </div>
</template>
