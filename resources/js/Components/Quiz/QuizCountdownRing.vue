<script setup>
import { ref, onMounted, onBeforeUnmount, computed, nextTick } from 'vue'

const props = defineProps({
  durationSeconds: { type: Number, required: true },
  startedAt: { type: String, required: true }, // server ISO timestamp — authoritative
})
const emit = defineEmits(['timeout'])

const RADIUS = 46
const CIRCUMFERENCE = 2 * Math.PI * RADIUS

const remaining = ref(props.durationSeconds)
const ringOffset = ref(0)
const transitionMs = ref(0)
let intervalId = null
let timeoutId = null

function elapsedSeconds() {
  return (Date.now() - new Date(props.startedAt).getTime()) / 1000
}

onMounted(async () => {
  const elapsed = elapsedSeconds()
  const initialRemaining = Math.max(props.durationSeconds - elapsed, 0)
  remaining.value = Math.ceil(initialRemaining)

  await nextTick()
  transitionMs.value = 0
  ringOffset.value = (elapsed / props.durationSeconds) * CIRCUMFERENCE

  requestAnimationFrame(() => {
    transitionMs.value = initialRemaining * 1000
    ringOffset.value = CIRCUMFERENCE
  })

  intervalId = setInterval(() => {
    remaining.value = Math.max(Math.ceil(props.durationSeconds - elapsedSeconds()), 0)
    if (remaining.value <= 0) clearInterval(intervalId)
  }, 250)

  timeoutId = setTimeout(() => emit('timeout'), Math.max(initialRemaining * 1000, 0))
})

onBeforeUnmount(() => {
  clearInterval(intervalId)
  clearTimeout(timeoutId)
})
</script>

<template>
  <div class="relative h-24 w-24">
    <svg viewBox="0 0 100 100" class="h-24 w-24 -rotate-90">
      <circle cx="50" cy="50" :r="RADIUS" fill="none" stroke="rgba(255,255,255,0.15)" stroke-width="8" />
      <circle
        cx="50" cy="50" :r="RADIUS" fill="none" stroke="white" stroke-width="8" stroke-linecap="round"
        :stroke-dasharray="CIRCUMFERENCE"
        :stroke-dashoffset="ringOffset"
        :style="{ transition: `stroke-dashoffset ${transitionMs}ms linear` }"
      />
    </svg>
    <span class="absolute inset-0 flex items-center justify-center text-2xl font-heading font-bold text-white">{{ remaining }}</span>
  </div>
</template>
