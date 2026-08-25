<script setup>
import { computed } from 'vue'
import axios from 'axios'
import { CheckCircleIcon, LockClosedIcon, UserGroupIcon } from '@heroicons/vue/24/outline'
import { CheckCircleIcon as CheckCircleSolid } from '@heroicons/vue/24/solid'

const props = defineProps({ alert: { type: Object, required: true }, currentUserId: { type: Number, required: true } })
const emit = defineEmits(['updated'])

const isClaimedByMe = computed(() => props.alert.responders.some(r => r.user_id === props.currentUserId))

// Once verified as a real emergency (or beyond), a responder's commitment is
// locked — "I'm responding" can no longer be undone from the UI.
const isLocked = computed(() => ['verified', 'escalated', 'resolved', 'false_alarm'].includes(props.alert.status))

async function toggleClaim() {
  if (isLocked.value && isClaimedByMe.value) return
  const action = isClaimedByMe.value ? 'unclaim' : 'claim'
  const { data } = await axios.post(route(`sos.${action}`, props.alert.id))
  emit('updated', data)
}
</script>

<template>
  <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <div class="flex items-center gap-2">
      <UserGroupIcon class="h-4 w-4 text-slate-400" />
      <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Responding</h4>
    </div>

    <div v-if="alert.responders.length === 0" class="mt-3 rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-400">
      No one has claimed this alert yet.
    </div>
    <ul v-else class="mt-3 space-y-1.5">
      <li v-for="r in alert.responders" :key="r.user_id"
          class="flex items-center gap-2 rounded-lg bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-800">
        <CheckCircleSolid class="h-4 w-4 shrink-0 text-emerald-500" />
        {{ r.name }}
      </li>
    </ul>

    <button
      class="mt-4 flex w-full items-center justify-center gap-1.5 rounded-xl px-3 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors disabled:cursor-not-allowed disabled:opacity-90"
      :class="isClaimedByMe ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-indigo-600 hover:bg-indigo-700'"
      :disabled="isLocked && isClaimedByMe"
      @click="toggleClaim"
    >
      <LockClosedIcon v-if="isLocked && isClaimedByMe" class="h-4 w-4" />
      <CheckCircleIcon v-else-if="isClaimedByMe" class="h-4 w-4" />
      {{ isClaimedByMe ? "Responding" : "I'm responding" }}
    </button>
    <p v-if="isLocked && isClaimedByMe" class="mt-1.5 text-center text-xs text-slate-400">
      Locked in — alert has been verified.
    </p>
  </div>
</template>
