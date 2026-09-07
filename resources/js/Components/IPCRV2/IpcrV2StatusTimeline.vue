<script setup>
import AppCard from "@/Components/AppCard.vue"
import { ShieldCheckIcon, ClockIcon } from "@heroicons/vue/24/outline"

defineProps({
  logs: { type: Array, default: () => [] },
})

function formatDate(d) {
  return d ? new Date(d).toLocaleDateString("en-PH", { year: "numeric", month: "long", day: "numeric", hour: "numeric", minute: "2-digit" }) : "—"
}

const ACTION_LABEL = {
  submitted: "Submitted",
  approved: "Approved",
  returned: "Returned for Revision",
  rated: "Marked as Rated",
  signed: "Signed",
  reopened: "Reopened",
  status_changed: "Status Changed",
}
</script>

<template>
  <AppCard v-if="logs.length" class="mt-6">
    <h3 class="text-sm font-semibold text-slate-700 mb-4">Audit Timeline</h3>
    <ol class="space-y-3">
      <li v-for="log in logs" :key="log.id" class="flex gap-3 text-sm">
        <div class="mt-0.5 shrink-0">
          <ShieldCheckIcon v-if="log.signed_via_pin" class="w-4 h-4 text-indigo-500" />
          <ClockIcon v-else class="w-4 h-4 text-slate-400" />
        </div>
        <div class="min-w-0">
          <p class="text-slate-700">
            <span class="font-medium">{{ ACTION_LABEL[log.action_type] ?? log.action_type }}</span>
            <span v-if="log.to_status"> — {{ log.to_status }}</span>
            <span v-if="log.actor?.name" class="text-slate-500"> by {{ log.actor.name }}</span>
          </p>
          <p v-if="log.remarks" class="text-slate-500 italic mt-0.5">"{{ log.remarks }}"</p>
          <p class="text-xs text-slate-400 mt-0.5">{{ formatDate(log.created_at) }}</p>
        </div>
      </li>
    </ol>
  </AppCard>
</template>
