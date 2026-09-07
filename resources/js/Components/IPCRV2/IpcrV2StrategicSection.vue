<script setup>
import { TD } from "@/Composables/useTableClasses.js"

defineProps({
  indicators: { type: Array, default: () => [] },
})

function dostSource(indicator) {
  return indicator.performance_indicator?.agency_outcome ?? indicator.agency_outcome
}
</script>

<template>
  <tbody>
    <tr class="bg-slate-200">
      <td colspan="11" class="px-4 py-2 font-bold text-slate-800 border border-slate-300 uppercase">
        Strategic Function (30%)
      </td>
    </tr>
    <tr v-for="indicator in indicators" :key="indicator.id">
      <td :class="TD" class="border border-slate-200">{{ dostSource(indicator)?.dost_strategy_names_joined ?? "—" }}</td>
      <td :class="TD" class="border border-slate-200">{{ dostSource(indicator)?.dost_sub_strategy_descriptions_joined ?? "—" }}</td>
      <td :class="TD" class="border border-slate-200">{{ indicator.agency_outcome?.outcome ?? "—" }}</td>
      <td :class="TD" class="border border-slate-200">{{ indicator.description }}</td>
      <td :class="TD" class="border border-slate-200">{{ indicator.target }}</td>
      <td :class="TD" class="border border-slate-200">{{ indicator.displayed_accomplishment ?? "—" }}</td>
      <td class="border border-slate-200 px-4 py-3 text-center text-sm">{{ indicator.rating_quality ?? "—" }}</td>
      <td class="border border-slate-200 px-4 py-3 text-center text-sm">{{ indicator.rating_efficiency ?? "—" }}</td>
      <td class="border border-slate-200 px-4 py-3 text-center text-sm">{{ indicator.rating_timeliness ?? "—" }}</td>
      <td class="border border-slate-200 px-4 py-3 text-center text-sm font-semibold">{{ indicator.rating_average ?? "—" }}</td>
      <td :class="TD" class="border border-slate-200">{{ indicator.remarks ?? "—" }}</td>
    </tr>
    <tr v-if="!indicators.length">
      <td :class="TD" class="border border-slate-200" colspan="11">No OPCR indicators for the current fiscal year yet.</td>
    </tr>
  </tbody>
</template>
