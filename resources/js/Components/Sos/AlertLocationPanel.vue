<script setup>
import AlertMap from '@/Components/Sos/AlertMap.vue'
import { AcademicCapIcon, BuildingOffice2Icon, HomeIcon, QuestionMarkCircleIcon, SignalIcon, SignalSlashIcon } from '@heroicons/vue/24/outline'

const props = defineProps({ alert: { type: Object, required: true } })

const typeIcon = {
  classroom: AcademicCapIcon,
  homeroom: HomeIcon,
  office: BuildingOffice2Icon,
  unknown: QuestionMarkCircleIcon,
}
</script>

<template>
  <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Location</h4>

    <div class="mt-3 flex items-start gap-3">
      <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-500">
        <component :is="typeIcon[alert.resolved_location.type] ?? QuestionMarkCircleIcon" class="h-5 w-5" />
      </span>
      <div>
        <p class="text-sm font-medium text-slate-900">Reported at trigger: {{ alert.resolved_location.label }}</p>
        <p v-if="alert.current_location && alert.current_location.label !== alert.resolved_location.label"
           class="mt-1 text-sm text-slate-600">
          Currently scheduled: {{ alert.current_location.label }}
        </p>
      </div>
    </div>

    <div class="mt-3 flex items-center gap-2 text-xs">
      <span v-if="alert.gps_badge.on_campus === true" class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-1 font-medium text-emerald-700">
        <SignalIcon class="h-3.5 w-3.5" />
        On campus{{ alert.gps_badge.zone_label ? ` · near ${alert.gps_badge.zone_label}` : '' }}
      </span>
      <span v-else-if="alert.gps_badge.on_campus === false" class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-1 font-medium text-amber-700">
        <SignalIcon class="h-3.5 w-3.5" />
        Off campus
      </span>
      <span v-else class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 font-medium text-slate-500">
        <SignalSlashIcon class="h-3.5 w-3.5" />
        No GPS signal
      </span>
    </div>

    <div class="mt-4 overflow-hidden rounded-xl">
      <AlertMap :lat="alert.lat" :lng="alert.lng" :label="alert.resolved_location.label" />
    </div>
  </div>
</template>
