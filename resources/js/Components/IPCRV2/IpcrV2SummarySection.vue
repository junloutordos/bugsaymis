<script setup>
import { ref, watch, computed } from "vue"
import AppCard from "@/Components/AppCard.vue"
import { TH, TD } from "@/Composables/useTableClasses.js"

const props = defineProps({
  summary: { type: Object, default: () => ({ strategic: [], core: [], support: [] }) },
  ratingDate: { type: String, default: null },
  comments: { type: String, default: null },
  editable: { type: Boolean, default: false },
  finalNumericRating: { type: [Number, String], default: null },
  finalAdjectivalRating: { type: String, default: null },
  employee: { type: Object, default: null },
  supervisor: { type: Object, default: null },
  ocdUser: { type: Object, default: null },
  submittedForReviewAt: { type: String, default: null },
  submittedRatingAt: { type: String, default: null },
  directorSignedAt: { type: String, default: null },
})

const emit = defineEmits(["save-comments"])

const draftComments = ref(props.comments ?? "")
watch(() => props.comments, (v) => { draftComments.value = v ?? "" })

function fmtDate(v) {
  return v ? new Date(v).toLocaleDateString("en-PH", { year: "numeric", month: "long", day: "numeric" }) : "—"
}

const formattedDate = computed(() =>
  props.ratingDate
    ? new Date(props.ratingDate).toLocaleDateString("en-PH", { year: "numeric", month: "long", day: "numeric" })
    : null
)

const discussedWithDate = computed(() => fmtDate(props.submittedForReviewAt))
const assessedByDate = computed(() => fmtDate(props.submittedRatingAt))
const finalRatingByDate = computed(() => fmtDate(props.directorSignedAt))

function fmt(v) {
  return v === null || v === undefined ? "—" : (typeof v === "number" ? v.toFixed(2) : v)
}
</script>

<template>
  <AppCard class="mt-6">
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-sm font-semibold text-slate-700">Rating Summary</h3>
      <span v-if="formattedDate" class="text-xs text-slate-500">Date: {{ formattedDate }}</span>
    </div>
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
          <tr v-for="row in summary.core" :key="'c-' + row.label">
            <td :class="TD" class="border border-slate-200">{{ row.label }}</td>
            <td class="border border-slate-200 px-3 py-2 text-center">{{ fmt(row.quality) }}</td>
            <td class="border border-slate-200 px-3 py-2 text-center">{{ fmt(row.efficiency) }}</td>
            <td class="border border-slate-200 px-3 py-2 text-center">{{ fmt(row.timeliness) }}</td>
            <td class="border border-slate-200 px-3 py-2 text-center font-semibold">{{ fmt(row.average) }}</td>
            <td :class="TD" class="border border-slate-200">{{ row.equivalent ?? "—" }}</td>
          </tr>

          <tr>
            <td colspan="6" class="border border-slate-200 px-3 py-1.5 font-semibold bg-slate-100">Support Functions (20%)</td>
          </tr>
          <tr v-for="row in summary.support" :key="'sup-' + row.label">
            <td :class="TD" class="border border-slate-200">{{ row.label }}</td>
            <td class="border border-slate-200 px-3 py-2 text-center">{{ fmt(row.quality) }}</td>
            <td class="border border-slate-200 px-3 py-2 text-center">{{ fmt(row.efficiency) }}</td>
            <td class="border border-slate-200 px-3 py-2 text-center">{{ fmt(row.timeliness) }}</td>
            <td class="border border-slate-200 px-3 py-2 text-center font-semibold">{{ fmt(row.average) }}</td>
            <td :class="TD" class="border border-slate-200">{{ row.equivalent ?? "—" }}</td>
          </tr>

          <tr class="bg-slate-100 text-slate-800 font-semibold">
            <td colspan="5" class="border border-slate-200 px-3 py-2 text-left">TOTAL</td>
            <td class="border border-slate-200 px-3 py-2 text-center">{{ fmt(finalNumericRating) }}</td>
          </tr>
          <tr class="bg-slate-100 text-slate-800 font-semibold">
            <td colspan="5" class="border border-slate-200 px-3 py-2 text-left">Adjectival Rating</td>
            <td class="border border-slate-200 px-3 py-2 text-center">{{ finalAdjectivalRating ?? "—" }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="mt-6">
      <h4 class="text-sm font-semibold text-slate-700 mb-2">Comments and Recommendations for Development Purposes</h4>
      <textarea
        v-if="editable"
        v-model="draftComments"
        rows="3"
        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
        placeholder="Enter comments and recommendations..."
      ></textarea>
      <p v-else class="text-sm text-slate-600 whitespace-pre-line">{{ comments || "—" }}</p>
      <div v-if="editable" class="mt-2 flex justify-end">
        <button class="text-xs text-indigo-600 hover:text-indigo-700 font-medium" @click="emit('save-comments', draftComments)">
          Save Comments
        </button>
      </div>
    </div>

    <div class="overflow-x-auto mt-6">
      <table class="w-full border-collapse border border-slate-200 text-sm">
        <tr class="font-semibold text-slate-700">
          <td class="border border-slate-200 px-3 py-2 text-left">Discussed with</td>
          <td class="border border-slate-200 px-3 py-2 text-left">Date</td>
          <td class="border border-slate-200 px-3 py-2 text-left">Assessed by</td>
          <td class="border border-slate-200 px-3 py-2 text-left">Date</td>
          <td class="border border-slate-200 px-3 py-2 text-left">Final Rating by</td>
          <td class="border border-slate-200 px-3 py-2 text-left">Date</td>
        </tr>
        <tr>
          <td class="border border-slate-200 px-3 py-6 text-center">
            <b class="text-slate-800">{{ employee?.formatted_name ?? "—" }}</b>
          </td>
          <td rowspan="2" class="border border-slate-200 px-3 py-6 text-center text-slate-600">{{ discussedWithDate }}</td>
          <td class="border border-slate-200 px-3 py-6 text-center">
            <b class="text-slate-800">{{ supervisor?.formatted_name ?? "—" }}</b>
          </td>
          <td rowspan="2" class="border border-slate-200 px-3 py-6 text-center text-slate-600">{{ assessedByDate }}</td>
          <td class="border border-slate-200 px-3 py-6 text-center">
            <b class="text-slate-800">{{ ocdUser?.formatted_name ?? "—" }}</b>
          </td>
          <td rowspan="2" class="border border-slate-200 px-3 py-6 text-center text-slate-600">{{ finalRatingByDate }}</td>
        </tr>
        <tr>
          <td class="border border-slate-200 px-3 py-2 text-center">
            <small class="text-slate-500">{{ employee?.position }}</small>
          </td>
          <td class="border border-slate-200 px-3 py-2 text-center">
            <small class="text-slate-500">{{ supervisor?.position ?? "Division Chief" }}</small>
          </td>
          <td class="border border-slate-200 px-3 py-2 text-center">
            <small class="text-slate-500">{{ ocdUser?.position ?? "Campus Director" }}</small>
          </td>
        </tr>
      </table>
    </div>

    <div class="mt-4 text-xs text-slate-500 italic">
      Legend: 5 - Outstanding &nbsp; 4 - Very Satisfactory &nbsp; 3 - Satisfactory &nbsp; 2 - Unsatisfactory &nbsp; 1 - Poor
    </div>
  </AppCard>
</template>
