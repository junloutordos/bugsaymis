<script setup>
import { TD } from "@/Composables/useTableClasses.js"

defineProps({
  indicators: { type: Array, default: () => [] },
})
</script>

<template>
  <tbody>
    <tr class="bg-slate-200">
      <td colspan="5" class="px-4 py-2 font-bold text-slate-800 border border-slate-300 uppercase">
        Strategic Function (30%)
      </td>
    </tr>
    <tr v-for="indicator in indicators" :key="indicator.id">
      <td :class="TD" class="border border-slate-200">{{ indicator.agency_outcome?.outcome }}</td>
      <td :class="TD" class="border border-slate-200">{{ indicator.description }}</td>
      <td :class="TD" class="border border-slate-200">{{ indicator.target }}</td>
      <td :class="TD" class="border border-slate-200">{{ indicator.displayed_accomplishment ?? "—" }}</td>
      <td class="border border-slate-200 px-4 py-3 text-center text-sm text-slate-400">—</td>
    </tr>
    <tr v-if="!indicators.length">
      <td :class="TD" class="border border-slate-200" colspan="5">No OPCR indicators for the current fiscal year yet.</td>
    </tr>
  </tbody>
</template>
