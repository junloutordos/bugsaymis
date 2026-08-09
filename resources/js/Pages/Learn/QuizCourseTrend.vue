<script setup>
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineProps({ course: Object, trend: Object })

function difficultyClass(difficulty) {
  return {
    easy: 'bg-emerald-50 text-emerald-700',
    medium: 'bg-amber-50 text-amber-700',
    hard: 'bg-red-50 text-red-700',
  }[difficulty] || 'bg-slate-100 text-slate-600'
}
</script>

<template>
  <Head :title="`Quiz Trend — ${course.subject_name}`" />
  <AdminLayout :title="course.subject_name">
    <div class="max-w-4xl mx-auto py-6 px-4 space-y-4">
      <h1 class="text-lg font-semibold text-slate-800">Quiz Trend — {{ course.subject_name }}</h1>

      <div class="border border-slate-200 rounded-lg p-4">
        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Average score by quiz (chronological)</p>
        <div v-for="quiz in trend.quizzes" :key="quiz.id" class="flex items-center justify-between py-2 border-b border-slate-100 last:border-0">
          <div>
            <p class="text-sm text-slate-700">{{ quiz.title }}</p>
            <p v-if="quiz.due_at" class="text-xs text-slate-400">{{ new Date(quiz.due_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }) }}</p>
          </div>
          <span class="text-sm font-medium text-slate-800">{{ quiz.avg_score_percentage !== null ? `${quiz.avg_score_percentage}%` : 'No data yet' }}</span>
        </div>
        <p v-if="trend.quizzes.length === 0" class="text-xs text-slate-400">No quizzes yet.</p>
      </div>

      <div class="border border-slate-200 rounded-lg p-4">
        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Average score by difficulty</p>
        <div class="flex gap-4">
          <div v-for="difficulty in ['easy', 'medium', 'hard']" :key="difficulty" class="flex-1 text-center">
            <span :class="['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium', difficultyClass(difficulty)]">{{ difficulty }}</span>
            <p class="text-lg font-semibold text-slate-800 mt-1">{{ trend.by_difficulty[difficulty] !== null ? `${trend.by_difficulty[difficulty]}%` : '—' }}</p>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
