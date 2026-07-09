<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import axios from 'axios'
import QuizPinDisplay from '@/Components/Quiz/QuizPinDisplay.vue'
import QuizCountdownRing from '@/Components/Quiz/QuizCountdownRing.vue'
import QuizLeaderboard from '@/Components/Quiz/QuizLeaderboard.vue'
import QuizConfetti from '@/Components/Quiz/QuizConfetti.vue'
import AppButton from '@/Components/AppButton.vue'
import { useQuizSounds } from '@/Composables/useQuizSounds'
import {
  UsersIcon, ArrowRightIcon, XMarkIcon, SpeakerWaveIcon, SpeakerXMarkIcon,
  DocumentChartBarIcon, ArrowPathIcon, CheckIcon,
} from '@heroicons/vue/24/outline'
import { BoltIcon } from '@heroicons/vue/24/solid'

const props = defineProps({
  session: Object,
  report_url: String,
  join_url: String,
  qr_svg: String,
})

const { muted, toggleMute, play, startLobbyLoop, stopLobbyLoop } = useQuizSounds()

// lobby | getready | question_active | reveal | leaderboard | ended
const status = ref(props.session.status)
const players = ref([...props.session.players])
const currentQuestion = ref(null)
const questionStartedAt = ref(null)
const questionIndex = ref(props.session.current_question_index)
const totalQuestions = ref(props.session.quiz.question_count)
const getReadyRemaining = ref(0)
const answeredCount = ref(0)
const totalPlayers = ref(players.value.length)
const distribution = ref({})
const correctOptionIds = ref([])
const leaderboard = ref([])
const podium = ref([])
const acting = ref(false)
const confetti = ref(false)

let channel = null
let getReadyTimer = null

const TILE_COLORS = ['bg-red-500', 'bg-blue-600', 'bg-amber-400', 'bg-emerald-500', 'bg-purple-500', 'bg-pink-500']

const maxDistribution = computed(() => Math.max(1, ...Object.values(distribution.value)))

function beginQuestion(payload) {
  currentQuestion.value = payload.question
  questionStartedAt.value = payload.server_started_at
  questionIndex.value = payload.question_index
  totalQuestions.value = payload.total_questions
  answeredCount.value = 0
  distribution.value = {}
  correctOptionIds.value = []
  stopLobbyLoop()

  clearInterval(getReadyTimer)
  const msUntilStart = new Date(payload.server_started_at).getTime() - Date.now()
  if (msUntilStart > 400) {
    status.value = 'getready'
    getReadyRemaining.value = Math.ceil(msUntilStart / 1000)
    play('countdown')
    getReadyTimer = setInterval(() => {
      const ms = new Date(payload.server_started_at).getTime() - Date.now()
      const next = Math.ceil(ms / 1000)
      if (ms <= 0) {
        clearInterval(getReadyTimer)
        status.value = 'question_active'
        play('start')
      } else if (next !== getReadyRemaining.value) {
        getReadyRemaining.value = next
        play('countdown')
      }
    }, 100)
  } else {
    status.value = 'question_active'
    play('start')
  }
}

onMounted(() => {
  channel = window.Echo.channel(`quiz-session.${props.session.game_pin}`)

  channel.listen('.player.joined', (payload) => {
    players.value.push(payload.player)
    totalPlayers.value = payload.player_count
    play('join')
  })

  channel.listen('.player.kicked', (payload) => {
    players.value = players.value.filter(p => p.id !== payload.player_id)
    totalPlayers.value = payload.player_count
  })

  channel.listen('.question.started', beginQuestion)

  channel.listen('.answer.submitted', (payload) => {
    answeredCount.value = payload.answered_count
    totalPlayers.value = payload.total_players
  })

  channel.listen('.question.ended', (payload) => {
    clearInterval(getReadyTimer)
    status.value = 'reveal'
    distribution.value = payload.distribution
    correctOptionIds.value = payload.correct_option_ids
    answeredCount.value = payload.answered_count
    totalPlayers.value = payload.total_players
    play('reveal')
  })

  channel.listen('.leaderboard.updated', (payload) => {
    status.value = 'leaderboard'
    leaderboard.value = payload.leaderboard
    play('reveal')
  })

  channel.listen('.session.ended', (payload) => {
    status.value = 'ended'
    podium.value = payload.podium
    stopLobbyLoop()
  })

  if (status.value === 'lobby') startLobbyLoop()
})

onUnmounted(() => {
  clearInterval(getReadyTimer)
  stopLobbyLoop()
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

async function kickPlayer(p) {
  if (!confirm(`Remove ${p.nickname} from the game?`)) return
  await axios.delete(route('quiz.sessions.players.kick', [props.session.id, p.id]))
}

function playAgain() {
  router.post(route('quiz.sessions.store', props.session.quiz.id), {
    require_roster_join: props.session.require_roster_join,
  })
}

function onWinnerRevealed() {
  confetti.value = true
  play('fanfare')
}
</script>

<template>
  <Head :title="session.quiz.title" />

  <div class="min-h-screen bg-gradient-to-br from-[#0A2A5E] to-[#073E85] text-white flex flex-col overflow-hidden">
    <QuizConfetti :active="confetti" />

    <!-- Top bar -->
    <div class="flex items-center justify-between px-6 py-4">
      <h1 class="font-heading font-semibold text-lg truncate">{{ session.quiz.title }}</h1>
      <div class="flex items-center gap-1">
        <button type="button" class="text-white/60 hover:text-white p-2" @click="toggleMute">
          <SpeakerXMarkIcon v-if="muted" class="h-5 w-5" />
          <SpeakerWaveIcon v-else class="h-5 w-5" />
        </button>
        <button v-if="status !== 'ended'" @click="exitEarly" class="text-white/60 hover:text-white p-2">
          <XMarkIcon class="h-5 w-5" />
        </button>
      </div>
    </div>

    <!-- Progress bar -->
    <div v-if="!['lobby', 'ended'].includes(status)" class="px-6">
      <div class="h-1 bg-white/10 rounded-full overflow-hidden">
        <div
          class="h-full bg-white/60 rounded-full transition-all duration-500"
          :style="{ width: `${((questionIndex + 1) / Math.max(totalQuestions, 1)) * 100}%` }"
        />
      </div>
    </div>

    <div class="flex-1 flex flex-col items-center justify-center px-6 pb-10">
      <!-- :duration => timer-based swap; transitionend never fires in hidden tabs -->
      <Transition name="qstate" mode="out-in" :duration="250">

        <!-- LOBBY -->
        <div v-if="status === 'lobby'" key="lobby" class="text-center w-full max-w-2xl">
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
          <TransitionGroup tag="div" name="chip" class="flex flex-wrap justify-center gap-2 mb-8 max-w-xl mx-auto">
            <span
              v-for="p in players" :key="p.id"
              class="group bg-white/10 hover:bg-white/20 rounded-full px-3 py-1 text-sm inline-flex items-center gap-1.5 transition-colors"
            >
              {{ p.nickname }}
              <button
                type="button"
                class="hidden group-hover:inline-flex text-white/50 hover:text-red-300"
                title="Remove player"
                @click="kickPlayer(p)"
              ><XMarkIcon class="h-3.5 w-3.5" /></button>
            </span>
          </TransitionGroup>

          <AppButton variant="success" size="lg" :loading="acting" :disabled="players.length === 0" @click="startSession">
            Start <ArrowRightIcon class="h-4 w-4" />
          </AppButton>
        </div>

        <!-- GET READY -->
        <div v-else-if="status === 'getready' && currentQuestion" key="getready" class="text-center w-full max-w-3xl">
          <p class="text-white/60 text-sm uppercase tracking-widest mb-3">Question {{ questionIndex + 1 }} / {{ totalQuestions }}</p>
          <span
            v-if="currentQuestion.double_points"
            class="inline-flex items-center gap-1 bg-amber-400/20 text-amber-300 rounded-full px-3 py-1 text-sm font-semibold mb-4"
          ><BoltIcon class="h-4 w-4" /> Double points!</span>
          <h2 class="text-3xl sm:text-4xl font-heading font-bold mb-8">{{ currentQuestion.question_text }}</h2>
          <div class="text-8xl font-heading font-bold quiz-getready-pulse">{{ getReadyRemaining }}</div>
        </div>

        <!-- QUESTION ACTIVE -->
        <div v-else-if="status === 'question_active' && currentQuestion" key="question" class="w-full max-w-4xl">
          <div class="flex items-center justify-between mb-4 text-sm text-white/70">
            <span>Question {{ questionIndex + 1 }} / {{ totalQuestions }}
              <span v-if="currentQuestion.double_points" class="ml-2 inline-flex items-center gap-0.5 text-amber-300 font-semibold"><BoltIcon class="h-3.5 w-3.5" /> 2×</span>
            </span>
            <span :key="answeredCount" class="quiz-count-bump font-semibold text-white">{{ answeredCount }} / {{ totalPlayers }} answered</span>
          </div>
          <h2 class="text-2xl sm:text-3xl font-heading font-bold text-center mb-8">{{ currentQuestion.question_text }}</h2>
          <img v-if="currentQuestion.image" :src="currentQuestion.image" class="max-h-56 mx-auto rounded-lg mb-8" />

          <div class="flex justify-center mb-8">
            <QuizCountdownRing
              v-if="questionStartedAt"
              :duration-seconds="currentQuestion.time_limit_seconds"
              :started-at="questionStartedAt"
              @timeout="endQuestion"
              @tick="play('tick')"
            />
          </div>

          <div v-if="currentQuestion.options?.length" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div
              v-for="(opt, i) in currentQuestion.options" :key="opt.id"
              class="quiz-tile-in flex items-center gap-3 rounded-xl px-5 py-4 font-heading font-semibold text-white"
              :class="TILE_COLORS[(opt.tile_index ?? i) % 6]"
              :style="{ animationDelay: `${i * 70}ms` }"
            >
              <span class="text-base sm:text-lg leading-snug break-words">{{ opt.option_text }}</span>
            </div>
          </div>

          <div class="text-center mt-6">
            <AppButton variant="secondary" :loading="acting" @click="endQuestion">Skip / End Question</AppButton>
          </div>
        </div>

        <!-- REVEAL — answer distribution chart, correct answer highlighted -->
        <div v-else-if="status === 'reveal'" key="reveal" class="w-full max-w-3xl">
          <h2 class="text-xl font-heading font-semibold text-center mb-2">Results</h2>
          <p v-if="currentQuestion" class="text-center text-white/70 mb-6">{{ currentQuestion.question_text }}</p>

          <div v-if="currentQuestion?.options?.length" class="space-y-3 mb-8">
            <div
              v-for="(opt, i) in currentQuestion.options" :key="opt.id"
              class="rounded-xl px-4 py-3 transition-all"
              :class="correctOptionIds.length && !correctOptionIds.includes(opt.id) ? 'bg-white/5 opacity-60' : 'bg-white/10'"
            >
              <div class="flex items-center gap-3 mb-2">
                <span :class="['h-4 w-4 rounded shrink-0', TILE_COLORS[(opt.tile_index ?? i) % 6]]" />
                <span class="flex-1 truncate font-medium">{{ opt.option_text }}</span>
                <CheckIcon v-if="correctOptionIds.includes(opt.id)" class="h-5 w-5 text-emerald-400 quiz-pop" />
                <span class="font-semibold text-sm">{{ distribution[opt.id] ?? 0 }}</span>
              </div>
              <div class="h-2.5 bg-white/10 rounded-full overflow-hidden">
                <div
                  class="h-full rounded-full quiz-bar"
                  :class="correctOptionIds.includes(opt.id) ? 'bg-emerald-400' : TILE_COLORS[(opt.tile_index ?? i) % 6]"
                  :style="{ width: `${((distribution[opt.id] ?? 0) / maxDistribution) * 100}%` }"
                />
              </div>
            </div>
          </div>

          <p class="text-center text-white/70 mb-6">{{ answeredCount }} / {{ totalPlayers }} answered</p>
          <div class="text-center">
            <AppButton variant="success" size="lg" :loading="acting" @click="showLeaderboard">Show Leaderboard</AppButton>
          </div>
        </div>

        <!-- LEADERBOARD -->
        <div v-else-if="status === 'leaderboard'" key="leaderboard" class="w-full max-w-lg">
          <h2 class="text-xl font-heading font-semibold text-center mb-6">Leaderboard</h2>
          <QuizLeaderboard :players="leaderboard.slice(0, 5)" class="mb-8" />
          <div class="text-center">
            <AppButton variant="success" size="lg" :loading="acting" @click="questionIndex + 1 < totalQuestions ? nextQuestion() : endSession()">
              {{ questionIndex + 1 < totalQuestions ? 'Next Question' : 'Finish — Show Podium' }}
            </AppButton>
          </div>
        </div>

        <!-- ENDED — podium ceremony -->
        <div v-else-if="status === 'ended'" key="ended" class="w-full max-w-lg text-center">
          <h2 class="text-2xl font-heading font-bold mb-8">Final Results</h2>
          <QuizLeaderboard :players="podium" podium class="mb-10" @winner-revealed="onWinnerRevealed" />
          <div class="flex flex-wrap items-center justify-center gap-3">
            <AppButton variant="primary" size="lg" @click="router.visit(report_url)">
              <DocumentChartBarIcon class="h-4 w-4" /> View Report
            </AppButton>
            <AppButton variant="success" size="lg" @click="playAgain">
              <ArrowPathIcon class="h-4 w-4" /> Play Again
            </AppButton>
            <AppButton variant="secondary" size="lg" @click="router.visit(route('quiz.edit', session.quiz.id))">Back to Editor</AppButton>
          </div>
        </div>

      </Transition>
    </div>
  </div>
</template>

<style scoped>
.qstate-enter-active, .qstate-leave-active { transition: opacity 0.22s ease, transform 0.22s ease; }
.qstate-enter-from { opacity: 0; transform: translateY(12px) scale(0.98); }
.qstate-leave-to { opacity: 0; transform: translateY(-8px) scale(0.98); }

.chip-enter-active { transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1); }
.chip-enter-from { opacity: 0; transform: scale(0.6); }
.chip-leave-active { transition: all 0.2s ease; }
.chip-leave-to { opacity: 0; transform: scale(0.6); }

@keyframes quiz-tile-in {
  from { opacity: 0; transform: translateY(14px) scale(0.94); }
  to { opacity: 1; transform: translateY(0) scale(1); }
}
.quiz-tile-in { opacity: 0; animation: quiz-tile-in 0.32s cubic-bezier(0.34, 1.56, 0.64, 1) forwards; }

@keyframes quiz-getready-pulse {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.12); }
}
.quiz-getready-pulse { animation: quiz-getready-pulse 1s ease-in-out infinite; }

@keyframes quiz-count-bump {
  0% { transform: scale(1.35); color: #6ee7b7; }
  100% { transform: scale(1); }
}
.quiz-count-bump { display: inline-block; animation: quiz-count-bump 0.35s ease; }

@keyframes quiz-pop {
  0% { transform: scale(0.3); opacity: 0; }
  70% { transform: scale(1.15); }
  100% { transform: scale(1); opacity: 1; }
}
.quiz-pop { animation: quiz-pop 0.45s cubic-bezier(0.34, 1.56, 0.64, 1); }

.quiz-bar { transition: width 0.7s cubic-bezier(0.22, 1, 0.36, 1); }
</style>
