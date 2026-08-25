<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { ExclamationTriangleIcon } from '@heroicons/vue/24/outline'

const visible = ref(false)
const alertData = ref(null)

function receiveNewAlert(payload) {
  // Silent/duress alerts stay invisible everywhere, including this modal —
  // that's the entire point of silent mode.
  if (payload.is_silent) return
  alertData.value = payload
  visible.value = true
}

function goToCommandCenter() {
  visible.value = false
  router.visit(route('sos.index'))
}

function dismiss() {
  visible.value = false
}

defineExpose({ receiveNewAlert })
</script>

<template>
  <div v-if="visible" class="fixed inset-0 z-[60] flex items-start justify-center bg-black/40 px-4 pt-20">
    <div class="w-full max-w-md rounded-2xl border-2 border-red-600 bg-white p-6 shadow-2xl">
      <div class="mb-3 flex items-center gap-2 text-red-700">
        <ExclamationTriangleIcon class="h-6 w-6 animate-pulse" />
        <h2 class="text-lg font-semibold">New SOS Alert</h2>
      </div>
      <p class="text-sm text-slate-700">
        <span class="font-medium">{{ alertData.reporter_name }}</span> triggered a
        <span class="font-medium">{{ alertData.alert_type.replace('_', ' ') }}</span> alert.
      </p>
      <p v-if="alertData.resolved_location_label" class="mt-1 text-sm text-slate-600">
        Last known location: {{ alertData.resolved_location_label }}
      </p>
      <div class="mt-5 flex gap-2">
        <button type="button" class="flex-1 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700" @click="goToCommandCenter">
          View in Command Center
        </button>
        <button type="button" class="rounded-lg bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200" @click="dismiss">
          Dismiss
        </button>
      </div>
    </div>
  </div>
</template>
