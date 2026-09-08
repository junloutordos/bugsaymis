<script setup>
import { computed } from "vue"

const props = defineProps({
  employee: Object,
  period: Object,
  supervisor: Object,
  ocdUser: Object,
  submittedForReviewAt: String,
  targetApprovedAt: String,
})

function fmt(v) {
  return v ? new Date(v).toLocaleDateString("en-PH", { year: "numeric", month: "long", day: "numeric" }) : "—"
}

const submittedForReviewLabel = computed(() => fmt(props.submittedForReviewAt))
const targetApprovedLabel = computed(() => fmt(props.targetApprovedAt))
</script>

<template>
  <div class="p-5 border-b border-slate-200">
    <p class="text-xs text-center text-slate-500">Republic of the Philippines</p>
    <p class="text-xs text-center text-slate-500 mb-3">Department of Science and Technology</p>
    <p class="text-base text-center font-semibold mb-4">
      Individual Performance Commitment and Review (IPCR)
    </p>
    <p class="text-sm text-slate-700 mb-6">
      I, <b>{{ employee?.formatted_name }}</b>, <b class="uppercase">{{ employee?.position }}</b>,
      of Philippine Science High School – Caraga Region Campus, commit to deliver and agree to be rated on the
      attainment of the following targets in accordance with the indicated measures for the period of
      <b class="uppercase">{{ period?.label }}</b>.
    </p>

    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <tr class="font-semibold text-slate-700">
          <td class="px-3 py-2 text-left w-1/3">Ratee</td>
          <td class="px-3 py-2 text-left w-1/3">Reviewed by</td>
          <td class="px-3 py-2 text-left w-1/3">Approved by</td>
        </tr>
        <tr>
          <td class="px-3 py-6 text-center">
            <b class="text-slate-800">{{ employee?.formatted_name ?? "—" }}</b><br />
            <small class="text-slate-500">Ratee</small><br />
            <small class="text-slate-500">Date: {{ submittedForReviewLabel }}</small>
          </td>
          <td class="px-3 py-6 text-center">
            <b class="text-slate-800">{{ supervisor?.formatted_name ?? "—" }}</b><br />
            <small class="text-slate-500">{{ supervisor?.position ?? "Division Chief" }}</small><br />
            <small class="text-slate-500">Date: {{ targetApprovedLabel }}</small>
          </td>
          <td class="px-3 py-6 text-center">
            <b class="text-slate-800">{{ ocdUser?.formatted_name ?? "—" }}</b><br />
            <small class="text-slate-500">{{ ocdUser?.position ?? "Campus Director" }}</small><br />
            <small class="text-slate-500">Date: {{ targetApprovedLabel }}</small>
          </td>
        </tr>
      </table>
    </div>
  </div>
</template>
