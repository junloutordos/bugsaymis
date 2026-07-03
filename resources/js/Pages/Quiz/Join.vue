<script setup>
import { ref, onMounted } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import axios from 'axios'
import AppButton from '@/Components/AppButton.vue'
import { ArrowRightIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  pin: { type: String, default: null },
})

const step = ref('pin') // pin | nickname | roster
const pinInput = ref(props.pin ?? '')
const nickname = ref('')
const roster = ref([])
const selectedStudentId = ref('')
const quizTitle = ref('')
const requireRosterJoin = ref(false)
const error = ref('')
const busy = ref(false)

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
  if (props.pin) lookupPin()
})
</script>

<template>
  <Head title="Join Quiz" />

  <div class="min-h-screen bg-gradient-to-br from-[#0A2A5E] to-[#019FE6] text-white flex items-center justify-center px-4">
    <div class="w-full max-w-sm">
      <h1 class="text-center font-heading font-bold text-2xl mb-8">Join Quiz</h1>

      <!-- PIN step -->
      <form v-if="step === 'pin'" @submit.prevent="lookupPin" class="space-y-4">
        <input
          v-model="pinInput"
          maxlength="6" inputmode="numeric" pattern="[0-9]*"
          placeholder="Game PIN"
          class="w-full text-center text-3xl tracking-[0.3em] font-heading font-bold rounded-xl bg-white/10 border border-white/20 py-4 text-white placeholder-white/40 focus:outline-none focus:ring-2 focus:ring-white"
        />
        <AppButton type="submit" variant="primary" size="lg" block :loading="busy" :disabled="pinInput.length !== 6">
          Enter <ArrowRightIcon class="h-4 w-4" />
        </AppButton>
      </form>

      <!-- Nickname step -->
      <form v-else-if="step === 'nickname'" @submit.prevent="joinGame" class="space-y-4">
        <p class="text-center text-white/70 text-sm">{{ quizTitle }}</p>
        <input
          v-model="nickname"
          maxlength="40"
          placeholder="Your nickname"
          class="w-full text-center text-xl font-medium rounded-xl bg-white/10 border border-white/20 py-3.5 text-white placeholder-white/40 focus:outline-none focus:ring-2 focus:ring-white"
        />
        <AppButton type="submit" variant="success" size="lg" block :loading="busy" :disabled="!nickname.trim()">
          Join <ArrowRightIcon class="h-4 w-4" />
        </AppButton>
      </form>

      <!-- Roster step -->
      <form v-else-if="step === 'roster'" @submit.prevent="joinGame" class="space-y-4">
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

      <p v-if="error" class="text-center text-red-200 text-sm mt-4">{{ error }}</p>
    </div>
  </div>
</template>
