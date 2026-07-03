<script setup>
import { TrophyIcon } from '@heroicons/vue/24/solid'

const props = defineProps({
  players: { type: Array, required: true }, // [{id, nickname, total_score, rank}]
  podium: { type: Boolean, default: false }, // final podium (top 3, big) vs in-game top list
  highlightPlayerId: { type: [Number, String], default: null },
})

const medalColor = ['text-amber-300', 'text-slate-300', 'text-orange-400']
</script>

<template>
  <!-- Final podium — 1st/2nd/3rd -->
  <div v-if="podium" class="flex items-end justify-center gap-4">
    <div v-for="(p, i) in players.slice(0, 3)" :key="p.id" :class="i === 0 ? 'order-2' : i === 1 ? 'order-1' : 'order-3'">
      <div class="text-center">
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
    </div>
  </div>

  <!-- In-game top list -->
  <ul v-else class="space-y-2 max-w-md mx-auto">
    <li
      v-for="p in players" :key="p.id"
      :class="[
        'flex items-center gap-3 rounded-xl px-4 py-2.5',
        p.id === highlightPlayerId ? 'bg-white/20 ring-2 ring-white' : 'bg-white/10',
      ]"
    >
      <span class="w-6 text-sm font-bold text-white/70">{{ p.rank }}</span>
      <span class="flex-1 text-white font-medium truncate">{{ p.nickname }}</span>
      <span class="text-white/80 text-sm font-semibold">{{ p.total_score }}</span>
    </li>
  </ul>
</template>
