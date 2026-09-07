<script setup>
import AppTextarea from "@/Components/AppTextarea.vue"
import { TD } from "@/Composables/useTableClasses.js"
import { router } from "@inertiajs/vue3"
import { computed } from "vue"
import { useSubmit } from "@/Composables/useSubmit"
import { groupConsecutiveByFunction } from "@/Composables/ipcrV2FunctionGrouping.js"

const props = defineProps({
  ipcrId: [Number, String],
  items: { type: Array, default: () => [] },
  isOwner: Boolean,
  isMutable: Boolean,
  canRate: { type: Boolean, default: false },
})

const { submit } = useSubmit()

// Rows sharing the same Core Function (one function tagged to N WDPs
// materializes into N items) merge into one rowspan'd Function block —
// isFirst/groupSize drive rendering the label/placeholder cells once.
const rows = computed(() => groupConsecutiveByFunction(props.items))

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
    mov_link: item.mov_link,
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

function rateWdpRow(item) {
  submit((opts) => router.put(route("division-chief-ipcr-v2.rateCoreItem", [props.ipcrId, item.id]), {
    quality_rating: item.quality_rating,
    efficiency_rating: item.efficiency_rating,
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
    <template v-for="row in rows" :key="row.item.id">
      <!-- WDP-tagged row: one independently-ratable row per tagged plan, mirroring Support Functions -->
      <tr v-if="row.item.success_indicator">
        <td v-if="row.isFirst" :rowspan="row.groupSize" :class="TD" class="border border-slate-200 align-top font-medium">
          {{ row.item.label }}<br />
          <small class="text-slate-400">Weight: {{ row.item.weight_percent ?? "—" }}%</small>
        </td>
        <td v-if="row.isFirst" :rowspan="row.groupSize" class="border border-slate-200 px-4 py-3 text-sm text-slate-400 align-top">—</td>
        <td v-if="row.isFirst" :rowspan="row.groupSize" class="border border-slate-200 px-4 py-3 text-sm text-slate-400 align-top">—</td>
        <td :class="TD" class="border border-slate-200 align-top">{{ row.item.success_indicator }}</td>
        <td :class="TD" class="border border-slate-200 align-top">
          <AppTextarea v-if="isOwner && isMutable" v-model="row.item.target" @blur="saveEmployeeFields(row.item)" />
          <span v-else>{{ row.item.target ?? "—" }}</span>
        </td>
        <td :class="TD" class="border border-slate-200 align-top">
          <AppTextarea v-if="isOwner && isMutable" v-model="row.item.actual_accomplishment" @blur="saveEmployeeFields(row.item)" />
          <span v-else>{{ row.item.actual_accomplishment ?? "—" }}</span>
          <input v-if="isOwner && isMutable" v-model="row.item.mov_link" placeholder="MOV link" class="border rounded px-2 py-1 text-xs w-full mt-1" @blur="saveEmployeeFields(row.item)" />
          <small v-else-if="row.item.mov_link" class="block text-slate-400 mt-1">MOV: {{ row.item.mov_link }}</small>
        </td>
        <td class="border border-slate-200 px-4 py-3 text-center text-sm">
          <select v-if="canRate" v-model.number="row.item.quality_rating" class="border rounded text-xs px-1"><option v-for="n in 5" :key="n" :value="n">{{ n }}</option></select>
          <span v-else>{{ row.item.quality_rating ?? "—" }}</span>
        </td>
        <td class="border border-slate-200 px-4 py-3 text-center text-sm">
          <select v-if="canRate" v-model.number="row.item.efficiency_rating" class="border rounded text-xs px-1"><option v-for="n in 5" :key="n" :value="n">{{ n }}</option></select>
          <span v-else>{{ row.item.efficiency_rating ?? "—" }}</span>
        </td>
        <td class="border border-slate-200 px-4 py-3 text-center text-sm">
          <select v-if="canRate" v-model.number="row.item.timeliness_rating" class="border rounded text-xs px-1"><option v-for="n in 5" :key="n" :value="n">{{ n }}</option></select>
          <span v-else>{{ row.item.timeliness_rating ?? "—" }}</span>
        </td>
        <td class="border border-slate-200 px-4 py-3 text-center text-sm font-semibold">
          {{ row.item.row_average ?? "—" }}
          <button v-if="canRate" type="button" class="block mt-1 text-xs text-indigo-600" @click="rateWdpRow(row.item)">Save</button>
        </td>
        <td :class="TD" class="border border-slate-200 align-top">
          <input v-if="canRate" v-model="row.item.remarks" class="border rounded px-2 py-1 text-xs w-full" />
          <span v-else>{{ row.item.remarks ?? "—" }}</span>
        </td>
      </tr>

      <!-- Untagged (teaching-load) row: fixed CSC 4-criteria rubric -->
      <template v-else>
        <tr v-for="(criterion, idx) in CRITERIA" :key="row.item.id + '-' + criterion.key">
          <td v-if="idx === 0" rowspan="5" :class="TD" class="border border-slate-200 align-top font-medium">
            {{ row.item.label }}<br />
            <small class="text-slate-400">Weight: {{ row.item.weight_percent ?? "—" }}%</small>
          </td>
          <td v-if="idx === 0" rowspan="5" class="border border-slate-200 px-4 py-3 text-sm text-slate-400 align-top">—</td>
          <td v-if="idx === 0" rowspan="5" class="border border-slate-200 px-4 py-3 text-sm text-slate-400 align-top">—</td>
          <td :class="TD" class="border border-slate-200">{{ criterion.label }}</td>
          <td v-if="idx === 0" rowspan="4" :class="TD" class="border border-slate-200 align-top">
            <AppTextarea v-if="isOwner && isMutable" v-model="row.item.target" @blur="saveEmployeeFields(row.item)" />
            <span v-else>{{ row.item.target ?? "—" }}</span>
          </td>
          <td v-if="idx === 0" rowspan="4" :class="TD" class="border border-slate-200 align-top">
            <AppTextarea v-if="isOwner && isMutable" v-model="row.item.actual_accomplishment" @blur="saveEmployeeFields(row.item)" />
            <span v-else>{{ row.item.actual_accomplishment ?? "—" }}</span>
            <input v-if="isOwner && isMutable" v-model="row.item.mov_link" placeholder="MOV link" class="border rounded px-2 py-1 text-xs w-full mt-1" @blur="saveEmployeeFields(row.item)" />
            <small v-else-if="row.item.mov_link" class="block text-slate-400 mt-1">MOV: {{ row.item.mov_link }}</small>
          </td>
          <td class="border border-slate-200 px-4 py-3 text-center text-sm text-slate-300">—</td>
          <td class="border border-slate-200 px-4 py-3 text-center text-sm text-slate-300">—</td>
          <td class="border border-slate-200 px-4 py-3 text-center text-sm text-slate-300">—</td>
          <td class="border border-slate-200 px-4 py-3 text-center text-sm">
            <select v-if="canRate" v-model.number="row.item[criterion.key]" class="border rounded text-xs px-1">
              <option v-for="n in 5" :key="n" :value="n">{{ n }}</option>
            </select>
            <span v-else>{{ row.item[criterion.key] ?? "—" }}</span>
          </td>
          <td v-if="idx === 0" rowspan="5" :class="TD" class="border border-slate-200 align-top">
            <input v-if="canRate" v-model="row.item.remarks" class="border rounded px-2 py-1 text-xs w-full" />
            <span v-else>{{ row.item.remarks ?? "—" }}</span>
          </td>
        </tr>
        <tr>
          <td :class="TD" class="border border-slate-200 font-semibold" colspan="3">Row Average</td>
          <td class="border border-slate-200 px-4 py-3 text-center text-sm text-slate-300">—</td>
          <td class="border border-slate-200 px-4 py-3 text-center text-sm text-slate-300">—</td>
          <td class="border border-slate-200 px-4 py-3 text-center text-sm text-slate-300">—</td>
          <td class="border border-slate-200 px-4 py-3 text-center text-sm font-semibold">
            {{ row.item.row_average ?? rowAverage(row.item) }}
            <button v-if="canRate" type="button" class="ml-2 text-xs text-indigo-600" @click="rate(row.item)">Save Ratings</button>
          </td>
        </tr>
      </template>
    </template>
    <tr v-if="!items.length">
      <td :class="TD" class="border border-slate-200" colspan="11">No Core Function rows yet — generate targets from Employee Functions.</td>
    </tr>
  </tbody>
</template>
