<script setup>
import { computed, ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import MathContent from '@/Components/MathContent.vue'
import DOMPurify from 'dompurify'

const props = defineProps({ quiz: Object, attempts: Array })

function sanitizeHtml(html) {
  return DOMPurify.sanitize(html || '')
}

const expandedAttemptId = ref(null)
const gradeForms = ref({})

function toggleExpand(attempt) {
  expandedAttemptId.value = expandedAttemptId.value === attempt.id ? null : attempt.id

  if (! gradeForms.value[attempt.id]) {
    const forms = {}
    for (const answer of attempt.answers) {
      if (answer.question_type === 'essay') {
        forms[answer.id] = useForm({ points_earned: answer.points_earned ?? '' })
      }
    }
    gradeForms.value[attempt.id] = forms
  }
}

function gradeEssay(attemptId, answerId) {
  gradeForms.value[attemptId][answerId].put(route('learn.quiz-attempt-answers.grade', answerId), { preserveScroll: true })
}

function reopen(attemptId) {
  router.post(route('learn.quiz-attempts.reopen', attemptId), {}, { preserveScroll: true })
}

const selectedClassRecordId = ref('')
const selectedQuarterId = ref('')
const selectedAssessmentId = ref('')

const availableQuarters = computed(() => {
  const cr = (props.quiz.class_record_options || []).find(c => c.id === Number(selectedClassRecordId.value))
  return cr ? cr.quarters : []
})
const availableAssessments = computed(() => {
  const q = availableQuarters.value.find(q => q.id === Number(selectedQuarterId.value))
  return q ? q.assessments : []
})

function linkAssessment() {
  if (! selectedAssessmentId.value) return
  router.put(route('learn.quizzes.link', props.quiz.id), {
    class_record_assessment_id: selectedAssessmentId.value,
  }, { preserveScroll: true })
}

function pushToClassRecord() {
  router.post(route('learn.quizzes.push', props.quiz.id), {}, { preserveScroll: true })
}
</script>

<template>
  <Head :title="`Quiz Grading — ${quiz.title}`" />
  <AdminLayout :title="quiz.title">
    <div class="max-w-4xl mx-auto py-6 px-4 space-y-4">
      <div>
        <h1 class="text-lg font-semibold text-slate-800">{{ quiz.title }}</h1>
        <p class="text-sm text-slate-500">{{ quiz.max_score }} pts total</p>
      </div>

      <div class="border border-slate-200 rounded-lg p-4 space-y-2">
        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Class Record</p>

        <div v-if="quiz.class_record_link">
          <p class="text-sm text-slate-700">
            Linked to <strong>{{ quiz.class_record_link.class_record_name }}</strong> —
            Q{{ quiz.class_record_link.quarter }} — {{ quiz.class_record_link.category_name }} —
            "{{ quiz.class_record_link.assessment_title }}"
          </p>
          <p class="text-xs text-slate-500 mt-1">
            {{ quiz.class_record_link.pushed_at ? `Last pushed ${new Date(quiz.class_record_link.pushed_at).toLocaleString('en-PH')}` : 'Not pushed yet' }}
          </p>
          <button @click="pushToClassRecord" class="mt-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
            Push graded scores
          </button>
        </div>

        <div v-else class="space-y-2">
          <p class="text-xs text-slate-500">Not linked yet. Pick the Class Record assessment to push scores into.</p>
          <div class="flex gap-2">
            <select v-model="selectedClassRecordId" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm flex-1">
              <option value="" disabled>Class Record</option>
              <option v-for="cr in quiz.class_record_options" :key="cr.id" :value="cr.id">{{ cr.display_name }}</option>
            </select>
            <select v-model="selectedQuarterId" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
              <option value="" disabled>Quarter</option>
              <option v-for="q in availableQuarters" :key="q.id" :value="q.id">Q{{ q.quarter }}</option>
            </select>
            <select v-model="selectedAssessmentId" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm flex-1">
              <option value="" disabled>Assessment</option>
              <option v-for="a in availableAssessments" :key="a.id" :value="a.id">{{ a.category_name }} — {{ a.title }} ({{ a.max_score }} pts)</option>
            </select>
          </div>
          <button @click="linkAssessment" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium">Link</button>
        </div>
      </div>

      <div v-for="attempt in attempts" :key="attempt.id" class="border border-slate-200 rounded-lg">
        <button class="w-full flex items-center justify-between px-4 py-3 text-left cursor-pointer hover:bg-slate-50" @click="toggleExpand(attempt)">
          <span class="text-sm font-medium text-slate-800">{{ attempt.student_name }} — Attempt {{ attempt.attempt_number }}</span>
          <div class="flex items-center gap-2">
            <span v-if="attempt.score !== null" class="text-xs text-slate-500">{{ attempt.score }} / {{ quiz.max_score }}</span>
            <span v-else-if="attempt.pending_essays > 0" class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-amber-50 text-amber-700">{{ attempt.pending_essays }} pending</span>
            <span v-else-if="!attempt.is_submitted" class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-slate-100 text-slate-600">In progress</span>
          </div>
        </button>

        <div v-if="expandedAttemptId === attempt.id" class="border-t border-slate-100 p-4 space-y-3">
          <div v-for="answer in attempt.answers" :key="answer.id" class="border border-slate-100 rounded-lg p-3 space-y-1">
            <MathContent :html="sanitizeHtml(answer.prompt)" class="prose prose-sm max-w-none" />
            <p class="text-xs text-slate-400">{{ answer.points_possible }} pts</p>

            <div v-if="answer.options">
              <p v-for="opt in answer.options" :key="opt.id" class="text-sm"
                 :class="{ 'font-semibold text-emerald-700': opt.is_correct, 'underline': answer.selected_option_ids.includes(opt.id) }">
                {{ opt.option_text }} <span v-if="answer.selected_option_ids.includes(opt.id)">(selected)</span>
              </p>
            </div>
            <p v-else class="text-sm text-slate-700 whitespace-pre-line">{{ answer.answer_text }}</p>

            <div v-if="answer.question_type === 'essay' && gradeForms[attempt.id]" class="flex items-center gap-2 mt-2">
              <input v-model="gradeForms[attempt.id][answer.id].points_earned" type="number" min="0" :max="answer.points_possible" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-24" />
              <button @click="gradeEssay(attempt.id, answer.id)" class="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium">Save grade</button>
              <span v-if="answer.points_earned !== null" class="text-xs text-emerald-600">Graded: {{ answer.points_earned }}</span>
            </div>
            <p v-else-if="answer.is_correct !== null" class="text-xs" :class="answer.is_correct ? 'text-emerald-600' : 'text-red-600'">
              {{ answer.is_correct ? 'Correct' : 'Incorrect' }} — {{ answer.points_earned }} pts
            </p>
          </div>

          <button @click="reopen(attempt.id)" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium">Reopen for resubmission</button>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
