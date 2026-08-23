<script setup>
defineProps({
  active: { type: Boolean, default: false },
})
</script>

<template>
  <div v-if="active" class="emergency-border-overlay" aria-hidden="true" />
</template>

<style scoped>
.emergency-border-overlay {
  position: fixed;
  inset: 0;
  z-index: 90;
  pointer-events: none;
  border: 6px solid rgba(220, 38, 38, 0.85);
  box-shadow: inset 0 0 40px rgba(220, 38, 38, 0.55);
  animation: emergency-pulse 1.6s ease-in-out infinite;
}

@keyframes emergency-pulse {
  0%, 100% { opacity: 0.55; }
  50% { opacity: 1; }
}

@media (prefers-reduced-motion: reduce) {
  .emergency-border-overlay {
    animation: none;
    opacity: 1;
  }
}
</style>
