<script setup>
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import MathContent from '@/Components/MathContent.vue'
import DOMPurify from 'dompurify'

defineProps({ quiz: Object, analysis: Object })

function sanitizeHtml(html) {
  return DOMPurify.sanitize(html || '')
}

function difficultyClass(difficulty) {
  return {
    easy: 'bg-emerald-50 text-emerald-700',
    medium: 'bg-amber-50 text-amber-700',
    hard: 'bg-red-50 text-red-700',
  }[difficulty] || 'bg-slate-100 text-slate-600'
}
</script>

<template>
  <Head :title="`Item Analysis — ${quiz.title}`" />
  <AdminLayout :title="quiz.title">
    <div class="max-w-4xl mx-auto py-6 px-4 space-y-4">
      <h1 class="text-lg font-semibold text-slate-800">Item Analysis — {{ quiz.title }}</h1>

      <div class="border border-slate-200 rounded-lg p-4">
        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Score distribution</p>
        <div class="grid grid-cols-4 gap-4 text-center">
          <div><p class="text-xs text-slate-500">Min</p><p class="text-lg font-semibold text-slate-800">{{ analysis.distribution.min ?? '—' }}</p></div>
          <div><p class="text-xs text-slate-500">Max</p><p class="text-lg font-semibold text-slate-800">{{ analysis.distribution.max ?? '—' }}</p></div>
          <div><p class="text-xs text-slate-500">Average</p><p class="text-lg font-semibold text-slate-800">{{ analysis.distribution.avg ?? '—' }}</p></div>
          <div><p class="text-xs text-slate-500">Median</p><p class="text-lg font-semibold text-slate-800">{{ analysis.distribution.median ?? '—' }}</p></div>
        </div>
      </div>

      <div v-for="question in analysis.questions" :key="question.id" class="border border-slate-200 rounded-lg p-4 space-y-1">
        <div class="flex items-center justify-between">
          <span v-if="question.difficulty" :class="['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium', difficultyClass(question.difficulty)]">{{ question.difficulty }}</span>
          <span class="text-sm font-medium text-slate-700">{{ question.avg_score_percentage !== null ? `${question.avg_score_percentage}%` : 'No data yet' }}</span>
        </div>
        <MathContent :html="sanitizeHtml(question.prompt)" class="prose prose-sm max-w-none" />
        <p class="text-xs text-slate-400">{{ question.graded_attempts }} graded attempt{{ question.graded_attempts === 1 ? '' : 's' }}</p>
      </div>
    </div>
  </AdminLayout>
</template>
