<script setup>
import { ref, reactive, onMounted, onUnmounted } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import axios from 'axios'
import QuizPinDisplay from '@/Components/Quiz/QuizPinDisplay.vue'
import QuizAnswerTile from '@/Components/Quiz/QuizAnswerTile.vue'
import QuizCountdownRing from '@/Components/Quiz/QuizCountdownRing.vue'
import QuizLeaderboard from '@/Components/Quiz/QuizLeaderboard.vue'
import AppButton from '@/Components/AppButton.vue'
import { UsersIcon, ArrowRightIcon, XMarkIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  session: Object,
  join_url: String,
  qr_svg: String,
})

const status = ref(props.session.status)
const players = ref([...props.session.players])
const currentQuestion = ref(null)
const questionStartedAt = ref(null)
const questionIndex = ref(props.session.current_question_index)
const totalQuestions = ref(props.session.quiz.question_count)
const answeredCount = ref(0)
const totalPlayers = ref(players.value.length)
const distribution = ref({})
const correctOptionIds = ref([])
const leaderboard = ref([])
const podium = ref([])
const acting = ref(false)

let channel = null

onMounted(() => {
  channel = window.Echo.channel(`quiz-session.${props.session.game_pin}`)

  channel.listen('.player.joined', (payload) => {
    players.value.push(payload.player)
    totalPlayers.value = payload.player_count
  })

  channel.listen('.question.started', (payload) => {
    status.value = 'question_active'
    currentQuestion.value = payload.question
    questionStartedAt.value = payload.server_started_at
    questionIndex.value = payload.question_index
    totalQuestions.value = payload.total_questions
    answeredCount.value = 0
  })

  channel.listen('.question.ended', (payload) => {
    status.value = 'reveal'
    distribution.value = payload.distribution
    correctOptionIds.value = payload.correct_option_ids
    answeredCount.value = payload.answered_count
    totalPlayers.value = payload.total_players
  })

  channel.listen('.leaderboard.updated', (payload) => {
    status.value = 'leaderboard'
    leaderboard.value = payload.leaderboard
  })

  channel.listen('.session.ended', (payload) => {
    status.value = 'ended'
    podium.value = payload.podium
  })
})

onUnmounted(() => {
  if (channel) window.Echo?.leaveChannel(`quiz-session.${props.session.game_pin}`)
})

async function act(url) {
  acting.value = true
  try {
    await axios.post(url)
  } finally {
    acting.value = false
  }
}

const startSession = () => act(route('quiz.sessions.start', props.session.id))
const endQuestion = () => act(route('quiz.sessions.end-question', props.session.id))
const showLeaderboard = () => act(route('quiz.sessions.leaderboard', props.session.id))
const nextQuestion = () => act(route('quiz.sessions.next', props.session.id))

function endSession() {
  router.post(route('quiz.sessions.end', props.session.id))
}

function exitEarly() {
  if (confirm('End this session now?')) endSession()
}
</script>

<template>
  <Head :title="session.quiz.title" />

  <div class="min-h-screen bg-gradient-to-br from-[#0A2A5E] to-[#073E85] text-white flex flex-col">
    <!-- Top bar -->
    <div class="flex items-center justify-between px-6 py-4">
      <h1 class="font-heading font-semibold text-lg truncate">{{ session.quiz.title }}</h1>
      <button v-if="status !== 'ended'" @click="exitEarly" class="text-white/60 hover:text-white p-2">
        <XMarkIcon class="h-5 w-5" />
      </button>
    </div>

    <div class="flex-1 flex flex-col items-center justify-center px-6 pb-10">
      <!-- LOBBY -->
      <div v-if="status === 'lobby'" class="text-center w-full max-w-2xl">
        <QuizPinDisplay :pin="session.game_pin" class="mb-6" />
        <div class="flex flex-col sm:flex-row items-center justify-center gap-8 mb-8">
          <div class="bg-white rounded-xl p-3" v-html="qr_svg" />
          <div class="text-left">
            <p class="text-white/70 text-sm">Scan the QR code or go to</p>
            <p class="font-heading text-xl font-semibold break-all">{{ join_url }}</p>
          </div>
        </div>

        <div class="flex items-center justify-center gap-2 mb-6 text-white/80">
          <UsersIcon class="h-5 w-5" /> {{ players.length }} joined
        </div>
        <div class="flex flex-wrap justify-center gap-2 mb-8 max-w-xl mx-auto">
          <span v-for="p in players" :key="p.id" class="bg-white/10 rounded-full px-3 py-1 text-sm">{{ p.nickname }}</span>
        </div>

        <AppButton variant="success" size="lg" :loading="acting" :disabled="players.length === 0" @click="startSession">
          Start <ArrowRightIcon class="h-4 w-4" />
        </AppButton>
      </div>

      <!-- QUESTION ACTIVE -->
      <div v-else-if="status === 'question_active' && currentQuestion" class="w-full max-w-4xl">
        <div class="flex items-center justify-between mb-4 text-sm text-white/70">
          <span>Question {{ questionIndex + 1 }} / {{ totalQuestions }}</span>
          <span>{{ answeredCount }} / {{ totalPlayers }} answered</span>
        </div>
        <h2 class="text-2xl sm:text-3xl font-heading font-bold text-center mb-8">{{ currentQuestion.question_text }}</h2>
        <img v-if="currentQuestion.image" :src="currentQuestion.image" class="max-h-56 mx-auto rounded-lg mb-8" />

        <div class="flex justify-center mb-8">
          <QuizCountdownRing v-if="questionStartedAt" :duration-seconds="currentQuestion.time_limit_seconds" :started-at="questionStartedAt" @timeout="endQuestion" />
        </div>

        <div v-if="currentQuestion.options?.length" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <QuizAnswerTile
            v-for="opt in currentQuestion.options" :key="opt.id"
            :tile-index="opt.tile_index" :text="opt.option_text" :show-text="false" disabled
          />
        </div>

        <div class="text-center mt-6">
          <AppButton variant="secondary" :loading="acting" @click="endQuestion">Skip / End Question</AppButton>
        </div>
      </div>

      <!-- REVEAL -->
      <div v-else-if="status === 'reveal'" class="w-full max-w-3xl">
        <h2 class="text-xl font-heading font-semibold text-center mb-6">Results</h2>
        <div v-if="currentQuestion?.options?.length" class="space-y-2 mb-8">
          <div
            v-for="opt in currentQuestion.options" :key="opt.id"
            class="flex items-center gap-3 rounded-lg px-4 py-2.5"
            :class="correctOptionIds.includes(opt.id) ? 'bg-emerald-500/30 ring-2 ring-emerald-400' : 'bg-white/10'"
          >
            <span class="flex-1 truncate">{{ opt.option_text }}</span>
            <span class="font-semibold">{{ distribution[opt.id] ?? 0 }}</span>
          </div>
        </div>
        <p class="text-center text-white/70 mb-6">{{ answeredCount }} / {{ totalPlayers }} answered</p>
        <div class="text-center">
          <AppButton variant="success" size="lg" :loading="acting" @click="showLeaderboard">Show Leaderboard</AppButton>
        </div>
      </div>

      <!-- LEADERBOARD -->
      <div v-else-if="status === 'leaderboard'" class="w-full max-w-lg">
        <h2 class="text-xl font-heading font-semibold text-center mb-6">Leaderboard</h2>
        <QuizLeaderboard :players="leaderboard" class="mb-8" />
        <div class="text-center">
          <AppButton variant="success" size="lg" :loading="acting" @click="questionIndex + 1 < totalQuestions ? nextQuestion() : endSession()">
            {{ questionIndex + 1 < totalQuestions ? 'Next Question' : 'End Session' }}
          </AppButton>
        </div>
      </div>

      <!-- ENDED -->
      <div v-else-if="status === 'ended'" class="w-full max-w-lg text-center">
        <h2 class="text-2xl font-heading font-bold mb-8">Final Results</h2>
        <QuizLeaderboard :players="podium" podium class="mb-10" />
        <AppButton variant="secondary" size="lg" @click="router.visit(route('quiz.edit', session.quiz.id))">Back to Editor</AppButton>
      </div>
    </div>
  </div>
</template>
