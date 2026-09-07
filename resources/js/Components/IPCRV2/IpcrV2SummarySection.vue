<script setup>
import AppCard from "@/Components/AppCard.vue"
import { TH, TD } from "@/Composables/useTableClasses.js"
import { computed } from "vue"
import { groupConsecutiveByFunction } from "@/Composables/ipcrV2FunctionGrouping.js"

const props = defineProps({
  summary: { type: Object, default: () => ({ strategic: [], core: [], support: [] }) },
})

// Rows sharing the same Core/Support Function (one function tagged to N
// WDPs produces N rows) merge into one rowspan'd label block, matching the
// item tables above.
const coreRows = computed(() => groupConsecutiveByFunction(props.summary.core ?? []))
const supportRows = computed(() => groupConsecutiveByFunction(props.summary.support ?? []))

function fmt(v) {
  return v === null || v === undefined ? "—" : (typeof v === "number" ? v.toFixed(2) : v)
}
</script>

<template>
  <AppCard class="mt-6">
    <h3 class="text-sm font-semibold text-slate-700 mb-4">Rating Summary</h3>
    <div class="overflow-x-auto">
      <table class="w-full border-collapse border border-slate-200 text-sm">
        <thead>
          <tr>
            <th :class="TH" class="border border-slate-200">Agency Organizational Outcome</th>
            <th :class="TH" class="border border-slate-200 text-center">Quality</th>
            <th :class="TH" class="border border-slate-200 text-center">Efficiency</th>
            <th :class="TH" class="border border-slate-200 text-center">Timeliness</th>
            <th :class="TH" class="border border-slate-200 text-center">Average</th>
            <th :class="TH" class="border border-slate-200">Equivalent</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td colspan="6" class="border border-slate-200 px-3 py-1.5 font-semibold bg-slate-100">Strategic Functions (30%)</td>
          </tr>
          <tr v-for="row in summary.strategic" :key="'s-' + row.label">
            <td :class="TD" class="border border-slate-200">{{ row.label }}</td>
            <td class="border border-slate-200 px-3 py-2 text-center">{{ fmt(row.quality) }}</td>
            <td class="border border-slate-200 px-3 py-2 text-center">{{ fmt(row.efficiency) }}</td>
            <td class="border border-slate-200 px-3 py-2 text-center">{{ fmt(row.timeliness) }}</td>
            <td class="border border-slate-200 px-3 py-2 text-center font-semibold">{{ fmt(row.average) }}</td>
            <td :class="TD" class="border border-slate-200">{{ row.equivalent ?? "—" }}</td>
          </tr>

          <tr>
            <td colspan="6" class="border border-slate-200 px-3 py-1.5 font-semibold bg-slate-100">Core Functions (50%)</td>
          </tr>
          <tr v-for="(row, idx) in coreRows" :key="'c-' + idx">
            <td v-if="row.isFirst" :rowspan="row.groupSize" :class="TD" class="border border-slate-200 align-top">{{ row.item.label }}</td>
            <td class="border border-slate-200 px-3 py-2 text-center">{{ fmt(row.item.quality) }}</td>
            <td class="border border-slate-200 px-3 py-2 text-center">{{ fmt(row.item.efficiency) }}</td>
            <td class="border border-slate-200 px-3 py-2 text-center">{{ fmt(row.item.timeliness) }}</td>
            <td class="border border-slate-200 px-3 py-2 text-center font-semibold">{{ fmt(row.item.average) }}</td>
            <td :class="TD" class="border border-slate-200">{{ row.item.equivalent ?? "—" }}</td>
          </tr>

          <tr>
            <td colspan="6" class="border border-slate-200 px-3 py-1.5 font-semibold bg-slate-100">Support Functions (20%)</td>
          </tr>
          <tr v-for="(row, idx) in supportRows" :key="'sup-' + idx">
            <td v-if="row.isFirst" :rowspan="row.groupSize" :class="TD" class="border border-slate-200 align-top">{{ row.item.label }}</td>
            <td class="border border-slate-200 px-3 py-2 text-center">{{ fmt(row.item.quality) }}</td>
            <td class="border border-slate-200 px-3 py-2 text-center">{{ fmt(row.item.efficiency) }}</td>
            <td class="border border-slate-200 px-3 py-2 text-center">{{ fmt(row.item.timeliness) }}</td>
            <td class="border border-slate-200 px-3 py-2 text-center font-semibold">{{ fmt(row.item.average) }}</td>
            <td :class="TD" class="border border-slate-200">{{ row.item.equivalent ?? "—" }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="mt-4 text-xs text-slate-500 italic">
      Legend: 5 - Outstanding &nbsp; 4 - Very Satisfactory &nbsp; 3 - Satisfactory &nbsp; 2 - Unsatisfactory &nbsp; 1 - Poor
    </div>

    <div class="overflow-x-auto mt-6">
      <table class="w-full border-collapse border border-slate-200 text-sm">
        <tr class="font-semibold text-slate-700">
          <td class="border border-slate-200 px-3 py-2 text-left">Discussed with</td>
          <td class="border border-slate-200 px-3 py-2 text-left">Assessed by</td>
          <td class="border border-slate-200 px-3 py-2 text-left">Final Rating by</td>
        </tr>
        <tr>
          <td class="border border-slate-200 px-3 py-6 text-center text-slate-400">____________________</td>
          <td class="border border-slate-200 px-3 py-6 text-center text-slate-400">____________________</td>
          <td class="border border-slate-200 px-3 py-6 text-center text-slate-400">____________________</td>
        </tr>
      </table>
    </div>
  </AppCard>
</template>
