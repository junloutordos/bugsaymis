<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import axios from 'axios'
import QuizAnswerTile from '@/Components/Quiz/QuizAnswerTile.vue'
import QuizCountdownRing from '@/Components/Quiz/QuizCountdownRing.vue'
import QuizConfetti from '@/Components/Quiz/QuizConfetti.vue'
import { useQuizSounds } from '@/Composables/useQuizSounds'
import { CheckCircleIcon, XCircleIcon, FireIcon, BoltIcon } from '@heroicons/vue/24/solid'
import { SpeakerWaveIcon, SpeakerXMarkIcon, LockClosedIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  player: Object,
  session: Object,
  player_token: String,
})

const { muted, toggleMute, play } = useQuizSounds()

// waiting | getready | question | locked | feedback | leaderboard | ended | kicked
const uiState = ref('waiting')
const currentQuestion = ref(null)
const questionStartedAt = ref(null)
const questionIndex = ref(0)
const totalQuestions = ref(0)
const getReadyRemaining = ref(0)
const selectedOptionIds = ref([])
const answerText = ref('')
const totalScore = ref(props.player.total_score)
const streak = ref(0)
const feedback = ref(null) // { answered, is_correct, points_awarded, timedOut }
const displayedPoints = ref(0)
const correctOptionIds = ref([])
const myRank = ref(null)
const rankDelta = ref(0)
const nemesis = ref(null)
const podium = ref([])
const submitting = ref(false)
const confetti = ref(false)

let channel = null
let getReadyTimer = null
let prevRank = null

const isCheckbox = computed(() => currentQuestion.value?.type === 'checkbox')
const correctOptionTexts = computed(() =>
  (currentQuestion.value?.options ?? [])
    .filter(o => correctOptionIds.value.includes(o.id))
    .map(o => o.option_text)
)

// ── Question lifecycle ─────────────────────────────────────────────────────

function beginQuestion(question, serverStartedAt, index, total) {
  currentQuestion.value = question
  questionStartedAt.value = serverStartedAt
  if (index !== undefined) questionIndex.value = index
  if (total !== undefined) totalQuestions.value = total
  selectedOptionIds.value = []
  answerText.value = ''
  feedback.value = null
  correctOptionIds.value = []
  confetti.value = false

  // "Get ready" intro — the server sets question_started_at in the future so
  // reading time never eats into the answer clock.
  clearInterval(getReadyTimer)
  const msUntilStart = new Date(serverStartedAt).getTime() - Date.now()
  if (msUntilStart > 400) {
    uiState.value = 'getready'
    getReadyRemaining.value = Math.ceil(msUntilStart / 1000)
    play('countdown')
    getReadyTimer = setInterval(() => {
      const ms = new Date(serverStartedAt).getTime() - Date.now()
      const next = Math.ceil(ms / 1000)
      if (ms <= 0) {
        clearInterval(getReadyTimer)
        uiState.value = 'question'
        play('start')
      } else if (next !== getReadyRemaining.value) {
        getReadyRemaining.value = next
        play('countdown')
      }
    }, 100)
  } else {
    uiState.value = 'question'
    play('start')
  }
}

/**
 * Correctness is server-gated: the /result endpoint 409s while the question is
 * live, so this only ever runs after the host has ended the question.
 */
async function showResult(ranOutOfTime) {
  let data = null
  try {
    ({ data } = await axios.get(route('quiz.play.result', props.player_token)))
  } catch {
    // Result not available (409/network) — treat as unanswered.
  }

  const answered = data?.answered ?? false
  if (data) {
    totalScore.value = data.total_score
    streak.value = data.current_streak ?? 0
  }
  feedback.value = {
    answered,
    is_correct: data?.is_correct ?? null,
    points_awarded: data?.points_awarded ?? 0,
    timedOut: !answered && ranOutOfTime,
  }
  uiState.value = 'feedback'

  if (feedback.value.is_correct === true) {
    play('correct')
    animatePoints(feedback.value.points_awarded)
  } else if (!answered || feedback.value.is_correct === false) {
    play('incorrect')
  } else {
    play('reveal') // poll / open-ended — no right answer
  }
}

function animatePoints(to) {
  displayedPoints.value = 0
  const t0 = performance.now()
  const step = (t) => {
    const k = Math.min((t - t0) / 700, 1)
    displayedPoints.value = Math.round(to * (1 - Math.pow(1 - k, 3)))
    if (k < 1) requestAnimationFrame(step)
  }
  requestAnimationFrame(step)
}

// ── Reconnect / refresh ────────────────────────────────────────────────────

async function loadState() {
  const { data } = await axios.get(route('quiz.play.state', props.player_token))
  totalScore.value = data.total_score
  streak.value = data.current_streak ?? 0
  questionIndex.value = data.question_index ?? 0
  totalQuestions.value = data.total_questions ?? 0

  if (data.status === 'question_active' && data.question) {
    if (data.already_answered) {
      currentQuestion.value = data.question
      questionStartedAt.value = data.question_started_at
      uiState.value = 'locked'
    } else {
      beginQuestion(data.question, data.question_started_at)
    }
  } else if (data.status === 'reveal' && data.question) {
    currentQuestion.value = data.question
    await showResult(false)
  } else if (data.status === 'ended') {
    uiState.value = 'ended'
  } else if (data.status === 'leaderboard') {
    uiState.value = 'leaderboard'
  } else {
    uiState.value = 'waiting'
  }
}

// ── Echo ───────────────────────────────────────────────────────────────────

function subscribe() {
  channel = window.Echo.channel(`quiz-session.${props.session.game_pin}`)

  channel.listen('.question.started', (payload) => {
    beginQuestion(payload.question, payload.server_started_at, payload.question_index, payload.total_questions)
  })

  channel.listen('.question.ended', async (payload) => {
    if (['leaderboard', 'ended', 'kicked'].includes(uiState.value)) return
    clearInterval(getReadyTimer)
    correctOptionIds.value = payload.correct_option_ids ?? []
    await showResult(uiState.value === 'question' || uiState.value === 'getready')
  })

  channel.listen('.leaderboard.updated', (payload) => {
    const mine = payload.leaderboard.find(p => p.id === props.player.id)
    if (mine) {
      rankDelta.value = prevRank !== null ? prevRank - mine.rank : 0
      prevRank = mine.rank
      totalScore.value = mine.total_score
      const above = payload.leaderboard.find(p => p.rank === mine.rank - 1)
      nemesis.value = above ? { nickname: above.nickname, gap: above.total_score - mine.total_score } : null
    }
    myRank.value = mine ?? null
    uiState.value = 'leaderboard'
    play('reveal')
  })

  channel.listen('.session.ended', (payload) => {
    podium.value = payload.podium ?? []
    uiState.value = 'ended'
    if (podium.value.some(p => p.id === props.player.id)) {
      confetti.value = true
      play('fanfare')
    } else {
      play('reveal')
    }
  })

  channel.listen('.player.kicked', (payload) => {
    if (payload.player_id === props.player.id) {
      uiState.value = 'kicked'
      window.Echo?.leaveChannel(`quiz-session.${props.session.game_pin}`)
      channel = null
    }
  })
}

onMounted(async () => {
  await loadState()
  subscribe()
})

onUnmounted(() => {
  clearInterval(getReadyTimer)
  if (channel) window.Echo?.leaveChannel(`quiz-session.${props.session.game_pin}`)
})

// ── Answering ──────────────────────────────────────────────────────────────

function tapOption(id) {
  if (uiState.value !== 'question' || submitting.value) return
  if (isCheckbox.value) {
    const i = selectedOptionIds.value.indexOf(id)
    i === -1 ? selectedOptionIds.value.push(id) : selectedOptionIds.value.splice(i, 1)
  } else {
    selectedOptionIds.value = [id]
    submitAnswer()
  }
}

async function submitAnswer() {
  if (submitting.value || uiState.value !== 'question') return
  submitting.value = true
  uiState.value = 'locked'
  play('lockin')

  try {
    await axios.post(route('quiz.play.answer', props.player_token), {
      selected_option_ids: selectedOptionIds.value,
      answer_text: currentQuestion.value?.type === 'open_ended' ? answerText.value : null,
    })
  } catch {
    // Question probably just ended — the reveal broadcast takes over. Only
    // fall back to the question if no reveal has arrived.
    if (uiState.value === 'locked' && !feedback.value) uiState.value = 'question'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <Head title="Playing..." />

  <div class="min-h-screen bg-gradient-to-br from-[#0A2A5E] to-[#073E85] text-white flex flex-col overflow-hidden">
    <QuizConfetti :active="confetti" />

    <!-- Top bar -->
    <div class="flex items-center justify-between gap-2 px-4 py-3 text-sm">
      <span class="font-medium truncate">{{ player.nickname }}</span>
      <div class="flex items-center gap-3 shrink-0">
        <span
          v-if="streak >= 2"
          class="inline-flex items-center gap-1 bg-amber-400/20 text-amber-300 rounded-full px-2 py-0.5 text-xs font-semibold"
        ><FireIcon class="h-3.5 w-3.5" /> {{ streak }}</span>
        <span class="font-heading font-semibold">{{ totalScore }} pts</span>
        <button type="button" class="text-white/50 hover:text-white" @click="toggleMute">
          <SpeakerXMarkIcon v-if="muted" class="h-5 w-5" />
          <SpeakerWaveIcon v-else class="h-5 w-5" />
        </button>
      </div>
    </div>

    <div class="flex-1 flex flex-col items-center justify-center px-4 pb-8">
      <!-- :duration makes Vue use a timer instead of transitionend — hidden
           (backgrounded) tabs never fire transitionend, which would freeze the
           screen on the old state until the player returns to the tab. -->
      <Transition name="qstate" mode="out-in" :duration="250">

        <!-- WAITING -->
        <div v-if="uiState === 'waiting'" key="waiting" class="text-center">
          <div class="text-2xl font-heading font-bold mb-3">You're in!</div>
          <div class="flex items-center justify-center gap-1.5 mb-3">
            <span v-for="i in 3" :key="i" class="h-2.5 w-2.5 rounded-full bg-white/60 quiz-dot" :style="{ animationDelay: `${(i - 1) * 0.18}s` }" />
          </div>
          <p class="text-white/60 text-sm">Waiting for the host to start...</p>
        </div>

        <!-- GET READY -->
        <div v-else-if="uiState === 'getready' && currentQuestion" key="getready" class="text-center w-full max-w-md">
          <p class="text-white/60 text-sm uppercase tracking-widest mb-2">Question {{ questionIndex + 1 }} / {{ totalQuestions }}</p>
          <span
            v-if="currentQuestion.double_points"
            class="inline-flex items-center gap-1 bg-amber-400/20 text-amber-300 rounded-full px-3 py-1 text-xs font-semibold mb-3"
          ><BoltIcon class="h-4 w-4" /> Double points!</span>
          <h2 class="text-xl font-heading font-bold mb-6">{{ currentQuestion.question_text }}</h2>
          <div class="text-7xl font-heading font-bold quiz-getready-pulse">{{ getReadyRemaining }}</div>
        </div>

        <!-- QUESTION -->
        <div v-else-if="uiState === 'question' && currentQuestion" key="question" class="w-full max-w-md">
          <div class="flex justify-center mb-6" v-if="questionStartedAt">
            <QuizCountdownRing
              :duration-seconds="currentQuestion.time_limit_seconds"
              :started-at="questionStartedAt"
              @tick="play('tick')"
            />
          </div>

          <div v-if="currentQuestion.type === 'open_ended'" class="space-y-3">
            <input
              v-model="answerText"
              placeholder="Type your answer..."
              class="w-full rounded-xl bg-white/10 border border-white/20 py-3 px-4 text-white placeholder-white/40 focus:outline-none focus:ring-2 focus:ring-white"
            />
            <button
              type="button" class="w-full bg-emerald-500 hover:bg-emerald-400 rounded-xl py-3 font-semibold transition-colors disabled:opacity-40"
              :disabled="!answerText.trim()" @click="submitAnswer"
            >Submit</button>
          </div>

          <template v-else>
            <div class="grid grid-cols-2 gap-3">
              <div
                v-for="(opt, i) in currentQuestion.options" :key="opt.id"
                class="quiz-tile-in" :style="{ animationDelay: `${i * 70}ms` }"
              >
                <QuizAnswerTile
                  :tile-index="opt.tile_index ?? i" :text="opt.option_text"
                  :selected="selectedOptionIds.includes(opt.id)"
                  class="w-full h-full"
                  @click="tapOption(opt.id)"
                />
              </div>
            </div>
            <button
              v-if="isCheckbox"
              type="button" class="w-full mt-3 bg-emerald-500 hover:bg-emerald-400 rounded-xl py-3 font-semibold transition-colors disabled:opacity-40"
              :disabled="selectedOptionIds.length === 0" @click="submitAnswer"
            >Submit {{ selectedOptionIds.length ? `(${selectedOptionIds.length} selected)` : '' }}</button>
          </template>
        </div>

        <!-- LOCKED (submitted, waiting for reveal) -->
        <div v-else-if="uiState === 'locked'" key="locked" class="text-center">
          <LockClosedIcon class="h-12 w-12 mx-auto mb-3 text-white/70 quiz-getready-pulse" />
          <div class="text-2xl font-heading font-bold mb-2">Answer locked in!</div>
          <p class="text-white/60 text-sm">Waiting for the reveal...</p>
        </div>

        <!-- FEEDBACK (only after the host ends the question) -->
        <div v-else-if="uiState === 'feedback' && feedback" key="feedback" class="text-center w-full max-w-md">
          <template v-if="!feedback.answered">
            <XCircleIcon class="h-16 w-16 mx-auto mb-3 text-red-400 quiz-shake" />
            <p class="text-xl font-heading font-bold">{{ feedback.timedOut ? "Time's up!" : 'No answer submitted' }}</p>
          </template>
          <template v-else-if="feedback.is_correct === true">
            <CheckCircleIcon class="h-16 w-16 mx-auto mb-3 text-emerald-400 quiz-pop" />
            <p class="text-xl font-heading font-bold">Correct! <span class="text-emerald-300">+{{ displayedPoints }}</span></p>
            <p v-if="streak >= 2" class="mt-2 inline-flex items-center gap-1 text-amber-300 text-sm font-semibold">
              <FireIcon class="h-4 w-4" /> {{ streak }} in a row!
            </p>
          </template>
          <template v-else-if="feedback.is_correct === false">
            <XCircleIcon class="h-16 w-16 mx-auto mb-3 text-red-400 quiz-shake" />
            <p class="text-xl font-heading font-bold">Not quite</p>
          </template>
          <template v-else>
            <CheckCircleIcon class="h-16 w-16 mx-auto mb-3 text-blue-300 quiz-pop" />
            <p class="text-xl font-heading font-bold">Answer recorded!</p>
          </template>

          <div v-if="feedback.is_correct === false && correctOptionTexts.length" class="mt-4 bg-white/10 rounded-xl px-4 py-3 text-sm">
            <p class="text-white/60 mb-1">Correct answer{{ correctOptionTexts.length > 1 ? 's' : '' }}</p>
            <p class="font-semibold">{{ correctOptionTexts.join(' · ') }}</p>
          </div>

          <p class="text-white/60 text-sm mt-4">Waiting for results...</p>
        </div>

        <!-- LEADERBOARD (own rank only) -->
        <div v-else-if="uiState === 'leaderboard'" key="leaderboard" class="text-center">
          <p class="text-white/60 text-sm mb-1">You're in</p>
          <p class="text-5xl font-heading font-bold mb-1">
            #{{ myRank?.rank ?? '-' }}
            <span v-if="rankDelta > 0" class="text-2xl text-emerald-400 align-middle">▲{{ rankDelta }}</span>
            <span v-else-if="rankDelta < 0" class="text-2xl text-red-400 align-middle">▼{{ -rankDelta }}</span>
          </p>
          <p class="text-white/70 mb-2">{{ totalScore }} points</p>
          <p v-if="nemesis && nemesis.gap > 0" class="text-white/50 text-sm">{{ nemesis.gap }} pts behind {{ nemesis.nickname }}</p>
        </div>

        <!-- ENDED -->
        <div v-else-if="uiState === 'ended'" key="ended" class="text-center">
          <p class="text-2xl font-heading font-bold mb-4">Game over!</p>
          <p v-if="podium.some(p => p.id === player.id)" class="text-amber-300 font-heading font-semibold text-lg mb-2">
            🏆 You made the podium!
          </p>
          <p class="text-white/70 mb-1">Final score</p>
          <p class="text-4xl font-heading font-bold">{{ totalScore }}</p>
        </div>

        <!-- KICKED -->
        <div v-else-if="uiState === 'kicked'" key="kicked" class="text-center">
          <XCircleIcon class="h-14 w-14 mx-auto mb-3 text-red-400" />
          <p class="text-xl font-heading font-bold mb-2">Removed from the game</p>
          <p class="text-white/60 text-sm">The host removed you from this session.</p>
        </div>

      </Transition>
    </div>
  </div>
</template>

<style scoped>
.qstate-enter-active, .qstate-leave-active { transition: opacity 0.22s ease, transform 0.22s ease; }
.qstate-enter-from { opacity: 0; transform: translateY(12px) scale(0.98); }
.qstate-leave-to { opacity: 0; transform: translateY(-8px) scale(0.98); }

@keyframes quiz-tile-in {
  from { opacity: 0; transform: translateY(14px) scale(0.94); }
  to { opacity: 1; transform: translateY(0) scale(1); }
}
.quiz-tile-in { opacity: 0; animation: quiz-tile-in 0.32s cubic-bezier(0.34, 1.56, 0.64, 1) forwards; }

@keyframes quiz-dot-bounce {
  0%, 80%, 100% { transform: translateY(0); opacity: 0.5; }
  40% { transform: translateY(-8px); opacity: 1; }
}
.quiz-dot { animation: quiz-dot-bounce 1.2s ease-in-out infinite; }

@keyframes quiz-getready-pulse {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.12); }
}
.quiz-getready-pulse { animation: quiz-getready-pulse 1s ease-in-out infinite; }

@keyframes quiz-pop {
  0% { transform: scale(0.3); opacity: 0; }
  70% { transform: scale(1.15); }
  100% { transform: scale(1); opacity: 1; }
}
.quiz-pop { animation: quiz-pop 0.45s cubic-bezier(0.34, 1.56, 0.64, 1); }

@keyframes quiz-shake {
  0%, 100% { transform: translateX(0); }
  20% { transform: translateX(-7px); }
  40% { transform: translateX(7px); }
  60% { transform: translateX(-5px); }
  80% { transform: translateX(5px); }
}
.quiz-shake { animation: quiz-shake 0.45s ease; }
</style>
