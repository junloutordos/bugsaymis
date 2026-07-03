<script setup>
defineProps({
  pin: { type: String, required: true },
  size: { type: String, default: 'lg' }, // lg | sm
})
</script>

<template>
  <div class="inline-flex items-center gap-3 bg-white/10 rounded-2xl px-6 py-4 backdrop-blur-sm">
    <span class="text-xs font-medium uppercase tracking-widest text-white/60">Game PIN</span>
    <div class="flex gap-2">
      <span
        v-for="(digit, i) in pin.split('')" :key="i"
        :class="[
          'font-heading font-bold text-white bg-white/10 rounded-lg flex items-center justify-center',
          size === 'lg' ? 'h-14 w-10 text-4xl' : 'h-9 w-7 text-xl',
        ]"
      >{{ digit }}</span>
    </div>
  </div>
</template>
