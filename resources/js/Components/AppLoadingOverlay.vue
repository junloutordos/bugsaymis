<template>
  <Teleport to="body">
    <Transition name="app-loading-fade">
      <div v-if="visible" class="app-loading-overlay" role="status" aria-live="polite">
        <div class="app-loading-card">
          <AtlasMarkLoader caption="Processing" size="lg" />
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'
import AtlasMarkLoader from '@/Components/AtlasMarkLoader.vue'

/**
 * Modal loading card for form submissions (non-GET Inertia visits) that stay
 * pending past the threshold. GET navigations are PageSkeleton's job — this
 * deliberately ignores them so the two systems never stack.
 */
const SHOW_DELAY_MS = 400

const visible = ref(false)
let pendingVisits = 0
let showTimer = null

function isGet(event) {
  return (event.detail?.visit?.method ?? 'get') === 'get'
}

function onStart(event) {
  if (isGet(event)) return
  pendingVisits++
  if (showTimer === null && !visible.value) {
    showTimer = setTimeout(() => {
      showTimer = null
      if (pendingVisits > 0) visible.value = true
    }, SHOW_DELAY_MS)
  }
}

function onFinish(event) {
  if (isGet(event)) return
  pendingVisits = Math.max(0, pendingVisits - 1)
  if (pendingVisits === 0) {
    if (showTimer !== null) {
      clearTimeout(showTimer)
      showTimer = null
    }
    visible.value = false
  }
}

onMounted(() => {
  document.addEventListener('inertia:start', onStart)
  document.addEventListener('inertia:finish', onFinish)
})

onBeforeUnmount(() => {
  document.removeEventListener('inertia:start', onStart)
  document.removeEventListener('inertia:finish', onFinish)
  if (showTimer !== null) clearTimeout(showTimer)
})
</script>

<style>
.app-loading-overlay {
  position: fixed;
  inset: 0;
  z-index: 100;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(15, 23, 42, 0.3);
  -webkit-backdrop-filter: blur(3px);
  backdrop-filter: blur(3px);
}

.app-loading-card {
  padding: 2rem 3rem 1.75rem;
  background: #fff;
  border-radius: 1rem;
  box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.35);
}

.app-loading-fade-enter-active { transition: opacity 0.15s ease; }
.app-loading-fade-leave-active { transition: opacity 0.2s ease; }
.app-loading-fade-enter-from,
.app-loading-fade-leave-to { opacity: 0; }
</style>
