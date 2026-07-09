<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import axios from 'axios'
import AppButton from '@/Components/AppButton.vue'
import { ArrowRightIcon, SparklesIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  pin: { type: String, default: null },
})

const step = ref('pin') // pin | nickname | roster
const pinDigits = ref(['', '', '', '', '', ''])
const pinBoxes = ref([])
const nickname = ref('')
const roster = ref([])
const selectedStudentId = ref('')
const quizTitle = ref('')
const requireRosterJoin = ref(false)
const error = ref('')
const busy = ref(false)

const pinInput = computed(() => pinDigits.value.join(''))

// ── Segmented PIN input ────────────────────────────────────────────────────

function onDigitInput(i, e) {
  const v = e.target.value.replace(/\D/g, '')
  pinDigits.value[i] = v.slice(-1)
  if (pinDigits.value[i] && i < 5) pinBoxes.value[i + 1]?.focus()
}

function onDigitKeydown(i, e) {
  if (e.key === 'Backspace' && !pinDigits.value[i] && i > 0) {
    pinBoxes.value[i - 1]?.focus()
  }
}

function onPinPaste(e) {
  const text = (e.clipboardData?.getData('text') ?? '').replace(/\D/g, '').slice(0, 6)
  if (!text) return
  e.preventDefault()
  pinDigits.value = Array.from({ length: 6 }, (_, i) => text[i] ?? '')
  pinBoxes.value[Math.min(text.length, 5)]?.focus()
}

// Auto-lookup the moment all six digits are in — one less tap on a phone.
watch(pinInput, (v) => {
  if (v.length === 6 && step.value === 'pin' && !busy.value) lookupPin()
})

// ── Nickname generator ─────────────────────────────────────────────────────

const NICK_ADJ = ['Swift', 'Brave', 'Clever', 'Sunny', 'Mighty', 'Lucky', 'Cosmic', 'Turbo', 'Golden', 'Zesty', 'Radiant', 'Daring', 'Nimble', 'Jolly', 'Epic', 'Blazing']
const NICK_ANIMAL = ['Tarsier', 'Eagle', 'Carabao', 'Dolphin', 'Tamaraw', 'Falcon', 'Otter', 'Gecko', 'Marlin', 'Civet', 'Hornbill', 'Dugong', 'Bearcat', 'Sunbird', 'Pawikan', 'Maya']

function rollNickname() {
  const adj = NICK_ADJ[Math.floor(Math.random() * NICK_ADJ.length)]
  const animal = NICK_ANIMAL[Math.floor(Math.random() * NICK_ANIMAL.length)]
  nickname.value = `${adj}${animal}${Math.floor(Math.random() * 90) + 10}`
}

// ── Join flow ──────────────────────────────────────────────────────────────

async function lookupPin() {
  if (pinInput.value.length !== 6) return
  busy.value = true
  error.value = ''
  try {
    const { data } = await axios.post(route('quiz.lookup-pin'), { pin: pinInput.value })
    quizTitle.value = data.quiz_title
    requireRosterJoin.value = data.require_roster_join
    roster.value = data.roster ?? []
    step.value = data.require_roster_join ? 'roster' : 'nickname'
  } catch (e) {
    error.value = e.response?.data?.message ?? 'That PIN isn\'t live right now.'
  } finally {
    busy.value = false
  }
}

async function joinGame() {
  const name = requireRosterJoin.value
    ? roster.value.find(s => String(s.student_id) === String(selectedStudentId.value))?.name
    : nickname.value

  if (!name) return
  busy.value = true
  error.value = ''
  try {
    const { data } = await axios.post(route('quiz.players.join'), {
      pin: pinInput.value,
      nickname: name,
      student_id: requireRosterJoin.value ? selectedStudentId.value : null,
    })
    sessionStorage.setItem('quiz_player_token', data.player_token)
    router.visit(data.play_url)
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Could not join — the game may have already started.'
  } finally {
    busy.value = false
  }
}

onMounted(() => {
  if (props.pin?.length === 6) {
    pinDigits.value = props.pin.split('')
    lookupPin()
  } else {
    pinBoxes.value[0]?.focus()
  }
})
</script>

<template>
  <Head title="Join Quiz" />

  <div class="min-h-screen bg-gradient-to-br from-[#0A2A5E] to-[#019FE6] text-white flex items-center justify-center px-4">
    <div class="w-full max-w-sm">
      <h1 class="text-center font-heading font-bold text-2xl mb-8">Join Quiz</h1>

      <Transition name="jstep" mode="out-in" :duration="220">
        <!-- PIN step — segmented six-digit input -->
        <form v-if="step === 'pin'" key="pin" @submit.prevent="lookupPin" class="space-y-5">
          <div class="flex justify-center gap-2" @paste="onPinPaste">
            <input
              v-for="(d, i) in pinDigits" :key="i"
              :ref="el => (pinBoxes[i] = el)"
              :value="d"
              inputmode="numeric" autocomplete="one-time-code" maxlength="2"
              class="h-14 w-11 text-center text-2xl font-heading font-bold rounded-xl bg-white/10 border border-white/20 text-white focus:outline-none focus:ring-2 focus:ring-white focus:bg-white/20 transition-colors"
              @input="onDigitInput(i, $event)"
              @keydown="onDigitKeydown(i, $event)"
            />
          </div>
          <p class="text-center text-white/50 text-xs">Enter the game PIN shown on the host screen</p>
          <AppButton type="submit" variant="primary" size="lg" block :loading="busy" :disabled="pinInput.length !== 6">
            Enter <ArrowRightIcon class="h-4 w-4" />
          </AppButton>
        </form>

        <!-- Nickname step -->
        <form v-else-if="step === 'nickname'" key="nickname" @submit.prevent="joinGame" class="space-y-4">
          <p class="text-center text-white/70 text-sm">{{ quizTitle }}</p>
          <div class="relative">
            <input
              v-model="nickname"
              maxlength="40"
              placeholder="Your nickname"
              class="w-full text-center text-xl font-medium rounded-xl bg-white/10 border border-white/20 py-3.5 px-12 text-white placeholder-white/40 focus:outline-none focus:ring-2 focus:ring-white"
            />
            <button
              type="button"
              class="absolute right-3 top-1/2 -translate-y-1/2 text-white/50 hover:text-amber-300 transition-colors"
              title="Random nickname"
              @click="rollNickname"
            ><SparklesIcon class="h-6 w-6" /></button>
          </div>
          <AppButton type="submit" variant="success" size="lg" block :loading="busy" :disabled="!nickname.trim()">
            Join <ArrowRightIcon class="h-4 w-4" />
          </AppButton>
        </form>

        <!-- Roster step -->
        <form v-else-if="step === 'roster'" key="roster" @submit.prevent="joinGame" class="space-y-4">
          <p class="text-center text-white/70 text-sm">{{ quizTitle }} — pick your name</p>
          <select
            v-model="selectedStudentId"
            class="w-full rounded-xl bg-white/10 border border-white/20 py-3.5 px-3 text-white focus:outline-none focus:ring-2 focus:ring-white"
          >
            <option value="" disabled>Select your name</option>
            <option v-for="s in roster" :key="s.student_id" :value="s.student_id" class="text-slate-800">{{ s.name }}</option>
          </select>
          <AppButton type="submit" variant="success" size="lg" block :loading="busy" :disabled="!selectedStudentId">
            Join <ArrowRightIcon class="h-4 w-4" />
          </AppButton>
        </form>
      </Transition>

      <p v-if="error" class="text-center text-red-200 text-sm mt-4">{{ error }}</p>
    </div>
  </div>
</template>

<style scoped>
.jstep-enter-active, .jstep-leave-active { transition: opacity 0.2s ease, transform 0.2s ease; }
.jstep-enter-from { opacity: 0; transform: translateY(10px); }
.jstep-leave-to { opacity: 0; transform: translateY(-8px); }
</style>
