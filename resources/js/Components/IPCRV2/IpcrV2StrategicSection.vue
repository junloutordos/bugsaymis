<script setup>
import AppCard from "@/Components/AppCard.vue"
import { TH, TD } from "@/Composables/useTableClasses.js"

defineProps({
  indicators: { type: Array, default: () => [] },
})
</script>

<template>
  <AppCard class="mb-6">
    <h3 class="text-sm font-semibold text-slate-700 mb-1">Strategic Function (30%)</h3>
    <p class="text-xs text-slate-500 mb-4">
      Inherited from the current campus OPCR — identical for every employee, read-only.
    </p>
    <table class="w-full">
      <thead>
        <tr>
          <th :class="TH">Program</th>
          <th :class="TH">Indicator</th>
          <th :class="TH">Target</th>
          <th :class="TH">Actual</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="indicator in indicators" :key="indicator.id">
          <td :class="TD">{{ indicator.agency_outcome?.outcome }}</td>
          <td :class="TD">{{ indicator.description }}</td>
          <td :class="TD">{{ indicator.target }}</td>
          <td :class="TD">{{ indicator.displayed_accomplishment ?? "—" }}</td>
        </tr>
        <tr v-if="!indicators.length">
          <td :class="TD" colspan="4">No OPCR indicators for the current fiscal year yet.</td>
        </tr>
      </tbody>
    </table>
  </AppCard>
</template>
