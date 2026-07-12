<script setup>
import { computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppCard from '@/Components/AppCard.vue'
import AppButton from '@/Components/AppButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import {
  ArrowDownTrayIcon, ArrowLeftIcon, UsersIcon, CheckCircleIcon, ClockIcon, FireIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  session: Object,
  questions: Array,
  players: Array,
  scored_question_count: Number,
})

const avgAccuracy = computed(() => {
  const scored = props.questions.filter(q => q.accuracy_pct !== null)
  if (!scored.length) return null
  return Math.round(scored.reduce((sum, q) => sum + q.accuracy_pct, 0) / scored.length)
})

const avgResponseSec = computed(() => {
  const withTimes = props.questions.filter(q => q.avg_response_ms !== null)
  if (!withTimes.length) return null
  return (withTimes.reduce((sum, q) => sum + q.avg_response_ms, 0) / withTimes.length / 1000).toFixed(1)
})

const hardestQuestion = computed(() => {
  const scored = props.questions.filter(q => q.accuracy_pct !== null)
  if (!scored.length) return null
  return scored.reduce((min, q) => (q.accuracy_pct < min.accuracy_pct ? q : min))
})

function accuracyColor(pct) {
  if (pct >= 70) return 'bg-emerald-500'
  if (pct >= 40) return 'bg-amber-400'
  return 'bg-red-500'
}

function fmtDate(d) {
  return d ? new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }) : '—'
}

function exportCsv() {
  const rows = [
    ['Rank', 'Nickname', 'Score', 'Correct', 'Answered', 'Best Streak', 'Avg Response (s)'],
    ...props.players.map(p => [
      p.rank, p.nickname, p.total_score,
      `${p.correct_count}/${props.scored_question_count}`,
      p.answered_count, p.best_streak,
      p.avg_response_ms !== null ? (p.avg_response_ms / 1000).toFixed(1) : '',
    ]),
  ]
  const csv = rows.map(r => r.map(v => `"${String(v).replaceAll('"', '""')}"`).join(',')).join('\n')
  const link = document.createElement('a')
  link.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv' }))
  link.download = `quiz-report-${props.session.game_pin}.csv`
  link.click()
  URL.revokeObjectURL(link.href)
}
</script>

<template>
  <Head :title="`Report — ${session.quiz.title}`" />
  <AdminLayout title="Quiz Report">
    <AppPageHeader
      :title="session.quiz.title"
      :subtitle="`Session PIN ${session.game_pin} · ${fmtDate(session.started_at)}`"
    >
      <template #actions>
        <AppButton variant="secondary" @click="router.visit(route('quiz.edit', session.quiz.id))">
          <ArrowLeftIcon class="h-4 w-4" /> Back to Quiz
        </AppButton>
        <AppButton variant="primary" @click="exportCsv">
          <ArrowDownTrayIcon class="h-4 w-4" /> Export CSV
        </AppButton>
      </template>
    </AppPageHeader>

    <!-- Summary -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
      <AppCard>
        <div class="flex items-center gap-3">
          <UsersIcon class="h-8 w-8 text-indigo-500" />
          <div>
            <p class="text-2xl font-semibold text-slate-800">{{ players.length }}</p>
            <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Players</p>
          </div>
        </div>
      </AppCard>
      <AppCard>
        <div class="flex items-center gap-3">
          <CheckCircleIcon class="h-8 w-8 text-emerald-500" />
          <div>
            <p class="text-2xl font-semibold text-slate-800">{{ avgAccuracy !== null ? `${avgAccuracy}%` : '—' }}</p>
            <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Avg accuracy</p>
          </div>
        </div>
      </AppCard>
      <AppCard>
        <div class="flex items-center gap-3">
          <ClockIcon class="h-8 w-8 text-indigo-500" />
          <div>
            <p class="text-2xl font-semibold text-slate-800">{{ avgResponseSec !== null ? `${avgResponseSec}s` : '—' }}</p>
            <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Avg response</p>
          </div>
        </div>
      </AppCard>
      <AppCard>
        <div class="flex items-center gap-3">
          <FireIcon class="h-8 w-8 text-amber-500" />
          <div>
            <p class="text-sm font-semibold text-slate-800 line-clamp-2">{{ hardestQuestion ? hardestQuestion.question_text : '—' }}</p>
            <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
              Hardest question{{ hardestQuestion ? ` (${hardestQuestion.accuracy_pct}%)` : '' }}
            </p>
          </div>
        </div>
      </AppCard>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
      <!-- Per-question breakdown -->
      <AppCard title="Questions">
        <div class="space-y-5">
          <div v-for="(q, i) in questions" :key="q.id" class="border-b border-slate-100 last:border-0 pb-5 last:pb-0">
            <div class="flex items-start gap-2 mb-2">
              <span class="text-xs text-slate-400 font-semibold mt-0.5">Q{{ i + 1 }}</span>
              <p class="flex-1 text-sm font-medium text-slate-700">{{ q.question_text }}</p>
              <span v-if="q.double_points" class="text-[10px] font-bold text-amber-500 shrink-0">2×</span>
              <AppBadge v-if="!q.is_scored" color="slate">{{ q.type === 'poll' ? 'poll' : 'open-ended' }}</AppBadge>
            </div>

            <div v-if="q.accuracy_pct !== null" class="flex items-center gap-3 mb-3">
              <div class="flex-1 h-2.5 bg-slate-100 rounded-full overflow-hidden">
                <div :class="['h-full rounded-full', accuracyColor(q.accuracy_pct)]" :style="{ width: `${q.accuracy_pct}%` }" />
              </div>
              <span class="text-sm font-semibold text-slate-700 w-12 text-right">{{ q.accuracy_pct }}%</span>
            </div>

            <div class="space-y-1.5">
              <div v-for="opt in q.options" :key="opt.id" class="flex items-center gap-2 text-sm">
                <CheckCircleIcon v-if="opt.is_correct" class="h-4 w-4 text-emerald-500 shrink-0" />
                <span v-else class="h-4 w-4 shrink-0" />
                <span :class="['flex-1 truncate', opt.is_correct ? 'text-slate-700 font-medium' : 'text-slate-500']">{{ opt.option_text }}</span>
                <span class="text-slate-400 text-xs">{{ opt.count }}</span>
              </div>
              <div v-if="q.answer_texts.length" class="text-sm text-slate-500 space-y-1 mt-1">
                <p v-for="(t, ti) in q.answer_texts" :key="ti" class="bg-slate-50 rounded px-2 py-1">"{{ t }}"</p>
              </div>
            </div>

            <div v-if="q.explanation_text" class="mt-2 bg-indigo-50/60 rounded-lg px-3 py-2 text-sm text-slate-600">
              <span class="font-semibold text-indigo-600">💡</span> {{ q.explanation_text }}
            </div>

            <p class="text-xs text-slate-400 mt-2">
              {{ q.answered_count }} answered{{ q.avg_response_ms !== null ? ` · avg ${(q.avg_response_ms / 1000).toFixed(1)}s` : '' }}
            </p>
          </div>
        </div>
      </AppCard>

      <!-- Player standings -->
      <AppCard title="Players" :padded="false">
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead>
              <tr class="text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider border-b border-slate-100">
                <th class="px-4 py-2.5">#</th>
                <th class="px-4 py-2.5">Player</th>
                <th class="px-4 py-2.5 text-right">Score</th>
                <th class="px-4 py-2.5 text-right">Correct</th>
                <th class="px-4 py-2.5 text-right">Best streak</th>
                <th class="px-4 py-2.5 text-right">Avg response</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
              <tr v-for="p in players" :key="p.rank" :class="p.rank <= 3 ? 'bg-amber-50/50' : ''">
                <td class="px-4 py-2.5 font-semibold text-slate-500">{{ p.rank }}</td>
                <td class="px-4 py-2.5 font-medium text-slate-700">{{ p.nickname }}</td>
                <td class="px-4 py-2.5 text-right font-semibold text-slate-800">{{ Number(p.total_score).toLocaleString('en-PH') }}</td>
                <td class="px-4 py-2.5 text-right text-slate-600">{{ p.correct_count }}/{{ scored_question_count }}</td>
                <td class="px-4 py-2.5 text-right text-slate-600">{{ p.best_streak }}</td>
                <td class="px-4 py-2.5 text-right text-slate-600">{{ p.avg_response_ms !== null ? `${(p.avg_response_ms / 1000).toFixed(1)}s` : '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </AppCard>
    </div>
  </AdminLayout>
</template>
