<script setup>
import axios from 'axios'

const props = defineProps({ alert: { type: Object, required: true }, currentUserId: { type: Number, required: true } })
const emit = defineEmits(['updated'])

const isClaimedByMe = () => props.alert.responders.some(r => r.user_id === props.currentUserId)

async function toggleClaim() {
  const action = isClaimedByMe() ? 'unclaim' : 'claim'
  const { data } = await axios.post(route(`sos.${action}`, props.alert.id))
  emit('updated', data)
}
</script>

<template>
  <div class="rounded-xl border border-slate-200 bg-white p-4">
    <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Responding</h4>

    <div v-if="alert.responders.length === 0" class="mt-2 text-sm text-slate-400">No one has claimed this alert yet.</div>
    <ul v-else class="mt-2 space-y-1">
      <li v-for="r in alert.responders" :key="r.user_id" class="text-sm text-slate-700">{{ r.name }}</li>
    </ul>

    <button
      class="mt-3 w-full rounded-lg px-3 py-2 text-sm font-medium text-white"
      :class="isClaimedByMe() ? 'bg-slate-600 hover:bg-slate-700' : 'bg-indigo-600 hover:bg-indigo-700'"
      @click="toggleClaim"
    >
      {{ isClaimedByMe() ? "Stop responding" : "I'm responding" }}
    </button>
  </div>
</template>
