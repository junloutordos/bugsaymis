<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import axios from 'axios'
import QuizAnswerTile from '@/Components/Quiz/QuizAnswerTile.vue'
import QuizCountdownRing from '@/Components/Quiz/QuizCountdownRing.vue'
import { CheckCircleIcon, XCircleIcon } from '@heroicons/vue/24/solid'

const props = defineProps({
  player: Object,
  session: Object,
  player_token: String,
})

const uiState = ref('waiting') // waiting | question | locked | feedback | leaderboard | ended
const currentQuestion = ref(null)
const questionStartedAt = ref(null)
const selectedOptionId = ref(null)
const answerText = ref('')
const totalScore = ref(props.player.total_score)
const feedback = ref(null) // { is_correct, points_awarded }
const myRank = ref(null)
const podium = ref([])
const submitting = ref(false)

let channel = null

async function loadState() {
  const { data } = await axios.get(route('quiz.play.state', props.player_token))
  totalScore.value = data.total_score
  if (data.status === 'question_active' && data.question) {
    currentQuestion.value = data.question
    uiState.value = data.already_answered ? 'locked' : 'question'
  } else if (data.status === 'lobby') {
    uiState.value = 'waiting'
  }
}

function subscribe() {
  channel = window.Echo.channel(`quiz-session.${props.session.game_pin}`)

  channel.listen('.question.started', (payload) => {
    currentQuestion.value = payload.question
    questionStartedAt.value = payload.server_started_at
    selectedOptionId.value = null
    answerText.value = ''
    feedback.value = null
    uiState.value = 'question'
  })

  channel.listen('.question.ended', () => {
    if (uiState.value === 'question') {
      // Ran out of time without answering
      feedback.value = { is_correct: false, points_awarded: 0, timedOut: true }
      uiState.value = 'feedback'
    }
  })

  channel.listen('.leaderboard.updated', (payload) => {
    const mine = payload.leaderboard.find(p => p.id === props.player.id)
    myRank.value = mine ?? null
    if (mine) totalScore.value = mine.total_score
    uiState.value = 'leaderboard'
  })

  channel.listen('.session.ended', (payload) => {
    podium.value = payload.podium
    uiState.value = 'ended'
  })
}

onMounted(async () => {
  await loadState()
  subscribe()
})

onUnmounted(() => {
  if (channel) window.Echo?.leaveChannel(`quiz-session.${props.session.game_pin}`)
})

async function submitAnswer() {
  if (submitting.value) return
  submitting.value = true
  uiState.value = 'locked'

  try {
    const { data } = await axios.post(route('quiz.play.answer', props.player_token), {
      selected_option_ids: selectedOptionId.value !== null ? [selectedOptionId.value] : [],
      answer_text: currentQuestion.value?.type === 'open_ended' ? answerText.value : null,
    })
    totalScore.value = data.total_score
    feedback.value = { is_correct: data.is_correct, points_awarded: data.points_awarded }
    uiState.value = 'feedback'
  } finally {
    submitting.value = false
  }
}

function selectOption(id) {
  if (uiState.value !== 'question') return
  selectedOptionId.value = id
  submitAnswer()
}
</script>

<template>
  <Head title="Playing..." />

  <div class="min-h-screen bg-gradient-to-br from-[#0A2A5E] to-[#073E85] text-white flex flex-col">
    <div class="flex items-center justify-between px-4 py-3 text-sm">
      <span class="font-medium truncate">{{ player.nickname }}</span>
      <span class="font-heading font-semibold">{{ totalScore }} pts</span>
    </div>

    <div class="flex-1 flex flex-col items-center justify-center px-4 pb-8">
      <!-- WAITING -->
      <div v-if="uiState === 'waiting'" class="text-center">
        <div class="animate-pulse text-2xl font-heading font-bold mb-2">You're in!</div>
        <p class="text-white/60 text-sm">Waiting for the host to start...</p>
      </div>

      <!-- QUESTION -->
      <div v-else-if="uiState === 'question' && currentQuestion" class="w-full max-w-md">
        <div class="flex justify-center mb-6" v-if="questionStartedAt">
          <QuizCountdownRing :duration-seconds="currentQuestion.time_limit_seconds" :started-at="questionStartedAt" />
        </div>

        <div v-if="currentQuestion.type === 'open_ended'" class="space-y-3">
          <input
            v-model="answerText"
            placeholder="Type your answer..."
            class="w-full rounded-xl bg-white/10 border border-white/20 py-3 px-4 text-white placeholder-white/40 focus:outline-none focus:ring-2 focus:ring-white"
          />
          <button
            type="button" class="w-full bg-emerald-500 rounded-xl py-3 font-semibold"
            :disabled="!answerText.trim()" @click="submitAnswer"
          >Submit</button>
        </div>

        <div v-else class="grid grid-cols-2 gap-3">
          <QuizAnswerTile
            v-for="opt in currentQuestion.options" :key="opt.id"
            :tile-index="opt.tile_index ?? 0" :text="opt.option_text"
            @click="selectOption(opt.id)"
          />
        </div>
      </div>

      <!-- LOCKED (submitted, waiting for reveal) -->
      <div v-else-if="uiState === 'locked'" class="text-center">
        <div class="text-2xl font-heading font-bold mb-2">Answer locked in!</div>
        <p class="text-white/60 text-sm">Waiting for other players...</p>
      </div>

      <!-- FEEDBACK -->
      <div v-else-if="uiState === 'feedback'" class="text-center">
        <template v-if="feedback?.timedOut">
          <XCircleIcon class="h-16 w-16 mx-auto mb-3 text-red-400" />
          <p class="text-xl font-heading font-bold">Time's up!</p>
        </template>
        <template v-else-if="feedback?.is_correct === true">
          <CheckCircleIcon class="h-16 w-16 mx-auto mb-3 text-emerald-400" />
          <p class="text-xl font-heading font-bold">Correct! +{{ feedback.points_awarded }}</p>
        </template>
        <template v-else-if="feedback?.is_correct === false">
          <XCircleIcon class="h-16 w-16 mx-auto mb-3 text-red-400" />
          <p class="text-xl font-heading font-bold">Not quite</p>
        </template>
        <template v-else>
          <CheckCircleIcon class="h-16 w-16 mx-auto mb-3 text-blue-300" />
          <p class="text-xl font-heading font-bold">Answer submitted!</p>
        </template>
        <p class="text-white/60 text-sm mt-2">Waiting for results...</p>
      </div>

      <!-- LEADERBOARD (own rank only) -->
      <div v-else-if="uiState === 'leaderboard'" class="text-center">
        <p class="text-white/60 text-sm mb-1">You're in</p>
        <p class="text-5xl font-heading font-bold mb-2">#{{ myRank?.rank ?? '-' }}</p>
        <p class="text-white/70">{{ totalScore }} points</p>
      </div>

      <!-- ENDED -->
      <div v-else-if="uiState === 'ended'" class="text-center">
        <p class="text-2xl font-heading font-bold mb-4">Game over!</p>
        <p class="text-white/70 mb-1">Final score</p>
        <p class="text-4xl font-heading font-bold">{{ totalScore }}</p>
      </div>
    </div>
  </div>
</template>
