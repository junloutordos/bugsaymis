<script setup>
import AppTextarea from "@/Components/AppTextarea.vue"
import { TD } from "@/Composables/useTableClasses.js"
import { router } from "@inertiajs/vue3"
import { useSubmit } from "@/Composables/useSubmit"

const props = defineProps({
  ipcrId: [Number, String],
  items: { type: Array, default: () => [] },
  isOwner: Boolean,
  isMutable: Boolean,
  canRate: { type: Boolean, default: false },
})

const { submit } = useSubmit()

const CRITERIA = [
  { key: "student_feedback_rating", label: "Positive feedback from students (30%)" },
  { key: "supervisor_feedback_rating", label: "Positive feedback from immediate supervisor (20%)" },
  { key: "im_development_rating", label: "Instructional materials development (20%)" },
  { key: "timeliness_rating", label: "Timely submission of forms and documents (30%)" },
]

function saveEmployeeFields(item) {
  submit((opts) => router.put(route("employee-ipcr-v2.updateCoreItem", [props.ipcrId, item.id]), {
    target: item.target,
    actual_accomplishment: item.actual_accomplishment,
  }, opts))
}

function rate(item) {
  submit((opts) => router.put(route("division-chief-ipcr-v2.rateCoreItem", [props.ipcrId, item.id]), {
    student_feedback_rating: item.student_feedback_rating,
    supervisor_feedback_rating: item.supervisor_feedback_rating,
    im_development_rating: item.im_development_rating,
    timeliness_rating: item.timeliness_rating,
    remarks: item.remarks,
  }, opts))
}

function rowAverage(item) {
  const parts = [item.student_feedback_rating, item.supervisor_feedback_rating, item.im_development_rating, item.timeliness_rating]
  if (parts.some(v => v === null || v === undefined)) return "—"
  return (parts[0] * 0.3 + parts[1] * 0.2 + parts[2] * 0.2 + parts[3] * 0.3).toFixed(2)
}
</script>

<template>
  <tbody>
    <tr class="bg-slate-200">
      <td colspan="11" class="px-4 py-2 font-bold text-slate-800 border border-slate-300 uppercase">
        Core Function (50%)
      </td>
    </tr>
    <template v-for="item in items" :key="item.id">
      <tr v-for="(criterion, idx) in CRITERIA" :key="item.id + '-' + criterion.key">
        <td v-if="idx === 0" rowspan="5" :class="TD" class="border border-slate-200 align-top font-medium">
          {{ item.label }}<br />
          <small class="text-slate-400">Weight: {{ item.weight_percent ?? "—" }}%</small>
        </td>
        <td v-if="idx === 0" rowspan="5" class="border border-slate-200 px-4 py-3 text-sm text-slate-400 align-top">—</td>
        <td v-if="idx === 0" rowspan="5" class="border border-slate-200 px-4 py-3 text-sm text-slate-400 align-top">—</td>
        <td :class="TD" class="border border-slate-200">{{ criterion.label }}</td>
        <td v-if="idx === 0" rowspan="4" :class="TD" class="border border-slate-200 align-top">
          <AppTextarea v-if="isOwner && isMutable" v-model="item.target" @blur="saveEmployeeFields(item)" />
          <span v-else>{{ item.target ?? "—" }}</span>
        </td>
        <td v-if="idx === 0" rowspan="4" :class="TD" class="border border-slate-200 align-top">
          <AppTextarea v-if="isOwner && isMutable" v-model="item.actual_accomplishment" @blur="saveEmployeeFields(item)" />
          <span v-else>{{ item.actual_accomplishment ?? "—" }}</span>
        </td>
        <td class="border border-slate-200 px-4 py-3 text-center text-sm text-slate-300">—</td>
        <td class="border border-slate-200 px-4 py-3 text-center text-sm text-slate-300">—</td>
        <td class="border border-slate-200 px-4 py-3 text-center text-sm text-slate-300">—</td>
        <td class="border border-slate-200 px-4 py-3 text-center text-sm">
          <select v-if="canRate" v-model.number="item[criterion.key]" class="border rounded text-xs px-1">
            <option v-for="n in 5" :key="n" :value="n">{{ n }}</option>
          </select>
          <span v-else>{{ item[criterion.key] ?? "—" }}</span>
        </td>
        <td v-if="idx === 0" rowspan="5" :class="TD" class="border border-slate-200 align-top">
          <input v-if="canRate" v-model="item.remarks" class="border rounded px-2 py-1 text-xs w-full" />
          <span v-else>{{ item.remarks ?? "—" }}</span>
        </td>
      </tr>
      <tr>
        <td :class="TD" class="border border-slate-200 font-semibold" colspan="3">Row Average</td>
        <td class="border border-slate-200 px-4 py-3 text-center text-sm text-slate-300">—</td>
        <td class="border border-slate-200 px-4 py-3 text-center text-sm text-slate-300">—</td>
        <td class="border border-slate-200 px-4 py-3 text-center text-sm text-slate-300">—</td>
        <td class="border border-slate-200 px-4 py-3 text-center text-sm font-semibold">
          {{ item.row_average ?? rowAverage(item) }}
          <button v-if="canRate" type="button" class="ml-2 text-xs text-indigo-600" @click="rate(item)">Save Ratings</button>
        </td>
      </tr>
    </template>
    <tr v-if="!items.length">
      <td :class="TD" class="border border-slate-200" colspan="11">No Core Function rows yet — generate targets from Employee Functions.</td>
    </tr>
  </tbody>
</template>
