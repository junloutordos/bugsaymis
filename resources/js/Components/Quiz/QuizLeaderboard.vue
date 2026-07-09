<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { TrophyIcon } from '@heroicons/vue/24/solid'

const props = defineProps({
  players: { type: Array, required: true }, // [{id, nickname, total_score, rank, current_streak?}]
  podium: { type: Boolean, default: false }, // final podium (top 3, big) vs in-game top list
  highlightPlayerId: { type: [Number, String], default: null },
})
const emit = defineEmits(['winner-revealed'])

const medalColor = ['text-amber-300', 'text-slate-300', 'text-orange-400']

// Staged podium ceremony — 3rd place first, then 2nd, then the winner.
const revealed = ref(props.podium ? 0 : 3)
let timers = []

onMounted(() => {
  if (!props.podium) return
  ;[1, 2, 3].forEach((step, i) => {
    timers.push(setTimeout(() => {
      revealed.value = step
      if (step === 3) emit('winner-revealed')
    }, 700 + i * 1100))
  })
})
onBeforeUnmount(() => timers.forEach(clearTimeout))

function isShown(index) {
  // players[] is rank order: index 0 = 1st (revealed last), 2 = 3rd (first)
  return revealed.value >= 3 - index
}
</script>

<template>
  <!-- Final podium — 1st/2nd/3rd -->
  <div v-if="podium" class="flex items-end justify-center gap-4">
    <div v-for="(p, i) in players.slice(0, 3)" :key="p.id" :class="i === 0 ? 'order-2' : i === 1 ? 'order-1' : 'order-3'">
      <Transition name="podium-pop">
        <div v-show="isShown(i)" class="text-center">
          <TrophyIcon :class="['h-8 w-8 mx-auto mb-2', medalColor[i]]" />
          <div
            :class="[
              'rounded-t-xl bg-white/10 backdrop-blur-sm px-6 flex flex-col items-center justify-end',
              i === 0 ? 'h-36' : i === 1 ? 'h-28' : 'h-20',
            ]"
          >
            <p class="text-white font-heading font-semibold truncate max-w-[8rem]">{{ p.nickname }}</p>
            <p class="text-white/70 text-sm mb-3">{{ p.total_score }} pts</p>
          </div>
        </div>
      </Transition>
    </div>
  </div>

  <!-- In-game top list — FLIP-animated so rank changes visibly reshuffle -->
  <TransitionGroup v-else tag="ul" name="lb" class="space-y-2 max-w-md mx-auto relative">
    <li
      v-for="p in players" :key="p.id"
      :class="[
        'flex items-center gap-3 rounded-xl px-4 py-2.5',
        p.id === highlightPlayerId ? 'bg-white/20 ring-2 ring-white' : 'bg-white/10',
      ]"
    >
      <span class="w-6 text-sm font-bold text-white/70">{{ p.rank }}</span>
      <span class="flex-1 text-white font-medium truncate">{{ p.nickname }}</span>
      <span v-if="(p.current_streak ?? 0) >= 2" class="text-xs font-semibold text-amber-300" title="Answer streak">🔥{{ p.current_streak }}</span>
      <span class="text-white/80 text-sm font-semibold">{{ p.total_score }}</span>
    </li>
  </TransitionGroup>
</template>

<style scoped>
.lb-move { transition: transform 0.6s cubic-bezier(0.22, 1, 0.36, 1); }
.lb-enter-active { transition: all 0.4s ease; }
.lb-enter-from { opacity: 0; transform: translateY(10px); }

.podium-pop-enter-active { transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1); }
.podium-pop-enter-from { opacity: 0; transform: translateY(40px) scale(0.7); }
</style>
