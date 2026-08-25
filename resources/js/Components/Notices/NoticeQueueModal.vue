<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { storageUrl } from '@/Composables/useStorage'

const pending = ref({ emergency_alerts: [], announcements: [] })
const loaded = ref(false)
const fullscreenImage = ref(null)

function openFullscreen(src) {
  fullscreenImage.value = src
}

function closeFullscreen() {
  fullscreenImage.value = null
}

// Emergency alerts always come first — a life-safety notice must never
// wait behind "no classes Friday" in the queue.
const queue = computed(() => [
  ...pending.value.emergency_alerts.map(e => ({ ...e, kind: 'emergency-alert' })),
  ...pending.value.announcements.map(a => ({ ...a, kind: 'announcement' })),
])
const current = computed(() => queue.value[0] ?? null)
const currentIndex = computed(() => current.value ? queue.value.indexOf(current.value) : -1)

async function fetchPending() {
  try {
    const { data } = await axios.get('/notices/pending')
    pending.value = data
  } catch {
    // Silent — the modal just won't show; it isn't worth blocking the page over.
  } finally {
    loaded.value = true
  }
}

async function acknowledge() {
  if (!current.value) return
  const item = current.value
  try {
    await axios.post(`/notices/${item.kind}/${item.id}/acknowledge`)
  } catch {
    return // leave it in the queue, try again next load
  }
  if (item.kind === 'emergency-alert') {
    pending.value.emergency_alerts = pending.value.emergency_alerts.filter(e => e.id !== item.id)
  } else {
    pending.value.announcements = pending.value.announcements.filter(a => a.id !== item.id)
  }
}

/** Called by AdminLayout when a live emergency broadcast arrives on the Echo channel. */
function receiveEmergencyAlert(alert) {
  if (pending.value.emergency_alerts.some(e => e.id === alert.id)) return
  pending.value.emergency_alerts = [alert, ...pending.value.emergency_alerts]
}

defineExpose({ receiveEmergencyAlert })

function handleKeydown(e) {
  if (e.key === 'Escape' && fullscreenImage.value) {
    closeFullscreen()
  }
}

onMounted(() => {
  fetchPending()
  window.addEventListener('keydown', handleKeydown)
})
</script>

<template>
  <div v-if="loaded && current" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/60 p-4">
    <div
      class="flex w-full max-w-2xl max-h-[90vh] flex-col rounded-2xl bg-white p-6 shadow-2xl"
      :class="current.kind === 'emergency-alert' ? 'border-4 border-red-600' : ''"
    >
      <p v-if="queue.length > 1" class="mb-2 shrink-0 text-xs font-medium text-slate-400">
        {{ currentIndex + 1 }} of {{ queue.length }}
      </p>

      <span
        v-if="current.kind === 'emergency-alert'"
        class="mb-2 inline-block w-fit shrink-0 rounded-full bg-red-600 px-2.5 py-1 text-xs font-semibold uppercase tracking-wide text-white"
      >
        Emergency Alert{{ current.severity ? ` — ${current.severity}` : '' }}
      </span>

      <h2 class="shrink-0 text-lg font-semibold text-slate-900">{{ current.title }}</h2>

      <div class="mt-2 min-h-0 flex-1 overflow-y-auto">
        <p class="whitespace-pre-line text-sm text-slate-600">{{ current.body || current.message }}</p>
        <img
          v-if="current.poster_path"
          :src="storageUrl(current.poster_path)"
          :alt="current.title"
          class="mt-3 max-h-64 w-full cursor-zoom-in rounded-lg object-contain"
          @click="openFullscreen(storageUrl(current.poster_path))"
        />
      </div>

      <button
        class="mt-5 w-full shrink-0 rounded-lg px-4 py-2 text-sm font-medium text-white"
        :class="current.kind === 'emergency-alert' ? 'bg-red-600 hover:bg-red-700' : 'bg-indigo-600 hover:bg-indigo-700'"
        @click="acknowledge"
      >
        {{ current.kind === 'emergency-alert' ? 'Acknowledge' : 'Mark as Read' }}
      </button>
    </div>

    <div
      v-if="fullscreenImage"
      class="fixed inset-0 z-[110] flex items-center justify-center bg-black/90 p-4"
      @click="closeFullscreen"
    >
      <button
        class="absolute right-4 top-4 rounded-full bg-white/10 p-2 text-white hover:bg-white/20"
        aria-label="Close fullscreen image"
        @click.stop="closeFullscreen"
      >
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
      <img
        :src="fullscreenImage"
        :alt="current?.title"
        class="max-h-full max-w-full cursor-zoom-out rounded-lg object-contain"
        @click.stop="closeFullscreen"
      />
    </div>
  </div>
</template>
