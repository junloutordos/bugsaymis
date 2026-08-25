<script setup>
import { ref, onUnmounted } from 'vue'
import axios from 'axios'

const visible = ref(false)
const minimized = ref(false)
const status = ref(null)
const alertId = ref(null)
let pollTimer = null

const CLOSED_STATUSES = ['resolved', 'false_alarm']

async function poll() {
  if (!alertId.value) return
  try {
    const { data } = await axios.get(route('sos.mine.status', alertId.value))
    status.value = data
    if (CLOSED_STATUSES.includes(data.status)) {
      stopPolling()
      localStorage.removeItem('sos_my_active_alert_id')
    }
  } catch {
    stopPolling()
    localStorage.removeItem('sos_my_active_alert_id')
  }
}

function open(id) {
  alertId.value = id
  visible.value = true
  minimized.value = false
  poll()
  stopPolling()
  pollTimer = setInterval(poll, 7000)
}

function stopPolling() {
  if (pollTimer) clearInterval(pollTimer)
  pollTimer = null
}

async function markSafe() {
  await axios.post(route('sos.mine.end', alertId.value))
  await poll()
}

function minimize() {
  minimized.value = true
}

function expand() {
  minimized.value = false
}

onUnmounted(stopPolling)

defineExpose({ open })
</script>

<template>
  <div v-if="visible && minimized" class="fixed bottom-24 right-5 z-50 cursor-pointer rounded-full bg-red-600 px-4 py-2 text-sm font-medium text-white shadow-lg" @click="expand">
    SOS: {{ status?.status ?? '...' }}
  </div>

  <div v-else-if="visible" class="fixed inset-0 z-[60] flex items-start justify-center bg-black/40 px-4 pt-20">
    <div class="w-full max-w-md rounded-2xl border-2 border-red-600 bg-white p-6 shadow-2xl">
      <h2 class="text-lg font-semibold text-red-700">Your SOS Alert</h2>
      <p class="mt-2 text-sm text-slate-700">
        Status: <span class="font-medium capitalize">{{ status?.status?.replace('_', ' ') }}</span>
      </p>
      <p v-if="status?.resolved_location_label" class="mt-1 text-sm text-slate-600">
        We noted your location as: {{ status.resolved_location_label }}
      </p>
      <p v-if="CLOSED_STATUSES.includes(status?.status)" class="mt-3 text-sm font-medium text-emerald-700">
        This alert has been closed.
      </p>

      <div class="mt-5 flex gap-2">
        <button
          v-if="!CLOSED_STATUSES.includes(status?.status)"
          type="button" class="flex-1 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700"
          @click="markSafe"
        >
          I'm safe now
        </button>
        <button type="button" class="rounded-lg bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200" @click="minimize">
          Minimize
        </button>
      </div>
    </div>
  </div>
</template>
