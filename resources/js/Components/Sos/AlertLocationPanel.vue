<script setup>
import AlertMap from '@/Components/Sos/AlertMap.vue'

const props = defineProps({ alert: { type: Object, required: true } })

const typeIcon = { classroom: '🏫', homeroom: '🏠', office: '🏢', unknown: '❓' }
</script>

<template>
  <div class="rounded-xl border border-slate-200 bg-white p-4">
    <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Location</h4>

    <div class="mt-2 flex items-start gap-2">
      <span class="text-lg">{{ typeIcon[alert.resolved_location.type] ?? '❓' }}</span>
      <div>
        <p class="text-sm font-medium text-slate-900">Reported at trigger: {{ alert.resolved_location.label }}</p>
        <p v-if="alert.current_location && alert.current_location.label !== alert.resolved_location.label"
           class="mt-1 text-sm text-slate-600">
          Currently scheduled: {{ alert.current_location.label }}
        </p>
      </div>
    </div>

    <div class="mt-3 flex items-center gap-2 text-xs">
      <span v-if="alert.gps_badge.on_campus === true" class="rounded-full bg-emerald-100 px-2 py-1 font-medium text-emerald-700">
        On campus{{ alert.gps_badge.zone_label ? ` · near ${alert.gps_badge.zone_label}` : '' }}
      </span>
      <span v-else-if="alert.gps_badge.on_campus === false" class="rounded-full bg-amber-100 px-2 py-1 font-medium text-amber-700">Off campus</span>
      <span v-else class="rounded-full bg-slate-100 px-2 py-1 font-medium text-slate-500">No GPS signal</span>
    </div>

    <div class="mt-3">
      <AlertMap :lat="alert.lat" :lng="alert.lng" :label="alert.resolved_location.label" />
    </div>
  </div>
</template>
