<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import StudentPortalLayout from '@/Layouts/StudentPortalLayout.vue'
import MathContent from '@/Components/MathContent.vue'
import DOMPurify from 'dompurify'

const props = defineProps({ attempt: Object })

function sanitizeHtml(html) {
  return DOMPurify.sanitize(html || '')
}

const answers = ref({})
for (const q of props.attempt.questions) {
  answers.value[q.id] = {
    answer_text: q.your_answer?.answer_text || '',
    selected_option_ids: q.your_answer?.selected_option_ids ? [...q.your_answer.selected_option_ids] : [],
  }
}

function saveTextAnswer(question) {
  router.put(route('student-portal.learn.quiz-attempts.answer', [props.attempt.id, question.id]), {
    answer_text: answers.value[question.id].answer_text,
  }, { preserveScroll: true, preserveState: true })
}

function toggleOption(question, optionId) {
  const current = answers.value[question.id].selected_option_ids
  if (question.question_type === 'multiple_select') {
    const idx = current.indexOf(optionId)
    if (idx === -1) current.push(optionId)
    else current.splice(idx, 1)
  } else {
    answers.value[question.id].selected_option_ids = [optionId]
  }
  router.put(route('student-portal.learn.quiz-attempts.answer', [props.attempt.id, question.id]), {
    selected_option_ids: answers.value[question.id].selected_option_ids,
  }, { preserveScroll: true, preserveState: true })
}

function submitQuiz() {
  router.post(route('student-portal.learn.quiz-attempts.submit', props.attempt.id))
}

// Client-side countdown drives the auto-submit call; the server stays authoritative via
// lazy expiry finalization on next touch even if this timer never fires (dropped tab, etc).
const remainingSeconds = ref(null)
let timer = null

function computeRemaining() {
  if (! props.attempt.time_limit_minutes || props.attempt.is_submitted) return null
  const deadline = new Date(props.attempt.started_at).getTime() + props.attempt.time_limit_minutes * 60000
  return Math.max(0, Math.floor((deadline - Date.now()) / 1000))
}

onMounted(() => {
  remainingSeconds.value = computeRemaining()
  if (remainingSeconds.value === null) return
  timer = setInterval(() => {
    remainingSeconds.value = computeRemaining()
    if (remainingSeconds.value === 0) {
      clearInterval(timer)
      submitQuiz()
    }
  }, 1000)
})
onUnmounted(() => { if (timer) clearInterval(timer) })

const formattedRemaining = computed(() => {
  if (remainingSeconds.value === null) return null
  const m = Math.floor(remainingSeconds.value / 60)
  const s = remainingSeconds.value % 60
  return `${m}:${s.toString().padStart(2, '0')}`
})
</script>

<template>
  <Head :title="attempt.quiz_title" />
  <StudentPortalLayout>
    <div class="max-w-3xl mx-auto py-6 px-4 space-y-4">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-lg font-semibold text-slate-800">{{ attempt.quiz_title }}</h1>
          <p class="text-sm text-slate-500">{{ attempt.max_score }} pts total</p>
        </div>
        <div v-if="formattedRemaining && !attempt.is_submitted" class="text-sm font-medium text-amber-700">
          Time remaining: {{ formattedRemaining }}
        </div>
      </div>

      <div v-if="attempt.is_submitted" class="border border-emerald-200 bg-emerald-50 rounded-lg p-4">
        <p class="text-sm font-medium text-emerald-800">
          {{ attempt.score !== null ? `Score: ${attempt.score} / ${attempt.max_score}` : 'Submitted — awaiting grading on essay questions.' }}
        </p>
        <p v-if="attempt.auto_submitted" class="text-xs text-amber-700 mt-1">Automatically submitted when time ran out.</p>
      </div>

      <div v-for="(question, index) in attempt.questions" :key="question.id" class="border border-slate-200 rounded-lg p-4 space-y-2">
        <div class="flex items-center justify-between">
          <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Question {{ index + 1 }}</p>
          <p class="text-xs text-slate-400">{{ question.points }} pts</p>
        </div>
        <MathContent :html="sanitizeHtml(question.prompt)" class="prose prose-sm max-w-none" />

        <div v-if="question.options" class="space-y-1">
          <label v-for="option in question.options" :key="option.id" class="flex items-center gap-2 text-sm text-slate-700">
            <input
              :type="question.question_type === 'multiple_select' ? 'checkbox' : 'radio'"
              :name="`question-${question.id}`"
              :disabled="attempt.is_submitted"
              :checked="answers[question.id].selected_option_ids.includes(option.id)"
              @change="toggleOption(question, option.id)"
            />
            {{ option.option_text }}
          </label>
        </div>

        <textarea
          v-else
          v-model="answers[question.id].answer_text"
          :disabled="attempt.is_submitted"
          @blur="saveTextAnswer(question)"
          :placeholder="question.question_type === 'essay' ? 'Write your answer' : 'Short answer'"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full"
          rows="3"
        />

        <p v-if="attempt.is_submitted && question.your_answer && question.your_answer.is_correct !== null" class="text-xs"
           :class="question.your_answer.is_correct ? 'text-emerald-600' : 'text-red-600'">
          {{ question.your_answer.is_correct ? 'Correct' : 'Incorrect' }} — {{ question.your_answer.points_earned }} pts
        </p>
      </div>

      <button v-if="!attempt.is_submitted" @click="submitQuiz" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
        Submit quiz
      </button>
    </div>
  </StudentPortalLayout>
</template>
