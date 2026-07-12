<script setup>
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppCard from '@/Components/AppCard.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppTable from '@/Components/AppTable.vue'
import EmptyState from '@/Components/EmptyState.vue'

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

const ratingBadgeColor = (avg) => {
  if (!avg) return 'slate'
  if (avg >= 4.5) return 'green'
  if (avg >= 3.5) return 'blue'
  if (avg >= 2.5) return 'amber'
  return 'red'
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

const breadcrumb = computed(() => [
  { label: 'Sessions', href: route('lnd.sessions.index') },
  { label: fmt(props.session.session_date), href: route('lnd.sessions.show', props.session.id) },
  { label: 'Evaluation Summary' },
])
</script>

<template>
  <Head :title="`Evaluation Summary · ${session.program?.title}`" />
  <AdminLayout :title="`Evaluation Summary — ${session.program?.title}`">
    <div class="space-y-5">

      <AppPageHeader title="Evaluation Summary" :breadcrumb="breadcrumb" />

      <!-- Session info bar -->
      <AppCard>
        <div class="flex flex-wrap gap-6 text-sm">
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
      </AppCard>

      <!-- Top KPI cards -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <AppCard class="text-center">
          <div class="text-3xl font-bold text-slate-800">{{ summary.total }}</div>
          <div class="text-xs text-slate-500 mt-1">Total Evaluations</div>
        </AppCard>
        <AppCard class="text-center">
          <div :class="['text-3xl font-bold', ratingColor(summary.avg_overall)]">
            {{ summary.avg_overall > 0 ? summary.avg_overall : '—' }}
          </div>
          <div :class="['text-xs mt-1 font-medium', ratingColor(summary.avg_overall)]">
            {{ ratingLabel(summary.avg_overall) }}
          </div>
          <div class="text-xs text-slate-500">Overall Average</div>
        </AppCard>
        <AppCard class="text-center">
          <div class="text-3xl font-bold text-indigo-600">{{ completionRate }}%</div>
          <div class="text-xs text-slate-500 mt-1">Evaluation Rate</div>
        </AppCard>
        <AppCard class="text-center">
          <div :class="['text-3xl font-bold', ratingColor(summary.avg_reaction)]">
            {{ summary.avg_reaction > 0 ? summary.avg_reaction : '—' }}
          </div>
          <div class="text-xs text-slate-500 mt-1">Avg. Reaction (L1)</div>
        </AppCard>
      </div>

      <!-- Kirkpatrick Levels -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        <AppCard v-for="lvl in kirkpatrickLevels" :key="lvl.level" :padded="false">
          <div :class="['flex items-center gap-3 px-5 py-3 border-b rounded-t-xl', lvl.bgColor, lvl.borderColor]">
            <span :class="['flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold text-white', `bg-${lvl.color}-600`]">{{ lvl.level }}</span>
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
        </AppCard>
      </div>

      <!-- Rating Distribution -->
      <AppCard title="Rating Distribution" subtitle="Overall average scores grouped by rating level">
        <div class="space-y-3">
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
      </AppCard>

      <!-- Per-participant table -->
      <AppCard title="Individual Results" :padded="false">
        <AppTable :card="false" :is-empty="!evaluations.length" :skeleton-cols="8">
          <template #head>
            <tr>
              <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Participant</th>
              <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">L1 Reaction</th>
              <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">L2 Learning</th>
              <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">L3 Behavior</th>
              <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">L4 Results</th>
              <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Overall</th>
              <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Rating</th>
              <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Action</th>
            </tr>
          </template>

          <tr v-for="ev in evaluations" :key="ev.id" class="hover:bg-indigo-50/40">
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
              <AppBadge :color="ratingBadgeColor(ev.overall_average)">{{ ratingLabel(ev.overall_average) }}</AppBadge>
            </td>
            <td class="px-4 py-3 text-center">
              <Link v-if="ev.participant_id"
                :href="route('lnd.evaluations.show', ev.participant_id)"
                class="text-xs font-medium text-indigo-600 hover:underline">View</Link>
            </td>
          </tr>

          <template #empty>
            <EmptyState title="No evaluations recorded" />
          </template>
        </AppTable>
      </AppCard>
    </div>
  </AdminLayout>
</template>
