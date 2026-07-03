<script setup>
// Kahoot-style tile — shape AND color are redundant on purpose (accessibility:
// never rely on color alone to distinguish answers).
const props = defineProps({
  tileIndex: { type: Number, required: true }, // 0-3, picks shape+color
  text: { type: String, default: '' },
  showText: { type: Boolean, default: true },
  selected: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  reveal: { type: Boolean, default: false },   // reveal mode — show correct/incorrect + count
  correct: { type: Boolean, default: null },
  count: { type: Number, default: null },
})
defineEmits(['click'])

const STYLES = [
  { bg: 'bg-red-500',    shape: 'triangle' },
  { bg: 'bg-blue-600',   shape: 'diamond' },
  { bg: 'bg-amber-400',  shape: 'circle' },
  { bg: 'bg-emerald-500', shape: 'square' },
]
</script>

<template>
  <button
    type="button"
    :disabled="disabled"
    @click="$emit('click')"
    :class="[
      'relative flex items-center gap-3 rounded-xl px-5 py-6 text-left font-heading font-semibold text-white transition-all',
      STYLES[tileIndex % 4].bg,
      selected ? 'ring-4 ring-white scale-[0.98]' : '',
      disabled ? 'opacity-50 cursor-not-allowed' : 'active:scale-[0.97]',
      reveal && correct === false ? 'opacity-40' : '',
      reveal && correct === true ? 'ring-4 ring-white' : '',
    ]"
  >
    <svg v-if="STYLES[tileIndex % 4].shape === 'triangle'" viewBox="0 0 24 24" class="h-6 w-6 shrink-0 fill-white/90"><path d="M12 3 22 21H2z" /></svg>
    <svg v-else-if="STYLES[tileIndex % 4].shape === 'diamond'" viewBox="0 0 24 24" class="h-6 w-6 shrink-0 fill-white/90"><path d="M12 2 22 12 12 22 2 12z" /></svg>
    <svg v-else-if="STYLES[tileIndex % 4].shape === 'circle'" viewBox="0 0 24 24" class="h-6 w-6 shrink-0 fill-white/90"><circle cx="12" cy="12" r="10" /></svg>
    <svg v-else viewBox="0 0 24 24" class="h-6 w-6 shrink-0 fill-white/90"><rect x="3" y="3" width="18" height="18" rx="2" /></svg>

    <span v-if="showText" class="text-base sm:text-lg leading-snug break-words">{{ text }}</span>

    <span v-if="reveal && count !== null" class="ml-auto text-sm font-bold bg-black/20 rounded-full px-2.5 py-0.5">{{ count }}</span>
  </button>
</template>
