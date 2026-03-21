<script setup>
import { computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  session:     { type: Object, required: true },
  evaluations: { type: Array,  default: () => [] },
  summary:     { type: Object, required: true },
})

// ── Helpers ────────────────────────────────────────────────────────────────────
const fmt = (d) => d ? new Date(d).toLocaleDateString('en-PH', { month: 'long', day: 'numeric', year: 'numeric' }) : '—'

const ratingLabel = (avg) => {
  if (!avg || avg === 0) return 'Not Rated'
  if (avg >= 4.5) return 'Outstanding'
  if (avg >= 3.5) return 'Very Satisfactory'
  if (avg >= 2.5) return 'Satisfactory'
  if (avg >= 1.5) return 'Unsatisfactory'
  return 'Poor'
}

const ratingColor = (avg) => {
  if (!avg || avg === 0) return 'text-gray-400'
  if (avg >= 4.5) return 'text-green-600'
  if (avg >= 3.5) return 'text-blue-600'
  if (avg >= 2.5) return 'text-yellow-600'
  return 'text-red-600'
}

const barColor = (avg) => {
  if (!avg || avg === 0) return 'bg-gray-200'
  if (avg >= 4.5) return 'bg-green-500'
  if (avg >= 3.5) return 'bg-blue-500'
  if (avg >= 2.5) return 'bg-yellow-400'
  return 'bg-red-400'
}

const barWidth = (avg) => `${Math.round((avg / 5) * 100)}%`

const completionRate = computed(() => {
  if (!props.evaluations.length) return 0
  const filled = props.evaluations.filter(e =>
    e.reaction_score || e.learning_score || e.behavior_score || e.results_score
  ).length
  return Math.round((filled / props.evaluations.length) * 100)
})

const kirkpatrickLevels = computed(() => [
  {
    level: 1,
    label: 'Reaction',
    desc: 'Participant satisfaction',
    color: 'purple',
    bgColor: 'bg-purple-50',
    borderColor: 'border-purple-200',
    textColor: 'text-purple-700',
    scores: [
      { label: 'Overall Reaction',  key: 'avg_reaction' },
      { label: 'Relevance to Work', key: 'avg_relevance' },
      { label: 'Facilitation',      key: 'avg_facilitation' },
      { label: 'Logistics',         key: 'avg_logistics' },
    ],
  },
  {
    level: 2,
    label: 'Learning',
    desc: 'Knowledge & skills acquired',
    color: 'blue',
    bgColor: 'bg-blue-50',
    borderColor: 'border-blue-200',
    textColor: 'text-blue-700',
    scores: [
      { label: 'Learning Score', key: 'avg_learning' },
    ],
  },
  {
    level: 3,
    label: 'Behavior',
    desc: 'On-the-job application',
    color: 'yellow',
    bgColor: 'bg-yellow-50',
    borderColor: 'border-yellow-200',
    textColor: 'text-yellow-700',
    scores: [
      { label: 'Behavior Score', key: 'avg_behavior' },
    ],
  },
  {
    level: 4,
    label: 'Results',
    desc: 'Organizational impact',
    color: 'green',
    bgColor: 'bg-green-50',
    borderColor: 'border-green-200',
    textColor: 'text-green-700',
    scores: [
      { label: 'Results Score', key: 'avg_results' },
    ],
  },
])

// Distribution of overall ratings
const distribution = computed(() => {
  const dist = { 5: 0, 4: 0, 3: 0, 2: 0, 1: 0 }
  props.evaluations.forEach(e => {
    if (e.overall_average) {
      const bucket = Math.floor(e.overall_average)
      const key = Math.min(5, Math.max(1, bucket))
      dist[key]++
    }
  })
  return dist
})

const distMax = computed(() => Math.max(...Object.values(distribution.value), 1))

const distLabels = { 5: 'Outstanding', 4: 'Very Satisfactory', 3: 'Satisfactory', 2: 'Unsatisfactory', 1: 'Poor' }
const distColors  = { 5: 'bg-green-500', 4: 'bg-blue-500', 3: 'bg-yellow-400', 2: 'bg-orange-400', 1: 'bg-red-500' }
</script>

<template>
  <AdminLayout :title="`Evaluation Summary — ${session.program?.title}`">
    <Head :title="`Evaluation Summary · ${session.program?.title}`" />

    <div class="p-6 space-y-6">

      <!-- Back + Header -->
      <div class="flex items-center gap-2 text-sm">
        <a :href="route('lnd.sessions.show', session.id)"
          class="inline-flex items-center gap-1 text-slate-500 hover:text-slate-700 transition-colors">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
          Back to Session
        </a>
        <span class="text-slate-300">/</span>
        <span class="font-medium text-slate-700">Evaluation Summary</span>
      </div>

      <!-- Session info bar -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 flex flex-wrap gap-6 text-sm">
        <div>
          <span class="text-slate-500">Program:</span>
          <span class="ml-2 font-medium text-slate-800">{{ session.program?.title }}</span>
        </div>
        <div>
          <span class="text-slate-500">Date:</span>
          <span class="ml-2 font-medium text-slate-800">{{ fmt(session.session_date) }}</span>
        </div>
        <div>
          <span class="text-slate-500">Facilitator:</span>
          <span class="ml-2 font-medium text-slate-800">{{ session.facilitator ?? '—' }}</span>
        </div>
        <div>
          <span class="text-slate-500">Venue:</span>
          <span class="ml-2 font-medium text-slate-800">{{ session.venue ?? '—' }}</span>
        </div>
      </div>

      <!-- Top KPI cards -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 text-center">
          <div class="text-3xl font-bold text-slate-800">{{ summary.total }}</div>
          <div class="text-xs text-slate-500 mt-1">Total Evaluations</div>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 text-center">
          <div :class="['text-3xl font-bold', ratingColor(summary.avg_overall)]">
            {{ summary.avg_overall > 0 ? summary.avg_overall : '—' }}
          </div>
          <div :class="['text-xs mt-1 font-medium', ratingColor(summary.avg_overall)]">
            {{ ratingLabel(summary.avg_overall) }}
          </div>
          <div class="text-xs text-slate-500">Overall Average</div>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 text-center">
          <div class="text-3xl font-bold text-indigo-600">{{ completionRate }}%</div>
          <div class="text-xs text-slate-500 mt-1">Evaluation Rate</div>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 text-center">
          <div :class="['text-3xl font-bold', ratingColor(summary.avg_reaction)]">
            {{ summary.avg_reaction > 0 ? summary.avg_reaction : '—' }}
          </div>
          <div class="text-xs text-slate-500 mt-1">Avg. Reaction (L1)</div>
        </div>
      </div>

      <!-- Kirkpatrick Levels -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        <div v-for="lvl in kirkpatrickLevels" :key="lvl.level"
          :class="['bg-white rounded-xl border shadow-sm overflow-hidden', lvl.borderColor]">
          <div :class="['flex items-center gap-3 px-5 py-3 border-b', lvl.bgColor, lvl.borderColor]">
            <span :class="['flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold text-white',
              `bg-${lvl.color}-600`]">{{ lvl.level }}</span>
            <div>
              <div :class="['text-sm font-semibold', lvl.textColor]">Level {{ lvl.level }} — {{ lvl.label }}</div>
              <div class="text-xs text-slate-500">{{ lvl.desc }}</div>
            </div>
          </div>
          <div class="p-4 space-y-3">
            <div v-for="score in lvl.scores" :key="score.key" class="space-y-1">
              <div class="flex items-center justify-between text-sm">
                <span class="text-slate-600">{{ score.label }}</span>
                <span :class="['font-bold', ratingColor(summary[score.key])]">
                  {{ summary[score.key] > 0 ? summary[score.key] : '—' }}
                  <span v-if="summary[score.key] > 0" class="text-xs font-normal text-slate-400">/ 5</span>
                </span>
              </div>
              <div class="h-2 w-full rounded-full bg-slate-100">
                <div :class="['h-2 rounded-full transition-all', barColor(summary[score.key])]"
                  :style="{ width: summary[score.key] > 0 ? barWidth(summary[score.key]) : '0%' }" />
              </div>
              <div class="text-xs text-slate-400 text-right">{{ ratingLabel(summary[score.key]) }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Rating Distribution -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 bg-slate-50">
          <h3 class="font-semibold text-slate-800">Rating Distribution</h3>
          <p class="text-xs text-slate-500">Overall average scores grouped by rating level</p>
        </div>
        <div class="p-5 space-y-3">
          <div v-for="(count, score) in distribution" :key="score" class="flex items-center gap-3">
            <div class="w-32 text-right text-xs font-medium text-slate-600 shrink-0">{{ distLabels[score] }}</div>
            <div class="flex-1 h-6 rounded-full bg-slate-100 overflow-hidden">
              <div :class="['h-6 rounded-full transition-all flex items-center justify-end pr-2', distColors[score]]"
                :style="{ width: `${Math.round((count / distMax) * 100)}%` }">
                <span v-if="count > 0" class="text-xs font-bold text-white">{{ count }}</span>
              </div>
            </div>
            <div class="w-8 text-xs text-slate-500 shrink-0">{{ count }}</div>
          </div>
          <div v-if="!evaluations.length" class="py-4 text-center text-sm text-slate-400">No evaluations yet.</div>
        </div>
      </div>

      <!-- Per-participant table -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 bg-slate-50">
          <h3 class="font-semibold text-slate-800">Individual Results</h3>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Participant</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">L1 Reaction</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">L2 Learning</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">L3 Behavior</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">L4 Results</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Overall</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Rating</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-if="!evaluations.length">
                <td colspan="8" class="py-16 text-center text-slate-400 text-sm">No evaluations recorded.</td>
              </tr>
              <tr v-for="ev in evaluations" :key="ev.id" class="hover:bg-slate-50/60">
                <td class="px-4 py-3 font-medium text-sm text-slate-800">
                  {{ ev.participant?.employee?.name ?? '—' }}
                </td>
                <td class="px-4 py-3 text-center">
                  <span v-if="ev.reaction_score" :class="['font-semibold', ratingColor(ev.reaction_score)]">
                    {{ ev.reaction_score }}
                  </span>
                  <span v-else class="text-slate-300">—</span>
                </td>
                <td class="px-4 py-3 text-center">
                  <span v-if="ev.learning_score" :class="['font-semibold', ratingColor(ev.learning_score)]">
                    {{ ev.learning_score }}
                  </span>
                  <span v-else class="text-slate-300">—</span>
                </td>
                <td class="px-4 py-3 text-center">
                  <span v-if="ev.behavior_score" :class="['font-semibold', ratingColor(ev.behavior_score)]">
                    {{ ev.behavior_score }}
                  </span>
                  <span v-else class="text-slate-300">—</span>
                </td>
                <td class="px-4 py-3 text-center">
                  <span v-if="ev.results_score" :class="['font-semibold', ratingColor(ev.results_score)]">
                    {{ ev.results_score }}
                  </span>
                  <span v-else class="text-slate-300">—</span>
                </td>
                <td class="px-4 py-3 text-center">
                  <span v-if="ev.overall_average" :class="['text-base font-bold', ratingColor(ev.overall_average)]">
                    {{ ev.overall_average }}
                  </span>
                  <span v-else class="text-slate-300">—</span>
                </td>
                <td class="px-4 py-3 text-center">
                  <span :class="['inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium',
                    ev.overall_average >= 4.5 ? 'bg-emerald-50 text-emerald-700' :
                    ev.overall_average >= 3.5 ? 'bg-blue-50 text-blue-700' :
                    ev.overall_average >= 2.5 ? 'bg-amber-50 text-amber-700' :
                    ev.overall_average ? 'bg-red-50 text-red-600' : 'bg-slate-100 text-slate-500']">
                    {{ ratingLabel(ev.overall_average) }}
                  </span>
                </td>
                <td class="px-4 py-3 text-center">
                  <a v-if="ev.participant_id"
                    :href="route('lnd.evaluations.show', ev.participant_id)"
                    class="text-xs font-medium text-indigo-600 hover:underline">View</a>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
